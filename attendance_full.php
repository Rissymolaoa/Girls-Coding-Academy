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
// Get student info
$user_res = $conn->query("SELECT * FROM users WHERE user_id = $user_id");
$user_data = $user_res->fetch_assoc();
$res = $conn->query("SELECT student_id, photo FROM students WHERE user_id = $user_id");
if ($res->num_rows > 0) {
    $student = $res->fetch_assoc();
    $student_id = $student['student_id'];
    $student_photo = $student['photo'] ?? 'default.jpg';
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
// Fetch attendance records
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
$startDayOfWeek  = date("w",$firstDayOfMonth);
$today           = date("Y-m-d");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>My Attendance - Girls Coding Academy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --primary: #667eea;
    --primary-dark: #764ba2;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --info: #3b82f6;
    --light-bg: #f9fafb;
    --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
  }
  * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
  body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
  }
  /* Top Navigation */
  .top-nav {
    background: white;
    padding: 1rem 2rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .top-nav .logo {
    font-size: 1.5rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }
  .top-nav .user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
  }
  .user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--primary);
  }
  /* Main Container */
  .main-container {
    padding-left: 280px;
    min-height: calc(100vh - 80px);
  }
  /* Sidebar Navigation */
  .sidebar {
    position: fixed;
    top: 80px;
    left: 0;
    width: 280px;
    height: calc(100vh - 80px);
    background: white;
    padding: 2rem 0;
    box-shadow: 2px 0 10px rgba(0,0,0,0.05);
    overflow-y: auto;
    z-index: 1000;
  }
  .sidebar-header {
    padding: 0 1.5rem 2rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
  }
  .sidebar-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--primary);
  }
  .sidebar-name {
    text-align: center;
    font-weight: 600;
    color: #1f2937;
  }
  .sidebar-nav a {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.5rem;
    color: #6b7280;
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
  }
  .sidebar-nav a:hover {
    color: var(--primary);
    background: #f0f4ff;
  }
  .sidebar-nav a.active {
    color: var(--primary);
    background: #f0f4ff;
    border-left: 4px solid var(--primary);
    padding-left: calc(1.5rem - 4px);
    font-weight: 600;
  }
  .sidebar-nav a i {
    font-size: 1.25rem;
  }
  /* Content Area */
  .content-area {
    width: 100%;
    padding: 2rem;
    overflow-y: auto;
  }
  /* Page Header */
  .page-header {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--card-shadow);
  }
  .page-header h1 {
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 0.5rem;
  }
  .page-header p {
    color: #6b7280;
    margin: 0;
  }
  /* Summary Cards */
  .summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
  }
  .summary-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s ease;
    border-top: 4px solid;
  }
  .summary-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  }
  .summary-card.present { border-top-color: var(--success); }
  .summary-card.absent { border-top-color: var(--danger); }
  .summary-card.late { border-top-color: var(--warning); }
  .summary-card.sick { border-top-color: var(--info); }
  .card-number {
    font-size: 2.5rem;
    font-weight: 700;
  }
  .summary-card.present .card-number { color: var(--success); }
  .summary-card.absent .card-number { color: var(--danger); }
  .summary-card.late .card-number { color: var(--warning); }
  .summary-card.sick .card-number { color: var(--info); }
  .card-label {
    font-weight: 600;
    color: #6b7280;
    font-size: 0.95rem;
  }
  /* Calendar */
  .calendar-container {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: var(--card-shadow);
    margin-bottom: 2rem;
  }
  .calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
  }
  .calendar-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
  }
  .month-nav {
    display: flex;
    gap: 1rem;
    align-items: center;
  }
  .month-nav .btn {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    border: none;
    background: #f3f4f6;
    color: #1f2937;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
  }
  .month-nav .btn:hover {
    background: var(--primary);
    color: white;
  }
  .month-nav .current-month {
    padding: 0.5rem 1rem;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    border-radius: 8px;
    font-weight: 600;
    min-width: 150px;
    text-align: center;
  }
  .calendar-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 2rem;
  }
  .calendar-table th {
    background: #f9fafb;
    padding: 1rem;
    text-align: center;
    font-weight: 700;
    color: #1f2937;
    border-bottom: 2px solid #e5e7eb;
  }
  .calendar-table td {
    height: 120px;
    padding: 0.75rem;
    border: 1px solid #e5e7eb;
    vertical-align: top;
    position: relative;
    background: white;
    transition: all 0.3s ease;
  }
  .calendar-table td:hover {
    background: #f9fafb;
  }
  .calendar-table td.today {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    border: 2px solid var(--primary);
  }
  .day-number {
    display: block;
    font-weight: 700;
    font-size: 1.1rem;
    color: #1f2937;
    margin-bottom: 0.5rem;
  }
  .status-icon {
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 1.8rem;
    margin: 0.5rem 0;
    cursor: pointer;
  }
  .status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-align: center;
  }
  .status-present { color: var(--success); }
  .status-absent { color: var(--danger); }
  .status-late { color: var(--warning); }
  .status-sick { color: var(--info); }
  /* Legend */
  .legend {
    display: flex;
    gap: 2rem;
    justify-content: center;
    padding: 1.5rem;
    background: #f9fafb;
    border-radius: 12px;
    flex-wrap: wrap;
  }
  .legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: #6b7280;
  }
  .legend-icon {
    font-size: 1.5rem;
  }
  /* Responsive */
  @media (max-width: 768px) {
    .main-container {
      padding-left: 0;
      display: flex;
      flex-direction: column;
    }
    .sidebar {
      position: static;
      width: 100%;
      height: auto;
      top: auto;
      left: auto;
      box-shadow: none;
      z-index: auto;
      padding: 1rem 0;
    }
    .content-area {
      width: 100%;
    }
    .summary-grid {
      grid-template-columns: repeat(2, 1fr);
    }
    .calendar-table td {
      height: 100px;
      padding: 0.5rem;
      font-size: 0.9rem;
    }
    .day-number {
      font-size: 0.9rem;
    }
    .status-icon {
      font-size: 1.4rem;
    }
    .legend {
      gap: 1rem;
    }
  }
  @media (max-width: 480px) {
    .content-area {
      padding: 1rem;
    }
    .page-header {
      padding: 1rem;
    }
    .page-header h1 {
      font-size: 1.5rem;
    }
    .summary-grid {
      grid-template-columns: repeat(2, 1fr);
      gap: 1rem;
    }
    .summary-card {
      padding: 1rem;
    }
    .card-number {
      font-size: 2rem;
    }
    .calendar-table td {
      height: 80px;
      padding: 0.4rem;
      font-size: 0.85rem;
    }
    .day-number {
      font-size: 0.8rem;
    }
    .status-icon {
      font-size: 1.2rem;
    }
  }
