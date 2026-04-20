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
        <h2><i class="fas fa-money-bill-wave text-warning"></i> Payment Management</h2>
        <div>
            <button class="btn btn-success me-2" onclick="exportPayments()">
                <i class="fas fa-download"></i> Export
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
                <i class="fas fa-plus"></i> Record Payment
            </button>
        </div>
    </div>
    
    <!-- Payment Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6>Total Collections</h6>
                    <h3 id="totalCollections">₱0.00</h3>
                    <small>All time</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6>Today's Collections</h6>
                    <h3 id="todayCollections">₱0.00</h3>
                    <small><?php echo date('F j, Y'); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6>Pending Payments</h6>
                    <h3 id="pendingPayments">₱0.00</h3>
                    <small id="pendingCount">0 pending</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6>Overdue Penalties</h6>
                    <h3 id="overduePenalties">₱0.00</h3>
                    <small>> 7 days pending</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Date Range</label>
                    <select class="form-select" id="dateRange" onchange="loadPayments()">
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month" selected>This Month</option>
                        <option value="quarter">This Quarter</option>
                        <option value="year">This Year</option>
                        <option value="all">All Time</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                <div class="col-md-3" id="customDateFields" style="display: none;">
                    <label class="form-label">From - To</label>
                    <div class="input-group">
                        <input type="date" class="form-control" id="dateFrom" value="<?php echo date('Y-m-01'); ?>">
                        <span class="input-group-text">to</span>
                        <input type="date" class="form-control" id="dateTo" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="paymentStatus" onchange="loadPayments()">
                        <option value="">All Status</option>
                        <option value="Paid">Paid</option>
                        <option value="Pending">Pending</option>
                        <option value="Waived">Waived</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Payment Method</label>
                    <select class="form-select" id="paymentMethod" onchange="loadPayments()">
                        <option value="">All Methods</option>
                        <option value="Cash">Cash</option>
                        <option value="GCash">GCash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="searchPayment" 
                           placeholder="Name, Receipt #..." onkeyup="debouncedLoad()">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-outline-primary w-100" onclick="loadPayments()">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Payments Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Payment Records</h5>
            <span class="badge bg-primary" id="recordCount">0 records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="12%">Receipt #</th>
                            <th width="10%">Date</th>
                            <th width="18%">Participant</th>
                            <th width="10%">Amount</th>
                            <th width="10%">Method</th>
                            <th width="12%">Reference</th>
                            <th width="8%">Status</th>
                            <th width="20%">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="paymentsTableBody">
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <div class="spinner-border text-warning" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Loading payment records...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Pagination -->
        <div class="card-footer">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <select class="form-select form-select-sm d-inline-block w-auto" id="perPage" onchange="loadPayments()">
                        <option value="10">10 per page</option>
                        <option value="25" selected>25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination">
                        <!-- Dynamic pagination -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-hand-holding-usd"></i> Record Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="paymentForm" onsubmit="recordPayment(event)">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Participant <span class="text-danger">*</span></label>
                            <select class="form-select" name="participant_id" id="participantSelect" required onchange="loadParticipantBalance()">
                                <option value="">Select Participant</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Type</label>
                            <select class="form-select" id="paymentType" onchange="togglePaymentType()">
                                <option value="penalty">Penalty Payment</option>
                                <option value="voluntary">Voluntary Donation</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div id="participantBalanceInfo" class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Select a participant to view balance
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" class="form-control" name="amount" id="paymentAmount" 
                                       step="0.01" min="0.01" value="200.00" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select class="form-select" name="payment_method" required>
                                <option value="Cash">Cash</option>
                                <option value="GCash">GCash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Reference Number</label>
                            <input type="text" class="form-control" name="reference_number" 
                                   placeholder="e.g., GCash Ref #, Bank Transaction ID">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Date</label>
                            <input type="date" class="form-control" name="payment_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row" id="attendanceSelectRow" style="display: none;">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Related Attendance (Optional)</label>
                            <select class="form-select" name="attendance_id" id="attendanceSelect">
                                <option value="">None - General Payment</option>
                            </select>
                            <small class="text-muted">Link this payment to a specific attendance record</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2" 
                                      placeholder="Additional notes..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Payment Modal -->
<div class="modal fade" id="viewPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-receipt"></i> Payment Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="paymentDetails">
                <div class="text-center py-4">
                    <div class="spinner-border text-info" role="status"></div>
                    <p class="mt-2">Loading details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="printReceiptBtn" onclick="printCurrentReceipt()">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>






<script>
let currentPage = 1;
let totalPages = 1;
let currentPaymentId = null;
let debounceTimer;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadPayments();
    loadPaymentSummary();
    loadParticipantsForDropdown();
    
    // Toggle custom date fields
    document.getElementById('dateRange').addEventListener('change', function() {
        const customFields = document.getElementById('customDateFields');
        customFields.style.display = this.value === 'custom' ? 'block' : 'none';
        if (this.value !== 'custom') {
            loadPayments();
        }
    });
});

