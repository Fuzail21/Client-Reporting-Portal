-- Client Reporting Portal Database Schema
-- Complete schema including M:N relationships and To-Do module
-- Run this script to create the database and all tables

CREATE DATABASE IF NOT EXISTS client_reporting_portal
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE client_reporting_portal;

-- Disable foreign key checks during table creation
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- Core Tables
-- =====================================================

-- Companies Table
DROP TABLE IF EXISTS companies;
CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users Table
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'manager', 'client') DEFAULT 'client',
    company_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Assignment Tables (Many-to-Many Relationships)
-- =====================================================

-- Manager-Company Mapping (Many-to-Many)
DROP TABLE IF EXISTS manager_assignments;
CREATE TABLE manager_assignments (
    manager_id INT NOT NULL,
    company_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (manager_id, company_id),
    INDEX idx_manager (manager_id),
    INDEX idx_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Client-Company Mapping (Many-to-Many)
DROP TABLE IF EXISTS client_assignments;
CREATE TABLE client_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    company_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_assignment (client_id, company_id),
    INDEX idx_client (client_id),
    INDEX idx_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Reports Table
-- =====================================================

DROP TABLE IF EXISTS reports;
CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    uploaded_by INT,
    title VARCHAR(255) NOT NULL,
    category ENUM('SEO', 'SMM', 'Web Dev', 'Other') DEFAULT 'SEO',
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company (company_id),
    INDEX idx_category (category),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- To-Do Module
-- =====================================================

DROP TABLE IF EXISTS todos;
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
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Activity Log Table
-- =====================================================

DROP TABLE IF EXISTS activity_logs;
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Add Foreign Keys (after all tables exist)
-- =====================================================

-- Users -> Companies
ALTER TABLE users
    ADD CONSTRAINT fk_users_company
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL;

-- Manager Assignments
ALTER TABLE manager_assignments
    ADD CONSTRAINT fk_manager_assignments_manager
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE manager_assignments
    ADD CONSTRAINT fk_manager_assignments_company
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

-- Client Assignments
ALTER TABLE client_assignments
    ADD CONSTRAINT fk_client_assignments_client
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE client_assignments
    ADD CONSTRAINT fk_client_assignments_company
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

-- Reports
ALTER TABLE reports
    ADD CONSTRAINT fk_reports_company
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

ALTER TABLE reports
    ADD CONSTRAINT fk_reports_uploader
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL;

-- Todos
ALTER TABLE todos
    ADD CONSTRAINT fk_todos_company
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

ALTER TABLE todos
    ADD CONSTRAINT fk_todos_creator
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE;

-- Activity Logs
ALTER TABLE activity_logs
    ADD CONSTRAINT fk_activity_logs_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- Default Data
-- =====================================================

-- Insert default Super Admin (password: password)
-- IMPORTANT: Change this password immediately after first login!
INSERT INTO users (name, email, password_hash, role) VALUES
('Super Admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');

-- =====================================================
-- Verification
-- =====================================================
SELECT 'Database setup completed successfully!' AS status;
SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'client_reporting_portal';
