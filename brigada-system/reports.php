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
        <h2><i class="fas fa-chart-bar text-info"></i> Reports & Analytics</h2>
        <div>
            <button class="btn btn-success me-2" onclick="exportCurrentReport()">
                <i class="fas fa-file-excel"></i> Export to Excel
            </button>
            <button class="btn btn-danger me-2" onclick="generatePDF()">
                <i class="fas fa-file-pdf"></i> Generate PDF
            </button>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>
    </div>
    
    <!-- Report Type Selection -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Report Type</label>
                    <select class="form-select" id="reportType" onchange="changeReportType()">
                        <option value="summary">📊 Summary Report</option>
                        <option value="attendance">📅 Attendance Report</option>
                        <option value="payments">💰 Payment Report</option>
                        <option value="participants">👥 Participant Report</option>
                        <option value="penalties">⚠️ Penalty Report</option>
                        <option value="performance">🏆 Performance Report</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Date Range</label>
                    <select class="form-select" id="quickRange" onchange="setQuickRange()">
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="week">This Week</option>
                        <option value="month" selected>This Month</option>
                        <option value="quarter">This Quarter</option>
                        <option value="year">This Year</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Date From</label>
                    <input type="date" class="form-control" id="dateFrom" 
                           value="<?php echo date('Y-m-01'); ?>" onchange="loadReport()">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Date To</label>
                    <input type="date" class="form-control" id="dateTo" 
                           value="<?php echo date('Y-m-d'); ?>" onchange="loadReport()">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" onclick="loadReport()">
                        <i class="fas fa-sync-alt"></i> Generate Report
                    </button>
                </div>
            </div>
            
            <!-- Additional Filters (Dynamic) -->
            <div class="row mt-3" id="additionalFilters">
                <!-- Dynamic filters will appear here -->
            </div>
        </div>
    </div>
    
    <!-- Summary Statistics Cards -->
    <div class="row mb-4" id="summaryCards">
        <!-- Dynamically loaded -->
    </div>
    
    <!-- Charts Section -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-line"></i> <span id="chart1Title">Attendance Trend</span></h5>
                </div>
                <div class="card-body">
                    <canvas id="chart1" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar"></i> <span id="chart2Title">Payment Analysis</span></h5>
                </div>
                <div class="card-body">
                    <canvas id="chart2" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Second Row Charts -->
    <div class="row mb-4" id="secondRowCharts">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-pie"></i> <span id="chart3Title">Role Distribution</span></h6>
                </div>
                <div class="card-body">
                    <canvas id="chart3" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-doughnut"></i> <span id="chart4Title">Status Breakdown</span></h6>
                </div>
                <div class="card-body">
                    <canvas id="chart4" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-trophy"></i> Top Performers</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="topPerformersList">
                        <!-- Dynamic content -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Detailed Report Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-table"></i> <span id="tableTitle">Detailed Report</span></h5>
            <div>
                <button class="btn btn-sm btn-outline-secondary me-2" onclick="toggleTableFullscreen()">
                    <i class="fas fa-expand"></i>
                </button>
                <button class="btn btn-sm btn-outline-primary" onclick="copyTableData()">
                    <i class="fas fa-copy"></i> Copy
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" id="reportTableContainer" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-hover table-striped mb-0" id="reportTable">
                    <thead class="table-light sticky-top">
                        <tr id="reportTableHeader">
                            <th>Loading...</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        <tr>
                            <td colspan="10" class="text-center py-4">
                                <div class="spinner-border text-info" role="status"></div>
                                <p class="mt-2">Loading report data...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted" id="tableInfo">Showing 0 records</small>
                <div>
                    <button class="btn btn-sm btn-outline-success" onclick="exportTableToCSV()">
                        <i class="fas fa-download"></i> Export Table
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
let chart1, chart2, chart3, chart4;
let currentReportData = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadReport();
});

