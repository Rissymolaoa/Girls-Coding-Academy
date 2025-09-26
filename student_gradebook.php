<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

include("db.php");

// Get student_id from user_id
$user_id = $_SESSION['user_id'];
$stmt_student = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt_student->bind_param("i", $user_id);
$stmt_student->execute();
$result_student = $stmt_student->get_result();

if ($result_student->num_rows > 0) {
    $student = $result_student->fetch_assoc();
    $student_id = $student['student_id'];
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
    <title>Student Dashboard - Grades</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { display: flex; min-height: 100vh; }
        .sidebar {
            min-width: 220px;
            max-width: 220px;
            background-color: #343a40;
            color: white;
            padding: 20px;
        }
        .sidebar a { color: white; display: block; margin: 10px 0; text-decoration: none; }
        .sidebar a.active { font-weight: bold; text-decoration: underline; }
        .main { flex: 1; padding: 20px; background-color: #f8f9fa; }
        .batch-card { margin-bottom: 25px; }
        .grade-cell {
            padding: 10px 12px;
            border-radius: 6px;
            color: white;
            font-weight: bold;
            display: inline-block;
            min-width: 45px;
            text-align: center;
            margin: 3px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .grade-cell:hover {
            transform: scale(1.1);
            box-shadow: 0px 0px 10px rgba(0,0,0,0.3);
        }
        .progress { height: 20px; border-radius: 10px; margin-top: 10px; }
        h5 { font-weight: bold; }
    </style>
</head>
<body>

<div class="sidebar">
    <img src="admin.png" alt="Student Picture" class="admin-pic">
    <h3 style="text-align:center;margin-bottom:10px;">Navigation</h3>
    <a href="student.php"><i class="bi bi-house-door"></i> Home</a>
     <a href="student_profile.php"><i class="bi bi-person-circle"></i> My Profile</a>
    <a href="student_courses.php"><i class="bi bi-journal-bookmark"></i> My Courses</a>
     <a href="#"><i class="bi bi-megaphone"></i> Announcements</a>
     <a href="#"><i class="bi bi-calendar-event"></i> My Calendar</a>
    <a href="attendance.php" class="active"><i class="bi bi-card-checklist"></i> My Schedule</a>
    <a href="student_marks.php"><i class="bi bi-bar-chart-line-fill"></i> My Grades</a> 
    <a href="student_gradebook.php"><i class="bi bi-graph-up"></i> My Performance</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<div class="main">
    <h2 class="mb-4">My Grades</h2>

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

    <div class="card batch-card shadow-sm">
        <div class="card-body">
            <h5 class="card-title"><?php echo htmlspecialchars($batch['course_name'] . " - " . $batch['batch_name']); ?></h5>

            <div class="d-flex flex-wrap mb-3">
                <?php foreach ($tests as $t): ?>
                    <div class="grade-cell" style="background-color: <?php echo gradeColor($grades[$t] ?? null); ?>">
                        <?php echo htmlspecialchars($grades[$t] ?? '-'); ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <p><strong>Total Marks:</strong> <?php echo $total; ?> / <?php echo $max_total; ?></p>
            <p><strong>Overall %:</strong> <?php echo number_format($percentage, 2); ?>%</p>

            <div class="progress">
                <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%; background-color: <?php echo gradeColor($percentage); ?>;" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                    <?php echo number_format($percentage, 2); ?>%
                </div>
            </div>

            <canvas id="chart-<?php echo $batch['batch_id']; ?>" class="mt-3" style="max-width: 100%; height: 250px;"></canvas>
            <script>
                const ctx<?php echo $batch['batch_id']; ?> = document.getElementById('chart-<?php echo $batch['batch_id']; ?>').getContext('2d');
                new Chart(ctx<?php echo $batch['batch_id']; ?>, {
                    type: 'line',
                    data: {
                        labels: ['Test 1','Test 2','Test 3','Test 4','Test 5','Test 6','Test 7','End Exam'],
                        datasets: [
                            {
                                label: 'My Score',
                                data: [<?php echo implode(',', array_map(function($t) use ($grades) { return $grades[$t] ?? 0; }, $tests)); ?>],
                                borderColor: 'blue',
                                fill: false,
                                tension: 0.2
                            },
                            {
                                label: 'Batch Average',
                                data: [<?php echo implode(',', array_map(function($x) use ($avg) { return $avg[$x] ?? 0; }, ['t1','t2','t3','t4','t5','t6','t7','t8'])); ?>],
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
</div>

</body>
</html>
