<?php
/**
 * Client Reports Page (Client View)
 * Client Reporting Portal
 * Updated for M:N client-company relationship
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Report.php';
require_once __DIR__ . '/../classes/Company.php';

requireRole([ROLE_CLIENT]);

$userModel = new User();
$reportModel = new Report();
$companyModel = new Company();

$userId = getCurrentUserId();

// Get all companies assigned to this client (M:N)
$clientCompanies = $userModel->getClientCompanies($userId);

if (empty($clientCompanies)) {
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
                <h4>No Projects Assigned</h4>
                <p>Your account is not associated with any projects.</p>
                <p class="text-muted">Please contact your administrator.</p>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Get selected company from URL or default to first assigned company
$selectedCompanyId = isset($_GET['company']) ? (int) $_GET['company'] : $clientCompanies[0]['id'];

// Verify client has access to selected company
$hasAccess = false;
$selectedCompany = null;
foreach ($clientCompanies as $company) {
    if ($company['id'] === $selectedCompanyId) {
        $hasAccess = true;
        $selectedCompany = $company;
        break;
    }
}

if (!$hasAccess) {
    $selectedCompanyId = $clientCompanies[0]['id'];
    $selectedCompany = $clientCompanies[0];
}

// Get reports for selected company
$reportsByCategory = $reportModel->getByCompanyGroupedByCategory($selectedCompanyId);

// Filter to only categories that have reports
$categoriesWithReports = [];
foreach (REPORT_CATEGORIES as $cat) {
    if (!empty($reportsByCategory[$cat])) {
        $categoriesWithReports[] = $cat;
    }
}

// Get active category from URL or default to first with reports
$activeCategory = $_GET['category'] ?? null;
if (!$activeCategory || !in_array($activeCategory, $categoriesWithReports)) {
    $activeCategory = !empty($categoriesWithReports) ? $categoriesWithReports[0] : null;
}

$pageTitle = 'My Reports';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Reports</h1>
        <p class="page-subtitle">
            <i class="bi bi-building me-1"></i>
            <?php echo e($selectedCompany['name']); ?>
        </p>
    </div>
</div>

<?php if (count($clientCompanies) > 1): ?>
<!-- Project/Company Selector -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label for="company" class="form-label">Select Project/Company</label>
                <select class="form-select" id="company" name="company" onchange="this.form.submit()">
                    <?php foreach ($clientCompanies as $company): ?>
                        <option value="<?php echo $company['id']; ?>"
                            <?php echo ($selectedCompanyId === $company['id']) ? 'selected' : ''; ?>>
                            <?php echo e($company['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <noscript>
                    <button type="submit" class="btn btn-outline-warning">Switch Project</button>
                </noscript>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (empty($categoriesWithReports)): ?>
    <!-- No Reports at All -->
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="bi bi-file-earmark-x"></i>
                <h4>No Reports Yet</h4>
                <p>Reports will appear here once uploaded by your account manager.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Category Tabs - Only show categories with reports -->
    <ul class="nav nav-tabs mb-4">
        <?php foreach ($categoriesWithReports as $category): ?>
            <?php $count = count($reportsByCategory[$category]); ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeCategory === $category ? 'active' : ''; ?>"
                   href="?company=<?php echo $selectedCompanyId; ?>&category=<?php echo urlencode($category); ?>">
                    <?php echo e($category); ?>
                    <span class="badge bg-warning text-dark ms-1"><?php echo $count; ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Reports for Active Category -->
    <div class="tab-content">
        <?php $reports = $reportsByCategory[$activeCategory] ?? []; ?>
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
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