// Set quick date range
function setQuickRange() {
    const range = document.getElementById('quickRange').value;
    const today = new Date();
    let dateFrom, dateTo;
    
    switch(range) {
        case 'today':
            dateFrom = dateTo = formatDate(today);
            break;
        case 'yesterday':
            const yesterday = new Date(today);
            yesterday.setDate(today.getDate() - 1);
            dateFrom = dateTo = formatDate(yesterday);
            break;
        case 'week':
            const weekStart = new Date(today);
            weekStart.setDate(today.getDate() - today.getDay());
            dateFrom = formatDate(weekStart);
            dateTo = formatDate(today);
            break;
        case 'month':
            dateFrom = formatDate(new Date(today.getFullYear(), today.getMonth(), 1));
            dateTo = formatDate(today);
            break;
        case 'quarter':
            const quarter = Math.floor(today.getMonth() / 3);
            dateFrom = formatDate(new Date(today.getFullYear(), quarter * 3, 1));
            dateTo = formatDate(today);
            break;
        case 'year':
            dateFrom = formatDate(new Date(today.getFullYear(), 0, 1));
            dateTo = formatDate(today);
            break;
        case 'custom':
            return;
    }
    
    if (dateFrom) document.getElementById('dateFrom').value = dateFrom;
    if (dateTo) document.getElementById('dateTo').value = dateTo;
    
    loadReport();
}

// Format date to YYYY-MM-DD
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Change report type
function changeReportType() {
    const type = document.getElementById('reportType').value;
    const filtersDiv = document.getElementById('additionalFilters');
    
    // Update chart titles
    const titles = {
        'summary': ['Attendance Trend', 'Payment Collections', 'Role Distribution', 'Status Breakdown'],
        'attendance': ['Daily Attendance', 'Hourly Distribution', 'Completion Rate', 'Penalty Analysis'],
        'payments': ['Collection Trend', 'Payment Methods', 'Status Distribution', 'Daily Collections'],
        'participants': ['Registration Trend', 'Role Distribution', 'Activity Level', 'Hours Served'],
        'penalties': ['Penalty Trend', 'Payment Status', 'By Role', 'Collection Rate'],
        'performance': ['Top Performers', 'Hours Distribution', 'Completion Rate', 'Rankings']
    };
    
    if (titles[type]) {
        document.getElementById('chart1Title').textContent = titles[type][0];
        document.getElementById('chart2Title').textContent = titles[type][1];
        document.getElementById('chart3Title').textContent = titles[type][2];
        document.getElementById('chart4Title').textContent = titles[type][3];
    }
    
    // Add specific filters
    let filterHtml = '';
    
    if (type === 'attendance') {
        filterHtml = `
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" id="attendanceStatus" onchange="loadReport()">
                    <option value="">All</option>
                    <option value="Complete">Complete (2+ hrs)</option>
                    <option value="Incomplete">Incomplete (< 2 hrs)</option>
                    <option value="No Show">No Show</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Role</label>
                <select class="form-select" id="roleFilter" onchange="loadReport()">
                    <option value="">All Roles</option>
                    <option value="Student">Student</option>
                    <option value="Parent">Parent</option>
                    <option value="Teacher">Teacher</option>
                    <option value="Volunteer">Volunteer</option>
                </select>
            </div>
        `;
    } else if (type === 'payments') {
        filterHtml = `
            <div class="col-md-3">
                <label class="form-label">Payment Status</label>
                <select class="form-select" id="paymentStatusFilter" onchange="loadReport()">
                    <option value="">All</option>
                    <option value="Paid">Paid</option>
                    <option value="Pending">Pending</option>
                    <option value="Waived">Waived</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Payment Method</label>
                <select class="form-select" id="paymentMethodFilter" onchange="loadReport()">
                    <option value="">All Methods</option>
                    <option value="Cash">Cash</option>
                    <option value="GCash">GCash</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                </select>
            </div>
        `;
    }
    
    filtersDiv.innerHTML = filterHtml;
    loadReport();
}

