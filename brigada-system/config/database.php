<?php
// config/database.php

// Default timezone – will be overridden below once DB is available
date_default_timezone_set('Asia/Manila');

// Include the simple QR code class (suppress warnings so they don't corrupt JSON)
@include_once __DIR__ . '/../lib/qrcode.php';

class Database {
    private $host     = "localhost";
    private $db_name  = "brigada_db";
    private $username = "root";
    private $password = "";
    public  $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );

            // Sync MySQL session timezone with PHP timezone
            $this->applyTimezone();

        } catch (PDOException $e) {
            die("Connection error: " . $e->getMessage() .
                "\n\nPlease ensure MySQL server is running and database credentials are correct.");
        }
        return $this->conn;
    }

    /**
     * Reads the timezone from the settings table (if it exists) and applies it
     * to both PHP and the current MySQL session so CURDATE(), NOW(), etc. are correct.
     */
    private function applyTimezone() {
        try {
            $stmt = $this->conn->query("SELECT setting_value FROM settings WHERE setting_key = 'timezone' LIMIT 1");
            $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            $tz   = ($row && !empty($row['setting_value'])) ? $row['setting_value'] : 'Asia/Manila';
        } catch (Exception $e) {
            // settings table doesn't exist yet – use default
            $tz = 'Asia/Manila';
        }

        // Apply to PHP
        date_default_timezone_set($tz);

        // Apply to MySQL session (convert PHP tz offset to ±HH:MM)
        try {
            $offset  = (new DateTime('now', new DateTimeZone($tz)))->getOffset();
            $sign    = $offset >= 0 ? '+' : '-';
            $absOff  = abs($offset);
            $hh      = str_pad(floor($absOff / 3600), 2, '0', STR_PAD_LEFT);
            $mm      = str_pad(($absOff % 3600) / 60, 2, '0', STR_PAD_LEFT);
            $this->conn->exec("SET time_zone = '{$sign}{$hh}:{$mm}'");
        } catch (Exception $e) {
            // Ignore – MySQL may not support named timezones without tz tables loaded
        }
    }
}

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Helper function for JSON responses
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// QR Code Generator function - UPDATED VERSION
function generateQRCode($participant_id) {
    $tempDir = __DIR__ . '/../qrcodes/';
    
    // Create directory if it doesn't exist
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    
    // Data to encode in QR code
    $qrData = json_encode([
        'participant_id' => $participant_id,
        'type' => 'brigada_attendance',
        'timestamp' => time()
    ]);
    
    $fileName = 'participant_' . $participant_id . '.png';
    $filePath = $tempDir . $fileName;
    
    // Generate QR code using our simple class
    SimpleQRCode::generate($qrData, $filePath, 300);
    
    return $fileName;
}

// Log activity function
function logActivity($action, $details = null) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        
        $user_id = $_SESSION['user_id'] ?? null;
        $details_json = $details ? json_encode($details) : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        
        $stmt->execute([$user_id, $action, $details_json, $ip]);
        return true;
    } catch(Exception $e) {
        return false;
    }
}
?>