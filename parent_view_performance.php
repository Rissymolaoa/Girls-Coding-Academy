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
    if ($grade === null) return '#e5e7eb';
    if ($grade < 50) return '#ef4444';
    if ($grade < 70) return '#f59e0b';
    if ($grade < 90) return '#84cc16';
    return '#10b981';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Overview - Parent Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            height: 100vh;
            overflow: hidden;
            color: #1e293b;
        }

        .container-flex {
            display: flex;
            height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
            z-index: 1000;
        }

        .sidebar-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header img {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 12px;
            object-fit: cover;
        }

        .sidebar-header h3 {
            color: white;
            font-weight: 600;
            margin: 0;
            font-size: 1.1rem;
        }

        .sidebar a {
            color: #cbd5e1;
            padding: 12px 16px;
            margin: 6px 0;
            border-radius: 10px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: rgba(0, 217, 255, 0.2);
            color: #00d9ff;
        }

        .sidebar a i {
            width: 20px;
        }

        .content {
            flex: 1;
            padding: 40px;
            margin-left: 260px;
            overflow-y: auto;
            height: 100vh;
        }

        .content::-webkit-scrollbar {
            width: 8px;
        }

        .content::-webkit-scrollbar-thumb {
            background: #00d9ff;
            border-radius: 4px;
        }

        .header {
            margin-bottom: 35px;
            padding-bottom: 25px;
            border-bottom: 2px solid rgba(0, 217, 255, 0.3);
        }

        .header h2 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #1e293b 0%, #00d9ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        .header p {
            color: #64748b;
            margin: 0;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }

        .summary-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 217, 255, 0.2);
            backdrop-filter: blur(10px);
            text-align: center;
            transition: all 0.3s ease;
        }

        .summary-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0, 217, 255, 0.15);
        }

        .summary-icon {
            font-size: 3rem;
            margin-bottom: 12px;
            display: inline-block;
        }

        .summary-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .summary-label {
            color: #64748b;
            font-size: 0.95rem;
        }

        .child-selector {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 35px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 217, 255, 0.2);
        }

        .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 16px;
            font-weight: 500;
        }

        .form-select:focus {
            border-color: #00d9ff;
            box-shadow: 0 0 0 0.2rem rgba(0, 217, 255, 0.25);
        }

        .performance-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 217, 255, 0.2);
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }

        .performance-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 217, 255, 0.15);
        }

        .performance-header {
            background: linear-gradient(135deg, #00d9ff 0%, #0099cc 100%);
            color: white;
            padding: 24px;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .performance-body {
            padding: 30px;
        }

        .grades-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(70px, 1fr));
            gap: 10px;
            margin-bottom: 30px;
        }

        .grade-cell {
            padding: 14px;
            border-radius: 10px;
            color: white;
            font-weight: 700;
            text-align: center;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .grade-cell:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e2e8f0;
        }

        .stat-item strong {
            display: block;
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-item p {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }

        .progress-custom {
            height: 24px;
            border-radius: 12px;
            background: #e2e8f0;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .progress-bar-custom {
            height: 100%;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
            transition: width 0.3s ease;
        }

        .chart-container {
            position: relative;
            height: 350px;
            margin-top: 30px;
        }

        .no-child {
            text-align: center;
            padding: 60px 20px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .no-child i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .no-child p {
            color: #64748b;
            font-size: 1.1rem;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 220px;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .content {
                margin-left: 0;
                padding: 20px;
            }

            .header h2 {
                font-size: 1.5rem;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .grades-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
    </style>
</head>
<body>

<div class="container-flex">
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="<?= $parent_details['photo'] ?? 'default-avatar.png' ?>" alt="Parent" onerror="this.src='default-avatar.png'">
            <h3><?= htmlspecialchars($parent_details['firstName'] ?? 'Parent') ?></h3>
        </div>
        <a href="parents_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
        <a href="children.php"><i class="bi bi-people"></i> My Children</a>
        <a href="parent_view_attendance.php"><i class="bi bi-card-checklist"></i> Attendance</a>
        <a href="parent_view_performance.php" class="active"><i class="bi bi-graph-up"></i> Performance</a>
        <a href="parent_view_materials.php"><i class="bi bi-folder"></i> Materials</a>
        <a href="parent_messages.php"><i class="bi bi-envelope"></i> Messages</a>
        <a href="parent_profile.php"><i class="bi bi-person-circle"></i> Profile</a>
        <a href="parent_payments.php"><i class="bi bi-credit-card"></i> Payments</a>
        <a href="parent_invoices_print.php"><i class="bi bi-file-earmark"></i> Invoices</a>
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>

    <!-- Main Content -->
    <main class="content">
        <div class="header">
            <h2><i class="bi bi-graph-up"></i> Performance Overview</h2>
            <p>Monitor your children's academic progress and celebrate their achievements</p>
        </div>

        <?php if (count($children) > 0): ?>
            <!-- Summary Cards -->
            <div class="summary-grid">
                <?php if (count($children) >= 1): ?>
                    <?php $topChild = $children[0]; $topAvg = $child_performances[$topChild['student_id']]['average'] ?? 0; ?>
                    <div class="summary-card">
                        <div class="summary-icon"><i class="bi bi-trophy" style="color: #fbbf24;"></i></div>
                        <div class="summary-value"><?= number_format($topAvg, 1) ?>%</div>
                        <p class="summary-label">Top Performer: <?= htmlspecialchars($child_performances[$topChild['student_id']]['name']) ?></p>
                    </div>
                <?php endif; ?>
                <?php if (count($children) >= 2): ?>
                    <?php $bottomChild = end($children); $bottomAvg = $child_performances[$bottomChild['student_id']]['average'] ?? 0; ?>
                    <div class="summary-card">
                        <div class="summary-icon"><i class="bi bi-exclamation-circle" style="color: #ef4444;"></i></div>
                        <div class="summary-value"><?= number_format($bottomAvg, 1) ?>%</div>
                        <p class="summary-label">Needs Attention: <?= htmlspecialchars($child_performances[$bottomChild['student_id']]['name']) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Child Selector -->
            <div class="child-selector">
                <form method="get">
                    <label for="student_id" style="font-weight: 600; margin-bottom: 12px; display: block;">Select Child</label>
                    <select class="form-select" id="student_id" name="student_id" onchange="this.form.submit()">
                        <?php foreach ($children as $c): ?>
                            <option value="<?= $c['student_id'] ?>" <?= $c['student_id']==$student_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['firstName'].' '.$c['lastName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($student_id): 
                // Fetch batches and courses
                $sql_batches = "
                    SELECT DISTINCT b.batch_id, c.courseName, b.batch_code
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
                    $test_labels = ['Test 1', 'Test 2', 'Test 3', 'Test 4', 'Test 5', 'Test 6', 'Test 7', 'Final Exam'];
                    $total = 0;
                    $student_scores = [];
                    $batch_scores = [];

                    foreach ($tests as $i => $t) {
                        $student_scores[] = (int)($grades[$t] ?? 0);
                        $batch_scores[] = round((float)($avg[$i === 7 ? 't8' : 't' . ($i + 1)] ?? 0), 2);
                        $total += ($grades[$t] ?? 0);
                    }

                    $max_total = count($tests) * 100;
                    $percentage = ($max_total > 0) ? $total / $max_total * 100 : 0;
                    $chart_id = 'chart_' . $batch['batch_id'];
                ?>

                <div class="performance-card">
                    <div class="performance-header">
                        <i class="bi bi-book"></i> <?= htmlspecialchars($batch['courseName']) ?> - <?= htmlspecialchars($batch['batch_code']) ?>
                    </div>
                    <div class="performance-body">
                        <!-- Grade Cells -->
                        <div class="grades-grid">
                            <?php foreach ($tests as $i => $t): ?>
                                <div class="grade-cell" style="background-color: <?= gradeColor($grades[$t] ?? null) ?>;">
                                    <?= htmlspecialchars($grades[$t] ?? '-') ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Statistics -->
                        <div class="stats-row">
                            <div class="stat-item">
                                <strong>Total Marks</strong>
                                <p><?= $total ?> / <?= $max_total ?></p>
                            </div>
                            <div class="stat-item">
                                <strong>Overall Percentage</strong>
                                <p><?= number_format($percentage, 2) ?>%</p>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="progress-custom">
                            <div class="progress-bar-custom" style="width: <?= $percentage ?>%; background-color: <?= gradeColor($percentage) ?>;">
                                <?= number_format($percentage, 1) ?>%
                            </div>
                        </div>

                        <!-- Chart -->
                        <div class="chart-container">
                            <canvas id="<?= $chart_id ?>"></canvas>
                        </div>

                        <script>
                        (function() {
                            const ctx = document.getElementById('<?= $chart_id ?>').getContext('2d');
                            new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: <?= json_encode($test_labels) ?>,
                                    datasets: [
                                        {
                                            label: 'Your Scores',
                                            data: <?= json_encode($student_scores) ?>,
                                            borderColor: '#00d9ff',
                                            backgroundColor: 'rgba(0, 217, 255, 0.1)',
                                            borderWidth: 3,
                                            tension: 0.4,
                                            fill: true,
                                            pointRadius: 6,
                                            pointBackgroundColor: '#00d9ff',
                                            pointBorderColor: '#fff',
                                            pointBorderWidth: 2,
                                            pointHoverRadius: 8
                                        },
                                        {
                                            label: 'Batch Average',
                                            data: <?= json_encode($batch_scores) ?>,
                                            borderColor: '#fbbf24',
                                            backgroundColor: 'rgba(251, 191, 36, 0.05)',
                                            borderWidth: 2,
                                            tension: 0.4,
                                            fill: true,
                                            pointRadius: 5,
                                            pointBackgroundColor: '#fbbf24',
                                            pointBorderColor: '#fff',
                                            pointBorderWidth: 2,
                                            borderDash: [5, 5]
                                        }
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            position: 'top',
                                            labels: {
                                                font: { size: 12, weight: '600' },
                                                padding: 16,
                                                usePointStyle: true
                                            }
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            max: 100,
                                            ticks: {
                                                font: { size: 11 },
                                                callback: function(value) {
                                                    return value + '%';
                                                }
                                            },
                                            grid: { color: '#e2e8f0' }
                                        },
                                        x: {
                                            ticks: { font: { size: 11 } },
                                            grid: { display: false }
                                        }
                                    }
                                }
                            });
                        })();
                        </script>
                    </div>
                </div>

                <?php endforeach; ?>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-child">
                <i class="bi bi-graph-up"></i>
                <h3 style="color: #1e293b; margin: 16px 0; font-size: 1.5rem;">No Children Enrolled</h3>
                <p>Link a child to your account to view performance records.</p>
            </div>
        <?php endif; ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>