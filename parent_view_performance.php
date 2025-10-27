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

// Step 2: Fetch children linked to this parent
$children_sql = "
    SELECT s.student_id, u.firstName, u.lastName
    FROM parent_students ps
    JOIN students s ON ps.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    WHERE ps.parent_id = ?
";
$stmt = $conn->prepare($children_sql);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$children_result = $stmt->get_result();
$children = $children_result->fetch_all(MYSQLI_ASSOC);

// Compute overall performance for each child
$child_performances = [];
foreach ($children as $child) {
    $sql_grades = "
        SELECT ig.*, b.batch_id
        FROM internal_grades ig
        JOIN batches b ON ig.batch_id = b.batch_id
        WHERE ig.student_id = ?
    ";
    $stmt_grades_all = $conn->prepare($sql_grades);
    $stmt_grades_all->bind_param("i", $child['student_id']);
    $stmt_grades_all->execute();
    $all_grades = $stmt_grades_all->get_result()->fetch_all(MYSQLI_ASSOC);

    $total = 0;
    $count = 0;
    $tests = ['test_1','test_2','test_3','test_4','test_5','test_6','test_7','end_examination'];
    foreach ($all_grades as $grade) {
        foreach ($tests as $t) {
            if ($grade[$t] !== null) {
                $total += $grade[$t];
                $count++;
            }
        }
    }
    $average = $count > 0 ? $total / $count : 0;
    $child_performances[$child['student_id']] = [
        'name' => $child['firstName'] . ' ' . $child['lastName'],
        'average' => $average
    ];
}

// Sort children by performance descending
usort($children, function($a, $b) use ($child_performances) {
    $avgA = $child_performances[$a['student_id']]['average'] ?? 0;
    $avgB = $child_performances[$b['student_id']]['average'] ?? 0;
    return $avgB <=> $avgA;
});

// Step 3: Selected child
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : ($children[0]['student_id'] ?? 0);

