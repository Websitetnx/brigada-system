<?php
// api/notifications.php
session_start();
header('Content-Type: application/json');

// Optional: Uncomment if you want to restrict to logged-in users only
// if (!isset($_SESSION['user_id'])) {
//     http_response_code(401);
//     echo json_encode(['error' => 'Unauthorized']);
//     exit;
// }

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$notifications = [];

try {
    // 1. Get pending penalty payments
    $stmt = $db->prepare("
        SELECT 
            'penalty' as type,
            CONCAT(p.first_name, ' ', p.last_name, ' has a pending penalty of ₱', pay.amount) as message,
            pay.created_at as date,
            pay.id as reference_id
        FROM payments pay
        JOIN participants p ON pay.participant_id = p.id
        WHERE pay.status = 'Pending'
        ORDER BY pay.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $penalties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $notifications = array_merge($notifications, $penalties);

    // 2. Get participants currently signed in (present)
    $stmt = $db->prepare("
        SELECT 
            'presence' as type,
            CONCAT(COUNT(*), ' participant(s) currently signed in') as message,
            NOW() as date,
            0 as reference_id
        FROM attendance 
        WHERE date = CURDATE() AND sign_in_time IS NOT NULL AND sign_out_time IS NULL
    ");
    $stmt->execute();
    $presence = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($presence && $presence['message'] != '0 participant(s) currently signed in') {
        $notifications[] = $presence;
    }

    // 3. Get today's attendance summary
    $stmt = $db->prepare("
        SELECT 
            'summary' as type,
            CONCAT('Today: ', COUNT(*), ' check-ins, ', 
                   SUM(CASE WHEN status = 'Complete' THEN 1 ELSE 0 END), ' completed') as message,
            NOW() as date,
            0 as reference_id
        FROM attendance 
        WHERE date = CURDATE()
    ");
    $stmt->execute();
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($summary) {
        $notifications[] = $summary;
    }

    // 4. Get unread announcements (if you have an announcements table)
    $stmt = $db->prepare("
        SELECT 
            'announcement' as type,
            title as message,
            created_at as date,
            id as reference_id
        FROM announcements
        WHERE expires_at >= CURDATE()
        ORDER BY 
            CASE priority WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 ELSE 3 END,
            created_at DESC
        LIMIT 3
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $notifications = array_merge($notifications, $announcements);

    // 5. Sort by date (most recent first)
    usort($notifications, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    // Limit total notifications
    $notifications = array_slice($notifications, 0, 10);

    echo json_encode($notifications);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Failed to fetch notifications: ' . $e->getMessage()
    ]);
}
?>