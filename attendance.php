<?php
session_start();

// DB connection
$host = "localhost:3307";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Check if logged in student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get student_id
$res = $conn->query("SELECT student_id FROM students WHERE user_id = $user_id");
if ($res->num_rows > 0) {
    $student = $res->fetch_assoc();
    $student_id = $student['student_id'];
} else {
    die("Error: Student not found.");
}

// Fetch only 4 latest attendance records
$attendance = $conn->query("
    SELECT 
        a.attendance_id,
        a.session_id,
        a.batch_id,
        a.status,
        a.marked_at,
        b.batch_code,
        c.courseName,
        CONCAT(u.firstName, ' ', u.lastName) AS teacher_name
    FROM attendance a
    INNER JOIN batches b ON a.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    INNER JOIN teachers t ON a.marked_by = t.teacher_id
    INNER JOIN users u ON t.user_id = u.user_id
    WHERE a.student_id = $student_id
    ORDER BY a.session_id DESC
    LIMIT 4
");

// Attendance summary
$presentCount = $conn->query("SELECT COUNT(*) as cnt FROM attendance WHERE student_id=$student_id AND status='Present'")->fetch_assoc()['cnt'];
$absentCount  = $conn->query("SELECT COUNT(*) as cnt FROM attendance WHERE student_id=$student_id AND status='Absent'")->fetch_assoc()['cnt'];
$lateCount    = $conn->query("SELECT COUNT(*) as cnt FROM attendance WHERE student_id=$student_id AND status='Late'")->fetch_assoc()['cnt'];
$sickCount    = $conn->query("SELECT COUNT(*) as cnt FROM attendance WHERE student_id=$student_id AND status='Sick'")->fetch_assoc()['cnt'];

// Attendance per course for bar chart (extended)
$courseAttendance = $conn->query("
    SELECT c.courseName, 
        SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) AS present,
        SUM(CASE WHEN a.status='Absent' THEN 1 ELSE 0 END) AS absent,
        SUM(CASE WHEN a.status='Late' THEN 1 ELSE 0 END) AS late,
        SUM(CASE WHEN a.status='Sick' THEN 1 ELSE 0 END) AS sick
    FROM attendance a
    INNER JOIN batches b ON a.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    WHERE a.student_id = $student_id
    GROUP BY c.courseName
");
$barLabels = [];
$barPresent = [];
$barAbsent = [];
$barLate = [];
$barSick = [];
while($row = $courseAttendance->fetch_assoc()) {
    $barLabels[] = $row['courseName'];
    $barPresent[] = $row['present'];
    $barAbsent[]  = $row['absent'];
    $barLate[]    = $row['late'];
    $barSick[]    = $row['sick'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Attendance - Student Dashboard</title>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    margin: 0;
    padding: 0;
    min-height: 100vh;
}
.container-flex {
    display: flex;
    min-height: 100vh;
}
.content { 
    flex: 1; 
    padding: 40px 50px;
    margin-left: 280px;
    overflow-y: auto;
}
h2 {
    margin-bottom: 20px;
    color: #2c3e50;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
}
.summary-cards {
    display: flex;
    gap: 20px;
    margin-bottom: 40px;
    flex-wrap: wrap;
}
.summary-card { 
    background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
    box-shadow: 0 5px 20px rgba(44, 62, 80, 0.1);
    border-radius: 15px;
    padding: 25px;
    flex: 1;
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
    position: relative;
    overflow: hidden;
    min-width: 200px;
}
.summary-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3498db, #2980b9);
}
.summary-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(52, 73, 94, 0.15);
}
.summary-card.success { border-left: 5px solid #27ae60; }
.summary-card.danger { border-left: 5px solid #e74c3c; }
.summary-card.warning { border-left: 5px solid #f39c12; }
.summary-card.info { border-left: 5px solid #3498db; }
.summary-card h3 {
    font-size: 2rem;
    margin: 0;
    color: #2c3e50;
    font-weight: bold;
}
.summary-card p {
    margin: 5px 0 0 0;
    color: #7f8c8d;
    font-weight: 500;
}
.table {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(44, 62, 80, 0.1);
}
.table thead th {
    background: linear-gradient(90deg, #34495e, #2c3e50);
    color: white;
    border: none;
    font-weight: 600;
    padding: 15px;
}
.table tbody td {
    padding: 15px;
    vertical-align: middle;
    border-color: #e9ecef;
}
.table tbody tr:hover {
    background: #f8f9fa;
}
.badge {
    font-size: 0.85rem;
    padding: 6px 12px;
    border-radius: 20px;
}
.btn-view-all {
    background: #3498db;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    display: inline-block;
    margin-top: 20px;
    transition: background 0.3s ease;
}
.btn-view-all:hover {
    background: #2980b9;
    color: white;
}
.chart-container {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(44, 62, 80, 0.1);
    margin-bottom: 30px;
}
.chart-container h5 {
    text-align: center;
    color: #2c3e50;
    margin-bottom: 20px;
    font-weight: 600;
}
.no-attendance {
    text-align: center;
    color: #7f8c8d;
    font-style: italic;
    padding: 50px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(44, 62, 80, 0.1);
}
@media (max-width: 768px) {
    .content {
        margin-left: 0;
        padding: 20px;
    }
    .summary-cards {
        flex-direction: column;
    }
    .table {
        font-size: 0.9rem;
    }
}
</style>
</head>
<body>

<div class="container-flex">
    <!-- Include the consistent navigation -->
    <?php include("student_navigation.php"); ?>

    <!-- Content -->
    <div class="content">
        <h2><i class="bi bi-card-checklist"></i> My Attendance Records</h2>
        <p class="mb-4">Track your attendance history and performance overview.</p>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card success">
                <h3><?php echo $presentCount; ?></h3>
                <p>Days Present</p>
            </div>
            <div class="summary-card danger">
                <h3><?php echo $absentCount; ?></h3>
                <p>Days Absent</p>
            </div>
            <div class="summary-card warning">
                <h3><?php echo $lateCount; ?></h3>
                <p>Days Late</p>
            </div>
            <div class="summary-card info">
                <h3><?php echo $sickCount; ?></h3>
                <p>Days Sick</p>
            </div>
        </div>

        <!-- Attendance Table -->
        <?php if($attendance->num_rows > 0): ?>
        <div class="table-responsive mb-4">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Course</th>
                        <th>Batch Code</th>
                        <th>Session</th>
                        <th>Status</th>
                        <th>Marked By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; while($row = $attendance->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars($row['courseName']); ?></td>
                        <td><?php echo htmlspecialchars($row['batch_code']); ?></td>
                        <td><?php echo htmlspecialchars($row['session_id']); ?></td>
                        <td>
                            <?php 
                                if($row['status']=='Present') echo "<span class='badge bg-success'>✔ Present</span>";
                                elseif($row['status']=='Absent') echo "<span class='badge bg-danger'>✖ Absent</span>";
                                elseif($row['status']=='Late') echo "<span class='badge bg-warning text-dark'>⏰ Late</span>";
                                elseif($row['status']=='Sick') echo "<span class='badge bg-info text-dark'>🤒 Sick</span>";
                            ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row['teacher_name']); ?><br>
                            <small class="text-muted"><?= date('d M Y, H:i', strtotime($row['marked_at'])) ?></small>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <a href="attendance_full.php" class="btn-view-all"><i class="bi bi-eye"></i> View All Attendance</a>
        <?php else: ?>
        <div class="no-attendance">No attendance records found yet.</div>
        <?php endif; ?>

        <!-- Charts -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="chart-container">
                    <h5>Overall Attendance Pie Chart</h5>
                    <canvas id="pieChart"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container">
                    <h5>Attendance by Course (Bar Chart)</h5>
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Pie Chart
const pieCtx = document.getElementById('pieChart').getContext('2d');
new Chart(pieCtx, {
    type: 'pie',
    data: {
        labels: ['Present', 'Absent', 'Late', 'Sick'],
        datasets: [{
            data: [<?php echo $presentCount; ?>, <?php echo $absentCount; ?>, <?php echo $lateCount; ?>, <?php echo $sickCount; ?>],
            backgroundColor: ['#27ae60','#e74c3c','#f39c12','#3498db'],
            borderColor: '#fff',
            borderWidth: 2
        }]
    },
    options: { 
        responsive: true, 
        plugins: { 
            legend: { 
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true
                }
            } 
        } 
    }
});

// Bar Chart
const barCtx = document.getElementById('barChart').getContext('2d');
new Chart(barCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($barLabels); ?>,
        datasets: [
            { label: 'Present', data: <?php echo json_encode($barPresent); ?>, backgroundColor: '#27ae60' },
            { label: 'Absent',  data: <?php echo json_encode($barAbsent); ?>,  backgroundColor: '#e74c3c' },
            { label: 'Late',    data: <?php echo json_encode($barLate); ?>,    backgroundColor: '#f39c12' },
            { label: 'Sick',    data: <?php echo json_encode($barSick); ?>,    backgroundColor: '#3498db' }
        ]
    },
    options: {
        responsive: true,
        scales: { 
            y: { 
                beginAtZero: true,
                grid: {
                    color: '#e9ecef'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        },
        plugins: { 
            legend: { 
                position: 'bottom',
                labels: {
                    padding: 15
                }
            } 
        }
    }
});
</script>

</body>
</html>