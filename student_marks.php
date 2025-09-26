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
    font-family: Arial, sans-serif;
}

.container-flex {
    display: flex;
    min-height: 100vh;
    background: #f8f9fa;
}

.sidebar {
    width: 250px;
    background: #495057;
    color: #fff;
    padding: 20px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.sidebar img {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    border: 2px solid #1abc9c;
    object-fit: cover;
    margin-bottom: 15px;
}

.sidebar h4 {
    text-align: center;
    margin-bottom: 10px;
    font-weight: bold;
}

.sidebar a {
    display: flex;
    align-items: center;
    gap: 10px;
    color: white;
    text-decoration: none;
    padding: 10px 15px;
    margin: 5px 0;
    border-radius: 6px;
    transition: background-color 0.3s;
    width: 100%;
}

.sidebar a:hover,
.sidebar a.active {
    background: #6c757d;
}

.main-content {
    flex: 1;
    padding: 30px 40px;
    overflow-y: auto;
}

h2 {
    margin-bottom: 30px;
}

.card-summary {
    cursor: pointer;
    transition: transform 0.2s;
    padding: 20px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 0 6px rgba(0,0,0,0.1);
    height: 150px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    user-select: none;
}

.card-summary:hover {
    transform: scale(1.05);
    box-shadow: 0 0 15px rgba(0,0,0,0.2);
}

.row.g-4 {
    margin-bottom: 40px;
}

.grade-table {
    background: white;
    border-radius: 10px;
    box-shadow: 0 0 6px rgba(0,0,0,0.1);
}

.grade-table th, .grade-table td {
    text-align: center;
    vertical-align: middle !important;
}

#chart-container {
    margin-top: 40px;
    max-width: 900px;
    display: none;
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 0 6px rgba(0,0,0,0.1);
}

.grade-cell {
    color: white;
    font-weight: bold;
    text-align: center;
    user-select: none;
}
</style>
</head>
<body>

<div class="container-flex">
    <nav class="sidebar">
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
    </nav>

    <main class="main-content">
        <h2>My Grades Overview</h2>

        <div class="row g-4">
            <?php foreach($batches as $batch): ?>
                <div class="col-md-4">
                    <div class="card-summary" data-batch-id="<?php echo $batch['batch_id']; ?>">
                        <h5><?php echo htmlspecialchars($batch['course_name']); ?></h5>
                        <h6><?php echo htmlspecialchars($batch['batch_name']); ?></h6>
                        <p><strong>Average:</strong> <?php echo number_format($batch['average_score'] ?? 0, 2); ?>%</p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <table class="table grade-table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Batch</th>
                    <?php foreach ($tests as $test): ?>
                        <th><?php echo ucfirst(str_replace('_',' ',$test)); ?></th>
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
                <tr data-batch-id="<?php echo $batch['batch_id']; ?>">
                    <td><?php echo htmlspecialchars("{$batch['course_name']} - {$batch['batch_name']}"); ?></td>
                    <?php foreach($tests as $t): ?>
                        <td class="grade-cell" style="background-color: <?php echo gradeColor($grades[$t] ?? null); ?>;">
                            <?php echo htmlspecialchars($grades[$t] ?? '-'); ?>
                        </td>
                    <?php endforeach; ?>
                    <td><?php echo $total; ?></td>
                    <td><?php echo number_format($percentage, 2); ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div id="chart-container">
            <canvas id="gradeChart" style="width:100%;height:300px;"></canvas>
        </div>
    </main>
</div>

<script>
const batches = <?php echo json_encode($batches); ?>;
const tests = <?php echo json_encode($tests); ?>;
const studentId = <?php echo json_encode($student_id); ?>;

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
                borderColor: 'blue',
                fill: false,
                tension: 0.2
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' }},
            scales: { y: { beginAtZero: true, max: 100 }}
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
