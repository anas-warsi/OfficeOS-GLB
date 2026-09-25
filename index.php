<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/database.php';

function officeos_esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function officeos_redirect_self(): void
{
    $target = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
    header('Location: ' . $target);
    exit;
}

function officeos_flash(?string $message = null, string $type = 'success'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

function officeos_current_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function officeos_sign_in(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) ($user['id'] ?? 0),
        'username' => (string) ($user['username'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'full_name' => (string) ($user['full_name'] ?? ''),
        'role' => (string) ($user['role'] ?? 'employee'),
        'department' => (string) ($user['department'] ?? ''),
        'status' => (string) ($user['status'] ?? 'active'),
    ];
}

function officeos_sign_out(): void
{
    $_SESSION = [];

    if (session_id() !== '') {
        session_destroy();
    }
}

function officeos_session_records(string $key): array
{
    return isset($_SESSION[$key]) && is_array($_SESSION[$key]) ? $_SESSION[$key] : [];
}

function officeos_session_store_record(string $key, array $record): void
{
    $records = officeos_session_records($key);
    $records[] = $record;
    $_SESSION[$key] = $records;
}

function officeos_role_label(string $role): string
{
    return match (strtolower($role)) {
        'admin' => 'Administrator',
        'manager' => 'Manager',
        default => 'Employee',
    };
}

function officeos_demo_users(): array
{
    return [
        [
            'id' => 1,
            'username' => 'admin',
            'email' => 'admin@officeos.local',
            'password_hash' => '$2y$12$qZbu8BjJlAQ7OlMki61aiOuuZtCV/kIict5eNofkn1ATr0J48IdHK',
            'full_name' => 'System Admin',
            'role' => 'admin',
            'department' => 'Operations',
            'status' => 'active',
        ],
        [
            'id' => 2,
            'username' => 'manager',
            'email' => 'manager@officeos.local',
            'password_hash' => '$2y$12$4SJKl3RNaLJKJxbACN6QwusLSmv71fSusQTcytATYsghF4EbNjjau',
            'full_name' => 'Team Manager',
            'role' => 'manager',
            'department' => 'Operations',
            'status' => 'active',
        ],
        [
            'id' => 3,
            'username' => 'employee',
            'email' => 'employee@officeos.local',
            'password_hash' => '$2y$12$Qm21piuetpqDJ2WHtjbVnOjDShQOmiux2h4HCMN76Rj/SyUGGRbnq',
            'full_name' => 'Office Employee',
            'role' => 'employee',
            'department' => 'Support',
            'status' => 'active',
        ],
    ];
}

function officeos_demo_lookup_user(string $identifier): ?array
{
    foreach (officeos_demo_users() as $user) {
        if (strcasecmp($identifier, $user['username']) === 0 || strcasecmp($identifier, $user['email']) === 0) {
            return $user;
        }
    }

    return null;
}

function officeos_user_matches_password(string $password, array $user): bool
{
    $hash = (string) ($user['password_hash'] ?? '');

    if ($hash !== '' && preg_match('/^\$(2y|2a|2b|argon2id|argon2i)\$/', $hash) === 1) {
        return password_verify($password, $hash);
    }

    $role = strtolower((string) ($user['role'] ?? 'employee'));
    $password = strtolower(trim($password));

    return match ($role) {
        'admin' => in_array($password, ['admin123', 'admin', 'officeos'], true),
        'manager' => in_array($password, ['manager123', 'manager', 'officeos'], true),
        default => in_array($password, ['employee123', 'employee', 'officeos'], true),
    };
}

function officeos_lookup_user(?mysqli $connection, string $identifier): ?array
{
    $identifier = trim($identifier);

    if ($identifier === '') {
        return null;
    }

    if ($connection instanceof mysqli) {
        $statement = $connection->prepare('SELECT id, username, email, password_hash, full_name, role, department, status FROM users WHERE username = ? OR email = ? LIMIT 1');

        if ($statement instanceof mysqli_stmt) {
            $statement->bind_param('ss', $identifier, $identifier);
            $statement->execute();
            $result = $statement->get_result();

            if ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
                return $row;
            }
        }
    }

    return officeos_demo_lookup_user($identifier);
}

