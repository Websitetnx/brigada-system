<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

include 'includes/header.php';

// Get statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM participants WHERE status = 'Active') as total_participants,
        (SELECT COUNT(*) FROM attendance WHERE DATE(date) = CURDATE() AND sign_in_time IS NOT NULL) as today_attendance,
        (SELECT COUNT(*) FROM attendance WHERE DATE(date) = CURDATE() AND sign_out_time IS NULL AND sign_in_time IS NOT NULL) as currently_present,
        (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'Paid' AND DATE(payment_date) = CURDATE()) as today_collections,
        (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'Pending') as pending_payments,
        (SELECT AVG(total_minutes) FROM attendance WHERE total_minutes > 0) as avg_minutes
    FROM dual
";
$stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-tachometer-alt text-primary"></i> Dashboard</h2>
        <div>
            <span class="badge bg-success me-2" id="liveTime"></span>
            <button class="btn btn-outline-secondary" onclick="window.location.reload()">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white stats-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Total Participants</h6>
                            <h2><?php echo number_format($stats['total_participants']); ?></h2>
                            <small>Registered volunteers</small>
                        </div>
                        <div>
                            <i class="fas fa-users fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white stats-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Present Today</h6>
                            <h2><?php echo $stats['currently_present']; ?></h2>
                            <small>Currently signed in</small>
                        </div>
                        <div>
                            <i class="fas fa-user-check fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-white stats-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Pending Payments</h6>
                            <h2>₱<?php echo number_format($stats['pending_payments'], 2); ?></h2>
                            <small>Awaiting collection</small>
                        </div>
                        <div>
                            <i class="fas fa-clock fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-info text-white stats-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Avg. Service Time</h6>
                            <h2><?php echo round($stats['avg_minutes'] ?? 0); ?> min</h2>
                            <small>Per participant</small>
                        </div>
                        <div>
                            <i class="fas fa-hourglass-half fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-qrcode"></i> Quick Sign In/Out</h5>
                </div>
                <div class="card-body">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="scanInput" 
                               placeholder="Scan QR Code or Enter ID">
                        <button class="btn btn-primary" onclick="processAttendance()">Process</button>
                    </div>
                    <div id="attendanceResult"></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user-plus"></i> Quick Add Participant</h5>
                </div>
                <div class="card-body">
                    <form id="quickAddForm">
                        <div class="mb-2">
                            <input type="text" class="form-control" name="first_name" 
                                   placeholder="First Name" required>
                        </div>
                        <div class="mb-2">
                            <input type="text" class="form-control" name="last_name" 
                                   placeholder="Last Name" required>
                        </div>
                        <div class="mb-2">
                            <select class="form-select" name="role">
                                <option value="Volunteer">Volunteer</option>
                                <option value="Student">Student</option>
                                <option value="Parent">Parent</option>
                                <option value="Guardian">Guardian</option>
                                <option value="Teacher">Teacher</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Add Participant</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Today's Summary</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Check-ins Today
                            <span class="badge bg-primary rounded-pill"><?php echo $stats['today_attendance']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Currently Present
                            <span class="badge bg-success rounded-pill"><?php echo $stats['currently_present']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Collections Today
                            <span class="badge bg-info rounded-pill">₱<?php echo number_format($stats['today_collections'], 2); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Required Hours
                            <span class="badge bg-warning rounded-pill">2 Hours</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-history"></i> Recent Attendance</h5>
                    <a href="attendance.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Time In</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="recentAttendance">
                                <?php
                                $recent = $db->query("
                                    SELECT 
                                        CONCAT(p.first_name, ' ', p.last_name) as name,
                                        a.sign_in_time,
                                        a.sign_out_time,
                                        a.status
                                    FROM attendance a
                                    JOIN participants p ON a.participant_id = p.id
                                    WHERE DATE(a.date) = CURDATE()
                                    ORDER BY a.sign_in_time DESC
                                    LIMIT 5
                                ");
                                while($row = $recent->fetch(PDO::FETCH_ASSOC)) {
                                    $statusBadge = $row['sign_out_time'] ? 
                                        ($row['status'] == 'Complete' ? 'success' : 'warning') : 'info';
                                    $statusText = $row['sign_out_time'] ? $row['status'] : 'Present';
                                    echo "<tr>
                                        <td>{$row['name']}</td>
                                        <td>".date('h:i A', strtotime($row['sign_in_time']))."</td>
                                        <td><span class='badge bg-{$statusBadge}'>{$statusText}</span></td>
                                    </tr>";
                                }
                                if ($recent->rowCount() == 0) {
                                    echo '<tr><td colspan="3" class="text-center text-muted py-3">No attendance records today</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-money-bill"></i> Recent Payments</h5>
                    <a href="payments.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $payments = $db->query("
                                    SELECT 
                                        CONCAT(p.first_name, ' ', p.last_name) as name,
                                        pay.amount,
                                        pay.status
                                    FROM payments pay
                                    JOIN participants p ON pay.participant_id = p.id
                                    ORDER BY pay.created_at DESC
                                    LIMIT 5
                                ");
                                while($row = $payments->fetch(PDO::FETCH_ASSOC)) {
                                    $badge_class = $row['status'] == 'Paid' ? 'success' : 'warning';
                                    echo "<tr>
                                        <td>{$row['name']}</td>
                                        <td>₱".number_format($row['amount'], 2)."</td>
                                        <td><span class='badge bg-{$badge_class}'>{$row['status']}</span></td>
                                    </tr>";
                                }
                                if ($payments->rowCount() == 0) {
                                    echo '<tr><td colspan="3" class="text-center text-muted py-3">No payment records</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Update live time
function updateTime() {
    const now = new Date();
    document.getElementById('liveTime').textContent = now.toLocaleString('en-US', { 
        hour: '2-digit', minute: '2-digit', second: '2-digit',
        month: 'short', day: 'numeric'
    });
}
updateTime();
setInterval(updateTime, 1000);

function processAttendance() {
    const input = document.getElementById('scanInput').value;
    if (!input) {
        alert('Please enter participant ID or scan QR code');
        return;
    }
    
    fetch('api/attendance.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({participant_identifier: input})
    })
    .then(response => response.json())
    .then(data => {
        const resultDiv = document.getElementById('attendanceResult');
        if (data.success) {
            resultDiv.innerHTML = `<div class="alert alert-success">
                ✓ ${data.message}<br>
                Participant: ${data.participant_name}<br>
                Time: ${new Date().toLocaleTimeString()}
            </div>`;
            document.getElementById('scanInput').value = '';
            setTimeout(() => location.reload(), 2000);
        } else {
            resultDiv.innerHTML = `<div class="alert alert-danger">
                ✗ ${data.message}
            </div>`;
        }
    });
}

document.getElementById('quickAddForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('api/participants.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Participant added successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
});

// Handle Enter key
document.getElementById('scanInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') processAttendance();
});
</script>

<?php include 'includes/footer.php'; ?>