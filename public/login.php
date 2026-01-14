<?php
/**
 * Login Page
 * Client Reporting Portal
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $userModel = new User();
        $user = $userModel->authenticate($email, $password);

        if ($user) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['company_id'] = $user['company_id'];

            // Regenerate session ID for security
            session_regenerate_id(true);

            // Log the login activity
            $userModel->logActivity($user['id'], 'login', 'User logged in');

            // Redirect to dashboard
            redirect('dashboard.php');
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$pageTitle = 'Login';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
    <div class="auth-logo">
        <a href="dashboard.php" class="brand-logo">
            <img src="assets/img/logo.png" alt="Logo" >
        </a>
        <h1><?php echo e(APP_NAME); ?></h1>
        <p>Sign in to access your reports</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php echo e($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" class="needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo e(generateCSRFToken()); ?>">

        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary">
                    <i class="bi bi-envelope text-warning"></i>
                </span>
                <input type="email" class="form-control" id="email" name="email"
                       placeholder="Enter your email" required
                       value="<?php echo e($_POST['email'] ?? ''); ?>">
            </div>
        </div>

        <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary">
                    <i class="bi bi-lock text-warning"></i>
                </span>
                <input type="password" class="form-control" id="password" name="password"
                       placeholder="Enter your password" required>
                <button class="btn btn-outline-secondary password-toggle" type="button">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>
        
        <button type="submit" class="btn btn-warning w-100 d-flex justify-content-center align-items-center gap-2">
            <i class="bi bi-box-arrow-in-right"></i>
            <span>Sign In</span>
        </button>
    </form>

    <div class="text-center mt-4">
        <small class="text-muted">
            <i class="bi bi-shield-check"></i> Secure Login Portal
        </small>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
