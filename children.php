<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

include("db.php");

$parent_id = $_SESSION['user_id']; // parent logged in, stored as user_id

// Fetch parent details for sidebar
$parent_query = $conn->prepare("SELECT u.firstName, u.lastName, p.photo FROM users u LEFT JOIN parents p ON u.user_id = p.user_id WHERE u.user_id = ?");
$parent_query->bind_param("i", $parent_id);
$parent_query->execute();
$parent_result = $parent_query->get_result();
$parent = $parent_result->fetch_assoc();

// Fetch children linked to this parent
$query = $conn->prepare("
    SELECT s.student_id, u.firstName, u.lastName, u.gender, s.photo
    FROM students s
    INNER JOIN parent_students ps ON s.student_id = ps.student_id
    INNER JOIN parents p ON ps.parent_id = p.parent_id
    INNER JOIN users u ON s.user_id = u.user_id
    WHERE p.user_id = ?
");
$query->bind_param("i", $parent_id);
$query->execute();
$result = $query->get_result();
$children = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Children - Parent Dashboard | Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --secondary-color: #8b5cf6;
            --accent-color: #06b6d4;
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
        .page-header p {
            color: var(--text-muted);
            margin: 0;
            font-size: 1.1rem;
        }
        .children-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        .child-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: none;
        }
        .child-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .child-img {
            width: 100%;
            height: 220px;
            object-fit: cover;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        .child-img-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
            font-weight: 300;
        }
        .child-card-body {
            padding: 1.5rem;
            text-align: center;
        }
        .child-card h5 {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }
        .child-card p {
            color: var(--text-muted);
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }
        .btn-modern {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            color: white;
            font-weight: 500;
            transition: all 0.2s ease;
            width: 100%;
        }
        .btn-modern:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
            color: white;
        }
        .no-children {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
        }
        .no-children i {
            font-size: 5rem;
            display: block;
            margin-bottom: 1rem;
            opacity: 0.5;
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
            .children-grid { grid-template-columns: 1fr; }
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
            <img src="<?= $parent['photo'] ?? 'default-parent-avatar.png' ?>" alt="Parent Avatar" onerror="this.src='default-avatar.png'">
            <h3><?= htmlspecialchars($parent['firstName'] ?? 'Parent') ?></h3>
        </div>
        <ul class="nav flex-column p-0 m-0">
            <li class="nav-item">
                <a href="parents_dashboard.php" class="nav-link" onclick="showSection('dashboard')"><i class="bi bi-house-door"></i> Dashboard</a>
            </li>
            <li class="nav-item">
                <a href="children.php" class="nav-link active" onclick="showSection('children')"><i class="bi bi-people"></i> My Children</a>
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
                <a href="parent_profile.php" class="nav-link" onclick="showSection('profile')"><i class="bi bi-person-circle"></i> Profile</a>
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
                <h1>My Children</h1>
                <p>View and manage your children's profiles and progress.</p>
            </div>
            <div>
                <span class="badge bg-primary fs-6"><?= count($children) ?> Child<?= count($children) !== 1 ? 'ren' : '' ?></span>
            </div>
        </header>

        <?php if (count($children) > 0): ?>
            <div class="children-grid">
                <?php foreach ($children as $child): ?>
                    <div class="child-card">
                        <?php if ($child['photo']): ?>
                            <img src="<?= htmlspecialchars($child['photo']) ?>" class="child-img" alt="<?= htmlspecialchars($child['firstName'] . ' ' . $child['lastName']) ?>">
                        <?php else: ?>
                            <div class="child-img child-img-placeholder">
                                <i class="bi bi-person"></i>
                            </div>
                        <?php endif; ?>
                        <div class="child-card-body">
                            <h5><?= htmlspecialchars($child['firstName'] . ' ' . $child['lastName']) ?></h5>
                            <p><i class="bi bi-gender-<?= strtolower($child['gender']) === 'male' ? 'male' : 'female' ?>"></i> <?= ucfirst(htmlspecialchars($child['gender'])) ?></p>
                            <a href="student_parent_profile.php?id=<?= $child['student_id'] ?>" class="btn btn-modern">
                                <i class="bi bi-eye"></i> View Profile
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-children">
                <i class="bi bi-people"></i>
                <h3>No Children Enrolled</h3>
                <p>Your children will appear here once they are linked to your account. Contact administration to add them.</p>
                <a href="parent_settings.php" class="btn btn-modern">Manage Account</a>
            </div>
        <?php endif; ?>
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