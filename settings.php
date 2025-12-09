<?php
// settings.php
// Admin settings page: Profile management, system settings, security, backups, etc.

session_start();

// Check if user is logged in and admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// DB connection
$host = "localhost:3307";
$user = "root";
$pass = "";
$db = "girlscodingacademydb";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Fetch admin user details
$user_id = $_SESSION['user_id'];
$user_query = $conn->query("SELECT * FROM users WHERE user_id = $user_id");
$admin_user = $user_query->fetch_assoc();

$message = '';
$message_type = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $updates = [];
    $params = [];
    
    // Selectively update only provided fields
    if (!empty($_POST['firstName'])) {
        $firstName = trim($_POST['firstName']);
        $updates[] = "firstName = ?";
        $params[] = $firstName;
    }
    
    if (!empty($_POST['lastName'])) {
        $lastName = trim($_POST['lastName']);
        $updates[] = "lastName = ?";
        $params[] = $lastName;
    }
    
    if (!empty($_POST['email'])) {
        $email = trim($_POST['email']);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $updates[] = "email = ?";
            $params[] = $email;
        } else {
            $message = "Invalid email format!";
            $message_type = "danger";
        }
    }
    
    if (!empty($_POST['phone'])) {
        $phone = trim($_POST['phone']);
        $updates[] = "phone = ?";
        $params[] = $phone;
    }
    
    // If there are updates to make
    if (!empty($updates) && $message_type !== 'danger') {
        $params[] = $user_id;
        $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        
        // Build type string dynamically
        $types = str_repeat('s', count($params) - 1) . 'i';
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $message = "Profile updated successfully!";
            $message_type = "success";
            // Refresh admin user data
            $user_query = $conn->query("SELECT * FROM users WHERE user_id = $user_id");
            $admin_user = $user_query->fetch_assoc();
        } else {
            $message = "Error updating profile: " . $stmt->error;
            $message_type = "danger";
        }
        $stmt->close();
    } elseif (empty($updates) && $message_type !== 'danger') {
        $message = "No fields to update.";
        $message_type = "info";
    }
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_password') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (!empty($password)) {
        if ($password === $confirm_password) {
            if (strlen($password) >= 6) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $message = "Password updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating password: " . $stmt->error;
                    $message_type = "danger";
                }
                $stmt->close();
            } else {
                $message = "Password must be at least 6 characters long.";
                $message_type = "danger";
            }
        } else {
            $message = "Passwords do not match!";
            $message_type = "danger";
        }
    } else {
        $message = "Please enter a password.";
        $message_type = "danger";
    }
}

// Handle backup creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_backup') {
    $backup_dir = 'backups';
    
    // Create backups directory if it doesn't exist
    if (!is_dir($backup_dir)) {
        if (!mkdir($backup_dir, 0755, true)) {
            $message = "Error: Could not create backups directory. Check folder permissions.";
            $message_type = "danger";
        }
    }
    
    if ($message_type !== 'danger') {
        $backup_file = $backup_dir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
        $file_handle = fopen($backup_file, 'w');
        
        if ($file_handle === false) {
            $message = "Error: Could not create backup file. Check folder permissions.";
            $message_type = "danger";
        } else {
            // Write SQL header
            $sql_header = "-- Database Backup\n";
            $sql_header .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $sql_header .= "-- Database: " . $db . "\n\n";
            $sql_header .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
            $sql_header .= "SET AUTOCOMMIT = 0;\n";
            $sql_header .= "START TRANSACTION;\n\n";
            
            fwrite($file_handle, $sql_header);
            
            // Get all tables
            $tables = [];
            $result = $conn->query("SHOW TABLES");
            if ($result) {
                while ($row = $result->fetch_array()) {
                    $tables[] = $row[0];
                }
            }
            
            $backup_success = true;
            
            // Backup each table
            foreach ($tables as $table) {
                // Drop table statement
                fwrite($file_handle, "DROP TABLE IF EXISTS `" . $table . "`;\n");
                
                // Create table statement
                $create_result = $conn->query("SHOW CREATE TABLE `" . $table . "`");
                if ($create_result) {
                    $create_row = $create_result->fetch_array();
                    fwrite($file_handle, $create_row[1] . ";\n\n");
                } else {
                    $backup_success = false;
                    break;
                }
                
                // Insert data
                $data_result = $conn->query("SELECT * FROM `" . $table . "`");
                if ($data_result && $data_result->num_rows > 0) {
                    while ($row = $data_result->fetch_assoc()) {
                        $columns = implode('`, `', array_keys($row));
                        $values = array_map(function($val) use ($conn) {
                            return $val === null ? 'NULL' : "'" . $conn->real_escape_string($val) . "'";
                        }, array_values($row));
                        
                        fwrite($file_handle, "INSERT INTO `" . $table . "` (`" . $columns . "`) VALUES (" . implode(', ', $values) . ");\n");
                    }
                    fwrite($file_handle, "\n");
                }
            }
            
            // Write footer
            $sql_footer = "COMMIT;\n";
            $sql_footer .= "SET AUTOCOMMIT = 1;\n";
            fwrite($file_handle, $sql_footer);
            
            fclose($file_handle);
            
            if ($backup_success) {
                $message = "Database backup created successfully! File: " . basename($backup_file) . " (" . round(filesize($backup_file) / 1024, 2) . " KB)";
                $message_type = "success";
            } else {
                @unlink($backup_file);
                $message = "Error: Could not read all tables. Backup cancelled.";
                $message_type = "danger";
            }
        }
    }
}

