<?php
session_start();

// DB connection
$host = "localhost";
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
body { background:#f6f5f8; font-family: Arial,sans-serif; }
header { background:#343a40; color: #fff; padding: 18px 24px; text-align: center; box-shadow:0 2px 6px rgba(0,0,0,0.12);}
header h1 { margin:0; font-size:22px; }
.d-flex { display:flex; flex-wrap:nowrap; min-height:100vh; }
.sidebar { width:250px; background:#495057; padding:20px; color:#fff; }
.sidebar h3 { text-align:center; margin-bottom:20px; font-weight:bold; }
.sidebar a { display:flex; align-items:center; gap:10px; color:white; text-decoration:none; padding:10px; margin:5px 0; border-radius:6px; transition:0.2s; }
.sidebar a:hover, .sidebar a.active { background:#6c757d; }
.admin-pic { width:90px; height:90px; border-radius:50%; display:block; margin:auto; margin-bottom:15px; border:2px solid #1abc9c; object-fit:cover; }
.content { flex:1; padding:30px; }
.table thead { background:#7b2cbf; color:white; }
.card-chart { padding:20px; margin-bottom:20px; }
.summary-card { text-align:center; padding:20px; color:white; border-radius:12px; }
</style>
</head>
<body>

<header>
    <h1>Girls Coding Academy - My Attendance</h1>
</header>

<div class="d-flex">
  <!-- Sidebar -->
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

  <!-- Content -->
  <div class="content flex-fill">
      <h2>My Attendance Records</h2>

      <!-- Summary Cards -->
      <div class="row mb-4">
        <div class="col-md-3"><div class="summary-card bg-success"><h3><?php echo $presentCount; ?></h3><p>Days Present</p></div></div>
        <div class="col-md-3"><div class="summary-card bg-danger"><h3><?php echo $absentCount; ?></h3><p>Days Absent</p></div></div>
        <div class="col-md-3"><div class="summary-card bg-warning"><h3><?php echo $lateCount; ?></h3><p>Days Late</p></div></div>
        <div class="col-md-3"><div class="summary-card bg-info"><h3><?php echo $sickCount; ?></h3><p>Days Sick</p></div></div>
      </div>

      <!-- Attendance Table -->
      <div class="table-responsive mb-2">
        <table class="table table-bordered table-hover">
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
                  <small><?= date('d M Y, H:i', strtotime($row['marked_at'])) ?></small>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
      <a href="attendance_full.php" class="btn btn-primary">View All Attendance</a>

      <!-- Charts -->
      <div class="row mt-4">
        <div class="col-md-6">
          <div class="card card-chart">
            <h5 class="text-center">Overall Attendance Pie Chart</h5>
            <canvas id="pieChart"></canvas>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card card-chart">
            <h5 class="text-center">Attendance by Course (Bar Chart)</h5>
            <canvas id="barChart"></canvas>
          </div>
        </div>
      </div>
  </div>
</div>

<footer class="text-center text-white mt-4 p-3" style="background:#495057;">
    &copy; <?php echo date("Y"); ?> Girls Coding Academy. All rights reserved.
</footer>

<script>
// Pie Chart
const pieCtx = document.getElementById('pieChart').getContext('2d');
new Chart(pieCtx, {
    type: 'pie',
    data: {
        labels: ['Present', 'Absent', 'Late', 'Sick'],
        datasets: [{
            data: [<?php echo $presentCount; ?>, <?php echo $absentCount; ?>, <?php echo $lateCount; ?>, <?php echo $sickCount; ?>],
            backgroundColor: ['#28a745','#dc3545','#ffc107','#17a2b8'],
            borderColor: '#fff',
            borderWidth: 2
        }]
    },
    options: { responsive:true, plugins: { legend: { position:'bottom' } } }
});

// Bar Chart
const barCtx = document.getElementById('barChart').getContext('2d');
new Chart(barCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($barLabels); ?>,
        datasets: [
            { label: 'Present', data: <?php echo json_encode($barPresent); ?>, backgroundColor: '#28a745' },
            { label: 'Absent',  data: <?php echo json_encode($barAbsent); ?>,  backgroundColor: '#dc3545' },
            { label: 'Late',    data: <?php echo json_encode($barLate); ?>,    backgroundColor: '#ffc107' },
            { label: 'Sick',    data: <?php echo json_encode($barSick); ?>,    backgroundColor: '#17a2b8' }
        ]
    },
    options: {
        responsive:true,
        scales: { y: { beginAtZero:true } },
        plugins: { legend: { position:'bottom' } }
    }
});
</script>

</body>
</html>
