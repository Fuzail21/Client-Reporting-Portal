<?php
/**
 * Manager's Assigned Companies Page
 * Client Reporting Portal
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Company.php';
require_once __DIR__ . '/../classes/Report.php';

requireRole([ROLE_MANAGER]);

$userModel = new User();
$companyModel = new Company();
$reportModel = new Report();

$userId = getCurrentUserId();
$companies = $userModel->getManagerCompanies($userId);

// Get report counts for each company
$companyData = [];
foreach ($companies as $company) {
    $reports = $reportModel->getByCompany($company['id']);
    $categoryCounts = $reportModel->getCountByCategory($company['id']);

    $companyData[] = [
        'company' => $company,
        'total_reports' => count($reports),
        'recent_reports' => array_slice($reports, 0, 3),
        'category_counts' => $categoryCounts
    ];
}

$pageTitle = 'My Companies';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Companies</h1>
        <p class="page-subtitle">Companies assigned to you for report management</p>
    </div>
    <a href="upload.php" class="btn btn-warning btn-icon">
        <i class="bi bi-cloud-upload"></i> Upload Report
    </a>
</div>

<?php if (empty($companyData)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="bi bi-building-x"></i>
                <h4>No Companies Assigned</h4>
                <p>You haven't been assigned to any companies yet.</p>
                <p class="text-muted">Please contact your administrator to be assigned to companies.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($companyData as $data): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="bi bi-building me-2"></i>
                        <?php echo e($data['company']['name']); ?>
                    </div>
                    <div class="card-body">
                        <!-- Stats -->
                        <div class="d-flex justify-content-between mb-3">
                            <div class="text-center">
                                <div class="fs-4 fw-bold text-warning"><?php echo $data['total_reports']; ?></div>
                                <small class="text-muted">Total Reports</small>
                            </div>
                            <?php foreach ($data['category_counts'] as $cat => $count): ?>
                                <?php if ($count > 0): ?>
                                    <div class="text-center">
                                        <div class="fs-4 fw-bold"><?php echo $count; ?></div>
                                        <small class="text-muted"><?php echo e($cat); ?></small>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                        <!-- Recent Reports -->
                        <?php if (!empty($data['recent_reports'])): ?>
                            <h6 class="text-muted mb-2">Recent Reports</h6>
                            <ul class="list-unstyled small mb-0">
                                <?php foreach ($data['recent_reports'] as $report): ?>
                                    <li class="mb-2">
                                        <a href="view_report.php?id=<?php echo $report['id']; ?>"
                                           class="text-decoration-none" target="_blank">
                                            <i class="bi bi-file-earmark-text text-warning me-1"></i>
                                            <?php echo e($report['title']); ?>
                                        </a>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y', strtotime($report['created_at'])); ?>
                                        </small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted small mb-0">No reports uploaded yet.</p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-transparent border-top">
                        <div class="d-flex gap-2">
                            <a href="upload.php?company=<?php echo $data['company']['id']; ?>"
                               class="btn btn-warning btn-sm flex-fill">
                                <i class="bi bi-upload"></i> Upload
                            </a>
                            <a href="reports.php?company=<?php echo $data['company']['id']; ?>"
                               class="btn btn-outline-warning btn-sm flex-fill">
                                <i class="bi bi-list"></i> View All
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
