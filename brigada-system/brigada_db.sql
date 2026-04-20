-- =============================================
-- BRIGADA ESKWELA MONITORING SYSTEM DATABASE
-- COPY AND PASTE THIS ENTIRE CODE INTO SQL TAB
-- =============================================

CREATE DATABASE IF NOT EXISTS brigada_db;
USE brigada_db;

-- =============================================
-- TABLE: participants
-- =============================================
CREATE TABLE IF NOT EXISTS participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    qr_code VARCHAR(50) UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    contact_number VARCHAR(15),
    email VARCHAR(100),
    role ENUM('Student', 'Parent', 'Guardian', 'Teacher', 'Volunteer') DEFAULT 'Volunteer',
    grade_section VARCHAR(20),
    guardian_name VARCHAR(100),
    guardian_contact VARCHAR(15),
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (last_name, first_name),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: attendance
-- =============================================
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NOT NULL,
    date DATE NOT NULL,
    sign_in_time DATETIME,
    sign_out_time DATETIME,
    total_minutes INT DEFAULT 0,
    status ENUM('Complete', 'Incomplete', 'No Show', 'Excused') DEFAULT 'No Show',
    penalty_applied BOOLEAN DEFAULT FALSE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
    INDEX idx_date (date),
    INDEX idx_participant (participant_id),
    INDEX idx_status (status),
    UNIQUE KEY unique_daily_attendance (participant_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: payments
-- =============================================
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NOT NULL,
    attendance_id INT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 200.00,
    payment_date DATE NOT NULL,
    payment_method ENUM('Cash', 'GCash', 'Bank Transfer', 'Other') DEFAULT 'Cash',
    reference_number VARCHAR(50),
    receipt_number VARCHAR(50) UNIQUE,
    status ENUM('Pending', 'Paid', 'Waived', 'Cancelled') DEFAULT 'Pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
    FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE SET NULL,
    INDEX idx_participant (participant_id),
    INDEX idx_payment_date (payment_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: users
-- =============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('Admin', 'Staff', 'Viewer') DEFAULT 'Staff',
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: settings
-- =============================================
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: activity_logs
-- =============================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100),
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- VIEW: vw_participant_summary
-- =============================================
CREATE OR REPLACE VIEW vw_participant_summary AS
SELECT 
    p.id,
    p.qr_code,
    p.first_name,
    p.last_name,
    CONCAT(p.first_name, ' ', p.last_name) AS full_name,
    p.role,
    p.grade_section,
    p.contact_number,
    p.email,
    p.guardian_name,
    p.guardian_contact,
    p.status,
    COUNT(DISTINCT a.date) AS days_attended,
    COALESCE(SUM(a.total_minutes), 0) AS total_minutes_served,
    ROUND(COALESCE(SUM(a.total_minutes), 0) / 60, 2) AS total_hours_served,
    COUNT(DISTINCT CASE WHEN a.status = 'Complete' THEN a.id END) AS completed_sessions,
    COUNT(DISTINCT CASE WHEN a.penalty_applied = TRUE THEN a.id END) AS penalties_count,
    COALESCE(SUM(CASE WHEN a.penalty_applied = TRUE THEN 200 ELSE 0 END), 0) AS total_penalty_amount,
    COALESCE(SUM(CASE WHEN pay.status = 'Paid' THEN pay.amount ELSE 0 END), 0) AS total_paid,
    COALESCE(SUM(CASE WHEN pay.status = 'Pending' THEN pay.amount ELSE 0 END), 0) AS pending_amount,
    (COALESCE(SUM(CASE WHEN a.penalty_applied = TRUE THEN 200 ELSE 0 END), 0) - 
     COALESCE(SUM(CASE WHEN pay.status = 'Paid' THEN pay.amount ELSE 0 END), 0)) AS balance
FROM participants p
LEFT JOIN attendance a ON p.id = a.participant_id
LEFT JOIN payments pay ON p.id = pay.participant_id
GROUP BY p.id;

-- =============================================
-- VIEW: vw_daily_summary
-- =============================================
CREATE OR REPLACE VIEW vw_daily_summary AS
SELECT 
    a.date,
    COUNT(DISTINCT a.participant_id) AS total_participants,
    COUNT(*) AS total_sessions,
    SUM(CASE WHEN a.sign_out_time IS NULL AND a.sign_in_time IS NOT NULL THEN 1 ELSE 0 END) AS currently_present,
    COALESCE(SUM(a.total_minutes), 0) AS total_minutes,
    SUM(CASE WHEN a.status = 'Complete' THEN 1 ELSE 0 END) AS completed,
    SUM(CASE WHEN a.penalty_applied = TRUE THEN 1 ELSE 0 END) AS penalties
FROM attendance a
GROUP BY a.date
ORDER BY a.date DESC;

-- =============================================
-- TRIGGER: Calculate minutes on sign out
-- =============================================
DELIMITER //
CREATE TRIGGER tr_attendance_update
BEFORE UPDATE ON attendance
FOR EACH ROW
BEGIN
    IF NEW.sign_out_time IS NOT NULL AND OLD.sign_out_time IS NULL THEN
        SET NEW.total_minutes = TIMESTAMPDIFF(MINUTE, NEW.sign_in_time, NEW.sign_out_time);
        
        IF NEW.total_minutes >= 120 THEN
            SET NEW.status = 'Complete';
            SET NEW.penalty_applied = FALSE;
        ELSE
            SET NEW.status = 'Incomplete';
            SET NEW.penalty_applied = TRUE;
        END IF;
    END IF;
END//
DELIMITER ;

-- =============================================
-- TRIGGER: Auto generate receipt number
-- =============================================
DELIMITER //
CREATE TRIGGER tr_payment_receipt
BEFORE INSERT ON payments
FOR EACH ROW
BEGIN
    IF NEW.receipt_number IS NULL THEN
        SET NEW.receipt_number = CONCAT('RCP-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(FLOOR(RAND() * 10000), 4, '0'));
    END IF;
END//
DELIMITER ;

-- =============================================
-- INSERT DEFAULT SETTINGS
-- =============================================
INSERT INTO settings (setting_key, setting_value, description) VALUES
('required_hours', '2', 'Required community service hours'),
('penalty_amount', '200', 'Penalty amount in pesos'),
('school_name', 'Brigada Eskwela School', 'School name'),
('auto_penalty', 'true', 'Auto apply penalty');

-- =============================================
-- INSERT DEFAULT ADMIN USER
-- Password: admin123 (hashed)
-- =============================================
INSERT INTO users (username, password, full_name, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@brigada.local', 'Admin');

-- =============================================
-- INSERT SAMPLE PARTICIPANTS
-- =============================================
INSERT INTO participants (qr_code, first_name, last_name, contact_number, email, role, grade_section, guardian_name, guardian_contact) VALUES
('BRIG001', 'Juan', 'Dela Cruz', '09123456789', 'juan@email.com', 'Student', 'Grade 5-A', 'Maria Dela Cruz', '09187654321'),
('BRIG002', 'Maria', 'Santos', '09234567890', 'maria@email.com', 'Student', 'Grade 6-B', 'Jose Santos', '09298765432'),
('BRIG003', 'Pedro', 'Reyes', '09345678901', 'pedro@email.com', 'Parent', NULL, NULL, NULL),
('BRIG004', 'Ana', 'Gonzales', '09456789012', 'ana@email.com', 'Teacher', 'Grade 4', NULL, NULL),
('BRIG005', 'Roberto', 'Fernandez', '09567890123', 'roberto@email.com', 'Volunteer', NULL, NULL, NULL),
('BRIG006', 'Sofia', 'Rivera', '09678901234', 'sofia@email.com', 'Student', 'Grade 5-B', 'Carlos Rivera', '09612345678'),
('BRIG007', 'Miguel', 'Torres', '09789012345', 'miguel@email.com', 'Guardian', NULL, NULL, NULL),
('BRIG008', 'Isabella', 'Cruz', '09890123456', 'isabella@email.com', 'Student', 'Grade 6-A', 'Ramon Cruz', '09876543210');

-- =============================================
-- INSERT SAMPLE ATTENDANCE (Last 5 days)
-- =============================================
INSERT INTO attendance (participant_id, date, sign_in_time, sign_out_time, total_minutes, status, penalty_applied) VALUES
(1, CURDATE() - INTERVAL 4 DAY, CONCAT(CURDATE() - INTERVAL 4 DAY, ' 08:00:00'), CONCAT(CURDATE() - INTERVAL 4 DAY, ' 10:30:00'), 150, 'Complete', FALSE),
(2, CURDATE() - INTERVAL 4 DAY, CONCAT(CURDATE() - INTERVAL 4 DAY, ' 08:15:00'), CONCAT(CURDATE() - INTERVAL 4 DAY, ' 10:45:00'), 150, 'Complete', FALSE),
(3, CURDATE() - INTERVAL 4 DAY, CONCAT(CURDATE() - INTERVAL 4 DAY, ' 09:00:00'), CONCAT(CURDATE() - INTERVAL 4 DAY, ' 10:30:00'), 90, 'Incomplete', TRUE),
(1, CURDATE() - INTERVAL 3 DAY, CONCAT(CURDATE() - INTERVAL 3 DAY, ' 08:00:00'), CONCAT(CURDATE() - INTERVAL 3 DAY, ' 10:00:00'), 120, 'Complete', FALSE),
(4, CURDATE() - INTERVAL 3 DAY, CONCAT(CURDATE() - INTERVAL 3 DAY, ' 08:30:00'), CONCAT(CURDATE() - INTERVAL 3 DAY, ' 11:00:00'), 150, 'Complete', FALSE),
(5, CURDATE() - INTERVAL 2 DAY, CONCAT(CURDATE() - INTERVAL 2 DAY, ' 09:00:00'), CONCAT(CURDATE() - INTERVAL 2 DAY, ' 10:30:00'), 90, 'Incomplete', TRUE),
(2, CURDATE() - INTERVAL 2 DAY, CONCAT(CURDATE() - INTERVAL 2 DAY, ' 08:00:00'), CONCAT(CURDATE() - INTERVAL 2 DAY, ' 10:15:00'), 135, 'Complete', FALSE),
(6, CURDATE() - INTERVAL 1 DAY, CONCAT(CURDATE() - INTERVAL 1 DAY, ' 08:45:00'), CONCAT(CURDATE() - INTERVAL 1 DAY, ' 11:00:00'), 135, 'Complete', FALSE),
(1, CURDATE() - INTERVAL 1 DAY, CONCAT(CURDATE() - INTERVAL 1 DAY, ' 08:00:00'), CONCAT(CURDATE() - INTERVAL 1 DAY, ' 09:30:00'), 90, 'Incomplete', TRUE),
(7, CURDATE() - INTERVAL 1 DAY, CONCAT(CURDATE() - INTERVAL 1 DAY, ' 08:30:00'), CONCAT(CURDATE() - INTERVAL 1 DAY, ' 11:00:00'), 150, 'Complete', FALSE),
(1, CURDATE(), CONCAT(CURDATE(), ' 08:00:00'), NULL, 0, 'Incomplete', FALSE),
(2, CURDATE(), CONCAT(CURDATE(), ' 08:15:00'), NULL, 0, 'Incomplete', FALSE),
(4, CURDATE(), CONCAT(CURDATE(), ' 09:00:00'), NULL, 0, 'Incomplete', FALSE),
(8, CURDATE(), CONCAT(CURDATE(), ' 08:30:00'), CONCAT(CURDATE(), ' 10:00:00'), 90, 'Incomplete', TRUE);

-- =============================================
-- INSERT SAMPLE PAYMENTS
-- =============================================
INSERT INTO payments (participant_id, attendance_id, amount, payment_date, payment_method, reference_number, status, notes) VALUES
(3, 3, 200.00, CURDATE() - INTERVAL 3 DAY, 'Cash', NULL, 'Paid', 'Penalty payment'),
(5, 6, 200.00, CURDATE() - INTERVAL 1 DAY, 'GCash', 'GCASH-123456', 'Paid', 'Penalty via GCash'),
(1, 9, 200.00, CURDATE(), 'Cash', NULL, 'Pending', 'Pending penalty'),
(8, 14, 200.00, CURDATE(), 'Bank Transfer', 'BPI-789012', 'Pending', 'Pending penalty');

-- =============================================
-- VERIFICATION
-- =============================================
SELECT 'DATABASE SETUP COMPLETE!' as Status;
SELECT 
    (SELECT COUNT(*) FROM participants) as Total_Participants,
    (SELECT COUNT(*) FROM attendance) as Total_Attendance,
    (SELECT COUNT(*) FROM payments) as Total_Payments,
    (SELECT COUNT(*) FROM users) as Total_Users;

-- Display sample data
SELECT * FROM vw_participant_summary LIMIT 5;
SELECT * FROM vw_daily_summary LIMIT 5;