// Load report data
function loadReport() {
    const reportType = document.getElementById('reportType').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    
    showLoading();
    
    const params = new URLSearchParams({
        type: reportType,
        date_from: dateFrom,
        date_to: dateTo
    });
    
    // Add additional filters
    const attendanceStatus = document.getElementById('attendanceStatus');
    if (attendanceStatus && attendanceStatus.value) {
        params.append('status', attendanceStatus.value);
    }
    
    const roleFilter = document.getElementById('roleFilter');
    if (roleFilter && roleFilter.value) {
        params.append('role', roleFilter.value);
    }
    
    const paymentStatus = document.getElementById('paymentStatusFilter');
    if (paymentStatus && paymentStatus.value) {
        params.append('payment_status', paymentStatus.value);
    }
    
    fetch('api/reports.php?' + params.toString())
        .then(response => response.json())
        .then(data => {
            hideLoading();
            currentReportData = data;
            updateSummaryCards(data, reportType);
            updateCharts(data, reportType);
            updateReportTable(data, reportType);
            updateTopPerformers(data);
        })
        .catch(error => {
            hideLoading();
            console.error('Error:', error);
            showToast('Error loading report data', 'error');
        });
}

// Update summary cards
function updateSummaryCards(data, reportType) {
    const cardsDiv = document.getElementById('summaryCards');
    let html = '';
    
    if (reportType === 'summary') {
        html = `
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h6>Total Participants</h6>
                        <h2>${data.total_participants || 0}</h2>
                        <small>Active: ${data.active_participants || 0}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6>Total Hours Served</h6>
                        <h2>${Math.round(data.total_hours_served || 0)}</h2>
                        <small>${data.completed_sessions || 0} completed sessions</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h6>Total Collections</h6>
                        <h2>₱${(data.total_collections || 0).toFixed(2)}</h2>
                        <small>Pending: ₱${(data.pending_collections || 0).toFixed(2)}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h6>Penalties Issued</h6>
                        <h2>${data.penalties_applied || 0}</h2>
                        <small>Total: ₱${(data.total_penalties || 0).toFixed(2)}</small>
                    </div>
                </div>
            </div>
        `;
    } else if (reportType === 'attendance') {
        html = `
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h6>Total Check-ins</h6>
                        <h2>${data.total_checkins || 0}</h2>
                        <small>Unique: ${data.unique_participants || 0}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6>Completion Rate</h6>
                        <h2>${data.completion_rate || 0}%</h2>
                        <small>${data.completed_sessions || 0}/${data.total_sessions || 0}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h6>Avg Duration</h6>
                        <h2>${Math.round(data.avg_minutes || 0)} min</h2>
                        <small>Target: 120 min</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h6>Penalty Rate</h6>
                        <h2>${data.penalty_rate || 0}%</h2>
                        <small>${data.penalties || 0} incomplete</small>
                    </div>
                </div>
            </div>
        `;
    } else if (reportType === 'payments') {
        html = `
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6>Total Collected</h6>
                        <h2>₱${(data.total_collected || 0).toFixed(2)}</h2>
                        <small>${data.total_transactions || 0} transactions</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h6>Pending Amount</h6>
                        <h2>₱${(data.pending_amount || 0).toFixed(2)}</h2>
                        <small>${data.pending_count || 0} pending</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h6>Avg Transaction</h6>
                        <h2>₱${(data.avg_transaction || 0).toFixed(2)}</h2>
                        <small>Per payment</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h6>Collection Rate</h6>
                        <h2>${data.collection_rate || 0}%</h2>
                        <small>Of total penalties</small>
                    </div>
                </div>
            </div>
        `;
    }
    
    cardsDiv.innerHTML = html;
}

