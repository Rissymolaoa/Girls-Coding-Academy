<?php
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Only allow teachers
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.html");
    exit();
}

include("db.php"); // your DB connection

$teacher_id = $_SESSION['user_id'];
echo "Debug: teacher_id (from session) = $teacher_id<br>"; // Debug session

// Debug: Check users table for teacher
$userDebugQuery = $conn->prepare("SELECT user_id, username, email, role FROM users WHERE user_id = ?");
$userDebugQuery->bind_param("i", $teacher_id);
$userDebugQuery->execute();
$userDebugResult = $userDebugQuery->get_result()->fetch_assoc();
echo "Debug: Users table - " . ($userDebugResult ? print_r($userDebugResult, true) : "No user found") . "<br>";
$userDebugQuery->close();

// Debug: Check teachers table
$teacherDebugQuery = $conn->prepare("SELECT teacher_id, user_id, subject_speciality FROM teachers WHERE user_id = ?");
$teacherDebugQuery->bind_param("i", $teacher_id);
$teacherDebugQuery->execute();
$teacherDebugResult = $teacherDebugQuery->get_result()->fetch_assoc();
echo "Debug: Teachers table - " . ($teacherDebugResult ? print_r($teacherDebugResult, true) : "No teacher record found") . "<br>";
$teacherDebugQuery->close();

// Debug: List available teacher_ids in course_assignments
$debugQuery = $conn->query("SELECT DISTINCT teacher_id FROM course_assignments");
$teacherIds = [];
while ($row = $debugQuery->fetch_assoc()) {
    $teacherIds[] = $row['teacher_id'];
}
echo "Debug: Available teacher_ids in course_assignments: " . implode(", ", $teacherIds) . "<br>";

// Get teacher info
$teacherQuery = $conn->prepare("SELECT username, email, gender, phone FROM users WHERE user_id=? AND role='teacher'");
if (!$teacherQuery) {
    die("Teacher query preparation failed: " . $conn->error);
}
$teacherQuery->bind_param("i", $teacher_id);
$teacherQuery->execute();
$teacherInfo = $teacherQuery->get_result()->fetch_assoc();
if (!$teacherInfo) {
    die("No teacher found for user_id: $teacher_id");
}
$teacherQuery->close();

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
$courseQuery->bind_param("i", $teacher_id);
$courseQuery->execute();
$assignedCourses = $courseQuery->get_result();
echo "Debug: Number of assigned courses = " . $assignedCourses->num_rows . "<br>";
$courseQuery->close();

