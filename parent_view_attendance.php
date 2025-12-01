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

// Fetch parent details for sidebar
$parent_details_sql = "SELECT u.firstName, u.lastName, p.photo FROM users u LEFT JOIN parents p ON u.user_id = p.user_id WHERE u.user_id = ?";
$stmt_details = $conn->prepare($parent_details_sql);
$stmt_details->bind_param("i", $user_id);
$stmt_details->execute();
$parent_details_result = $stmt_details->get_result();
$parent_details = $parent_details_result->fetch_assoc();

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance View - Parent Dashboard | Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --secondary-color: #8b5cf6;
            --accent-color: #06b6d4;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --bg-light: #f8fafc;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
        }
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #1e293b 0%, #334155 100%);
            color: white;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        .sidebar.collapsed {
            transform: translateX(-260px);
        }
        .sidebar-header {
            padding: 2rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.2);
            margin-bottom: 1rem;
            object-fit: cover;
        }
        .sidebar h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.8);
            padding: 0.875rem 1.5rem;
            text-decoration: none;
            border-radius: 0 20px 20px 0;
            margin: 0.25rem 0;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(99, 102, 241, 0.2);
            color: var(--primary-color);
            transform: translateX(5px);
        }
        .sidebar .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            font-size: 1.1rem;
        }
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }
        .main-content.expanded {
            margin-left: 0;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
        }
        .page-header p {
            color: var(--text-muted);
            margin: 0;
            font-size: 1.1rem;
        }
        .child-selector {
            max-width: 400px;
            margin-bottom: 2rem;
        }
        .calendar-container {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .month-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .month-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        .nav-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 8px;
            color: white;
            padding: 0.5rem 1rem;
            transition: all 0.2s ease;
        }
        .nav-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        .calendar-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .calendar-table thead th {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
        }
        .calendar-table tbody td {
            height: 120px;
            vertical-align: top;
            border: 1px solid var(--border-color);
            position: relative;
            transition: all 0.2s ease;
        }
        .calendar-table tbody td:hover {
            background: rgba(99, 102, 241, 0.05);
            transform: scale(1.02);
        }
        .calendar-table tbody td.today-cell {
            background: linear-gradient(135deg, #fef3c7, #fde68a) !important;
            border: 2px solid var(--warning-color) !important;
        }
        .day-number {
            font-weight: 600;
            display: block;
            text-align: right;
            padding: 0.5rem 0.75rem 0;
            color: var(--text-dark);
            font-size: 0.95rem;
        }
        .status-symbol {
            font-size: 1.5rem;
            display: block;
            margin: 0.5rem auto;
            text-align: center;
        }
        .status-present { color: var(--success-color); }
        .status-absent { color: var(--danger-color); }
        .status-late { color: var(--warning-color); }
        .status-sick { color: var(--info-color); }
        .legend {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            color: var(--text-muted);
        }
        .legend-symbol {
            font-size: 1.2rem;
        }
        .no-child {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
        }
        .no-child i {
            font-size: 5rem;
            display: block;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        .toggle-sidebar {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-dark);
            z-index: 1001;
            position: fixed;
            top: 1rem;
            left: 1rem;
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-260px); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .page-header h1 { font-size: 1.5rem; }
            .calendar-table tbody td { height: 80px; }
            .legend { gap: 1rem; }
        }
        @media (max-width: 768px) {
            .toggle-sidebar { display: block; }
        }
    </style>
