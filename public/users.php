<?php
/**
 * User Management Page (Super Admin Only)
 * Client Reporting Portal
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Company.php';

requireRole([ROLE_SUPER_ADMIN]);

$userModel = new User();
$companyModel = new Company();

$action = $_GET['action'] ?? 'list';
$userId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$errors = [];
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $formAction = $_POST['form_action'] ?? '';

        if ($formAction === 'create' || $formAction === 'update') {
            $data = [
                'name' => trim($_POST['name'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'role' => $_POST['role'] ?? ROLE_CLIENT,
                'company_id' => !empty($_POST['company_id']) ? (int) $_POST['company_id'] : null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];

            if (!empty($_POST['password'])) {
                $data['password'] = $_POST['password'];
            }

            // Validation
            if (empty($data['name'])) {
                $errors[] = 'Name is required.';
            }
            if (empty($data['email'])) {
                $errors[] = 'Email is required.';
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format.';
            }
            if ($formAction === 'create' && empty($data['password'])) {
                $errors[] = 'Password is required for new users.';
            }
            if (!empty($data['password']) && strlen($data['password']) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            }

            // Check email uniqueness
            $excludeId = $formAction === 'update' ? (int) $_POST['user_id'] : null;
            if ($userModel->emailExists($data['email'], $excludeId)) {
                $errors[] = 'Email is already in use.';
            }

            // Company required for clients
            if ($data['role'] === ROLE_CLIENT && empty($data['company_id'])) {
                $errors[] = 'Company is required for client users.';
            }

            if (empty($errors)) {
                if ($formAction === 'create') {
                    $newUserId = $userModel->create($data);

                    // Handle manager assignments
                    if ($data['role'] === ROLE_MANAGER && !empty($_POST['assigned_companies'])) {
                        $userModel->updateManagerAssignments($newUserId, $_POST['assigned_companies']);
                    }

                    setFlashMessage('success', 'User created successfully.');
                    redirect('users.php');
                } else {
                    $updateId = (int) $_POST['user_id'];
                    $userModel->update($updateId, $data);

                    // Handle manager assignments
                    if ($data['role'] === ROLE_MANAGER) {
                        $assignedCompanies = $_POST['assigned_companies'] ?? [];
                        $userModel->updateManagerAssignments($updateId, $assignedCompanies);
                    }

                    setFlashMessage('success', 'User updated successfully.');
                    redirect('users.php');
                }
            }
        } elseif ($formAction === 'delete') {
            $deleteId = (int) $_POST['user_id'];
            // Prevent self-deletion
            if ($deleteId === getCurrentUserId()) {
                setFlashMessage('error', 'You cannot delete your own account.');
            } else {
                $userModel->delete($deleteId);
                setFlashMessage('success', 'User deleted successfully.');
            }
            redirect('users.php');
        }
    }
}

// Get data for views
$users = $userModel->getAll();
$companies = $companyModel->getAll();
$editUser = null;
$userAssignedCompanies = [];

if ($action === 'edit' && $userId) {
    $editUser = $userModel->getById($userId);
    if (!$editUser) {
        setFlashMessage('error', 'User not found.');
        redirect('users.php');
    }
    if ($editUser['role'] === ROLE_MANAGER) {
        $userAssignedCompanies = array_column($userModel->getManagerCompanies($userId), 'id');
    }
}

$pageTitle = 'Manage Users';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Manage Users</h1>
        <p class="page-subtitle">Create and manage system users</p>
    </div>
    <?php if ($action === 'list'): ?>
        <a href="users.php?action=create" class="btn btn-warning btn-icon">
            <i class="bi bi-plus-lg"></i> Add User
        </a>
    <?php else: ?>
        <a href="users.php" class="btn btn-outline-warning btn-icon">
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
            <i class="bi bi-person-<?php echo $action === 'create' ? 'plus' : 'gear'; ?> me-2"></i>
            <?php echo $action === 'create' ? 'Create New User' : 'Edit User'; ?>
        </div>
        <div class="card-body">
            <form method="POST" action="" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo e(generateCSRFToken()); ?>">
                <input type="hidden" name="form_action" value="<?php echo $action === 'create' ? 'create' : 'update'; ?>">
                <?php if ($editUser): ?>
                    <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="<?php echo e($editUser['name'] ?? $_POST['name'] ?? ''); ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?php echo e($editUser['email'] ?? $_POST['email'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">
                            Password <?php echo $action === 'create' ? '*' : '(leave blank to keep current)'; ?>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password"
                                   <?php echo $action === 'create' ? 'required' : ''; ?>>
                            <button class="btn btn-outline-secondary password-toggle" type="button">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Minimum 8 characters</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="role" class="form-label">Role *</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="<?php echo ROLE_SUPER_ADMIN; ?>"
                                <?php echo (($editUser['role'] ?? $_POST['role'] ?? '') === ROLE_SUPER_ADMIN) ? 'selected' : ''; ?>>
                                Super Admin
                            </option>
                            <option value="<?php echo ROLE_MANAGER; ?>"
                                <?php echo (($editUser['role'] ?? $_POST['role'] ?? '') === ROLE_MANAGER) ? 'selected' : ''; ?>>
                                Manager
                            </option>
                            <option value="<?php echo ROLE_CLIENT; ?>"
                                <?php echo (($editUser['role'] ?? $_POST['role'] ?? '') === ROLE_CLIENT) ? 'selected' : ''; ?>>
                                Client
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Company selection for Clients -->
                <div class="mb-3" id="companyField" style="display: none;">
                    <label for="company_id" class="form-label">Company *</label>
                    <select class="form-select" id="company_id" name="company_id">
                        <option value="">Select Company</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>"
                                <?php echo (($editUser['company_id'] ?? $_POST['company_id'] ?? '') == $company['id']) ? 'selected' : ''; ?>>
                                <?php echo e($company['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Company assignments for Managers -->
                <div class="mb-3" id="managerAssignments" style="display: none;">
                    <label class="form-label">Assigned Companies</label>
                    <div class="company-assignment-list">
                        <?php foreach ($companies as $company): ?>
                            <div class="company-assignment-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="assigned_companies[]" value="<?php echo $company['id']; ?>"
                                           id="company_<?php echo $company['id']; ?>"
                                           <?php echo in_array($company['id'], $userAssignedCompanies) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="company_<?php echo $company['id']; ?>">
                                        <?php echo e($company['name']); ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text">Select companies this manager can access</div>
                </div>

                <div class="mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                               <?php echo ($editUser['is_active'] ?? true) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">
                            Active Account
                        </label>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-lg"></i>
                        <?php echo $action === 'create' ? 'Create User' : 'Update User'; ?>
                    </button>
                    <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role');
            const companyField = document.getElementById('companyField');
            const managerAssignments = document.getElementById('managerAssignments');

            function toggleFields() {
                const role = roleSelect.value;
                companyField.style.display = role === 'client' ? 'block' : 'none';
                managerAssignments.style.display = role === 'manager' ? 'block' : 'none';
            }

            roleSelect.addEventListener('change', toggleFields);
            toggleFields(); // Initial state
        });
    </script>

<?php else: ?>
    <!-- User List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-people me-2"></i>All Users</span>
            <input type="text" id="tableSearch" class="form-control form-control-sm" style="width: 200px;"
                   placeholder="Search users...">
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Company</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><strong><?php echo e($user['name']); ?></strong></td>
                                <td><?php echo e($user['email']); ?></td>
                                <td>
                                    <span class="badge <?php
                                        echo match($user['role']) {
                                            ROLE_SUPER_ADMIN => 'bg-danger',
                                            ROLE_MANAGER => 'bg-warning text-dark',
                                            default => 'bg-info'
                                        };
                                    ?>">
                                        <?php echo e(ucwords(str_replace('_', ' ', $user['role']))); ?>
                                    </span>
                                </td>
                                <td><?php echo e($user['company_name'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="users.php?action=edit&id=<?php echo $user['id']; ?>"
                                           class="btn btn-outline-warning btn-sm" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($user['id'] !== getCurrentUserId()): ?>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo e(generateCSRFToken()); ?>">
                                                <input type="hidden" name="form_action" value="delete">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm"
                                                        title="Delete"
                                                        data-confirm-delete="Are you sure you want to delete this user?">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
