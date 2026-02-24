<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}
$success_message = '';
$error_message = '';

// Handle add new user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $username   = trim($_POST['username']);
    $email      = trim($_POST['email']);
    $password   = $_POST['password'];
    $firstName  = trim($_POST['firstName']);
    $lastName   = trim($_POST['lastName']);
    $role       = $_POST['role'];
    $phone      = trim($_POST['phone'] ?? '');
    $gender     = $_POST['gender'];
    $dob        = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $IDNumber   = trim($_POST['IDNumber'] ?? '');
    // Address fields
    $address1   = trim($_POST['physicalAddress'] ?? '');
    $streetName = trim($_POST['streetName'] ?? '');
    $postalCode = trim($_POST['postalCode'] ?? '');
    $district   = trim($_POST['city'] ?? '');
    $country    = trim($_POST['country'] ?? '');
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($firstName) || empty($lastName) || empty($role)) {
        $error_message = 'All required fields must be filled!';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters!';
    } else {
        // Check duplicate username/email
        $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows > 0) {
            $error_message = 'Username or email already exists!';
            $check_stmt->close();
        } else {
            $check_stmt->close();
            $conn->begin_transaction();
            try {
                $address_id = null;
                // Only process address if any field is provided
                if (!empty($address1) || !empty($streetName) || !empty($postalCode) || !empty($district) || !empty($country)) {
                    $address1   = $address1   ?: null;
                    $streetName = $streetName ?: null;
                    $postalCode = $postalCode ?: null;
                    $district   = $district   ?: null;
                    $country    = $country    ?: null;
                    // Check if exact address already exists
                    $sql = "SELECT address_id FROM addresses WHERE
                            (address1 IS NULL AND ? IS NULL OR address1 = ?) AND
                            (streetName IS NULL AND ? IS NULL OR streetName = ?) AND
                            (postalCode IS NULL AND ? IS NULL OR postalCode = ?) AND
                            (district IS NULL AND ? IS NULL OR district = ?) AND
                            (country IS NULL AND ? IS NULL OR country = ?)";
                    $check_addr = $conn->prepare($sql);
                    $check_addr->bind_param("ssssssssss",
                        $address1, $address1,
                        $streetName, $streetName,
                        $postalCode, $postalCode,
                        $district, $district,
                        $country, $country
                    );
                    $check_addr->execute();
                    $result = $check_addr->get_result();
                    if ($result->num_rows > 0) {
                        $address_id = $result->fetch_assoc()['address_id'];
                    } else {
                        $ins = $conn->prepare("INSERT INTO addresses (address1, streetName, postalCode, district, country, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                        $ins->bind_param("sssss", $address1, $streetName, $postalCode, $district, $country);
                        $ins->execute();
                        $address_id = $conn->insert_id;
                        $ins->close();
                    }
                    $check_addr->close();
                }
                // Insert user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $user_stmt = $conn->prepare("
                    INSERT INTO users
                    (username, email, password, firstName, lastName, role, phone, gender, dob, IDNumber, address_id, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                $user_stmt->bind_param("ssssssssssi",
                    $username, $email, $hashed_password, $firstName, $lastName,
                    $role, $phone, $gender, $dob, $IDNumber, $address_id
                );
                $user_stmt->execute();
                $user_id = $conn->insert_id;
                $user_stmt->close();
                // Insert into role-specific table
                if (in_array($role, ['teacher', 'student', 'parent'])) {
                    $table = $role . "s";
                    $extra_col = $role === 'parent' ? ", relationship" : "";
                    $extra_val = $role === 'parent' ? ", 'Mother'" : "";
                    $stmt = $conn->prepare("INSERT INTO $table (user_id$extra_col) VALUES (? $extra_val)");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $stmt->close();
                }
                $conn->commit();
                $success_message = 'User added successfully!';
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = 'Failed to add user: ' . $e->getMessage();
            }
        }
    }
}
// Handle toggle status, change role, delete — SAFE ACCESS TO user_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] !== 'add_user') {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    if ($user_id <= 0) {
        $error_message = 'Invalid user ID.';
    } else {
        $action = $_POST['action'];
        if ($action === 'toggle_status') {
            $stmt = $conn->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            $success_message = 'User status updated!';
        }
        if ($action === 'change_role') {
            $new_role = $_POST['new_role'] ?? '';
            if (in_array($new_role, ['admin','teacher','parent','student','marketing','accounts'])) {
                $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
                $stmt->bind_param("si", $new_role, $user_id);
                $stmt->execute();
                $stmt->close();
                $success_message = 'User role updated!';
            }
        }
        if ($action === 'delete_user') {
            // NEW (soft delete - moves to recycle bin)
            $stmt = $conn->prepare("UPDATE users SET deleted_at = NOW() WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $success_message = 'User deleted successfully!';
            } else {
                $error_message = 'Failed to delete user.';
            }
            $stmt->close();
        }
    }
}
// Search & Filter
$search = trim($_GET['search'] ?? '');
$role_filter = $_GET['role'] ?? '';
$where = [];
$params = [];
$types = "";
if ($search) {
    $where[] = "(username LIKE ? OR firstName LIKE ? OR lastName LIKE ? OR email LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "ssss";
}
if ($role_filter && in_array($role_filter, ['admin','teacher','parent','student','marketing','accounts'])) {
    $where[] = "role = ?";
    $params[] = $role_filter;
    $types .= "s";
}
$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";
$sql = "SELECT u.user_id, u.username, u.firstName, u.lastName, u.email, u.role, u.status, u.phone, u.gender,
              COALESCE(t.photo, s.photo, p.photo) as photo
        FROM users u
        LEFT JOIN teachers t ON u.user_id = t.user_id AND u.role = 'teacher'
        LEFT JOIN students s ON u.user_id = s.user_id AND u.role = 'student'
        LEFT JOIN parents p ON u.user_id = p.user_id AND u.role = 'parent'
        $where_clause
        ORDER BY u.role, u.created_at DESC";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$users = $stmt->get_result();
