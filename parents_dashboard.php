<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

include("db.php");

$user_id = $_SESSION['user_id'];

// Fetch parent details from users and parents
$parent_sql = "SELECT u.*, p.photo as parent_photo 
               FROM users u 
               LEFT JOIN parents p ON u.user_id = p.user_id 
               WHERE u.user_id = ?";
$stmt = $conn->prepare($parent_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$parent_result = $stmt->get_result();
$parent = $parent_result->fetch_assoc();

// Get parent's record ID from parents table
$parent_record_sql = "SELECT parent_id FROM parents WHERE user_id = ?";
$stmt_record = $conn->prepare($parent_record_sql);
$stmt_record->bind_param("i", $user_id);
$stmt_record->execute();
$parent_record_result = $stmt_record->get_result();
$parent_record = $parent_record_result->fetch_assoc();
$parent_record_id = $parent_record ? $parent_record['parent_id'] : 0;

// Fetch children count
$children_count = 0;
if ($parent_record_id > 0) {
    $count_sql = "SELECT COUNT(*) as total FROM parent_students WHERE parent_id = ?";
    $stmt_count = $conn->prepare($count_sql);
    $stmt_count->bind_param("i", $parent_record_id);
    $stmt_count->execute();
    $count_result = $stmt_count->get_result()->fetch_assoc();
    $children_count = $count_result['total'];
}

// Fetch children details
$children = [];
if ($parent_record_id > 0) {
    $children_sql = "SELECT u.firstName, u.lastName, u.user_id, s.photo, ps.relationship 
                     FROM parent_students ps 
                     JOIN students s ON ps.student_id = s.student_id 
                     JOIN users u ON s.user_id = u.user_id 
                     WHERE ps.parent_id = ?";
    $stmt_children = $conn->prepare($children_sql);
    $stmt_children->bind_param("i", $parent_record_id);
    $stmt_children->execute();
    $children_result = $stmt_children->get_result();
    while ($child = $children_result->fetch_assoc()) {
        $children[] = $child;
    }
}

// Fetch recent notifications (example: last 5 for the parent or children)
$notifications_sql = "SELECT n.* FROM notifications n 
                      JOIN students s ON n.student_id = s.student_id 
                      JOIN parent_students ps ON s.student_id = ps.student_id 
                      WHERE ps.parent_id = ? 
                      ORDER BY n.date DESC LIMIT 5";
$notifications = [];
if ($parent_record_id > 0) {
    $stmt_notifs = $conn->prepare($notifications_sql);
    $stmt_notifs->bind_param("i", $parent_record_id);
    $stmt_notifs->execute();
    $notifs_result = $stmt_notifs->get_result();
    while ($notif = $notifs_result->fetch_assoc()) {
        $notifications[] = $notif;
    }
}

// Fetch recent attendance summary (for all children, last 7 days)
$attendance_summary = []; // Placeholder: Aggregate present/absent count
$recent_att_sql = "SELECT a.status, COUNT(*) as count 
                   FROM attendance a 
                   JOIN students s ON a.student_id = s.student_id 
                   JOIN parent_students ps ON s.student_id = ps.student_id 
                   WHERE ps.parent_id = ? AND a.session_id >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                   GROUP BY a.status";
if ($parent_record_id > 0) {
    $stmt_att = $conn->prepare($recent_att_sql);
    $stmt_att->bind_param("i", $parent_record_id);
    $stmt_att->execute();
    $att_result = $stmt_att->get_result();
    while ($row = $att_result->fetch_assoc()) {
        $attendance_summary[$row['status']] = $row['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - Girls Coding Academy</title>
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
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
        }
        .welcome-text {
            color: var(--text-muted);
            font-size: 1.1rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: none;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .stat-card .icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        .stat-card.primary .icon { background: rgba(99, 102, 241, 0.1); color: var(--primary-color); }
        .stat-card.info .icon { background: rgba(6, 182, 212, 0.1); color: var(--accent-color); }
        .stat-card.success .icon { background: rgba(34, 197, 94, 0.1); color: #10b981; }
        .stat-card.warning .icon { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .stat-card.danger .icon { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }
        .stat-label {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin: 0;
        }
        .recent-activity {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .activity-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1rem;
            color: white;
        }
        .activity-content h6 { margin: 0; font-weight: 600; }
        .activity-content p { margin: 0; color: var(--text-muted); font-size: 0.9rem; }
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
            transition: transform 0.2s ease;
        }
        .child-card:hover {
            transform: translateY(-4px);
        }
        .child-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .child-card-body {
            padding: 1.5rem;
            text-align: center;
        }
        .child-card h5 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .child-card p {
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        .btn-modern {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            color: white;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .btn-modern:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-260px); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .header h1 { font-size: 1.5rem; }
            .stats-grid { grid-template-columns: 1fr; }
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
                <a href="parents_dashboard.php" class="nav-link active" onclick="showSection('dashboard')"><i class="bi bi-house-door"></i> Dashboard</a>
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
                <a href="parents_chatting.php" class="nav-link" target="_blank"><i class="bi bi-chat"></i> Group Chat</a>
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
        <header class="header">
            <div>
                <h1>Welcome Back</h1>
                <p class="welcome-text mb-0">Here's what's happening with your child's progress today.</p>
            </div>
            <div>
                <span class="badge bg-primary fs-6">Active</span>
            </div>
        </header>

        <!-- Dashboard Section -->
        <section id="dashboard" class="section active">
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="icon"><i class="bi bi-people"></i></div>
                    <h3 class="stat-value"><?= $children_count ?></h3>
                    <p class="stat-label">Children Enrolled</p>
                </div>
                <div class="stat-card info">
                    <div class="icon"><i class="bi bi-check-circle"></i></div>
                    <h3 class="stat-value"><?= $attendance_summary['Present'] ?? 0 ?></h3>
                    <p class="stat-label">Recent Attendance</p>
                </div>
                <div class="stat-card success">
                    <div class="icon"><i class="bi bi-star-fill"></i></div>
                    <h3 class="stat-value">85%</h3>
                    <p class="stat-label">Avg Performance</p>
                </div>
                <div class="stat-card warning">
                    <div class="icon"><i class="bi bi-calendar-event"></i></div>
                    <h3 class="stat-value">3</h3>
                    <p class="stat-label">Upcoming Events</p>
                </div>
                <div class="stat-card danger">
                    <div class="icon"><i class="bi bi-exclamation-triangle"></i></div>
                    <h3 class="stat-value"><?= $attendance_summary['Absent'] ?? 0 ?></h3>
                    <p class="stat-label">Missed Sessions</p>
                </div>
                <div class="stat-card secondary">
                    <div class="icon"><i class="bi bi-bell"></i></div>
                    <h3 class="stat-value"><?= count($notifications) ?></h3>
                    <p class="stat-label">New Notifications</p>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity">
                <h3 class="mb-3"><i class="bi bi-clock-history"></i> Recent Activity</h3>
                <?php if (!empty($notifications)): ?>
                    <?php foreach (array_slice($notifications, 0, 5) as $notif): ?>
                        <div class="activity-item">
                            <div class="activity-icon bg-primary"><i class="bi bi-bell"></i></div>
                            <div class="activity-content flex-grow-1">
                                <h6><?= htmlspecialchars($notif['title']) ?></h6>
                                <p><?= htmlspecialchars($notif['description']) ?> <small class="text-muted">· <?= $notif['date'] ?></small></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">No recent activity. Check back soon!</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- Profile Section -->
        <section id="profile" class="section">
            <div class="row">
                <div class="col-12">
                    <div class="card stat-card p-4">
                        <h2 class="mb-4"><i class="bi bi-person-circle"></i> My Profile</h2>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Full Name:</strong> <?= htmlspecialchars($parent['firstName'] . ' ' . $parent['lastName']) ?></p>
                                <p><strong>Email:</strong> <?= htmlspecialchars($parent['email']) ?></p>
                                <p><strong>Phone:</strong> <?= htmlspecialchars($parent['phone']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Username:</strong> <?= htmlspecialchars($parent['username']) ?></p>
                                <p><strong>Role:</strong> Parent</p>
                                <a href="#" class="btn btn-modern">Edit Profile</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Children Section -->
        <section id="children" class="section">
            <h2 class="mb-4"><i class="bi bi-people"></i> My Children</h2>
            <?php if (!empty($children)): ?>
                <div class="children-grid">
                    <?php foreach ($children as $child): ?>
                        <div class="child-card">
                            <img src="<?= $child['photo'] ?? 'default-student-avatar.png' ?>" alt="Child Photo" onerror="this.src='default-avatar.png'">
                            <div class="child-card-body">
                                <h5><?= htmlspecialchars($child['firstName'] . ' ' . $child['lastName']) ?></h5>
                                <p>Relationship: <?= htmlspecialchars($child['relationship']) ?></p>
                                <a href="temp.php?user_id=<?= $child['user_id'] ?>" class="btn btn-modern">View Profile</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-people display-1 text-muted"></i>
                    <p class="text-muted mt-3">No children enrolled yet. <a href="#">Add a child</a></p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showSection(sectionId) {
            document.querySelectorAll('.section').forEach(sec => sec.classList.remove('active'));
            document.getElementById(sectionId).classList.add('active');
            document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
            event.target.classList.add('active');
        }

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