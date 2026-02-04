<?php
/**
 * Dashboard Page
 * Client Reporting Portal
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Company.php';
require_once __DIR__ . '/../classes/Report.php';

requireAuth();

$userModel = new User();
$companyModel = new Company();
$reportModel = new Report();

$role = getCurrentUserRole();
$userId = getCurrentUserId();

// Get dashboard stats based on role
$stats = [];
$recentReports = [];

if ($role === ROLE_SUPER_ADMIN) {
    // Super Admin sees everything
    $allUsers = $userModel->getAll();
    $allCompanies = $companyModel->getAll();
    $allReports = $reportModel->getAll();

    $stats = [
        'total_users' => count($allUsers),
        'total_companies' => count($allCompanies),
        'total_reports' => count($allReports),
        'managers' => count(array_filter($allUsers, fn($u) => $u['role'] === ROLE_MANAGER)),
        'clients' => count(array_filter($allUsers, fn($u) => $u['role'] === ROLE_CLIENT))
    ];

    $recentReports = $reportModel->getRecent(5);

} elseif ($role === ROLE_MANAGER) {
    // Manager sees their assigned companies
    $myCompanies = $userModel->getManagerCompanies($userId);
    $myReports = $reportModel->getForManager($userId);

    $stats = [
        'assigned_companies' => count($myCompanies),
        'total_reports' => count($myReports)
    ];

    $recentReports = array_slice($myReports, 0, 5);

} else {
    // Client sees reports from all assigned companies (M:N relationship)
    $myCompanies = $userModel->getClientCompanies($userId);
    $myReports = $reportModel->getForClient($userId);
    $categoryCounts = $reportModel->getCountByCategoryForClient($userId);

    $stats = [
        'assigned_companies' => count($myCompanies),
        'total_reports' => count($myReports),
        'categories' => $categoryCounts
    ];

    $recentReports = array_slice($myReports, 0, 5);
}

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Welcome back, <?php echo e($_SESSION['user_name']); ?>!</p>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <?php if ($role === ROLE_SUPER_ADMIN): ?>
        <div class="col-md-6 col-lg-3">
            <div class="card stat-card">
                <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card stat-card">
                <div class="stat-icon"><i class="bi bi-building"></i></div>
                <div class="stat-value"><?php echo $stats['total_companies']; ?></div>
                <div class="stat-label">Companies</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card stat-card">
                <div class="stat-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
                <div class="stat-value"><?php echo $stats['total_reports']; ?></div>
                <div class="stat-label">Total Reports</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card stat-card">
                <div class="stat-icon"><i class="bi bi-person-badge-fill"></i></div>
                <div class="stat-value"><?php echo $stats['managers']; ?></div>
                <div class="stat-label">Managers</div>
            </div>
        </div>

    <?php elseif ($role === ROLE_MANAGER): ?>
        <div class="col-md-6">
            <div class="card stat-card">
                <div class="stat-icon"><i class="bi bi-building"></i></div>
                <div class="stat-value"><?php echo $stats['assigned_companies']; ?></div>
                <div class="stat-label">Assigned Companies</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card stat-card">
                <div class="stat-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
                <div class="stat-value"><?php echo $stats['total_reports']; ?></div>
                <div class="stat-label">Total Reports</div>
            </div>
        </div>

    <?php else: ?>
        <div class="col-md-6 col-lg-3">
            <div class="card stat-card">
                <div class="stat-icon"><i class="bi bi-building"></i></div>
                <div class="stat-value"><?php echo $stats['assigned_companies'] ?? 0; ?></div>
                <div class="stat-label">My Projects</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card stat-card">
                <div class="stat-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
                <div class="stat-value"><?php echo $stats['total_reports'] ?? 0; ?></div>
                <div class="stat-label">Total Reports</div>
            </div>
        </div>
        <?php if (isset($stats['categories'])): ?>
            <?php foreach ($stats['categories'] as $category => $count): ?>
                <?php if ($count > 0): ?>
                <div class="col-md-6 col-lg-3">
                    <div class="card stat-card">
                        <div class="stat-icon"><i class="bi bi-tag-fill"></i></div>
                        <div class="stat-value"><?php echo $count; ?></div>
                        <div class="stat-label"><?php echo e($category); ?></div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Recent Reports -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-2"></i>Recent Reports</span>
                <?php if ($role === ROLE_MANAGER): ?>
                    <a href="upload.php" class="btn btn-warning btn-sm">
                        <i class="bi bi-plus-lg"></i> Upload New
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($recentReports)): ?>
                    <div class="empty-state">
                        <i class="bi bi-file-earmark-x"></i>
                        <h4>No Reports Yet</h4>
                        <p>
                            <?php if ($role === ROLE_MANAGER): ?>
                                Start by uploading your first report.
                            <?php elseif ($role === ROLE_CLIENT): ?>
                                Reports will appear here once uploaded by your account manager.
                            <?php else: ?>
                                No reports have been uploaded to the system.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Project</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentReports as $report): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo e($report['title']); ?></strong>
                                            <br><small class="text-muted"><?php echo e($report['original_name']); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $categoryClass = strtolower(str_replace(' ', '', $report['category']));
                                            ?>
                                            <span class="badge bg-<?php echo $categoryClass; ?> badge-category">
                                                <?php echo e($report['category']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo e($report['company_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($report['created_at'])); ?></td>
                                        <td>
                                            <a href="view_report.php?id=<?php echo $report['id']; ?>"
                                               class="btn btn-outline-warning btn-sm" target="_blank">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($role === ROLE_MANAGER): ?>
<!-- Quick Actions for Manager -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-lightning-fill me-2"></i>Quick Actions
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="upload.php" class="btn btn-warning btn-icon">
                        <i class="bi bi-cloud-upload"></i> Upload New Report
                    </a>
                    <a href="my-companies.php" class="btn btn-outline-warning btn-icon">
                        <i class="bi bi-building"></i> View My Companies
                    </a>
                    <a href="reports.php" class="btn btn-outline-warning btn-icon">
                        <i class="bi bi-file-earmark-text"></i> Manage Reports
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
