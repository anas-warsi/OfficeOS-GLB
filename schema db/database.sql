-- Enable foreign keys for data integrity
SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================
-- 1. USERS TABLE (Stores Login & Roles)
-- ==========================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL, -- In production, store hashed 
passwords
    full_name VARCHAR(100),
    role ENUM('admin', 'manager', 'employee') NOT NULL DEFAULT 'employee',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE 
CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
);

-- ==========================================
-- 2. DEPARTMENTS TABLE (Organizational Structure)
-- Managers usually manage a specific department/team.
-- ==========================================
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- e.g., 'Sales', 'IT'
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Link Users to Departments (Most employees/manager belong here)
CREATE TABLE user_depts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    dept_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (dept_id) REFERENCES departments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_dept (user_id, dept_id)
);

-- ==========================================
-- 3. LEAVE REQUESTS TABLE
-- ==========================================
CREATE TABLE leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, -- The Employee requesting leave
    request_type VARCHAR(50), -- 'Annual', 'Sick', 'Unpaid'
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    
    -- Optional: Track who made the final decision if needed
    approved_by_user_id INT, 
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_request_status (status),
    FOREIGN KEY (approved_by_user_id) REFERENCES users(id) -- 
Admin/Manager who approved
);

-- ==========================================
-- 4. TASK ASSIGNMENTS TABLE
-- Linking Managers to Tasks and Employees
-- ==========================================
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    created_by_user_id INT NOT NULL, -- The Manager who created the task 
list
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE task_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    employee_id INT NOT NULL,
    
    -- Status of the specific assignment
    status ENUM('assigned', 'in_progress', 'completed', 'cancelled') 
DEFAULT 'assigned',
    
    -- Specific deadline for this employee on this task
    due_date DATE,
    
    -- Notes from manager regarding allocation
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_task_employee (task_id, employee_id),
    UNIQUE KEY unique_task_employee_status (task_id, employee_id, status) 
-- Ensure 1 active task per person usually
);

-- ==========================================
-- 5. ACTIVITY LOGS TABLE (Optional but recommended for Audits)
-- ==========================================
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, -- Who performed the action
    action_type VARCHAR(100), -- 'left_request', 'assigned_task', 
'approved_leave'
    target_entity_id INT, -- ID of LeaveRequest or Task involved
    notes TEXT,
    ip_address VARCHAR(45), -- For security logging
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_log_time (created_at)
);

SET FOREIGN_KEY_CHECKS = 0;
```

---

### 2. Schema Design Logic

#### A. Users & Roles
*   **Design:** Instead of creating separate tables for Admins and 
Employees, we use a `users` table with a `role` ENUM column (`'admin'`, 
`'manager'`, `'employee'`).
*   **Security:** This keeps authentication logic clean. The password 
field should store Bcrypt hashes, not plain text.

#### B. Departments (Optional but Recommended)
*   In many companies, an "Employee" reports to a specific Department Head 
(Manager), who reports to a higher Manager.
*   I added the `departments` and `user_depts` tables. This allows you to 
query: *"Show me all leave requests for the Sales Department"*.

#### C. Leave Request Logic
*   **Requester:** Uses `employee_id` from the `users` table.
*   **Approval:** The schema includes `approved_by_user_id`.
    *   A Manager sees a list of `'pending'` leaves.
    *   An Admin might have a higher authority (or vice versa) to override 
decisions.
    *   You can check who approved the request using: `SELECT 
user.full_name FROM users JOIN leave_requests...`.

#### D. Task Allocation Logic
*   **Creator:** Managers create tasks via the `tasks` table 
(`created_by_user_id`).
*   **Assignment:** The `task_assignments` table is a junction table 
(Many-to-Many relationship).
    *   This allows one Task to be assigned to multiple employees, or an 
Employee to take on multiple Tasks.
    *   It tracks the specific status (`assigned`, `completed`) per 
person, not just globally.

---

### 3. Example: How to use this (Logic Flow)

#### Step 1: Create Dummy Data (Admin, Manager, Employee)
```sql
-- Insert User (Manager John)
INSERT INTO users (username, email, password_hash, full_name, role) 
VALUES ('john_m', 'john.m@company.com', '$2b$...', 'John Miller', 
'manager');

-- Insert User (Employee Sarah)
INSERT INTO users (username, email, password_hash, full_name, role) 
VALUES ('sarah_e', 'sarah.e@company.com', '$2b$...', 'Sarah Smith', 
'employee');

-- Assign Sarah to a Department
INSERT INTO user_depts (user_id, dept_id) VALUES (LAST_INSERT_ID(), 1);
```

#### Step 2: Employee Submits Leave
*The application will insert a row into `leave_requests` with status = 
'pending'.*
```sql
INSERT INTO leave_requests (user_id, start_date, end_date, reason, status) 

VALUES (ID_SARAH, '2023-12-10', '2023-12-12', 'Family Emergency', 
'pending');
```

#### Step 3: Manager Approves Leave
*The application looks for all leaves where `status = 'pending'`. The 
Manager clicks approve.*
```sql
-- Update the status and record who approved it
UPDATE leave_requests 
SET status = 'approved', approved_by_user_id = ID_JOHN 
WHERE id = ID_LEAVE_REQUEST;

-- (Optional) Log action in activity_logs table
```

#### Step 4: Task Assignment
*Manager assigns a task to Sarah.*
```sql
INSERT INTO tasks (title, description, created_by_user_id) 
VALUES ('Website Redesign', 'Update the homepage', ID_JOHN); -- Gets 
TaskID_1

INSERT INTO task_assignments (task_id, employee_id, status, due_date) 
VALUES (ID_TASK_1, ID_SARAH, 'assigned', '2023-12-20');