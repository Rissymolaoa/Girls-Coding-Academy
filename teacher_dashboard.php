<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'db.php';

// Ensure teacher is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch teacher info
$teacherQuery = $conn->prepare("SELECT username, email, gender, phone FROM users WHERE user_id=? AND role='teacher'");
if (!$teacherQuery) {
    die("Teacher query preparation failed: " . $conn->error);
}
$teacherQuery->bind_param("i", $user_id);
$teacherQuery->execute();
$teacherInfo = $teacherQuery->get_result()->fetch_assoc();
if (!$teacherInfo) {
    die("No teacher found for user_id: $user_id");
}
$teacherQuery->close();

// Fetch teacher_id
$teacherIdQuery = $conn->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
if (!$teacherIdQuery) {
    die("Teacher ID query preparation failed: " . $conn->error);
}
$teacherIdQuery->bind_param("i", $user_id);
$teacherIdQuery->execute();
$teacherIdResult = $teacherIdQuery->get_result();
if ($teacherIdResult->num_rows === 0) {
    die("Error: Teacher profile not found. Please contact the administrator.");
}
$teacher = $teacherIdResult->fetch_assoc();
$teacher_id = (int)$teacher['teacher_id'];
$teacherIdQuery->close();

// Fetch total stats
// Total batches
$totalBatchesQuery = $conn->prepare("
    SELECT COUNT(DISTINCT ca.batch_id) AS total_batches
    FROM course_assignments ca
    WHERE ca.teacher_id = ?
");
$totalBatchesQuery->bind_param("i", $teacher_id);
$totalBatchesQuery->execute();
$totalBatchesResult = $totalBatchesQuery->get_result()->fetch_assoc();
$total_batches = $totalBatchesResult['total_batches'];
$totalBatchesQuery->close();

// Total learners (total enrollments, not unique students)
$totalLearnersQuery = $conn->prepare("
    SELECT COUNT(*) AS total_learners
    FROM course_enrollments ce
    INNER JOIN course_assignments ca ON ce.batch_id = ca.batch_id
    WHERE ca.teacher_id = ? AND ce.status = 'active'
");
$totalLearnersQuery->bind_param("i", $teacher_id);
$totalLearnersQuery->execute();
$totalLearnersResult = $totalLearnersQuery->get_result()->fetch_assoc();
$total_learners = $totalLearnersResult['total_learners'];
$totalLearnersQuery->close();

// Total activities
$totalActivitiesQuery = $conn->prepare("
    SELECT COUNT(*) AS total_activities
    FROM activities
    WHERE teacher_id = ?
");
$totalActivitiesQuery->bind_param("i", $teacher_id);
$totalActivitiesQuery->execute();
$totalActivitiesResult = $totalActivitiesQuery->get_result()->fetch_assoc();
$total_activities = $totalActivitiesResult['total_activities'];
$totalActivitiesQuery->close();

// Total internals (grades recorded)
$totalInternalsQuery = $conn->prepare("
    SELECT COUNT(*) AS total_internals
    FROM internal_grades g
    INNER JOIN course_enrollments ce ON g.student_id = ce.student_id AND g.batch_id = ce.batch_id
    INNER JOIN course_assignments ca ON ce.batch_id = ca.batch_id
    WHERE ca.teacher_id = ?
");
if (!$totalInternalsQuery) {
    die("Total internals query preparation failed: " . $conn->error);
}
$totalInternalsQuery->bind_param("i", $teacher_id);
$totalInternalsQuery->execute();
$totalInternalsResult = $totalInternalsQuery->get_result()->fetch_assoc();
$total_internals = $totalInternalsResult['total_internals'];
$totalInternalsQuery->close();

// Get courses assigned to teacher
$courseQuery = $conn->prepare("
    SELECT ca.assignment_id, b.batch_id, b.batch_code, b.start_date, b.end_date, b.status, c.courseName
    FROM course_assignments ca
    INNER JOIN batches b ON ca.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    INNER JOIN teachers t ON ca.teacher_id = t.teacher_id
    WHERE t.user_id = ?
    ORDER BY b.start_date DESC
");
if (!$courseQuery) {
    die("Course query preparation failed: " . $conn->error);
}
$courseQuery->bind_param("i", $user_id);
$courseQuery->execute();
$assignedCourses = $courseQuery->get_result();
$courseQuery->close();

// Fetch batch stats
$batchStats = [];
while ($batch = $assignedCourses->fetch_assoc()) {
    $batch_id = (int)$batch['batch_id'];
    $studentCountQuery = $conn->prepare("SELECT COUNT(*) AS student_count FROM course_enrollments WHERE batch_id = ? AND status = 'active'");
    $studentCountQuery->bind_param("i", $batch_id);
    $studentCountQuery->execute();
    $studentCountResult = $studentCountQuery->get_result()->fetch_assoc();
    $student_count = $studentCountResult['student_count'];
    $studentCountQuery->close();

    $activityCountQuery = $conn->prepare("SELECT COUNT(*) AS total_activities FROM activities WHERE batch_id = ?");
    $activityCountQuery->bind_param("i", $batch_id);
    $activityCountQuery->execute();
    $activityCountResult = $activityCountQuery->get_result()->fetch_assoc();
    $activity_count = $activityCountResult['total_activities'];
    $activityCountQuery->close();

    $gradeCountQuery = $conn->prepare("SELECT COUNT(*) AS grade_count FROM internal_grades WHERE batch_id = ?");
    $gradeCountQuery->bind_param("i", $batch_id);
    $gradeCountQuery->execute();
    $gradeCountResult = $gradeCountQuery->get_result()->fetch_assoc();
    $grade_count = $gradeCountResult['grade_count'];
    $gradeCountQuery->close();

    $batchStats[] = [
        'batch_code' => $batch['batch_code'],
        'courseName' => $batch['courseName'],
        'start_date' => $batch['start_date'],
        'end_date' => $batch['end_date'],
        'status' => $batch['status'],
        'student_count' => $student_count,
        'activity_count' => $activity_count,
        'grade_count' => $grade_count,
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Teacher Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
<style>
body {
    font-family: Inter, Arial, Helvetica, sans-serif;
    background: #f4f6f9;
}
header {
    background: linear-gradient(90deg, #7b2cbf, #5a189a);
    color: #fff;
}
header h1 {
    margin: 0;
    font-size: 22px;
}
</style>
</head>
<body>
<header class="py-3 px-4 text-center">
    <h1>Welcome, <?= htmlspecialchars($teacherInfo['username']) ?></h1>
    <p class="mb-0">Email: <?= htmlspecialchars($teacherInfo['email']) ?> | Gender: <?= htmlspecialchars($teacherInfo['gender']) ?> | Phone: <?= htmlspecialchars($teacherInfo['phone']) ?></p>
</header>

<div class="container-fluid d-flex flex-nowrap" style="min-height: calc(100vh - 70px);">
    <nav class="col-md-3 col-xl-2 bg-dark text-white p-3 vh-100" style="min-width:220px;">
        <div class="text-center mb-4">
            <img src="admin.png" class="rounded-circle border border-info mb-2" width="92" height="92" alt="Teacher">
            <h5>Teacher Dashboard</h5>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item mb-2"><a class="nav-link text-white active" href="teacher_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a></li>
            <li class="nav-item mb-2"><a class="nav-link text-white" href="manage_teacher_courses.php"><i class="bi bi-journal-bookmark"></i> Manage Courses</a></li>
            <li class="nav-item mb-2"><a class="nav-link text-white" href="upload_materials.php"><i class="bi bi-folder"></i> Upload Materials</a></li>
            <li class="nav-item mb-2"><a class="nav-link text-white" href="grades.php"><i class="bi bi-pencil-square"></i> Grade</a></li>
            <li class="nav-item mb-2"><a class="nav-link text-white" href="mark_attendance.php"><i class="bi bi-check-circle"></i> Mark Attendance</a></li>
            <li class="nav-item mb-2"><a class="nav-link text-white" href="message_students.php"><i class="bi bi-chat-dots"></i> Message Students</a></li>
            <li class="nav-item mb-2"><a class="nav-link text-white" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
    </nav>

    <main class="col py-4">
        <h2>Dashboard Overview</h2>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Total Batches</h5>
                        <p class="card-text display-4"><?= $total_batches ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Total Learners</h5>
                        <p class="card-text display-4"><?= $total_learners ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Total Activities</h5>
                        <p class="card-text display-4"><?= $total_activities ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Total Internals Graded</h5>
                        <p class="card-text display-4"><?= $total_internals ?></p>
                    </div>
                </div>
            </div>
        </div>

        <h2>Batches Overview</h2>
        <div class="table-responsive mb-4">
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Batch Code</th>
                        <th>Course Name</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Students</th>
                        <th>Activities</th>
                        <th>Grades</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($batchStats as $stat): ?>
                    <tr>
                        <td><?= htmlspecialchars($stat['batch_code']) ?></td>
                        <td><?= htmlspecialchars($stat['courseName']) ?></td>
                        <td><?= htmlspecialchars($stat['start_date']) ?></td>
                        <td><?= htmlspecialchars($stat['end_date']) ?></td>
                        <td><span class="badge <?= $stat['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= htmlspecialchars($stat['status']) ?></span></td>
                        <td><?= $stat['student_count'] ?></td>
                        <td><?= $stat['activity_count'] ?></td>
                        <td><?= $stat['grade_count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<footer class="bg-dark text-white text-center py-3 mt-4">
    &copy; <?= date('Y') ?> Girls Coding Academy
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>