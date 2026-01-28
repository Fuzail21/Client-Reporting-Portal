<?php
/**
 * User Model Class
 * Handles all user-related database operations
 */

require_once __DIR__ . '/../config/config.php';

class User {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Authenticate user with email and password
     */
    public function authenticate(string $email, string $password): ?array {
        $stmt = $this->db->prepare("
            SELECT id, name, email, password_hash, role, company_id, is_active
            FROM users
            WHERE email = ? AND is_active = 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            unset($user['password_hash']);
            return $user;
        }
        return null;
    }

    /**
     * Create a new user
     */
    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password_hash, role, company_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['email'],
            password_hash($data['password'], PASSWORD_BCRYPT),
            $data['role'],
            $data['company_id'] ?? null
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update user
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $values = [];

        if (isset($data['name'])) {
            $fields[] = 'name = ?';
            $values[] = $data['name'];
        }
        if (isset($data['email'])) {
            $fields[] = 'email = ?';
            $values[] = $data['email'];
        }
        if (!empty($data['password'])) {
            $fields[] = 'password_hash = ?';
            $values[] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        if (isset($data['role'])) {
            $fields[] = 'role = ?';
            $values[] = $data['role'];
        }
        if (array_key_exists('company_id', $data)) {
            $fields[] = 'company_id = ?';
            $values[] = $data['company_id'];
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = ?';
            $values[] = $data['is_active'];
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Delete user
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Get user by ID
     */
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT u.*, c.name as company_name
            FROM users u
            LEFT JOIN companies c ON u.company_id = c.id
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if ($user) {
            unset($user['password_hash']);
        }
        return $user ?: null;
    }

    /**
     * Get user by email
     */
    public function getByEmail(string $email): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get all users with optional filtering
     */
    public function getAll(?string $role = null, ?int $companyId = null): array {
        $sql = "
            SELECT u.id, u.name, u.email, u.role, u.company_id, u.is_active, u.created_at,
                   c.name as company_name
            FROM users u
            LEFT JOIN companies c ON u.company_id = c.id
            WHERE 1=1
        ";
        $params = [];

        if ($role) {
            $sql .= " AND u.role = ?";
            $params[] = $role;
        }
        if ($companyId) {
            $sql .= " AND u.company_id = ?";
            $params[] = $companyId;
        }

        $sql .= " ORDER BY u.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get all managers
     */
    public function getManagers(): array {
        return $this->getAll(ROLE_MANAGER);
    }

    /**
     * Get all clients
     */
    public function getClients(): array {
        return $this->getAll(ROLE_CLIENT);
    }

    /**
     * Check if email exists (for validation)
     */
    public function emailExists(string $email, ?int $excludeId = null): bool {
        $sql = "SELECT id FROM users WHERE email = ?";
        $params = [$email];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    /**
     * Get companies assigned to a manager
     */
    public function getManagerCompanies(int $managerId): array {
        $stmt = $this->db->prepare("
            SELECT c.*
            FROM companies c
            INNER JOIN manager_assignments ma ON c.id = ma.company_id
            WHERE ma.manager_id = ?
            ORDER BY c.name
        ");
        $stmt->execute([$managerId]);
        return $stmt->fetchAll();
    }

    /**
     * Assign manager to company
     */
    public function assignManagerToCompany(int $managerId, int $companyId): bool {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO manager_assignments (manager_id, company_id)
            VALUES (?, ?)
        ");
        return $stmt->execute([$managerId, $companyId]);
    }

    /**
     * Remove manager from company
     */
    public function removeManagerFromCompany(int $managerId, int $companyId): bool {
        $stmt = $this->db->prepare("
            DELETE FROM manager_assignments
            WHERE manager_id = ? AND company_id = ?
        ");
        return $stmt->execute([$managerId, $companyId]);
    }

    /**
     * Update all manager assignments
     */
    public function updateManagerAssignments(int $managerId, array $companyIds): bool {
        // First, remove all existing assignments
        $stmt = $this->db->prepare("DELETE FROM manager_assignments WHERE manager_id = ?");
        $stmt->execute([$managerId]);

        // Then add new assignments
        if (!empty($companyIds)) {
            $stmt = $this->db->prepare("
                INSERT INTO manager_assignments (manager_id, company_id) VALUES (?, ?)
            ");
            foreach ($companyIds as $companyId) {
                $stmt->execute([$managerId, $companyId]);
            }
        }
        return true;
    }

    /**
     * Check if manager is assigned to company
     */
    public function isManagerAssignedToCompany(int $managerId, int $companyId): bool {
        $stmt = $this->db->prepare("
            SELECT 1 FROM manager_assignments
            WHERE manager_id = ? AND company_id = ?
        ");
        $stmt->execute([$managerId, $companyId]);
        return (bool) $stmt->fetch();
    }

    /**
     * Log user activity
     */
    public function logActivity(int $userId, string $action, string $description = ''): bool {
        $stmt = $this->db->prepare("
            INSERT INTO activity_logs (user_id, action, description, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([
            $userId,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }

    // =====================================================
    // Client-Company M:N Methods
    // =====================================================

    /**
     * Get companies assigned to a client (M:N relationship)
     */
    public function getClientCompanies(int $clientId): array {
        $stmt = $this->db->prepare("
            SELECT c.*
            FROM companies c
            INNER JOIN client_assignments ca ON c.id = ca.company_id
            WHERE ca.client_id = ?
            ORDER BY c.name
        ");
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    /**
     * Assign client to company
     */
    public function assignClientToCompany(int $clientId, int $companyId): bool {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO client_assignments (client_id, company_id)
            VALUES (?, ?)
        ");
        return $stmt->execute([$clientId, $companyId]);
    }

    /**
     * Remove client from company
     */
    public function removeClientFromCompany(int $clientId, int $companyId): bool {
        $stmt = $this->db->prepare("
            DELETE FROM client_assignments
            WHERE client_id = ? AND company_id = ?
        ");
        return $stmt->execute([$clientId, $companyId]);
    }

    /**
     * Update all client assignments
     */
    public function updateClientAssignments(int $clientId, array $companyIds): bool {
        // First, remove all existing assignments
        $stmt = $this->db->prepare("DELETE FROM client_assignments WHERE client_id = ?");
        $stmt->execute([$clientId]);

        // Then add new assignments
        if (!empty($companyIds)) {
            $stmt = $this->db->prepare("
                INSERT INTO client_assignments (client_id, company_id) VALUES (?, ?)
            ");
            foreach ($companyIds as $companyId) {
                $stmt->execute([$clientId, $companyId]);
            }
        }
        return true;
    }

    /**
     * Check if client is assigned to company
     */
    public function isClientAssignedToCompany(int $clientId, int $companyId): bool {
        $stmt = $this->db->prepare("
            SELECT 1 FROM client_assignments
            WHERE client_id = ? AND company_id = ?
        ");
        $stmt->execute([$clientId, $companyId]);
        return (bool) $stmt->fetch();
    }

    /**
     * Get clients assigned to a company
     */
    public function getClientsByCompany(int $companyId): array {
        $stmt = $this->db->prepare("
            SELECT u.id, u.name, u.email, u.is_active, ca.assigned_at
            FROM users u
            INNER JOIN client_assignments ca ON u.id = ca.client_id
            WHERE ca.company_id = ? AND u.role = 'client'
            ORDER BY u.name
        ");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }
}
