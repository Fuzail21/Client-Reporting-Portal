<?php
/**
 * Company Model Class
 * Handles all company-related database operations
 */

require_once __DIR__ . '/../config/config.php';

class Company {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create a new company
     */
    public function create(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO companies (name) VALUES (?)");
        $stmt->execute([$data['name']]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update company
     */
    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare("UPDATE companies SET name = ? WHERE id = ?");
        return $stmt->execute([$data['name'], $id]);
    }

    /**
     * Delete company
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM companies WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Get company by ID
     */
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM companies WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get all companies
     */
    public function getAll(): array {
        $stmt = $this->db->query("SELECT * FROM companies ORDER BY name ASC");
        return $stmt->fetchAll();
    }

    /**
     * Get companies with statistics
     */
    public function getAllWithStats(): array {
        $stmt = $this->db->query("
            SELECT c.*,
                   COUNT(DISTINCT CASE WHEN u.role = 'client' THEN u.id END) as client_count,
                   COUNT(DISTINCT r.id) as report_count
            FROM companies c
            LEFT JOIN users u ON c.id = u.company_id
            LEFT JOIN reports r ON c.id = r.company_id
            GROUP BY c.id
            ORDER BY c.name ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Get managers assigned to a company
     */
    public function getAssignedManagers(int $companyId): array {
        $stmt = $this->db->prepare("
            SELECT u.id, u.name, u.email
            FROM users u
            INNER JOIN manager_assignments ma ON u.id = ma.manager_id
            WHERE ma.company_id = ?
            ORDER BY u.name
        ");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }

    /**
     * Get clients for a company
     */
    public function getClients(int $companyId): array {
        $stmt = $this->db->prepare("
            SELECT id, name, email, is_active, created_at
            FROM users
            WHERE company_id = ? AND role = 'client'
            ORDER BY name
        ");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }

    /**
     * Check if company name exists
     */
    public function nameExists(string $name, ?int $excludeId = null): bool {
        $sql = "SELECT id FROM companies WHERE name = ?";
        $params = [$name];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    /**
     * Get report count for company
     */
    public function getReportCount(int $companyId): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM reports WHERE company_id = ?");
        $stmt->execute([$companyId]);
        return (int) $stmt->fetchColumn();
    }
}