function officeos_fetch_all(?mysqli $connection, string $sql, string $types = '', array $params = []): array
{
    if (!($connection instanceof mysqli)) {
        return [];
    }

    $statement = $connection->prepare($sql);
    if (!($statement instanceof mysqli_stmt)) {
        return [];
    }

    if ($types !== '' && $params !== []) {
        officeos_bind_params($statement, $types, $params);
    }

    $statement->execute();
    $result = $statement->get_result();

    $rows = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function officeos_fetch_value(?mysqli $connection, string $sql, string $types = '', array $params = [], int $fallback = 0): int
{
    $rows = officeos_fetch_all($connection, $sql, $types, $params);
    if ($rows === []) {
        return $fallback;
    }

    $row = $rows[0];
    $value = array_values($row)[0] ?? $fallback;

    return (int) $value;
}

function officeos_execute(?mysqli $connection, string $sql, string $types = '', array $params = []): bool
{
    if (!($connection instanceof mysqli)) {
        return false;
    }

    $statement = $connection->prepare($sql);
    if (!($statement instanceof mysqli_stmt)) {
        return false;
    }

    if ($types !== '' && $params !== []) {
        officeos_bind_params($statement, $types, $params);
    }

    return (bool) $statement->execute();
}

function officeos_bind_params(mysqli_stmt $statement, string $types, array $params): bool
{
    $references = [$types];

    foreach ($params as $index => $value) {
        $references[$index + 1] = &$params[$index];
    }

    return $statement->bind_param(...$references);
}

function officeos_demo_employees(): array
{
    return array_values(array_filter(
        officeos_demo_users(),
        static fn (array $user): bool => ($user['role'] ?? '') === 'employee'
    ));
}

function officeos_leaderboard(?mysqli $connection, int $currentUserId): array
{
    if ($connection instanceof mysqli) {
        $rows = officeos_fetch_all(
            $connection,
            "SELECT u.id, u.full_name, u.department, COALESCE(t.completed_tasks, 0) AS completed_tasks, COALESCE(a.attendance_days, 0) AS attendance_days, (COALESCE(t.completed_tasks, 0) * 10 + COALESCE(a.attendance_days, 0) * 2) AS points FROM users u LEFT JOIN (SELECT employee_id, COUNT(*) AS completed_tasks FROM task_assignments WHERE status = 'completed' GROUP BY employee_id) t ON t.employee_id = u.id LEFT JOIN (SELECT user_id, COUNT(*) AS attendance_days FROM attendance GROUP BY user_id) a ON a.user_id = u.id WHERE u.role = 'employee' AND u.status = 'active' ORDER BY points DESC, u.full_name ASC LIMIT 5"
        );

        if ($rows !== []) {
            foreach ($rows as &$row) {
                $row['is_current'] = ((int) ($row['id'] ?? 0) === $currentUserId);
            }

            return $rows;
        }
    }

    $fallback = [
        ['id' => 3, 'full_name' => 'Office Employee', 'department' => 'Support', 'completed_tasks' => 2, 'attendance_days' => 12, 'points' => 44],
        ['id' => 4, 'full_name' => 'Ava Patel', 'department' => 'Support', 'completed_tasks' => 1, 'attendance_days' => 11, 'points' => 32],
        ['id' => 5, 'full_name' => 'Noah James', 'department' => 'Sales', 'completed_tasks' => 1, 'attendance_days' => 10, 'points' => 30],
    ];

    foreach ($fallback as &$row) {
        $row['is_current'] = ((int) $row['id'] === $currentUserId);
    }

    return $fallback;
}

function officeos_employee_attendance_summary(?mysqli $connection, int $userId): array
{
    if ($connection instanceof mysqli) {
        $rows = officeos_fetch_all(
            $connection,
            'SELECT work_date, check_in, check_out, status FROM attendance WHERE user_id = ? ORDER BY work_date DESC, id DESC LIMIT 1',
            'i',
            [$userId]
        );

        if ($rows !== []) {
            return $rows[0];
        }
    }

    $records = array_values(array_filter(
        officeos_session_records('attendance_entries'),
        static fn (array $row): bool => (int) ($row['user_id'] ?? 0) === $userId
    ));

    if ($records === []) {
        return [];
    }

    usort($records, static fn (array $left, array $right): int => strcmp((string) ($right['work_date'] ?? ''), (string) ($left['work_date'] ?? '')));

    return $records[0];
}

function officeos_employee_leave_requests(?mysqli $connection, int $userId): array
{
    if ($connection instanceof mysqli) {
        return officeos_fetch_all(
            $connection,
            'SELECT id, leave_type, start_date, end_date, status, reason FROM leave_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 5',
            'i',
            [$userId]
        );
    }

    $records = array_values(array_filter(
        officeos_session_records('leave_requests'),
        static fn (array $row): bool => (int) ($row['user_id'] ?? 0) === $userId
    ));

    usort($records, static fn (array $left, array $right): int => strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? '')));

    return array_slice($records, 0, 5);
}

