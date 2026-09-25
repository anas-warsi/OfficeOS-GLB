
-- SET FOREIGN_KEY_CHECKS = 1;
-- CREATE TABLE users (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     username VARCHAR(50) NOT NULL UNIQUE,
--     email VARCHAR(100) NOT NULL UNIQUE,
--     password_hash VARCHAR(255) NOT NULL, -- In production, store hashed 
-- passwords
--     full_name VARCHAR(100),
--     role ENUM('admin', 'manager', 'employee') NOT NULL DEFAULT 'employee',
--     status ENUM('active', 'inactive') DEFAULT 'active',
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE 
-- CURRENT_TIMESTAMP,
--     INDEX idx_email (email),
--     INDEX idx_role (role)
-- );


-- CREATE TABLE departments (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     name VARCHAR(50) NOT NULL UNIQUE, -- e.g., 'Sales', 'IT'
--     description TEXT,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
-- );

-- CREATE TABLE user_depts (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     user_id INT NOT NULL,
--     dept_id INT NOT NULL,
--     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
--     FOREIGN KEY (dept_id) REFERENCES departments(id) ON DELETE CASCADE,
--     UNIQUE KEY unique_user_dept (user_id, dept_id)
-- );

-- CREATE TABLE leave_requests (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     user_id INT NOT NULL, -- The Employee requesting leave
--     request_type VARCHAR(50), -- 'Annual', 'Sick', 'Unpaid'
--     start_date DATE NOT NULL,
--     end_date DATE NOT NULL,
--     reason TEXT,
--     status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    
--     -- Optional: Track who made the final decision if needed
--     approved_by_user_id INT, 
    
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
--     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
--     INDEX idx_request_status (status),
--     FOREIGN KEY (approved_by_user_id) REFERENCES users(id) -- 
-- Admin/Manager who approved
-- );

-- CREATE TABLE tasks (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     title VARCHAR(255) NOT NULL,
--     description TEXT,
--     created_by_user_id INT NOT NULL, -- The Manager who created the task 
-- list
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
-- );

-- CREATE TABLE task_assignments (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     task_id INT NOT NULL,
--     employee_id INT NOT NULL,
    
--     -- Status of the specific assignment
--     status ENUM('assigned', 'in_progress', 'completed', 'cancelled') 
-- DEFAULT 'assigned',
    
--     -- Specific deadline for this employee on this task
--     due_date DATE,
    
--     -- Notes from manager regarding allocation
--     notes TEXT,
    
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
--     FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
--     FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
--     INDEX idx_task_employee (task_id, employee_id),
--     UNIQUE KEY unique_task_employee_status (task_id, employee_id, status) 
-- -- Ensure 1 active task per person usually
-- );

-- CREATE TABLE activity_logs (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     user_id INT NOT NULL, -- Who performed the action
--     action_type VARCHAR(100), -- 'left_request', 'assigned_task', 
-- 'approved_leave'
--     target_entity_id INT, -- ID of LeaveRequest or Task involved
--     notes TEXT,
--     ip_address VARCHAR(45), -- For security logging
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
--     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
--     INDEX idx_log_time (created_at)
-- );

-- SET FOREIGN_KEY_CHECKS = 0;
-- ```

-- ---

-- ### 2. Schema Design Logic

-- #### A. Users & Roles
-- *   **Design:** Instead of creating separate tables for Admins and 
-- Employees, we use a `users` table with a `role` ENUM column (`'admin'`, 
-- `'manager'`, `'employee'`).
-- *   **Security:** This keeps authentication logic clean. The password 
-- field should store Bcrypt hashes, not plain text.

-- #### B. Departments (Optional but Recommended)
-- *   In many companies, an "Employee" reports to a specific Department Head 
-- (Manager), who reports to a higher Manager.
-- *   I added the `departments` and `user_depts` tables. This allows you to 
-- query: *"Show me all leave requests for the Sales Department"*.

-- #### C. Leave Request Logic
-- *   **Requester:** Uses `employee_id` from the `users` table.
-- *   **Approval:** The schema includes `approved_by_user_id`.
--     *   A Manager sees a list of `'pending'` leaves.
--     *   An Admin might have a higher authority (or vice versa) to override 
-- decisions.
--     *   You can check who approved the request using: `SELECT 
-- user.full_name FROM users JOIN leave_requests...`.

-- #### D. Task Allocation Logic
-- *   **Creator:** Managers create tasks via the `tasks` table 
-- (`created_by_user_id`).
-- *   **Assignment:** The `task_assignments` table is a junction table 
-- (Many-to-Many relationship).
--     *   This allows one Task to be assigned to multiple employees, or an 
-- Employee to take on multiple Tasks.
--     *   It tracks the specific status (`assigned`, `completed`) per 
-- person, not just globally.

-- ---

-- ### 3. Example: How to use this (Logic Flow)

-- #### Step 1: Create Dummy Data (Admin, Manager, Employee)
-- ```sql
-- -- Insert User (Manager John)
-- INSERT INTO users (username, email, password_hash, full_name, role) 
-- VALUES ('john_m', 'john.m@company.com', '$2b$...', 'John Miller', 
-- 'manager');

