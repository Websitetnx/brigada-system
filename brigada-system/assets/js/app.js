/**
 * Brigada Eskwela Monitoring System
 * Main Application JavaScript
 * Version: 1.0
 */

// Main Application Object
const BrigadaApp = {
    // API Base URL
    apiUrl: 'api/',
    
    // Initialize application
    init: function() {
        this.setupEventListeners();
        this.updateDateTime();
        this.loadNotifications();
        this.startAutoRefresh();
    },
    
    // Setup global event listeners
    setupEventListeners: function() {
        // Search functionality with debounce
        const searchInput = document.getElementById('globalSearch');
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce(this.handleSearch.bind(this), 300));
        }
        
        // Date range picker
        const dateRange = document.getElementById('dateRange');
        if (dateRange) {
            dateRange.addEventListener('change', this.handleDateRangeChange.bind(this));
        }
        
        // Setup AJAX forms
        this.setupAjaxForms();
    },
    
    // Setup AJAX form submissions
    setupAjaxForms: function() {
        document.querySelectorAll('.ajax-form').forEach(form => {
            form.addEventListener('submit', this.handleAjaxSubmit.bind(this));
        });
    },
    
    // Handle AJAX form submit
    handleAjaxSubmit: function(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const url = form.action;
        const method = form.method || 'POST';
        
        this.showLoading();
        
        fetch(url, {
            method: method,
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            this.hideLoading();
            
            if (data.success) {
                this.showToast(data.message, 'success');
                
                if (data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1500);
                } else if (form.dataset.reload === 'true') {
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                }
            } else {
                this.showToast(data.message || 'An error occurred', 'error');
            }
        })
        .catch(error => {
            this.hideLoading();
            this.showToast('Network error occurred', 'error');
            console.error('Error:', error);
        });
    },
    
    // Debounce function for search
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    // Handle global search
    handleSearch: function(event) {
        const query = event.target.value;
        if (query.length >= 2) {
            this.searchParticipants(query);
        }
    },
    
    // Search participants
    searchParticipants: function(query) {
        fetch(this.apiUrl + 'participants.php?search=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(data => {
                this.updateSearchResults(data);
            })
            .catch(error => console.error('Search error:', error));
    },
    
    // Update search results
    updateSearchResults: function(results) {
        const resultsContainer = document.getElementById('searchResults');
        if (!resultsContainer) return;
        
        if (results.length === 0) {
            resultsContainer.innerHTML = '<p class="text-muted p-3">No results found</p>';
            return;
        }
        
        const html = results.map(participant => `
            <div class="search-result-item p-3 border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${this.escapeHtml(participant.first_name)} ${this.escapeHtml(participant.last_name)}</strong>
                        <br>
                        <small class="text-muted">${this.escapeHtml(participant.role)}</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" 
                            onclick="BrigadaApp.viewParticipant(${participant.id})">
                        View
                    </button>
                </div>
            </div>
        `).join('');
        
        resultsContainer.innerHTML = html;
    },
    
    // Escape HTML to prevent XSS
    escapeHtml: function(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    // Handle date range change
    handleDateRangeChange: function(event) {
        const range = event.target.value;
        let dateFrom, dateTo;
        
        const today = new Date();
        
        switch(range) {
            case 'today':
                dateFrom = this.formatDate(today);
                dateTo = this.formatDate(today);
                break;
            case 'week':
                const weekAgo = new Date(today);
                weekAgo.setDate(today.getDate() - 7);
                dateFrom = this.formatDate(weekAgo);
                dateTo = this.formatDate(new Date());
                break;
            case 'month':
                const monthAgo = new Date(today);
                monthAgo.setMonth(today.getMonth() - 1);
                dateFrom = this.formatDate(monthAgo);
                dateTo = this.formatDate(new Date());
                break;
            default:
                return;
        }
        
        if (dateFrom && dateTo) {
            window.location.href = '?date_from=' + dateFrom + '&date_to=' + dateTo;
        }
    },
    
    // Format date to YYYY-MM-DD
    formatDate: function(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    },
    
    // Update date and time display
    updateDateTime: function() {
        const timeElement = document.getElementById('currentTime');
        const dateElement = document.getElementById('currentDate');
        
        if (timeElement || dateElement) {
            const now = new Date();
            
            if (timeElement) {
                timeElement.textContent = now.toLocaleTimeString();
            }
            
            if (dateElement) {
                dateElement.textContent = now.toLocaleDateString('en-US', {
                    weekday: 'short',
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            }
            
            setTimeout(() => this.updateDateTime(), 1000);
        }
    },
    
    // Load notifications
    loadNotifications: function() {
        fetch(this.apiUrl + 'notifications.php')
            .then(response => response.json())
            .then(data => {
                this.updateNotifications(data);
            })
            .catch(error => console.error('Notifications error:', error));
    },
    
    // Update notifications display
    updateNotifications: function(notifications) {
        const countElement = document.getElementById('notificationCount');
        const listElement = document.getElementById('notificationList');
        
        if (!countElement || !listElement) return;
        
        const count = notifications.length || 0;
        countElement.textContent = count;
        
        if (count > 0) {
            const listHtml = notifications.map(notif => `
                <li><a class="dropdown-item" href="#">
                    <small>${this.escapeHtml(notif.message || 'New notification')}</small>
                </a></li>
            `).join('');
            
            listElement.innerHTML = `
                <li><h6 class="dropdown-header">Notifications (${count})</h6></li>
                <li><hr class="dropdown-divider"></li>
                ${listHtml}
            `;
        } else {
            listElement.innerHTML = `
                <li><h6 class="dropdown-header">Notifications</h6></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-muted" href="#">No new notifications</a></li>
            `;
        }
    },
    
    // Start auto-refresh for real-time updates
    startAutoRefresh: function() {
        setInterval(() => {
            if (window.location.pathname.includes('dashboard.php')) {
                this.loadDashboardData();
            }
            this.loadNotifications();
        }, 30000); // Refresh every 30 seconds
    },
    
    // Load dashboard data
    loadDashboardData: function() {
        fetch(this.apiUrl + 'reports.php?type=summary')
            .then(response => response.json())
            .then(data => {
                this.updateDashboardStats(data);
            })
            .catch(error => console.error('Dashboard data error:', error));
    },
    
    // Update dashboard statistics
    updateDashboardStats: function(data) {
        const elements = {
            'totalParticipants': data.total_participants || 0,
            'activeToday': data.active_participants || 0,
            'totalHours': Math.round(data.total_hours_served || 0),
            'collections': this.formatCurrency(data.total_collections || 0)
        };
        
        for (const [id, value] of Object.entries(elements)) {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        }
    },
    
    // View participant details
    viewParticipant: function(participantId) {
        window.location.href = 'participants.php?id=' + participantId;
    },
    
    // Process quick attendance
    processAttendance: function(participantId) {
        fetch(this.apiUrl + 'attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ participant_identifier: participantId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showToast(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                this.showToast(data.message, 'error');
            }
        })
        .catch(error => {
            this.showToast('Error processing attendance', 'error');
            console.error('Error:', error);
        });
    },
    
    // Format currency
    formatCurrency: function(amount) {
        return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    },
    
    // Format duration
    formatDuration: function(minutes) {
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        return hours + 'h ' + mins + 'm';
    },
    
    // Show toast notification
    showToast: function(message, type) {
        type = type || 'info';
        
        const bgClass = {
            'success': 'bg-success',
            'error': 'bg-danger',
            'warning': 'bg-warning',
            'info': 'bg-info'
        }[type] || 'bg-info';
        
        const toastContainer = document.createElement('div');
        toastContainer.style.position = 'fixed';
        toastContainer.style.top = '20px';
        toastContainer.style.right = '20px';
        toastContainer.style.zIndex = '9999';
        toastContainer.style.maxWidth = '350px';
        
        toastContainer.innerHTML = `
            <div class="toast show ${bgClass} text-white" role="alert">
                <div class="toast-header">
                    <strong class="me-auto">Brigada System</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    ${this.escapeHtml(message)}
                </div>
            </div>
        `;
        
        document.body.appendChild(toastContainer);
        
        setTimeout(() => {
            toastContainer.remove();
        }, 4000);
    },
    
    // Confirm dialog
    confirmAction: function(message, callback) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Are you sure?',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, proceed!'
            }).then((result) => {
                if (result.isConfirmed && callback) {
                    callback();
                }
            });
        } else {
            if (confirm(message)) {
                callback();
            }
        }
    },
    
    // Show loading spinner
    showLoading: function() {
        const spinner = document.getElementById('loadingSpinner');
        if (spinner) {
            spinner.style.display = 'flex';
        }
    },
    
    // Hide loading spinner
    hideLoading: function() {
        const spinner = document.getElementById('loadingSpinner');
        if (spinner) {
            spinner.style.display = 'none';
        }
    },
    
    // Print element
    printElement: function(elementId) {
        const printContent = document.getElementById(elementId);
        if (!printContent) return;
        
        const originalContent = document.body.innerHTML;
        const printHTML = printContent.innerHTML;
        
        document.body.innerHTML = printHTML;
        window.print();
        document.body.innerHTML = originalContent;
        location.reload();
    },
    
    // Export to CSV
    exportToCSV: function(data, filename) {
        if (!data || data.length === 0) {
            this.showToast('No data to export', 'warning');
            return;
        }
        
        const csv = this.convertToCSV(data);
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', filename || 'export.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    },
    
    // Convert array to CSV
    convertToCSV: function(objArray) {
        const array = typeof objArray !== 'object' ? JSON.parse(objArray) : objArray;
        let str = '';
        
        if (array.length > 0) {
            const headers = Object.keys(array[0]);
            str += headers.join(',') + '\r\n';
            
            for (let i = 0; i < array.length; i++) {
                let line = '';
                for (const header of headers) {
                    if (line !== '') line += ',';
                    const cell = array[i][header] || '';
                    line += '"' + String(cell).replace(/"/g, '""') + '"';
                }
                str += line + '\r\n';
            }
        }
        
        return str;
    },
    
    // Print receipt
    printReceipt: function(paymentId) {
        window.open('receipt.php?id=' + paymentId, '_blank', 'width=500,height=600');
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    BrigadaApp.init();
    
    // Initialize DataTables if jQuery and DataTables are available
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
        $('.datatable').DataTable({
            pageLength: 25,
            responsive: true,
            order: [[0, 'desc']],
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                infoEmpty: "No entries found",
                infoFiltered: "(filtered from _MAX_ total entries)"
            }
        });
    }
    
    // Handle keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + S for quick search focus
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            const searchInput = document.querySelector('input[type="search"]');
            if (searchInput) searchInput.focus();
        }
        
        // Ctrl + N for new participant
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            window.location.href = 'participants.php?action=new';
        }
        
        // Ctrl + A for attendance
        if (e.ctrlKey && e.key === 'a') {
            e.preventDefault();
            window.location.href = 'attendance.php';
        }
    });
});

// Export for global use
window.BrigadaApp = BrigadaApp;