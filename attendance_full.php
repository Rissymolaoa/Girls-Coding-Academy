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

// Handle month/year navigation
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date("n");
$year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date("Y");

if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

// Fetch one (latest) attendance record per day for selected month
$attendanceQuery = $conn->query("
    SELECT d.day, a.status, a.marked_at
    FROM (
        SELECT DATE(marked_at) as day, MAX(marked_at) as maxm
        FROM attendance
        WHERE student_id = $student_id
          AND MONTH(marked_at) = $month
          AND YEAR(marked_at) = $year
        GROUP BY DATE(marked_at)
    ) d
    JOIN attendance a 
      ON DATE(a.marked_at) = d.day 
     AND a.marked_at = d.maxm
    ORDER BY d.day DESC
");

$attendanceData = [];
while ($row = $attendanceQuery->fetch_assoc()) {
    $attendanceData[$row['day']] = [
        'status' => $row['status'],
        'time'   => $row['marked_at']
    ];
}

// Summary counts (overall)
$presentCount = (int)$conn->query("SELECT COUNT(*) as cnt FROM attendance WHERE student_id=$student_id AND status='Present'")->fetch_assoc()['cnt'];
$absentCount  = (int)$conn->query("SELECT COUNT(*) as cnt FROM attendance WHERE student_id=$student_id AND status='Absent'")->fetch_assoc()['cnt'];
$lateCount    = (int)$conn->query("SELECT COUNT(*) as cnt FROM attendance WHERE student_id=$student_id AND status='Late'")->fetch_assoc()['cnt'];
$sickCount    = (int)$conn->query("SELECT COUNT(*) as cnt FROM attendance WHERE student_id=$student_id AND status='Sick'")->fetch_assoc()['cnt'];

// Calendar setup
$firstDayOfMonth = mktime(0,0,0,$month,1,$year);
$daysInMonth     = date("t",$firstDayOfMonth);
$monthName       = date("F Y",$firstDayOfMonth);
$startDayOfWeek  = date("w",$firstDayOfMonth); // 0 = Sunday
$today           = date("Y-m-d");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Full Attendance - Calendar</title>

<!-- Bootstrap CSS + Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
  body { background:#f6f5f8; font-family: Arial, sans-serif; }
  header { background:#343a40; color:#fff; padding:18px; text-align:center; }
  .d-flex { display:flex; min-height:100vh; }
  .sidebar { width:250px; background:#495057; padding:20px; color:#fff; }
  .sidebar a { display:flex; align-items:center; gap:10px; color:#fff; text-decoration:none; padding:10px; margin:5px 0; border-radius:6px; }
  .sidebar a.active, .sidebar a:hover { background:#6c757d; }
  .admin-pic { width:90px; height:90px; border-radius:50%; display:block; margin:auto; margin-bottom:15px; border:2px solid #1abc9c; object-fit:cover; }
  .content { flex:1; padding:30px; }
  .table thead { background:#7b2cbf; color:white; }
  .summary-card { text-align:center; padding:20px; color:white; border-radius:12px; }
  .calendar .day-number { font-weight:600; display:block; text-align:left; padding-left:8px; }
  .status-symbol { font-size:20px; display:block; margin:8px auto 0; }
  .today-cell { background:#fff3cd !important; border:2px solid #ffc107 !important; }
  .nav-buttons .btn { padding:.25rem .5rem; font-size:.85rem; line-height:1; }
  .calendar td { vertical-align: top; height:110px; }
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
  <div class="content">
    <h2>My Attendance Records - <?php echo htmlspecialchars($monthName); ?></h2>

    <!-- Summary Cards -->
    <div class="row mb-4">
      <div class="col-md-3"><div class="summary-card bg-success"><h3><?php echo $presentCount; ?></h3><p>Present</p></div></div>
      <div class="col-md-3"><div class="summary-card bg-danger"><h3><?php echo $absentCount; ?></h3><p>Absent</p></div></div>
      <div class="col-md-3"><div class="summary-card bg-warning"><h3><?php echo $lateCount; ?></h3><p>Late</p></div></div>
      <div class="col-md-3"><div class="summary-card bg-info"><h3><?php echo $sickCount; ?></h3><p>Sick</p></div></div>
    </div>

    <!-- Calendar (where attendance table normally sits) -->
    <div class="table-responsive mb-2">
      <table class="calendar table table-bordered table-hover text-center">
        <thead>
          <tr>
            <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th>
            <th>Thu</th><th>Fri</th><th>Sat</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $day = 1;
            $currentWeekDay = 0;
            echo "<tr>";

            for ($i=0; $i<$startDayOfWeek; $i++) {
                echo "<td></td>";
                $currentWeekDay++;
            }

            while ($day <= $daysInMonth) {
                $date = $year . '-' . str_pad($month,2,'0',STR_PAD_LEFT) . '-' . str_pad($day,2,'0',STR_PAD_LEFT);
                $isToday = ($date === $today);
                $cellClass = $isToday ? 'today-cell' : '';

                echo "<td class='{$cellClass}'>";
                echo "<span class='day-number'>{$day}</span>";

                if (isset($attendanceData[$date])) {
                    $status = $attendanceData[$date]['status'];
                    $time   = $attendanceData[$date]['time'];
                    $tooltip = htmlspecialchars($status . " on " . date('d M Y, H:i', strtotime($time)), ENT_QUOTES);

                    if ($status === 'Present') {
                        echo "<span class='status-symbol text-success' data-bs-toggle='tooltip' title=\"{$tooltip}\">✔</span>";
                    } elseif ($status === 'Absent') {
                        echo "<span class='status-symbol text-danger' data-bs-toggle='tooltip' title=\"{$tooltip}\">✖</span>";
                    } elseif ($status === 'Late') {
                        echo "<span class='status-symbol text-warning' data-bs-toggle='tooltip' title=\"{$tooltip}\">⏰</span>";
                    } elseif ($status === 'Sick') {
                        echo "<span class='status-symbol text-info' data-bs-toggle='tooltip' title=\"{$tooltip}\">🤒</span>";
                    }
                }

                echo "</td>";

                $day++;
                $currentWeekDay++;

                if ($currentWeekDay == 7 && $day <= $daysInMonth) {
                    echo "</tr><tr>";
                    $currentWeekDay = 0;
                }
            }

            while ($currentWeekDay < 7) {
                echo "<td></td>";
                $currentWeekDay++;
            }
            echo "</tr>";
          ?>
        </tbody>
      </table>
    </div>

    <!-- Month Navigation moved BELOW the table -->
    <!-- Month Navigation replaced with Pagination -->
    <nav aria-label="Attendance month navigation">
      <ul class="pagination justify-content-center mt-3">
        <li class="page-item">
          <a class="page-link" href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>">&laquo; Prev</a>
        </li>
        <li class="page-item disabled">
          <span class="page-link"><?php echo $monthName; ?></span>
        </li>
        <li class="page-item">
          <a class="page-link" href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>">Next &raquo;</a>
        </li>
      </ul>
    </nav>


    <!-- Legend -->
    <div class="legend text-center mt-2">
      <span class="text-success me-3">✔ Present</span>
      <span class="text-danger me-3">✖ Absent</span>
      <span class="text-warning me-3">⏰ Late</span>
      <span class="text-info me-3">🤒 Sick</span>
    </div>

  </div>
</div>

<footer class="text-center text-white mt-4 p-3" style="background:#495057;">
  &copy; <?php echo date("Y"); ?> Girls Coding Academy. All rights reserved.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el); });
  });
</script>
</body>
</html>