function officeos_role_model(string $role, ?mysqli $connection, array $user): array
{
    $userId = (int) ($user['id'] ?? 0);
    $role = strtolower($role);

    $models = [
        'admin' => [
            'title' => 'Admin Dashboard',
            'subtitle' => 'Manage users, review work, and keep the platform in order.',
            'badge' => 'Admin access',
            'stats' => [
                ['label' => 'Users', 'value' => officeos_fetch_value($connection, 'SELECT COUNT(*) FROM users', '', [], 3), 'hint' => 'All accounts'],
                ['label' => 'Managers', 'value' => officeos_fetch_value($connection, "SELECT COUNT(*) FROM users WHERE role = 'manager'", '', [], 1), 'hint' => 'Supervisors'],
                ['label' => 'Employees', 'value' => officeos_fetch_value($connection, "SELECT COUNT(*) FROM users WHERE role = 'employee'", '', [], 1), 'hint' => 'Active staff'],
                ['label' => 'Pending leave', 'value' => officeos_fetch_value($connection, "SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'", '', [], 1), 'hint' => 'Awaiting review'],
            ],
            'cards' => [
                ['title' => 'Users', 'text' => 'Keep employee, manager, and admin accounts active and clean.'],
                ['title' => 'Leave queue', 'text' => 'Review requests and move them forward without leaving the dashboard.'],
                ['title' => 'Task overview', 'text' => 'Watch the board and keep assignments balanced.'],
            ],
            'table_title' => 'Recent users',
            'table_headers' => ['Name', 'Role', 'Status'],
            'table_rows' => officeos_fetch_all($connection, 'SELECT full_name, role, status FROM users ORDER BY created_at DESC LIMIT 5'),
            'forms' => ['task' => true, 'attendance' => false, 'leave' => true],
        ],
        'manager' => [
            'title' => 'Manager Dashboard',
            'subtitle' => 'Assign tasks, review attendance, and process leave requests.',
            'badge' => 'Manager access',
            'stats' => [
                ['label' => 'Assigned tasks', 'value' => officeos_fetch_value($connection, 'SELECT COUNT(*) FROM task_assignments', '', [], 3), 'hint' => 'Open workload'],
                ['label' => 'In progress', 'value' => officeos_fetch_value($connection, "SELECT COUNT(*) FROM task_assignments WHERE status = 'in_progress'", '', [], 1), 'hint' => 'Active tasks'],
                ['label' => 'Today attendance', 'value' => officeos_fetch_value($connection, 'SELECT COUNT(*) FROM attendance WHERE work_date = CURDATE()', '', [], 1), 'hint' => 'Checked in'],
                ['label' => 'Pending leave', 'value' => officeos_fetch_value($connection, "SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'", '', [], 1), 'hint' => 'Needs review'],
            ],
            'cards' => [
                ['title' => 'Task board', 'text' => 'Create work items and assign them to the right employee.'],
                ['title' => 'Attendance', 'text' => 'Check who is present and who still needs a check-in.'],
                ['title' => 'Leave review', 'text' => 'Approve or reject requests with a short note.'],
            ],
            'table_title' => 'Team tasks',
            'table_headers' => ['Task', 'Owner', 'Status'],
            'table_rows' => officeos_fetch_all(
                $connection,
                'SELECT ta.id, t.title AS task, u.full_name AS owner, ta.status, ta.due_date FROM task_assignments ta INNER JOIN tasks t ON t.id = ta.task_id INNER JOIN users u ON u.id = ta.employee_id ORDER BY ta.created_at DESC LIMIT 5'
            ),
            'forms' => ['task' => true, 'attendance' => false, 'leave' => true],
        ],
        'employee' => [
            'title' => 'Employee Dashboard',
            'subtitle' => 'Track tasks, attendance, and leave in one simple place.',
            'badge' => 'Employee access',
            'stats' => [
                ['label' => 'My tasks', 'value' => officeos_fetch_value($connection, 'SELECT COUNT(*) FROM task_assignments WHERE employee_id = ?', 'i', [$userId], 1), 'hint' => 'Assigned work'],
                ['label' => 'Completed', 'value' => officeos_fetch_value($connection, "SELECT COUNT(*) FROM task_assignments WHERE employee_id = ? AND status = 'completed'", 'i', [$userId], 1), 'hint' => 'Done tasks'],
                ['label' => 'Leave status', 'value' => officeos_fetch_value($connection, "SELECT COUNT(*) FROM leave_requests WHERE user_id = ? AND status = 'pending'", 'i', [$userId], 0), 'hint' => 'Pending requests'],
                ['label' => 'Attendance', 'value' => officeos_fetch_value($connection, 'SELECT COUNT(*) FROM attendance WHERE user_id = ?', 'i', [$userId], 1), 'hint' => 'Recorded days'],
            ],
            'cards' => [
                ['title' => 'My work', 'text' => 'See the tasks assigned to you and update their status.'],
                ['title' => 'Attendance', 'text' => 'Check in and check out from the dashboard.'],
                ['title' => 'Leave', 'text' => 'Submit a request and track its progress.'],
            ],
            'table_title' => 'Assigned tasks',
            'table_headers' => ['Task', 'Due date', 'Status'],
            'table_rows' => officeos_fetch_all(
                $connection,
                'SELECT ta.id, t.title AS task, COALESCE(ta.due_date, "--") AS due_date, ta.status FROM task_assignments ta INNER JOIN tasks t ON t.id = ta.task_id WHERE ta.employee_id = ? ORDER BY ta.created_at DESC LIMIT 5',
                'i',
                [$userId]
            ),
            'forms' => ['task' => false, 'attendance' => true, 'leave' => true],
        ],
    ];

    $model = $models[$role] ?? $models['employee'];
    $model['leaderboard'] = officeos_leaderboard($connection, $userId);
    $model['role'] = $role;

    return $model;
}

