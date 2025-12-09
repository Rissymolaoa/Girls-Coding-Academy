<?php
// backup.php
// Secure database backup endpoint for admin only. Handles create, list, download.

session_start();

// Security: Admin-only access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

// DB config (use .env in production)
$db_host = 'localhost:3307';
$db_user = 'root';
$db_pass = '';
$db_name = 'girlscodingacademydb';
$backup_dir = 'backups/';
$max_backups = 10; // Retain last 10 backups

// Ensure backup directory exists and is writable
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Handle requests
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'create') {
    // Create new backup
    $timestamp = date('Y-m-d_H-i-s');
    $backup_file = $backup_dir . $db_name . '_' . $timestamp . '.sql.gz';

    // mysqldump command (add --single-transaction for InnoDB consistency)
    $command = sprintf(
        'mysqldump --host=%s --user=%s --password=%s --single-transaction --routines --triggers %s | gzip > %s 2>&1',
        escapeshellarg($db_host),
        escapeshellarg($db_user),
        escapeshellarg($db_pass),
        escapeshellarg($db_name),
        escapeshellarg($backup_file)
    );

    $output = shell_exec($command);
    $exit_code = $exit_code ?? 0; // Capture exit code if needed

    if (file_exists($backup_file) && filesize($backup_file) > 0) {
        // Cleanup old backups
        $files = glob($backup_dir . $db_name . '_*.sql.gz');
        if (count($files) > $max_backups) {
            $old_files = array_slice($files, $max_backups);
            foreach ($old_files as $old_file) {
                unlink($old_file);
            }
        }

        error_log("Backup created: " . $backup_file); // Log success
        echo json_encode([
            'success' => true,
            'file' => $backup_file,
            'size' => filesize($backup_file),
            'timestamp' => $timestamp
        ]);
    } else {
        error_log("Backup failed: " . $output); // Log error
        echo json_encode(['success' => false, 'error' => 'Backup creation failed. Check logs.']);
    }

} elseif ($action === 'list') {
    // List recent backups
    $files = glob($backup_dir . $db_name . '_*.sql.gz');
    usort($files, function($a, $b) { return filemtime($b) - filemtime($a); }); // Newest first
    $backups = [];
    foreach (array_slice($files, 0, $max_backups) as $file) {
        $backups[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => date('M j, Y H:i', filemtime($file)),
            'url' => basename($file) // For download
        ];
    }
    echo json_encode(['success' => true, 'backups' => $backups]);

} elseif ($action === 'download' && isset($_GET['file'])) {
    // Secure download
    $file_name = basename($_GET['file']);
    $full_path = $backup_dir . $file_name;
    if (file_exists($full_path) && strpos($full_path, $backup_dir) === 0) {
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . filesize($full_path));
        readfile($full_path);
        exit();
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'File not found']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>