// Step 4: Grade color function
function gradeColor($grade) {
    if ($grade === null) return 'lightgray';
    if ($grade < 50) return '#ff4d4d'; // red
    if ($grade < 70) return '#ffd633'; // yellow
    if ($grade < 90) return '#99e699'; // light green
    return '#33cc33'; // green
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Overview - Parent Dashboard | Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .page-header p {
            color: var(--text-muted);
            margin: 0;
            font-size: 1.1rem;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .top-performer .summary-icon { color: var(--success-color); }
        .bottom-performer .summary-icon { color: var(--danger-color); }
        .summary-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        .summary-label {
            color: var(--text-muted);
            font-size: 1rem;
        }
        .child-selector {
            max-width: 400px;
            margin-bottom: 2rem;
        }
        .performance-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            transition: transform 0.2s ease;
        }
        .performance-card:hover {
            transform: translateY(-2px);
        }
        .performance-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem;
        }
        .performance-body {
            padding: 1.5rem;
        }
        .grades-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(60px, 1fr));
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .grade-cell {
            padding: 0.75rem;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            text-align: center;
            font-size: 0.9rem;
        }
        .progress-custom {
            height: 20px;
            border-radius: 10px;
            background: var(--border-color);
            overflow: hidden;
        }
        .progress-bar-custom {
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            color: white;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 1.5rem;
        }
        .no-child {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
        }
        .no-child i {
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
            .summary-grid { grid-template-columns: 1fr; }
            .grades-grid { grid-template-columns: repeat(4, 1fr); }
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
                <a href="parent_view_performance.php" class="nav-link active"><i class="bi bi-graph-up"></i> Performance</a>
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
        <header class="page-header">
            <div>
                <h1>Performance Overview</h1>
                <p>Monitor your children's academic progress and celebrate their achievements.</p>
            </div>
        </header>

        <?php if (count($children) > 0): ?>
            <!-- Summary Cards -->
            <section class="summary-section">
                <h3 class="mb-3">Performance Summary</h3>
                <div class="summary-grid">
                    <?php if (count($children) >= 1): ?>
                        <?php $topChild = $children[0]; $topAvg = $child_performances[$topChild['student_id']]['average'] ?? 0; ?>
                        <div class="summary-card top-performer">
                            <div class="summary-icon"><i class="bi bi-trophy"></i></div>
                            <div class="summary-value"><?= number_format($topAvg, 1) ?>%</div>
                            <p class="summary-label">Top Performer: <?= htmlspecialchars($child_performances[$topChild['student_id']]['name']) ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if (count($children) >= 2): ?>
                        <?php $bottomChild = end($children); $bottomAvg = $child_performances[$bottomChild['student_id']]['average'] ?? 0; ?>
                        <div class="summary-card bottom-performer">
                            <div class="summary-icon"><i class="bi bi-arrow-down-circle"></i></div>
                            <div class="summary-value"><?= number_format($bottomAvg, 1) ?>%</div>
                            <p class="summary-label">Needs Attention: <?= htmlspecialchars($child_performances[$bottomChild['student_id']]['name']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

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
                // Fetch batches and courses
                $sql_batches = "
                    SELECT DISTINCT b.batch_id, c.courseName AS course_name, b.batch_code AS batch_name
                    FROM batches b
                    JOIN courses c ON b.course_id = c.course_id
                    JOIN course_enrollments e ON e.batch_id = b.batch_id
                    WHERE e.student_id = ?
                ";
                $stmt_batches = $conn->prepare($sql_batches);
                $stmt_batches->bind_param("i", $student_id);
                $stmt_batches->execute();
                $batches_result = $stmt_batches->get_result();
                $batches = $batches_result->fetch_all(MYSQLI_ASSOC);

                foreach ($batches as $batch):
                    // Fetch student grades
                    $sql_grades = "SELECT * FROM internal_grades WHERE student_id = ? AND batch_id = ?";
                    $stmt_grades = $conn->prepare($sql_grades);
                    $stmt_grades->bind_param("ii", $student_id, $batch['batch_id']);
                    $stmt_grades->execute();
                    $grades = $stmt_grades->get_result()->fetch_assoc();

                    // Fetch batch average
                    $sql_avg = "SELECT AVG(test_1) AS t1, AVG(test_2) AS t2, AVG(test_3) AS t3,
                                       AVG(test_4) AS t4, AVG(test_5) AS t5, AVG(test_6) AS t6,
                                       AVG(test_7) AS t7, AVG(end_examination) AS t8
                                FROM internal_grades WHERE batch_id = ?";
                    $stmt_avg = $conn->prepare($sql_avg);
                    $stmt_avg->bind_param("i", $batch['batch_id']);
                    $stmt_avg->execute();
                    $avg = $stmt_avg->get_result()->fetch_assoc();

                    $tests = ['test_1','test_2','test_3','test_4','test_5','test_6','test_7','end_examination'];
                    $total = 0;
                    foreach ($tests as $t) $total += $grades[$t] ?? 0;
                    $max_total = count($tests) * 100;
                    $percentage = ($max_total > 0) ? $total / $max_total * 100 : 0;
                ?>

                <div class="performance-card">
                    <div class="performance-header">
                        <h5><?= htmlspecialchars($batch['course_name'].' - '.$batch['batch_name']) ?></h5>
                    </div>
                    <div class="performance-body">
                        <!-- Grades -->
                        <div class="grades-grid">
                            <?php foreach ($tests as $t): ?>
                                <div class="grade-cell" style="background-color: <?= gradeColor($grades[$t] ?? null) ?>;">
                                    <?= htmlspecialchars($grades[$t] ?? '-') ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Total Marks:</strong> <?= $total ?> / <?= $max_total ?></p>
                                <p><strong>Overall %:</strong> <?= number_format($percentage, 2) ?>%</p>
                            </div>
                            <div class="col-md-6">
                                <div class="progress-custom">
                                    <div class="progress-bar-custom" style="width: <?= $percentage ?>%; background-color: <?= gradeColor($percentage) ?>;">
                                        <?= number_format($percentage, 1) ?>%
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Chart -->
                        <div class="chart-container">
                            <canvas id="chart-<?= $batch['batch_id'] ?>"></canvas>
                        </div>
                        <script>
                        const ctx<?= $batch['batch_id'] ?> = document.getElementById('chart-<?= $batch['batch_id'] ?>').getContext('2d');
                        new Chart(ctx<?= $batch['batch_id'] ?>, {
                            type: 'line',
                            data: {
                                labels: ['Test 1','Test 2','Test 3','Test 4','Test 5','Test 6','Test 7','End Exam'],
                                datasets: [
                                    {
                                        label: 'My Child',
                                        data: [<?= implode(',', array_map(fn($t)=>$grades[$t] ?? 0, $tests)) ?>],
                                        borderColor: 'rgb(99, 102, 241)',
                                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                                        tension: 0.4,
                                        fill: true
                                    },
                                    {
                                        label: 'Batch Avg',
                                        data: [<?= implode(',', array_map(fn($x)=>round($avg[$x] ?? 0, 2), ['t1','t2','t3','t4','t5','t6','t7','t8'])) ?>],
                                        borderColor: 'rgb(245, 158, 11)',
                                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                                        tension: 0.4,
                                        fill: true
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { 
                                    legend: { position: 'top' },
                                    title: { display: true, text: 'Performance vs Batch Average' }
                                },
                                scales: { 
                                    y: { 
                                        beginAtZero: true, 
                                        max: 100,
                                        grid: { color: var(--border-color) }
                                    },
                                    x: { grid: { color: var(--border-color) } }
                                }
                            }
                        });
                        </script>
                    </div>
                </div>

                <?php endforeach; ?>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-child">
                <i class="bi bi-graph-up"></i>
                <h3>No Children Enrolled</h3>
                <p>Link a child to your account to view performance records.</p>
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