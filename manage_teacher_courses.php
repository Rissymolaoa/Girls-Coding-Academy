<?php
session_start();
include 'db.php'; // DB connection (MySQLi)

// Ensure teacher is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$teacher_user_id = $_SESSION['user_id'];

// --- Handle form actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Remove student from course
    if (isset($_POST['remove_student'])) {
        $enrollment_id = intval($_POST['enrollment_id']);
        $remove_query = "UPDATE course_enrollments SET status = 'inactive' WHERE enrollment_id = ?";
        $stmt = $conn->prepare($remove_query);
        $stmt->bind_param("i", $enrollment_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: manage_own_courses.php");
    exit();
}

try {
    // Fetch teacher info
    $teacher_query = "SELECT username, email, gender, phone FROM users WHERE user_id=? AND role='teacher'";
    $teacher_stmt = $conn->prepare($teacher_query);
    $teacher_stmt->bind_param("i", $teacher_user_id);
    $teacher_stmt->execute();
    $teacher_info = $teacher_stmt->get_result()->fetch_assoc();
    $teacher_stmt->close();

    // Fetch assigned courses
    $course_query = "
        SELECT DISTINCT ca.batch_id, b.batch_code, c.courseName AS course_name, 
               b.start_date, b.end_date, b.status
        FROM course_assignments ca
        INNER JOIN teachers t ON ca.teacher_id = t.teacher_id
        INNER JOIN batches b ON ca.batch_id = b.batch_id
        INNER JOIN courses c ON b.course_id = c.course_id
        WHERE t.user_id = ?
        ORDER BY b.start_date
    ";
    $course_stmt = $conn->prepare($course_query);
    $course_stmt->bind_param("i", $teacher_user_id);
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();
    $courses = [];
    while ($row = $course_result->fetch_assoc()) {
        $courses[] = $row;
    }

    // Fetch enrolled students
    $student_query = "
        SELECT ce.enrollment_id, ce.batch_id, u.username, u.email, u.firstName, u.lastName
        FROM course_enrollments ce
        INNER JOIN students s ON ce.student_id = s.student_id
        INNER JOIN users u ON s.user_id = u.user_id
        INNER JOIN course_assignments ca ON ce.batch_id = ca.batch_id
        INNER JOIN teachers t ON ca.teacher_id = t.teacher_id
        WHERE t.user_id = ? AND ce.status = 'active'
        ORDER BY ce.batch_id, u.username
    ";
    $student_stmt = $conn->prepare($student_query);
    $student_stmt->bind_param("i", $teacher_user_id);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();
    $students = [];
    while ($row = $student_result->fetch_assoc()) {
        $students[] = $row;
    }

    // Group students by batch
    $students_by_batch = [];
    foreach ($students as $student) {
        $students_by_batch[$student['batch_id']][] = $student;
    }
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Own Courses</title>
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
.remove-btn{display:inline-block;padding:6px 12px;background:#e74c3c;color:#fff;border:none;border-radius:4px;cursor:pointer;}
.remove-btn:hover{background:#c0392b;}
</style>
</head>
<body>
<header>
<h1>Welcome, <?= htmlspecialchars($teacher_info['username']) ?></h1>
<p>Email: <?= htmlspecialchars($teacher_info['email']) ?> | Gender: <?= htmlspecialchars($teacher_info['gender']) ?> | Phone: <?= htmlspecialchars($teacher_info['phone']) ?></p>
</header>

<div class="layout">
    <aside class="sidebar">
        <img src="admin.jpg" alt="Teacher">
        <h3>Teacher Dashboard</h3>
        <nav class="nav">
            <a href="teacher_dashboard.php">🏠 Dashboard</a>
            <a href="manage_own_courses.php" class="active">📚 Manage Own Courses</a>
            <a href="upload_materials.php">📂 Upload Materials</a>
            <a href="grade.php">📝 Grade</a>
            <a href="mark_attendance.php">✅ Mark Attendance</a>
            <a href="message_students.php">💬 Message Students</a>
            <a href="logout.php">🚪 Logout</a>
        </nav>
    </aside>

    <main class="main">
        <h2>Manage Own Courses</h2>
        <?php if (count($courses) > 0): ?>
            <?php foreach ($courses as $course): ?>
                <div class="table-card">
                    <h3><?= htmlspecialchars($course['course_name']) ?> (<?= htmlspecialchars($course['batch_code']) ?>)</h3>
                    <p><strong>Start:</strong> <?= htmlspecialchars($course['start_date']) ?> |
                       <strong>End:</strong> <?= htmlspecialchars($course['end_date']) ?> |
                       <strong>Status:</strong> <?= htmlspecialchars($course['status']) ?></p>

                    <h4>Enrolled Students</h4>
                    <?php if (isset($students_by_batch[$course['batch_id']])): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students_by_batch[$course['batch_id']] as $student): ?>
                                <tr>
                                    <td><?= htmlspecialchars($student['firstName'] . " " . $student['lastName']) ?></td>
                                    <td><?= htmlspecialchars($student['email']) ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" 
                                              onsubmit="return confirm('Are you sure you want to remove this student?');">
                                            <input type="hidden" name="enrollment_id" value="<?= $student['enrollment_id'] ?>">
                                            <button type="submit" name="remove_student" class="remove-btn">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No students enrolled.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>You are not assigned to any courses.</p>
        <?php endif; ?>
    </main>
</div>

<footer>
&copy; <?= date('Y') ?> Girls Coding Academy
</footer>

</body>
</html>
