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
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-clock text-success"></i> Attendance Tracking</h2>
        <div>
            <button class="btn btn-outline-primary me-2" onclick="window.location.href='scan.php'">
                <i class="fas fa-qrcode"></i> Open QR Scanner
            </button>
            <button class="btn btn-success" onclick="exportAttendance()">
                <i class="fas fa-download"></i> Export
            </button>
        </div>
    </div>

    <!-- Date Selection and Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <label class="form-label fw-bold">Select Date</label>
                    <input type="date" class="form-control" id="attendanceDate" 
                           value="<?php echo date('Y-m-d'); ?>" onchange="loadAttendance()">
                </div>
            </div>
        </div>
        <div class="col-md-9">
            <div class="row">
                <div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body"><h6>Total Check-ins</h6><h3 id="totalCheckins">0</h3></div></div></div>
                <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body"><h6>Currently Present</h6><h3 id="currentlyPresent">0</h3></div></div></div>
                <div class="col-md-3"><div class="card bg-info text-white"><div class="card-body"><h6>Completed (2+ hrs)</h6><h3 id="completedCount">0</h3></div></div></div>
                <div class="col-md-3"><div class="card bg-warning text-white"><div class="card-body"><h6>Penalties Today</h6><h3 id="penaltiesToday">0</h3></div></div></div>
            </div>
        </div>
    </div>

    <!-- Quick Sign In/Out -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-user-check text-primary"></i> Quick Sign In/Out</h5>
        </div>
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="quickParticipant" placeholder="Enter Participant ID or Scan QR Code" autofocus>
                        <button class="btn btn-primary" onclick="processQuickAttendance()"><i class="fas fa-sign-in-alt"></i> Process</button>
                    </div>
                </div>
                <div class="col-md-7"><div id="quickResult"></div></div>
            </div>
        </div>
    </div>

    <!-- Manual Sign In Button -->
    <div class="mb-3">
        <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#manualSignInModal">
            <i class="fas fa-user-plus"></i> Manual Sign In
        </button>
    </div>

    <!-- Attendance Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Attendance Records - <span id="displayDate"><?php echo date('F j, Y'); ?></span></h5>
            <div>
                <button class="btn btn-sm btn-outline-secondary me-2" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                <button class="btn btn-sm btn-outline-primary" onclick="loadAttendance()"><i class="fas fa-sync-alt"></i> Refresh</button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Time In</th><th>Participant</th><th>Role</th><th>Time Out</th><th>Duration</th><th>Status</th><th>Penalty</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceTableBody">
                        <tr><td colspan="8" class="text-center py-4"><div class="spinner-border text-success"></div><p class="mt-2">Loading...</p></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Manual Sign In Modal -->
<div class="modal fade" id="manualSignInModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-sign-in-alt"></i> Manual Sign In</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form onsubmit="manualSignIn(event)">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Participant <span class="text-danger">*</span></label>
                        <select class="form-select" id="participantSelect" required><option value="">Loading...</option></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sign In Time</label>
                        <input type="datetime-local" class="form-control" id="signInTime"
                               value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="signInNotes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Sign In</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Store attendance data globally for real-time updates
let currentAttendanceData = [];
let realTimeInterval = null;

// Helper function to parse MySQL datetime to JavaScript Date
function parseMySQLDateTime(dateTimeStr) {
    if (!dateTimeStr) return null;
    // MySQL format: "2024-04-17 08:30:00" -> ISO: "2024-04-17T08:30:00"
    const isoTimestamp = dateTimeStr.replace(' ', 'T');
    return new Date(isoTimestamp);
}

function loadAttendance() {
    const date = document.getElementById('attendanceDate').value;
    document.getElementById('displayDate').textContent = new Date(date + 'T12:00:00').toLocaleDateString('en-US', {year:'numeric', month:'long', day:'numeric'});
    
    fetch('api/attendance.php?date=' + date)
        .then(r => r.json())
        .then(data => {
            currentAttendanceData = data;
            renderAttendanceTable(data);
            updateStats(data);
            startRealTimeUpdates();
        })
        .catch(e => showToast('Error loading attendance', 'error'));
}