// Update charts
function updateCharts(data, reportType) {
    // Destroy existing charts
    if (chart1) chart1.destroy();
    if (chart2) chart2.destroy();
    if (chart3) chart3.destroy();
    if (chart4) chart4.destroy();
    
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' }
        }
    };
    
    // Chart 1 - Attendance Trend
    const ctx1 = document.getElementById('chart1').getContext('2d');
    const dailyData = data.daily_breakdown || [];
    
    chart1 = new Chart(ctx1, {
        type: 'line',
        data: {
            labels: dailyData.map(d => d.date),
            datasets: [{
                label: 'Participants',
                data: dailyData.map(d => d.participants || 0),
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                tension: 0.3
            }, {
                label: 'Completed',
                data: dailyData.map(d => d.completed || 0),
                borderColor: 'rgb(40, 167, 69)',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.3
            }]
        },
        options: chartOptions
    });
    
    // Chart 2 - Payment/Collections
    const ctx2 = document.getElementById('chart2').getContext('2d');
    
    chart2 = new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: dailyData.map(d => d.date),
            datasets: [{
                label: 'Collections (₱)',
                data: dailyData.map(d => d.collections || 0),
                backgroundColor: 'rgba(255, 193, 7, 0.5)',
                borderColor: 'rgb(255, 193, 7)',
                borderWidth: 1
            }, {
                label: 'Penalties',
                data: dailyData.map(d => d.penalties || 0),
                backgroundColor: 'rgba(220, 53, 69, 0.5)',
                borderColor: 'rgb(220, 53, 69)',
                borderWidth: 1
            }]
        },
        options: chartOptions
    });
    
    // Chart 3 - Role Distribution (Pie)
    const ctx3 = document.getElementById('chart3').getContext('2d');
    const roleData = data.role_distribution || {};
    
    chart3 = new Chart(ctx3, {
        type: 'pie',
        data: {
            labels: Object.keys(roleData),
            datasets: [{
                data: Object.values(roleData),
                backgroundColor: [
                    'rgb(54, 162, 235)',
                    'rgb(75, 192, 192)',
                    'rgb(255, 206, 86)',
                    'rgb(255, 99, 132)',
                    'rgb(153, 102, 255)'
                ]
            }]
        },
        options: chartOptions
    });
    
    // Chart 4 - Status Breakdown (Doughnut)
    const ctx4 = document.getElementById('chart4').getContext('2d');
    const statusData = data.status_breakdown || { Complete: 0, Incomplete: 0 };
    
    chart4 = new Chart(ctx4, {
        type: 'doughnut',
        data: {
            labels: Object.keys(statusData),
            datasets: [{
                data: Object.values(statusData),
                backgroundColor: [
                    'rgb(40, 167, 69)',
                    'rgb(255, 193, 7)',
                    'rgb(108, 117, 125)'
                ]
            }]
        },
        options: chartOptions
    });
}

// Update report table
function updateReportTable(data, reportType) {
    const headerRow = document.getElementById('reportTableHeader');
    const tbody = document.getElementById('reportTableBody');
    const tableTitle = document.getElementById('tableTitle');
    const tableInfo = document.getElementById('tableInfo');
    
    let headers = [];
    let rows = [];
    
    if (reportType === 'summary') {
        tableTitle.textContent = 'Participant Summary';
        headers = ['Rank', 'Name', 'Role', 'Days', 'Hours', 'Completed', 'Penalties', 'Balance'];
        
        if (data.participant_summary) {
            data.participant_summary.forEach((p, i) => {
                rows.push([
                    i + 1,
                    p.name,
                    p.role,
                    p.days_attended || 0,
                    p.total_hours || 0,
                    p.completed_sessions || 0,
                    '₱' + (p.penalty_amount || 0).toFixed(2),
                    '₱' + (p.balance || 0).toFixed(2)
                ]);
            });
        }
    } else if (reportType === 'attendance') {
        tableTitle.textContent = 'Attendance Records';
        headers = ['Date', 'Participant', 'Role', 'Time In', 'Time Out', 'Duration', 'Status', 'Penalty'];
        
        if (data.attendance_records) {
            data.attendance_records.forEach(r => {
                rows.push([
                    r.date,
                    r.participant_name,
                    r.role,
                    r.sign_in_time ? new Date(r.sign_in_time).toLocaleTimeString() : '-',
                    r.sign_out_time ? new Date(r.sign_out_time).toLocaleTimeString() : '-',
                    (r.total_minutes || 0) + ' min',
                    r.status,
                    r.penalty_applied ? '₱200' : '-'
                ]);
            });
        }
    } else if (reportType === 'payments') {
        tableTitle.textContent = 'Payment Transactions';
        headers = ['Receipt #', 'Date', 'Participant', 'Amount', 'Method', 'Reference', 'Status'];
        
        if (data.payments) {
            data.payments.forEach(p => {
                rows.push([
                    p.receipt_number || 'N/A',
                    p.payment_date,
                    p.participant_name,
                    '₱' + parseFloat(p.amount).toFixed(2),
                    p.payment_method,
                    p.reference_number || '-',
                    p.status
                ]);
            });
        }
    } else if (reportType === 'penalties') {
        tableTitle.textContent = 'Penalty Report';
        headers = ['Participant', 'Role', 'Date', 'Minutes', 'Amount', 'Status', 'Paid Date'];
        
        if (data.penalties) {
            data.penalties.forEach(p => {
                rows.push([
                    p.participant_name,
                    p.role,
                    p.date,
                    p.minutes_served + ' min',
                    '₱200',
                    p.payment_status || 'Unpaid',
                    p.paid_date || '-'
                ]);
            });
        }
    }
    
    // Render header
    headerRow.innerHTML = headers.map(h => `<th>${h}</th>`).join('');
    
    // Render body
    if (rows.length > 0) {
        tbody.innerHTML = rows.map(row => 
            `<tr>${row.map(cell => `<td>${escapeHtml(String(cell))}</td>`).join('')}</tr>`
        ).join('');
        tableInfo.textContent = `Showing ${rows.length} records`;
    } else {
        tbody.innerHTML = `<tr><td colspan="${headers.length}" class="text-center text-muted py-4">No data available for the selected period</td></tr>`;
        tableInfo.textContent = 'No records found';
    }
}