</style>
</head>
<body>
<!-- Top Navigation -->
<div class="top-nav">
  <div class="logo"><i class="bi bi-code-slash"></i> Girls Coding Academy</div>
  <div class="user-info">
    <span><?= htmlspecialchars($user_data['firstName'] . ' ' . $user_data['lastName']) ?></span>
    <img src="<?= htmlspecialchars($student_photo) ?>" alt="Profile" class="user-avatar">
  </div>
</div>
<div class="main-container">
  <!-- Sidebar Navigation -->
<?php include 'student_navigation.php'; ?>
  <!-- Content Area -->
  <div class="content-area">
    <div class="page-header">
      <h1><i class="bi bi-card-checklist"></i> My Attendance Records</h1>
      <p>Track your attendance history and attendance status</p>
    </div>
    <!-- Summary Cards -->
    <div class="summary-grid">
      <div class="summary-card present">
        <div class="card-number"><?= $presentCount ?></div>
        <div class="card-label">Present</div>
      </div>
      <div class="summary-card absent">
        <div class="card-number"><?= $absentCount ?></div>
        <div class="card-label">Absent</div>
      </div>
      <div class="summary-card late">
        <div class="card-number"><?= $lateCount ?></div>
        <div class="card-label">Late</div>
      </div>
      <div class="summary-card sick">
        <div class="card-number"><?= $sickCount ?></div>
        <div class="card-label">Sick Leave</div>
      </div>
    </div>
    <!-- Calendar -->
    <div class="calendar-container">
      <div class="calendar-header">
        <h2>Attendance Calendar</h2>
        <div class="month-nav">
          <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn">
            <i class="bi bi-chevron-left"></i> Prev
          </a>
          <div class="current-month"><?= $monthName ?></div>
          <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn">
            Next <i class="bi bi-chevron-right"></i>
          </a>
        </div>
      </div>
      <table class="calendar-table">
        <thead>
          <tr>
            <th>Sun</th>
            <th>Mon</th>
            <th>Tue</th>
            <th>Wed</th>
            <th>Thu</th>
            <th>Fri</th>
            <th>Sat</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $day = 1;
            $currentWeekDay = 0;
            echo "<tr>";
            for ($i = 0; $i < $startDayOfWeek; $i++) {
                echo "<td></td>";
                $currentWeekDay++;
            }
            while ($day <= $daysInMonth) {
                $date = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                $isToday = ($date === $today);
                $cellClass = $isToday ? 'today' : '';
                echo "<td class='{$cellClass}'>";
                echo "<span class='day-number'>{$day}</span>";
                if (isset($attendanceData[$date])) {
                    $status = $attendanceData[$date]['status'];
                    $time = $attendanceData[$date]['time'];
                    $tooltip = htmlspecialchars($status . " on " . date('d M Y, H:i', strtotime($time)), ENT_QUOTES);
                    if ($status === 'Present') {
                        echo "<div class='status-icon text-success' data-bs-toggle='tooltip' title=\"{$tooltip}\"><i class='bi bi-check-circle-fill'></i></div>";
                    } elseif ($status === 'Absent') {
                        echo "<div class='status-icon text-danger' data-bs-toggle='tooltip' title=\"{$tooltip}\"><i class='bi bi-x-circle-fill'></i></div>";
                    } elseif ($status === 'Late') {
                        echo "<div class='status-icon text-warning' data-bs-toggle='tooltip' title=\"{$tooltip}\"><i class='bi bi-clock-fill'></i></div>";
                    } elseif ($status === 'Sick') {
                        echo "<div class='status-icon text-info' data-bs-toggle='tooltip' title=\"{$tooltip}\"><i class='bi bi-heartpulse-fill'></i></div>";
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
      <!-- Legend -->
      <div class="legend">
        <div class="legend-item">
          <span class="legend-icon text-success"><i class="bi bi-check-circle-fill"></i></span>
          <span>Present</span>
        </div>
        <div class="legend-item">
          <span class="legend-icon text-danger"><i class="bi bi-x-circle-fill"></i></span>
          <span>Absent</span>
        </div>
        <div class="legend-item">
          <span class="legend-icon text-warning"><i class="bi bi-clock-fill"></i></span>
          <span>Late</span>
        </div>
        <div class="legend-item">
          <span class="legend-icon text-info"><i class="bi bi-heartpulse-fill"></i></span>
          <span>Sick Leave</span>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (el) {
      return new bootstrap.Tooltip(el);
    });
  });
</script>
</body>
</html>