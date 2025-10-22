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

// Fetch batches student is enrolled in along with batch averages of tests
$sql_batches = "SELECT DISTINCT b.batch_id, c.courseName AS course_name, b.batch_code AS batch_name,
                       AVG(ig.test_1 + ig.test_2 + ig.test_3 + ig.test_4 + ig.test_5 + ig.test_6 + ig.test_7 + ig.end_examination) / 8 AS average_score
                FROM batches b
                JOIN courses c ON b.course_id = c.course_id
                JOIN course_enrollments e ON e.batch_id = b.batch_id
                LEFT JOIN internal_grades ig ON ig.batch_id = b.batch_id
                WHERE e.student_id = ?
                GROUP BY b.batch_id, c.courseName, b.batch_code";
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

// Tests list
$tests = ['test_1','test_2','test_3','test_4','test_5','test_6','test_7','end_examination'];

// Determine current page for active sidebar link
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Student Grades Overview</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
<style>
html, body {
    height: 100%;
    margin: 0; padding: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}

.container-flex {
    display: flex;
    min-height: 100vh;
}

.main-content {
    flex: 1;
    padding: 40px 50px;
    margin-left: 280px;
    overflow-y: auto;
    height: 100vh;
}

h2 {
    margin-bottom: 30px;
    color: #2c3e50;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
}

.card-summary {
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 25px;
    background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(44, 62, 80, 0.1);
    height: 160px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    user-select: none;
    position: relative;
    overflow: hidden;
    border: 1px solid #e9ecef;
}

.card-summary::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3498db, #2980b9);
}

.card-summary:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 10px 30px rgba(52, 73, 94, 0.15);
}

.row.g-4 {
    margin-bottom: 40px;
}

.grade-table {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(44, 62, 80, 0.1);
    overflow: hidden;
}

.grade-table th {
    background: linear-gradient(90deg, #34495e, #2c3e50);
    color: white;
    border: none;
    font-weight: 600;
    padding: 15px;
    text-align: center;
}

.grade-table td {
    padding: 15px;
    vertical-align: middle;
    text-align: center;
    border-color: #e9ecef;
}

.grade-table tbody tr:hover {
    background: #f8f9fa;
}

#chart-container {
    margin-top: 40px;
    max-width: 900px;
    display: none;
    background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(44, 62, 80, 0.1);
    overflow: hidden;
}

.grade-cell {
    color: white;
    font-weight: bold;
    text-align: center;
    user-select: none;
    padding: 10px;
    border-radius: 6px;
    font-size: 0.9rem;
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
    .row.g-4 {
        flex-direction: column;
    }
    #chart-container {
        padding: 15px;
    }
}
</style>
</head>
<body>

<div class="container-flex">
    <!-- Include the consistent navigation -->
    <?php include("student_navigation.php"); ?>

    <main class="main-content">
        <h2><i class="bi bi-bar-chart-line-fill"></i> My Grades Overview</h2>
        <p class="mb-4">View your performance across enrolled courses. Click on a course card to see detailed chart.</p>

        <?php if (empty($batches)): ?>
            <div class="no-grades">No enrolled courses found. <a href="enroll.php">Enroll now</a> to start tracking grades.</div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach($batches as $batch): ?>
                <div class="col-md-4">
                    <div class="card-summary" data-batch-id="<?= htmlspecialchars($batch['batch_id']); ?>">
                        <h5><?= htmlspecialchars($batch['course_name']); ?></h5>
                        <h6><?= htmlspecialchars($batch['batch_name']); ?></h6>
                        <p><strong>Average:</strong> <?= number_format($batch['average_score'] ?? 0, 2); ?>%</p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <table class="table grade-table table-striped">
            <thead>
                <tr>
                    <th>Batch</th>
                    <?php foreach ($tests as $test): ?>
                        <th><?= ucfirst(str_replace('_',' ',$test)); ?></th>
                    <?php endforeach; ?>
                    <th>Total</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($batches as $batch):
                    $stmt_grades = $conn->prepare("SELECT * FROM internal_grades WHERE student_id = ? AND batch_id = ?");
                    $stmt_grades->bind_param("ii", $student_id, $batch['batch_id']);
                    $stmt_grades->execute();
                    $result_grades = $stmt_grades->get_result();
                    $grades = $result_grades->fetch_assoc();

                    $total = 0;
                    foreach ($tests as $t) $total += $grades[$t] ?? 0;
                    $max_total = count($tests)*100;
                    $percentage = $max_total ? $total/$max_total*100 : 0;
                ?>
                <tr data-batch-id="<?= htmlspecialchars($batch['batch_id']); ?>">
                    <td><?= htmlspecialchars("{$batch['course_name']} - {$batch['batch_name']}"); ?></td>
                    <?php foreach($tests as $t): ?>
                        <td class="grade-cell" style="background-color: <?= gradeColor($grades[$t] ?? null); ?>;">
                            <?= htmlspecialchars($grades[$t] ?? '-'); ?>
                        </td>
                    <?php endforeach; ?>
                    <td><?= $total; ?></td>
                    <td><?= number_format($percentage, 2); ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div id="chart-container">
            <canvas id="gradeChart" style="width:100%;height:300px;"></canvas>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
const batches = <?= json_encode($batches); ?>;
const tests = <?= json_encode($tests); ?>;
const studentId = <?= json_encode($student_id); ?>;

const batchGrades = {};

<?php
foreach ($batches as $batch) {
    $stmt_grades = $conn->prepare("SELECT * FROM internal_grades WHERE student_id = ? AND batch_id = ?");
    $stmt_grades->bind_param("ii", $student_id, $batch['batch_id']);
    $stmt_grades->execute();
    $result_grades = $stmt_grades->get_result();
    $grades = $result_grades->fetch_assoc();

    echo "batchGrades['" . $batch['batch_id'] . "'] = {";
    foreach ($tests as $test) {
        $val = isset($grades[$test]) ? $grades[$test] : 0;
        echo "'" . $test . "': $val,";
    }
    echo "};\n";
}
?>

const chartContainer = document.getElementById('chart-container');
const ctx = document.getElementById('gradeChart').getContext('2d');
let gradeChart = null;

function renderChart(batch_id) {
    const data = batchGrades[batch_id];
    if(!data) return;

    if(gradeChart) gradeChart.destroy();

    gradeChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: tests.map(t => t.replace(/_/g,' ').replace(/\b\w/g, c => c.toUpperCase())),
            datasets: [{
                label: 'My Score',
                data: tests.map(t => data[t] ?? 0),
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                fill: true,
                tension: 0.4
            }]
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
    chartContainer.style.display = 'block';
    chartContainer.scrollIntoView({behavior:"smooth"});
}

// Add click listeners to summary cards
document.querySelectorAll('.card-summary').forEach(card => {
    card.addEventListener('click', () => {
        const batch_id = card.dataset.batchId;
        renderChart(batch_id);
    });
});
</script>

</body>
</html>