// Get list of backups
$backups = [];
if (is_dir('backups')) {
    $files = scandir('backups', SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if (strpos($file, 'backup_') === 0 && strpos($file, '.sql') !== false) {
            $backups[] = [
                'filename' => $file,
                'size' => filesize('backups/' . $file),
                'date' => filemtime('backups/' . $file)
            ];
        }
    }
}

// Handle backup download
if (isset($_GET['download_backup'])) {
    $backup_file = basename($_GET['download_backup']);
    $file_path = 'backups/' . $backup_file;
    
    if (file_exists($file_path) && strpos($backup_file, 'backup_') === 0) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $backup_file . '"');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit();
    }
}

// Handle backup deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_backup') {
    $backup_file = basename($_POST['backup_file']);
    $file_path = 'backups/' . $backup_file;
    
    if (file_exists($file_path) && strpos($backup_file, 'backup_') === 0) {
        if (unlink($file_path)) {
            $message = "Backup deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting backup.";
            $message_type = "danger";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Settings - Admin Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
  :root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.1), 0 2px 4px rgba(0,0,0,0.06);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.1), 0 4px 6px rgba(0,0,0,0.05);
  }

  body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
    padding-top: 56px;
  }

  .content {
    min-height: calc(100vh - 56px);
  }

  .main {
    padding: 2rem;
  }

  .page-header {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-lg);
    border: 1px solid rgba(255, 255, 255, 0.2);
  }

  .page-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
  }

  .settings-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid rgba(255, 255, 255, 0.2);
    margin-bottom: 2rem;
  }

  .nav-tabs .nav-link {
    border: none;
    border-radius: 12px 12px 0 0;
    font-weight: 500;
    color: #6B7280;
  }

  .nav-tabs .nav-link.active {
    background: var(--primary-gradient);
    color: white;
  }

  .form-label {
    font-weight: 600;
    color: #374151;
  }

  .btn-save {
    background: var(--primary-gradient);
    border: none;
    border-radius: 12px;
    padding: 0.75rem 2rem;
    color: white;
    font-weight: 600;
  }

  .btn-save:hover {
    opacity: 0.9;
    color: white;
  }

  .backup-item {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .backup-info h6 {
    margin-bottom: 0.5rem;
    font-weight: 600;
  }

  .backup-info small {
    color: #6B7280;
  }

  .form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
  }
