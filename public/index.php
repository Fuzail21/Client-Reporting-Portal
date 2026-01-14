<?php
/**
 * Index - Redirect to appropriate page
 * Client Reporting Portal
 */

require_once __DIR__ . '/../config/config.php';

// Redirect to dashboard if logged in, otherwise to login
if (isLoggedIn()) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}
