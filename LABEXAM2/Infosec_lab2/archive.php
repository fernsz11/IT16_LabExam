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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$flash_success = '';
$flash_error = '';
if (isset($_SESSION['flash_success'])) {
    $flash_success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $flash_error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_id, s.fullname, s.email, 
               c.course_code AS course,
               u.username AS deleted_by_user,
               s.updated_at AS deleted_at
        FROM students s 
        LEFT JOIN courses c ON s.course_id = c.id 
        LEFT JOIN users u ON s.deleted_by = u.id
        WHERE s.is_deleted = 1 
        ORDER BY s.updated_at DESC
    ");
    $stmt->execute();
    $archived = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("archive.php query error: " . $e->getMessage());
    $archived = [];
    $flash_error = "Unable to load archived records.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Archive</title>
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
            <a href="audit_log.php">Audit Log</a>
            <a href="logout.php" class="btn-logout">Logout (<?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?>)</a>
        </div>
    </nav>

    <?php if (!empty($flash_success)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!empty($flash_error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="card">
        <h3>Archived Students</h3>

        <?php if (empty($archived)): ?>
            <div class="empty-state">
                <p>No archived students found.</p>
                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        <?php else: ?>
            <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Course</th>
                    <th>Deleted By</th>
                    <th>Deleted On</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($archived as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($row['student_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><strong><?php echo htmlspecialchars($row['fullname'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><span class="badge badge-course"><?php echo htmlspecialchars($row['course'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td>
                        <?php if (!empty($row['deleted_by_user'])): ?>
                            <span class="badge badge-user"><?php echo htmlspecialchars($row['deleted_by_user'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php else: ?>
                            <span class="meta-text">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="meta-text"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($row['deleted_at'])), ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td>
                        <form method="POST" action="restore_student.php" style="display:inline;"
                              onsubmit="return confirm('Restore student <?php echo htmlspecialchars($row['fullname'], ENT_QUOTES, 'UTF-8'); ?>?');">
                            <input type="hidden" name="csrf_token"
                                   value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="id"
                                   value="<?php echo (int)$row['id']; ?>">
                            <button type="submit" class="btn btn-success" style="padding: 6px 14px; font-size: 13px;">Restore</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
