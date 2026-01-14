<?php
/**
 * Application Configuration
 * Client Reporting Portal
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base paths
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('REPORTS_PATH', STORAGE_PATH . '/reports');
define('PUBLIC_PATH', ROOT_PATH . '/public');

// Application settings
define('APP_NAME', 'Client Reporting Portal');
define('APP_URL', 'http://localhost/Client%20Reporting%20Portal/public');

// Security settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 3600); // 1 hour

// File upload settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['html', 'htm']);

// Report categories
define('REPORT_CATEGORIES', ['SEO', 'SMM', 'Web Dev', 'Other']);

// User roles
define('ROLE_SUPER_ADMIN', 'super_admin');
define('ROLE_MANAGER', 'manager');
define('ROLE_CLIENT', 'client');

// Include database configuration
require_once CONFIG_PATH . '/database.php';

/**
 * Generate CSRF token
 */
function generateCSRFToken(): string {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken(string $token): bool {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Sanitize output for XSS prevention
 */
function e(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect helper
 */
function redirect(string $url): void {
    header("Location: " . $url);
    exit;
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user role
 */
function getCurrentUserRole(): ?string {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Get current user ID
 */
function getCurrentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user's company ID (for clients)
 */
function getCurrentUserCompanyId(): ?int {
    return $_SESSION['company_id'] ?? null;
}

/**
 * Check if current user has specific role
 */
function hasRole(string $role): bool {
    return getCurrentUserRole() === $role;
}

/**
 * Require authentication
 */
function requireAuth(): void {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

/**
 * Require specific role
 */
function requireRole(array $roles): void {
    requireAuth();
    if (!in_array(getCurrentUserRole(), $roles)) {
        redirect('dashboard.php?error=unauthorized');
    }
}

/**
 * Flash message helper
 */
function setFlashMessage(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Generate random filename for uploaded reports
 */
function generateSecureFilename(string $extension): string {
    return 'rep_' . bin2hex(random_bytes(8)) . '.' . $extension;
}
