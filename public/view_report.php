<?php
/**
 * Secure Report Viewer (Proxy)
 * Client Reporting Portal
 *
 * This script securely serves HTML reports by:
 * 1. Validating user session
 * 2. Checking user permissions for the requested report
 * 3. Serving the file content without exposing the actual file path
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Report.php';
require_once __DIR__ . '/../classes/User.php';

requireAuth();

$reportId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$inline = isset($_GET['inline']); // Whether to display inline in iframe

if (!$reportId) {
    http_response_code(400);
    die('Invalid report ID.');
}

$reportModel = new Report();
$report = $reportModel->getById($reportId);

if (!$report) {
    http_response_code(404);
    die('Report not found.');
}

// Check if user has permission to access this report
$canAccess = $reportModel->canUserAccess(
    $reportId,
    getCurrentUserId(),
    getCurrentUserRole(),
    getCurrentUserCompanyId()
);

if (!$canAccess) {
    http_response_code(403);
    die('You do not have permission to view this report.');
}

// Get the file path
$filePath = $reportModel->getFilePath($reportId);

if (!$filePath || !file_exists($filePath)) {
    http_response_code(404);
    die('Report file not found.');
}

// Log the view activity
$userModel = new User();
$userModel->logActivity(getCurrentUserId(), 'report_view',
    "Viewed report: {$report['title']} (ID: {$reportId})");

// Serve the file
$fileSize = filesize($filePath);
$extension = strtolower(pathinfo($report['file_name'], PATHINFO_EXTENSION));

// Set appropriate headers based on file type
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// Prevent caching of confidential reports
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($extension === 'pdf') {
    // Serve PDF file
    header('Content-Type: application/pdf');
    header('Content-Length: ' . $fileSize);
    header('Content-Disposition: inline; filename="' . basename($report['original_name']) . '"');
    readfile($filePath);
} else {
    // Serve HTML file
    $fileContent = file_get_contents($filePath);
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Length: ' . $fileSize);

    // Allow inline styles and scripts for HTML reports to render properly
    // Also allow common CDNs for Bootstrap, Google Fonts, etc.
    header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' data: blob:; img-src 'self' data: blob: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https:; font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https:; frame-ancestors 'self';");

    echo $fileContent;
}
exit;
