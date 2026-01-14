<?php
/**
 * Company Management Page (Super Admin Only)
 * Client Reporting Portal
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Company.php';

requireRole([ROLE_SUPER_ADMIN]);

$companyModel = new Company();
$userModel = new User();

$action = $_GET['action'] ?? 'list';
$companyId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$errors = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $formAction = $_POST['form_action'] ?? '';

        if ($formAction === 'create' || $formAction === 'update') {
            $name = trim($_POST['name'] ?? '');

            if (empty($name)) {
                $errors[] = 'Company name is required.';
            }

            // Check name uniqueness
            $excludeId = $formAction === 'update' ? (int) $_POST['company_id'] : null;
            if ($companyModel->nameExists($name, $excludeId)) {
                $errors[] = 'A company with this name already exists.';
            }

            if (empty($errors)) {
                if ($formAction === 'create') {
                    $companyModel->create(['name' => $name]);
                    setFlashMessage('success', 'Company created successfully.');
                    redirect('companies.php');
                } else {
                    $updateId = (int) $_POST['company_id'];
                    $companyModel->update($updateId, ['name' => $name]);
                    setFlashMessage('success', 'Company updated successfully.');
                    redirect('companies.php');
                }
            }
        } elseif ($formAction === 'delete') {
            $deleteId = (int) $_POST['company_id'];
            $companyModel->delete($deleteId);
            setFlashMessage('success', 'Company deleted successfully.');
            redirect('companies.php');
        }
    }
}

// Get data
$companies = $companyModel->getAllWithStats();
$editCompany = null;
$companyManagers = [];
$companyClients = [];

if ($action === 'edit' && $companyId) {
    $editCompany = $companyModel->getById($companyId);
    if (!$editCompany) {
        setFlashMessage('error', 'Company not found.');
        redirect('companies.php');
    }
    $companyManagers = $companyModel->getAssignedManagers($companyId);
    $companyClients = $companyModel->getClients($companyId);
}

if ($action === 'view' && $companyId) {
    $viewCompany = $companyModel->getById($companyId);
    if (!$viewCompany) {
        setFlashMessage('error', 'Company not found.');
        redirect('companies.php');
    }
    $companyManagers = $companyModel->getAssignedManagers($companyId);
    $companyClients = $companyModel->getClients($companyId);
}

$pageTitle = 'Manage Companies';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Manage Companies</h1>
        <p class="page-subtitle">Create and manage client companies</p>
    </div>
    <?php if ($action === 'list'): ?>
        <a href="companies.php?action=create" class="btn btn-warning btn-icon">
            <i class="bi bi-plus-lg"></i> Add Company
        </a>
    <?php else: ?>
        <a href="companies.php" class="btn btn-outline-warning btn-icon">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
    <?php endif; ?>
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

<?php if ($action === 'create' || $action === 'edit'): ?>
    <!-- Create/Edit Form -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-building me-2"></i>
            <?php echo $action === 'create' ? 'Create New Company' : 'Edit Company'; ?>
        </div>
        <div class="card-body">
            <form method="POST" action="" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo e(generateCSRFToken()); ?>">
                <input type="hidden" name="form_action" value="<?php echo $action === 'create' ? 'create' : 'update'; ?>">
                <?php if ($editCompany): ?>
                    <input type="hidden" name="company_id" value="<?php echo $editCompany['id']; ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="name" class="form-label">Company Name *</label>
                    <input type="text" class="form-control" id="name" name="name"
                           value="<?php echo e($editCompany['name'] ?? $_POST['name'] ?? ''); ?>" required>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-lg"></i>
                        <?php echo $action === 'create' ? 'Create Company' : 'Update Company'; ?>
                    </button>
                    <a href="companies.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($action === 'edit' && $editCompany): ?>
        <div class="row mt-4">
            <!-- Assigned Managers -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-person-badge me-2"></i>Assigned Managers
                    </div>
                    <div class="card-body">
                        <?php if (empty($companyManagers)): ?>
                            <p class="text-muted mb-0">No managers assigned to this company.</p>
                        <?php else: ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($companyManagers as $manager): ?>
                                    <li class="mb-2">
                                        <i class="bi bi-person text-warning me-2"></i>
                                        <?php echo e($manager['name']); ?>
                                        <small class="text-muted">(<?php echo e($manager['email']); ?>)</small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Company Clients -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-people me-2"></i>Company Clients
                    </div>
                    <div class="card-body">
                        <?php if (empty($companyClients)): ?>
                            <p class="text-muted mb-0">No clients in this company.</p>
                        <?php else: ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($companyClients as $client): ?>
                                    <li class="mb-2">
                                        <i class="bi bi-person text-info me-2"></i>
                                        <?php echo e($client['name']); ?>
                                        <small class="text-muted">(<?php echo e($client['email']); ?>)</small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php elseif ($action === 'view' && isset($viewCompany)): ?>
    <!-- Company Detail View -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-building me-2"></i><?php echo e($viewCompany['name']); ?>
        </div>
        <div class="card-body">
            <p class="mb-2"><strong>Created:</strong> <?php echo date('F d, Y', strtotime($viewCompany['created_at'])); ?></p>
        </div>
    </div>

    <div class="row">
        <!-- Assigned Managers -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-person-badge me-2"></i>Assigned Managers
                    <span class="badge bg-warning text-dark ms-2"><?php echo count($companyManagers); ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($companyManagers)): ?>
                        <div class="empty-state py-4">
                            <i class="bi bi-person-x" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2 mb-0">No managers assigned</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($companyManagers as $manager): ?>
                                <div class="list-group-item bg-transparent px-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-person-badge text-warning me-2"></i>
                                            <strong><?php echo e($manager['name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo e($manager['email']); ?></small>
                                        </div>
                                        <a href="users.php?action=edit&id=<?php echo $manager['id']; ?>"
                                           class="btn btn-outline-warning btn-sm">Edit</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Company Clients -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-people me-2"></i>Client Users
                    <span class="badge bg-info ms-2"><?php echo count($companyClients); ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($companyClients)): ?>
                        <div class="empty-state py-4">
                            <i class="bi bi-people" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2 mb-0">No clients in this company</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($companyClients as $client): ?>
                                <div class="list-group-item bg-transparent px-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-person text-info me-2"></i>
                                            <strong><?php echo e($client['name']); ?></strong>
                                            <?php if (!$client['is_active']): ?>
                                                <span class="badge bg-secondary ms-2">Inactive</span>
                                            <?php endif; ?>
                                            <br>
                                            <small class="text-muted"><?php echo e($client['email']); ?></small>
                                        </div>
                                        <a href="users.php?action=edit&id=<?php echo $client['id']; ?>"
                                           class="btn btn-outline-warning btn-sm">Edit</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Company List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-building me-2"></i>All Companies</span>
            <input type="text" id="tableSearch" class="form-control form-control-sm" style="width: 200px;"
                   placeholder="Search companies...">
        </div>
        <div class="card-body p-0">
            <?php if (empty($companies)): ?>
                <div class="empty-state">
                    <i class="bi bi-building"></i>
                    <h4>No Companies Yet</h4>
                    <p>Create your first company to get started.</p>
                    <a href="companies.php?action=create" class="btn btn-warning">
                        <i class="bi bi-plus-lg"></i> Add Company
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Company Name</th>
                                <th>Clients</th>
                                <th>Reports</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($companies as $company): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo e($company['name']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $company['client_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $company['report_count']; ?></span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($company['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="companies.php?action=view&id=<?php echo $company['id']; ?>"
                                               class="btn btn-outline-info btn-sm" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="companies.php?action=edit&id=<?php echo $company['id']; ?>"
                                               class="btn btn-outline-warning btn-sm" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo e(generateCSRFToken()); ?>">
                                                <input type="hidden" name="form_action" value="delete">
                                                <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm"
                                                        title="Delete"
                                                        data-confirm-delete="Are you sure you want to delete this company? All associated reports will also be deleted.">
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
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
