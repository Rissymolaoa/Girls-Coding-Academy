<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

include("db.php");

$parent_user_id = $_SESSION['user_id']; 
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch parent details for sidebar
$parent_sql_details = "SELECT u.firstName, u.lastName, p.photo FROM users u LEFT JOIN parents p ON u.user_id = p.user_id WHERE u.user_id = ?";
$stmt_details = $conn->prepare($parent_sql_details);
$stmt_details->bind_param("i", $parent_user_id);
$stmt_details->execute();
$parent_result_details = $stmt_details->get_result();
$parent_details = $parent_result_details->fetch_assoc();

// Validate that this student belongs to this parent
$check = $conn->prepare("
    SELECT s.student_id, u.firstName, u.lastName, u.gender, s.photo
    FROM students s
    INNER JOIN parent_students ps ON s.student_id = ps.student_id
    INNER JOIN parents p ON ps.parent_id = p.parent_id
    INNER JOIN users u ON s.user_id = u.user_id
    WHERE p.user_id = ? AND s.student_id = ?
");
$check->bind_param("ii", $parent_user_id, $student_id);
$check->execute();
$student_result = $check->get_result();
$student = $student_result->fetch_assoc();

if (!$student) {
    echo "You are not authorized to view this student.";
    exit();
}

// Fetch attendance records
$attendance_sql = $conn->prepare("
    SELECT a.session_id, a.status, a.marked_at, b.batch_code, c.courseName
    FROM attendance a
    INNER JOIN batches b ON a.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    WHERE a.student_id = ?
    ORDER BY a.session_id DESC
    LIMIT 10
");
$attendance_sql->bind_param("i", $student_id);
$attendance_sql->execute();
$attendance_result = $attendance_sql->get_result();
$attendance = $attendance_result->fetch_all(MYSQLI_ASSOC);

// Calculate attendance summary
$total_sessions = count($attendance);
$present_count = array_reduce($attendance, function($carry, $item) {
    return $item['status'] === 'Present' ? $carry + 1 : $carry;
}, 0);
$attendance_rate = $total_sessions > 0 ? round(($present_count / $total_sessions) * 100, 1) : 0;

// Fetch tasks (activities) for this student
$tasks_sql = $conn->prepare("
    SELECT a.activity_id, a.title, a.description, a.due_date, a.resource_file, c.courseName
    FROM activities a
    INNER JOIN batches b ON a.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    INNER JOIN course_enrollments ce ON b.batch_id = ce.batch_id
    WHERE ce.student_id = ? AND a.status = 'active'
    ORDER BY a.due_date ASC
");
$tasks_sql->bind_param("i", $student_id);
$tasks_sql->execute();
$tasks_result = $tasks_sql->get_result();
$tasks = $tasks_result->fetch_all(MYSQLI_ASSOC);

$today = date('Y-m-d');
$upcoming_tasks = [];
$overdue_tasks = [];

foreach ($tasks as $task) {
    if ($task['due_date'] >= $today) {
        $upcoming_tasks[] = $task;
    } else {
        $overdue_tasks[] = $task;
    }
}

// Fetch recent announcements (assuming for students)
$announcements_sql = "SELECT * FROM admin_announcements WHERE recipients = 'students' ORDER BY created_at DESC LIMIT 5";
$announcements_result = $conn->query($announcements_sql);
$announcements = $announcements_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($student['firstName'] . " " . $student['lastName']) ?> - Student Profile | Girls Coding Academy</title>
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
        .profile-header {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .profile-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-color);
        }
        .profile-info h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }
        .profile-info p {
            color: var(--text-muted);
            margin: 0;
        }
        .btn-modern {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 8px;
            color: white;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .summary-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.2s ease;
        }
        .summary-card:hover {
            transform: translateY(-4px);
        }
        .summary-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .summary-card.attendance .summary-icon { color: var(--accent-color); }
        .summary-card.tasks .summary-icon { color: var(--success-color); }
        .summary-card.overdue .summary-icon { color: var(--danger-color); }
        .summary-card.announcements .summary-icon { color: var(--warning-color); }
        .summary-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        .summary-label {
            color: var(--text-muted);
            font-size: 0.95rem;
        }
        .section-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .section-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem;
        }
        .section-body {
            padding: 1.5rem;
            max-height: 400px;
            overflow-y: auto;
        }
        .task-item {
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 0;
        }
        .task-item:last-child {
            border-bottom: none;
        }
        .task-title {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        .task-meta {
            font-size: 0.875rem;
            color: var(--text-muted);
        }
        .due-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
        }
        .due-upcoming { background: rgba(16, 185, 129, 0.1); color: var(--success-color); }
        .due-overdue { background: rgba(239, 68, 68, 0.1); color: var(--danger-color); }
        .announcement-item {
            padding: 1rem;
            border-left: 4px solid var(--warning-color);
            background: rgba(255, 193, 7, 0.05);
            margin-bottom: 1rem;
        }
        .announcement-item:last-child {
            margin-bottom: 0;
        }
        .table-custom {
            font-size: 0.9rem;
        }
        .table-custom th {
            background: var(--border-color);
            font-weight: 600;
        }
        .status-badge {
            font-size: 0.8rem;
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
            .profile-header { flex-direction: column; text-align: center; }
            .summary-grid { grid-template-columns: 1fr; }
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
            <img src="<?= $parent_details['photo'] ?? 'default-parent-avatar.png' ?>" alt="Parent Avatar" onerror="this.src='default-avatar.png'">
            <h3><?= htmlspecialchars($parent_details['firstName'] ?? 'Parent') ?></h3>
        </div>
        <ul class="nav flex-column p-0 m-0">
            <li class="nav-item">
                <a href="parents_dashboard.php" class="nav-link"><i class="bi bi-house-door"></i> Dashboard</a>
            </li>
            <li class="nav-item">
                <a href="children.php" class="nav-link"><i class="bi bi-people"></i> My Children</a>
            </li>
            <li class="nav-item">
                <a href="parent_view_attendance.php" class="nav-link"><i class="bi bi-card-checklist"></i> Attendance</a>
            </li>
            <li class="nav-item">
                <a href="parent_view_performance.php" class="nav-link"><i class="bi bi-graph-up"></i> Performance</a>
            </li>
            <li class="nav-item">
                <a href="parent_view_materials.php" class="nav-link"><i class="bi bi-folder"></i> Materials</a>
            </li>
            <li class="nav-item">
                <a href="parent_messages.php" class="nav-link"><i class="bi bi-envelope"></i> Messages</a>
            </li>
            <li class="nav-item">
                <a href="parents_chatting.php" class="nav-link"><i class="bi bi-chat"></i> Group Chat</a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content" id="main-content">
        <!-- Profile Header -->
        <div class="profile-header">
            <img src="<?= $student['photo'] ?: 'default_student.png' ?>" alt="Student Photo" class="profile-img">
            <div class="profile-info flex-grow-1">
                <h1><?= htmlspecialchars($student['firstName'] . " " . $student['lastName']) ?></h1>
                <p><strong>Gender:</strong> <?= htmlspecialchars($student['gender']) ?></p>
            </div>
            <a href="temp.php?id=<?= $student['student_id'] ?>" class="btn btn-modern">
                <i class="bi bi-eye"></i> Full Profile
            </a>
        </div>

        <!-- Summary Cards -->
        <section class="summary-section mb-4">
            <div class="summary-grid">
                <div class="summary-card attendance">
                    <div class="summary-icon"><i class="bi bi-calendar-check"></i></div>
                    <div class="summary-value"><?= $attendance_rate ?>%</div>
                    <p class="summary-label">Attendance Rate</p>
                </div>
                <div class="summary-card tasks">
                    <div class="summary-icon"><i class="bi bi-clock"></i></div>
                    <div class="summary-value"><?= count($upcoming_tasks) ?></div>
                    <p class="summary-label">Upcoming Tasks</p>
                </div>
                <div class="summary-card overdue">
                    <div class="summary-icon"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="summary-value"><?= count($overdue_tasks) ?></div>
                    <p class="summary-label">Overdue Tasks</p>
                </div>
                <div class="summary-card announcements">
                    <div class="summary-icon"><i class="bi bi-megaphone"></i></div>
                    <div class="summary-value"><?= count($announcements) ?></div>
                    <p class="summary-label">Recent Announcements</p>
                </div>
            </div>
        </section>

        <!-- Attendance Section -->
        <div class="section-card">
            <div class="section-header">
                <h5><i class="bi bi-calendar-check"></i> Recent Attendance</h5>
            </div>
            <div class="section-body">
                <?php if (count($attendance) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Course</th>
                                    <th>Batch</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance as $record): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($record['session_id']) ?></td>
                                        <td><?= htmlspecialchars($record['courseName']) ?></td>
                                        <td><?= htmlspecialchars($record['batch_code']) ?></td>
                                        <td>
                                            <?php 
                                                $status = $record['status'];
                                                $badgeClass = $status === 'Present' ? 'success' : ($status === 'Late' ? 'warning' : ($status === 'Sick' ? 'info' : 'danger'));
                                            ?>
                                            <span class="badge bg-<?= $badgeClass ?> status-badge"><?= htmlspecialchars($status) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No attendance records found.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tasks / Homeworks Section -->
        <div class="section-card">
            <div class="section-header">
                <h5><i class="bi bi-clipboard-check"></i> Tasks & Homeworks</h5>
            </div>
            <div class="section-body">
                <?php if (count($upcoming_tasks) > 0 || count($overdue_tasks) > 0): ?>
                    <?php if (count($upcoming_tasks) > 0): ?>
                        <h6 class="text-success mb-2"><i class="bi bi-clock"></i> Upcoming</h6>
                        <?php foreach ($upcoming_tasks as $task): ?>
                            <div class="task-item">
                                <div class="task-title"><?= htmlspecialchars($task['title']) ?></div>
                                <div class="task-meta">
                                    <small><?= htmlspecialchars($task['description']) ?></small><br>
                                    <span class="due-badge due-upcoming">Due: <?= htmlspecialchars($task['due_date']) ?></span><br>
                                    <em><?= htmlspecialchars($task['courseName']) ?></em>
                                    <?php if (!empty($task['resource_file'])): ?>
                                        <br>
                                        <a href="<?= htmlspecialchars($task['resource_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                                            <i class="bi bi-file-earmark-arrow-down"></i> View Resource
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (count($overdue_tasks) > 0): ?>
                        <h6 class="text-danger mb-2 mt-3"><i class="bi bi-exclamation-triangle"></i> Overdue</h6>
                        <?php foreach ($overdue_tasks as $task): ?>
                            <div class="task-item">
                                <div class="task-title"><?= htmlspecialchars($task['title']) ?></div>
                                <div class="task-meta">
                                    <small><?= htmlspecialchars($task['description']) ?></small><br>
                                    <span class="due-badge due-overdue">Due: <?= htmlspecialchars($task['due_date']) ?></span><br>
                                    <em><?= htmlspecialchars($task['courseName']) ?></em>
                                    <?php if (!empty($task['resource_file'])): ?>
                                        <br>
                                        <a href="<?= htmlspecialchars($task['resource_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                                            <i class="bi bi-file-earmark-arrow-down"></i> View Resource
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted">No tasks assigned yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Announcements Section -->
        <div class="section-card">
            <div class="section-header">
                <h5><i class="bi bi-megaphone"></i> Recent Announcements</h5>
            </div>
            <div class="section-body">
                <?php if (count($announcements) > 0): ?>
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="announcement-item">
                            <h6 class="mb-1"><?= htmlspecialchars($announcement['message']) ?></h6>
                            <small class="text-muted"><?= date("M d, Y H:i", strtotime($announcement['created_at'])) ?></small>
                            <?php if (!empty($announcement['file_path'])): ?>
                                <br>
                                <a href="<?= htmlspecialchars($announcement['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                                    <i class="bi bi-download"></i> Download Attachment
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">No recent announcements.</p>
                <?php endif; ?>
            </div>
        </div>
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