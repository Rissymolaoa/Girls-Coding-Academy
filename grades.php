<?php
session_start();

// Enable error reporting for debugging (remove or comment out in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.html");
    exit();
}

include("db.php"); // Your database connection

$teacher_id = $_SESSION['user_id'];

// Fetch teacher info
$teacherQuery = $conn->prepare("SELECT username, email, gender, phone FROM users WHERE user_id = ? AND role = 'teacher'");
if (!$teacherQuery) {
    die("Teacher query preparation failed: " . $conn->error);
}
$teacherQuery->bind_param("i", $teacher_id);
$teacherQuery->execute();
$teacherQuery->store_result();

$teacherQuery->bind_result($username, $email, $gender, $phone);
$teacherInfo = null;
if ($teacherQuery->fetch()) {
    $teacherInfo = [
        'username' => $username,
        'email' => $email,
        'gender' => $gender,
        'phone' => $phone
    ];
} else {
    die("No teacher found for user_id: $teacher_id");
}
$teacherQuery->close();

// Handle POST actions: add, edit, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $enrollment_id = (int)$_POST['enrollment_id'];
            $assignment_id = (int)$_POST['assignment_id'];
            $score = floatval($_POST['score']);
            $comments = trim($_POST['comments']);

            if ($score < 0 || $score > 100) {
                echo "Error: Score must be between 0 and 100.";
            } else {
                $insertStmt = $conn->prepare("
                    INSERT INTO grades (enrollment_id, assignment_id, score, comments)
                    VALUES (?, ?, ?, ?)
                ");
                if (!$insertStmt) {
                    die("Insert grade prepare failed: " . $conn->error);
                }
                $insertStmt->bind_param("iids", $enrollment_id, $assignment_id, $score, $comments);
                $insertStmt->execute();
                $insertStmt->close();
            }
        } elseif ($_POST['action'] === 'edit') {
            $grade_id = (int)$_POST['grade_id'];
            $score = floatval($_POST['score']);
            $comments = trim($_POST['comments']);

            if ($score < 0 || $score > 100) {
                echo "Error: Score must be between 0 and 100.";
            } else {
                $updateStmt = $conn->prepare("
                    UPDATE grades
                    SET score = ?, comments = ?
                    WHERE grade_id = ? AND enrollment_id IN (
                        SELECT ce.enrollment_id
                        FROM course_enrollments ce
                        INNER JOIN course_assignments ca ON ce.batch_id = ca.batch_id
                        INNER JOIN teachers t ON ca.teacher_id = t.teacher_id
                        WHERE t.user_id = ?
                    )
                ");
                if (!$updateStmt) {
                    die("Update grade prepare failed: " . $conn->error);
                }
                $updateStmt->bind_param("dsi", $score, $comments, $grade_id, $teacher_id);
                $updateStmt->execute();
                $updateStmt->close();
            }
        } elseif ($_POST['action'] === 'delete') {
            $grade_id = (int)$_POST['grade_id'];
            $deleteStmt = $conn->prepare("
                DELETE FROM grades
                WHERE grade_id = ? AND enrollment_id IN (
                    SELECT ce.enrollment_id
                    FROM course_enrollments ce
                    INNER JOIN course_assignments ca ON ce.batch_id = ca.batch_id
                    INNER JOIN teachers t ON ca.teacher_id = t.teacher_id
                    WHERE t.user_id = ?
                )
            ");
            if (!$deleteStmt) {
                die("Delete grade prepare failed: " . $conn->error);
            }
            $deleteStmt->bind_param("ii", $grade_id, $teacher_id);
            $deleteStmt->execute();
            $deleteStmt->close();
        }
    }
}