function renderAttendanceTable(data) {
    let html = '';
    
    data.forEach(r => {
        const inTime = parseMySQLDateTime(r.sign_in_time);
        const outTime = parseMySQLDateTime(r.sign_out_time);
        const isPresent = inTime && !outTime;
        let displayStatus = r.status;
        let statusBadge = r.status === 'Complete' ? 'success' : (r.status === 'Incomplete' ? 'warning' : 'secondary');
        
        let durationDisplay = '-';
        let durationMinutes = r.total_minutes || 0;
        
        if (isPresent && inTime) {
            const nowMs         = Date.now();
            const durationMs    = nowMs - inTime.getTime();
            durationMinutes     = Math.max(0, Math.floor(durationMs / 60000));
            const hours         = Math.floor(durationMinutes / 60);
            const mins          = durationMinutes % 60;

            if (durationMinutes >= 120) {
                durationDisplay = `<span style="color: #28a745; font-weight: bold;">2h <i class="fas fa-check-circle ms-1"></i></span>`;
                displayStatus   = 'Ready to Sign Out';
                statusBadge     = 'success';
            } else {
                durationDisplay = `${hours}h ${mins}m <span class="live-indicator"><i class="fas fa-circle text-danger ms-1" style="font-size: 8px;"></i></span>`;
            }
        } else if (durationMinutes > 0) {
            const hours = Math.floor(durationMinutes / 60);
            const mins = durationMinutes % 60;
            durationDisplay = `${hours}h ${mins}m`;
        }
        
        const signInMs  = inTime  ? inTime.getTime()  : 0;
        const signOutMs = outTime ? outTime.getTime() : 0;
        
        html += `<tr id="attendance-row-${r.id}">
            <td>${inTime ? inTime.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : '-'}</td>
            <td><strong>${escapeHtml(r.participant_name)}</strong>${r.grade_section ? `<br><small>${escapeHtml(r.grade_section)}</small>` : ''}</td>
            <td><span class="badge bg-secondary">${escapeHtml(r.role)}</span></td>
            <td>${outTime ? outTime.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : '<span class="badge bg-info">Present</span>'}</td>
            <td class="duration-cell"
                data-attendance-id="${r.id}"
                data-sign-in-ms="${inTime ? inTime.getTime() : ''}"
                data-sign-out-ms="${outTime ? outTime.getTime() : ''}">
                ${durationDisplay}
            </td>
            <td><span class="badge bg-${statusBadge}">${displayStatus}</span></td>
            <td>${r.penalty_applied ? '<span class="badge bg-warning">₱200</span>' : '<span class="badge bg-success">None</span>'}</td>
            <td>
                ${isPresent ? `<button class="btn btn-sm btn-warning" onclick="manualSignOut(${r.id}, '${escapeHtml(r.participant_name)}')"><i class="fas fa-sign-out-alt"></i> Sign Out</button>` : ''}
                <button class="btn btn-sm btn-info" onclick="viewDetails(${r.id})"><i class="fas fa-eye"></i></button>
            </td>
        </tr>`;
    });
    
    document.getElementById('attendanceTableBody').innerHTML = html || '<tr><td colspan="8" class="text-center py-4">No records</td></tr>';
}

function updateStats(data) {
    const total = data.length;
    const present = data.filter(r => r.sign_in_time && !r.sign_out_time).length;
    const complete = data.filter(r => r.status === 'Complete').length;
    const penalties = data.filter(r => r.penalty_applied).length;
    
    document.getElementById('totalCheckins').textContent = total;
    document.getElementById('currentlyPresent').textContent = present;
    document.getElementById('completedCount').textContent = complete;
    document.getElementById('penaltiesToday').textContent = penalties;
}

function startRealTimeUpdates() {
    if (realTimeInterval) {
        clearInterval(realTimeInterval);
    }
    realTimeInterval = setInterval(() => {
        updateLiveDurations();
    }, 1000);
}

function updateLiveDurations() {
    const durationCells = document.querySelectorAll('.duration-cell');
    const nowMs = Date.now();

    durationCells.forEach(cell => {
        const signInMs  = parseInt(cell.dataset.signInMs,  10);
        const signOutMs = parseInt(cell.dataset.signOutMs, 10);

        // Only update cells where person is still signed in (no sign-out)
        if (!signInMs || isNaN(signInMs) || (!isNaN(signOutMs) && signOutMs > 0)) return;

        const durationMs      = nowMs - signInMs;
        const durationMinutes = Math.floor(durationMs / 60000);

        if (durationMinutes < 0) {
            cell.innerHTML = `<span class="live-indicator"><i class="fas fa-circle text-danger ms-1" style="font-size:8px;"></i> Waiting...</span>`;
            cell.style.color      = '#6c757d';
            cell.style.fontWeight = 'normal';
            return;
        }

        const hours = Math.floor(durationMinutes / 60);
        const mins  = durationMinutes % 60;

        if (durationMinutes >= 120) {
            cell.innerHTML    = `2h <i class="fas fa-check-circle ms-1"></i>`;
            cell.style.color      = '#28a745';
            cell.style.fontWeight = 'bold';

            const row = cell.closest('tr');
            if (row) {
                const statusCell = row.cells[5];
                if (statusCell && !statusCell.innerHTML.includes('Ready to Sign Out')) {
                    statusCell.innerHTML = '<span class="badge bg-success">Ready to Sign Out</span>';
                }
            }
        } else {
            cell.innerHTML    = `${hours}h ${mins}m <span class="live-indicator"><i class="fas fa-circle text-danger ms-1" style="font-size:8px;"></i></span>`;
            cell.style.fontWeight = 'normal';
            cell.style.color      = durationMinutes >= 90 ? '#fd7e14' : '';
        }
    });
}

