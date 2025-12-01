<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

include("db.php");

// Step 1: Get parent_id from parents table
$user_id = $_SESSION['user_id'];
$parent_sql = "SELECT parent_id FROM parents WHERE user_id = ?";
$stmt = $conn->prepare($parent_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$parent_result = $stmt->get_result();
$parent = $parent_result->fetch_assoc();
$parent_id = $parent['parent_id'] ?? 0;

// Fetch parent details for sidebar
$parent_details_sql = "SELECT u.firstName, u.lastName, p.photo FROM users u LEFT JOIN parents p ON u.user_id = p.user_id WHERE u.user_id = ?";
$stmt_details = $conn->prepare($parent_details_sql);
$stmt_details->bind_param("i", $user_id);
$stmt_details->execute();
$parent_details_result = $stmt_details->get_result();
$parent_details = $parent_details_result->fetch_assoc();

// Step 2: Get children linked to this parent_id
$children_sql = "
    SELECT s.student_id, u.firstName, u.lastName, u.gender, s.photo
    FROM students s
    INNER JOIN parent_students ps ON s.student_id = ps.student_id
    INNER JOIN users u ON s.user_id = u.user_id
    WHERE ps.parent_id = ?
";
$stmt = $conn->prepare($children_sql);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$children_result = $stmt->get_result();
$children = $children_result->fetch_all(MYSQLI_ASSOC);

// Step 3: Pick selected child (or default first)
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : ($children[0]['student_id'] ?? 0);

// Fetch materials count for summary
$materials_count = 0;
if ($student_id) {
    $count_sql = "SELECT COUNT(*) as total FROM materials m JOIN batches b ON m.batch_id = b.batch_id JOIN course_enrollments e ON e.batch_id = b.batch_id WHERE e.student_id = ?";
    $stmt_count = $conn->prepare($count_sql);
    $stmt_count->bind_param("i", $student_id);
    $stmt_count->execute();
    $count_result = $stmt_count->get_result()->fetch_assoc();
    $materials_count = $count_result['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Materials - Parent Dashboard | Girls Coding Academy</title>
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
        .materials-summary {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            text-align: center;
        }
        .materials-count {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        .child-selector {
            max-width: 400px;
            margin-bottom: 2rem;
        }
        .materials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }
        .material-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: none;
        }
        .material-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .material-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem;
        }
        .material-header h5 {
            margin: 0;
            font-weight: 600;
        }
        .material-body {
            padding: 1.5rem;
        }
        .material-meta {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        .material-description {
            color: var(--text-dark);
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }
        .btn-download {
            background: linear-gradient(135deg, var(--accent-color), #0891b2);
            border: none;
            border-radius: 8px;
            color: white;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
            width: 100%;
        }
        .btn-download:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
            color: white;
        }
        .no-materials {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
        }
        .no-materials i {
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
            .materials-grid { grid-template-columns: 1fr; }
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
                <a href="parent_view_materials.php" class="nav-link active" target="_blank"><i class="bi bi-folder"></i> Materials</a>
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
                <h1>Learning Materials</h1>
                <p>Access course notes, assignments, and resources for your child's studies.</p>
            </div>
        </header>

        <?php if (count($children) > 0): ?>
            <!-- Child Selector -->
            <div class="child-selector">
                <form method="get">
                    <div class="input-group">
                        <label class="input-group-text" for="student_id">Select Child</label>
                        <select class="form-select" id="student_id" name="student_id" onchange="this.form.submit()">
                            <?php foreach ($children as $c): ?>
                                <option value="<?= $c['student_id'] ?>" <?= $c['student_id']==$student_id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['firstName'].' '.$c['lastName']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <?php if ($student_id): 
                // Fetch materials for student's enrolled courses
                $sql = "
                    SELECT m.*, c.courseName, b.batch_code, u.firstName AS teacherFirst, u.lastName AS teacherLast
                    FROM materials m
                    JOIN batches b ON m.batch_id = b.batch_id
                    JOIN courses c ON b.course_id = c.course_id
                    JOIN course_enrollments e ON e.batch_id = b.batch_id
                    JOIN teachers t ON m.teacher_id = t.teacher_id
                    JOIN users u ON t.user_id = u.user_id
                    WHERE e.student_id = ?
                    ORDER BY m.uploaded_at DESC
                ";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $student_id);
                $stmt->execute();
                $materials_result = $stmt->get_result();
                $materials = $materials_result->fetch_all(MYSQLI_ASSOC);
            ?>

                <!-- Materials Summary -->
                <div class="materials-summary">
                    <i class="bi bi-folder-fill" style="font-size: 4rem; color: var(--primary-color); margin-bottom: 1rem;"></i>
                    <div class="materials-count"><?= $materials_count ?></div>
                    <p class="mb-0" style="color: var(--text-muted);">Total Resources Available</p>
                </div>

                <?php if (!empty($materials)): ?>
                    <div class="materials-grid">
                        <?php foreach ($materials as $m): ?>
                            <div class="material-card">
                                <div class="material-header">
                                    <h5><i class="bi bi-file-earmark-text"></i> <?= htmlspecialchars($m['title']) ?></h5>
                                </div>
                                <div class="material-body">
                                    <div class="material-meta">
                                        <i class="bi bi-book"></i> <?= htmlspecialchars($m['courseName'].' - '.$m['batch_code']) ?><br>
                                        <i class="bi bi-person"></i> <?= htmlspecialchars($m['teacherFirst'].' '.$m['teacherLast']) ?><br>
                                        <i class="bi bi-calendar"></i> <?= date("M d, Y", strtotime($m['uploaded_at'])) ?>
                                    </div>
                                    <div class="material-description">
                                        <?= nl2br(htmlspecialchars($m['description'])) ?>
                                    </div>
                                    <?php if (!empty($m['file_path'])): ?>
                                        <a href="<?= htmlspecialchars($m['file_path']) ?>" target="_blank" class="btn btn-download">
                                            <i class="bi bi-download"></i> Download Material
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-materials">
                        <i class="bi bi-folder-x"></i>
                        <h3>No Materials Yet</h3>
                        <p>Materials for this child will appear here once uploaded by teachers.</p>
                        <a href="parent_messages.php" class="btn btn-primary">Contact Teachers</a>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        <?php else: ?>
            <div class="no-materials">
                <i class="bi bi-folder"></i>
                <h3>No Children Enrolled</h3>
                <p>Link a child to your account to access learning materials.</p>
                <a href="children.php" class="btn btn-primary">Manage Children</a>
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