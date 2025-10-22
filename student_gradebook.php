<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

include("db.php");

// Get student_id from user_id
$user_id = $_SESSION['user_id'];
$stmt_student = $conn->prepare("SELECT student_id, photo FROM students WHERE user_id = ?");
$stmt_student->bind_param("i", $user_id);
$stmt_student->execute();
$result_student = $stmt_student->get_result();

if ($result_student->num_rows > 0) {
    $student = $result_student->fetch_assoc();
    $student_id = $student['student_id'];
    $student_photo = $student['photo'];
} else {
    die("Student not found.");
}

// Fetch batches student is enrolled in
$sql_batches = "SELECT DISTINCT b.batch_id, c.courseName AS course_name, b.batch_code AS batch_name
                FROM batches b
                JOIN courses c ON b.course_id = c.course_id
                JOIN course_enrollments e ON e.batch_id = b.batch_id
                WHERE e.student_id = ?";
$stmt_batches = $conn->prepare($sql_batches);
$stmt_batches->bind_param("i", $student_id);
$stmt_batches->execute();
$result_batches = $stmt_batches->get_result();
$batches = $result_batches->fetch_all(MYSQLI_ASSOC);

// Function to get color based on grade
function gradeColor($grade) {
    if ($grade === null) return 'lightgray';
    if ($grade < 50) return '#ff4d4d';
    if ($grade < 70) return '#ffd633';
    if ($grade < 90) return '#99e699';
    return '#33cc33';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Student Dashboard - Grades</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
<style>
    /* Modern Flex Layout */
    body, html {
        margin: 0; padding: 0; height: 100%;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        overflow: hidden;
    }
    .container-flex {
        display: flex;
        height: 100vh;
        overflow: hidden;
    }
    /* Main Content Styles */
    .main-content {
        flex: 1;
        margin-left: 280px;
        padding: 40px 50px;
        overflow-y: auto;
        height: 100vh;
    }
    h2 {
        margin-bottom: 30px;
        font-weight: 700;
        color: #2c3e50;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    }
    /* Batch Card styles */
    .batch-card {
        margin-bottom: 30px;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(44, 62, 80, 0.1);
        padding: 30px;
        background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        border: 1px solid #e9ecef;
    }
    .batch-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #3498db, #2980b9);
    }
    .batch-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(52, 73, 94, 0.15);
    }
    .batch-card h5 {
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 20px;
        letter-spacing: 0.03em;
        font-size: 1.3rem;
    }
    /* Grades display */
    .grade-cell {
        padding: 12px 16px;
        border-radius: 10px;
        color: white;
        font-weight: 700;
        display: inline-block;
        min-width: 55px;
        text-align: center;
        margin: 5px 8px 5px 0;
        transition: all 0.2s ease;
        user-select: none;
        cursor: default;
        font-size: 1rem;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .grade-cell:hover {
        transform: scale(1.1);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    /* Progress bar */
    .progress {
        height: 25px;
        border-radius: 15px;
        overflow: hidden;
        margin-top: 15px;
        background: #ecf0f1;
        box-shadow: inset 0 2px 5px rgba(255,255,255,0.6);
    }
    .progress-bar {
        line-height: 25px;
        font-weight: 700;
        color: #fff;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        font-size: 1rem;
        transition: width 0.8s ease;
        border-radius: 15px;
    }
    /* Chart canvas */
    .batch-chart {
        max-width: 100%;
        height: 280px;
        margin-top: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        border: 1px solid #e9ecef;
        background: white;
    }
    .no-grades {
        text-align: center;
        color: #7f8c8d;
        font-style: italic;
        padding: 50px;
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(44, 62, 80, 0.1);
    }
    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            padding: 20px;
        }
        .batch-card {
            padding: 20px;
        }
        .grade-cell {
            min-width: 45px;
            font-size: 0.9rem;
            margin: 3px 5px 3px 0;
        }
        .batch-chart {
            height: 250px;
        }
    }
</style>
</head>
<body>

