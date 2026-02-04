<?php
/**
 * Report Model Class
 * Handles all report-related database operations
 */

require_once __DIR__ . '/../config/config.php';

class Report {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create a new report
     */
    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO reports (company_id, uploaded_by, title, category, file_name, original_name, file_size)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['company_id'],
            $data['uploaded_by'],
            $data['title'],
            $data['category'],
            $data['file_name'],
            $data['original_name'],
            $data['file_size'] ?? 0
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update report
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $values = [];

        if (isset($data['title'])) {
            $fields[] = 'title = ?';
            $values[] = $data['title'];
        }
        if (isset($data['category'])) {
            $fields[] = 'category = ?';
            $values[] = $data['category'];
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $sql = "UPDATE reports SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Delete report (also removes file)
     */
    public function delete(int $id): bool {
        // Get file info first
        $report = $this->getById($id);
        if ($report) {
            // Delete the physical file
            $filePath = REPORTS_PATH . '/' . $report['file_name'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $stmt = $this->db->prepare("DELETE FROM reports WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Get report by ID
     */
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT r.*, c.name as company_name, u.name as uploader_name
            FROM reports r
            LEFT JOIN companies c ON r.company_id = c.id
            LEFT JOIN users u ON r.uploaded_by = u.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get all reports with optional filtering
     */
    public function getAll(?int $companyId = null, ?string $category = null, ?int $limit = null): array {
        $sql = "
            SELECT r.*, c.name as company_name, u.name as uploader_name
            FROM reports r
            LEFT JOIN companies c ON r.company_id = c.id
            LEFT JOIN users u ON r.uploaded_by = u.id
            WHERE 1=1
        ";
        $params = [];

        if ($companyId) {
            $sql .= " AND r.company_id = ?";
            $params[] = $companyId;
        }
        if ($category) {
            $sql .= " AND r.category = ?";
            $params[] = $category;
        }

        $sql .= " ORDER BY r.created_at DESC";

        if ($limit) {
            $sql .= " LIMIT " . (int) $limit;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get reports for a specific company
     */
    public function getByCompany(int $companyId, ?string $category = null): array {
        return $this->getAll($companyId, $category);
    }

    /**
     * Get reports by category for a company
     */
    public function getByCompanyGroupedByCategory(int $companyId): array {
        $reports = $this->getByCompany($companyId);
        $grouped = [];

        foreach (REPORT_CATEGORIES as $category) {
            $grouped[$category] = [];
        }

        foreach ($reports as $report) {
            $grouped[$report['category']][] = $report;
        }

        return $grouped;
    }

    /**
     * Get reports for companies managed by a specific manager
     */
    public function getForManager(int $managerId): array {
        $stmt = $this->db->prepare("
            SELECT r.*, c.name as company_name, u.name as uploader_name
            FROM reports r
            INNER JOIN manager_assignments ma ON r.company_id = ma.company_id
            LEFT JOIN companies c ON r.company_id = c.id
            LEFT JOIN users u ON r.uploaded_by = u.id
            WHERE ma.manager_id = ?
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$managerId]);
        return $stmt->fetchAll();
    }

    /**
     * Get recent reports (for dashboard)
     */
    public function getRecent(int $limit = 10): array {
        return $this->getAll(null, null, $limit);
    }

    /**
     * Get reports for a client using M:N client_assignments
     */
    public function getForClient(int $clientId, ?int $companyId = null, ?string $category = null): array {
        $sql = "
            SELECT r.*, c.name as company_name, u.name as uploader_name
            FROM reports r
            INNER JOIN client_assignments ca ON r.company_id = ca.company_id
            LEFT JOIN companies c ON r.company_id = c.id
            LEFT JOIN users u ON r.uploaded_by = u.id
            WHERE ca.client_id = ?
        ";
        $params = [$clientId];

        if ($companyId) {
            $sql .= " AND r.company_id = ?";
            $params[] = $companyId;
        }
        if ($category) {
            $sql .= " AND r.category = ?";
            $params[] = $category;
        }

        $sql .= " ORDER BY r.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get report count by category for a client (M:N)
     */
    public function getCountByCategoryForClient(int $clientId): array {
        $stmt = $this->db->prepare("
            SELECT r.category, COUNT(*) as count
            FROM reports r
            INNER JOIN client_assignments ca ON r.company_id = ca.company_id
            WHERE ca.client_id = ?
            GROUP BY r.category
        ");
        $stmt->execute([$clientId]);
        $results = $stmt->fetchAll();

        $counts = [];
        foreach (REPORT_CATEGORIES as $category) {
            $counts[$category] = 0;
        }
        foreach ($results as $row) {
            $counts[$row['category']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Get report count by category for a company
     */
    public function getCountByCategory(int $companyId): array {
        $stmt = $this->db->prepare("
            SELECT category, COUNT(*) as count
            FROM reports
            WHERE company_id = ?
            GROUP BY category
        ");
        $stmt->execute([$companyId]);
        $results = $stmt->fetchAll();

        $counts = [];
        foreach (REPORT_CATEGORIES as $category) {
            $counts[$category] = 0;
        }
        foreach ($results as $row) {
            $counts[$row['category']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Check if user can access report
     */
    public function canUserAccess(int $reportId, int $userId, string $userRole, ?int $userCompanyId = null): bool {
        $report = $this->getById($reportId);
        if (!$report) {
            return false;
        }

        // Super admin can access all reports
        if ($userRole === ROLE_SUPER_ADMIN) {
            return true;
        }

        // Manager can access reports from their assigned companies
        if ($userRole === ROLE_MANAGER) {
            $stmt = $this->db->prepare("
                SELECT 1 FROM manager_assignments
                WHERE manager_id = ? AND company_id = ?
            ");
            $stmt->execute([$userId, $report['company_id']]);
            return (bool) $stmt->fetch();
        }

        // Client can access reports from their assigned companies (M:N)
        if ($userRole === ROLE_CLIENT) {
            $stmt = $this->db->prepare("
                SELECT 1 FROM client_assignments
                WHERE client_id = ? AND company_id = ?
            ");
            $stmt->execute([$userId, $report['company_id']]);
            return (bool) $stmt->fetch();
        }

        return false;
    }

    /**
     * Validate uploaded file
     */
    public function validateUpload(array $file): array {
        $errors = [];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed. Please try again.';
            return $errors;
        }

        // Check file size
        if ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = 'File size exceeds maximum allowed (' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB).';
        }

        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ALLOWED_EXTENSIONS)) {
            $errors[] = 'Only HTML (.html, .htm) and PDF (.pdf) files are allowed.';
        }

        // Additional MIME type check
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $allowedMimes = ['text/html', 'text/plain', 'application/octet-stream', 'application/pdf'];
        if (!in_array($mimeType, $allowedMimes)) {
            $errors[] = 'Invalid file type detected.';
        }

        return $errors;
    }

    /**
     * Process and save uploaded file
     */
    public function saveUploadedFile(array $file): ?string {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $secureFilename = generateSecureFilename($extension);
        $destination = REPORTS_PATH . '/' . $secureFilename;

        // Ensure storage directory exists
        if (!is_dir(REPORTS_PATH)) {
            mkdir(REPORTS_PATH, 0755, true);
        }

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return $secureFilename;
        }

        return null;
    }

    /**
     * Get file path for a report
     */
    public function getFilePath(int $reportId): ?string {
        $report = $this->getById($reportId);
        if (!$report) {
            return null;
        }

        $path = REPORTS_PATH . '/' . $report['file_name'];
        return file_exists($path) ? $path : null;
    }
}
