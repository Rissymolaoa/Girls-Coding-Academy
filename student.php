<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

include("db.php");

// Get student info (join users and students)
$user_id = $_SESSION['user_id'];
$stmt_student = $conn->prepare("
    SELECT s.student_id, s.photo, u.username, u.email, u.role
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    WHERE s.user_id = ?
");
$stmt_student->bind_param("i", $user_id);
$stmt_student->execute();
$result_student = $stmt_student->get_result();
$studentInfo = $result_student->fetch_assoc();
$student_id = $studentInfo['student_id'];

// === Summary Stats from DB ===

// Activities count
$res = $conn->query("
    SELECT COUNT(*) AS total
    FROM activities a
    JOIN course_enrollments e ON a.batch_id = e.batch_id
    WHERE e.student_id = $student_id
");
$activities = $res->fetch_assoc()['total'] ?? 0;

// Attendance %
$res = $conn->query("SELECT 
    (SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) / COUNT(*)) * 100 AS attendance_pct
    FROM attendance WHERE student_id = $student_id");
$attendance = number_format($res->fetch_assoc()['attendance_pct'] ?? 0, 2) . "%";

// Overall Performance (average % from internal_grades)
$res = $conn->query("SELECT 
    AVG((COALESCE(test_1,0)+COALESCE(test_2,0)+COALESCE(test_3,0)+COALESCE(test_4,0)+
         COALESCE(test_5,0)+COALESCE(test_6,0)+COALESCE(test_7,0)+COALESCE(end_examination,0)) / 800 * 100) 
    AS perf FROM internal_grades WHERE student_id = $student_id");
$performance = number_format($res->fetch_assoc()['perf'] ?? 0, 2) . "%";

// Stats array for cards (with icons + smart borders)
$stats = [
    ["label" => "Activities", "value" => $activities, "icon" => "bi-activity", "border" => "border-primary"],
    ["label" => "Attendance %", "value" => $attendance, "icon" => "bi-calendar-check", "border" => "border-success"],
    ["label" => "Overall Performance", "value" => $performance, "icon" => "bi-bar-chart-line", "border" => "border-info"]
];

// Tasks given by teachers
$tasks = [];
$res = $conn->prepare("
    SELECT a.activity_id, a.title as task_title, a.description, a.due_date, a.resource_file, c.courseName
    FROM activities a
    INNER JOIN batches b ON a.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    INNER JOIN course_enrollments ce ON b.batch_id = ce.batch_id
    WHERE ce.student_id = ? AND a.status = 'active'
    ORDER BY a.due_date ASC
");
$res->bind_param("i", $student_id);
$res->execute();
$result_tasks = $res->get_result();
while ($row = $result_tasks->fetch_assoc()) {
    $tasks[] = $row;
}

// Determine current page for active sidebar link
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Student Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        margin: 0;
        padding: 0;
        height: 100vh;
        overflow: hidden;
    }
    .container-flex {
        display: flex;
        height: 100vh;
    }
    h2 {
        margin-bottom: 20px;
        color: #2c3e50;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    }
    .stats {
        display: flex;
        gap: 25px;
        margin-bottom: 45px;
    }
    .stat-card {
        background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
        box-shadow: 0 5px 20px rgba(44, 62, 80, 0.1);
        border-radius: 15px;
        padding: 30px;
        flex: 1;
        text-align: center;
        transition: all 0.3s ease;
        cursor: default;
        border: 1px solid #e9ecef;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #e74c3c, #f39c12);
    }
    .stat-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 10px 30px rgba(231, 76, 60, 0.2);
    }
    .stat-card h3 {
        font-size: 1.1rem;
        margin-bottom: 12px;
        color: #7f8c8d;
        font-weight: 500;
    }
    .stat-card p {
        font-size: 2.8rem;
        font-weight: bold;
        margin: 0;
        color: #2c3e50;
    }
    .stat-card i {
        font-size: 3rem;
        color: #bdc3c7;
        margin-bottom: 15px;
        opacity: 0.7;
    }
    .stat-card.border-primary { border-left: 5px solid #0d6efd; }
    .stat-card.border-success { border-left: 5px solid #198754; }
    .stat-card.border-info { border-left: 5px solid #0dcaf0; }
    .section {
        margin-bottom: 45px;
        background: rgba(255,255,255,0.8);
        padding: 25px;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .section h3 {
        margin-bottom: 20px;
        font-weight: 600;
        color: #2c3e50;
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 8px;
    }
    .tasks-list {
        list-style: none;
        padding: 0;
    }
    .tasks-list li {
        background: white;
        margin-bottom: 12px;
        padding: 15px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border-left: 4px solid #f39c12;
        transition: all 0.3s ease;
    }
    .tasks-list li:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .tasks-list li strong {
        color: #e74c3c;
        display: block;
        margin-bottom: 5px;
    }
    .tasks-list li a {
        color: #2c3e50;
        text-decoration: none;
    }
    .tasks-list li a:hover {
        color: #e74c3c;
    }
    .no-tasks {
        text-align: center;
        color: #7f8c8d;
        font-style: italic;
        padding: 20px;
    }
</style>
</head>
<body>

<div class="container-flex">
    <!-- Include the consistent navigation -->
    <?php include("student_navigation.php"); ?>

    <!-- Main Content -->
    <main class="content" role="main">
        <h2><i class="bi bi-house-door"></i> Welcome, <?= htmlspecialchars($studentInfo['username']) ?>!</h2>
        <p class="mb-4">Here's a quick overview of your progress.</p>

        <!-- Summary Cards -->
        <div class="stats" aria-label="Quick stats summary">
            <?php foreach ($stats as $stat): ?>
            <div class="stat-card <?= $stat['border'] ?>">
                <i class="bi <?= $stat['icon'] ?>"></i>
                <h3><?= htmlspecialchars($stat['label']) ?></h3>
                <p><?= htmlspecialchars($stat['value']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Tasks by Teachers -->
        <section class="section" aria-label="Tasks assigned by teachers">
            <h3><i class="bi bi-list-task"></i> Tasks Given by Teachers</h3>
            <?php if (!empty($tasks)): ?>
                <ul class="tasks-list">
                    <?php foreach ($tasks as $task): ?>
                        <li>
                            <strong><?= htmlspecialchars($task['due_date']) ?> - <?= htmlspecialchars($task['courseName']) ?></strong>
                            <a href="view_activity.php?activity_id=<?= (int)$task['activity_id'] ?>"><?= htmlspecialchars($task['task_title']) ?></a>
                            <?php if ($task['resource_file']): ?>
                                <br><small><i class="bi bi-paperclip"></i> <a href="<?= htmlspecialchars($task['resource_file']) ?>" target="_blank">Resource</a></small>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="no-tasks">No tasks assigned yet. Check your <a href="student_courses.php">courses</a> for updates.</div>
            <?php endif; ?>
        </section>
    </main>
</div>

</body>
</html>