// Statistics
$stats_query = "SELECT
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admins,
    SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as total_teachers,
    SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as total_students,
    SUM(CASE WHEN role = 'parent' THEN 1 ELSE 0 END) as total_parents
FROM users";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage All Users - Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .main-content { margin-left: 220px; transition: margin-left 0.3s ease; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        .card-hover:hover { transform: translateY(-8px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        /* Loading Screen – White background + centered small logo + rotating ring only */
        #loading-screen {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            transition: opacity 0.8s ease, visibility 0.8s ease;
        }
        .loaded #loading-screen {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
        .logo-ring-container {
            position: relative;
            width: 90px;
            height: 90px;
        }
        @media (min-width: 768px) {
            .logo-ring-container {
                width: 120px;
                height: 120px;
            }
        }
        .logo-ring-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            animation: pulse 2.8s infinite ease-in-out;
        }
        .rotating-ring {
            position: absolute;
            inset: -12px;
            border: 4px solid transparent;
            border-top-color: #3b82f6;
            border-right-color: #60a5fa;
            border-radius: 50%;
            animation: spin 7s linear infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.07); }
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="h-full bg-gray-50">

<!-- Loading Screen -->
<div id="loading-screen">
    <div class="logo-ring-container">
        <img 
            src="imageuploads/logo.png" 
            alt="GCA Logo" 
            class="rounded-full"
            onerror="this.src='imageuploads/default_logo.png';"
        />
        <div class="rotating-ring"></div>
    </div>
</div>

