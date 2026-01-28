-- Client Reporting Portal - Migration v2
-- Adds: Client-Company M:N relationship and To-Do module
-- Run this AFTER the initial schema.sql

USE client_reporting_portal;

-- Temporarily disable foreign key checks
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- Client-Company Many-to-Many Relationship
-- =====================================================

-- Drop table if exists (for clean re-run)
DROP TABLE IF EXISTS client_assignments;

-- Client-Company Mapping (Many-to-Many)
-- Allows one client to be assigned to multiple companies/projects
CREATE TABLE client_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    company_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_assignment (client_id, company_id),
    INDEX idx_client (client_id),
    INDEX idx_company (company_id),
    CONSTRAINT fk_client_assignments_client
        FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_client_assignments_company
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing client-company relationships to the new table
-- This preserves existing data from users.company_id
INSERT IGNORE INTO client_assignments (client_id, company_id)
SELECT id, company_id FROM users
WHERE role = 'client' AND company_id IS NOT NULL;

-- =====================================================
-- To-Do Module
-- =====================================================

-- Drop table if exists (for clean re-run)
DROP TABLE IF EXISTS todos;

-- To-Do Tasks Table
CREATE TABLE todos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    company_id INT NOT NULL,
    created_by INT NOT NULL,
    visibility ENUM('private', 'public') DEFAULT 'private',
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    due_date DATE NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company (company_id),
    INDEX idx_visibility (visibility),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    INDEX idx_created_by (created_by),
    CONSTRAINT fk_todos_company
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_todos_creator
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- Verification
-- =====================================================
SELECT 'Migration v2 completed successfully!' AS status;