// Debounced load for search
function debouncedLoad() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => loadPayments(), 300);
}

// Load payments
// Load payments
function loadPayments(page = 1) {
    currentPage = page;
    
    const params = new URLSearchParams();
    
    // Date range
    const dateRange = document.getElementById('dateRange').value;
    if (dateRange === 'custom') {
        params.append('date_from', document.getElementById('dateFrom').value);
        params.append('date_to', document.getElementById('dateTo').value);
    } else if (dateRange !== 'all') {
        params.append('date_range', dateRange);
    }
    
    // Filters
    const status = document.getElementById('paymentStatus').value;
    const method = document.getElementById('paymentMethod').value;
    const search = document.getElementById('searchPayment').value;
    const perPage = document.getElementById('perPage').value;
    
    if (status) params.append('status', status);
    if (method) params.append('payment_method', method);
    if (search) params.append('search', search);
    
    params.append('page', page);
    params.append('per_page', perPage);
    
    // Show loading
    document.getElementById('paymentsTableBody').innerHTML = `
        <tr>
            <td colspan="8" class="text-center py-4">
                <div class="spinner-border text-warning" role="status"></div>
                <p class="mt-2 text-muted">Loading payment records...</p>
            </td>
        </tr>
    `;
    
    fetch('api/payments.php?' + params.toString())
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            // Handle both formats: {data: [...]} or direct array
            const payments = data.data || data;
            
            if (payments.length === undefined) {
                console.error('Invalid data format:', data);
                throw new Error('Invalid data received');
            }
            
            updatePaymentsTable(payments);
            updatePagination(data.total_pages || 1, page);
            document.getElementById('recordCount').textContent = 
                (data.total_records || payments.length) + ' records';
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('paymentsTableBody').innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4 text-danger">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                        <p><strong>Error loading payments</strong></p>
                        <p class="small">${error.message}</p>
                        <button class="btn btn-sm btn-outline-primary mt-2" onclick="loadPayments()">
                            <i class="fas fa-sync-alt"></i> Retry
                        </button>
                    </td>
                </tr>
            `;
        });
}

// Update payments table
function updatePaymentsTable(payments) {
    const tbody = document.getElementById('paymentsTableBody');
    
    if (!payments || payments.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4 text-muted">
                    <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                    <p>No payment records found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    payments.forEach(payment => {
        const statusBadge = {
            'Paid': 'success',
            'Pending': 'warning',
            'Waived': 'secondary',
            'Cancelled': 'danger'
        }[payment.status] || 'secondary';
        
        html += `
            <tr>
                <td>
                    <strong>${escapeHtml(payment.receipt_number || 'N/A')}</strong>
                </td>
                <td>${formatDate(payment.payment_date)}</td>
                <td>
                    ${escapeHtml(payment.participant_name)}
                    ${payment.role ? `<br><small class="text-muted">${escapeHtml(payment.role)}</small>` : ''}
                </td>
                <td>
                    <strong>₱${parseFloat(payment.amount).toFixed(2)}</strong>
                </td>
                <td>
                    ${getPaymentMethodIcon(payment.payment_method)} ${escapeHtml(payment.payment_method)}
                </td>
                <td>
                    ${payment.reference_number ? escapeHtml(payment.reference_number) : '-'}
                </td>
                <td>
                    <span class="badge bg-${statusBadge}">${payment.status}</span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-info" onclick="viewPayment(${payment.id})" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-success" onclick="printReceipt(${payment.id})" title="Print Receipt">
                            <i class="fas fa-print"></i>
                        </button>
                        ${payment.status === 'Pending' ? `
                            <button class="btn btn-warning" onclick="markAsPaid(${payment.id})" title="Mark as Paid">
                                <i class="fas fa-check"></i>
                            </button>
                        ` : ''}
                        ${payment.status !== 'Cancelled' && payment.status !== 'Paid' ? `
                            <button class="btn btn-danger" onclick="cancelPayment(${payment.id})" title="Cancel">
                                <i class="fas fa-times"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

// Update pagination
function updatePagination(total, current) {
    totalPages = total;
    const pagination = document.getElementById('pagination');
    
    if (total <= 1) {
        pagination.innerHTML = '';
        return;
    }
    
    let html = '';
    
    // Previous
    html += `<li class="page-item ${current === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadPayments(${current - 1}); return false;">«</a>
    </li>`;
    
    // Page numbers
    for (let i = 1; i <= total; i++) {
        if (i === 1 || i === total || (i >= current - 2 && i <= current + 2)) {
            html += `<li class="page-item ${i === current ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadPayments(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === current - 3 || i === current + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    
    // Next
    html += `<li class="page-item ${current === total ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadPayments(${current + 1}); return false;">»</a>
    </li>`;
    
    pagination.innerHTML = html;
}

// Load payment summary
function loadPaymentSummary() {
    fetch('api/reports.php?type=payments')
        .then(response => response.json())
        .then(data => {
            document.getElementById('totalCollections').textContent = 
                '₱' + (data.total_collections || 0).toFixed(2);
            document.getElementById('todayCollections').textContent = 
                '₱' + (data.today_collections || 0).toFixed(2);
            document.getElementById('pendingPayments').textContent = 
                '₱' + (data.pending_amount || 0).toFixed(2);
            document.getElementById('pendingCount').textContent = 
                (data.pending_count || 0) + ' pending';
            document.getElementById('overduePenalties').textContent = 
                '₱' + (data.overdue_amount || 0).toFixed(2);
        })
        .catch(error => console.error('Error loading summary:', error));
}

// Load participants for dropdown
function loadParticipantsForDropdown() {
    fetch('api/participants.php?status=Active')
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('participantSelect');
            let options = '<option value="">Select Participant</option>';
            
            data.forEach(p => {
                // API returns pending_penalties (sum of unpaid penalties)
                const balance = parseFloat(p.pending_penalties || 0);
                const balanceText = balance > 0 ? ` (Balance: ₱${balance.toFixed(2)})` : '';
                options += `<option value="${p.id}" data-balance="${balance}">
                    ${escapeHtml(p.first_name)} ${escapeHtml(p.last_name)} - ${escapeHtml(p.role)}${balanceText}
                </option>`;
            });
            
            select.innerHTML = options;
        })
        .catch(err => console.error('Error loading participants:', err));
}

