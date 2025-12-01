<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

include("db.php");

$user_id = $_SESSION['user_id'];

// Fetch parent details
$parent_sql = "SELECT u.*, p.photo as parent_photo, p.relationship 
               FROM users u 
               LEFT JOIN parents p ON u.user_id = p.user_id 
               WHERE u.user_id = ?";
$stmt = $conn->prepare($parent_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$parent_result = $stmt->get_result();
$parent = $parent_result->fetch_assoc();

if (!$parent) {
    header("Location: login.php");
    exit();
}

// Handle profile update
$success = '';
$error = '';
if (isset($_POST['update_profile'])) {
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $phone = trim($_POST['phone']);
    $address_id = intval($_POST['address_id'] ?? 0);

    if (empty($firstName) || empty($lastName) || empty($phone)) {
        $error = "All fields are required.";
    } else {
        $update_sql = "UPDATE users SET firstName = ?, lastName = ?, phone = ?, address_id = ? WHERE user_id = ?";
        $stmt_update = $conn->prepare($update_sql);
        $stmt_update->bind_param("ssiii", $firstName, $lastName, $phone, $address_id, $user_id);
        if ($stmt_update->execute()) {
            $success = "Profile updated successfully!";
            // Refresh parent data
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $error = "Failed to update profile. Please try again.";
        }
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters long.";
    } else {
        // Verify current password
        if (password_verify($current_password, $parent['password'])) {
            $hashed_new = password_hash($new_password, PASSWORD_DEFAULT);
            $update_pass_sql = "UPDATE users SET password = ? WHERE user_id = ?";
            $stmt_pass = $conn->prepare($update_pass_sql);
            $stmt_pass->bind_param("si", $hashed_new, $user_id);
            if ($stmt_pass->execute()) {
                $success = "Password changed successfully!";
            } else {
                $error = "Failed to change password. Please try again.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }
}

// Fetch addresses for dropdown (simplified, assume existing addresses)
$addresses_sql = "SELECT * FROM addresses ORDER BY created_at DESC";
$addresses_result = $conn->query($addresses_sql);
$addresses = $addresses_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Parent Dashboard | Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --secondary-color: #8b5cf6;
            --accent-color: #06b6d4;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --bg-light: #f8fafc;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
        }
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #1e293b 0%, #334155 100%);
            color: white;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        .sidebar.collapsed {
            transform: translateX(-260px);
        }
        .sidebar-header {
            padding: 2rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.2);
            margin-bottom: 1rem;
            object-fit: cover;
        }
        .sidebar h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.8);
            padding: 0.875rem 1.5rem;
            text-decoration: none;
            border-radius: 0 20px 20px 0;
            margin: 0.25rem 0;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(99, 102, 241, 0.2);
            color: var(--primary-color);
            transform: translateX(5px);
        }
        .sidebar .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            font-size: 1.1rem;
        }
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }
        .main-content.expanded {
            margin-left: 0;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
        }
        .profile-section {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            margin: 0 auto 1rem;
            object-fit: cover;
        }
        .profile-body {
            padding: 2rem;
        }
        .form-modern label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            display: block;
        }
        .form-modern .form-control {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 0.75rem 1rem;
            transition: border-color 0.2s ease;
        }
        .form-modern .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }
        .btn-update {
            background: linear-gradient(135deg, var(--success-color), #059669);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-update:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .password-section {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .password-header {
            background: linear-gradient(135deg, var(--warning-color), #d97706);
            color: white;
            padding: 1.5rem;
        }
        .password-body {
            padding: 2rem;
        }
        .alert-modern {
            border-radius: 8px;
            border: none;
        }
        .toggle-sidebar {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-dark);
            z-index: 1001;
            position: fixed;
            top: 1rem;
            left: 1rem;
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-260px); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .page-header h1 { font-size: 1.5rem; }
        }
        @media (max-width: 768px) {
            .toggle-sidebar { display: block; }
        }
    </style>
