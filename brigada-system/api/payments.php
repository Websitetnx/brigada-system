<?php
// Buffer ALL output so stray warnings/notices never corrupt the JSON response
ob_start();

require_once '../config/database.php';

// Discard anything the includes may have printed (warnings, notices, etc.)
ob_clean();

// From here every response MUST go through jsonResponse() in database.php
header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getPayments($db);
        break;
    case 'POST':
        processPayment($db);
        break;
    case 'PUT':
        updatePaymentStatus($db);
        break;
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

// ─── POST: Record a new payment ─────────────────────────────────────────────

function processPayment($db) {
    try {
        // Frontend sends FormData → use $_POST
        $participant_id   = $_POST['participant_id']    ?? null;
        $amount           = $_POST['amount']            ?? 200.00;
        $payment_method   = $_POST['payment_method']   ?? 'Cash';
        $reference_number = $_POST['reference_number'] ?? '';
        $payment_date     = $_POST['payment_date']     ?? date('Y-m-d');
        $notes            = $_POST['notes']            ?? '';
        $attendance_id    = !empty($_POST['attendance_id']) ? $_POST['attendance_id'] : null;

        if (!$participant_id) {
            jsonResponse(['success' => false, 'message' => 'Participant is required'], 400);
        }

        if (!$amount || floatval($amount) <= 0) {
            jsonResponse(['success' => false, 'message' => 'A valid amount is required'], 400);
        }

        // Generate a unique receipt number
        $receipt_number = 'BRIG-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("
            INSERT INTO payments (
                participant_id, amount, payment_date, payment_method,
                reference_number, receipt_number, notes, attendance_id, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Paid', NOW())
        ");

        $stmt->execute([
            $participant_id,
            floatval($amount),
            $payment_date,
            $payment_method,
            $reference_number,
            $receipt_number,
            $notes,
            $attendance_id
        ]);

        $payment_id = $db->lastInsertId();

        jsonResponse([
            'success'        => true,
            'message'        => 'Payment recorded successfully',
            'receipt_number' => $receipt_number,
            'payment_id'     => $payment_id
        ]);

    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// ─── GET: List / search payments ────────────────────────────────────────────

function getPayments($db) {
    try {
        // Single record fetch by ID
        if (!empty($_GET['id'])) {
            $stmt = $db->prepare("
                SELECT pay.*, CONCAT(p.first_name, ' ', p.last_name) AS participant_name, p.role
                FROM payments pay
                JOIN participants p ON pay.participant_id = p.id
                WHERE pay.id = ?
            ");
            $stmt->execute([$_GET['id']]);
            jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        // Build WHERE clause
        $where  = [];
        $params = [];

        $date_range = $_GET['date_range'] ?? '';
        $date_from  = $_GET['date_from']  ?? '';
        $date_to    = $_GET['date_to']    ?? '';

        if ($date_from && $date_to) {
            $where[]  = "DATE(pay.payment_date) BETWEEN ? AND ?";
            $params[] = $date_from;
            $params[] = $date_to;
        } elseif ($date_range) {
            switch ($date_range) {
                case 'today':
                    $where[] = "DATE(pay.payment_date) = CURDATE()";
                    break;
                case 'week':
                    $where[] = "YEARWEEK(pay.payment_date, 1) = YEARWEEK(CURDATE(), 1)";
                    break;
                case 'month':
                    $where[] = "YEAR(pay.payment_date) = YEAR(CURDATE()) AND MONTH(pay.payment_date) = MONTH(CURDATE())";
                    break;
                case 'quarter':
                    $where[] = "QUARTER(pay.payment_date) = QUARTER(CURDATE()) AND YEAR(pay.payment_date) = YEAR(CURDATE())";
                    break;
                case 'year':
                    $where[] = "YEAR(pay.payment_date) = YEAR(CURDATE())";
                    break;
                // 'all' → no date filter
            }
        } else {
            // Default: current month
            $where[] = "YEAR(pay.payment_date) = YEAR(CURDATE()) AND MONTH(pay.payment_date) = MONTH(CURDATE())";
        }

        if (!empty($_GET['status'])) {
            $where[]  = "pay.status = ?";
            $params[] = $_GET['status'];
        }

        if (!empty($_GET['payment_method'])) {
            $where[]  = "pay.payment_method = ?";
            $params[] = $_GET['payment_method'];
        }

        if (!empty($_GET['search'])) {
            $search   = '%' . $_GET['search'] . '%';
            $where[]  = "(CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR pay.receipt_number LIKE ? OR pay.reference_number LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $whereSQL = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total count
        $countStmt = $db->prepare("
            SELECT COUNT(*)
            FROM payments pay
            JOIN participants p ON pay.participant_id = p.id
            $whereSQL
        ");
        $countStmt->execute($params);
        $total_records = (int) $countStmt->fetchColumn();

        // Pagination
        $per_page    = max(1, (int) ($_GET['per_page'] ?? 25));
        $page        = max(1, (int) ($_GET['page']     ?? 1));
        $total_pages = max(1, (int) ceil($total_records / $per_page));
        $offset      = ($page - 1) * $per_page;

        $dataStmt = $db->prepare("
            SELECT
                pay.*,
                CONCAT(p.first_name, ' ', p.last_name) AS participant_name,
                p.role
            FROM payments pay
            JOIN participants p ON pay.participant_id = p.id
            $whereSQL
            ORDER BY pay.created_at DESC
            LIMIT $per_page OFFSET $offset
        ");
        $dataStmt->execute($params);
        $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse([
            'data'          => $data,
            'total_records' => $total_records,
            'total_pages'   => $total_pages,
            'current_page'  => $page,
            'per_page'      => $per_page
        ]);

    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// ─── PUT: Update payment status ──────────────────────────────────────────────

function updatePaymentStatus($db) {
    try {
        $data   = json_decode(file_get_contents('php://input'), true);
        $id     = $data['id']     ?? null;
        $status = $data['status'] ?? null;

        if (!$id || !$status) {
            jsonResponse(['success' => false, 'message' => 'Missing ID or status'], 400);
        }

        $allowed = ['Paid', 'Pending', 'Waived', 'Cancelled'];
        if (!in_array($status, $allowed)) {
            jsonResponse(['success' => false, 'message' => 'Invalid status value'], 400);
        }

        $stmt = $db->prepare("UPDATE payments SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(['success' => false, 'message' => 'Payment not found or no change made'], 404);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Payment status updated successfully'
        ]);

    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
?>