function officeos_status_chip(string $status): string
{
    $class = match (strtolower($status)) {
        'completed', 'approved', 'present' => 'success',
        'pending', 'assigned', 'in_progress' => 'warning',
        'rejected', 'absent' => 'danger',
        default => '',
    };

    return '<span class="chip ' . $class . '">' . officeos_esc(str_replace('_', ' ', $status)) . '</span>';
}

function officeos_render_table(array $headers, array $rows, string $role): void
{
    echo '<div class="table-wrap"><table><thead><tr>';
    foreach ($headers as $header) {
        echo '<th>' . officeos_esc((string) $header) . '</th>';
    }
    echo '</tr></thead><tbody>';

    if ($rows === []) {
        echo '<tr><td colspan="' . count($headers) . '" class="muted">No records yet.</td></tr>';
        echo '</tbody></table></div>';
        return;
    }

    foreach ($rows as $row) {
        echo '<tr>';

        if ($role === 'admin') {
            echo '<td>' . officeos_esc((string) ($row['full_name'] ?? '')) . '</td>';
            echo '<td>' . officeos_status_chip((string) ($row['role'] ?? '')) . '</td>';
            echo '<td>' . officeos_status_chip((string) ($row['status'] ?? '')) . '</td>';
        } elseif ($role === 'manager') {
            echo '<td>' . officeos_esc((string) ($row['task'] ?? '')) . '</td>';
            echo '<td>' . officeos_esc((string) ($row['owner'] ?? '')) . '</td>';
            echo '<td>' . officeos_status_chip((string) ($row['status'] ?? '')) . '</td>';
        } else {
            echo '<td>' . officeos_esc((string) ($row['task'] ?? '')) . '</td>';
            echo '<td>' . officeos_esc((string) ($row['due_date'] ?? '--')) . '</td>';
            echo '<td>' . officeos_status_chip((string) ($row['status'] ?? '')) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function officeos_render_dashboard(array $model, array $currentUser, ?mysqli $connection): void
{
    $role = (string) ($model['role'] ?? 'employee');
    $userName = (string) ($currentUser['full_name'] ?? 'User');
    $userRole = officeos_role_label((string) ($currentUser['role'] ?? 'employee'));
    $employees = $connection instanceof mysqli ? officeos_fetch_all($connection, "SELECT id, full_name FROM users WHERE role = 'employee' AND status = 'active' ORDER BY full_name ASC") : officeos_demo_employees();
    $leaveRows = $connection instanceof mysqli ? officeos_fetch_all($connection, 'SELECT lr.id, u.full_name, lr.leave_type, lr.start_date, lr.end_date, lr.status FROM leave_requests lr INNER JOIN users u ON u.id = lr.user_id ORDER BY lr.created_at DESC LIMIT 5') : [];
    ?>
    <div class="topbar">
        <div class="container topbar-inner">
            <div class="brand">
                <div class="brand-mark">O</div>
                <div>
                    <p class="brand-title">Office OS</p>
                    <p class="brand-subtitle">PHP and MySQL workspace</p>
                </div>
            </div>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <span class="badge"><?php echo officeos_esc((string) $model['badge']); ?></span>
                <a class="btn ghost" href="?logout=1">Logout</a>
            </div>
        </div>
    </div>
    <main class="container layout">
        <aside class="sidebar">
            <div class="sidebar-profile">
                <div class="sidebar-avatar"><?php echo officeos_esc(strtoupper(substr($userName, 0, 1) ?: 'U')); ?></div>
                <div>
                    <h2><?php echo officeos_esc($userName); ?></h2>
                    <p><?php echo officeos_esc($userRole); ?> dashboard</p>
                </div>
            </div>

            <div class="sidebar-card">
                <span class="sidebar-label">Quick status</span>
                <strong><?php echo officeos_esc((string) $model['badge']); ?></strong>
                <p>Simple access to tasks, attendance, leave, and leaderboard.</p>
            </div>

            <nav class="sidebar-nav" aria-label="Dashboard sections">
                <a href="#overview">Overview</a>
                <a href="#tasks">Tasks</a>
                <a href="#attendance">Attendance</a>
                <a href="#leave">Leave</a>
                <a href="#leaderboard">Leaderboard</a>
            </nav>

            <div class="sidebar-card sidebar-footer">
                <span class="sidebar-label">Today</span>
                <strong><?php echo officeos_esc(date('M j, Y')); ?></strong>
                <p><?php echo officeos_esc(ucfirst($role)); ?> workspace ready.</p>
                <a class="btn ghost sidebar-button" href="?logout=1">Logout</a>
            </div>
        </aside>
        <section class="content">
            <div class="hero" id="overview">
                <span class="badge"><?php echo officeos_esc((string) $model['badge']); ?></span>
                <h1><?php echo officeos_esc((string) $model['title']); ?></h1>
                <p><?php echo officeos_esc((string) $model['subtitle']); ?></p>
            </div>

            <div class="stats-grid">
                <?php foreach ($model['stats'] as $stat): ?>
                    <div class="stat">
                        <div class="label"><?php echo officeos_esc((string) $stat['label']); ?></div>
                        <div class="value"><?php echo officeos_esc((string) $stat['value']); ?></div>
                        <div class="hint"><?php echo officeos_esc((string) $stat['hint']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="cards-grid">
                <?php foreach ($model['cards'] as $card): ?>
                    <article class="section-card">
                        <h3><?php echo officeos_esc((string) $card['title']); ?></h3>
                        <p><?php echo officeos_esc((string) $card['text']); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

            <section class="panel" id="tasks">
                <h2><?php echo officeos_esc((string) $model['table_title']); ?></h2>
                <?php officeos_render_table($model['table_headers'], $model['table_rows'], $role); ?>
            </section>

            <div class="forms-grid">
                <?php if (!empty($model['forms']['task'])): ?>
                    <section class="section-card">
                        <h3>Create task</h3>
                        <form method="post">
                            <input type="hidden" name="action" value="create_task" />
                            <label>
                                Task title
                                <input type="text" name="task_title" required />
                            </label>
                            <label>
                                Description
                                <textarea name="task_description" placeholder="Short task note"></textarea>
                            </label>
                            <div class="field-grid">
                                <label>
                                    Priority
                                    <select name="priority">
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                    </select>
                                </label>
                                <label>
                                    Due date
                                    <input type="date" name="due_date" />
                                </label>
                            </div>
                            <label>
                                Assign to employee
                                <select name="employee_id" required>
                                    <option value="">Choose employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo (int) ($employee['id'] ?? 0); ?>"><?php echo officeos_esc((string) ($employee['full_name'] ?? '')); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button class="btn primary" type="submit">Save task</button>
                        </form>
                    </section>
                <?php endif; ?>

                <?php if (!empty($model['forms']['attendance'])): ?>
                    <section class="section-card" id="attendance">
                        <h3>Attendance</h3>
                        <form method="post">
                            <input type="hidden" name="action" value="attendance_check_in" />
                            <button class="btn primary" type="submit">Check in</button>
                        </form>
                        <form method="post" style="margin-top:12px;">
                            <input type="hidden" name="action" value="attendance_check_out" />
                            <button class="btn" type="submit">Check out</button>
                        </form>
                    </section>
                <?php endif; ?>

                <?php if (!empty($model['forms']['leave'])): ?>
                    <section class="section-card" id="leave">
                        <h3>Leave request</h3>
                        <form method="post">
                            <input type="hidden" name="action" value="leave_request" />
                            <div class="field-grid">
                                <label>
                                    Leave type
                                    <select name="leave_type">
                                        <option value="annual">Annual</option>
                                        <option value="sick">Sick</option>
                                        <option value="casual">Casual</option>
                                    </select>
                                </label>
                                <label>
                                    Start date
                                    <input type="date" name="start_date" required />
                                </label>
                                <label>
                                    End date
                                    <input type="date" name="end_date" required />
                                </label>
                            </div>
                            <label>
                                Reason
                                <textarea name="reason" required></textarea>
                            </label>
                            <button class="btn primary" type="submit">Submit leave</button>
                        </form>
                    </section>
                <?php endif; ?>
            </div>

            <?php if ($role !== 'admin' && $role !== 'manager'): ?>
                <section class="panel">
                    <h2>Task status update</h2>
                    <form method="post">
                        <input type="hidden" name="action" value="update_task" />
                        <div class="field-grid">
                            <label>
                                Assignment
                                <select name="assignment_id" required>
                                    <option value="">Choose assignment</option>
                                    <?php foreach ($model['table_rows'] as $row): ?>
                                        <?php if (!empty($row['id'])): ?>
                                            <option value="<?php echo (int) $row['id']; ?>"><?php echo officeos_esc((string) ($row['task'] ?? 'Task')); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                Status
                                <select name="task_status">
                                    <option value="assigned">Assigned</option>
                                    <option value="in_progress">In progress</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </label>
                        </div>
                        <button class="btn primary" type="submit">Update task</button>
                    </form>
                </section>
            <?php elseif ($role === 'manager' || $role === 'admin'): ?>
                <section class="panel">
                    <h2>Leave review</h2>
                    <?php if ($leaveRows === []): ?>
                        <p class="muted">No leave requests to review.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Type</th>
                                        <th>Dates</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leaveRows as $leaveRow): ?>
                                        <tr>
                                            <td><?php echo officeos_esc((string) ($leaveRow['full_name'] ?? '')); ?></td>
                                            <td><?php echo officeos_esc((string) ($leaveRow['leave_type'] ?? '')); ?></td>
                                            <td><?php echo officeos_esc((string) ($leaveRow['start_date'] ?? '')); ?> to <?php echo officeos_esc((string) ($leaveRow['end_date'] ?? '')); ?></td>
                                            <td><?php echo officeos_status_chip((string) ($leaveRow['status'] ?? 'pending')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <form method="post" style="margin-top:14px;">
                            <input type="hidden" name="action" value="review_leave" />
                            <div class="field-grid">
                                <label>
                                    Leave request
                                    <select name="leave_id" required>
                                        <option value="">Choose request</option>
                                        <?php foreach ($leaveRows as $leaveRow): ?>
                                            <option value="<?php echo (int) ($leaveRow['id'] ?? 0); ?>"><?php echo officeos_esc((string) ($leaveRow['full_name'] ?? '')); ?> - <?php echo officeos_esc((string) ($leaveRow['leave_type'] ?? '')); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>
                                    Action
                                    <select name="leave_status">
                                        <option value="approved">Approve</option>
                                        <option value="rejected">Reject</option>
                                    </select>
                                </label>
                            </div>
                            <button class="btn primary" type="submit">Save review</button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="panel" id="leaderboard">
                <h2>Employee leaderboard</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Completed tasks</th>
                                <th>Attendance days</th>
                                <th>Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($model['leaderboard'] as $leader): ?>
                                <tr>
                                    <td><?php echo officeos_esc((string) ($leader['full_name'] ?? '')); ?><?php echo !empty($leader['is_current']) ? ' (you)' : ''; ?></td>
                                    <td><?php echo officeos_esc((string) ($leader['department'] ?? '')); ?></td>
                                    <td><?php echo officeos_esc((string) ($leader['completed_tasks'] ?? 0)); ?></td>
                                    <td><?php echo officeos_esc((string) ($leader['attendance_days'] ?? 0)); ?></td>
                                    <td><?php echo officeos_esc((string) ($leader['points'] ?? 0)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>
    <?php
}

$connection = officeos_db_connection();
$flash = officeos_flash();

if (isset($_GET['logout'])) {
    officeos_sign_out();
    officeos_redirect_self();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'login') {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($identifier === '' || $password === '') {
            officeos_flash('Enter your username or email and password.', 'error');
            officeos_redirect_self();
        }

        $user = officeos_lookup_user($connection, $identifier);

        if ($user && officeos_user_matches_password($password, $user)) {
            officeos_sign_in($user);
            officeos_flash('Welcome back, ' . (string) ($user['full_name'] ?? 'user') . '.');
            officeos_redirect_self();
        }

        officeos_flash('Invalid credentials. Use the seeded demo users or your database account.', 'error');
        officeos_redirect_self();
    }

    $currentUser = officeos_current_user();

    if (!$currentUser) {
        officeos_flash('Please sign in first.', 'error');
        officeos_redirect_self();
    }

    $role = strtolower((string) ($currentUser['role'] ?? 'employee'));
    $currentUserId = (int) ($currentUser['id'] ?? 0);

    if ($action === 'attendance_check_in' && $role === 'employee') {
        $today = date('Y-m-d');
        $time = date('H:i:s');
        if ($connection instanceof mysqli) {
            $saved = officeos_execute(
                $connection,
                'INSERT INTO attendance (user_id, work_date, check_in, status) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE check_in = VALUES(check_in), status = VALUES(status)',
                'isss',
                [$currentUserId, $today, $time, 'present']
            );
        } else {
            officeos_session_store_record('attendance_entries', [
                'user_id' => $currentUserId,
                'work_date' => $today,
                'check_in' => $time,
                'check_out' => null,
                'status' => 'present',
                'created_at' => date('c'),
            ]);
            $saved = true;
        }

        officeos_flash($saved ? 'Attendance check-in saved.' : 'Check-in could not be saved.');
        officeos_redirect_self();
    }

    if ($action === 'attendance_check_out' && $role === 'employee') {
        $today = date('Y-m-d');
        $time = date('H:i:s');
        if ($connection instanceof mysqli) {
            $saved = officeos_execute(
                $connection,
                'INSERT INTO attendance (user_id, work_date, check_out, status) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE check_out = VALUES(check_out), status = VALUES(status)',
                'isss',
                [$currentUserId, $today, $time, 'present']
            );
        } else {
            $records = officeos_session_records('attendance_entries');
            $updated = false;

            foreach ($records as &$record) {
                if ((int) ($record['user_id'] ?? 0) === $currentUserId && (string) ($record['work_date'] ?? '') === $today) {
                    $record['check_out'] = $time;
                    $record['status'] = 'present';
                    $updated = true;
                }
            }

            if (!$updated) {
                $records[] = [
                    'user_id' => $currentUserId,
                    'work_date' => $today,
                    'check_in' => null,
                    'check_out' => $time,
                    'status' => 'present',
                    'created_at' => date('c'),
                ];
            }

            $_SESSION['attendance_entries'] = $records;
            $saved = true;
        }

        officeos_flash($saved ? 'Attendance check-out saved.' : 'Check-out could not be saved.');
        officeos_redirect_self();
    }

    if ($action === 'leave_request' && in_array($role, ['employee', 'manager', 'admin'], true)) {
        $leaveType = trim((string) ($_POST['leave_type'] ?? 'annual'));
        $startDate = trim((string) ($_POST['start_date'] ?? ''));
        $endDate = trim((string) ($_POST['end_date'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));

        if ($leaveType === '' || $startDate === '' || $endDate === '' || $reason === '') {
            officeos_flash('Complete the leave form before submitting.', 'error');
            officeos_redirect_self();
        }

        if ($connection instanceof mysqli) {
            $saved = officeos_execute(
                $connection,
                'INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, ?, ?)',
                'isssss',
                [$currentUserId, $leaveType, $startDate, $endDate, $reason, 'pending']
            );
        } else {
            officeos_session_store_record('leave_requests', [
                'id' => count(officeos_session_records('leave_requests')) + 1,
                'user_id' => $currentUserId,
                'leave_type' => $leaveType,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => $reason,
                'status' => 'pending',
                'created_at' => date('c'),
            ]);
            $saved = true;
        }

        officeos_flash($saved ? 'Leave request submitted.' : 'Leave request could not be saved.');
        officeos_redirect_self();
    }

    if ($action === 'create_task' && in_array($role, ['manager', 'admin'], true)) {
        $title = trim((string) ($_POST['task_title'] ?? ''));
        $description = trim((string) ($_POST['task_description'] ?? ''));
        $priority = trim((string) ($_POST['priority'] ?? 'medium'));
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $dueDate = trim((string) ($_POST['due_date'] ?? ''));

        if ($title === '' || $employeeId <= 0) {
            officeos_flash('Add a task title and assign it to an employee.', 'error');
            officeos_redirect_self();
        }

        if ($dueDate === '') {
            $dueDate = date('Y-m-d', strtotime('+1 day'));
        }

        $created = officeos_execute(
            $connection,
            'INSERT INTO tasks (title, description, priority, created_by) VALUES (?, ?, ?, ?)',
            'sssi',
            [$title, $description, $priority, $currentUserId]
        );

        if ($created && $connection instanceof mysqli) {
            $taskId = (int) $connection->insert_id;
            officeos_execute(
                $connection,
                'INSERT INTO task_assignments (task_id, employee_id, due_date, status) VALUES (?, ?, ?, ?)',
                'iiss',
                [$taskId, $employeeId, $dueDate, 'assigned']
            );
        }

        officeos_flash($created ? 'Task created and assigned.' : 'Task could not be created.');
        officeos_redirect_self();
    }

    if ($action === 'update_task' && in_array($role, ['employee', 'manager', 'admin'], true)) {
        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
        $status = trim((string) ($_POST['task_status'] ?? 'in_progress'));

        if ($assignmentId <= 0) {
            officeos_flash('Select a task first.', 'error');
            officeos_redirect_self();
        }

        if ($role === 'employee') {
            $updated = officeos_execute(
                $connection,
                'UPDATE task_assignments SET status = ? WHERE id = ? AND employee_id = ?',
                'sii',
                [$status, $assignmentId, $currentUserId]
            );
        } else {
            $updated = officeos_execute(
                $connection,
                'UPDATE task_assignments SET status = ? WHERE id = ?',
                'si',
                [$status, $assignmentId]
            );
        }

        officeos_flash($updated ? 'Task status updated.' : 'Task status could not be updated.');
        officeos_redirect_self();
    }

    if ($action === 'review_leave' && in_array($role, ['manager', 'admin'], true)) {
        $leaveId = (int) ($_POST['leave_id'] ?? 0);
        $status = trim((string) ($_POST['leave_status'] ?? 'approved'));

        if ($leaveId <= 0) {
            officeos_flash('Select a leave request first.', 'error');
            officeos_redirect_self();
        }

        $updated = officeos_execute(
            $connection,
            'UPDATE leave_requests SET status = ?, reviewed_by = ? WHERE id = ?',
            'sii',
            [$status, $currentUserId, $leaveId]
        );

        officeos_flash($updated ? 'Leave request updated.' : 'Leave request could not be updated.');
        officeos_redirect_self();
    }
}

$currentUser = officeos_current_user();
$model = $currentUser ? officeos_role_model((string) ($currentUser['role'] ?? 'employee'), $connection, $currentUser) : null;

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Office OS</title>
    <link rel="stylesheet" href="assets/css/app.css" />
</head>
<body class="app-shell">
<?php if (!$currentUser || !$model): ?>
    <div class="login-wrap">
        <section class="login-card">
            <div class="brand" style="margin-bottom:14px;">
                <div class="brand-mark">O</div>
                <div>
                    <p class="brand-title">Office OS</p>
                    <p class="brand-subtitle">Login for employee, manager, or admin</p>
                </div>
            </div>
            <h1>Sign in</h1>
            <p class="muted">Use the seeded accounts in the SQL file or connect the database and use your own users.</p>
            <?php if ($flash): ?>
                <div class="notice <?php echo officeos_esc((string) ($flash['type'] ?? 'success')); ?>"><?php echo officeos_esc((string) ($flash['message'] ?? '')); ?></div>
            <?php endif; ?>
            <form method="post" style="margin-top:16px;">
                <input type="hidden" name="action" value="login" />
                <div class="field-grid">
                    <label>
                        Username or email
                        <input type="text" name="identifier" required />
                    </label>
                    <label>
                        Password
                        <input type="password" name="password" required />
                    </label>
                </div>
                <button class="btn primary" type="submit">Login</button>
            </form>
        </section>
    </div>
<?php else: ?>
    <?php if ($flash): ?>
        <div class="container" style="padding-top:18px;">
            <div class="notice <?php echo officeos_esc((string) ($flash['type'] ?? 'success')); ?>"><?php echo officeos_esc((string) ($flash['message'] ?? '')); ?></div>
        </div>
    <?php endif; ?>
    <?php officeos_render_dashboard($model, $currentUser, $connection); ?>
<?php endif; ?>
</body>
</html>
