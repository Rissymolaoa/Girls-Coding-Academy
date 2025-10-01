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
        font-family: Arial, sans-serif;
        background-color: #f8f9fa;
        margin: 0;
        padding: 0;
        height: 100vh;
        overflow: hidden;
    }
    .container-flex {
        display: flex;
        height: 100vh;
    }
    .sidebar {
        width: 250px;
        background-color: #343a40;
        color: white;
        padding: 20px;
        display: flex;
        flex-direction: column;
        align-items: center;
        flex-shrink: 0;
    }
    .sidebar img {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        margin-bottom: 15px;
        object-fit: cover;
        border: 2px solid #1abc9c;
    }
    .sidebar h3 {
        margin-bottom: 30px;
        font-weight: bold;
        text-align: center;
    }
    .sidebar a {
        width: 100%;
        color: white;
        padding: 12px 15px;
        margin: 5px 0;
        border-radius: 6px;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: background-color 0.3s ease;
        font-weight: 500;
    }
    .sidebar a:hover,
    .sidebar a.active {
        background-color: #495057;
    }
    .content {
        flex: 1;
        padding: 30px 40px;
        overflow-y: auto;
    }
    h2 {
        margin-bottom: 30px;
    }
    .stats {
        display: flex;
        gap: 20px;
        margin-bottom: 40px;
    }
    .stat-card {
        background-color: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border-radius: 12px;
        padding: 25px 30px;
        flex: 1;
        text-align: center;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        cursor: default;
        border: 4px solid transparent;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }
    .stat-card h3 {
        font-size: 1.25rem;
        margin-bottom: 10px;
        color: #333;
    }
    .stat-card p {
        font-size: 2.5rem;
        font-weight: bold;
        margin: 0;
        color: #1abc9c;
    }
    .stat-card.border-primary { border-color: #0d6efd; }
    .stat-card.border-success { border-color: #198754; }
    .stat-card.border-info { border-color: #0dcaf0; }
    .section {
        margin-bottom: 40px;
    }
    .section h3 {
        margin-bottom: 20px;
        font-weight: bold;
        color: #333;
    }
    ul {
        padding-left: 20px;
    }
    ul li {
        margin-bottom: 8px;
        color: #555;
    }
</style>
</head>
<body>

<div class="container-flex">
    <!-- Sidebar -->
    <nav class="sidebar" aria-label="Student navigation">
        <img src="uploads/students/<?= htmlspecialchars($studentInfo['photo']) ?>" alt="Student Profile Picture" />
        <h3>Navigation</h3>
        <a href="student.php" class="active"><i class="bi bi-house-door"></i> Home</a>
        <a href="student_profile.php"><i class="bi bi-person-circle"></i> My Profile</a>
        <a href="student_courses.php"><i class="bi bi-journal-bookmark"></i> My Courses</a>
        <a href="tasks.php"><i class="bi bi-list-task"></i> My Tasks</a>
        <a href="enroll.php"><i class="bi bi-list-task"></i> Enroll</a>
        <a href="student_calendar.php"><i class="bi bi-calendar-event"></i> My Calendar</a>
        <a href="attendance.php"><i class="bi bi-card-checklist"></i> My Attendance</a>
        <a href="student_marks.php"><i class="bi bi-bar-chart-line-fill"></i> My Grades</a>
        <a href="student_gradebook.php"><i class="bi bi-graph-up"></i> My Performance</a>
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>

    <!-- Main Content -->
    <main class="content" role="main">
        <h2>Welcome, <?= htmlspecialchars($studentInfo['username']) ?>!</h2>
        <p>You are logged in as a <strong><?= htmlspecialchars($studentInfo['role']) ?></strong>.</p>

        <!-- Summary Cards -->
        <div class="stats" aria-label="Quick stats summary">
            <?php foreach ($stats as $stat): ?>
            <div class="stat-card <?= $stat['border'] ?>">
                <i class="bi <?= $stat['icon'] ?> fs-1 text-secondary mb-3"></i>
                <h3><?= htmlspecialchars($stat['label']) ?></h3>
                <p><?= htmlspecialchars($stat['value']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Tasks by Teachers -->
        <section class="section" aria-label="Tasks assigned by teachers">
            <h3>Tasks Given by Teachers</h3>
            <ul>
                <?php if (!empty($tasks)): ?>
                    <?php foreach ($tasks as $task): ?>
                        <li>
                            <strong><?= htmlspecialchars($task['due_date']) ?>:</strong> 
                            <?= htmlspecialchars($task['task_title']) ?>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No tasks assigned yet.</li>
                <?php endif; ?>
            </ul>
        </section>
    </main>
</div>

</body>
</html>
