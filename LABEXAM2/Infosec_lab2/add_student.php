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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    $courses_stmt = $pdo->query("SELECT id, course_code, course_name FROM courses ORDER BY course_code ASC");
    $courses = $courses_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("add_student.php courses query error: " . $e->getMessage());
    $courses = [];
}

$errors = [];
$student_id = '';
$fullname = '';
$email = '';
$course_id = '';

if (isset($_POST['add'])) {

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid request.");
    }

    $student_id          = trim($_POST['student_id'] ?? '');
    $fullname            = trim($_POST['fullname'] ?? '');
    $email               = trim($_POST['email'] ?? '');
    $course_id           = $_POST['course_id'] ?? '';

    if (empty($student_id)) {
        $errors['student_id'] = "Student ID is required.";
    } elseif (strlen($student_id) > 20) {
        $errors['student_id'] = "Student ID must be 20 characters or fewer.";
    } elseif (!preg_match('/^[A-Z0-9\-]+$/i', $student_id)) {
        $errors['student_id'] = "Student ID must be alphanumeric (hyphens allowed).";
    }

    if (empty($fullname)) {
        $errors['fullname'] = "Full Name is required.";
    } elseif (strlen($fullname) > 100) {
        $errors['fullname'] = "Full Name must be 100 characters or fewer.";
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $fullname)) {
        $errors['fullname'] = "Full Name must contain only letters and spaces.";
    }

    if (empty($email)) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Please enter a valid email address.";
    }

    $course_id = filter_var($course_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($course_id === false) {
        $errors['course_id'] = "Please select a valid course.";
    } else {
        $check_course = $pdo->prepare("SELECT id FROM courses WHERE id = :id");
        $check_course->execute([':id' => $course_id]);
        if (!$check_course->fetch()) {
            $errors['course_id'] = "Selected course does not exist.";
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO students 
                                   (student_id, fullname, email, course_id, created_by) 
                                   VALUES (:student_id, :fullname, :email, :course_id, :created_by)");

            $stmt->execute([
                ':student_id'  => $student_id,
                ':fullname'    => $fullname,
                ':email'       => $email,
                ':course_id'   => $course_id,
                ':created_by'  => $_SESSION['user_id'],
            ]);

            $newStudentId = $pdo->lastInsertId();

            $audit = $pdo->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id) VALUES (:user_id, 'INSERT', 'students', :record_id)");
            $audit->execute([
                ':user_id'   => $_SESSION['user_id'],
                ':record_id' => $newStudentId,
            ]);

            $_SESSION['flash_success'] = "Student added successfully.";
            header("Location: dashboard.php");
            exit();

        } catch (PDOException $e) {
            error_log("add_student.php error: " . $e->getMessage());

            if ($e->getCode() == 23000) {
                if (stripos($e->getMessage(), 'student_id') !== false) {
                    $errors['student_id'] = "This Student ID already exists.";
                } elseif (stripos($e->getMessage(), 'email') !== false) {
                    $errors['email'] = "This email address is already registered.";
                } else {
                    $errors['database'] = "A student with this information already exists.";
                }
            } else {
                $errors['database'] = "An error occurred while adding the student. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Student</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="page-wrapper">
    <nav class="navbar">
        <span class="nav-brand">Student Management System</span>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="add_user.php">Manage Users</a>
            <a href="archive.php">Archive</a>
            <a href="audit_log.php">Audit Log</a>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>

    <div class="container" style="margin-top: 10px;">
        <h2>Add New Student</h2>

        <?php if (isset($errors['database'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($errors['database'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" 
                   value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <div class="form-group">
                <label for="student_id">Student ID</label>
                <input type="text" id="student_id" name="student_id" maxlength="20" placeholder="e.g. STU-001"
                       value="<?php echo htmlspecialchars($student_id, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (isset($errors['student_id'])): ?>
                    <div class="field-error"><?php echo htmlspecialchars($errors['student_id'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="fullname">Full Name</label>
                <input type="text" id="fullname" name="fullname" maxlength="100" placeholder="e.g. Juan Dela Cruz"
                       value="<?php echo htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (isset($errors['fullname'])): ?>
                    <div class="field-error"><?php echo htmlspecialchars($errors['fullname'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="e.g. juan@email.com"
                       value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (isset($errors['email'])): ?>
                    <div class="field-error"><?php echo htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="course_id">Course</label>
                <select id="course_id" name="course_id">
                    <option value="">-- Select Course --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>"
                            <?php echo ($course_id == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['course_id'])): ?>
                    <div class="field-error"><?php echo htmlspecialchars($errors['course_id'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group" style="margin-top: 22px;">
                <button name="add" class="btn btn-primary">Add Student</button>
            </div>
        </form>

        <div style="text-align: center; margin-top: 14px;">
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
        </div>
    </div>
</div>

</body>
</html>
