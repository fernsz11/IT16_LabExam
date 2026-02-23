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
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'");

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
               u.username AS created_by_user,
               s.created_at
        FROM students s 
        LEFT JOIN courses c ON s.course_id = c.id 
        LEFT JOIN users u ON s.created_by = u.id
        WHERE s.is_deleted = 0 
        ORDER BY s.id ASC
    ");
    $stmt->execute();
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("dashboard.php query error: " . $e->getMessage());
    $students = [];
    $flash_error = "Unable to load student records.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="page-wrapper">
    <nav class="navbar">
        <span class="nav-brand">Student Management System</span>
        <div class="nav-links">
            <a href="add_student.php">+ Add Student</a>
            <a href="add_user.php">Manage Users</a>
            <a href="archive.php">Archive</a>
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
        <h3>Student Records</h3>

        <?php if (empty($students)): ?>
            <div class="empty-state">
                <p>No students found.</p>
                <a href="add_student.php" class="btn btn-success">Add Your First Student</a>
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
                    <th>Added By</th>
                    <th>Date Added</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($students as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($row['student_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><strong><?php echo htmlspecialchars($row['fullname'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><span class="badge badge-course"><?php echo htmlspecialchars($row['course'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td>
                        <?php if (!empty($row['created_by_user'])): ?>
                            <span class="badge badge-user"><?php echo htmlspecialchars($row['created_by_user'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php else: ?>
                            <span class="meta-text">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="meta-text"><?php echo htmlspecialchars(date('M d, Y', strtotime($row['created_at'])), ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td>
                        <form method="POST" action="delete_student.php" style="display:inline;"
                              onsubmit="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($row['fullname'], ENT_QUOTES, 'UTF-8'); ?>? This student will be moved to the archive.');">
                            <input type="hidden" name="csrf_token"
                                   value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="id"
                                   value="<?php echo (int)$row['id']; ?>">
                            <button type="submit" class="btn btn-danger">Delete</button>
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