<div class="container-flex">
    <!-- Include the consistent navigation -->
    <?php include("student_navigation.php"); ?>

    <main class="main-content" role="main">
        <h2><i class="bi bi-graph-up"></i> My Performance</h2>
        <p class="mb-4">Track your progress and compare with batch averages.</p>

        <?php if (empty($batches)): ?>
            <div class="no-grades">No enrolled courses found. <a href="enroll.php">Enroll now</a> to start tracking performance.</div>
        <?php else: ?>
        <?php foreach ($batches as $batch): 
            $sql_grades = "SELECT * FROM internal_grades WHERE student_id = ? AND batch_id = ?";
            $stmt_grades = $conn->prepare($sql_grades);
            $stmt_grades->bind_param("ii", $student_id, $batch['batch_id']);
            $stmt_grades->execute();
            $result_grades = $stmt_grades->get_result();
            $grades = $result_grades->fetch_assoc();

            $tests = ['test_1','test_2','test_3','test_4','test_5','test_6','test_7','end_examination'];
            $total = 0;
            foreach ($tests as $t) $total += $grades[$t] ?? 0;
            $max_total = count($tests) * 100;
            $percentage = ($max_total > 0) ? $total / $max_total * 100 : 0;

            $sql_avg = "SELECT AVG(test_1) AS t1, AVG(test_2) AS t2, AVG(test_3) AS t3, 
                                AVG(test_4) AS t4, AVG(test_5) AS t5, AVG(test_6) AS t6,
                                AVG(test_7) AS t7, AVG(end_examination) AS t8
                        FROM internal_grades WHERE batch_id = ?";
            $stmt_avg = $conn->prepare($sql_avg);
            $stmt_avg->bind_param("i", $batch['batch_id']);
            $stmt_avg->execute();
            $result_avg = $stmt_avg->get_result();
            $avg = $result_avg->fetch_assoc();
        ?>

        <div class="batch-card">
            <div class="card-body">
                <h5 class="card-title"><?= htmlspecialchars($batch['course_name'] . " - " . $batch['batch_name']) ?></h5>

                <div class="d-flex flex-wrap mb-3 justify-content-center">
                    <?php foreach ($tests as $t): ?>
                        <div class="grade-cell" style="background-color: <?= gradeColor($grades[$t] ?? null) ?>">
                            <?= htmlspecialchars($grades[$t] ?? '-') ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p class="text-center mb-3"><strong>Total Marks:</strong> <?= $total ?> / <?= $max_total ?></p>
                <p class="text-center mb-4"><strong>Overall %:</strong> <?= number_format($percentage, 2) ?>%</p>

                <div class="progress mx-auto" style="width: 80%; max-width: 400px;" aria-label="Overall score progress bar">
                    <div class="progress-bar" role="progressbar" style="width: <?= $percentage ?>%; background-color: <?= gradeColor($percentage) ?>;" aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                        <?= number_format($percentage, 2) ?>%
                    </div>
                </div>

                <canvas id="chart-<?= $batch['batch_id'] ?>" class="batch-chart"></canvas>

                <script>
                    const ctx<?= $batch['batch_id'] ?> = document.getElementById('chart-<?= $batch['batch_id'] ?>').getContext('2d');
                    new Chart(ctx<?= $batch['batch_id'] ?>, {
                        type: 'line',
                        data: {
                            labels: ['Test 1','Test 2','Test 3','Test 4','Test 5','Test 6','Test 7','End Exam'],
                            datasets: [
                                {
                                    label: 'My Score',
                                    data: [<?= implode(',', array_map(function($t) use ($grades) { return $grades[$t] ?? 0; }, $tests)); ?>],
                                    borderColor: '#3498db',
                                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                                    fill: true,
                                    tension: 0.4
                                },
                                {
                                    label: 'Batch Average',
                                    data: [<?= implode(',', array_map(function($x) use ($avg) { return $avg[$x] ?? 0; }, ['t1','t2','t3','t4','t5','t6','t7','t8'])); ?>],
                                    borderColor: '#e74c3c',
                                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                                    fill: true,
                                    tension: 0.4
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            plugins: { 
                                legend: { 
                                    position: 'top',
                                    labels: {
                                        padding: 20
                                    }
                                } 
                            },
                            scales: { 
                                y: { 
                                    beginAtZero: true, 
                                    max: 100,
                                    grid: {
                                        color: '#e9ecef'
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    }
                                }
                            }
                        }
                    });
                </script>

            </div>
        </div>

        <?php endforeach; ?>
        <?php endif; ?>

    </main>
</div>

</body>
</html>