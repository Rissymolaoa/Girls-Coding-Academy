<?php
session_start();
include 'db.php';

// Ensure teacher is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$teacher_user_id = $_SESSION['user_id'];
// Fetch teacher info including teacher_id
$teacher_query = $conn->prepare("
    SELECT t.teacher_id, u.username, u.email, u.gender, u.phone 
    FROM teachers t 
    INNER JOIN users u ON t.user_id = u.user_id 
    WHERE u.user_id = ?
");
$teacher_query->bind_param("i", $teacher_user_id);
$teacher_query->execute();
$teacherInfo = $teacher_query->get_result()->fetch_assoc();
$teacher_query->close();

$message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_week'])) {
    $batch_id = intval($_POST['batch_id']);
    $attendance = $_POST['attendance'] ?? [];
    $marked_by = $teacherInfo['teacher_id'];

    foreach ($attendance as $student_id => $days) {
        foreach ($days as $day => $status) {
            // Check if attendance exists
            $check_q = $conn->prepare("SELECT COUNT(*) as count FROM attendance WHERE batch_id=? AND session_id=? AND student_id=?");
            $check_q->bind_param("isi", $batch_id, $day, $student_id);
            $check_q->execute();
            $res = $check_q->get_result()->fetch_assoc();
            $check_q->close();

            if ($res['count'] == 0) {
                // Insert attendance
                $insert_q = $conn->prepare("INSERT INTO attendance (student_id, batch_id, session_id, status, marked_by) VALUES (?, ?, ?, ?, ?)");
                $insert_q->bind_param("iissi", $student_id, $batch_id, $day, $status, $marked_by);
                $insert_q->execute();
                $insert_q->close();
            }
        }
    }
    $message = "✅ Weekly attendance saved successfully.";
}

// Fetch assigned batches
$batch_query = $conn->prepare("
    SELECT b.batch_id, b.batch_code, c.courseName
    FROM course_assignments ca
    INNER JOIN batches b ON ca.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    WHERE ca.teacher_id = (SELECT teacher_id FROM teachers WHERE user_id = ?)
");
$batch_query->bind_param("i", $teacher_user_id);
$batch_query->execute();
$assigned_batches = $batch_query->get_result();
$batch_query->close();

$selected_batch_id = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;
$students = [];
$week_days = [];
$current_day = date('Y-m-d');

if ($selected_batch_id > 0) {
    // Get students
    $student_query = $conn->prepare("
        SELECT s.student_id, u.firstName, u.lastName, u.email
        FROM course_enrollments ce
        INNER JOIN students s ON ce.student_id = s.student_id
        INNER JOIN users u ON s.user_id = u.user_id
        WHERE ce.batch_id = ? AND ce.status='active'
    ");
    $student_query->bind_param("i", $selected_batch_id);
    $student_query->execute();
    $students_result = $student_query->get_result();
    while ($row = $students_result->fetch_assoc()) {
        $students[] = $row;
    }
    $student_query->close();

    // Generate week dates (Monday to Sunday)
    $week_start = date('Y-m-d', strtotime('monday this week'));
    for ($i=0; $i<7; $i++) {
        $day = date('Y-m-d', strtotime("$week_start +$i days"));
        $week_days[] = $day;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Mark Attendance</title>
<style>
:root{--primary:#7b2cbf;--accent:#5a189a;--muted:#f4f4f8;--card:#fff;--text:#222;}
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
button, select{background:#1abc9c;color:#fff;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;}
button:hover, select:hover{background:#16a085;}
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
        <a href="grade.php">📝 Grade</a>
        <a href="mark_attendance.php" class="active">✅ Mark Attendance</a>
        <a href="message_students.php">💬 Message Students</a>
        <a href="logout.php">🚪 Logout</a>
    </nav>
</aside>

<main class="main">
<h2>Mark Attendance for the Week</h2>
<?php if ($message): ?>
    <p><strong><?= htmlspecialchars($message) ?></strong></p>
<?php endif; ?>

<form method="GET" action="">
    <label for="batch_id">Select Batch:</label>
    <select name="batch_id" id="batch_id" onchange="this.form.submit()">
        <option value="">-- Select Batch --</option>
        <?php while ($batch = $assigned_batches->fetch_assoc()): ?>
            <option value="<?= $batch['batch_id'] ?>" <?= ($batch['batch_id']==$selected_batch_id)?'selected':'' ?>>
                <?= htmlspecialchars($batch['courseName'].' - '.$batch['batch_code']) ?>
            </option>
        <?php endwhile; ?>
    </select>
</form>

<?php if ($selected_batch_id > 0 && !empty($students)): ?>
<form method="POST" action="">
    <input type="hidden" name="batch_id" value="<?= $selected_batch_id ?>">
    <h3>Mark Attendance for the Week (Monday - Sunday)</h3>
    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Email</th>
                <?php foreach ($week_days as $day): ?>
                    <th><?= date('D', strtotime($day)) ?> <br><?= $day ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($students as $student): ?>
            <tr>
                <td><?= htmlspecialchars($student['firstName'].' '.$student['lastName']) ?></td>
                <td><?= htmlspecialchars($student['email']) ?></td>
                <?php foreach ($week_days as $day): ?>
                    <?php
                    // Determine if the day is today
                    $is_today = ($day === $current_day);
                    // Check if there's existing attendance data (if form submitted)
                    $selected_value = '';
                    if (isset($_POST['attendance'][$student['student_id']][$day])) {
                        $selected_value = $_POST['attendance'][$student['student_id']][$day];
                    }
                    ?>
                    <td style="text-align:center;">
                        <select name="attendance[<?= $student['student_id'] ?>][<?= $day ?>]" <?= $is_today ? '' : 'disabled' ?>>
                            <option value="Present" <?= ($selected_value=='Present')?'selected':'' ?>>Present</option>
                            <option value="Absent" <?= ($selected_value=='Absent')?'selected':'' ?>>Absent</option>
                        </select>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <br>
    <button type="submit" name="mark_week" value="1">Save Weekly Attendance</button>
</form>
<?php elseif ($selected_batch_id > 0): ?>
    <p>No active students enrolled in this batch.</p>
<?php endif; ?>
</main>
</div>
<footer>&copy; <?= date('Y') ?> Girls Coding Academy</footer>
</body>
</html>