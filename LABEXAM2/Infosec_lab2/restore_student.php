<?php

session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: archive.php");
    exit();
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die("Invalid CSRF token.");
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    header("Location: archive.php");
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE students SET is_deleted = 0 WHERE id = :id");
    $stmt->execute([':id' => $id]);

    $audit = $pdo->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id) VALUES (:user_id, 'UPDATE', 'students', :record_id)");
    $audit->execute([
        ':user_id'   => $_SESSION['user_id'],
        ':record_id' => $id,
    ]);

    $_SESSION['flash_success'] = "Student restored successfully.";
} catch (PDOException $e) {
    error_log("restore_student.php error: " . $e->getMessage());
    $_SESSION['flash_error'] = "An error occurred while restoring the student.";
}

header("Location: archive.php");
exit();
?>