</style>
</head>
<body>
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="content">
  <main class="main">
    <div class="page-header">
      <h1>Settings</h1>
      <p class="text-muted">Manage your profile, system preferences, security, and database backups.</p>
      
      <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
          <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : ($message_type === 'danger' ? 'exclamation-circle' : 'info-circle') ?>"></i>
          <?= htmlspecialchars($message) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
    </div>

    <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button">
          <i class="bi bi-person"></i> Profile
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button">
          <i class="bi bi-shield-lock"></i> Security
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup" type="button">
          <i class="bi bi-hdd-network"></i> Backups
        </button>
      </li>
    </ul>

    <div class="tab-content">
      <!-- Profile Tab -->
      <div class="tab-pane fade show active" id="profile" role="tabpanel">
        <div class="settings-card">
          <h3><i class="bi bi-person-circle"></i> Admin Profile</h3>
          <p class="text-muted">Update your profile information. Leave fields blank to keep existing values.</p>
          <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">First Name</label>
                <input type="text" name="firstName" class="form-control" 
                  placeholder="<?= htmlspecialchars($admin_user['firstName'] ?? 'Enter first name') ?>">
                <small class="text-muted">Current: <?= htmlspecialchars($admin_user['firstName'] ?? 'Not set') ?></small>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Last Name</label>
                <input type="text" name="lastName" class="form-control" 
                  placeholder="<?= htmlspecialchars($admin_user['lastName'] ?? 'Enter last name') ?>">
                <small class="text-muted">Current: <?= htmlspecialchars($admin_user['lastName'] ?? 'Not set') ?></small>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" 
                  placeholder="<?= htmlspecialchars($admin_user['email'] ?? 'Enter email') ?>">
                <small class="text-muted">Current: <?= htmlspecialchars($admin_user['email'] ?? 'Not set') ?></small>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Phone</label>
                <input type="tel" name="phone" class="form-control" 
                  placeholder="<?= htmlspecialchars($admin_user['phone'] ?? 'Enter phone') ?>">
                <small class="text-muted">Current: <?= htmlspecialchars($admin_user['phone'] ?? 'Not set') ?></small>
              </div>
            </div>
            <button type="submit" class="btn btn-save"><i class="bi bi-check-circle"></i> Save Profile</button>
          </form>
        </div>
      </div>

      <!-- Security Tab -->
      <div class="tab-pane fade" id="security" role="tabpanel">
        <div class="settings-card">
          <h3><i class="bi bi-shield-lock"></i> Security & Password</h3>
          <p class="text-muted">Update your password to keep your account secure.</p>
          <form method="POST">
            <input type="hidden" name="action" value="update_password">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-control" required>
                <small class="text-muted">Minimum 6 characters</small>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
              </div>
            </div>
            <button type="submit" class="btn btn-save"><i class="bi bi-check-circle"></i> Update Password</button>
          </form>
        </div>
      </div>

      <!-- Backups Tab -->
      <div class="tab-pane fade" id="backup" role="tabpanel">
        <div class="settings-card">
          <h3><i class="bi bi-hdd-network"></i> Database Backups</h3>
          <p class="text-muted">Create, download, and manage database backups securely.</p>
          
          <div class="mb-4">
            <form method="POST">
              <input type="hidden" name="action" value="create_backup">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-cloud-arrow-down"></i> Create New Backup
              </button>
            </form>
          </div>

          <?php if (!empty($backups)): ?>
            <h5>Available Backups</h5>
            <?php foreach ($backups as $backup): ?>
              <div class="backup-item">
                <div class="backup-info">
                  <h6><?= htmlspecialchars($backup['filename']) ?></h6>
                  <small>
                    <i class="bi bi-calendar"></i> <?= date('M d, Y H:i:s', $backup['date']) ?> | 
                    <i class="bi bi-file-earmark"></i> <?= round($backup['size'] / 1024 / 1024, 2) ?> MB
                  </small>
                </div>
                <div>
                  <a href="?download_backup=<?= urlencode($backup['filename']) ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-download"></i> Download
                  </a>
                  <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete_backup">
                    <input type="hidden" name="backup_file" value="<?= htmlspecialchars($backup['filename']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" 
                      onclick="return confirm('Are you sure you want to delete this backup?')">
                      <i class="bi bi-trash"></i> Delete
                    </button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="alert alert-info">
              <i class="bi bi-info-circle"></i> No backups available yet. Create one now to get started.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<?php $conn->close(); ?>
</body>
</html>