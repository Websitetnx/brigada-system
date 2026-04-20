<?php
// api/test.php
header('Content-Type: application/json');

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Test database connection
    $stmt = $db->query("SELECT COUNT(*) as count FROM participants");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'API is working',
        'participant_count' => $result['count'],
        'php_version' => phpversion()
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>