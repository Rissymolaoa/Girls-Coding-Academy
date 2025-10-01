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
<title>Parent View - Performance</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    body { display:flex; min-height:100vh; }
    .sidebar { width:250px; background:#343a40; color:white; padding:20px; }
    .sidebar a { display:block; color:white; padding:10px; text-decoration:none; border-radius:5px; }
    .sidebar a:hover, .sidebar a.active { background:#495057; }
    .sidebar img { width:90px; border-radius:50%; margin:0 auto 15px; display:block; border:2px solid #6c757d; }
    .main-content { flex:1; padding:20px; background:#f8f9fa; }
    .grade-cell {
        padding: 8px 12px; border-radius: 6px;
        color: white; font-weight: bold; display: inline-block;
        min-width: 45px; text-align: center; margin: 3px;
    }
    .progress { height:20px; border-radius:10px; margin-top:10px; }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <img src="admin.png" alt="Parent Picture">
    <h3 class="text-center">Parent Panel</h3>
    <a href="parents_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <a href="children.php"><i class="bi bi-people"></i> My Children</a>
    <a href="parent_view_attendance.php"><i class="bi bi-card-checklist"></i> Attendance</a>
    <a href="parent_view_performance.php" class="active"><i class="bi bi-graph-up"></i> Performance</a>
    <a href="parent_view_materials.php"><i class="bi bi-folder"></i> Materials</a>
    <a href="parent_messages.php"><i class="bi bi-envelope"></i> Messages</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<!-- Main Content -->
<div class="main-content">
    <h2><i class="bi bi-graph-up"></i> View Performance</h2>

    <!-- Child Selector -->
    <?php if (count($children) > 0): ?>
    <form method="get" class="mb-3">
        <div class="input-group" style="max-width:400px;">
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
    <?php else: ?>
        <div class="alert alert-warning">No children linked to your account.</div>
    <?php endif; ?>

    <?php if ($student_id && count($children) > 0): ?>
        <?php
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

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title"><?= htmlspecialchars($batch['course_name'].' - '.$batch['batch_name']) ?></h5>

                <!-- Grades -->
                <div class="d-flex flex-wrap mb-3">
                    <?php foreach ($tests as $t): ?>
                        <div class="grade-cell" style="background-color: <?= gradeColor($grades[$t] ?? null) ?>;">
                            <?= htmlspecialchars($grades[$t] ?? '-') ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p><strong>Total Marks:</strong> <?= $total ?> / <?= $max_total ?></p>
                <p><strong>Overall %:</strong> <?= number_format($percentage, 2) ?>%</p>

                <div class="progress">
                    <div class="progress-bar" role="progressbar"
                        style="width: <?= $percentage ?>%; background-color: <?= gradeColor($percentage) ?>;"
                        aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                        <?= number_format($percentage, 1) ?>%
                    </div>
                </div>

                <!-- Chart -->
                <canvas id="chart-<?= $batch['batch_id'] ?>" class="mt-3" style="max-width:100%; height:250px;"></canvas>
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
                                borderColor: 'blue',
                                fill: false,
                                tension: 0.2
                            },
                            {
                                label: 'Batch Avg',
                                data: [<?= implode(',', array_map(fn($x)=>$avg[$x] ?? 0, ['t1','t2','t3','t4','t5','t6','t7','t8'])) ?>],
                                borderColor: 'orange',
                                fill: false,
                                tension: 0.2
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { position: 'top' } },
                        scales: { y: { beginAtZero: true, max: 100 } }
                    }
                });
                </script>
            </div>
        </div>

        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
