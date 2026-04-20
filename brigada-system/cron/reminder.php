<?php
require_once '../config/database.php';

// This script should be run via cron job daily
$database = new Database();
$db = $database->getConnection();

// Find participants with pending penalties
$stmt = $db->prepare("
    SELECT DISTINCT
        p.id,
        p.first_name,
        p.last_name,
        p.email,
        p.contact_number,
        SUM(pay.amount) as total_due,
        COUNT(pay.id) as penalty_count
    FROM participants p
    JOIN payments pay ON p.id = pay.participant_id
    WHERE pay.status = 'Pending'
        AND pay.created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)
    GROUP BY p.id
    HAVING total_due > 0
");

$stmt->execute();
$pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($pending as $participant) {
    // Send email reminder if email exists
    if ($participant['email']) {
        $to = $participant['email'];
        $subject = "Brigada Eskwela - Payment Reminder";
        $message = "Dear {$participant['first_name']},\n\n";
        $message .= "This is a reminder that you have pending penalties totaling ₱" . number_format($participant['total_due'], 2) . ".\n";
        $message .= "Please settle your payment at your earliest convenience.\n\n";
        $message .= "Thank you for your cooperation.\n";
        $message .= "Brigada Eskwela Committee";
        
        mail($to, $subject, $message);
    }
    
    // Log reminder sent
    $log = $db->prepare("
        INSERT INTO activity_logs (action, details) 
        VALUES ('REMINDER_SENT', ?)
    ");
    $log->execute([json_encode([
        'participant_id' => $participant['id'],
        'amount_due' => $participant['total_due']
    ])]);
}

echo "Reminders processed: " . count($pending) . "\n";
?>