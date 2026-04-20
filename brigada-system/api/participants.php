<?php
// api/participants.php
require_once '../config/database.php';
header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        // Get participant(s) - supports ?id= for single, or filters
        getParticipants($db);
        break;
    case 'POST':
        // Check for _method field to simulate PUT
        $input = $_POST;
        if (isset($input['_method']) && $input['_method'] === 'PUT') {
            updateParticipant($db);
        } else {
            addParticipant($db);
        }
        break;
    case 'PUT':
        updateParticipant($db);
        break;
    case 'DELETE':
        deleteParticipant($db);
        break;
    default:
        echo json_encode(['error' => 'Method not allowed']);
}

function getParticipants($db) {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmt = $db->prepare("SELECT * FROM participants WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $role = $_GET['role'] ?? '';
        $status = $_GET['status'] ?? 'Active';
        $search = $_GET['search'] ?? '';
        $query = "SELECT p.*, 
                  COALESCE(SUM(a.total_minutes),0) as total_minutes_served,
                  COUNT(DISTINCT a.date) as days_attended,
                  COALESCE(SUM(CASE WHEN pay.status='Pending' THEN pay.amount ELSE 0 END),0) as pending_penalties
                  FROM participants p
                  LEFT JOIN attendance a ON p.id = a.participant_id
                  LEFT JOIN payments pay ON p.id = pay.participant_id
                  WHERE p.status = ?";
        $params = [$status];
        if ($role) { $query .= " AND p.role = ?"; $params[] = $role; }
        if ($search) { $query .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.email LIKE ?)"; 
                       $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
        $query .= " GROUP BY p.id ORDER BY p.created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode($data);
}

function addParticipant($db) {
    $first = $_POST['first_name'] ?? '';
    $last = $_POST['last_name'] ?? '';
    // Check duplicate
    $check = $db->prepare("SELECT id FROM participants WHERE first_name = ? AND last_name = ?");
    $check->execute([$first, $last]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Participant already exists']);
        return;
    }
    // Insert
    $qr = uniqid('BRIG_');
    $stmt = $db->prepare("INSERT INTO participants (qr_code, first_name, last_name, role, grade_section, contact_number, email, guardian_name, guardian_contact) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$qr, $first, $last, $_POST['role']??'Volunteer', $_POST['grade_section']??null, $_POST['contact_number']??null, $_POST['email']??null, $_POST['guardian_name']??null, $_POST['guardian_contact']??null]);
    $id = $db->lastInsertId();
    echo json_encode(['success' => true, 'id' => $id, 'qr_code' => $qr]);
}

function updateParticipant($db) {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = $input['id'] ?? null;
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID required']); return; }
    // Duplicate check excluding self
    $check = $db->prepare("SELECT id FROM participants WHERE first_name = ? AND last_name = ? AND id != ?");
    $check->execute([$input['first_name'], $input['last_name'], $id]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Another participant with this name exists']);
        return;
    }
    $stmt = $db->prepare("UPDATE participants SET first_name=?, last_name=?, role=?, grade_section=?, contact_number=?, email=?, guardian_name=?, guardian_contact=?, status=? WHERE id=?");
    $stmt->execute([$input['first_name'], $input['last_name'], $input['role'], $input['grade_section']??null, $input['contact_number']??null, $input['email']??null, $input['guardian_name']??null, $input['guardian_contact']??null, $input['status']??'Active', $id]);
    echo json_encode(['success' => true]);
}

function deleteParticipant($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    if (!$id) { echo json_encode(['success' => false]); return; }
    $stmt = $db->prepare("DELETE FROM participants WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
}
?>