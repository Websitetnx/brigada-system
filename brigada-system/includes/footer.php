                    <!-- Page Content End -->
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Spinner (Fallback) -->
    <div class="loading-spinner" id="loadingSpinner" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Moment.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom JS -->
    <script src="assets/js/app.js"></script>
    
    <script>
        // =============================================
        // DARK MODE TOGGLE
        // =============================================
        function toggleDarkMode() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            updateDarkModeButton(newTheme);
            showToast('Dark mode ' + (newTheme === 'dark' ? 'enabled' : 'disabled'), 'info');
        }
        
        function updateDarkModeButton(theme) {
            const btn = document.getElementById('darkModeToggle');
            if (!btn) return;
            
            const icon = btn.querySelector('i');
            const span = btn.querySelector('span');
            
            if (theme === 'dark') {
                icon.className = 'fas fa-sun';
                span.textContent = 'Light Mode';
            } else {
                icon.className = 'fas fa-moon';
                span.textContent = 'Dark Mode';
            }
        }
        
        // Load saved theme on page load
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            updateDarkModeButton(savedTheme);
        });
        
        // =============================================
        // DATE & TIME UPDATES - synced to server timezone
        // =============================================
        let _serverTimeOffsetMs = 0; // difference: serverTime - browserTime
        let _timeSynced = false;

        function getServerNow() {
            // Returns a Date object adjusted to server's current time
            return new Date(Date.now() + _serverTimeOffsetMs);
        }

        function updateDateTime() {
            const now = getServerNow();
            const timeElement = document.getElementById('currentTime');
            const dateElement = document.getElementById('currentDate');

            if (timeElement) {
                timeElement.textContent = now.toLocaleTimeString('en-US', {
                    hour: '2-digit', minute: '2-digit', second: '2-digit'
                });
            }
            if (dateElement) {
                dateElement.textContent = now.toLocaleDateString('en-US', {
                    weekday: 'short', year: 'numeric', month: 'short', day: 'numeric'
                });
            }
        }

        // Sync to server time once on load
        function syncServerTime() {
            fetch('api/time.php')
                .then(r => r.json())
                .then(data => {
                    // Parse server's ISO time string as local (not UTC) by appending the offset
                    const serverDate = new Date(data.server_time + data.offset_string);
                    _serverTimeOffsetMs = serverDate.getTime() - Date.now();
                    _timeSynced = true;
                    updateDateTime();
                })
                .catch(() => { _timeSynced = true; /* fall back to browser time */ });
        }

        if (document.getElementById('currentTime') || document.getElementById('currentDate')) {
            syncServerTime();
            setInterval(updateDateTime, 1000);
        }
        
        // =============================================
        // LOADING SPINNER
        // =============================================
        function showLoading() {
            const spinner = document.getElementById('loadingSpinner');
            if (spinner) spinner.style.display = 'flex';
        }
        
        function hideLoading() {
            const spinner = document.getElementById('loadingSpinner');
            if (spinner) spinner.style.display = 'none';
        }
        
        // =============================================
        // TOAST NOTIFICATIONS
        // =============================================
        function showToast(message, type = 'info') {
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
            toastContainer.style.zIndex = '99999';
            toastContainer.style.maxWidth = '350px';
            toastContainer.style.animation = 'fadeInUp 0.3s ease';
            
            toastContainer.innerHTML = `
                <div class="toast show ${bgClass} text-white" role="alert">
                    <div class="toast-header">
                        <strong class="me-auto">Brigada System</strong>
                        <small>${new Date().toLocaleTimeString()}</small>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        ${escapeHtml(message)}
                    </div>
                </div>
            `;
            
            document.body.appendChild(toastContainer);
            
            setTimeout(() => {
                toastContainer.remove();
            }, 4000);
        }
        
        // =============================================
        // CONFIRM DIALOG
        // =============================================
        function confirmAction(message, callback) {
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
                if (confirm(message) && callback) {
                    callback();
                }
            }
        }
        
        // =============================================
        // NOTIFICATIONS
        // =============================================
        function fetchNotifications() {
            fetch('api/notifications.php')
                .then(response => response.json())
                .then(data => {
                    const countElement = document.getElementById('notificationCount');
                    const listElement = document.getElementById('notificationList');
                    
                    if (!countElement || !listElement) return;
                    
                    const count = data.length || 0;
                    countElement.textContent = count;
                    
                    if (count > 0) {
                        const listHtml = data.slice(0, 5).map(notif => `
                            <li><a class="dropdown-item" href="#">
                                <small>${escapeHtml(notif.message || 'New notification')}</small>
                                <br><small class="text-muted">${notif.date ? formatTimeAgo(notif.date) : 'Just now'}</small>
                            </a></li>
                        `).join('');
                        
                        listElement.innerHTML = `
                            <li><h6 class="dropdown-header">Notifications (${count})</h6></li>
                            <li><hr class="dropdown-divider"></li>
                            ${listHtml}
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="#"><small>View all notifications</small></a></li>
                        `;
                    } else {
                        listElement.innerHTML = `
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-muted" href="#">No new notifications</a></li>
                        `;
                    }
                })
                .catch(error => console.error('Error fetching notifications:', error));
        }
        
        // Fetch notifications on load and every 60 seconds
        if (document.getElementById('notificationCount')) {
            fetchNotifications();
            setInterval(fetchNotifications, 60000);
        }
        
        // =============================================
        // FORMATTING HELPERS
        // =============================================
        function formatCurrency(amount) {
            return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
        
        function formatDuration(minutes) {
            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;
            return hours + 'h ' + mins + 'm';
        }
        
        function formatTimeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);
            
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
            if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
            return Math.floor(diff / 86400) + ' days ago';
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // =============================================
        // PRINT FUNCTIONS
        // =============================================
        function printElement(elementId) {
            const printContent = document.getElementById(elementId);
            if (!printContent) return;
            
            const originalContent = document.body.innerHTML;
            const printHTML = printContent.innerHTML;
            
            document.body.innerHTML = printHTML;
            window.print();
            document.body.innerHTML = originalContent;
            location.reload();
        }
        
        function printReceipt(paymentId) {
            window.open('receipt.php?id=' + paymentId, '_blank', 'width=500,height=650');
        }
        
        function printQRCode(participantId) {
            window.open('qr.php?id=' + participantId, '_blank', 'width=450,height=550');
        }
        
        // =============================================
        // EXPORT FUNCTIONS
        // =============================================
        function exportToCSV(data, filename) {
            if (!data || data.length === 0) {
                showToast('No data to export', 'warning');
                return;
            }
            
            const csv = convertToCSV(data);
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', filename || 'export.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        function convertToCSV(objArray) {
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
        }
        
        // =============================================
        // COPY TO CLIPBOARD
        // =============================================
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                showToast('Copied to clipboard!', 'success');
            }).catch(function() {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                showToast('Copied to clipboard!', 'success');
            });
        }
        
        // =============================================
        // KEYBOARD SHORTCUTS
        // =============================================
        document.addEventListener('keydown', function(e) {
            // Ctrl + D - Toggle Dark Mode
            if (e.ctrlKey && e.key === 'd') {
                e.preventDefault();
                toggleDarkMode();
            }
            
            // Ctrl + S - Focus search
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                const searchInput = document.querySelector('input[type="search"]') || document.querySelector('input[placeholder*="Search"]');
                if (searchInput) searchInput.focus();
            }
            
            // Ctrl + N - New participant
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'participants.php?action=new';
            }
            
            // Ctrl + A - Attendance
            if (e.ctrlKey && e.key === 'a') {
                e.preventDefault();
                window.location.href = 'attendance.php';
            }
            
            // Ctrl + P - Payments
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.location.href = 'payments.php';
            }
            
            // Ctrl + R - Reports
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                window.location.href = 'reports.php';
            }
            
            // Ctrl + H - Home/Dashboard
            if (e.ctrlKey && e.key === 'h') {
                e.preventDefault();
                window.location.href = 'dashboard.php';
            }
            
            // Escape - Close modals
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal.show');
                modals.forEach(modal => {
                    const modalInstance = bootstrap.Modal.getInstance(modal);
                    if (modalInstance) modalInstance.hide();
                });
            }
        });
        
        // =============================================
        // LOGOUT FUNCTION
        // =============================================
        function logout() {
            confirmAction('Are you sure you want to logout?', function() {
                window.location.href = 'logout.php';
            });
        }
        
        // =============================================
        // DELETE CONFIRMATION
        // =============================================
        function confirmDelete(message, url) {
            confirmAction(message || 'Are you sure you want to delete this item?', function() {
                window.location.href = url;
            });
        }
        
        // =============================================
        // INITIALIZE DATATABLES
        // =============================================
        $(document).ready(function() {
            if ($.fn.DataTable) {
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
        });
        
        // =============================================
        // SIDEBAR TOGGLE (MOBILE)
        // =============================================
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                sidebar.classList.toggle('show');
            }
        }
        
        window.addEventListener('resize', function() {
            const sidebar = document.querySelector('.sidebar');
            if (window.innerWidth > 768 && sidebar) {
                sidebar.classList.remove('show');
            }
        });
        
        // =============================================
        // QUICK SEARCH
        // =============================================
        function quickSearch() {
            const input = document.getElementById('quickSearchInput');
            const type = document.getElementById('searchType');
            
            if (input && input.value) {
                const searchType = type ? type.value : 'participants';
                window.location.href = searchType + '.php?search=' + encodeURIComponent(input.value);
            }
        }
        
        // =============================================
        // ADD ANIMATION STYLE
        // =============================================
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
    
    <?php if (isset($additional_scripts)): ?>
        <?php echo $additional_scripts; ?>
    <?php endif; ?>
    
</body>
</html>