<?php

$config = [
    'host'     => 'localhost',
    'dbname'   => 'infosec_lab',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
];

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    echo "Service temporarily unavailable. Please try again later.";
    exit();
}
?>