// Fetch assigned courses for the teacher
$courseStmt = $conn->prepare("
    SELECT ca.assignment_id, b.batch_id, b.batch_code, c.courseName
    FROM course_assignments ca
    INNER JOIN batches b ON ca.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    WHERE ca.teacher_id = (SELECT teacher_id FROM teachers WHERE user_id = ?)
    ORDER BY b.start_date DESC
");
if (!$courseStmt) {
    die("Course prepare failed: " . $conn->error);
}
$courseStmt->bind_param("i", $teacher_id);
$courseStmt->execute();
$courseStmt->store_result();

$assignedCourses = [];
$courseStmt->bind_result($assignment_id, $batch_id, $batch_code, $courseName);
while ($courseStmt->fetch()) {
    $assignedCourses[] = [
        'assignment_id' => $assignment_id,
        'batch_id' => $batch_id,
        'batch_code' => $batch_code,
        'courseName' => $courseName
    ];
}
$courseStmt->close();

// Fetch enrolled students grouped by batch
$studentStmt = $conn->prepare("
    SELECT ce.enrollment_id, ce.batch_id, u.username, u.email
    FROM course_enrollments ce
    INNER JOIN course_assignments ca ON ce.batch_id = ca.batch_id
    INNER JOIN students s ON ce.student_id = s.student_id
    INNER JOIN users u ON s.user_id = u.user_id
    INNER JOIN teachers t ON ca.teacher_id = t.teacher_id
    WHERE t.user_id = ? AND ce.status = 'active'
    ORDER BY ce.batch_id, u.username
");
if (!$studentStmt) {
    die("Student prepare failed: " . $conn->error);
}
$studentStmt->bind_param("i", $teacher_id);
$studentStmt->execute();
$studentStmt->store_result();

$studentsByBatch = [];
while ($studentStmt->fetch()) {
    $studentsByBatch[$batch_id][] = [
        'enrollment_id' => $enrollment_id,
        'username' => $username,
        'email' => $email
    ];
}
$studentStmt->close();

// Fetch grades grouped by batch
$gradesStmt = $conn->prepare("
    SELECT g.grade_id, g.enrollment_id, g.assignment_id, g.score, g.comments, ce.batch_id, u.username, u.email
    FROM grades g
    INNER JOIN course_enrollments ce ON g.enrollment_id = ce.enrollment_id
    INNER JOIN course_assignments ca ON ce.batch_id = ca.batch_id
    INNER JOIN students s ON ce.student_id = s.student_id
    INNER JOIN users u ON s.user_id = u.user_id
    INNER JOIN teachers t ON ca.teacher_id = t.teacher_id
    WHERE t.user_id = ?
    ORDER BY ce.batch_id, u.username
");
if (!$gradesStmt) {
    die("Grades prepare failed: " . $conn->error);
}
$gradesStmt->bind_param("i", $teacher_id);
$gradesStmt->execute();
$gradesStmt->store_result();

$gradesByBatch = [];
// Fetch all grades
while ($gradesStmt->fetch()) {
    $gradesByBatch[$batch_id][] = [
        'grade_id' => $g_grade_id,
        'enrollment_id' => $g_enrollment_id,
        'assignment_id' => $g_assignment_id,
        'score' => $g_score,
        'comments' => $g_comments,
        'username' => $g_username,
        'email' => $g_email
    ];
}
// To get variables, we need to bind result set again
// But since we're fetching multiple columns, do a second bind_result

// Rewind and fetch again for better clarity
// Instead, let's do a single fetch with fetch_assoc() style (but mysqli_stmt doesn't support that directly)
// So, better to use get_result() if available, or fetch with bind_result()

// For simplicity, since you have "store_result()", let's bind all result variables

// Reread from the query to set bind_result for grades
// Instead, re-execute the statement and bind_result
$gradesStmt->close();

$gradesStmt = $conn->prepare("
    SELECT g.grade_id, g.enrollment_id, g.assignment_id, g.score, g.comments, ce.batch_id, u.username, u.email
    FROM grades g
    INNER JOIN course_enrollments ce ON g.enrollment_id = ce.enrollment_id
    INNER JOIN course_assignments ca ON ce.batch_id = ca.batch_id
    INNER JOIN students s ON ce.student_id = s.student_id
    INNER JOIN users u ON s.user_id = u.user_id
    INNER JOIN teachers t ON ca.teacher_id = t.teacher_id
    WHERE t.user_id = ?
    ORDER BY ce.batch_id, u.username
");
if (!$gradesStmt) {
    die("Grades re-prepare failed: " . $conn->error);
}
$gradesStmt->bind_param("i", $teacher_id);
$gradesStmt->execute();
$gradesStmt->store_result();

$gradesByBatch = [];
$gradesStmt->bind_result($g_grade_id, $g_enrollment_id, $g_assignment_id, $g_score, $g_comments, $g_batch_id, $g_username, $g_email);
while ($gradesStmt->fetch()) {
    $gradesByBatch[$g_batch_id][] = [
        'grade_id' => $g_grade_id,
        'enrollment_id' => $g_enrollment_id,
        'assignment_id' => $g_assignment_id,
        'score' => $g_score,
        'comments' => $g_comments,
        'username' => $g_username,
        'email' => $g_email
    ];
}
$gradesStmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Grade Management</title>
<!-- Your CSS styles here (unchanged) -->
<style>
/* (Your existing styles) */
</style>
</head>
<body>
<header>
<h1>Welcome, <?= htmlspecialchars($teacherInfo['username']) ?></h1>
<p>Email: <?= htmlspecialchars($teacherInfo['email']) ?> | Gender: <?= htmlspecialchars($teacherInfo['gender']) ?> | Phone: <?= htmlspecialchars($teacherInfo['phone']) ?></p>
</header>

<div class="layout">
<aside class="sidebar">
<img src="admin.jpg" alt="Teacher" />
<h3>Teacher Dashboard</h3>
<nav class="nav">
<a href="teacher_dashboard.php">🏠 Dashboard</a>
<a href="manage_teacher_courses.php">📚 Manage Own Courses</a>
<a href="upload_materials.php">📂 Upload Materials</a>
<a href="grades.php" class="active">📝 Grade</a>
<a href="mark_attendance.php">✅ Mark Attendance</a>
<a href="message_students.php">💬 Message Students</a>
<a href="logout.php">🚪 Logout</a>
</nav>
</aside>

<main class="main">
<h2>Add New Grade</h2>
<div class="form-card">
<form method="POST">
<input type="hidden" name="action" value="add" />
<div class="form-group">
<label for="batch_id">Select Batch</label>
<select name="batch_id" id="batch_id" required>
<option value="">Select a batch</option>
<?php
// Output assigned courses for batch selection
foreach ($assignedCourses as $course): ?>
<option value="<?= $course['batch_id'] ?>">
<?= htmlspecialchars($course['batch_code']) ?> (<?= htmlspecialchars($course['courseName']) ?>)
</option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group">
<label for="enrollment_id">Select Student</label>
<select name="enrollment_id" id="enrollment_id" required>
<option value="">Select a student</option>
<?php
// Output students grouped by batch
foreach ($studentsByBatch as $batch_id => $students): ?>
<?php foreach ($students as $student): ?>
<option value="<?= $student['enrollment_id'] ?>" data-batch-id="<?= $batch_id ?>">
<?= htmlspecialchars($student['username']) ?> (<?= htmlspecialchars($student['email']) ?>)
</option>
<?php endforeach; ?>
<?php endforeach; ?>
</select>
</div>
<div class="form-group">
<label for="assignment_id">Select Assignment</label>
<select name="assignment_id" id="assignment_id" required>
<option value="">Select an assignment</option>
<?php
// Output assignment options
foreach ($assignedCourses as $course): ?>
<option value="<?= $course['assignment_id'] ?>" data-batch-id="<?= $course['batch_id'] ?>">
<?= htmlspecialchars($course['batch_code']) ?> (<?= htmlspecialchars($course['courseName']) ?>)
</option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group">
<label for="score">Score (0-100)</label>
<input type="number" name="score" id="score" step="0.01" min="0" max="100" required>
</div>
<div class="form-group">
<label for="comments">Comments</label>
<textarea name="comments" id="comments"></textarea>
</div>
<div class="form-group">
<button type="submit" class="submit-btn">Add Grade</button>
</div>
</form>
</div>

<h2>Grades</h2>
<?php if (!empty($assignedCourses)): ?>
<?php foreach ($assignedCourses as $course): ?>
<div class="table-card">
<h3>Batch: <?= htmlspecialchars($course['batch_code']) ?> (<?= htmlspecialchars($course['courseName']) ?>)</h3>
<?php if (isset($gradesByBatch[$course['batch_id']]) && count($gradesByBatch[$course['batch_id']]) > 0): ?>
<table>
<thead>
<tr>
<th>Student</th>
<th>Email</th>
<th>Score</th>
<th>Comments</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($gradesByBatch[$course['batch_id']] as $grade): ?>
<tr>
<td><?= htmlspecialchars($grade['username']) ?></td>
<td><?= htmlspecialchars($grade['email']) ?></td>
<td><?= htmlspecialchars($grade['score']) ?></td>
<td><?= htmlspecialchars($grade['comments'] ?? 'N/A') ?></td>
<td>
<!-- Edit Grade -->
<form method="POST" style="display:inline;">
<input type="hidden" name="action" value="edit" />
<input type="hidden" name="grade_id" value="<?= $grade['grade_id'] ?>" />
<input type="number" name="score" value="<?= htmlspecialchars($grade['score']) ?>" step="0.01" min="0" max="100" required style="width:80px;">
<textarea name="comments" style="width:150px;"><?= htmlspecialchars($grade['comments'] ?? '') ?></textarea>
<button type="submit" class="action-btn">Update</button>
</form>
<!-- Delete Grade -->
<form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this grade?');">
<input type="hidden" name="action" value="delete" />
<input type="hidden" name="grade_id" value="<?= $grade['grade_id'] ?>" />
<button type="submit" class="action-btn delete-btn">Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php else: ?>
<p>No grades recorded for this batch.</p>
<?php endif; ?>
</div>
<?php endforeach; ?>
<?php else: ?>
<p>No batches assigned, so no grades to display.</p>
<?php endif; ?>
</main>
</div>

<footer>
&copy; <?= date('Y') ?> Girls Coding Academy
</footer>
</body>
</html>