<?php
require_once '../config/database.php';
header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET': getAttendance($db); break;
    case 'POST': processSignIn($db); break;
    case 'PUT': processSignOut($db); break;
    default: echo json_encode(['error'=>'Method not allowed']);
}

function getAttendance($db) {
    $where  = [];
    $params = [];

    // Filter by participant
    if (!empty($_GET['participant_id'])) {
        $where[]  = "a.participant_id = ?";
        $params[] = $_GET['participant_id'];
    }

    // Filter by status
    if (!empty($_GET['status'])) {
        $where[]  = "a.status = ?";
        $params[] = $_GET['status'];
    }

    // Filter by date (only when no participant_id provided, to keep legacy behaviour)
    if (empty($_GET['participant_id'])) {
        $date     = $_GET['date'] ?? date('Y-m-d');
        $where[]  = "a.date = ?";
        $params[] = $date;
    }

    $whereSQL = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare("
        SELECT a.*, CONCAT(p.first_name,' ',p.last_name) AS participant_name, p.role, p.grade_section
        FROM attendance a
        JOIN participants p ON a.participant_id = p.id
        $whereSQL
        ORDER BY a.date DESC, a.sign_in_time DESC
    ");
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function processSignIn($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;
    $participantId = $input['participant_id'] ?? $input['participant_identifier'] ?? null;
    if (!$participantId) { echo json_encode(['success'=>false,'message'=>'Participant ID required']); return; }

    // Find participant by ID or QR code
    $stmt = $db->prepare("SELECT id, first_name, last_name FROM participants WHERE id=? OR qr_code=?");
    $stmt->execute([$participantId, $participantId]);
    $participant = $stmt->fetch();
    if (!$participant) { echo json_encode(['success'=>false,'message'=>'Participant not found']); return; }

    $today = date('Y-m-d');
    // Check existing attendance for today
    $check = $db->prepare("SELECT id, sign_in_time, sign_out_time FROM attendance WHERE participant_id=? AND date=?");
    $check->execute([$participant['id'], $today]);
    $att = $check->fetch();

    if (!$att) {
        // Sign In
        $signInTime = $input['sign_in_time'] ?? date('Y-m-d H:i:s');
        $stmt = $db->prepare("INSERT INTO attendance (participant_id, date, sign_in_time, status, notes) VALUES (?,?,?, 'Incomplete', ?)");
        $stmt->execute([$participant['id'], $today, $signInTime, $input['notes']??null]);
        echo json_encode(['success'=>true, 'message'=>'Sign In successful', 'participant_name'=>$participant['first_name'].' '.$participant['last_name'], 'action'=>'sign_in']);
    } elseif ($att['sign_in_time'] && !$att['sign_out_time']) {
        // Already signed in but not out - process sign out
        $attendanceId = $att['id'];
        $signOutTime = date('Y-m-d H:i:s');
        $minutes = round((strtotime($signOutTime) - strtotime($att['sign_in_time'])) / 60);
        $status = $minutes >= 120 ? 'Complete' : 'Incomplete';
        $penalty = $minutes < 120;

        $update = $db->prepare("UPDATE attendance SET sign_out_time=?, total_minutes=?, status=?, penalty_applied=? WHERE id=?");
        $update->execute([$signOutTime, $minutes, $status, $penalty, $attendanceId]);

        if ($penalty) {
            $checkPay = $db->prepare("SELECT id FROM payments WHERE attendance_id=?");
            $checkPay->execute([$attendanceId]);
            if (!$checkPay->fetch()) {
                $receipt = 'PEN-'.date('Ymd').'-'.str_pad($attendanceId,4,'0',STR_PAD_LEFT);
                $pay = $db->prepare("INSERT INTO payments (participant_id, attendance_id, amount, payment_date, status, notes, receipt_number) VALUES (?,?,200,CURDATE(),'Pending','Auto penalty: <120 min',?)");
                $pay->execute([$participant['id'], $attendanceId, $receipt]);
            }
        }

        $hours = floor($minutes/60); $mins = $minutes%60;
        echo json_encode([
            'success'=>true,
            'message'=>'Sign Out successful',
            'action'=>'sign_out',
            'participant_name'=>$participant['first_name'].' '.$participant['last_name'],
            'duration'=>"{$hours}h {$mins}m",
            'status'=>$status,
            'penalty_applied'=>$penalty
        ]);
    } else {
        echo json_encode(['success'=>false, 'message'=>'Already signed out for today']);
    }
}

function processSignOut($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $attendanceId = $input['attendance_id'] ?? null;
    if (!$attendanceId) { echo json_encode(['success'=>false,'message'=>'Attendance ID required']); return; }

    // Get attendance record
    $stmt = $db->prepare("SELECT a.*, p.first_name, p.last_name FROM attendance a JOIN participants p ON a.participant_id=p.id WHERE a.id=?");
    $stmt->execute([$attendanceId]);
    $att = $stmt->fetch();
    if (!$att) { echo json_encode(['success'=>false,'message'=>'Record not found']); return; }
    if ($att['sign_out_time']) { echo json_encode(['success'=>false,'message'=>'Already signed out']); return; }

    $signOutTime = date('Y-m-d H:i:s');
    $minutes = round((strtotime($signOutTime) - strtotime($att['sign_in_time'])) / 60);
    $status = $minutes >= 120 ? 'Complete' : 'Incomplete';
    $penalty = $minutes < 120;

    $update = $db->prepare("UPDATE attendance SET sign_out_time=?, total_minutes=?, status=?, penalty_applied=? WHERE id=?");
    $update->execute([$signOutTime, $minutes, $status, $penalty, $attendanceId]);

    if ($penalty) {
        // Insert penalty payment if not already present
        $checkPay = $db->prepare("SELECT id FROM payments WHERE attendance_id=?");
        $checkPay->execute([$attendanceId]);
        if (!$checkPay->fetch()) {
            $receipt = 'PEN-'.date('Ymd').'-'.str_pad($attendanceId,4,'0',STR_PAD_LEFT);
            $pay = $db->prepare("INSERT INTO payments (participant_id, attendance_id, amount, payment_date, status, notes, receipt_number) VALUES (?,?,200,CURDATE(),'Pending','Auto penalty: <120 min',?)");
            $pay->execute([$att['participant_id'], $attendanceId, $receipt]);
        }
    }

    $hours = floor($minutes/60); $mins = $minutes%60;
    echo json_encode([
        'success'=>true,
        'message'=>'Sign Out successful',
        'participant_name'=>$att['first_name'].' '.$att['last_name'],
        'duration'=>"{$hours}h {$mins}m",
        'status'=>$status,
        'penalty_applied'=>$penalty
    ]);
}
?>