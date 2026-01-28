<?php
/**
 * To-Do Management Page
 * Super Admin: Full CRUD + Mark Complete
 * Manager: View + Mark Complete (for assigned companies)
 * Client: View only (public tasks only, read-only)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Company.php';
require_once __DIR__ . '/../classes/Todo.php';

requireAuth();

$userModel = new User();
$companyModel = new Company();
$todoModel = new Todo();

$role = getCurrentUserRole();
$userId = getCurrentUserId();

$errors = [];
$action = $_GET['action'] ?? 'list';
$todoId = isset($_GET['id']) ? (int) $_GET['id'] : null;

// Get companies based on role
if ($role === ROLE_SUPER_ADMIN) {
    $companies = $companyModel->getAll();
} elseif ($role === ROLE_MANAGER) {
    $companies = $userModel->getManagerCompanies($userId);
} else {
    $companies = $userModel->getClientCompanies($userId);
}

// Handle form submissions (Super Admin: full CRUD, Manager: status update only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $formAction = $_POST['form_action'] ?? '';

        // Super Admin: Create/Update/Delete
        if ($role === ROLE_SUPER_ADMIN) {
            if ($formAction === 'create' || $formAction === 'update') {
                $data = [
                    'title' => trim($_POST['title'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                    'company_id' => (int) ($_POST['company_id'] ?? 0),
                    'visibility' => $_POST['visibility'] ?? Todo::VISIBILITY_PRIVATE,
                    'status' => $_POST['status'] ?? Todo::STATUS_PENDING,
                    'priority' => $_POST['priority'] ?? Todo::PRIORITY_MEDIUM,
                    'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
                    'created_by' => $userId
                ];

                // Validation
                if (empty($data['title'])) {
                    $errors[] = 'Title is required.';
                }
                if (empty($data['company_id'])) {
                    $errors[] = 'Please select a project/company.';
                }

                if (empty($errors)) {
                    if ($formAction === 'create') {
                        $todoModel->create($data);
                        $userModel->logActivity($userId, 'todo_create', "Created to-do: {$data['title']}");
                        setFlashMessage('success', 'To-Do created successfully.');
                        redirect('todos.php');
                    } else {
                        $updateId = (int) $_POST['todo_id'];
                        $todoModel->update($updateId, $data);
                        $userModel->logActivity($userId, 'todo_update', "Updated to-do ID: {$updateId}");
                        setFlashMessage('success', 'To-Do updated successfully.');
                        redirect('todos.php');
                    }
                }
            } elseif ($formAction === 'delete') {
                $deleteId = (int) $_POST['todo_id'];
                $todoModel->delete($deleteId);
                $userModel->logActivity($userId, 'todo_delete', "Deleted to-do ID: {$deleteId}");
                setFlashMessage('success', 'To-Do deleted successfully.');
                redirect('todos.php');
            }
        }

        // Admin and Manager can toggle completion
        if (($role === ROLE_SUPER_ADMIN || $role === ROLE_MANAGER) && $formAction === 'toggle_complete') {
            $updateId = (int) $_POST['todo_id'];

            // Verify access for manager
            if ($role === ROLE_MANAGER && !$todoModel->canUserAccess($updateId, $userId, $role)) {
                setFlashMessage('error', 'You do not have access to this task.');
                redirect('todos.php?' . http_build_query($_GET));
            }

            $todo = $todoModel->getById($updateId);
            if ($todo) {
                $newStatus = $todo['status'] === Todo::STATUS_COMPLETED
                    ? Todo::STATUS_PENDING
                    : Todo::STATUS_COMPLETED;
                $todoModel->update($updateId, ['status' => $newStatus]);
                $userModel->logActivity($userId, 'todo_status', "Marked to-do ID: {$updateId} as {$newStatus}");
            }
            redirect('todos.php?' . http_build_query($_GET));
        }

        // Quick status change (Admin only)
        if ($role === ROLE_SUPER_ADMIN && $formAction === 'quick_status') {
            $updateId = (int) $_POST['todo_id'];
            $newStatus = $_POST['new_status'] ?? '';
            if (in_array($newStatus, [Todo::STATUS_PENDING, Todo::STATUS_IN_PROGRESS, Todo::STATUS_COMPLETED])) {
                $todoModel->update($updateId, ['status' => $newStatus]);
                setFlashMessage('success', 'Status updated.');
            }
            redirect('todos.php?' . http_build_query($_GET));
        }
    }
}

// Get filter parameters
$filterCompany = isset($_GET['company']) ? (int) $_GET['company'] : null;
$filterDateFrom = $_GET['date_from'] ?? null;
$filterDateTo = $_GET['date_to'] ?? null;
$showAll = isset($_GET['show_all']);

// Get todos based on role
if ($role === ROLE_SUPER_ADMIN) {
    $todos = $todoModel->getAllForAdmin($filterCompany, $filterDateFrom, $filterDateTo);
} elseif ($role === ROLE_MANAGER) {
    $todos = $todoModel->getForManager($userId, $filterCompany, $filterDateFrom, $filterDateTo);
} else {
    // Client - only public tasks
    $todos = $todoModel->getForClient($userId, $filterCompany, $filterDateFrom, $filterDateTo);
}

// Group todos by due date
$todosByDate = [];
$todosNoDate = [];
foreach ($todos as $todo) {
    if ($todo['due_date']) {
        $dateKey = $todo['due_date'];
        if (!isset($todosByDate[$dateKey])) {
            $todosByDate[$dateKey] = [];
        }
        $todosByDate[$dateKey][] = $todo;
    } else {
        $todosNoDate[] = $todo;
    }
}
// Sort dates
ksort($todosByDate);

// Get edit todo if editing
$editTodo = null;
if ($action === 'edit' && $todoId && $role === ROLE_SUPER_ADMIN) {
    $editTodo = $todoModel->getById($todoId);
    if (!$editTodo) {
        setFlashMessage('error', 'To-Do not found.');
        redirect('todos.php');
    }
}

// Get stats for dashboard
$stats = $todoModel->getStats($filterCompany);

$pageTitle = 'To-Do List';
include __DIR__ . '/includes/header.php';
?>

<style>
    .todo-completed .todo-title {
        text-decoration: line-through;
        color: #6c757d;
    }
    .todo-checkbox {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }
    .date-group-header {
        background: linear-gradient(135deg, #2d2d2d 0%, #1e1e1e 100%);
        padding: 10px 15px;
        border-left: 4px solid #FCC115;
        margin-bottom: 0;
    }
    .date-group-header.today {
        border-left-color: #198754;
    }
    .date-group-header.overdue {
        border-left-color: #dc3545;
    }
    .todo-item {
        padding: 12px 15px;
        border-bottom: 1px solid #333;
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }
    .todo-item:last-child {
        border-bottom: none;
    }
    .todo-content {
        flex: 1;
    }
    .todo-meta {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 5px;
    }
    .no-date-section {
        margin-top: 20px;
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">To-Do List</h1>
        <p class="page-subtitle">
            <?php if ($role === ROLE_SUPER_ADMIN): ?>
                Manage tasks across all projects
            <?php elseif ($role === ROLE_MANAGER): ?>
                View and complete tasks for your projects
            <?php else: ?>
                View your project tasks
            <?php endif; ?>
        </p>
    </div>
    <?php if ($role === ROLE_SUPER_ADMIN && $action === 'list'): ?>
        <a href="todos.php?action=create" class="btn btn-warning btn-icon">
            <i class="bi bi-plus-lg"></i> Add To-Do
        </a>
    <?php elseif ($action !== 'list'): ?>
        <a href="todos.php" class="btn btn-outline-warning btn-icon">
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
    <?php if ($role !== ROLE_SUPER_ADMIN): ?>
        <div class="alert alert-warning">You do not have permission to create or edit tasks.</div>
    <?php else: ?>
    <!-- Create/Edit Form -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-<?php echo $action === 'create' ? 'plus-circle' : 'pencil'; ?> me-2"></i>
            <?php echo $action === 'create' ? 'Create New To-Do' : 'Edit To-Do'; ?>
        </div>
        <div class="card-body">
            <form method="POST" action="" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo e(generateCSRFToken()); ?>">
                <input type="hidden" name="form_action" value="<?php echo $action === 'create' ? 'create' : 'update'; ?>">
                <?php if ($editTodo): ?>
                    <input type="hidden" name="todo_id" value="<?php echo $editTodo['id']; ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="title" class="form-label">Title *</label>
                        <input type="text" class="form-control" id="title" name="title"
                               value="<?php echo e($editTodo['title'] ?? $_POST['title'] ?? ''); ?>" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="company_id" class="form-label">Project/Company *</label>
                        <select class="form-select" id="company_id" name="company_id" required>
                            <option value="">Select Project</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo $company['id']; ?>"
                                    <?php echo (($editTodo['company_id'] ?? $_POST['company_id'] ?? '') == $company['id']) ? 'selected' : ''; ?>>
                                    <?php echo e($company['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo e($editTodo['description'] ?? $_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="due_date" class="form-label">Due Date</label>
                        <input type="date" class="form-control" id="due_date" name="due_date"
                               value="<?php echo e($editTodo['due_date'] ?? $_POST['due_date'] ?? ''); ?>">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="priority" class="form-label">Priority</label>
                        <select class="form-select" id="priority" name="priority">
                            <?php foreach (Todo::getPriorityOptions() as $value => $label): ?>
                                <option value="<?php echo $value; ?>"
                                    <?php echo (($editTodo['priority'] ?? $_POST['priority'] ?? 'medium') === $value) ? 'selected' : ''; ?>>
                                    <?php echo e($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <?php foreach (Todo::getStatusOptions() as $value => $label): ?>
                                <option value="<?php echo $value; ?>"
                                    <?php echo (($editTodo['status'] ?? $_POST['status'] ?? 'pending') === $value) ? 'selected' : ''; ?>>
                                    <?php echo e($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="visibility" class="form-label">Visibility *</label>
                        <select class="form-select" id="visibility" name="visibility" required>
                            <?php foreach (Todo::getVisibilityOptions() as $value => $label): ?>
                                <option value="<?php echo $value; ?>"
                                    <?php echo (($editTodo['visibility'] ?? $_POST['visibility'] ?? 'private') === $value) ? 'selected' : ''; ?>>
                                    <?php echo e($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            <i class="bi bi-info-circle"></i> Private = Admin & Manager only
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-lg"></i>
                        <?php echo $action === 'create' ? 'Create To-Do' : 'Update To-Do'; ?>
                    </button>
                    <a href="todos.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

<?php else: ?>
    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="card text-center p-3">
                <div class="fs-4 fw-bold text-warning"><?php echo $stats['total'] ?? 0; ?></div>
                <small class="text-muted">Total</small>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card text-center p-3">
                <div class="fs-4 fw-bold text-info"><?php echo $stats['pending'] ?? 0; ?></div>
                <small class="text-muted">Pending</small>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card text-center p-3">
                <div class="fs-4 fw-bold text-primary"><?php echo $stats['in_progress'] ?? 0; ?></div>
                <small class="text-muted">In Progress</small>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card text-center p-3">
                <div class="fs-4 fw-bold text-success"><?php echo $stats['completed'] ?? 0; ?></div>
                <small class="text-muted">Completed</small>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card text-center p-3">
                <div class="fs-4 fw-bold text-warning"><?php echo $stats['due_today'] ?? 0; ?></div>
                <small class="text-muted">Due Today</small>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card text-center p-3">
                <div class="fs-4 fw-bold text-danger"><?php echo $stats['overdue'] ?? 0; ?></div>
                <small class="text-muted">Overdue</small>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="company" class="form-label">Project/Company</label>
                    <select class="form-select" id="company" name="company">
                        <option value="">All Projects</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>"
                                <?php echo ($filterCompany == $company['id']) ? 'selected' : ''; ?>>
                                <?php echo e($company['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="date_from" name="date_from"
                           value="<?php echo e($filterDateFrom ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="date_to" name="date_to"
                           value="<?php echo e($filterDateTo ?? ''); ?>">
                </div>
                <div class="col-md-5">
                    <button type="submit" class="btn btn-outline-warning me-2">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <a href="todos.php?show_all=1" class="btn btn-outline-secondary me-2">Show All</a>
                    <a href="todos.php?date_from=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-info">Today</a>
                </div>
            </form>
        </div>
    </div>

    <!-- To-Do List Grouped by Date -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>
                <i class="bi bi-list-check me-2"></i>
                Tasks (<?php echo count($todos); ?>)
            </span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($todos)): ?>
                <div class="empty-state">
                    <i class="bi bi-clipboard-check"></i>
                    <h4>No Tasks Found</h4>
                    <p>
                        <?php if ($filterCompany || $filterDateFrom): ?>
                            No tasks match your filter criteria.
                        <?php else: ?>
                            No tasks have been created yet.
                        <?php endif; ?>
                    </p>
                    <?php if ($role === ROLE_SUPER_ADMIN): ?>
                        <a href="todos.php?action=create" class="btn btn-warning">
                            <i class="bi bi-plus-lg"></i> Create First Task
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>

                <?php
                $today = date('Y-m-d');
                foreach ($todosByDate as $date => $dateTodos):
                    $isToday = ($date === $today);
                    $isOverdue = ($date < $today);
                    $headerClass = $isOverdue ? 'overdue' : ($isToday ? 'today' : '');
                ?>
                    <div class="date-group-header <?php echo $headerClass; ?>">
                        <strong>
                            <?php if ($isToday): ?>
                                <i class="bi bi-calendar-check text-success me-2"></i>Today
                            <?php elseif ($isOverdue): ?>
                                <i class="bi bi-exclamation-triangle text-danger me-2"></i><?php echo date('l, F j, Y', strtotime($date)); ?>
                                <span class="badge bg-danger ms-2">Overdue</span>
                            <?php else: ?>
                                <i class="bi bi-calendar3 me-2"></i><?php echo date('l, F j, Y', strtotime($date)); ?>
                            <?php endif; ?>
                        </strong>
                        <span class="badge bg-secondary ms-2"><?php echo count($dateTodos); ?> task(s)</span>
                    </div>

                    <?php foreach ($dateTodos as $todo): ?>
                        <?php
                        $isCompleted = ($todo['status'] === 'completed');
                        ?>
                        <div class="todo-item <?php echo $isCompleted ? 'todo-completed' : ''; ?>">
                            <?php if ($role !== ROLE_CLIENT): ?>
                                <!-- Checkbox for Admin and Manager -->
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo e(generateCSRFToken()); ?>">
                                    <input type="hidden" name="form_action" value="toggle_complete">
                                    <input type="hidden" name="todo_id" value="<?php echo $todo['id']; ?>">
                                    <input type="checkbox" class="form-check-input todo-checkbox"
                                           <?php echo $isCompleted ? 'checked' : ''; ?>
                                           onchange="this.form.submit()"
                                           title="<?php echo $isCompleted ? 'Mark as pending' : 'Mark as complete'; ?>">
                                </form>
                            <?php else: ?>
                                <!-- Read-only indicator for Client -->
                                <i class="bi bi-<?php echo $isCompleted ? 'check-circle-fill text-success' : 'circle'; ?>" style="font-size: 1.2rem;"></i>
                            <?php endif; ?>

                            <div class="todo-content">
                                <div class="todo-title">
                                    <strong><?php echo e($todo['title']); ?></strong>
                                </div>
                                <?php if ($todo['description']): ?>
                                    <small class="text-muted"><?php echo e(substr($todo['description'], 0, 150)); ?><?php echo strlen($todo['description']) > 150 ? '...' : ''; ?></small>
                                <?php endif; ?>
                                <div class="todo-meta">
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-building"></i> <?php echo e($todo['company_name']); ?>
                                    </span>
                                    <?php
                                    $priorityClass = match($todo['priority']) {
                                        'high' => 'bg-danger',
                                        'medium' => 'bg-warning text-dark',
                                        'low' => 'bg-secondary',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?php echo $priorityClass; ?>">
                                        <?php echo e(ucfirst($todo['priority'])); ?>
                                    </span>
                                    <?php if ($role !== ROLE_CLIENT): ?>
                                        <?php if ($todo['visibility'] === 'public'): ?>
                                            <span class="badge bg-info"><i class="bi bi-globe"></i> Public</span>
                                        <?php else: ?>
                                            <span class="badge bg-dark"><i class="bi bi-lock"></i> Private</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($role === ROLE_SUPER_ADMIN): ?>
                                <div class="action-buttons">
                                    <a href="todos.php?action=edit&id=<?php echo $todo['id']; ?>"
                                       class="btn btn-outline-warning btn-sm" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo e(generateCSRFToken()); ?>">
                                        <input type="hidden" name="form_action" value="delete">
                                        <input type="hidden" name="todo_id" value="<?php echo $todo['id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                                title="Delete"
                                                data-confirm-delete="Are you sure you want to delete this task?">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>

                <?php if (!empty($todosNoDate)): ?>
                    <div class="date-group-header no-date-section">
                        <strong><i class="bi bi-calendar-x me-2"></i>No Due Date</strong>
                        <span class="badge bg-secondary ms-2"><?php echo count($todosNoDate); ?> task(s)</span>
                    </div>

                    <?php foreach ($todosNoDate as $todo): ?>
                        <?php
                        $isCompleted = ($todo['status'] === 'completed');
                        ?>
                        <div class="todo-item <?php echo $isCompleted ? 'todo-completed' : ''; ?>">
                            <?php if ($role !== ROLE_CLIENT): ?>
                                <!-- Checkbox for Admin and Manager -->
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo e(generateCSRFToken()); ?>">
                                    <input type="hidden" name="form_action" value="toggle_complete">
                                    <input type="hidden" name="todo_id" value="<?php echo $todo['id']; ?>">
                                    <input type="checkbox" class="form-check-input todo-checkbox"
                                           <?php echo $isCompleted ? 'checked' : ''; ?>
                                           onchange="this.form.submit()"
                                           title="<?php echo $isCompleted ? 'Mark as pending' : 'Mark as complete'; ?>">
                                </form>
                            <?php else: ?>
                                <!-- Read-only indicator for Client -->
                                <i class="bi bi-<?php echo $isCompleted ? 'check-circle-fill text-success' : 'circle'; ?>" style="font-size: 1.2rem;"></i>
                            <?php endif; ?>

                            <div class="todo-content">
                                <div class="todo-title">
                                    <strong><?php echo e($todo['title']); ?></strong>
                                </div>
                                <?php if ($todo['description']): ?>
                                    <small class="text-muted"><?php echo e(substr($todo['description'], 0, 150)); ?><?php echo strlen($todo['description']) > 150 ? '...' : ''; ?></small>
                                <?php endif; ?>
                                <div class="todo-meta">
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-building"></i> <?php echo e($todo['company_name']); ?>
                                    </span>
                                    <?php
                                    $priorityClass = match($todo['priority']) {
                                        'high' => 'bg-danger',
                                        'medium' => 'bg-warning text-dark',
                                        'low' => 'bg-secondary',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?php echo $priorityClass; ?>">
                                        <?php echo e(ucfirst($todo['priority'])); ?>
                                    </span>
                                    <?php if ($role !== ROLE_CLIENT): ?>
                                        <?php if ($todo['visibility'] === 'public'): ?>
                                            <span class="badge bg-info"><i class="bi bi-globe"></i> Public</span>
                                        <?php else: ?>
                                            <span class="badge bg-dark"><i class="bi bi-lock"></i> Private</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($role === ROLE_SUPER_ADMIN): ?>
                                <div class="action-buttons">
                                    <a href="todos.php?action=edit&id=<?php echo $todo['id']; ?>"
                                       class="btn btn-outline-warning btn-sm" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo e(generateCSRFToken()); ?>">
                                        <input type="hidden" name="form_action" value="delete">
                                        <input type="hidden" name="todo_id" value="<?php echo $todo['id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                                title="Delete"
                                                data-confirm-delete="Are you sure you want to delete this task?">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
