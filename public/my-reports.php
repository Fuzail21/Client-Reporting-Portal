<?php
/**
 * Client Reports Page (Client View)
 * Client Reporting Portal
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Report.php';
require_once __DIR__ . '/../classes/Company.php';

requireRole([ROLE_CLIENT]);

$companyId = getCurrentUserCompanyId();

if (!$companyId) {
    $pageTitle = 'My Reports';
    include __DIR__ . '/includes/header.php';
    ?>
    <div class="page-header">
        <h1 class="page-title">My Reports</h1>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="bi bi-building-x"></i>
                <h4>No Company Assigned</h4>
                <p>Your account is not associated with any company.</p>
                <p class="text-muted">Please contact your administrator.</p>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

$reportModel = new Report();
$companyModel = new Company();

$company = $companyModel->getById($companyId);
$reportsByCategory = $reportModel->getByCompanyGroupedByCategory($companyId);

// Get active category from URL or default to first with reports
$activeCategory = $_GET['category'] ?? null;
if (!$activeCategory || !in_array($activeCategory, REPORT_CATEGORIES)) {
    // Find first category with reports
    foreach (REPORT_CATEGORIES as $cat) {
        if (!empty($reportsByCategory[$cat])) {
            $activeCategory = $cat;
            break;
        }
    }
    if (!$activeCategory) {
        $activeCategory = REPORT_CATEGORIES[0];
    }
}

$pageTitle = 'My Reports';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Reports</h1>
        <p class="page-subtitle">
            <i class="bi bi-building me-1"></i>
            <?php echo e($company['name'] ?? 'Your Company'); ?>
        </p>
    </div>
</div>

<!-- Category Tabs -->
<ul class="nav nav-tabs mb-4">
    <?php foreach (REPORT_CATEGORIES as $category): ?>
        <?php $count = count($reportsByCategory[$category] ?? []); ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeCategory === $category ? 'active' : ''; ?>"
               href="?category=<?php echo urlencode($category); ?>">
                <?php echo e($category); ?>
                <?php if ($count > 0): ?>
                    <span class="badge bg-warning text-dark ms-1"><?php echo $count; ?></span>
                <?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<!-- Reports for Active Category -->
<div class="tab-content">
    <?php $reports = $reportsByCategory[$activeCategory] ?? []; ?>

    <?php if (empty($reports)): ?>
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <i class="bi bi-file-earmark-x"></i>
                    <h4>No <?php echo e($activeCategory); ?> Reports</h4>
                    <p>There are no reports in this category yet.</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="report-grid">
            <?php foreach ($reports as $report): ?>
                <div class="report-card">
                    <div class="report-card-header">
                        <h5 class="report-card-title"><?php echo e($report['title']); ?></h5>
                        <?php
                        $categoryClass = strtolower(str_replace(' ', '', $report['category']));
                        ?>
                        <span class="badge bg-<?php echo $categoryClass; ?> badge-category">
                            <?php echo e($report['category']); ?>
                        </span>
                    </div>

                    <div class="report-card-meta">
                        <div>
                            <i class="bi bi-calendar3"></i>
                            <?php echo date('F d, Y', strtotime($report['created_at'])); ?>
                        </div>
                        <div class="mt-1">
                            <i class="bi bi-file-earmark"></i>
                            <?php echo e($report['original_name']); ?>
                        </div>
                    </div>

                    <div class="report-card-actions">
                        <a href="view_report.php?id=<?php echo $report['id']; ?>"
                           class="btn btn-warning btn-sm w-100" target="_blank">
                            <i class="bi bi-eye me-1"></i> View Report
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