// Load participant balance
function loadParticipantBalance() {
    const select = document.getElementById('participantSelect');
    const selectedOption = select.options[select.selectedIndex];
    const balance = selectedOption.dataset.balance || 0;
    const participantId = select.value;
    
    const balanceDiv = document.getElementById('participantBalanceInfo');
    
    if (participantId) {
        if (balance > 0) {
            balanceDiv.className = 'alert alert-warning';
            balanceDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Outstanding Balance:</strong> ₱${parseFloat(balance).toFixed(2)}
                <br><small>This participant has pending penalties</small>
            `;
            document.getElementById('paymentAmount').value = balance;
        } else {
            balanceDiv.className = 'alert alert-success';
            balanceDiv.innerHTML = `
                <i class="fas fa-check-circle"></i>
                <strong>No outstanding balance</strong>
                <br><small>All penalties have been paid</small>
            `;
            document.getElementById('paymentAmount').value = '200.00';
        }
        
        // Load incomplete attendance records
        loadIncompleteAttendance(participantId);
    } else {
        balanceDiv.className = 'alert alert-info';
        balanceDiv.innerHTML = '<i class="fas fa-info-circle"></i> Select a participant to view balance';
    }
}

// Load incomplete attendance for participant
function loadIncompleteAttendance(participantId) {
    fetch(`api/attendance.php?participant_id=${participantId}&status=Incomplete`)
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('attendanceSelect');
            let options = '<option value="">None - General Payment</option>';
            
            data.forEach(a => {
                options += `<option value="${a.id}">
                    ${a.date} - ${a.total_minutes || 0} min (Penalty: ₱200)
                </option>`;
            });
            
            select.innerHTML = options;
            
            if (data.length > 0) {
                document.getElementById('attendanceSelectRow').style.display = 'block';
            }
        });
}

// Toggle payment type
function togglePaymentType() {
    const type = document.getElementById('paymentType').value;
    const amountInput = document.getElementById('paymentAmount');
    const attendanceRow = document.getElementById('attendanceSelectRow');
    
    if (type === 'penalty') {
        amountInput.value = '200.00';
        attendanceRow.style.display = 'block';
    } else {
        amountInput.value = '';
        attendanceRow.style.display = 'none';
    }
}

// Record payment
function recordPayment(event) {
    event.preventDefault();
    
    const form = document.getElementById('paymentForm');
    const formData = new FormData(form);

    // Also send the payment_type field (it has no name attribute in the select)
    formData.append('payment_type', document.getElementById('paymentType').value);
    
    showLoading();
    
    fetch('api/payments.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Grab raw text first so we can show PHP errors if JSON parse fails
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch(e) {
                console.error('Non-JSON response from server:', text);
                throw new Error('Server returned an invalid response. Check the browser console for details.');
            }
        });
    })
    .then(data => {
        hideLoading();
        
        if (data.success) {
            showToast('Payment recorded successfully!', 'success');
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('recordPaymentModal'));
            modal.hide();
            
            // Reset form
            form.reset();
            document.getElementById('paymentType').value = 'penalty';
            document.getElementById('paymentAmount').value = '200.00';
            document.getElementById('attendanceSelectRow').style.display = 'none';
            document.getElementById('participantBalanceInfo').className = 'alert alert-info';
            document.getElementById('participantBalanceInfo').innerHTML =
                '<i class="fas fa-info-circle"></i> Select a participant to view balance';
            
            // Reload data
            loadPayments();
            loadPaymentSummary();
            loadParticipantsForDropdown();
            
            // Offer to print receipt
            if (data.payment_id) {
                setTimeout(() => {
                    if (confirm('Payment recorded! Would you like to print the receipt?')) {
                        printReceipt(data.payment_id);
                    }
                }, 500);
            }
        } else {
            showToast(data.message || 'Error recording payment', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showToast(error.message || 'Network error occurred', 'error');
        console.error('recordPayment error:', error);
    });
}

// View payment details
function viewPayment(id) {
    currentPaymentId = id;
    const modal = new bootstrap.Modal(document.getElementById('viewPaymentModal'));
    modal.show();
    
    fetch(`api/payments.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            const detailsDiv = document.getElementById('paymentDetails');
            
            if (data.length > 0) {
                const p = data[0];
                const statusBadge = {
                    'Paid': 'success',
                    'Pending': 'warning',
                    'Waived': 'secondary',
                    'Cancelled': 'danger'
                }[p.status] || 'secondary';
                
                detailsDiv.innerHTML = `
                    <div class="text-center mb-3">
                        <h5>Receipt #${escapeHtml(p.receipt_number)}</h5>
                    </div>
                    <table class="table table-sm">
                        <tr><th width="40%">Date:</th><td>${formatDate(p.payment_date)}</td></tr>
                        <tr><th>Participant:</th><td>${escapeHtml(p.participant_name)}</td></tr>
                        <tr><th>Role:</th><td>${escapeHtml(p.role)}</td></tr>
                        <tr><th>Amount:</th><td><strong>₱${parseFloat(p.amount).toFixed(2)}</strong></td></tr>
                        <tr><th>Method:</th><td>${escapeHtml(p.payment_method)}</td></tr>
                        <tr><th>Reference:</th><td>${p.reference_number ? escapeHtml(p.reference_number) : 'N/A'}</td></tr>
                        <tr><th>Status:</th><td><span class="badge bg-${statusBadge}">${p.status}</span></td></tr>
                        <tr><th>Recorded:</th><td>${new Date(p.created_at).toLocaleString()}</td></tr>
                        ${p.notes ? `<tr><th>Notes:</th><td>${escapeHtml(p.notes)}</td></tr>` : ''}
                    </table>
                `;
            }
        })
        .catch(error => {
            document.getElementById('paymentDetails').innerHTML = `
                <div class="alert alert-danger">Error loading payment details</div>
            `;
        });
}

