<?php
/**
 * Activity Log Page (Super Admin Only)
 * Client Reporting Portal
 */

require_once __DIR__ . '/../config/config.php';

requireRole([ROLE_SUPER_ADMIN]);

$db = Database::getInstance();

// Get activity logs with pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Get total count
$totalStmt = $db->query("SELECT COUNT(*) FROM activity_logs");
$totalCount = $totalStmt->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

// Get logs
$stmt = $db->prepare("
    SELECT al.*, u.name as user_name, u.email as user_email
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$pageTitle = 'Activity Log';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Activity Log</h1>
        <p class="page-subtitle">System activity and audit trail</p>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-2"></i>Recent Activity</span>
        <span class="badge bg-secondary"><?php echo number_format($totalCount); ?> total entries</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <i class="bi bi-clock-history"></i>
                <h4>No Activity Yet</h4>
                <p>Activity logs will appear here as users interact with the system.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <small>
                                        <?php echo date('M d, Y', strtotime($log['created_at'])); ?>
                                        <br>
                                        <span class="text-muted"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></span>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($log['user_name']): ?>
                                        <strong><?php echo e($log['user_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo e($log['user_email']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Unknown User</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $actionBadge = match($log['action']) {
                                        'login' => 'bg-success',
                                        'logout' => 'bg-secondary',
                                        'report_upload' => 'bg-info',
                                        'report_view' => 'bg-warning text-dark',
                                        'report_delete' => 'bg-danger',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?php echo $actionBadge; ?>">
                                        <?php echo e(ucwords(str_replace('_', ' ', $log['action']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo e($log['description'] ?: '-'); ?></small>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo e($log['ip_address'] ?: '-'); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="card-footer bg-transparent">
                    <nav aria-label="Activity log pagination">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                            </li>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            ?>

                            <?php if ($startPage > 1): ?>
                                <li class="page-item"><a class="page-link" href="?page=1">1</a></li>
                                <?php if ($startPage > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item"><a class="page-link" href="?page=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a></li>
                            <?php endif; ?>

                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
