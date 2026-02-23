<?php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Forbidden");
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'infosec_lab');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BACKUP_DIR', __DIR__ . '/backups/');
define('RETENTION_DAYS', 7);

function writeLog($message) {
    $logFile = BACKUP_DIR . 'backup_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}" . PHP_EOL, FILE_APPEND);
    echo "[{$timestamp}] {$message}" . PHP_EOL;
}

if (!is_dir(BACKUP_DIR)) {
    if (!mkdir(BACKUP_DIR, 0750, true)) {
        error_log("backup.php: Failed to create backup directory: " . BACKUP_DIR);
        echo "ERROR: Failed to create backup directory." . PHP_EOL;
        exit(1);
    }
    writeLog("INFO: Created backup directory: " . BACKUP_DIR);
}

$timestamp = date('Y-m-d_H-i-s');
$filename = "backup_infosec_{$timestamp}.sql";
$filepath = BACKUP_DIR . $filename;

$command = sprintf(
    'mysqldump --host=%s --user=%s --password=%s %s > %s 2>&1',
    escapeshellarg(DB_HOST),
    escapeshellarg(DB_USER),
    escapeshellarg(DB_PASS),
    escapeshellarg(DB_NAME),
    escapeshellarg($filepath)
);

$output = [];
$returnCode = 0;
exec($command, $output, $returnCode);

if ($returnCode !== 0) {
    $errorOutput = implode("\n", $output);
    writeLog("ERROR: mysqldump failed with code {$returnCode}. Output: {$errorOutput}");
    error_log("backup.php: mysqldump failed with code {$returnCode}");
    exit(1);
}

if (!file_exists($filepath) || filesize($filepath) === 0) {
    writeLog("ERROR: Backup file is empty or was not created: {$filename}");
    exit(1);
}

writeLog("SUCCESS: Database dumped to {$filename} (" . filesize($filepath) . " bytes)");

$gzFilepath = $filepath . '.gz';

$sqlContent = file_get_contents($filepath);
if ($sqlContent === false) {
    writeLog("ERROR: Failed to read dump file for compression: {$filename}");
    exit(1);
}

$gzFile = gzopen($gzFilepath, 'wb9');
if ($gzFile === false) {
    writeLog("ERROR: Failed to create gzip file: {$filename}.gz");
    exit(1);
}

gzwrite($gzFile, $sqlContent);
gzclose($gzFile);

if (file_exists($gzFilepath) && filesize($gzFilepath) > 0) {
    unlink($filepath);
    writeLog("SUCCESS: Compressed to {$filename}.gz (" . filesize($gzFilepath) . " bytes)");
} else {
    writeLog("ERROR: Compression failed — keeping uncompressed file.");
    exit(1);
}

$retentionSeconds = RETENTION_DAYS * 24 * 60 * 60;
$cutoff = time() - $retentionSeconds;
$deletedCount = 0;

$files = glob(BACKUP_DIR . 'backup_infosec_*.sql.gz');
if ($files !== false) {
    foreach ($files as $file) {
        if (filemtime($file) < $cutoff) {
            if (unlink($file)) {
                $deletedCount++;
            }
        }
    }
}

if ($deletedCount > 0) {
    writeLog("CLEANUP: Deleted {$deletedCount} old backup(s) (older than " . RETENTION_DAYS . " days)");
} else {
    writeLog("CLEANUP: No old backups to delete.");
}

writeLog("INFO: Backup process completed successfully.");
exit(0);
?>
