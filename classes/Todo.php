<?php
/**
 * Todo Model Class
 * Handles all to-do related database operations with visibility rules
 */

require_once __DIR__ . '/../config/config.php';

class Todo {
    private PDO $db;

    // Visibility constants
    const VISIBILITY_PRIVATE = 'private';
    const VISIBILITY_PUBLIC = 'public';

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';

    // Priority constants
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create a new to-do (Super Admin only)
     */
    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO todos (title, description, company_id, created_by, visibility, status, priority, due_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['title'],
            $data['description'] ?? null,
            $data['company_id'],
            $data['created_by'],
            $data['visibility'] ?? self::VISIBILITY_PRIVATE,
            $data['status'] ?? self::STATUS_PENDING,
            $data['priority'] ?? self::PRIORITY_MEDIUM,
            $data['due_date'] ?? null
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a to-do (Super Admin only)
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $values = [];

        $allowedFields = ['title', 'description', 'company_id', 'visibility', 'status', 'priority', 'due_date'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        // Handle completion timestamp
        if (isset($data['status']) && $data['status'] === self::STATUS_COMPLETED) {
            $fields[] = 'completed_at = NOW()';
        } elseif (isset($data['status']) && $data['status'] !== self::STATUS_COMPLETED) {
            $fields[] = 'completed_at = NULL';
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $sql = "UPDATE todos SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Delete a to-do (Super Admin only)
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM todos WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Get to-do by ID
     */
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT t.*, c.name as company_name, u.name as creator_name
            FROM todos t
            LEFT JOIN companies c ON t.company_id = c.id
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get all to-dos for Super Admin (full access)
     */
    public function getAllForAdmin(?int $companyId = null, ?string $dateFrom = null, ?string $dateTo = null): array {
        $sql = "
            SELECT t.*, c.name as company_name, u.name as creator_name
            FROM todos t
            LEFT JOIN companies c ON t.company_id = c.id
            LEFT JOIN users u ON t.created_by = u.id
            WHERE 1=1
        ";
        $params = [];

        if ($companyId) {
            $sql .= " AND t.company_id = ?";
            $params[] = $companyId;
        }

        // Date filtering
        if ($dateFrom && $dateTo) {
            $sql .= " AND t.due_date BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        } elseif ($dateFrom) {
            $sql .= " AND t.due_date >= ?";
            $params[] = $dateFrom;
        } elseif ($dateTo) {
            $sql .= " AND t.due_date <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY t.due_date ASC, t.priority DESC, t.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get to-dos for Manager (all tasks for assigned companies)
     * Manager can see both private and public tasks
     */
    public function getForManager(int $managerId, ?int $companyId = null, ?string $dateFrom = null, ?string $dateTo = null): array {
        $sql = "
            SELECT t.*, c.name as company_name, u.name as creator_name
            FROM todos t
            INNER JOIN manager_assignments ma ON t.company_id = ma.company_id
            LEFT JOIN companies c ON t.company_id = c.id
            LEFT JOIN users u ON t.created_by = u.id
            WHERE ma.manager_id = ?
        ";
        $params = [$managerId];

        if ($companyId) {
            $sql .= " AND t.company_id = ?";
            $params[] = $companyId;
        }

        // Date filtering
        if ($dateFrom && $dateTo) {
            $sql .= " AND t.due_date BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        } elseif ($dateFrom) {
            $sql .= " AND t.due_date >= ?";
            $params[] = $dateFrom;
        } elseif ($dateTo) {
            $sql .= " AND t.due_date <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY t.due_date ASC, t.priority DESC, t.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get to-dos for Client (PUBLIC tasks only for assigned companies)
     * CRITICAL: Clients can only see public visibility tasks
     */
    public function getForClient(int $clientId, ?int $companyId = null, ?string $dateFrom = null, ?string $dateTo = null): array {
        $sql = "
            SELECT t.*, c.name as company_name, u.name as creator_name
            FROM todos t
            INNER JOIN client_assignments ca ON t.company_id = ca.company_id
            LEFT JOIN companies c ON t.company_id = c.id
            LEFT JOIN users u ON t.created_by = u.id
            WHERE ca.client_id = ?
            AND t.visibility = 'public'
        ";
        $params = [$clientId];

        if ($companyId) {
            $sql .= " AND t.company_id = ?";
            $params[] = $companyId;
        }

        // Date filtering
        if ($dateFrom && $dateTo) {
            $sql .= " AND t.due_date BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        } elseif ($dateFrom) {
            $sql .= " AND t.due_date >= ?";
            $params[] = $dateFrom;
        } elseif ($dateTo) {
            $sql .= " AND t.due_date <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY t.due_date ASC, t.priority DESC, t.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get today's to-dos based on role
     */
    public function getTodaysTodos(int $userId, string $role, ?int $companyId = null): array {
        $today = date('Y-m-d');

        if ($role === ROLE_SUPER_ADMIN) {
            return $this->getAllForAdmin($companyId, $today, $today);
        } elseif ($role === ROLE_MANAGER) {
            return $this->getForManager($userId, $companyId, $today, $today);
        } else {
            return $this->getForClient($userId, $companyId, $today, $today);
        }
    }

    /**
     * Get to-do count by company
     */
    public function getCountByCompany(int $companyId, ?string $visibility = null): int {
        $sql = "SELECT COUNT(*) FROM todos WHERE company_id = ?";
        $params = [$companyId];

        if ($visibility) {
            $sql .= " AND visibility = ?";
            $params[] = $visibility;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get to-do statistics for dashboard
     */
    public function getStats(?int $companyId = null): array {
        $whereClause = $companyId ? "WHERE company_id = ?" : "";
        $params = $companyId ? [$companyId] : [];

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN due_date = CURDATE() THEN 1 ELSE 0 END) as due_today,
                SUM(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) as overdue
            FROM todos
            $whereClause
        ");
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * Check if user can access this to-do
     */
    public function canUserAccess(int $todoId, int $userId, string $role): bool {
        $todo = $this->getById($todoId);
        if (!$todo) {
            return false;
        }

        // Super Admin can access all
        if ($role === ROLE_SUPER_ADMIN) {
            return true;
        }

        // Manager - check if assigned to the company
        if ($role === ROLE_MANAGER) {
            $stmt = $this->db->prepare("
                SELECT 1 FROM manager_assignments
                WHERE manager_id = ? AND company_id = ?
            ");
            $stmt->execute([$userId, $todo['company_id']]);
            return (bool) $stmt->fetch();
        }

        // Client - check if assigned AND task is public
        if ($role === ROLE_CLIENT) {
            if ($todo['visibility'] !== self::VISIBILITY_PUBLIC) {
                return false; // Private tasks are never visible to clients
            }
            $stmt = $this->db->prepare("
                SELECT 1 FROM client_assignments
                WHERE client_id = ? AND company_id = ?
            ");
            $stmt->execute([$userId, $todo['company_id']]);
            return (bool) $stmt->fetch();
        }

        return false;
    }

    /**
     * Get visibility options
     */
    public static function getVisibilityOptions(): array {
        return [
            self::VISIBILITY_PRIVATE => 'Private (Admin & Manager)',
            self::VISIBILITY_PUBLIC => 'Public (All Users)'
        ];
    }

    /**
     * Get status options
     */
    public static function getStatusOptions(): array {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed'
        ];
    }

    /**
     * Get priority options
     */
    public static function getPriorityOptions(): array {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_HIGH => 'High'
        ];
    }
}
