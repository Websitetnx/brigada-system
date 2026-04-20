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
        <h2><i class="fas fa-users text-primary"></i> Participant Management</h2>
        <div>
            <button class="btn btn-success me-2" onclick="exportParticipants()">
                <i class="fas fa-download"></i> Export
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addParticipantModal">
                <i class="fas fa-plus"></i> Add New Participant
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6>Total Participants</h6>
                    <h3 id="totalCount">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6>Active Participants</h6>
                    <h3 id="activeCount">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6>Total Hours Served</h6>
                    <h3 id="totalHours">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6>Pending Penalties</h6>
                    <h3 id="pendingPenalties">₱0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Filter by Role</label>
                    <select class="form-select" id="filterRole" onchange="loadParticipants()">
                        <option value="">All Roles</option>
                        <option value="Student">Student</option>
                        <option value="Parent">Parent</option>
                        <option value="Guardian">Guardian</option>
                        <option value="Teacher">Teacher</option>
                        <option value="Volunteer">Volunteer</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Filter by Status</label>
                    <select class="form-select" id="filterStatus" onchange="loadParticipants()">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="">All Status</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="searchInput" 
                           placeholder="Search by name, email, or contact...">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadParticipants()">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Participants Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Participants List</h5>
            <span class="badge bg-primary" id="resultCount">0</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">ID</th>
                            <th width="18%">Name</th>
                            <th width="10%">Role</th>
                            <th width="15%">Contact</th>
                            <th width="15%">Guardian</th>
                            <th width="10%">Hours</th>
                            <th width="10%">Penalty</th>
                            <th width="7%">Status</th>
                            <th width="10%">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="participantsTableBody">
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status"></div>
                                <p class="mt-2 text-muted">Loading participants...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Participant Modal -->
<div class="modal fade" id="addParticipantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add New Participant</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addParticipantForm" onsubmit="saveParticipant(event)">
                <div class="modal-body">
                    <div id="addErrorMsg" class="alert alert-danger" style="display:none;"></div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" id="add_first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name" id="add_last_name" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" id="add_role" required onchange="toggleAddStudentFields()">
                                <option value="">Select Role</option>
                                <option value="Student">Student</option>
                                <option value="Parent">Parent</option>
                                <option value="Guardian">Guardian</option>
                                <option value="Teacher">Teacher</option>
                                <option value="Volunteer">Volunteer</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="add_grade_section_div" style="display:none;">
                            <label class="form-label">Grade & Section</label>
                            <input type="text" class="form-control" name="grade_section" id="add_grade_section">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="contact_number" id="add_contact_number">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" name="email" id="add_email">
                        </div>
                    </div>
                    <div class="row" id="add_guardian_fields" style="display:none;">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Guardian Name</label>
                            <input type="text" class="form-control" name="guardian_name" id="add_guardian_name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Guardian Contact</label>
                            <input type="tel" class="form-control" name="guardian_contact" id="add_guardian_contact">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Participant</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Participant Modal -->
<div class="modal fade" id="editParticipantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-user-edit"></i> Edit Participant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editParticipantForm" onsubmit="updateParticipant(event)">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div id="editErrorMsg" class="alert alert-danger" style="display:none;"></div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" id="edit_role" required onchange="toggleEditStudentFields()">
                                <option value="Student">Student</option>
                                <option value="Parent">Parent</option>
                                <option value="Guardian">Guardian</option>
                                <option value="Teacher">Teacher</option>
                                <option value="Volunteer">Volunteer</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="edit_grade_section_div">
                            <label class="form-label">Grade & Section</label>
                            <input type="text" class="form-control" name="grade_section" id="edit_grade_section">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="contact_number" id="edit_contact_number">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" name="email" id="edit_email">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Guardian Name</label>
                            <input type="text" class="form-control" name="guardian_name" id="edit_guardian_name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Guardian Contact</label>
                            <input type="tel" class="form-control" name="guardian_contact" id="edit_guardian_contact">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Update Participant</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Participant Modal (unchanged but included for completeness) -->
<div class="modal fade" id="viewParticipantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-user"></i> Participant Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="participantDetails">
                <div class="text-center py-4"><div class="spinner-border text-info"></div><p class="mt-2">Loading details...</p></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Global participants data for duplicate checking
let currentParticipants = [];

// Toggle add student fields
function toggleAddStudentFields() {
    const role = document.getElementById('add_role').value;
    document.getElementById('add_grade_section_div').style.display = role === 'Student' ? 'block' : 'none';
    document.getElementById('add_guardian_fields').style.display = (role === 'Student' || role === 'Guardian') ? 'flex' : 'none';
}

