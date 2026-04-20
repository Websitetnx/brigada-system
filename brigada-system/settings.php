<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Check if user is admin (optional - uncomment if you have role check)
// if ($_SESSION['user_role'] !== 'Admin') {
//     header('Location: dashboard.php');
//     exit;
// }

include 'includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $updates = [];
    $success = true;
    
    try {
        // Update each setting
        $settings_to_update = [
            'school_name', 'school_address', 'contact_number', 'contact_email',
            'required_hours', 'penalty_amount', 'brigada_start_date', 'brigada_end_date',
            'auto_penalty_enabled', 'timezone'
        ];
        
        foreach ($settings_to_update as $key) {
            if (isset($_POST[$key])) {
                $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$_POST[$key], $key]);
            }
        }
        
        $success_message = "Settings saved successfully!";
        
        // Log activity
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, 'UPDATE_SETTINGS', ?, ?)");
        $stmt->execute([$_SESSION['user_id'], json_encode(['updated_by' => $_SESSION['user_id']]), $_SERVER['REMOTE_ADDR']]);
        
    } catch (Exception $e) {
        $error_message = "Error saving settings: " . $e->getMessage();
    }
}

// Fetch current settings
$stmt = $db->query("SELECT setting_key, setting_value, description FROM settings ORDER BY setting_key");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = [
        'value' => $row['setting_value'],
        'description' => $row['description']
    ];
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-cog text-secondary"></i> System Settings</h2>
        <div>
            <button class="btn btn-primary" onclick="document.getElementById('settingsForm').requestSubmit()">
                <i class="fas fa-save"></i> Save Settings
            </button>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <form id="settingsForm" method="POST" action="">
        <div class="row">
            <!-- School Information -->
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-school"></i> School Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">School Name</label>
                            <input type="text" class="form-control" name="school_name" 
                                   value="<?php echo htmlspecialchars($settings['school_name']['value'] ?? 'Brigada Eskwela Elementary School'); ?>">
                            <small class="text-muted"><?php echo $settings['school_name']['description'] ?? 'School name for receipts and reports'; ?></small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">School Address</label>
                            <textarea class="form-control" name="school_address" rows="2"><?php echo htmlspecialchars($settings['school_address']['value'] ?? ''); ?></textarea>
                            <small class="text-muted">Full address for official documents</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Number</label>
                                <input type="text" class="form-control" name="contact_number" 
                                       value="<?php echo htmlspecialchars($settings['contact_number']['value'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="contact_email" 
                                       value="<?php echo htmlspecialchars($settings['contact_email']['value'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Brigada Settings -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Brigada Eskwela Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="brigada_start_date" 
                                       value="<?php echo htmlspecialchars($settings['brigada_start_date']['value'] ?? date('Y-01-01')); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="brigada_end_date" 
                                       value="<?php echo htmlspecialchars($settings['brigada_end_date']['value'] ?? date('Y-12-31')); ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Required Hours (per day)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="required_hours" 
                                           value="<?php echo htmlspecialchars($settings['required_hours']['value'] ?? '2'); ?>" min="0" max="24">
                                    <span class="input-group-text">hours</span>
                                </div>
                                <small class="text-muted">Minimum hours required to avoid penalty</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Penalty Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" class="form-control" name="penalty_amount" 
                                           value="<?php echo htmlspecialchars($settings['penalty_amount']['value'] ?? '200'); ?>" min="0" step="0.01">
                                </div>
                                <small class="text-muted">Amount charged for incomplete service</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Timezone</label>
                            <select class="form-select" name="timezone">
                                <?php
                                $timezones = [
                                    '--- Asia / Pacific ---'        => '--- Asia / Pacific ---',
                                    'Asia/Manila'                   => 'Asia/Manila (Philippines, UTC+8)',
                                    'Asia/Singapore'                => 'Asia/Singapore (UTC+8)',
                                    'Asia/Hong_Kong'                => 'Asia/Hong_Kong (UTC+8)',
                                    'Asia/Shanghai'                 => 'Asia/Shanghai (China, UTC+8)',
                                    'Asia/Tokyo'                    => 'Asia/Tokyo (Japan, UTC+9)',
                                    'Asia/Seoul'                    => 'Asia/Seoul (Korea, UTC+9)',
                                    'Asia/Jakarta'                  => 'Asia/Jakarta (Indonesia, UTC+7)',
                                    'Asia/Bangkok'                  => 'Asia/Bangkok (UTC+7)',
                                    'Asia/Dubai'                    => 'Asia/Dubai (UTC+4)',
                                    'Asia/Kolkata'                  => 'Asia/Kolkata (India, UTC+5:30)',
                                    'Australia/Sydney'              => 'Australia/Sydney (AEST, UTC+10)',
                                    '--- United States ---'         => '--- United States ---',
                                    'America/Los_Angeles'           => 'America/Los_Angeles (Pacific, UTC-7/-8)',
                                    'America/Denver'                => 'America/Denver (Mountain, UTC-6/-7)',
                                    'America/Phoenix'               => 'America/Phoenix (Arizona, UTC-7)',
                                    'America/Chicago'               => 'America/Chicago (Central, UTC-5/-6)',
                                    'America/New_York'              => 'America/New_York (Eastern, UTC-4/-5)',
                                    'Pacific/Honolulu'              => 'Pacific/Honolulu (Hawaii, UTC-10)',
                                    '--- Europe ---'                => '--- Europe ---',
                                    'Europe/London'                 => 'Europe/London (GMT/BST)',
                                    'Europe/Paris'                  => 'Europe/Paris (CET, UTC+1/+2)',
                                    'Europe/Berlin'                 => 'Europe/Berlin (CET, UTC+1/+2)',
                                    'UTC'                           => 'UTC (Universal, UTC+0)',
                                ];
                                $current_tz = $settings['timezone']['value'] ?? 'Asia/Manila';
                                foreach ($timezones as $tz => $label) {
                                    if (str_starts_with($label, '---')) {
                                        echo "<option disabled>$label</option>";
                                    } else {
                                        $selected = ($tz === $current_tz) ? 'selected' : '';
                                        echo "<option value=\"$tz\" $selected>$label</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="auto_penalty_enabled" value="true" 
                                       <?php echo ($settings['auto_penalty_enabled']['value'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                <label class="form-check-label">Auto-apply penalty for incomplete attendance</label>
                            </div>
                            <small class="text-muted">Automatically add ₱200 penalty when service is less than required hours</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Information -->
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> System Information</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">System Version</th>
                                <td>Brigada Eskwela Monitoring System v1.0</td>
                            </tr>
                            <tr>
                                <th>PHP Version</th>
                                <td><?php echo phpversion(); ?></td>
                            </tr>
                            <tr>
                                <th>MySQL Version</th>
                                <td><?php 
                                    $stmt = $db->query("SELECT VERSION() as version");
                                    echo $stmt->fetch(PDO::FETCH_ASSOC)['version'];
                                ?></td>
                            </tr>
                            <tr>
                                <th>Database Name</th>
                                <td><?php echo $db->query("SELECT DATABASE() as db")->fetch()['db']; ?></td>
                            </tr>
                            <tr>
                                <th>Server Time</th>
                                <td><?php echo date('F j, Y g:i A'); ?></td>
                            </tr>
                            <tr>
                                <th>Default Timezone</th>
                                <td><?php echo date_default_timezone_get(); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Database Statistics -->
                <div class="card mb-4">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="fas fa-database"></i> Database Statistics</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th width="50%">Total Participants</th>
                                <td><?php 
                                    $stmt = $db->query("SELECT COUNT(*) FROM participants");
                                    echo number_format($stmt->fetchColumn());
                                ?></td>
                            </tr>
                            <tr>
                                <th>Total Attendance Records</th>
                                <td><?php 
                                    $stmt = $db->query("SELECT COUNT(*) FROM attendance");
                                    echo number_format($stmt->fetchColumn());
                                ?></td>
                            </tr>
                            <tr>
                                <th>Total Payments</th>
                                <td><?php 
                                    $stmt = $db->query("SELECT COUNT(*) FROM payments");
                                    echo number_format($stmt->fetchColumn());
                                ?></td>
                            </tr>
                            <tr>
                                <th>Total Users</th>
                                <td><?php 
                                    $stmt = $db->query("SELECT COUNT(*) FROM users");
                                    echo number_format($stmt->fetchColumn());
                                ?></td>
                            </tr>
                            <tr>
                                <th>Database Size</th>
                                <td><?php 
                                    $stmt = $db->query("
                                        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb 
                                        FROM information_schema.tables 
                                        WHERE table_schema = DATABASE()
                                    ");
                                    echo $stmt->fetchColumn() . ' MB';
                                ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary" onclick="location.href='dashboard.php'">
                                <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                            </button>
                            <button type="button" class="btn btn-outline-success" onclick="location.href='reports.php'">
                                <i class="fas fa-chart-bar"></i> View Reports
                            </button>
                            <button type="button" class="btn btn-outline-warning" onclick="clearCache()">
                                <i class="fas fa-sync-alt"></i> Clear System Cache
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" name="save_settings" value="1">
    </form>
</div>

<script>
function clearCache() {
    if (confirm('Clear system cache? This will refresh the page.')) {
        // Clear localStorage
        localStorage.clear();
        // Clear sessionStorage
        sessionStorage.clear();
        // Reload page
        location.reload();
        showToast('Cache cleared successfully!', 'success');
    }
}

// Confirm before leaving if form is dirty
let formDirty = false;
const form = document.getElementById('settingsForm');
const inputs = form.querySelectorAll('input, select, textarea');

inputs.forEach(input => {
    input.addEventListener('change', () => { formDirty = true; });
    input.addEventListener('input', () => { formDirty = true; });
});

window.addEventListener('beforeunload', (e) => {
    if (formDirty) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
    }
});

// Reset dirty flag on form submit
form.addEventListener('submit', () => { formDirty = false; });
</script>

<?php include 'includes/footer.php'; ?>