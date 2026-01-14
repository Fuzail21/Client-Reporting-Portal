<?php
/**
 * Header Template
 * Contains navigation and common header elements
 */

require_once __DIR__ . '/../../config/config.php';

$flash = getFlashMessage();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle ?? APP_NAME); ?> - <?php echo e(APP_NAME); ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="brand-logo">
                    <img src="assets/img/logo.png" alt="Logo" class="sidebar-logo">
                </a>
            </div>

            <ul class="nav flex-column sidebar-nav">
                <?php if (hasRole(ROLE_SUPER_ADMIN)): ?>
                    <!-- Super Admin Menu -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'users' ? 'active' : ''; ?>" href="users.php">
                            <i class="bi bi-people"></i> Manage Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'companies' ? 'active' : ''; ?>" href="companies.php">
                            <i class="bi bi-building"></i> Manage Companies
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'reports' ? 'active' : ''; ?>" href="reports.php">
                            <i class="bi bi-file-earmark-text"></i> All Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'activity' ? 'active' : ''; ?>" href="activity.php">
                            <i class="bi bi-clock-history"></i> Activity Log
                        </a>
                    </li>
                <?php elseif (hasRole(ROLE_MANAGER)): ?>
                    <!-- Manager Menu -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'my-companies' ? 'active' : ''; ?>" href="my-companies.php">
                            <i class="bi bi-building"></i> My Companies
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'upload' ? 'active' : ''; ?>" href="upload.php">
                            <i class="bi bi-cloud-upload"></i> Upload Report
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'reports' ? 'active' : ''; ?>" href="reports.php">
                            <i class="bi bi-file-earmark-text"></i> My Reports
                        </a>
                    </li>
                <?php else: ?>
                    <!-- Client Menu -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'my-reports' ? 'active' : ''; ?>" href="my-reports.php">
                            <i class="bi bi-file-earmark-text"></i> My Reports
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="sidebar-footer">
                <div class="user-info">
                    <i class="bi bi-person-circle"></i>
                    <div class="user-details">
                        <span class="user-name"><?php echo e($_SESSION['user_name'] ?? 'User'); ?></span>
                        <span class="user-role badge bg-warning text-dark"><?php echo e(ucwords(str_replace('_', ' ', $_SESSION['user_role'] ?? ''))); ?></span>
                    </div>
                </div>
                <a href="logout.php" class="btn btn-outline-warning btn-sm w-100 mt-2">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <!-- Top Navbar (Mobile) -->
            <nav class="navbar navbar-dark d-lg-none">
                <div class="container-fluid">
                    <button type="button" id="sidebarToggle" class="btn btn-warning">
                        <i class="bi bi-list"></i>
                    </button>
                    <span class="navbar-brand"><?php echo e(APP_NAME); ?></span>
                    <a href="logout.php" class="btn btn-outline-warning btn-sm">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </nav>

            <div class="main-content">
                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : e($flash['type']); ?> alert-dismissible fade show" role="alert">
                        <?php echo e($flash['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
    <?php else: ?>
        <div class="auth-wrapper">
    <?php endif; ?>