function toggleEditStudentFields() {
    const role = document.getElementById('edit_role').value;
    document.getElementById('edit_grade_section_div').style.display = role === 'Student' ? 'block' : 'none';
    // Guardian fields always visible in edit for simplicity
}

// Load participants
function loadParticipants() {
    const role = document.getElementById('filterRole').value;
    const status = document.getElementById('filterStatus').value;
    const search = document.getElementById('searchInput').value;

    const params = new URLSearchParams();
    if (role) params.append('role', role);
    if (status) params.append('status', status);
    if (search) params.append('search', search);

    fetch('api/participants.php?' + params.toString())
        .then(response => response.json())
        .then(data => {
            currentParticipants = data;
            updateParticipantsTable(data);
            updateStatistics(data);
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading participants', 'error');
        });
}

function updateParticipantsTable(participants) {
    const tbody = document.getElementById('participantsTableBody');
    const countSpan = document.getElementById('resultCount');
    countSpan.textContent = participants.length + ' records';

    if (participants.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fas fa-users fa-2x mb-2"></i><p>No participants found</p></td></tr>`;
        return;
    }

    let html = '';
    participants.forEach(p => {
        const hoursServed = (p.total_minutes_served / 60).toFixed(1);
        const penaltyBadge = p.pending_penalties > 0 ? 
            `<span class="badge bg-warning">₱${parseFloat(p.pending_penalties).toFixed(2)}</span>` : 
            '<span class="badge bg-success">None</span>';
        const statusBadge = p.status === 'Active' ? 
            '<span class="badge bg-success">Active</span>' : 
            '<span class="badge bg-secondary">Inactive</span>';

        html += `<tr>
            <td>${p.id}</td>
            <td><strong>${escapeHtml(p.first_name)} ${escapeHtml(p.last_name)}</strong>${p.grade_section ? `<br><small class="text-muted">${escapeHtml(p.grade_section)}</small>` : ''}</td>
            <td><span class="badge bg-secondary">${escapeHtml(p.role)}</span></td>
            <td>${p.contact_number ? escapeHtml(p.contact_number) : '-'}<br><small>${p.email ? escapeHtml(p.email) : ''}</small></td>
            <td>${p.guardian_name ? escapeHtml(p.guardian_name) : '-'}<br><small>${p.guardian_contact ? escapeHtml(p.guardian_contact) : ''}</small></td>
            <td><span class="badge bg-primary">${hoursServed} hrs</span><br><small>${p.days_attended || 0} days</small></td>
            <td>${penaltyBadge}</td>
            <td>${statusBadge}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-info" onclick="viewParticipant(${p.id})" title="View"><i class="fas fa-eye"></i></button>
                    <button class="btn btn-warning" onclick="editParticipant(${p.id})" title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-success" onclick="printQRCode(${p.id})" title="Print QR"><i class="fas fa-qrcode"></i></button>
                    <button class="btn btn-danger" onclick="deleteParticipant(${p.id}, '${escapeHtml(p.first_name + ' ' + p.last_name)}')" title="Delete"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function updateStatistics(participants) {
    const total = participants.length;
    const active = participants.filter(p => p.status === 'Active').length;
    const totalMinutes = participants.reduce((sum, p) => sum + (parseInt(p.total_minutes_served) || 0), 0);
    const totalHours = Math.round(totalMinutes / 60);
    const pendingPenalties = participants.reduce((sum, p) => sum + (parseFloat(p.pending_penalties) || 0), 0);
    document.getElementById('totalCount').textContent = total;
    document.getElementById('activeCount').textContent = active;
    document.getElementById('totalHours').textContent = totalHours;
    document.getElementById('pendingPenalties').textContent = '₱' + pendingPenalties.toFixed(2);
}

// Check duplicate name (client-side)
function isDuplicateName(firstName, lastName, excludeId = null) {
    return currentParticipants.some(p => 
        p.first_name.toLowerCase() === firstName.toLowerCase() && 
        p.last_name.toLowerCase() === lastName.toLowerCase() &&
        p.id != excludeId
    );
}

// Save participant
function saveParticipant(event) {
    event.preventDefault();
    const form = document.getElementById('addParticipantForm');
    const firstName = document.getElementById('add_first_name').value.trim();
    const lastName = document.getElementById('add_last_name').value.trim();
    const errorDiv = document.getElementById('addErrorMsg');

    // Duplicate check
    if (isDuplicateName(firstName, lastName)) {
        errorDiv.textContent = 'A participant with this name already exists.';
        errorDiv.style.display = 'block';
        return;
    }
    errorDiv.style.display = 'none';

    const formData = new FormData(form);
    showLoading();
    fetch('api/participants.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showToast('Participant added successfully!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('addParticipantModal')).hide();
                form.reset();
                loadParticipants();
                if (data.id) setTimeout(() => { if (confirm('Print QR code?')) printQRCode(data.id); }, 500);
            } else {
                errorDiv.textContent = data.message || 'Error adding participant';
                errorDiv.style.display = 'block';
            }
        })
        .catch(error => { hideLoading(); showToast('Network error', 'error'); });
}

// Edit participant - fetch and populate
function editParticipant(id) {
    fetch('api/participants.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.length > 0) {
                const p = data[0];
                document.getElementById('edit_id').value = p.id;
                document.getElementById('edit_first_name').value = p.first_name;
                document.getElementById('edit_last_name').value = p.last_name;
                document.getElementById('edit_role').value = p.role;
                document.getElementById('edit_grade_section').value = p.grade_section || '';
                document.getElementById('edit_contact_number').value = p.contact_number || '';
                document.getElementById('edit_email').value = p.email || '';
                document.getElementById('edit_guardian_name').value = p.guardian_name || '';
                document.getElementById('edit_guardian_contact').value = p.guardian_contact || '';
                document.getElementById('edit_status').value = p.status;
                toggleEditStudentFields();
                new bootstrap.Modal(document.getElementById('editParticipantModal')).show();
            }
        });
}

// Update participant
function updateParticipant(event) {
    event.preventDefault();
    const form = document.getElementById('editParticipantForm');
    const id = document.getElementById('edit_id').value;
    const firstName = document.getElementById('edit_first_name').value.trim();
    const lastName = document.getElementById('edit_last_name').value.trim();
    const errorDiv = document.getElementById('editErrorMsg');

    // Duplicate check (exclude current ID)
    if (isDuplicateName(firstName, lastName, id)) {
        errorDiv.textContent = 'Another participant with this name already exists.';
        errorDiv.style.display = 'block';
        return;
    }
    errorDiv.style.display = 'none';

    const formData = new FormData(form);
    formData.append('_method', 'PUT');
    showLoading();
    fetch('api/participants.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showToast('Participant updated!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('editParticipantModal')).hide();
                loadParticipants();
            } else {
                errorDiv.textContent = data.message || 'Update failed';
                errorDiv.style.display = 'block';
            }
        })
        .catch(error => { hideLoading(); showToast('Network error', 'error'); });
}

// Delete participant
function deleteParticipant(id, name) {
    confirmAction('Delete ' + name + '? This cannot be undone.', function() {
        fetch('api/participants.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Participant deleted', 'success');
                loadParticipants();
            } else {
                showToast(data.message || 'Delete failed', 'error');
            }
        });
    });
}

function viewParticipant(id) {
    fetch('api/reports.php?type=participant_detail&participant_id=' + id)
        .then(response => response.json())
        .then(data => {
            let html = `<div class="row"><div class="col-md-6"><h6>Personal Info</h6>
                <table class="table table-sm"><tr><th>Name:</th><td>${escapeHtml(data.first_name)} ${escapeHtml(data.last_name)}</td></tr>
                <tr><th>Role:</th><td>${escapeHtml(data.role)}</td></tr>
                <tr><th>Contact:</th><td>${data.contact_number||'N/A'}</td></tr>
                <tr><th>Email:</th><td>${data.email||'N/A'}</td></tr>
                <tr><th>Guardian:</th><td>${data.guardian_name||'N/A'}</td></tr></table></div>
                <div class="col-md-6"><h6>Service Summary</h6>
                <table class="table table-sm"><tr><th>Total Hours:</th><td>${data.total_hours_served||0}</td></tr>
                <tr><th>Days Attended:</th><td>${data.days_attended||0}</td></tr>
                <tr><th>Penalties:</th><td>₱${data.total_penalty_amount||0}</td></tr>
                <tr><th>Balance:</th><td>₱${data.balance||0}</td></tr></table></div></div>`;
            document.getElementById('participantDetails').innerHTML = html;
            new bootstrap.Modal(document.getElementById('viewParticipantModal')).show();
        });
}

function printQRCode(id) { window.open('qr.php?id=' + id, '_blank', 'width=450,height=550'); }
function exportParticipants() { window.location.href = 'api/reports.php?type=export&export_type=participants&format=csv'; }
function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }

document.addEventListener('DOMContentLoaded', function() {
    loadParticipants();
    document.getElementById('searchInput').addEventListener('keypress', e => { if(e.key==='Enter') loadParticipants(); });
});
</script>

<?php include 'includes/footer.php'; ?>