</head>
<body>
    <button class="toggle-sidebar" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="<?= $parent['parent_photo'] ?? 'default-parent-avatar.png' ?>" alt="Parent Avatar" onerror="this.src='default-avatar.png'">
            <h3><?= htmlspecialchars($parent['firstName'] ?? 'Parent') ?></h3>
        </div>
        <ul class="nav flex-column p-0 m-0">
            <li class="nav-item">
                <a href="parents_dashboard.php" class="nav-link" onclick="showSection('dashboard')"><i class="bi bi-house-door"></i> Dashboard</a>
            </li>
            <li class="nav-item">
                <a href="children.php" class="nav-link" onclick="showSection('children')"><i class="bi bi-people"></i> My Children</a>
            </li>
            <li class="nav-item">
                <a href="parent_view_attendance.php" class="nav-link" target="_blank"><i class="bi bi-card-checklist"></i> Attendance</a>
            </li>
            <li class="nav-item">
                <a href="parent_view_performance.php" class="nav-link" target="_blank"><i class="bi bi-graph-up"></i> Performance</a>
            </li>
            <li class="nav-item">
                <a href="parent_view_materials.php" class="nav-link" target="_blank"><i class="bi bi-folder"></i> Materials</a>
            </li>
            <li class="nav-item">
                <a href="parent_messages.php" class="nav-link" target="_blank"><i class="bi bi-envelope"></i> Messages</a>
            </li>
            
            <li class="nav-item">
                <a href="parent_profile.php" class="nav-link active" onclick="showSection('profile')"><i class="bi bi-person-circle"></i> Profile</a>
            </li>
            <li class="nav-item">
                <a href="parent_payments.php" class="nav-link "><i class="bi bi-credit-card"></i> Payments</a>
            </li>
             <li class="nav-item">
                <a href="parent_invoices_print.php" class="nav-link "><i class="bi bi-credit-card"></i> Invoices</a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content" id="main-content">
        <header class="page-header">
            <div>
                <h1>My Profile</h1>
                <p>Update your personal information and manage account settings securely.</p>
            </div>
        </header>

<div class="container mt-3">
<?php if(isset($_SESSION['success'])): ?>
    <div class="alert alert-success fade-alert"><?= $_SESSION['success']; ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if(isset($_SESSION['error'])): ?>
    <div class="alert alert-danger fade-alert"><?= $_SESSION['error']; ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
</div>

        <!-- Profile Update Section -->
        <section class="profile-section">
            <div class="profile-header">
                <img src="<?= $parent['parent_photo'] ?? 'default-parent-avatar.png' ?>" alt="Profile Photo" class="profile-avatar" onerror="this.src='default-avatar.png'">
                <h2><?= htmlspecialchars($parent['firstName'] . ' ' . $parent['lastName']) ?></h2>
                <p class="mb-0"><?= htmlspecialchars($parent['email']) ?></p>
            </div>
            <div class="profile-body">
                <h5 class="mb-3"><i class="bi bi-pencil-square"></i> Edit Profile</h5>
                <form method="POST" class="form-modern">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label>First Name</label>
                            <input type="text" class="form-control" name="firstName" value="<?= htmlspecialchars($parent['firstName']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label>Last Name</label>
                            <input type="text" class="form-control" name="lastName" value="<?= htmlspecialchars($parent['lastName']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label>Phone</label>
                            <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($parent['phone']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label>Address</label>
                            <select class="form-select" name="address_id">
                                <option value="">Select Address</option>
                                <?php foreach ($addresses as $addr): ?>
                                    <option value="<?= $addr['address_id'] ?>" <?= $addr['address_id'] == $parent['address_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($addr['address1'] . ', ' . $addr['district'] . ', ' . $addr['country']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" name="update_profile" class="btn btn-update">
                                <i class="bi bi-check-circle"></i> Update Profile
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <!-- Password Change Section -->
        <section class="password-section">
            <div class="password-header">
                <h5><i class="bi bi-lock"></i> Change Password</h5>
            </div>
            <div class="password-body">
                <form method="POST" class="form-modern">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label>Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="col-md-4">
                            <label>New Password</label>
                            <input type="password" class="form-control" name="new_password" required>
                        </div>
                        <div class="col-md-4">
                            <label>Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" name="change_password" class="btn btn-update">
                                <i class="bi bi-key"></i> Change Password
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            sidebar.classList.toggle('show');
            mainContent.classList.toggle('expanded');
        }

        // Close sidebar on outside click (mobile)
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.toggle-sidebar');
            if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target) && window.innerWidth <= 768) {
                sidebar.classList.remove('show');
                document.getElementById('main-content').classList.remove('expanded');
            }
        });
    </script>
</body>
</html>