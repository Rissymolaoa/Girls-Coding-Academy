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
$current_day = date('Y-m-d');

// Handle form submission for attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_week'])) {
    $batch_id = intval($_POST['batch_id']);
    $attendance = $_POST['attendance'] ?? [];
    $marked_by = $teacherInfo['teacher_id'];

    foreach ($attendance as $student_id => $days) {
        foreach ($days as $day => $status) {
            if ($day !== $current_day) continue;
            $stmt = $conn->prepare("
                INSERT INTO attendance (student_id, batch_id, session_id, status, marked_by)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status=VALUES(status), marked_by=VALUES(marked_by)
            ");
            $stmt->bind_param("iissi", $student_id, $batch_id, $day, $status, $marked_by);
            $stmt->execute();
            $stmt->close();
        }
    }
    $message = "✅ Attendance for today saved successfully.";
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
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Mark Attendance</title>
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
<style>
:root {
  --primary: #7b2cbf;
  --accent: #5a189a;
  --muted: #f4f4f8;
  --card: #fff;
  --text: #222;
}
body {
  margin: 0;
  font-family: Arial, sans-serif;
  background: var(--muted);
  color: var(--text);
}
header {
  background: linear-gradient(90deg, var(--primary), var(--accent));
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
  <!-- Sidebar -->
  <nav class="col-md-3 col-xl-2 bg-dark text-white p-3 vh-100" style="min-width:220px;">
    <div class="text-center mb-4">
      <img src="admin.png" class="rounded-circle border border-info mb-2" width="92" height="92" alt="Teacher" />
      <h5>Teacher Dashboard</h5>
    </div>
    <ul class="nav flex-column">
      <li class="nav-item mb-2">
        <a class="nav-link text-white" href="teacher_dashboard.php">🏠 Dashboard</a>
      </li>
      <li class="nav-item mb-2">
        <a class="nav-link text-white" href="manage_own_courses.php">📚 Manage Courses</a>
      </li>
      <li class="nav-item mb-2">
        <a class="nav-link text-white" href="upload_materials.php">📂 Upload Materials</a>
      </li>
      <li class="nav-item mb-2">
        <a class="nav-link text-white" href="grade.php">📝 Grade</a>
      </li>
      <li class="nav-item mb-2">
        <a class="nav-link text-white active" href="mark_attendance.php">✅ Mark Attendance</a>
      </li>
      <li class="nav-item mb-2">
        <a class="nav-link text-white" href="message_students.php">💬 Message Students</a>
      </li>
      <li class="nav-item mb-2">
        <a class="nav-link text-white" href="logout.php">🚪 Logout</a>
      </li>
    </ul>
  </nav>

  <!-- Main Content -->
  <main class="col py-4">
    <h2>Mark Attendance for the Week</h2>
    <?php if ($message): ?>
      <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <!-- Batch selection -->
    <form method="GET" class="mb-4">
      <div class="row g-2 align-items-center">
        <div class="col-auto">
          <label for="batch_id" class="form-label mb-0">Select Batch</label>
        </div>
        <div class="col-auto flex-fill">
          <select class="form-select" name="batch_id" id="batch_id" onchange="this.form.submit()" required>
            <option value="">-- Select Batch --</option>
            <?php while ($batch = $assigned_batches->fetch_assoc()): ?>
              <option value="<?= $batch['batch_id'] ?>" <?= ($batch['batch_id'] == $selected_batch_id) ? 'selected' : '' ?>>
                <?= htmlspecialchars($batch['courseName'] . ' - ' . $batch['batch_code']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
      </div>
    </form>

    <?php if ($selected_batch_id > 0 && !empty($students)): ?>
      <form method="POST" action="">
        <input type="hidden" name="batch_id" value="<?= $selected_batch_id ?>">
        <h3>Mark Attendance for the Week (Monday - Sunday)</h3>
        <div class="table-responsive">
          <table class="table table-bordered table-striped">
            <thead class="table-dark">
              <tr>
                <th>Student</th>
                <th>Email</th>
                <?php foreach ($week_days as $day): ?>
                  <th><?= date('D', strtotime($day)) ?><br><?= $day ?></th>
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
                      $is_today = ($day === $current_day);
                      $selected_value = $_POST['attendance'][$student['student_id']][$day] ?? '';
                    ?>
                    <td class="text-center">
                      <select name="attendance[<?= $student['student_id'] ?>][<?= $day ?>]" class="form-select" <?= $is_today ? '' : 'disabled' ?> >
                        <option value="Present" <?= ($selected_value=='Present')?'selected':'' ?>>Present</option>
                        <option value="Absent" <?= ($selected_value=='Absent')?'selected':'' ?>>Absent</option>
						<option value="Late" <?= ($selected_value=='Late')?'selected':'' ?>>Late</option>
						<option value="Sick" <?= ($selected_value=='Sick')?'selected':'' ?>>Sick</option>
                      </select>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <button type="submit" name="mark_week" class="btn btn-success">Save Today's Attendance</button>
      </form>
    <?php elseif ($selected_batch_id > 0): ?>
      <div class="alert alert-warning">No active students enrolled in this batch.</div>
    <?php endif; ?>
  </main>
</div>
<footer class="bg-dark text-white text-center py-3 mt-4">
  &copy; <?= date('Y') ?> Girls Coding Academy
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>