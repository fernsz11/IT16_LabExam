<?php

session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: default-src 'self'");

try {
    $stmt = $pdo->prepare("
        SELECT a.id, a.action, a.table_name, a.record_id, a.performed_at,
               u.username
        FROM audit_log a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.performed_at DESC
    ");
    $stmt->execute();
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("audit_log.php query error: " . $e->getMessage());
    $logs = [];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Audit Log</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="page-wrapper">
    <nav class="navbar">
        <span class="nav-brand">Student Management System</span>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="add_student.php">+ Add Student</a>
            <a href="add_user.php">Manage Users</a>
            <a href="archive.php">Archive</a>
            <a href="logout.php" class="btn-logout">Logout (<?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?>)</a>
        </div>
    </nav>

    <div class="card">
        <h3>Audit Log</h3>

        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <p>No audit records found.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Action</th>
                    <th>Table</th>
                    <th>Record ID</th>
                    <th>Performed By</th>
                    <th>Date & Time</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo htmlspecialchars($log['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <?php
                            $actionClass = 'badge-course';
                            if ($log['action'] === 'DELETE') $actionClass = 'badge-danger';
                            elseif ($log['action'] === 'UPDATE') $actionClass = 'badge-user';
                        ?>
                        <span class="badge <?php echo $actionClass; ?>"><?php echo htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($log['table_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($log['record_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <?php if (!empty($log['username'])): ?>
                            <span class="badge badge-user"><?php echo htmlspecialchars($log['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php else: ?>
                            <span class="meta-text">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="meta-text"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($log['performed_at'])), ENT_QUOTES, 'UTF-8'); ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
