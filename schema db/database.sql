CREATE DATABASE IF NOT EXISTS officeos_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE officeos_db;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(120) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    role ENUM('employee', 'manager', 'admin') NOT NULL,
    department VARCHAR(80) DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_username (username),
    UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tasks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(160) NOT NULL,
    description TEXT DEFAULT NULL,
    priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_created_by (created_by),
    CONSTRAINT fk_tasks_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS task_assignments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    due_date DATE DEFAULT NULL,
    status ENUM('assigned', 'in_progress', 'completed') NOT NULL DEFAULT 'assigned',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_task_employee (task_id, employee_id),
    KEY idx_employee_id (employee_id),
    CONSTRAINT fk_assignments_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_assignments_employee FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    check_in TIME DEFAULT NULL,
    check_out TIME DEFAULT NULL,
    status ENUM('present', 'absent', 'late') NOT NULL DEFAULT 'present',
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_user_date (user_id, work_date),
    CONSTRAINT fk_attendance_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS leave_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    leave_type VARCHAR(40) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reviewed_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_leave_status (status),
    CONSTRAINT fk_leave_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_leave_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO users (username, email, password_hash, full_name, role, department, status) VALUES
('admin', 'admin@officeos.local', '$2y$12$qZbu8BjJlAQ7OlMki61aiOuuZtCV/kIict5eNofkn1ATr0J48IdHK', 'System Admin', 'admin', 'Operations', 'active'),
('manager', 'manager@officeos.local', '$2y$12$4SJKl3RNaLJKJxbACN6QwusLSmv71fSusQTcytATYsghF4EbNjjau', 'Team Manager', 'manager', 'Operations', 'active'),
('employee', 'employee@officeos.local', '$2y$12$Qm21piuetpqDJ2WHtjbVnOjDShQOmiux2h4HCMN76Rj/SyUGGRbnq', 'Office Employee', 'employee', 'Support', 'active')
ON DUPLICATE KEY UPDATE username = VALUES(username);

INSERT INTO tasks (title, description, priority, created_by) VALUES
('Review monthly attendance', 'Check attendance exceptions for the current month.', 'medium', 1),
('Prepare task board', 'Split active work into clear weekly tasks.', 'high', 2),
('Update profile details', 'Make sure the employee record is current.', 'low', 1)
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO task_assignments (task_id, employee_id, due_date, status) VALUES
(1, 3, '2026-09-26', 'in_progress'),
(2, 3, '2026-09-27', 'assigned'),
(3, 3, '2026-09-28', 'assigned')
ON DUPLICATE KEY UPDATE status = VALUES(status);

INSERT INTO attendance (user_id, work_date, check_in, check_out, status, notes) VALUES
(3, CURDATE(), '09:00:00', NULL, 'present', 'Checked in from demo data')
ON DUPLICATE KEY UPDATE status = VALUES(status), notes = VALUES(notes);

INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason, status, reviewed_by) VALUES
(3, 'annual', '2026-10-02', '2026-10-03', 'Personal leave request', 'pending', NULL)
ON DUPLICATE KEY UPDATE status = VALUES(status);
