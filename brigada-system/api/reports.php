<?php
// api/reports.php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$report_type = $_GET['type'] ?? 'summary';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

try {
    switch($report_type) {
        case 'summary':
            getSummaryReport($db, $date_from, $date_to);
            break;
        case 'attendance':
            getAttendanceReport($db, $date_from, $date_to);
            break;
        case 'payments':
            getPaymentReport($db, $date_from, $date_to);
            break;
        case 'participants':
            getParticipantReport($db);
            break;
        case 'penalties':
            getPenaltyReport($db, $date_from, $date_to);
            break;
        case 'participant_detail':
            getParticipantDetail($db, $_GET['participant_id'] ?? 0);
            break;
        case 'export':
            exportData($db);
            break;
        default:
            getSummaryReport($db, $date_from, $date_to);
    }
} catch(Exception $e) {
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

// =============================================
// SUMMARY REPORT - SIMPLIFIED
// =============================================
function getSummaryReport($db, $date_from, $date_to) {
    $data = [];
    
    // Get basic counts
    $data['total_participants'] = 0;
    $data['active_participants'] = 0;
    $data['total_hours_served'] = 0;
    $data['completed_sessions'] = 0;
    $data['total_sessions'] = 0;
    $data['penalties_applied'] = 0;
    $data['total_penalties'] = 0;
    $data['total_collections'] = 0;
    $data['pending_collections'] = 0;
    
    // Total participants
    $result = $db->query("SELECT COUNT(*) as cnt FROM participants");
    if ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data['total_participants'] = (int)$row['cnt'];
    }
    
    // Active participants
    $result = $db->query("SELECT COUNT(*) as cnt FROM participants WHERE status = 'Active'");
    if ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data['active_participants'] = (int)$row['cnt'];
    }
    
    // Total minutes served
    $stmt = $db->prepare("SELECT COALESCE(SUM(total_minutes), 0) as mins FROM attendance WHERE date BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data['total_hours_served'] = round($row['mins'] / 60, 1);
    }
    
    // Completed sessions
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM attendance WHERE date BETWEEN ? AND ? AND status = 'Complete'");
    $stmt->execute([$date_from, $date_to]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data['completed_sessions'] = (int)$row['cnt'];
    }
    
    // Total sessions
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM attendance WHERE date BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data['total_sessions'] = (int)$row['cnt'];
    }
    
    // Penalties
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM attendance WHERE date BETWEEN ? AND ? AND penalty_applied = TRUE");
    $stmt->execute([$date_from, $date_to]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data['penalties_applied'] = (int)$row['cnt'];
        $data['total_penalties'] = $data['penalties_applied'] * 200;
    }
    
    // Collections
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'Paid' AND payment_date BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data['total_collections'] = (float)$row['total'];
    }
    
    // Pending collections
    $result = $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'Pending'");
    if ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data['pending_collections'] = (float)$row['total'];
    }
    
    // Daily breakdown
    $data['daily_breakdown'] = [];
    $stmt = $db->prepare("
        SELECT 
            a.date,
            COUNT(DISTINCT a.participant_id) as participants,
            COUNT(*) as sessions,
            COALESCE(SUM(a.total_minutes), 0) as minutes_served,
            SUM(CASE WHEN a.status = 'Complete' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN a.penalty_applied = TRUE THEN 1 ELSE 0 END) as penalties,
            0 as collections
        FROM attendance a
        WHERE a.date BETWEEN ? AND ?
        GROUP BY a.date
        ORDER BY a.date ASC
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['daily_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Role distribution
    $data['role_distribution'] = [];
    $result = $db->query("SELECT role, COUNT(*) as count FROM participants WHERE status = 'Active' GROUP BY role");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data['role_distribution'][$row['role']] = (int)$row['count'];
    }
    
    // Status breakdown
    $data['status_breakdown'] = ['Complete' => 0, 'Incomplete' => 0];
    $stmt = $db->prepare("
        SELECT 
            SUM(CASE WHEN status = 'Complete' THEN 1 ELSE 0 END) as Complete,
            SUM(CASE WHEN status = 'Incomplete' THEN 1 ELSE 0 END) as Incomplete
        FROM attendance 
        WHERE date BETWEEN ? AND ?
    ");
    $stmt->execute([$date_from, $date_to]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data['status_breakdown']['Complete'] = (int)($row['Complete'] ?? 0);
        $data['status_breakdown']['Incomplete'] = (int)($row['Incomplete'] ?? 0);
    }
    
    // Participant summary
    $data['participant_summary'] = [];
    $data['top_performers'] = [];
    
    $stmt = $db->prepare("
        SELECT 
            p.id,
            CONCAT(p.first_name, ' ', p.last_name) as name,
            p.role,
            p.status,
            COUNT(DISTINCT a.date) as days_attended,
            COALESCE(SUM(a.total_minutes), 0) as total_minutes,
            ROUND(COALESCE(SUM(a.total_minutes), 0) / 60, 1) as total_hours,
            SUM(CASE WHEN a.status = 'Complete' THEN 1 ELSE 0 END) as completed_sessions,
            SUM(CASE WHEN a.penalty_applied = TRUE THEN 1 ELSE 0 END) as penalties_count,
            COALESCE(SUM(CASE WHEN a.penalty_applied = TRUE THEN 200 ELSE 0 END), 0) as penalty_amount,
            COALESCE((SELECT SUM(amount) FROM payments pay WHERE pay.participant_id = p.id AND pay.status = 'Paid'), 0) as total_paid
        FROM participants p
        LEFT JOIN attendance a ON p.id = a.participant_id AND a.date BETWEEN ? AND ?
        GROUP BY p.id, p.first_name, p.last_name, p.role, p.status
        ORDER BY total_minutes DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['days_attended'] = (int)$row['days_attended'];
        $row['total_minutes'] = (int)$row['total_minutes'];
        $row['total_hours'] = (float)$row['total_hours'];
        $row['completed_sessions'] = (int)$row['completed_sessions'];
        $row['penalties_count'] = (int)$row['penalties_count'];
        $row['penalty_amount'] = (float)$row['penalty_amount'];
        $row['total_paid'] = (float)$row['total_paid'];
        $row['balance'] = $row['penalty_amount'] - $row['total_paid'];
        
        $data['participant_summary'][] = $row;
    }
    
    // Top performers (first 10)
    $data['top_performers'] = array_slice($data['participant_summary'], 0, 10);
    
    echo json_encode($data);
}

// =============================================
// ATTENDANCE REPORT
// =============================================
function getAttendanceReport($db, $date_from, $date_to) {
    $data = [];
    $data['total_checkins'] = 0;
    $data['unique_participants'] = 0;
    $data['total_sessions'] = 0;
    $data['completed_sessions'] = 0;
    $data['avg_minutes'] = 0;
    $data['penalties'] = 0;
    $data['completion_rate'] = 0;
    $data['penalty_rate'] = 0;
    $data['daily_breakdown'] = [];
    $data['attendance_records'] = [];
    
    // Get stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_checkins,
            COUNT(DISTINCT participant_id) as unique_participants,
            SUM(CASE WHEN status = 'Complete' THEN 1 ELSE 0 END) as completed_sessions,
            AVG(total_minutes) as avg_minutes,
            SUM(CASE WHEN penalty_applied = TRUE THEN 1 ELSE 0 END) as penalties
        FROM attendance 
        WHERE date BETWEEN ? AND ?
    ");
    $stmt->execute([$date_from, $date_to]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data['total_checkins'] = (int)$row['total_checkins'];
        $data['total_sessions'] = (int)$row['total_checkins'];
        $data['unique_participants'] = (int)$row['unique_participants'];
        $data['completed_sessions'] = (int)$row['completed_sessions'];
        $data['avg_minutes'] = round($row['avg_minutes'] ?? 0);
        $data['penalties'] = (int)$row['penalties'];
    }
    
    if ($data['total_sessions'] > 0) {
        $data['completion_rate'] = round(($data['completed_sessions'] / $data['total_sessions']) * 100, 1);
        $data['penalty_rate'] = round(($data['penalties'] / $data['total_sessions']) * 100, 1);
    }
    
    // Daily breakdown
    $stmt = $db->prepare("
        SELECT 
            date,
            COUNT(*) as participants,
            COUNT(*) as sessions,
            COALESCE(SUM(total_minutes), 0) as minutes_served,
            SUM(CASE WHEN status = 'Complete' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN penalty_applied = TRUE THEN 1 ELSE 0 END) as penalties
        FROM attendance
        WHERE date BETWEEN ? AND ?
        GROUP BY date
        ORDER BY date ASC
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['daily_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Attendance records
    $stmt = $db->prepare("
        SELECT 
            a.date,
            a.sign_in_time,
            a.sign_out_time,
            a.total_minutes,
            a.status,
            a.penalty_applied,
            CONCAT(p.first_name, ' ', p.last_name) as participant_name,
            p.role
        FROM attendance a
        JOIN participants p ON a.participant_id = p.id
        WHERE a.date BETWEEN ? AND ?
        ORDER BY a.date DESC, a.sign_in_time DESC
        LIMIT 100
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['attendance_records'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($data);
}

// =============================================
// PAYMENT REPORT
// =============================================
function getPaymentReport($db, $date_from, $date_to) {
    $data = [];
    $data['total_transactions'] = 0;
    $data['total_collected'] = 0;
    $data['pending_amount'] = 0;
    $data['pending_count'] = 0;
    $data['avg_transaction'] = 0;
    $data['collection_rate'] = 0;
    $data['daily_breakdown'] = [];
    $data['payments'] = [];
    
    // Stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_transactions,
            COALESCE(SUM(CASE WHEN status = 'Paid' THEN amount ELSE 0 END), 0) as total_collected,
            COALESCE(SUM(CASE WHEN status = 'Pending' THEN amount ELSE 0 END), 0) as pending_amount,
            COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_count,
            AVG(CASE WHEN status = 'Paid' THEN amount END) as avg_transaction
        FROM payments
        WHERE payment_date BETWEEN ? AND ?
    ");
    $stmt->execute([$date_from, $date_to]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data['total_transactions'] = (int)$row['total_transactions'];
        $data['total_collected'] = (float)$row['total_collected'];
        $data['pending_amount'] = (float)$row['pending_amount'];
        $data['pending_count'] = (int)$row['pending_count'];
        $data['avg_transaction'] = round($row['avg_transaction'] ?? 0, 2);
    }
    
    // Daily breakdown
    $stmt = $db->prepare("
        SELECT 
            payment_date as date,
            COUNT(*) as transactions,
            COALESCE(SUM(CASE WHEN status = 'Paid' THEN amount ELSE 0 END), 0) as collections,
            COALESCE(SUM(CASE WHEN status = 'Pending' THEN amount ELSE 0 END), 0) as pending,
            0 as participants,
            0 as completed,
            0 as penalties
        FROM payments
        WHERE payment_date BETWEEN ? AND ?
        GROUP BY payment_date
        ORDER BY payment_date ASC
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['daily_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Payment records
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.receipt_number,
            p.payment_date,
            p.amount,
            p.payment_method,
            p.reference_number,
            p.status,
            CONCAT(part.first_name, ' ', part.last_name) as participant_name,
            part.role
        FROM payments p
        JOIN participants part ON p.participant_id = part.id
        WHERE p.payment_date BETWEEN ? AND ?
        ORDER BY p.payment_date DESC
        LIMIT 100
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($data);
}

// =============================================
// PARTICIPANT REPORT
// =============================================
function getParticipantReport($db) {
    $data = [];
    
    $stmt = $db->query("
        SELECT 
            p.id,
            CONCAT(p.first_name, ' ', p.last_name) as name,
            p.role,
            p.grade_section,
            p.status,
            COUNT(DISTINCT a.date) as days_attended,
            COALESCE(SUM(a.total_minutes), 0) as total_minutes,
            ROUND(COALESCE(SUM(a.total_minutes), 0) / 60, 1) as total_hours,
            SUM(CASE WHEN a.status = 'Complete' THEN 1 ELSE 0 END) as completed_sessions,
            SUM(CASE WHEN a.penalty_applied = TRUE THEN 1 ELSE 0 END) as penalties_count,
            COALESCE(SUM(CASE WHEN a.penalty_applied = TRUE THEN 200 ELSE 0 END), 0) as penalty_amount,
            COALESCE((SELECT SUM(amount) FROM payments pay WHERE pay.participant_id = p.id AND pay.status = 'Paid'), 0) as total_paid
        FROM participants p
        LEFT JOIN attendance a ON p.id = a.participant_id
        GROUP BY p.id
        ORDER BY total_minutes DESC
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['balance'] = $row['penalty_amount'] - $row['total_paid'];
        $data[] = $row;
    }
    
    echo json_encode($data);
}

// =============================================
// PENALTY REPORT
// =============================================
function getPenaltyReport($db, $date_from, $date_to) {
    $data = [];
    $data['penalties'] = [];
    $data['total_penalties'] = 0;
    $data['total_amount'] = 0;
    $data['paid_count'] = 0;
    $data['paid_amount'] = 0;
    $data['unpaid_count'] = 0;
    $data['unpaid_amount'] = 0;
    
    $stmt = $db->prepare("
        SELECT 
            a.id,
            a.date,
            a.total_minutes as minutes_served,
            CONCAT(p.first_name, ' ', p.last_name) as participant_name,
            p.role,
            (SELECT status FROM payments pay WHERE pay.attendance_id = a.id LIMIT 1) as payment_status
        FROM attendance a
        JOIN participants p ON a.participant_id = p.id
        WHERE a.date BETWEEN ? AND ? AND a.penalty_applied = TRUE
        ORDER BY a.date DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    $data['penalties'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data['total_penalties'] = count($data['penalties']);
    $data['total_amount'] = $data['total_penalties'] * 200;
    
    foreach ($data['penalties'] as $p) {
        if ($p['payment_status'] === 'Paid') {
            $data['paid_count']++;
        } else {
            $data['unpaid_count']++;
        }
    }
    
    $data['paid_amount'] = $data['paid_count'] * 200;
    $data['unpaid_amount'] = $data['unpaid_count'] * 200;
    
    echo json_encode($data);
}

// =============================================
// PARTICIPANT DETAIL
// =============================================
function getParticipantDetail($db, $participant_id) {
    $stmt = $db->prepare("
        SELECT 
            p.*,
            COUNT(DISTINCT a.date) as days_attended,
            COALESCE(SUM(a.total_minutes), 0) as total_minutes_served,
            ROUND(COALESCE(SUM(a.total_minutes), 0) / 60, 2) as total_hours_served,
            SUM(CASE WHEN a.status = 'Complete' THEN 1 ELSE 0 END) as completed_sessions,
            SUM(CASE WHEN a.status = 'Incomplete' THEN 1 ELSE 0 END) as incomplete_sessions,
            SUM(CASE WHEN a.penalty_applied = TRUE THEN 1 ELSE 0 END) as penalties_count,
            COALESCE(SUM(CASE WHEN a.penalty_applied = TRUE THEN 200 ELSE 0 END), 0) as total_penalty_amount,
            COALESCE((SELECT SUM(amount) FROM payments pay WHERE pay.participant_id = p.id AND pay.status = 'Paid'), 0) as total_paid
        FROM participants p
        LEFT JOIN attendance a ON p.id = a.participant_id
        WHERE p.id = ?
        GROUP BY p.id
    ");
    $stmt->execute([$participant_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) {
        echo json_encode(['error' => 'Participant not found']);
        return;
    }
    
    $data['balance'] = $data['total_penalty_amount'] - $data['total_paid'];
    
    // Attendance history
    $stmt = $db->prepare("
        SELECT date, sign_in_time, sign_out_time, total_minutes, status, penalty_applied
        FROM attendance
        WHERE participant_id = ?
        ORDER BY date DESC
        LIMIT 30
    ");
    $stmt->execute([$participant_id]);
    $data['attendance_history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Payment history
    $stmt = $db->prepare("
        SELECT payment_date, amount, payment_method, receipt_number, status, notes
        FROM payments
        WHERE participant_id = ?
        ORDER BY payment_date DESC
    ");
    $stmt->execute([$participant_id]);
    $data['payment_history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($data);
}

// =============================================
// EXPORT DATA
// =============================================
function exportData($db) {
    $export_type = $_GET['export_type'] ?? 'participants';
    $date_from = $_GET['date_from'] ?? date('Y-m-01');
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    
    $filename = "brigada_{$export_type}_" . date('Y-m-d') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    if ($export_type == 'participants') {
        $stmt = $db->query("
            SELECT 
                CONCAT(first_name, ' ', last_name) as Name,
                role as Role,
                grade_section as Section,
                status as Status,
                (SELECT COUNT(*) FROM attendance WHERE participant_id = participants.id) as Days,
                (SELECT COALESCE(SUM(total_minutes), 0) FROM attendance WHERE participant_id = participants.id) as Minutes
            FROM participants
            ORDER BY last_name
        ");
    } else {
        $stmt = $db->query("SELECT * FROM participants LIMIT 1");
    }
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        foreach($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}
?>