<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="main-content" style="padding-top: 80px;">
    <div class="p-8 min-h-screen">
        <div class="max-w-7xl mx-auto">
            <div class="mb-8 flex justify-between items-center flex-wrap gap-4">
                <div>
                    <h1 class="text-4xl font-bold text-gray-800">User Management</h1>
                    <p class="text-gray-600 mt-2">Control permissions and status for all system users</p>
                </div>
                <div class="flex gap-3">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-1 flex">
                        <button onclick="setView('grid')" id="gridViewBtn" class="px-4 py-2 rounded-md transition <?= (!isset($_GET['view']) || $_GET['view'] === 'grid') ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                            Grid
                        </button>
                        <button onclick="setView('list')" id="listViewBtn" class="px-4 py-2 rounded-md transition <?= (isset($_GET['view']) && $_GET['view'] === 'list') ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                            List
                        </button>
                    </div>
                    <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition flex items-center gap-2">
                        Add New User
                    </button>
                </div>
            </div>

            <?php if ($success_message): ?>
                <script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: '<?= addslashes($success_message) ?>',
                        timer: 3000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end',
                        customClass: { popup: 'swal2-modern-success' }
                    });
                </script>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: '<?= addslashes($error_message) ?>',
                        timer: 4000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end',
                        customClass: { popup: 'swal2-modern-error' }
                    });
                </script>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Total Users</p>
                            <p class="text-3xl font-bold mt-2"><?= $stats['total_users'] ?></p>
                        </div>
                        <i class="fas fa-users text-4xl opacity-30"></i>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Admins</p>
                            <p class="text-3xl font-bold mt-2"><?= $stats['total_admins'] ?></p>
                        </div>
                        <i class="fas fa-user-shield text-4xl opacity-30"></i>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Teachers</p>
                            <p class="text-3xl font-bold mt-2"><?= $stats['total_teachers'] ?></p>
                        </div>
                        <i class="fas fa-chalkboard-teacher text-4xl opacity-30"></i>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Students</p>
                            <p class="text-3xl font-bold mt-2"><?= $stats['total_students'] ?></p>
                        </div>
                        <i class="fas fa-user-graduate text-4xl opacity-30"></i>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Parents</p>
                            <p class="text-3xl font-bold mt-2"><?= $stats['total_parents'] ?></p>
                        </div>
                        <i class="fas fa-user-friends text-4xl opacity-30"></i>
                    </div>
                </div>
            </div>

            <!-- Search & Filter -->
            <form method="get" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, username or email..." class="px-6 py-4 border border-gray-300 rounded-xl focus:ring-4 focus:ring-blue-200 text-lg">
                    <select name="role" class="px-6 py-4 border border-gray-300 rounded-xl text-lg">
                        <option value="">All Roles</option>
                        <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="teacher" <?= $role_filter === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                        <option value="parent" <?= $role_filter === 'parent' ? 'selected' : '' ?>>Parent</option>
                        <option value="student" <?= $role_filter === 'student' ? 'selected' : '' ?>>Student</option>
                        <option value="marketing" <?= $role_filter === 'marketing' ? 'selected' : '' ?>>Marketing</option>
                        <option value="accounts" <?= $role_filter === 'accounts' ? 'selected' : '' ?>>Finance</option>
                    </select>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-4 rounded-xl font-semibold transition flex items-center justify-center gap-3">
                        Filter
                    </button>
                </div>
            </form>

            <!-- Users Grid/List View -->
            <?php if ($users->num_rows === 0): ?>
                <div class="text-center py-20 bg-white rounded-2xl shadow-sm border border-gray-200">
                    <p class="text-2xl text-gray-600">No users found</p>
                </div>
            <?php else: ?>
                <?php $view_mode = $_GET['view'] ?? 'grid'; ?>
                <?php if ($view_mode === 'list'): ?>
                    <!-- List View -->
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gradient-to-r from-blue-600 to-blue-700 text-white">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-sm font-semibold">Photo</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold">Name</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold">Username</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold">Email</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold">Phone</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold">Role</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold">Status</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php mysqli_data_seek($users, 0); while ($u = $users->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4">
                                                <?php if ($u['photo'] && file_exists($u['photo'])): ?>
                                                    <img src="<?= htmlspecialchars($u['photo']) ?>" class="w-12 h-12 rounded-full object-cover">
                                                <?php else: ?>
                                                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 font-bold">
                                                        <?= strtoupper(substr($u['firstName'],0,1).substr($u['lastName'],0,1)) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <a href="view_user_details.php?user_id=<?= $u['user_id'] ?>" class="text-blue-600 hover:text-blue-800 font-semibold hover:underline">
                                                    <?= htmlspecialchars($u['firstName'] . ' ' . $u['lastName']) ?>
                                                </a>
                                            </td>
                                            <td class="px-6 py-4 text-gray-700">@<?= htmlspecialchars($u['username']) ?></td>
                                            <td class="px-6 py-4 text-gray-700"><?= htmlspecialchars($u['email']) ?></td>
                                            <td class="px-6 py-4 text-gray-700"><?= htmlspecialchars($u['phone'] ?: '-') ?></td>
                                            <td class="px-6 py-4">
                                                <span class="inline-block px-3 py-1 rounded-full text-xs font-bold
                                                    <?= $u['role']=='admin' ? 'bg-red-100 text-red-800' : ($u['role']=='teacher' ? 'bg-blue-100 text-blue-800' : ($u['role']=='parent' ? 'bg-purple-100 text-purple-800' : ($u['role']=='student' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'))) ?>">
                                                    <?= ucfirst($u['role']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?= $u['status']=='active'?'bg-green-100 text-green-800':'bg-red-100 text-red-800' ?>">
                                                    <?= ucfirst($u['status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex gap-2">
                                                    <a href="view_user_details.php?user_id=<?= $u['user_id'] ?>" class="text-blue-600 hover:text-blue-800" title="View">
                                                        View
                                                    </a>
                                                    <form method="post" class="inline">
                                                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <button type="submit" class="text-gray-600 hover:text-gray-800" title="Toggle Status" onclick="return confirmAction('toggle_status', <?= $u['user_id'] ?>)">
                                                            Toggle
                                                        </button>
                                                    </form>
                                                    <form method="post" class="inline" onsubmit="return confirmAction('delete_user', <?= $u['user_id'] ?>)">
                                                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <button type="submit" class="text-red-600 hover:text-red-800" title="Delete">
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Grid View -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                        <?php mysqli_data_seek($users, 0); while ($u = $users->fetch_assoc()): ?>
                            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden card-hover transition-all duration-300">
                                <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-6 text-center">
                                    <?php if ($u['photo'] && file_exists($u['photo'])): ?>
                                        <img src="<?= htmlspecialchars($u['photo']) ?>" class="w-24 h-24 rounded-full mx-auto border-4 border-white shadow-xl object-cover">
                                    <?php else: ?>
                                        <div class="w-24 h-24 bg-white bg-opacity-20 rounded-full mx-auto flex items-center justify-center text-4xl font-bold">
                                            <?= strtoupper(substr($u['firstName'],0,1).substr($u['lastName'],0,1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <h3 class="text-xl font-bold mt-4"><?= htmlspecialchars($u['firstName'] . ' ' . $u['lastName']) ?></h3>
                                    <p class="text-blue-100">@<?= htmlspecialchars($u['username']) ?></p>
                                </div>
                                <div class="p-6 space-y-4">
                                    <div class="text-center">
                                        <span class="inline-block px-4 py-2 rounded-full text-sm font-bold
                                            <?= $u['role']=='admin' ? 'bg-red-100 text-red-800' : ($u['role']=='teacher' ? 'bg-blue-100 text-blue-800' : ($u['role']=='parent' ? 'bg-purple-100 text-purple-800' : ($u['role']=='student' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'))) ?>">
                                            <?= ucfirst($u['role']) ?>
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-600 space-y-2">
                                        <p><strong>Email:</strong> <?= htmlspecialchars($u['email']) ?></p>
                                        <?php if ($u['phone']): ?>
                                            <p><strong>Phone:</strong> <?= htmlspecialchars($u['phone']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center justify-center gap-2">
                                        <span class="text-sm <?= $u['status']=='active'?'text-green-600':'text-red-600' ?> font-medium">
                                            <?= ucfirst($u['status']) ?>
                                        </span>
                                    </div>
                                    <a href="view_user_details.php?user_id=<?= $u['user_id'] ?>" class="block w-full bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-2 rounded-lg text-sm font-medium transition text-center">
                                        View Full Details
                                    </a>
                                    <div class="flex gap-2">
                                        <form method="post" class="flex-1">
                                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <button type="submit" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-lg text-sm font-medium transition" onclick="return confirmAction('toggle_status', <?= $u['user_id'] ?>)">
                                                Toggle
                                            </button>
                                        </form>
                                        <form method="post" class="flex-1" onsubmit="return confirmAction('delete_user', <?= $u['user_id'] ?>)">
                                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                            <input type="hidden" name="action" value="delete_user">
                                            <button type="submit" class="w-full bg-red-100 hover:bg-red-200 text-red-700 px-3 py-2 rounded-lg text-sm font-medium transition">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                    <form method="post">
                                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                        <input type="hidden" name="action" value="change_role">
                                        <select name="new_role" onchange="this.form.submit()" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                                            <option value="admin" <?= $u['role']=='admin'?'selected':'' ?>>Admin</option>
                                            <option value="teacher" <?= $u['role']=='teacher'?'selected':'' ?>>Teacher</option>
                                            <option value="parent" <?= $u['role']=='parent'?'selected':'' ?>>Parent</option>
                                            <option value="student" <?= $u['role']=='student'?'selected':'' ?>>Student</option>
                                        </select>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add User Modal (unchanged) -->
<div id="addUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="display: none;">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-screen overflow-y-auto">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-4 flex justify-between items-center rounded-t-lg">
            <h3 class="text-xl font-bold">Add New User</h3>
            <button onclick="closeAddModal()" class="text-white hover:text-gray-200">
                Close
            </button>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="add_user">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div><label class="block text-gray-700 font-semibold mb-2">Username *</label><input type="text" name="username" required class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
                <div><label class="block text-gray-700 font-semibold mb-2">Email *</label><input type="email" name="email" required class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div><label class="block text-gray-700 font-semibold mb-2">First Name *</label><input type="text" name="firstName" required class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
                <div><label class="block text-gray-700 font-semibold mb-2">Last Name *</label><input type="text" name="lastName" required class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
            </div>
            <div class="mb-4"><label class="block text-gray-700 font-semibold mb-2">Password * (min. 6 chars)</label><input type="password" name="password" required minlength="6" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Role *</label>
                    <select name="role" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                        <option value="parent">Parent</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Gender *</label>
                    <select name="gender" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Date of Birth</label>
                    <input type="date" name="dob" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div><label class="block text-gray-700 font-semibold mb-2">Phone</label><input type="text" name="phone" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
                <div><label class="block text-gray-700 font-semibold mb-2">ID Number</label><input type="text" name="IDNumber" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div><label class="block text-gray-700 font-semibold mb-2">House/Plot Number</label><input type="text" name="physicalAddress" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
                <div><label class="block text-gray-700 font-semibold mb-2">Street Name</label><input type="text" name="streetName" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div><label class="block text-gray-700 font-semibold mb-2">City/District</label><input type="text" name="city" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
                <div><label class="block text-gray-700 font-semibold mb-2">Postal Code</label><input type="text" name="postalCode" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
                <div><label class="block text-gray-700 font-semibold mb-2">Country</label><input type="text" name="country" value="Lesotho" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeAddModal()" class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2 px-6 rounded-lg">Cancel</button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg">Add User</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddModal() {
        document.getElementById('addUserModal').style.display = 'flex';
    }
    function closeAddModal() {
        document.getElementById('addUserModal').style.display = 'none';
    }
    document.getElementById('addUserModal').addEventListener('click', function(e) {
        if (e.target === this) closeAddModal();
    });
    function setView(view) {
        const url = new URL(window.location);
        url.searchParams.set('view', view);
        window.location = url;
    }

    // Hide loading screen when ready
    window.addEventListener('load', function () {
        document.body.classList.add('loaded');
    });
    setTimeout(() => {
        document.body.classList.add('loaded');
    }, 5000);

    // SweetAlert2 Confirmation for Status Changes
    function confirmAction(action, userId) {
        let title = '';
        let text = '';
        let icon = 'warning';
        let confirmButtonText = 'Yes';
        let confirmButtonColor = '#10b981';

        switch(action) {
            case 'approve':
                title = 'Approve User?';
                text = 'This will activate the user account.';
                break;
            case 'reject':
                title = 'Reject User?';
                text = 'This will reject the registration request.';
                break;
            case 'waitlist':
                title = 'Add to Waitlist?';
                text = 'This will move the user to the waitlist.';
                break;
            case 'delete':
                title = 'Delete User?';
                text = 'This action cannot be undone!';
                icon = 'error';
                confirmButtonText = 'Yes, Delete';
                confirmButtonColor = '#ef4444';
                break;
            case 'toggle_status':
                title = 'Change Status?';
                text = 'This will toggle the user between active/inactive.';
                break;
            default:
                return;
        }

        Swal.fire({
            title: title,
            text: text,
            icon: icon,
            showCancelButton: true,
            confirmButtonColor: confirmButtonColor,
            cancelButtonColor: '#6b7280',
            confirmButtonText: confirmButtonText,
            reverseButtons: true,
            customClass: {
                popup: 'swal2-modern-confirm',
                confirmButton: 'px-6 py-3 font-medium rounded-xl',
                cancelButton: 'px-6 py-3 font-medium rounded-xl'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Perform the action via URL redirect (same as before)
                window.location.href = `approve_users.php?action=${action}&user_id=${userId}`;
            }
        });
    }

    // Show success/error toasts after redirect
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'Action completed successfully!',
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end',
                customClass: { popup: 'swal2-modern-success' }
            });
        }
    });
</script>

<style>
    .swal2-modern-confirm, .swal2-modern-success {
        border-radius: 16px !important;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1) !important;
    }
    .swal2-modern-success {
        background: #ecfdf5 !important;
        color: #065f46 !important;
    }
</style>

</body>
</html>