// Get enrolled students for each batch
$studentQuery = $conn->prepare("
    SELECT ce.enrollment_id, ce.batch_id, ce.status, u.username, u.email
    FROM course_enrollments ce
    INNER JOIN course_assignments ca ON ce.batch_id = ca.batch_id
    INNER JOIN users u ON ce.student_id = u.user_id
    INNER JOIN teachers t ON ca.teacher_id = t.teacher_id
    WHERE t.user_id = ? AND ce.status = 'active'
    ORDER BY ce.batch_id, u.username
");
if (!$studentQuery) {
    die("Student query preparation failed: " . $conn->error);
}
$studentQuery->bind_param("i", $teacher_id);
$studentQuery->execute();
$enrolledStudents = $studentQuery->get_result();
echo "Debug: Number of enrolled students = " . $enrolledStudents->num_rows . "<br>";
$studentQuery->close();

// Group students by batch_id for easier display
$studentsByBatch = [];
while ($student = $enrolledStudents->fetch_assoc()) {
    $studentsByBatch[$student['batch_id']][] = $student;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Teacher Dashboard</title>
<style>
:root{
    --primary:#7b2cbf;
    --accent:#5a189a;
    --muted:#f4f4f8;
    --card:#ffffff;
    --text:#222;
}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,Arial,Helvetica,sans-serif;background:var(--muted);color:var(--text);}
header{background:linear-gradient(90deg,var(--primary),var(--accent));color:#fff;padding:18px 24px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,0.12);}
header h1{margin:0;font-size:22px;}
header p{margin:4px 0 0;font-size:14px;}
.layout{display:flex;min-height:calc(100vh - 72px);}
.sidebar{width:220px;background:#34495e;padding:20px;display:flex;flex-direction:column;align-items:center;color:#fff;}
.sidebar img{width:92px;height:92px;border-radius:50%;object-fit:cover;border:3px solid #1abc9c;margin-bottom:12px;}
.sidebar h3{font-size:14px;margin:0 0 12px;text-align:center;}
.nav a{width:100%;display:block;color:#fff;text-decoration:none;padding:10px;border-radius:6px;margin:6px 0;text-align:left;}
.nav a.active, .nav a:hover{background:#1abc9c;color:#062018;}
.main{flex:1;padding:26px;}
.table-card{background:var(--card);padding:14px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.06);margin-bottom:20px;}
table{width:100%;border-collapse:collapse;font-size:14px;}
th,td{padding:10px;border-bottom:1px solid #732d91;text-align:left;}
th{background:linear-gradient(90deg,var(--primary),var(--accent));color:#fff;}
footer{background:#34495e;color:#fff;padding:12px;text-align:center;margin-top:auto;}
.status-active{color:green;font-weight:bold;}
.status-inactive{color:red;font-weight:bold;}
.assign-btn{display:inline-block;padding:6px 12px;background:#1abc9c;color:#fff;border-radius:4px;text-decoration:none;}
.assign-btn:hover{background:#16a085;}
</style>
</head>
<body>
<header>
<h1>Welcome, <?= htmlspecialchars($teacherInfo['username']) ?></h1>
<p>Email: <?= htmlspecialchars($teacherInfo['email']) ?> | Gender: <?= htmlspecialchars($teacherInfo['gender']) ?> | Phone: <?= htmlspecialchars($teacherInfo['phone']) ?></p>
</header>

<div class="layout">
    <aside class="sidebar">
        <img src="admin.jpg" alt="Teacher">
        <h3>Teacher Dashboard</h3>
        <nav class="nav">
            <a href="teacher_dashboard.php" class="active">🏠 Dashboard</a>
            <a href="manage_courses.php">📚 Manage Own Courses</a>
            <a href="upload_materials.php">📂 Upload Materials</a>
            <a href="grade.php">📝 Grade</a>
            <a href="mark_attendance.php">✅ Mark Attendance</a>
            <a href="message_students.php">💬 Message Students</a>
            <a href="logout.php">🚪 Logout</a>
        </nav>
    </aside>

    <main class="main">
        <h2>Assigned Courses/Batches</h2>
        <div class="table-card">
        <?php if($assignedCourses->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Batch Code</th>
                    <th>Course Name</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $assignedCourses->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['batch_code']) ?></td>
                    <td><?= htmlspecialchars($row['courseName']) ?></td>
                    <td><?= htmlspecialchars($row['start_date']) ?></td>
                    <td><?= htmlspecialchars($row['end_date']) ?></td>
                    <td class="<?= $row['status'] === 'active' ? 'status-active' : 'status-inactive' ?>"><?= htmlspecialchars($row['status']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>You are not assigned to any courses yet.</p>
        <?php endif; ?>
        </div>

        <h2>Enrolled Students</h2>
        <?php if($assignedCourses->num_rows > 0): ?>
        <?php mysqli_data_seek($assignedCourses, 0); // Reset pointer to loop through courses again ?>
        <?php while($course = $assignedCourses->fetch_assoc()): ?>
        <div class="table-card">
            <h3>Batch: <?= htmlspecialchars($course['batch_code']) ?> (<?= htmlspecialchars($course['courseName']) ?>)</h3>
            <?php if(isset($studentsByBatch[$course['batch_id']]) && count($studentsByBatch[$course['batch_id']]) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Email</th>
                        <th>Enrollment Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($studentsByBatch[$course['batch_id']] as $student): ?>
                    <tr>
                        <td><?= htmlspecialchars($student['username']) ?></td>
                        <td><?= htmlspecialchars($student['email']) ?></td>
                        <td class="status-active"><?= htmlspecialchars($student['status']) ?></td>
                        <td><a href="assign_activity.php?enrollment_id=<?= $student['enrollment_id'] ?>" class="assign-btn">Assign Activity</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>No students enrolled in this batch.</p>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
        <?php else: ?>
        <p>No batches assigned, so no students to display.</p>
        <?php endif; ?>
    </main>
</div>

<footer>
&copy; <?= date('Y') ?> Girls Coding Academy
</footer>

</body>
</html>