</head>
<body>
    <button class="toggle-sidebar" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="<?= $parent_details['photo'] ?? 'default-parent-avatar.png' ?>" alt="Parent Avatar" onerror="this.src='default-avatar.png'">
            <h3><?= htmlspecialchars($parent_details['firstName'] ?? 'Parent') ?></h3>
        </div>
        <ul class="nav flex-column p-0 m-0">
            <li class="nav-item">
                <a href="parents_dashboard.php" class="nav-link" onclick="showSection('dashboard')"><i class="bi bi-house-door"></i> Dashboard</a>
            </li>
            <li class="nav-item">
                <a href="children.php" class="nav-link" onclick="showSection('children')"><i class="bi bi-people"></i> My Children</a>
            </li>
            <li class="nav-item">
                <a href="parent_view_attendance.php" class="nav-link active" target="_blank"><i class="bi bi-card-checklist"></i> Attendance</a>
            </li>
            <li class="nav-item">
                <a href="parent_view_performance.php" class="nav-link" target="_blank"><i class="bi bi-graph-up"></i> Performance</a>
            </li>
            <li class="nav-item">
                <a href="parent_view_materials.php" class="nav-link" target="_blank"><i class="bi bi-folder"></i> Materials</a>
            </li>
            <li class="nav-item">
                <a href="parent_messages.php" class="nav-link" target="_blank"><i class="bi bi-envelope"></i> Messages</a>
            </li>
           
            <li class="nav-item">
                <a href="parent_profile.php" class="nav-link" onclick="showSection('profile')"><i class="bi bi-person-circle"></i> Profile</a>
            </li>
            <li class="nav-item">
                <a href="parent_payments.php" class="nav-link "><i class="bi bi-credit-card"></i> Payments</a>
            </li>
             <li class="nav-item">
                <a href="parent_invoices_print.php" class="nav-link "><i class="bi bi-credit-card"></i> Invoices</a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content" id="main-content">
        <header class="page-header">
            <div>
                <h1>Attendance Overview</h1>
                <p>Track your child's daily attendance with ease.</p>
            </div>
        </header>

        <?php if (count($children) > 0): ?>
            <!-- Child Selector -->
            <div class="child-selector">
                <form method="get" class="input-group">
                    <label class="input-group-text" for="student_id">Select Child</label>
                    <select class="form-select" id="student_id" name="student_id" onchange="this.form.submit()">
                        <?php foreach ($children as $c): ?>
                            <option value="<?= $c['student_id'] ?>" <?= $c['student_id']==$student_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['firstName'].' '.$c['lastName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($student_id): 
                $selected_child = null;
                foreach ($children as $c) {
                    if ($c['student_id'] == $student_id) {
                        $selected_child = $c;
                        break;
                    }
                }
            ?>
                <div class="calendar-container">
                    <div class="month-nav">
                        <button class="nav-btn" onclick="window.location.href='?student_id=<?= $student_id ?>&month=<?= $prevMonth ?>&year=<?= $prevYear ?>'">
                            <i class="bi bi-chevron-left"></i> Previous
                        </button>
                        <h2 class="month-title"><?= $monthName ?></h2>
                        <button class="nav-btn" onclick="window.location.href='?student_id=<?= $student_id ?>&month=<?= $nextMonth ?>&year=<?= $nextYear ?>'">
                            Next <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="calendar-table">
                            <thead>
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
                                $statusClass = '';
                                $statusSymbol = '';
                                if (isset($attendanceData[$date])) {
                                    $status = $attendanceData[$date]['status'];
                                    if ($status === 'Present') { 
                                        $statusClass = 'status-present'; 
                                        $statusSymbol = '✔'; 
                                    }
                                    if ($status === 'Absent') { 
                                        $statusClass = 'status-absent'; 
                                        $statusSymbol = '✖'; 
                                    }
                                    if ($status === 'Late') { 
                                        $statusClass = 'status-late'; 
                                        $statusSymbol = '⏰'; 
                                    }
                                    if ($status === 'Sick') { 
                                        $statusClass = 'status-sick'; 
                                        $statusSymbol = '🤒'; 
                                    }
                                }
                                echo "<td class='{$cellClass}'>";
                                echo "<span class='day-number'>{$day}</span>";
                                if ($statusSymbol) {
                                    echo "<span class='status-symbol {$statusClass}'>{$statusSymbol}</span>";
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
                    <div class="legend">
                        <div class="legend-item">
                            <span class="legend-symbol status-present">✔</span> Present
                        </div>
                        <div class="legend-item">
                            <span class="legend-symbol status-absent">✖</span> Absent
                        </div>
                        <div class="legend-item">
                            <span class="legend-symbol status-late">⏰</span> Late
                        </div>
                        <div class="legend-item">
                            <span class="legend-symbol status-sick">🤒</span> Sick
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-child">
                <i class="bi bi-card-checklist"></i>
                <h3>No Children Enrolled</h3>
                <p>Link a child to your account to view attendance records.</p>
                <a href="children.php" class="btn btn-modern">Manage Children</a>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            sidebar.classList.toggle('show');
            mainContent.classList.toggle('expanded');
        }

        // Close sidebar on outside click (mobile)
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.toggle-sidebar');
            if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target) && window.innerWidth <= 768) {
                sidebar.classList.remove('show');
                document.getElementById('main-content').classList.remove('expanded');
            }
        });
    </script>
</body>
</html>