// Mark as paid
function markAsPaid(id) {
    confirmAction('Mark this payment as paid?', function() {
        showLoading();
        
        fetch('api/payments.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, status: 'Paid' })
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.success) {
                showToast('Payment marked as paid', 'success');
                loadPayments();
                loadPaymentSummary();
            } else {
                showToast(data.message || 'Error updating payment', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            showToast('Network error occurred', 'error');
        });
    });
}

// Cancel payment
function cancelPayment(id) {
    confirmAction('Cancel this payment record?', function() {
        showLoading();
        
        fetch('api/payments.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, status: 'Cancelled' })
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.success) {
                showToast('Payment cancelled', 'success');
                loadPayments();
                loadPaymentSummary();
            } else {
                showToast(data.message || 'Error cancelling payment', 'error');
            }
        });
    });
}

// Print receipt
function printReceipt(id) {
    window.open(`receipt.php?id=${id}`, '_blank', 'width=500,height=650');
}

// Print current receipt
function printCurrentReceipt() {
    if (currentPaymentId) {
        printReceipt(currentPaymentId);
    }
}

// Export payments
function exportPayments() {
    const params = new URLSearchParams();
    
    const dateRange = document.getElementById('dateRange').value;
    if (dateRange === 'custom') {
        params.append('date_from', document.getElementById('dateFrom').value);
        params.append('date_to', document.getElementById('dateTo').value);
    } else if (dateRange !== 'all') {
        params.append('date_range', dateRange);
    }
    
    const status = document.getElementById('paymentStatus').value;
    if (status) params.append('status', status);
    
    window.location.href = `api/reports.php?type=export&export_type=payments&format=csv&${params.toString()}`;
}

// Helper functions
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function getPaymentMethodIcon(method) {
    const icons = {
        'Cash': '💵',
        'GCash': '📱',
        'Bank Transfer': '🏦',
        'Other': '💰'
    };
    return icons[method] || '💰';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include 'includes/footer.php'; ?>