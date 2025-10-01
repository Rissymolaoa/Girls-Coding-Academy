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

// Step 2: Fetch children for this parent
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

// Step 3: Which child is selected?
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : ($children[0]['student_id'] ?? 0);

// Step 4: Month/year navigation
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date("n");
$year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date("Y");

if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

// Step 5: Fetch attendance for selected child
$attendanceData = [];
if ($student_id) {
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
    while ($row = $attendanceQuery->fetch_assoc()) {
        $attendanceData[$row['day']] = [
            'status' => $row['status'],
            'time'   => $row['marked_at']
        ];
    }
}

// Step 6: Calendar setup
$firstDayOfMonth = mktime(0,0,0,$month,1,$year);
$daysInMonth     = date("t",$firstDayOfMonth);
$monthName       = date("F Y",$firstDayOfMonth);
$startDayOfWeek  = date("w",$firstDayOfMonth);
$today           = date("Y-m-d");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Parent View - Attendance</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
    body { display:flex; min-height:100vh; }
    .sidebar { width:250px; background:#343a40; color:white; padding:20px; }
    .sidebar a { display:block; color:white; padding:10px; text-decoration:none; border-radius:5px; }
    .sidebar a:hover, .sidebar a.active { background:#495057; }
    .sidebar img { width:90px; border-radius:50%; margin:0 auto 15px; display:block; border:2px solid #6c757d; }
    .main-content { flex:1; padding:20px; background:#f8f9fa; }
    .calendar td { vertical-align: top; height:110px; }
    .calendar .day-number { font-weight:600; display:block; text-align:left; padding-left:8px; }
    .status-symbol { font-size:20px; display:block; margin:8px auto 0; }
    .today-cell { background:#fff3cd !important; border:2px solid #ffc107 !important; }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <img src="admin.png" alt="Parent Picture">
    <h3 class="text-center">Parent Panel</h3>
    <a href="parents_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <a href="children.php"><i class="bi bi-people"></i> My Children</a>
    <a href="parent_view_attendance.php" class="active"><i class="bi bi-card-checklist"></i> Attendance</a>
    <a href="parent_view_performance.php"><i class="bi bi-graph-up"></i> Performance</a>
    <a href="parent_view_materials.php"><i class="bi bi-folder"></i> Materials</a>
    <a href="parent_messages.php"><i class="bi bi-envelope"></i> Messages</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<!-- Main Content -->
<div class="main-content">
    <h2><i class="bi bi-card-checklist"></i> View Attendance</h2>

    <!-- Select Child -->
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
        <h4 class="mb-3"><?= $monthName ?></h4>
        <div class="table-responsive">
            <table class="calendar table table-bordered text-center">
                <thead class="table-dark">
                    <tr>
                        <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th>
                        <th>Thu</th><th>Fri</th><th>Sat</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $day = 1; $currentWeekDay = 0;
                echo "<tr>";
                for ($i=0; $i<$startDayOfWeek; $i++) { echo "<td></td>"; $currentWeekDay++; }
                while ($day <= $daysInMonth) {
                    $date = $year.'-'.str_pad($month,2,'0',STR_PAD_LEFT).'-'.str_pad($day,2,'0',STR_PAD_LEFT);
                    $isToday = ($date === $today);
                    $cellClass = $isToday ? 'today-cell' : '';
                    echo "<td class='{$cellClass}'>";
                    echo "<span class='day-number'>{$day}</span>";

                    if (isset($attendanceData[$date])) {
                        $status = $attendanceData[$date]['status'];
                        if ($status === 'Present') echo "<span class='status-symbol text-success'>✔</span>";
                        if ($status === 'Absent')  echo "<span class='status-symbol text-danger'>✖</span>";
                        if ($status === 'Late')    echo "<span class='status-symbol text-warning'>⏰</span>";
                        if ($status === 'Sick')    echo "<span class='status-symbol text-info'>🤒</span>";
                    }

                    echo "</td>";
                    $day++; $currentWeekDay++;
                    if ($currentWeekDay==7 && $day <= $daysInMonth) { echo "</tr><tr>"; $currentWeekDay=0; }
                }
                while ($currentWeekDay<7) { echo "<td></td>"; $currentWeekDay++; }
                echo "</tr>";
                ?>
                </tbody>
            </table>
        </div>

        <!-- Month Navigation -->
        <nav>
            <ul class="pagination justify-content-center">
                <li class="page-item">
                    <a class="page-link" href="?student_id=<?= $student_id ?>&month=<?= $prevMonth ?>&year=<?= $prevYear ?>">&laquo; Prev</a>
                </li>
                <li class="page-item disabled"><span class="page-link"><?= $monthName ?></span></li>
                <li class="page-item">
                    <a class="page-link" href="?student_id=<?= $student_id ?>&month=<?= $nextMonth ?>&year=<?= $nextYear ?>">Next &raquo;</a>
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
    <?php endif; ?>
</div>

</body>
</html>