// Update top performers
function updateTopPerformers(data) {
    const listDiv = document.getElementById('topPerformersList');
    const performers = data.top_performers || [];
    
    if (performers.length === 0) {
        listDiv.innerHTML = '<div class="list-group-item text-center text-muted">No data available</div>';
        return;
    }
    
    let html = '';
    performers.slice(0, 5).forEach((p, i) => {
        const medal = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : `${i+1}.`;
        html += `
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="me-2">${medal}</span>
                        <strong>${escapeHtml(p.name)}</strong>
                        <br>
                        <small class="text-muted">${escapeHtml(p.role)}</small>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-primary">${p.total_hours || 0} hrs</span>
                        <br>
                        <small>${p.days_attended || 0} days</small>
                    </div>
                </div>
            </div>
        `;
    });
    
    listDiv.innerHTML = html;
}

// Export current report
function exportCurrentReport() {
    const reportType = document.getElementById('reportType').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    
    window.location.href = `api/reports.php?type=export&export_type=${reportType}&date_from=${dateFrom}&date_to=${dateTo}&format=csv`;
}

// Export table to CSV
function exportTableToCSV() {
    if (!currentReportData) {
        showToast('No data to export', 'warning');
        return;
    }
    
    const table = document.getElementById('reportTable');
    let csv = [];
    
    // Headers
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => headers.push(th.textContent));
    csv.push(headers.join(','));
    
    // Rows
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => row.push('"' + td.textContent.replace(/"/g, '""') + '"'));
        csv.push(row.join(','));
    });
    
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `report_${document.getElementById('reportType').value}_${Date.now()}.csv`;
    a.click();
}

// Generate PDF
function generatePDF() {
    window.print();
}

// Toggle table fullscreen
function toggleTableFullscreen() {
    const container = document.getElementById('reportTableContainer');
    
    if (container.style.maxHeight === 'none') {
        container.style.maxHeight = '400px';
    } else {
        container.style.maxHeight = 'none';
    }
}

// Copy table data
function copyTableData() {
    const table = document.getElementById('reportTable');
    const range = document.createRange();
    range.selectNode(table);
    window.getSelection().removeAllRanges();
    window.getSelection().addRange(range);
    
    try {
        document.execCommand('copy');
        showToast('Table data copied to clipboard', 'success');
    } catch (err) {
        showToast('Failed to copy data', 'error');
    }
    
    window.getSelection().removeAllRanges();
}

// Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Auto-refresh every 5 minutes
setInterval(loadReport, 300000);
</script>

<style>
.sticky-top {
    position: sticky;
    top: 0;
    z-index: 10;
    background: #f8f9fa;
}

#reportTableContainer::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

#reportTableContainer::-webkit-scrollbar-track {
    background: #f1f1f1;
}

#reportTableContainer::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

#reportTableContainer::-webkit-scrollbar-thumb:hover {
    background: #555;
}

@media print {
    .sidebar, .navbar-top, .btn, .card-header button, #additionalFilters {
        display: none !important;
    }
    
    .card {
        break-inside: avoid;
        border: 1px solid #ddd !important;
    }
}
</style>

<?php include 'includes/footer.php'; ?>