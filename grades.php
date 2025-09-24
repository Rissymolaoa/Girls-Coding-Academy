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
            $student_id = (int)$_POST['student_id'];
            $batch_id = (int)$_POST['batch_id'];
            $test_column = $_POST['test_column'];
            $score = floatval($_POST['score']);

            // Validate score
            if ($score < 0 || $score > 100) {
                echo "<div class='alert alert-danger'>Error: Score must be between 0 and 100.</div>";
            } elseif (!in_array($test_column, ['test_1', 'test_2', 'test_3', 'test_4', 'test_5', 'test_6', 'test_7', 'end_examination'])) {
                echo "<div class='alert alert-danger'>Error: Invalid test column selected.</div>";
            } else {
                // Check if grade record exists for student and batch
                $checkStmt = $conn->prepare("SELECT grade_id FROM internal_grades WHERE student_id = ? AND batch_id = ?");
                $checkStmt->bind_param("ii", $student_id, $batch_id);
                $checkStmt->execute();
                $checkStmt->store_result();
                if ($checkStmt->num_rows > 0) {
                    // Update existing record
                    $updateStmt = $conn->prepare("UPDATE internal_grades SET $test_column = ? WHERE student_id = ? AND batch_id = ?");
                    if (!$updateStmt) {
                        die("Update grade prepare failed: " . $conn->error);
                    }
                    $updateStmt->bind_param("dii", $score, $student_id, $batch_id);
                    $updateStmt->execute();
                    $updateStmt->close();
                } else {
                    // Insert new record
                    $insertStmt = $conn->prepare("INSERT INTO internal_grades (student_id, batch_id, $test_column) VALUES (?, ?, ?)");
                    if (!$insertStmt) {
                        die("Insert grade prepare failed: " . $conn->error);
                    }
                    $insertStmt->bind_param("iid", $student_id, $batch_id, $score);
                    $insertStmt->execute();
                    $insertStmt->close();
                }
                $checkStmt->close();
                echo "<div class='alert alert-success'>Grade added/updated successfully.</div>";
            }
        } elseif ($_POST['action'] === 'edit') {
            $grade_id = (int)$_POST['grade_id'];
            $test_column = $_POST['test_column'];
            $score = floatval($_POST['score']);

            if ($score < 0 || $score > 100) {
                echo "<div class='alert alert-danger'>Error: Score must be between 0 and 100.</div>";
            } elseif (!in_array($test_column, ['test_1', 'test_2', 'test_3', 'test_4', 'test_5', 'test_6', 'test_7', 'end_examination'])) {
                echo "<div class='alert alert-danger'>Error: Invalid test column selected.</div>";
            } else {
                $updateStmt = $conn->prepare("
                    UPDATE internal_grades
                    SET $test_column = ?
                    WHERE grade_id = ? AND batch_id IN (
                        SELECT ca.batch_id
                        FROM course_assignments ca
                        INNER JOIN teachers t ON ca.teacher_id = t.teacher_id
                        WHERE t.user_id = ?
                    )
                ");
                if (!$updateStmt) {
                    die("Update grade prepare failed: " . $conn->error);
                }
                $updateStmt->bind_param("dii", $score, $grade_id, $teacher_id);
                $updateStmt->execute();
                $updateStmt->close();
                echo "<div class='alert alert-success'>Grade updated successfully.</div>";
            }
        } elseif ($_POST['action'] === 'delete') {
            $grade_id = (int)$_POST['grade_id'];
            $deleteStmt = $conn->prepare("
                DELETE FROM internal_grades
                WHERE grade_id = ? AND batch_id IN (
                    SELECT ca.batch_id
                    FROM course_assignments ca
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
            echo "<div class='alert alert-success'>Grade deleted successfully.</div>";
        }
    }
}

// Fetch assigned courses for the teacher
$courseStmt = $conn->prepare("
    SELECT ca.batch_id, b.batch_code, c.courseName
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
$courseStmt->bind_result($batch_id, $batch_code, $courseName);
while ($courseStmt->fetch()) {
    $assignedCourses[] = [
        'batch_id' => $batch_id,
        'batch_code' => $batch_code,
        'courseName' => $courseName
    ];
}
$courseStmt->close();

// Fetch enrolled students grouped by batch
$studentStmt = $conn->prepare("
    SELECT ce.student_id, ce.batch_id, u.username, u.email
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
$studentStmt->bind_result($student_id, $batch_id, $username, $email);
while ($studentStmt->fetch()) {
    $studentsByBatch[$batch_id][] = [
        'student_id' => $student_id,
        'username' => $username,
        'email' => $email
    ];
}
$studentStmt->close();

// Fetch grades grouped by batch
$gradesStmt = $conn->prepare("
    SELECT g.grade_id, g.student_id, g.batch_id, g.test_1, g.test_2, g.test_3, g.test_4, g.test_5, g.test_6, g.test_7, g.end_examination, g.created_at, u.username, u.email
    FROM internal_grades g
    INNER JOIN course_enrollments ce ON g.student_id = ce.student_id AND g.batch_id = ce.batch_id
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
$gradesStmt->bind_result($g_grade_id, $g_student_id, $g_batch_id, $g_test_1, $g_test_2, $g_test_3, $g_test_4, $g_test_5, $g_test_6, $g_test_7, $g_end_examination, $g_created_at, $g_username, $g_email);
while ($gradesStmt->fetch()) {
    $gradesByBatch[$g_batch_id][] = [
        'grade_id' => $g_grade_id,
        'student_id' => $g_student_id,
        'batch_id' => $g_batch_id,
        'test_1' => $g_test_1,
        'test_2' => $g_test_2,
        'test_3' => $g_test_3,
        'test_4' => $g_test_4,
        'test_5' => $g_test_5,
        'test_6' => $g_test_6,
        'test_7' => $g_test_7,
        'end_examination' => $g_end_examination,
        'created_at' => $g_created_at,
        'username' => $g_username,
        'email' => $g_email
    ];
}
$gradesStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Grade Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial, sans-serif; background:#f9f9f9; color:#333; }
header { background:#fff; color:#333; padding:15px 30px; text-align:center; border-bottom:1px solid #ddd; }
.layout { display:flex; min-height:90vh; }
.sidebar {
    width:240px; background:#1a1a1a; padding:20px; min-height:100vh; color:#fff;
}
.sidebar img { width:90px; height:90px; border-radius:50%; margin-bottom:15px; border:2px solid #fff; display:block; margin-left:auto; margin-right:auto; }
.sidebar h3 { color:#fff; margin-bottom:15px; font-size:18px; text-align:center; }
.nav a { display:flex; align-items:center; gap:10px; color:#fff; text-decoration:none; padding:10px; margin:5px 0; border-radius:6px; transition:background 0.2s; }
.nav a:hover { background:#333; }
.nav a.active { background:#5a189a; }
.main { flex:1; padding:30px; }
h2 { margin-bottom:20px; color:#5a189a; }
.form-card, .table-card { background:white; border-radius:8px; padding:20px; box-shadow:0 2px 6px rgba(0,0,0,0.1); margin-bottom:20px; }
.form-group { margin-bottom:15px; }
.form-group label { display:block; margin-bottom:5px; font-weight:bold; }
.form-group select, .form-group input, .form-group textarea { width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; }
.form-group textarea { resize:vertical; min-height:100px; }
.submit-btn { background:#5a189a; color:white; padding:10px 20px; border:none; border-radius:4px; cursor:pointer; }
.submit-btn:hover { background:#7b2cbf; }
.action-btn { background:#5a189a; color:white; padding:5px 10px; border:none; border-radius:4px; margin-right:5px; }
.delete-btn { background:#dc3545; }
.delete-btn:hover { background:#c82333; }
table { width:100%; border-collapse:collapse; margin-top:10px; }
th, td { padding:10px; border:1px solid #ddd; text-align:left; }
th { background:#1a1a1a; color:#fff; }
td a { color:#5a189a; text-decoration:none; }
td a:hover { text-decoration:underline; }
.alert { margin-bottom:20px; padding:15px; border-radius:4px; }
.alert-danger { background:#f8d7da; color:#721c24; }
.alert-success { background:#d4edda; color:#155724; }
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
            <a href="teacher_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
            <a href="manage_teacher_courses.php"><i class="bi bi-journal-bookmark"></i> Manage Own Courses</a>
            <a href="upload_materials.php"><i class="bi bi-upload"></i> Upload Materials</a>
            <a href="grades.php" class="active"><i class="bi bi-clipboard-check"></i> Grade</a>
            <a href="mark_attendance.php"><i class="bi bi-check-square"></i> Mark Attendance</a>
            <a href="message_students.php"><i class="bi bi-chat-dots"></i> Message Students</a>
            <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </aside>

    <main class="main">
        <h2>Add New Grade</h2>
        <div class="form-card">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label for="batch_id">Select Batch</label>
                    <select name="batch_id" id="batch_id" required>
                        <option value="">Select a batch</option>
                        <?php foreach ($assignedCourses as $course): ?>
                            <option value="<?= $course['batch_id'] ?>">
                                <?= htmlspecialchars($course['batch_code']) ?> (<?= htmlspecialchars($course['courseName']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="student_id">Select Student</label>
                    <select name="student_id" id="student_id" required>
                        <option value="">Select a student</option>
                        <?php foreach ($studentsByBatch as $batch_id => $students): ?>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['student_id'] ?>" data-batch-id="<?= $batch_id ?>">
                                    <?= htmlspecialchars($student['username']) ?> (<?= htmlspecialchars($student['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="test_column">Select Test</label>
                    <select name="test_column" id="test_column" required>
                        <option value="">Select a test</option>
                        <option value="test_1">Test 1</option>
                        <option value="test_2">Test 2</option>
                        <option value="test_3">Test 3</option>
                        <option value="test_4">Test 4</option>
                        <option value="test_5">Test 5</option>
                        <option value="test_6">Test 6</option>
                        <option value="test_7">Test 7</option>
                        <option value="end_examination">End Examination</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="score">Score (0-100)</label>
                    <input type="number" name="score" id="score" step="0.01" min="0" max="100" required>
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
                                    <th>Test 1</th>
                                    <th>Test 2</th>
                                    <th>Test 3</th>
                                    <th>Test 4</th>
                                    <th>Test 5</th>
                                    <th>Test 6</th>
                                    <th>Test 7</th>
                                    <th>End Exam</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gradesByBatch[$course['batch_id']] as $grade): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($grade['username']) ?></td>
                                        <td><?= htmlspecialchars($grade['email']) ?></td>
                                        <td><?= $grade['test_1'] !== null ? htmlspecialchars($grade['test_1']) : 'N/A' ?></td>
                                        <td><?= $grade['test_2'] !== null ? htmlspecialchars($grade['test_2']) : 'N/A' ?></td>
                                        <td><?= $grade['test_3'] !== null ? htmlspecialchars($grade['test_3']) : 'N/A' ?></td>
                                        <td><?= $grade['test_4'] !== null ? htmlspecialchars($grade['test_4']) : 'N/A' ?></td>
                                        <td><?= $grade['test_5'] !== null ? htmlspecialchars($grade['test_5']) : 'N/A' ?></td>
                                        <td><?= $grade['test_6'] !== null ? htmlspecialchars($grade['test_6']) : 'N/A' ?></td>
                                        <td><?= $grade['test_7'] !== null ? htmlspecialchars($grade['test_7']) : 'N/A' ?></td>
                                        <td><?= $grade['end_examination'] !== null ? htmlspecialchars($grade['end_examination']) : 'N/A' ?></td>
                                        <td><?= htmlspecialchars($grade['created_at']) ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="grade_id" value="<?= $grade['grade_id'] ?>">
                                                <select name="test_column" required>
                                                    <option value="test_1">Test 1</option>
                                                    <option value="test_2">Test 2</option>
                                                    <option value="test_3">Test 3</option>
                                                    <option value="test_4">Test 4</option>
                                                    <option value="test_5">Test 5</option>
                                                    <option value="test_6">Test 6</option>
                                                    <option value="test_7">Test 7</option>
                                                    <option value="end_examination">End Examination</option>
                                                </select>
                                                <input type="number" name="score" value="<?= $grade[$grade['test_1'] !== null ? 'test_1' : ($grade['test_2'] !== null ? 'test_2' : 'test_3')] ?? '0' ?>" step="0.01" min="0" max="100" required style="width:80px;">
                                                <button type="submit" class="action-btn">Update</button>
                                            </form>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this grade?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="grade_id" value="<?= $grade['grade_id'] ?>">
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Filter students by batch in the Add Grade form
    document.getElementById('batch_id').addEventListener('change', function() {
        const batchId = this.value;
        const studentSelect = document.getElementById('student_id');
        const options = studentSelect.querySelectorAll('option[data-batch-id]');
        options.forEach(option => {
            option.style.display = (batchId === '' || option.getAttribute('data-batch-id') === batchId) ? 'block' : 'none';
            if (option.style.display === 'none' && option.selected) {
                studentSelect.value = '';
            }
        });
    });
</script>
</body>
</html>