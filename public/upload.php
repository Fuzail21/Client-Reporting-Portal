<?php
/**
 * Upload Report Page (Manager Only)
 * Client Reporting Portal
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Report.php';

requireRole([ROLE_MANAGER, ROLE_SUPER_ADMIN]);

$userModel = new User();
$reportModel = new Report();

$errors = [];
$success = false;

// Get companies for the dropdown
if (hasRole(ROLE_SUPER_ADMIN)) {
    require_once __DIR__ . '/../classes/Company.php';
    $companyModel = new Company();
    $companies = $companyModel->getAll();
} else {
    $companies = $userModel->getManagerCompanies(getCurrentUserId());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $companyId = (int) ($_POST['company_id'] ?? 0);
        $category = $_POST['category'] ?? 'SEO';

        // Validate inputs
        if (empty($title)) {
            $errors[] = 'Report title is required.';
        }

        if (empty($companyId)) {
            $errors[] = 'Please select a company.';
        } else {
            // Verify manager has access to this company (unless super admin)
            if (!hasRole(ROLE_SUPER_ADMIN)) {
                $hasAccess = $userModel->isManagerAssignedToCompany(getCurrentUserId(), $companyId);
                if (!$hasAccess) {
                    $errors[] = 'You do not have access to this company.';
                }
            }
        }

        if (!in_array($category, REPORT_CATEGORIES)) {
            $errors[] = 'Invalid category selected.';
        }

        // Validate file upload
        if (!isset($_FILES['report_file']) || $_FILES['report_file']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Please select an HTML file to upload.';
        } else {
            $fileErrors = $reportModel->validateUpload($_FILES['report_file']);
            $errors = array_merge($errors, $fileErrors);
        }

        // Process upload if no errors
        if (empty($errors)) {
            $secureFilename = $reportModel->saveUploadedFile($_FILES['report_file']);

            if ($secureFilename) {
                $reportData = [
                    'company_id' => $companyId,
                    'uploaded_by' => getCurrentUserId(),
                    'title' => $title,
                    'category' => $category,
                    'file_name' => $secureFilename,
                    'original_name' => $_FILES['report_file']['name'],
                    'file_size' => $_FILES['report_file']['size']
                ];

                $reportId = $reportModel->create($reportData);

                if ($reportId) {
                    // Log the activity
                    $userModel->logActivity(getCurrentUserId(), 'report_upload',
                        "Uploaded report: {$title} for company ID: {$companyId}");

                    setFlashMessage('success', 'Report uploaded successfully!');
                    redirect('reports.php');
                } else {
                    $errors[] = 'Failed to save report to database.';
                    // Clean up the uploaded file
                    @unlink(REPORTS_PATH . '/' . $secureFilename);
                }
            } else {
                $errors[] = 'Failed to save uploaded file. Please try again.';
            }
        }
    }
}

$pageTitle = 'Upload Report';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Upload Report</h1>
        <p class="page-subtitle">Upload a new HTML report for a client company</p>
    </div>
    <a href="reports.php" class="btn btn-outline-warning btn-icon">
        <i class="bi bi-arrow-left"></i> Back to Reports
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo e($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (empty($companies)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="bi bi-building-x"></i>
                <h4>No Companies Assigned</h4>
                <p>You need to be assigned to at least one company to upload reports.</p>
                <p class="text-muted">Please contact your administrator to be assigned to companies.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-header">
            <i class="bi bi-cloud-upload me-2"></i>Upload New Report
        </div>
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo e(generateCSRFToken()); ?>">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="title" class="form-label">Report Title *</label>
                        <input type="text" class="form-control" id="title" name="title"
                               value="<?php echo e($_POST['title'] ?? ''); ?>"
                               placeholder="e.g., Monthly SEO Report - October 2024" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="company_id" class="form-label">Company *</label>
                        <select class="form-select" id="company_id" name="company_id" required>
                            <option value="">Select Company</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo $company['id']; ?>"
                                    <?php echo (($_POST['company_id'] ?? '') == $company['id']) ? 'selected' : ''; ?>>
                                    <?php echo e($company['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="category" class="form-label">Category *</label>
                        <select class="form-select" id="category" name="category" required>
                            <?php foreach (REPORT_CATEGORIES as $cat): ?>
                                <option value="<?php echo e($cat); ?>"
                                    <?php echo (($_POST['category'] ?? '') === $cat) ? 'selected' : ''; ?>>
                                    <?php echo e($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">HTML Report File *</label>
                    <input type="file" id="reportFile" name="report_file" accept=".html,.htm"
                           style="display: none;" required>
                    <div class="custom-file-upload">
                        <i class="bi bi-file-earmark-code"></i>
                        <p>
                            <strong>Click or drag</strong> to upload your HTML report<br>
                            <small class="text-muted">Only .html and .htm files (max <?php echo MAX_FILE_SIZE / 1024 / 1024; ?>MB)</small>
                        </p>
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Note:</strong> The uploaded file will be renamed for security purposes.
                    The original filename will be preserved in the system.
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-warning btn-icon">
                        <i class="bi bi-cloud-upload"></i> Upload Report
                    </button>
                    <a href="reports.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
