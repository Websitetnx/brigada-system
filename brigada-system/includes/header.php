<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brigada Eskwela Monitoring System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        /* Dark Mode Variables */
        :root {
            --bg-primary: #f8f9fa;
            --bg-secondary: #ffffff;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --border-color: #dee2e6;
            --card-bg: #ffffff;
            --sidebar-bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --navbar-bg: #ffffff;
            --input-bg: #ffffff;
            --hover-bg: #f8f9fa;
        }
        
        [data-theme="dark"] {
            --bg-primary: #1a1a2e;
            --bg-secondary: #16213e;
            --text-primary: #e2e2e2;
            --text-secondary: #a0a0a0;
            --border-color: #2a2a4a;
            --card-bg: #0f3460;
            --sidebar-bg: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            --navbar-bg: #16213e;
            --input-bg: #1a1a2e;
            --hover-bg: #1a1a2e;
        }
        
        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            transition: background-color 0.3s ease, color 0.3s ease;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            min-height: 100vh;
            background: var(--sidebar-bg);
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.9);
            padding: 12px 20px;
            margin: 4px 10px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.3);
            color: white;
            font-weight: bold;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
        }
        
        .main-content {
            padding: 20px 30px;
        }
        
        .navbar-top {
            background: var(--navbar-bg);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 15px 30px;
            margin-bottom: 20px;
            border-radius: 10px;
            color: var(--text-primary);
            transition: background-color 0.3s ease;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s, background-color 0.3s ease;
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background-color: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            border-radius: 15px 15px 0 0 !important;
            padding: 15px 20px;
            font-weight: bold;
            color: var(--text-primary);
        }
        
        .btn {
            border-radius: 8px;
            padding: 8px 20px;
            font-weight: 500;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .table {
            color: var(--text-primary);
        }
        
        .table thead th {
            background: var(--bg-secondary);
            border-bottom: 2px solid var(--border-color);
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .table-light {
            background-color: var(--bg-secondary);
        }
        
        .list-group-item {
            background-color: var(--card-bg);
            border-color: var(--border-color);
            color: var(--text-primary);
        }
        
        .text-muted {
            color: var(--text-secondary) !important;
        }
        
        .modal-content {
            background-color: var(--card-bg);
            color: var(--text-primary);
        }
        
        .modal-header {
            border-bottom-color: var(--border-color);
        }
        
        .modal-footer {
            border-top-color: var(--border-color);
        }
        
        .form-control, .form-select {
            background-color: var(--input-bg);
            border-color: var(--border-color);
            color: var(--text-primary);
        }
        
        .form-control:focus, .form-select:focus {
            background-color: var(--input-bg);
            color: var(--text-primary);
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }
        
        .input-group-text {
            background-color: var(--bg-secondary);
            border-color: var(--border-color);
            color: var(--text-primary);
        }
        
        .dropdown-menu {
            background-color: var(--card-bg);
            border-color: var(--border-color);
        }
        
        .dropdown-item {
            color: var(--text-primary);
        }
        
        .dropdown-item:hover {
            background-color: var(--hover-bg);
            color: var(--text-primary);
        }
        
        .dropdown-divider {
            border-top-color: var(--border-color);
        }
        
        .breadcrumb-item a {
            color: var(--text-secondary);
            text-decoration: none;
        }
        
        .breadcrumb-item.active {
            color: var(--text-primary);
        }
        
        .pagination .page-link {
            background-color: var(--card-bg);
            border-color: var(--border-color);
            color: var(--text-primary);
        }
        
        .pagination .page-item.active .page-link {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        .pagination .page-item.disabled .page-link {
            background-color: var(--bg-secondary);
            color: var(--text-secondary);
        }
        
        /* Dark Mode Toggle Button */
        .dark-mode-toggle {
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 20px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .dark-mode-toggle:hover {
            opacity: 0.8;
            transform: scale(1.02);
        }
        
        .dark-mode-toggle i {
            margin-right: 8px;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-complete {
            background: #d4edda;
            color: #155724;
        }
        
        .status-incomplete {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-pending {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Stats Cards */
        .stats-card {
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-3px);
        }
        
        .opacity-50 {
            opacity: 0.5;
        }
        
        /* Loading Spinner */
        .loading-spinner {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        
        /* Print Styles */
        @media print {
            .sidebar, .navbar-top, .btn, .no-print {
                display: none !important;
            }
            
            .main-content {
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
            }
            
            .main-content {
                padding: 15px;
            }
            
            .navbar-top {
                padding: 10px 15px;
            }
        }
        
        /* Animation for cards */
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
        
        .card {
            animation: fadeInUp 0.5s ease-out;
        }
    </style>
</head>
<body>
    <!-- Loading Spinner -->
    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar">
                    <div class="text-center py-4">
                        <h4 class="text-white mb-0">
                            <i class="fas fa-school"></i> Brigada
                        </h4>
                        <small class="text-white-50">Monitoring System v1.0</small>
                    </div>
                    
                    <div class="px-3">
                        <hr class="text-white-50">
                    </div>
                    
                    <nav class="nav flex-column">
                        <?php
                        $current_page = basename($_SERVER['PHP_SELF']);
                        ?>
                        
                        <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        
                        <a class="nav-link <?php echo $current_page == 'participants.php' ? 'active' : ''; ?>" href="participants.php">
                            <i class="fas fa-users"></i> Participants
                        </a>
                        
                        <a class="nav-link <?php echo $current_page == 'attendance.php' ? 'active' : ''; ?>" href="attendance.php">
                            <i class="fas fa-clock"></i> Attendance
                        </a>
                        
                        <a class="nav-link <?php echo $current_page == 'scan.php' ? 'active' : ''; ?>" href="scan.php">
                            <i class="fas fa-qrcode"></i> QR Scanner
                        </a>
                        
                        <a class="nav-link <?php echo $current_page == 'payments.php' ? 'active' : ''; ?>" href="payments.php">
                            <i class="fas fa-money-bill-wave"></i> Payments
                        </a>
                        
                        <a class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                        
                        <div class="px-3 mt-4">
                            <hr class="text-white-50">
                        </div>
                        
                        <a class="nav-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        
                        <a class="nav-link text-white-50" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </nav>
                    
                    <div class="position-absolute bottom-0 start-0 p-3 w-100">
                        <div class="text-white-50 text-center small">
                            <i class="far fa-clock"></i> 
                            <span id="currentTime"></span><br>
                            <span id="currentDate"></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 px-0">
                <div class="main-content">
                    <!-- Top Navigation -->
                    <div class="navbar-top d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0">
                                <?php
                                $page_titles = [
                                    'dashboard.php' => 'Dashboard',
                                    'participants.php' => 'Participant Management',
                                    'attendance.php' => 'Attendance Tracking',
                                    'scan.php' => 'QR Code Scanner',
                                    'payments.php' => 'Payment Management',
                                    'reports.php' => 'Reports & Analytics',
                                    'settings.php' => 'System Settings'
                                ];
                                echo $page_titles[$current_page] ?? 'Brigada Eskwela';
                                ?>
                            </h4>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                                    <li class="breadcrumb-item active"><?php echo $page_titles[$current_page] ?? 'Page'; ?></li>
                                </ol>
                            </nav>
                        </div>
                        
                        <div class="d-flex align-items-center">
                            <!-- Dark Mode Toggle -->
                            <button class="dark-mode-toggle me-3" onclick="toggleDarkMode()" id="darkModeToggle">
                                <i class="fas fa-moon"></i> <span>Dark Mode</span>
                            </button>
                            
                            <div class="dropdown me-3">
                                <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                    <i class="fas fa-bell"></i>
                                    <span class="badge bg-danger rounded-pill" id="notificationCount">0</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" id="notificationList">
                                    <li><h6 class="dropdown-header">Notifications</h6></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="#">No new notifications</a></li>
                                </ul>
                            </div>
                            
                            <div class="dropdown">
                                <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                    <i class="fas fa-user-circle"></i> <?php echo $_SESSION['user_name'] ?? 'Admin'; ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Page Content Start -->