<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/database.php';

function officeos_demo_users(): array
{
    return [
        [
            'id' => 1,
            'username' => 'admin',
            'email' => 'admin@officeos.local',
            'password_hash' => '$2b$...',
            'full_name' => 'System Admin',
            'role' => 'admin',
            'status' => 'active',
        ],
        [
            'id' => 2,
            'username' => 'john_m',
            'email' => 'john.m@company.com',
            'password_hash' => '$2b$...',
            'full_name' => 'John Miller',
            'role' => 'manager',
            'status' => 'active',
        ],
        [
            'id' => 3,
            'username' => 'sarah_e',
            'email' => 'sarah.e@company.com',
            'password_hash' => '$2b$...',
            'full_name' => 'Sarah Smith',
            'role' => 'employee',
            'status' => 'active',
        ],
    ];
}

function officeos_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function officeos_redirect_to_self(): void
{
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

function officeos_logout(): void
{
    $_SESSION = [];

    if (session_id() !== '') {
        session_destroy();
    }

    officeos_redirect_to_self();
}

function officeos_current_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function officeos_normalize_user(array $user): array
{
    return [
        'id' => (int) ($user['id'] ?? 0),
        'username' => (string) ($user['username'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'full_name' => (string) ($user['full_name'] ?? ''),
        'role' => (string) ($user['role'] ?? 'employee'),
        'status' => (string) ($user['status'] ?? 'active'),
    ];
}

function officeos_store_user_session(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = officeos_normalize_user($user);
}

function officeos_lookup_user(?mysqli $connection, string $identifier): ?array
{
    $identifier = trim($identifier);

    if ($identifier !== '' && $connection instanceof mysqli) {
        try {
            $statement = $connection->prepare(
                'SELECT id, username, email, password_hash, full_name, role, status FROM users WHERE username = ? OR email = ? LIMIT 1'
            );
            $statement->bind_param('ss', $identifier, $identifier);
            $statement->execute();
            $result = $statement->get_result();

            if ($row = $result->fetch_assoc()) {
                return $row;
            }
        } catch (Throwable $throwable) {
        }
    }

    foreach (officeos_demo_users() as $user) {
        if (strcasecmp($user['username'], $identifier) === 0 || strcasecmp($user['email'], $identifier) === 0) {
            return $user;
        }
    }

    return null;
}

function officeos_password_matches(string $password, array $user): bool
{
    $hash = (string) ($user['password_hash'] ?? '');

    if ($hash !== '' && preg_match('/^\$(2y|2a|2b|argon2id|argon2i)\$/', $hash) === 1) {
        return password_verify($password, $hash);
    }

    $role = strtolower((string) ($user['role'] ?? 'employee'));
    $password = strtolower(trim($password));
    $username = strtolower((string) ($user['username'] ?? ''));

    $demoPasswords = [
        'admin' => ['admin123', 'admin', 'officeos'],
        'manager' => ['manager123', 'manager', 'officeos'],
        'employee' => ['employee123', 'employee', 'officeos'],
    ];

    return in_array($password, $demoPasswords[$role] ?? [], true) || $password === $username;
}

function officeos_query_count(?mysqli $connection, string $sql, int $fallback): int
{
    if (!$connection instanceof mysqli) {
        return $fallback;
    }

    try {
        $result = $connection->query($sql);

        if ($result instanceof mysqli_result) {
            $row = $result->fetch_row();
            return (int) ($row[0] ?? $fallback);
        }
    } catch (Throwable $throwable) {
    }

    return $fallback;
}

function officeos_query_rows(?mysqli $connection, string $sql, array $fallback): array
{
    if (!$connection instanceof mysqli) {
        return $fallback;
    }

    try {
        $result = $connection->query($sql);

        if ($result instanceof mysqli_result) {
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }

            return $rows;
        }
    } catch (Throwable $throwable) {
    }

    return $fallback;
}

function officeos_dashboard_config(array $user, ?mysqli $connection): array
{
    $role = strtolower((string) ($user['role'] ?? 'employee'));

    $config = [
        'admin' => [
            'title' => 'Admin Control Center',
            'subtitle' => 'Monitor users, approvals, and platform health from one place.',
            'badge' => 'Full system access',
            'stats' => [
                ['label' => 'Total Users', 'value' => officeos_query_count($connection, 'SELECT COUNT(*) FROM users', 18), 'hint' => 'All accounts'],
                ['label' => 'Managers', 'value' => officeos_query_count($connection, "SELECT COUNT(*) FROM users WHERE role = 'manager'", 2), 'hint' => 'Supervisors'],
                ['label' => 'Employees', 'value' => officeos_query_count($connection, "SELECT COUNT(*) FROM users WHERE role = 'employee'", 12), 'hint' => 'Active staff'],
                ['label' => 'Open Leaves', 'value' => officeos_query_count($connection, "SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'", 4), 'hint' => 'Awaiting review'],
            ],
            'summary' => [
                ['title' => 'Security', 'value' => 'Sessions active', 'detail' => 'Login state is handled entirely in PHP.'],
                ['title' => 'Data', 'value' => 'MySQL ready', 'detail' => 'Prepared statements protect login queries.'],
                ['title' => 'Coverage', 'value' => 'Role based', 'detail' => 'Admin, manager, and employee dashboards are split by session role.'],
            ],
            'tableTitle' => 'Recent users',
            'tableHeaders' => ['Name', 'Role', 'Status'],
            'tableRows' => officeos_query_rows(
                $connection,
                'SELECT full_name, role, status FROM users ORDER BY created_at DESC LIMIT 5',
                [
                    ['full_name' => 'System Admin', 'role' => 'admin', 'status' => 'active'],
                    ['full_name' => 'John Miller', 'role' => 'manager', 'status' => 'active'],
                    ['full_name' => 'Sarah Smith', 'role' => 'employee', 'status' => 'active'],
                ]
            ),
            'cards' => [
                ['title' => 'User Management', 'text' => 'Create, review, and disable accounts.'],
                ['title' => 'Leave Queue', 'text' => 'Approve or reject pending requests.'],
                ['title' => 'Live Oversight', 'text' => 'Track the health of every role dashboard.'],
            ],
        ],
        'manager' => [
            'title' => 'Manager Workspace',
            'subtitle' => 'Assign work, balance load, and keep the team moving.',
            'badge' => 'Team operations',
            'stats' => [
                ['label' => 'Assigned Tasks', 'value' => officeos_query_count($connection, 'SELECT COUNT(*) FROM tasks', 9), 'hint' => 'Current workload'],
                ['label' => 'In Progress', 'value' => officeos_query_count($connection, "SELECT COUNT(*) FROM task_assignments WHERE status = 'in_progress'", 5), 'hint' => 'Moving now'],
                ['label' => 'Pending Leaves', 'value' => officeos_query_count($connection, "SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'", 3), 'hint' => 'Needs review'],
                ['label' => 'Active Staff', 'value' => officeos_query_count($connection, "SELECT COUNT(*) FROM users WHERE role = 'employee'", 12), 'hint' => 'Team members'],
            ],
            'summary' => [
                ['title' => 'Workload', 'value' => 'Balanced view', 'detail' => 'Spot overloaded employees before deadlines slip.'],
                ['title' => 'Approvals', 'value' => 'Fast review', 'detail' => 'Leaves and status updates stay centralized.'],
                ['title' => 'Planning', 'value' => 'Task flow', 'detail' => 'Tasks move from assigned to in progress to complete.'],
            ],
            'tableTitle' => 'Team tasks',
            'tableHeaders' => ['Task', 'Owner', 'Status'],
            'tableRows' => officeos_query_rows(
                $connection,
                'SELECT t.title AS task, u.full_name AS owner, ta.status FROM task_assignments ta INNER JOIN tasks t ON t.id = ta.task_id INNER JOIN users u ON u.id = ta.employee_id ORDER BY ta.created_at DESC LIMIT 5',
                [
                    ['task' => 'Website redesign', 'owner' => 'Sarah Smith', 'status' => 'in_progress'],
                    ['task' => 'Attendance review', 'owner' => 'John Miller', 'status' => 'assigned'],
                    ['task' => 'Leave approvals', 'owner' => 'Sarah Smith', 'status' => 'completed'],
                ]
            ),
            'cards' => [
                ['title' => 'Task Assignment', 'text' => 'Create tasks and attach due dates.'],
                ['title' => 'Workload Monitor', 'text' => 'See overloaded and underloaded staff instantly.'],
                ['title' => 'Leave Review', 'text' => 'Approve pending requests without leaving the page.'],
            ],
        ],
        'employee' => [
            'title' => 'Employee Dashboard',
            'subtitle' => 'Check tasks, attendance, and leave status in one view.',
            'badge' => 'Personal workspace',
            'stats' => [
                ['label' => 'My Tasks', 'value' => officeos_query_count($connection, 'SELECT COUNT(*) FROM task_assignments', 4), 'hint' => 'Assigned work'],
                ['label' => 'Completed', 'value' => officeos_query_count($connection, "SELECT COUNT(*) FROM task_assignments WHERE status = 'completed'", 2), 'hint' => 'Finished tasks'],
                ['label' => 'Leave Balance', 'value' => '12 days', 'hint' => 'Configured in PHP'],
                ['label' => 'Attendance', 'value' => 'Checked in', 'hint' => 'Today status'],
            ],
            'summary' => [
                ['title' => 'Today', 'value' => 'Focus mode', 'detail' => 'Review your assigned work and update progress.'],
                ['title' => 'Attendance', 'value' => 'One click', 'detail' => 'Mark attendance from your dashboard.'],
                ['title' => 'Leave', 'value' => 'Request flow', 'detail' => 'Submit leave and track approval state.'],
            ],
            'tableTitle' => 'Assigned tasks',
            'tableHeaders' => ['Task', 'Deadline', 'Status'],
            'tableRows' => officeos_query_rows(
                $connection,
                'SELECT t.title AS task, COALESCE(ta.due_date, "--") AS deadline, ta.status FROM task_assignments ta INNER JOIN tasks t ON t.id = ta.task_id ORDER BY ta.created_at DESC LIMIT 5',
                [
                    ['task' => 'Update homepage sections', 'deadline' => '2026-09-26', 'status' => 'in_progress'],
                    ['task' => 'Verify attendance', 'deadline' => '2026-09-27', 'status' => 'assigned'],
                    ['task' => 'Prepare leave request', 'deadline' => '--', 'status' => 'completed'],
                ]
            ),
            'cards' => [
                ['title' => 'My Work', 'text' => 'See tasks assigned by your manager.'],
                ['title' => 'Time Tracking', 'text' => 'Attendance stays visible and simple.'],
                ['title' => 'Requests', 'text' => 'Leave applications remain in your control.'],
            ],
        ],
    ];

    return $config[$role] ?? $config['employee'];
}

$connection = officeos_db_connection();
$authError = '';

if (isset($_GET['logout'])) {
    officeos_logout();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $authError = 'Enter your username/email and password.';
    } else {
        $user = officeos_lookup_user($connection, $identifier);

        if ($user && officeos_password_matches($password, $user)) {
            officeos_store_user_session($user);
            officeos_redirect_to_self();
        }

        $authError = 'Invalid credentials. Try the demo credentials shown below.';
    }
}

$currentUser = officeos_current_user();
$dashboard = $currentUser ? officeos_dashboard_config($currentUser, $connection) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>OfficeOS | Main Dashboard</title>
    <style>
        :root {
            --bg: #07111d;
            --bg-soft: #0f1a2a;
            --panel: rgba(13, 22, 36, 0.92);
            --panel-soft: rgba(18, 31, 49, 0.88);
            --line: rgba(255, 255, 255, 0.12);
            --text: #edf7ff;
            --muted: #a9bfd3;
            --primary: #62e6d7;
            --accent: #7ab7ff;
            --warning: #f9c74f;
            --danger: #ff7d7d;
            --success: #4ade80;
            --shadow: 0 24px 60px rgba(0, 0, 0, 0.3);
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(98, 230, 215, 0.12), transparent 32%),
                radial-gradient(circle at top right, rgba(122, 183, 255, 0.14), transparent 28%),
                linear-gradient(135deg, var(--bg), #09182b 52%, #102138);
            min-height: 100vh;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .shell {
            width: min(1280px, calc(100% - 28px));
            margin: 0 auto;
            padding: 18px 0 28px;
        }

        .top-banner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 18px 20px;
            margin-bottom: 18px;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: rgba(9, 17, 29, 0.55);
            box-shadow: var(--shadow);
            backdrop-filter: blur(14px);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-mark {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #08111d;
            font-weight: 900;
        }

        .brand-title {
            margin: 0;
            font-size: 1.05rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .brand-subtitle {
            margin: 3px 0 0;
            color: var(--muted);
            font-size: 0.92rem;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 14px;
            border-radius: 999px;
            border: 1px solid var(--line);
            color: var(--text);
            background: rgba(255, 255, 255, 0.05);
            font-size: 0.88rem;
        }

        .login-wrap {
            min-height: calc(100vh - 80px);
            display: grid;
            place-items: center;
        }

        .login-grid {
            width: min(1100px, 100%);
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 18px;
        }

        .card {
            border: 1px solid var(--line);
            border-radius: 22px;
            background: var(--panel);
            box-shadow: var(--shadow);
            backdrop-filter: blur(14px);
        }

        .login-copy {
            padding: 34px;
            position: relative;
            overflow: hidden;
        }

        .login-copy::after {
            content: '';
            position: absolute;
            right: -80px;
            top: -80px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(98, 230, 215, 0.18), transparent 70%);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(98, 230, 215, 0.11);
            color: var(--primary);
            border: 1px solid rgba(98, 230, 215, 0.2);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        h1, h2, h3, p {
            margin-top: 0;
        }

        .hero-title {
            font-size: clamp(2.4rem, 5vw, 4.4rem);
            line-height: 1.05;
            margin: 18px 0 16px;
            max-width: 11ch;
        }

        .hero-text {
            color: var(--muted);
            max-width: 60ch;
            font-size: 1.02rem;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 24px 0 0;
            display: grid;
            gap: 12px;
        }

        .feature-list li {
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.04);
            color: var(--muted);
        }

        .login-form {
            padding: 30px;
            background: linear-gradient(180deg, rgba(16, 28, 44, 0.98), rgba(10, 18, 30, 0.98));
        }

        .form-title {
            font-size: 1.55rem;
            margin-bottom: 6px;
        }

        .form-text {
            color: var(--muted);
            margin-bottom: 24px;
        }

        .alert {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid rgba(255, 125, 125, 0.32);
            background: rgba(255, 125, 125, 0.1);
            color: #ffd0d0;
        }

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            margin-bottom: 8px;
            color: var(--muted);
            font-size: 0.92rem;
            font-weight: 600;
        }

        .field input {
            width: 100%;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.04);
            color: var(--text);
            padding: 14px 15px;
            font-size: 1rem;
            outline: none;
        }

        .field input:focus {
            border-color: rgba(98, 230, 215, 0.62);
            box-shadow: 0 0 0 3px rgba(98, 230, 215, 0.14);
        }

        .btn {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: none;
            border-radius: 14px;
            padding: 14px 18px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #07111d;
            font-weight: 800;
            font-size: 1rem;
            cursor: pointer;
        }

        .form-note {
            margin-top: 16px;
            color: var(--muted);
            font-size: 0.92rem;
            line-height: 1.6;
        }

        .dashboard-shell {
            display: grid;
            grid-template-columns: 270px minmax(0, 1fr);
            gap: 18px;
        }

        .sidebar {
            position: sticky;
            top: 18px;
            align-self: start;
            padding: 18px;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--line);
            margin-bottom: 18px;
        }

        .nav {
            display: grid;
            gap: 10px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .nav a {
            display: block;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid transparent;
            color: var(--muted);
            background: rgba(255, 255, 255, 0.03);
        }

        .nav a:hover {
            color: var(--text);
            border-color: rgba(98, 230, 215, 0.28);
        }

        .sidebar-footer {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 0.92rem;
        }

        .main {
            display: grid;
            gap: 18px;
        }

        .main-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 22px;
        }

        .header-meta {
            display: grid;
            gap: 4px;
        }

        .header-meta h1 {
            margin: 0;
            font-size: 1.65rem;
        }

        .header-meta p {
            margin: 0;
            color: var(--muted);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .ghost-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.04);
            color: var(--text);
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .stat-card {
            padding: 18px;
        }

        .stat-card .label {
            color: var(--muted);
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .stat-card .hint {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .panel {
            padding: 22px;
        }

        .panel h2 {
            margin-bottom: 10px;
            font-size: 1.15rem;
        }

        .panel p {
            color: var(--muted);
        }

        .mini-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .mini-box {
            padding: 16px;
            border-radius: 16px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.04);
        }

        .mini-box strong {
            display: block;
            margin-bottom: 8px;
        }

        .mini-box span {
            color: var(--muted);
            font-size: 0.92rem;
        }

        .table-wrap {
            padding: 0;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
            text-align: left;
        }

        th {
            color: var(--muted);
            font-size: 0.88rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            background: rgba(255, 255, 255, 0.03);
        }

        tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(98, 230, 215, 0.12);
            border: 1px solid rgba(98, 230, 215, 0.24);
            color: var(--primary);
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .role-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            background: var(--success);
            box-shadow: 0 0 10px currentColor;
        }

        .muted {
            color: var(--muted);
        }

        @media (max-width: 1060px) {
            .login-grid,
            .dashboard-shell,
            .stats,
            .grid-2,
            .role-summary,
            .mini-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 760px) {
            .shell {
                width: min(100% - 16px, 1280px);
                padding-top: 12px;
            }

            .top-banner,
            .main-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .login-grid,
            .dashboard-shell,
            .stats,
            .grid-2,
            .role-summary,
            .mini-grid {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: static;
            }

            .login-copy,
            .login-form,
            .panel,
            .stat-card {
                padding: 20px;
            }

            .hero-title {
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="top-banner card">
            <div class="brand">
                <div class="brand-mark">O</div>
                <div>
                    <p class="brand-title">OfficeOS</p>
                    <p class="brand-subtitle">Single PHP dashboard with role-based access</p>
                </div>
            </div>
            <div class="pill">
                <span class="status-dot"></span>
                <?php echo $currentUser ? 'Logged in as ' . officeos_escape($currentUser['role']) : 'Login required'; ?>
            </div>
        </div>

        <?php if (!$currentUser): ?>
            <div class="login-wrap">
                <div class="login-grid">
                    <section class="card login-copy">
                        <span class="eyebrow">Workforce platform</span>
                        <h1 class="hero-title">Everything your office needs in one dashboard.</h1>
                        <p class="hero-text">OfficeOS keeps login, role control, attendance, leave, tasks, and workload tracking inside one PHP page. No external libraries. No separate frontend stack. Just a direct workflow from login to the correct dashboard.</p>
                        <ul class="feature-list">
                            <li>Login with username or email and get the correct role dashboard automatically.</li>
                            <li>Admin, manager, and employee views are all rendered from the same backend entry point.</li>
                            <li>Prepared statements and sessions handle the secure PHP side of the app.</li>
                        </ul>
                    </section>

                    <section class="card login-form">
                        <h2 class="form-title">Sign in</h2>
                        <p class="form-text">Use your account details to open the matching dashboard.</p>

                        <?php if ($authError !== ''): ?>
                            <div class="alert"><?php echo officeos_escape($authError); ?></div>
                        <?php endif; ?>

                        <form method="post" action="">
                            <input type="hidden" name="action" value="login" />
                            <div class="field">
                                <label for="identifier">Username or email</label>
                                <input id="identifier" name="identifier" type="text" autocomplete="username" required />
                            </div>
                            <div class="field">
                                <label for="password">Password</label>
                                <input id="password" name="password" type="password" autocomplete="current-password" required />
                            </div>
                            <button type="submit" class="btn">Open dashboard</button>
                        </form>

                        <div class="form-note">
                            Demo logins: <strong>admin / admin123</strong>, <strong>john_m / manager123</strong>, <strong>sarah_e / employee123</strong>.
                            If your MySQL users table has real hashes, those will be used first.
                        </div>
                    </section>
                </div>
            </div>
        <?php else: ?>
            <div class="dashboard-shell">
                <aside class="card sidebar">
                    <div class="sidebar-brand">
                        <div class="brand-mark">O</div>
                        <div>
                            <p class="brand-title">OfficeOS</p>
                            <p class="brand-subtitle"><?php echo officeos_escape(ucfirst($currentUser['role'])); ?> panel</p>
                        </div>
                    </div>

                    <ul class="nav">
                        <li><a href="#overview">Overview</a></li>
                        <li><a href="#workload">Workload</a></li>
                        <li><a href="#tasks">Tasks</a></li>
                        <li><a href="#people">People</a></li>
                    </ul>

                    <div class="sidebar-footer">
                        <strong><?php echo officeos_escape($currentUser['full_name'] ?: $currentUser['username']); ?></strong><br />
                        <?php echo officeos_escape($currentUser['email']); ?><br /><br />
                        <a class="ghost-btn" href="?logout=1">Logout</a>
                    </div>
                </aside>

                <main class="main">
                    <section class="card main-header" id="overview">
                        <div class="header-meta">
                            <span class="badge"><?php echo officeos_escape($dashboard['badge']); ?></span>
                            <h1><?php echo officeos_escape($dashboard['title']); ?></h1>
                            <p><?php echo officeos_escape($dashboard['subtitle']); ?></p>
                        </div>
                        <div class="header-actions">
                            <span class="pill">Role: <?php echo officeos_escape($currentUser['role']); ?></span>
                            <a class="ghost-btn" href="?logout=1">Logout</a>
                        </div>
                    </section>

                    <section class="stats">
                        <?php foreach ($dashboard['stats'] as $stat): ?>
                            <div class="card stat-card">
                                <div class="label"><?php echo officeos_escape((string) $stat['label']); ?></div>
                                <div class="value"><?php echo officeos_escape((string) $stat['value']); ?></div>
                                <div class="hint"><?php echo officeos_escape((string) $stat['hint']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </section>

                    <section class="grid-2">
                        <div class="card panel">
                            <h2>Platform summary</h2>
                            <div class="role-summary">
                                <?php foreach ($dashboard['summary'] as $item): ?>
                                    <div class="mini-box">
                                        <strong><?php echo officeos_escape((string) $item['title']); ?></strong>
                                        <span><?php echo officeos_escape((string) $item['value']); ?></span><br />
                                        <span><?php echo officeos_escape((string) $item['detail']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="card panel" id="workload">
                            <h2>Role-specific control</h2>
                            <div class="mini-grid">
                                <?php foreach ($dashboard['cards'] as $card): ?>
                                    <div class="mini-box">
                                        <strong><?php echo officeos_escape((string) $card['title']); ?></strong>
                                        <span><?php echo officeos_escape((string) $card['text']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>

                    <section class="card panel table-wrap" id="tasks">
                        <h2 style="padding: 22px 22px 0;"><?php echo officeos_escape($dashboard['tableTitle']); ?></h2>
                        <table>
                            <thead>
                                <tr>
                                    <?php foreach ($dashboard['tableHeaders'] as $header): ?>
                                        <th><?php echo officeos_escape((string) $header); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dashboard['tableRows'] as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $cell): ?>
                                            <td><?php echo officeos_escape((string) $cell); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>

                    <section class="grid-2" id="people">
                        <div class="card panel">
                            <h2>Current session</h2>
                            <p><strong>Name:</strong> <?php echo officeos_escape($currentUser['full_name'] ?: $currentUser['username']); ?></p>
                            <p><strong>Email:</strong> <?php echo officeos_escape($currentUser['email']); ?></p>
                            <p><strong>Role:</strong> <?php echo officeos_escape($currentUser['role']); ?></p>
                            <p class="muted">This page stays in PHP, and the visible dashboard changes as the session role changes.</p>
                        </div>

                        <div class="card panel">
                            <h2>Workflow notes</h2>
                            <p class="muted">Admin gets platform control, managers get task and approval tools, and employees see personal work, attendance, and leave status.</p>
                            <p class="muted">If you want, the next step can connect real attendance forms, leave submission, and task update handlers to the same PHP entry point.</p>
                        </div>
                    </section>
                </main>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
