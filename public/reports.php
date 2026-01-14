<?php
/**
 * Reports Management Page
 * Client Reporting Portal
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Company.php';
require_once __DIR__ . '/../classes/Report.php';

requireRole([ROLE_SUPER_ADMIN, ROLE_MANAGER]);

$userModel = new User();
$companyModel = new Company();
$reportModel = new Report();

$role = getCurrentUserRole();
$userId = getCurrentUserId();

$errors = [];

// Handle report deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_report'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $reportId = (int) $_POST['report_id'];
        $report = $reportModel->getById($reportId);

        if ($report) {
            // Check permission to delete
            $canDelete = false;
            if ($role === ROLE_SUPER_ADMIN) {
                $canDelete = true;
            } elseif ($role === ROLE_MANAGER) {
                $canDelete = $userModel->isManagerAssignedToCompany($userId, $report['company_id']);
            }

            if ($canDelete) {
                $reportModel->delete($reportId);
                $userModel->logActivity($userId, 'report_delete', "Deleted report: {$report['title']}");
                setFlashMessage('success', 'Report deleted successfully.');
            } else {
                setFlashMessage('error', 'You do not have permission to delete this report.');
            }
        }
        redirect('reports.php');
    }
}

// Get reports based on role
$filterCompany = isset($_GET['company']) ? (int) $_GET['company'] : null;
$filterCategory = $_GET['category'] ?? null;

if ($role === ROLE_SUPER_ADMIN) {
    $reports = $reportModel->getAll($filterCompany, $filterCategory);
    $companies = $companyModel->getAll();
} else {
    // Manager sees only their assigned companies' reports
    if ($filterCompany) {
        // Verify manager has access to this company
        if (!$userModel->isManagerAssignedToCompany($userId, $filterCompany)) {
            $filterCompany = null;
        }
    }
    $reports = $reportModel->getForManager($userId);

    // Apply filters manually for manager
    if ($filterCompany || $filterCategory) {
        $reports = array_filter($reports, function($r) use ($filterCompany, $filterCategory) {
            if ($filterCompany && $r['company_id'] != $filterCompany) return false;
            if ($filterCategory && $r['category'] != $filterCategory) return false;
            return true;
        });
    }

    $companies = $userModel->getManagerCompanies($userId);
}

$pageTitle = 'Reports';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo $role === ROLE_SUPER_ADMIN ? 'All Reports' : 'My Reports'; ?></h1>
        <p class="page-subtitle">View and manage uploaded reports</p>
    </div>
    <a href="upload.php" class="btn btn-warning btn-icon">
        <i class="bi bi-cloud-upload"></i> Upload New Report
    </a>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="company" class="form-label">Filter by Company</label>
                <select class="form-select" id="company" name="company">
                    <option value="">All Companies</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?php echo $company['id']; ?>"
                            <?php echo ($filterCompany == $company['id']) ? 'selected' : ''; ?>>
                            <?php echo e($company['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="category" class="form-label">Filter by Category</label>
                <select class="form-select" id="category" name="category">
                    <option value="">All Categories</option>
                    <?php foreach (REPORT_CATEGORIES as $cat): ?>
                        <option value="<?php echo e($cat); ?>"
                            <?php echo ($filterCategory === $cat) ? 'selected' : ''; ?>>
                            <?php echo e($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-outline-warning me-2">
                    <i class="bi bi-funnel"></i> Apply Filters
                </button>
                <a href="reports.php" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Reports List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-file-earmark-text me-2"></i>
            Reports (<?php echo count($reports); ?>)
        </span>
        <input type="text" id="tableSearch" class="form-control form-control-sm" style="width: 200px;"
               placeholder="Search reports...">
    </div>
    <div class="card-body p-0">
        <?php if (empty($reports)): ?>
            <div class="empty-state">
                <i class="bi bi-file-earmark-x"></i>
                <h4>No Reports Found</h4>
                <p>
                    <?php if ($filterCompany || $filterCategory): ?>
                        No reports match your filter criteria.
                    <?php else: ?>
                        Start by uploading your first report.
                    <?php endif; ?>
                </p>
                <a href="upload.php" class="btn btn-warning">
                    <i class="bi bi-cloud-upload"></i> Upload Report
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Company</th>
                            <th>Uploaded By</th>
                            <th>Date</th>
                            <th>Size</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($report['title']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo e($report['original_name']); ?></small>
                                </td>
                                <td>
                                    <?php
                                    $categoryClass = strtolower(str_replace(' ', '', $report['category']));
                                    ?>
                                    <span class="badge bg-<?php echo $categoryClass; ?> badge-category">
                                        <?php echo e($report['category']); ?>
                                    </span>
                                </td>
                                <td><?php echo e($report['company_name']); ?></td>
                                <td><?php echo e($report['uploader_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($report['created_at'])); ?></td>
                                <td>
                                    <?php
                                    $size = $report['file_size'] ?? 0;
                                    if ($size > 1048576) {
                                        echo round($size / 1048576, 2) . ' MB';
                                    } elseif ($size > 1024) {
                                        echo round($size / 1024, 2) . ' KB';
                                    } else {
                                        echo $size . ' B';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_report.php?id=<?php echo $report['id']; ?>"
                                           class="btn btn-outline-warning btn-sm" target="_blank" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo e(generateCSRFToken()); ?>">
                                            <input type="hidden" name="delete_report" value="1">
                                            <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm"
                                                    title="Delete"
                                                    data-confirm-delete="Are you sure you want to delete this report?">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