-- -- Insert User (Employee Sarah)
-- INSERT INTO users (username, email, password_hash, full_name, role) 
-- VALUES ('sarah_e', 'sarah.e@company.com', '$2b$...', 'Sarah Smith', 
-- 'employee');

-- -- Assign Sarah to a Department
-- INSERT INTO user_depts (user_id, dept_id) VALUES (LAST_INSERT_ID(), 1);
-- ```

-- #### Step 2: Employee Submits Leave
-- *The application will insert a row into `leave_requests` with status = 
-- 'pending'.*
-- ```sql
-- INSERT INTO leave_requests (user_id, start_date, end_date, reason, status) 

-- VALUES (ID_SARAH, '2023-12-10', '2023-12-12', 'Family Emergency', 
-- 'pending');
-- ```

-- #### Step 3: Manager Approves Leave
-- *The application looks for all leaves where `status = 'pending'`. The 
-- Manager clicks approve.*
-- ```sql
-- -- Update the status and record who approved it
-- UPDATE leave_requests 
-- SET status = 'approved', approved_by_user_id = ID_JOHN 
-- WHERE id = ID_LEAVE_REQUEST;

-- -- (Optional) Log action in activity_logs table
-- ```

-- #### Step 4: Task Assignment
-- *Manager assigns a task to Sarah.*
-- ```sql
-- INSERT INTO tasks (title, description, created_by_user_id) 
-- VALUES ('Website Redesign', 'Update the homepage', ID_JOHN); -- Gets 
-- TaskID_1

-- INSERT INTO task_assignments (task_id, employee_id, status, due_date) 
-- VALUES (ID_TASK_1, ID_SARAH, 'assigned', '2023-12-20');

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 25, 2026 at 09:50 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `officeos_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(100) DEFAULT NULL,
  `target_entity_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `request_type` varchar(50) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `task_assignments`
--

CREATE TABLE `task_assignments` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `status` enum('assigned','in_progress','completed','cancelled') DEFAULT 'assigned',
  `due_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('admin','manager','employee') NOT NULL DEFAULT 'employee',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'john_m', 'john.m@company.com', '$2b$...', 'John Miller', 'manager', 'active', '2026-09-25 07:45:19', '2026-09-25 07:45:19'),
(2, 'sarah_e', 'sarah.e@company.com', '$2b$...', 'Sarah Smith', 'employee', 'active', '2026-09-25 07:45:19', '2026-09-25 07:45:19');

-- --------------------------------------------------------

--
-- Table structure for table `user_depts`
--

CREATE TABLE `user_depts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `dept_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_log_time` (`created_at`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_request_status` (`status`),
  ADD KEY `approved_by_user_id` (`approved_by_user_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `task_assignments`
--
ALTER TABLE `task_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_task_employee_status` (`task_id`,`employee_id`,`status`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `idx_task_employee` (`task_id`,`employee_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- Indexes for table `user_depts`
--
ALTER TABLE `user_depts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_dept` (`user_id`,`dept_id`),
  ADD KEY `dept_id` (`dept_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `task_assignments`
--
ALTER TABLE `task_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_depts`
--
ALTER TABLE `user_depts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `task_assignments`
--
ALTER TABLE `task_assignments`
  ADD CONSTRAINT `task_assignments_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_assignments_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_depts`
--
ALTER TABLE `user_depts`
  ADD CONSTRAINT `user_depts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_depts_ibfk_2` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;



