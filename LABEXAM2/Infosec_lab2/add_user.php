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

$errors = [];
$username = '';
$success = '';

if (isset($_POST['add_user'])) {

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid request.");
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($username)) {
        $errors['username'] = "Username is required.";
    } elseif (strlen($username) > 50 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors['username'] = "Username must be alphanumeric/underscores, max 50 characters.";
    }

    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors['password'] = "Password must be at least 8 characters.";
    } elseif (strlen($password) > 128) {
        $errors['password'] = "Password must be 128 characters or fewer.";
    }

    if ($password !== $confirm) {
        $errors['confirm_password'] = "Passwords do not match.";
    }

    if (empty($errors)) {
        try {
            $hashed = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
            $stmt->execute([
                ':username' => $username,
                ':password' => $hashed,
            ]);

            $success = "User created successfully.";
            $username = '';

        } catch (PDOException $e) {
            error_log("add_user.php error: " . $e->getMessage());

            if ($e->getCode() == 23000) {
                $errors['username'] = "This username already exists.";
            } else {
                $errors['database'] = "An error occurred while creating the user.";
            }
        }
    }
}

try {
    $stmt = $pdo->query("SELECT id, username, created_at FROM users ORDER BY id ASC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("add_user.php users query error: " . $e->getMessage());
    $users = [];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Users</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="page-wrapper">
    <nav class="navbar">
        <span class="nav-brand">Student Management System</span>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="add_student.php">+ Add Student</a>
            <a href="archive.php">Archive</a>
            <a href="audit_log.php">Audit Log</a>
            <a href="logout.php" class="btn-logout">Logout (<?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?>)</a>
        </div>
    </nav>

    <div class="container" style="margin-top: 10px;">
        <h2>Add New User</h2>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (isset($errors['database'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($errors['database'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" maxlength="50" placeholder="e.g. john_doe"
                       value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (isset($errors['username'])): ?>
                    <div class="field-error"><?php echo htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" minlength="8" maxlength="128"
                       placeholder="Minimum 8 characters">
                <?php if (isset($errors['password'])): ?>
                    <div class="field-error"><?php echo htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" minlength="8" maxlength="128"
                       placeholder="Re-enter password">
                <?php if (isset($errors['confirm_password'])): ?>
                    <div class="field-error"><?php echo htmlspecialchars($errors['confirm_password'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group" style="margin-top: 22px;">
                <button name="add_user" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>

    <div class="card" style="margin-top: 25px;">
        <h3>Existing Users</h3>

        <?php if (empty($users)): ?>
            <div class="empty-state"><p>No users found.</p></div>
        <?php else: ?>
            <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Username</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><strong><?php echo htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                    <td><span class="meta-text"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($u['created_at'])), ENT_QUOTES, 'UTF-8'); ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
