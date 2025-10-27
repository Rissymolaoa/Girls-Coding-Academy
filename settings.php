<?php
// settings.php
// Admin settings page: Profile management, system settings, security, backups, etc.
// Modern UI with tabs, forms, responsive design.

session_start();

// Check if user is logged in and admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// DB connection
$host = "localhost";
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

// Handle form submissions
$message = '';
if ($_POST) {
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'] ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

    $sql = "UPDATE users SET firstName='$firstName', lastName='$lastName', email='$email', phone='$phone'";
    if ($password) $sql .= ", password='$password'";
    $sql .= " WHERE user_id = $user_id";
    
    if ($conn->query($sql)) {
        $message = "Profile updated successfully!";
        $_SESSION['username'] = $firstName . ' ' . $lastName; // Update session if needed
        $admin_user = ['firstName' => $firstName, 'lastName' => $lastName, 'email' => $email, 'phone' => $phone]; // Refresh
    } else {
        $message = "Error updating profile: " . $conn->error;
    }
}

// System settings (placeholder for now)
$system_settings = [
    'site_name' => 'Girls Coding Academy',
    'email_notifications' => 'enabled',
    'backup_frequency' => 'weekly'
];
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
      <p class="text-muted">Manage your profile, system preferences, and admin tools.</p>
      <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>
    </div>

    <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button">Profile</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button">Security</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button">System Settings</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup" type="button">Backups</button>
      </li>
    </ul>

    <div class="tab-content">
      <!-- Profile Tab -->
      <div class="tab-pane fade show active" id="profile" role="tabpanel">
        <div class="settings-card">
          <h3>Admin Profile</h3>
          <form method="POST">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">First Name</label>
                <input type="text" name="firstName" class="form-control" value="<?= htmlspecialchars($admin_user['firstName'] ?? '') ?>" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Last Name</label>
                <input type="text" name="lastName" class="form-control" value="<?= htmlspecialchars($admin_user['lastName'] ?? '') ?>" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($admin_user['email'] ?? '') ?>" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Phone</label>
                <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($admin_user['phone'] ?? '') ?>">
              </div>
              <div class="col-12 mb-3">
                <label class="form-label">Profile Photo</label>
                <input type="file" name="photo" class="form-control" accept="image/*">
              </div>
            </div>
            <button type="submit" class="btn btn-save">Save Profile</button>
          </form>
        </div>
      </div>

      <!-- Security Tab -->
      <div class="tab-pane fade" id="security" role="tabpanel">
        <div class="settings-card">
          <h3>Security & Password</h3>
          <form method="POST">
            <input type="hidden" name="update_security" value="1">
            <div class="mb-3">
              <label class="form-label">New Password</label>
              <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current">
            </div>
            <div class="mb-3">
              <label class="form-label">Confirm Password</label>
              <input type="password" name="confirm_password" class="form-control">
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="two_factor">
              <label class="form-check-label" for="two_factor">Enable Two-Factor Authentication</label>
            </div>
            <button type="submit" class="btn btn-save">Update Security</button>
          </form>
        </div>
      </div>

      <!-- System Settings Tab -->
      <div class="tab-pane fade" id="system" role="tabpanel">
        <div class="settings-card">
          <h3>System Configuration</h3>
          <form>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Site Name</label>
                <input type="text" class="form-control" value="<?= $system_settings['site_name'] ?>">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Default Email Notifications</label>
                <select class="form-select">
                  <option <?= $system_settings['email_notifications'] === 'enabled' ? 'selected' : '' ?>>Enabled</option>
                  <option <?= $system_settings['email_notifications'] === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Backup Frequency</label>
                <select class="form-select">
                  <option <?= $system_settings['backup_frequency'] === 'daily' ? 'selected' : '' ?>>Daily</option>
                  <option <?= $system_settings['backup_frequency'] === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                  <option <?= $system_settings['backup_frequency'] === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                </select>
              </div>
              <div class="col-12 mb-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="maintenance_mode">
                  <label class="form-check-label" for="maintenance_mode">Enable Maintenance Mode</label>
                </div>
              </div>
            </div>
            <button type="submit" class="btn btn-save">Save System Settings</button>
          </form>
        </div>
      </div>

      <!-- Backups Tab -->
      <div class="tab-pane fade" id="backup" role="tabpanel">
        <div class="settings-card">
          <h3>Database Backups</h3>
          <p class="text-muted">Manage and download database backups.</p>
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5>Last Backup: October 20, 2025</h5>
            <button class="btn btn-outline-success"><i class="bi bi-download"></i> Download Latest</button>
          </div>
          <button class="btn btn-primary" onclick="createBackup()"><i class="bi bi-hdd-network"></i> Create New Backup</button>
          <div class="mt-3">
            <small class="text-muted">Backups are stored securely and can be restored via the admin console.</small>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function createBackup() {
    if (confirm('Create a new database backup? This may take a few minutes.')) {
      // Placeholder for backup logic
      alert('Backup initiated. Check the logs for status.');
    }
  }
</script>

<?php $conn->close(); ?>
</body>
</html>