window.addEventListener('beforeunload', function() {
    if (realTimeInterval) {
        clearInterval(realTimeInterval);
    }
});

function processQuickAttendance() {
    const id = document.getElementById('quickParticipant').value.trim();
    if (!id) return showToast('Enter ID', 'warning');
    showLoading();
    fetch('api/attendance.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({participant_identifier: id}) })
        .then(r => r.json()).then(data => {
            hideLoading();
            const resDiv = document.getElementById('quickResult');
            if (data.success) {
                const actionText = data.action === 'sign_in' ? 'success' : 'info';
                resDiv.innerHTML = `<div class="alert alert-${actionText}">
                    ✓ ${data.message}<br>
                    ${data.participant_name}<br>
                    ${data.duration || ''} 
                    ${data.penalty_applied ? '<span class="badge bg-warning">Penalty ₱200</span>' : ''}
                </div>`;
                document.getElementById('quickParticipant').value = '';
                loadAttendance();
                setTimeout(() => resDiv.innerHTML = '', 5000);
            } else {
                resDiv.innerHTML = `<div class="alert alert-danger">✗ ${data.message}</div>`;
            }
        }).catch(e => { hideLoading(); showToast('Network error', 'error'); });
}

function manualSignIn(e) {
    e.preventDefault();
    const participantId = document.getElementById('participantSelect').value;
    if (!participantId) return showToast('Select participant', 'warning');
    const signInTime = document.getElementById('signInTime').value;
    const notes = document.getElementById('signInNotes').value;
    showLoading();
    fetch('api/attendance.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({participant_id: participantId, manual_sign_in: true, sign_in_time: signInTime.replace('T', ' ') + ':00', notes: notes}) })
        .then(r => r.json()).then(data => {
            hideLoading();
            if (data.success) {
                showToast('Signed in', 'success');
                bootstrap.Modal.getInstance(document.getElementById('manualSignInModal')).hide();
                loadAttendance();
            } else showToast(data.message||'Error', 'error');
        }).catch(e => { hideLoading(); showToast('Network error', 'error'); });
}

function manualSignOut(attendanceId, name) {
    if (!confirm(`Sign out ${name}?`)) return;
    showLoading();
    fetch('api/attendance.php', { method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify({attendance_id: attendanceId, action: 'sign_out'}) })
        .then(r => r.json()).then(data => {
            hideLoading();
            if (data.success) {
                let msg = `${name} signed out. Duration: ${data.duration}.`;
                if (data.penalty_applied) msg += ' Penalty ₱200 applied.';
                showToast(msg, data.penalty_applied?'warning':'success');
                loadAttendance();
            } else showToast(data.message||'Error signing out', 'error');
        }).catch(e => { hideLoading(); showToast('Network error', 'error'); });
}

function loadParticipantsForDropdown() {
    fetch('api/participants.php?status=Active').then(r => r.json()).then(data => {
        let opts = '<option value="">Select participant...</option>';
        data.forEach(p => opts += `<option value="${p.id}">${escapeHtml(p.first_name)} ${escapeHtml(p.last_name)} (${p.role})</option>`);
        document.getElementById('participantSelect').innerHTML = opts;
    });
}

function viewDetails(id) {
    // View details function - can be implemented later
    console.log('View details for attendance ID:', id);
}

function exportAttendance() { 
    window.location.href = `api/reports.php?type=export&export_type=attendance&date=${document.getElementById('attendanceDate').value}&format=csv`; 
}

function escapeHtml(t) { 
    if(!t) return ''; 
    const d = document.createElement('div'); 
    d.textContent = t; 
    return d.innerHTML; 
}

document.addEventListener('DOMContentLoaded', () => {
    loadAttendance();
    loadParticipantsForDropdown();
    
    setInterval(() => {
        const date = document.getElementById('attendanceDate').value;
        fetch('api/attendance.php?date=' + date)
            .then(r => r.json())
            .then(data => {
                currentAttendanceData = data;
                renderAttendanceTable(data);
                updateStats(data);
            })
            .catch(e => console.error('Background refresh error:', e));
    }, 30000);
    
    // Set signInTime default to current local time from server
    // (value already set by PHP in the HTML - no JS manipulation needed)
    
    document.getElementById('quickParticipant').addEventListener('keypress', e => {
        if (e.key === 'Enter') processQuickAttendance();
    });
});
</script>

<style>
.live-indicator {
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.3; }
    100% { opacity: 1; }
}

.duration-cell {
    transition: color 0.3s ease;
}
</style>

<?php include 'includes/footer.php'; ?>