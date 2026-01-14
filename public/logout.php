<?php
/**
 * Logout Handler
 * Client Reporting Portal
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';

// Log the logout activity if user was logged in
if (isLoggedIn()) {
    $userModel = new User();
    $userModel->logActivity(getCurrentUserId(), 'logout', 'User logged out');
}

// Destroy session
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

// Redirect to login
header('Location: login.php');
exit;
