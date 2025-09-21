<?php
session_start();

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Only allow students
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.html");
    exit();
}

include("db.php"); // DB connection

$user_id = $_SESSION['user_id'];

// Fetch student info from users table
$studentQuery = $conn->prepare("SELECT username FROM users WHERE user_id = ? AND role = 'student'");
if (!$studentQuery) {
    die("Student query preparation failed: " . $conn->error);
}
$studentQuery->bind_param("i", $user_id);
$studentQuery->execute();
$studentInfo = $studentQuery->get_result()->fetch_assoc();
if (!$studentInfo) {
    die("No student found for user_id: $user_id");
}
$studentQuery->close();

// Fetch student_id from students table
$studentIdQuery = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
if (!$studentIdQuery) {
    die("Student ID query preparation failed: " . $conn->error);
}
$studentIdQuery->bind_param("i", $user_id);
$studentIdQuery->execute();
$studentIdResult = $studentIdQuery->get_result();
if ($studentIdResult->num_rows === 0) {
    die("Error: Student profile not found. Please contact the administrator.");
}
$student = $studentIdResult->fetch_assoc();
$student_id = (int)$student['student_id'];
$studentIdQuery->close();

// Get course_id and batch_id from URL
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
if ($course_id <= 0 || $batch_id <= 0) {
    die("Error: Invalid or missing course_id or batch_id in URL. Please ensure you accessed this page from the 'My Courses' section.");
}

// Verify student is enrolled in the batch and course
$enrollmentQuery = $conn->prepare("
    SELECT c.courseName, b.batch_code
    FROM course_enrollments ce
    INNER JOIN batches b ON ce.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    WHERE ce.student_id = ? AND ce.batch_id = ? AND c.course_id = ?
");
if (!$enrollmentQuery) {
    die("Enrollment query preparation failed: " . $conn->error);
}
$enrollmentQuery->bind_param("iii", $student_id, $batch_id, $course_id);
$enrollmentQuery->execute();
$enrollmentResult = $enrollmentQuery->get_result();
if ($enrollmentResult->num_rows === 0) {
    die("Error: You are not enrolled in this course or batch. Please enroll in the course or contact the administrator.");
}
$courseInfo = $enrollmentResult->fetch_assoc();
$enrollmentQuery->close();

// Fetch materials for the batch
$materialQuery = $conn->prepare("
    SELECT m.material_id, m.title, m.description, m.file_path, m.uploaded_at
    FROM materials m
    WHERE m.batch_id = ?
    ORDER BY m.uploaded_at DESC
");
if (!$materialQuery) {
    die("Material query preparation failed: " . $conn->error);
}
$materialQuery->bind_param("i", $batch_id);
$materialQuery->execute();
$materialsResult = $materialQuery->get_result();
$materials = [];
while ($material = $materialsResult->fetch_assoc()) {
    $materials[] = $material;
}
$materialQuery->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Course Materials - <?php echo htmlspecialchars($courseInfo['courseName']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial, sans-serif; background:#f9f9f9; color:#333; }

/* Header */
header { background:#fff; color:#333; padding:15px 30px; text-align:center; border-bottom:1px solid #ddd; }

/* Layout */
.container { display:flex; min-height:90vh; }

/* Sidebar - Dark style */
.sidebar {
  width: 240px;
  background:#1a1a1a;
  padding:20px;
  min-height:100vh;
  color:#fff;
}
.sidebar h3 { color:#fff; margin-bottom:15px; font-size:18px; text-align:center; }
.sidebar a {
  display:flex; align-items:center; gap:10px;
  color:#fff; text-decoration:none; padding:10px; margin:5px 0; border-radius:6px; transition:background 0.2s;
}
.sidebar a:hover { background:#333; }
.student-pic {
  width:90px; height:90px; border-radius:50%;
  margin-bottom:15px; border:2px solid #fff;
  object-fit:cover; display:block; margin-left:auto; margin-right:auto;
}

/* Content */
.content { flex:1; padding:30px; }
h2 { margin-bottom:20px; color:#5a189a; }

/* Materials table */
.materials-table {
  background:white;
  border-radius:8px;
  padding:15px;
  box-shadow:0 2px 6px rgba(0,0,0,0.1);
  border:1px solid #eee;
}
.materials-table h3 { color:#5a189a; margin-bottom:15px; }
.materials-table table { width:100%; border-collapse:collapse; }
.materials-table th, .materials-table td {
  padding:10px;
  border:1px solid #ddd;
  text-align:left;
}
.materials-table th { background:#1a1a1a; color:#fff; }
.materials-table td a { color:#5a189a; text-decoration:none; }
.materials-table td a:hover { text-decoration:underline; }
.no-materials { color:#6c757d; font-style:italic; }
</style>
</head>
<body>

<header>
  <h1>Girls Coding Academy - Course Materials</h1>
</header>

<div class="container">
  <!-- Sidebar -->
  <div class="sidebar">
    <img src="admin.png" alt="Student Picture" class="student-pic">
    <h3>Navigation</h3>
    <a href="student.php"><i class="bi bi-house-door"></i> Home</a>
    <a href="student_courses.php" class="active"><i class="bi bi-journal-bookmark"></i> My Courses</a>
    <a href="#"><i class="bi bi-megaphone"></i> Announcements</a>
    <a href="#"><i class="bi bi-calendar-event"></i> My Calendar</a>
    <a href="attendance.php"><i class="bi bi-card-checklist"></i> Attendance</a>
    <a href="student_profile.php"><i class="bi bi-person-circle"></i> My Profile</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </div>

  <!-- Main Content -->
  <div class="content">
    <h2><i class="bi bi-journal-bookmark"></i> Materials for <?php echo htmlspecialchars($courseInfo['courseName']); ?> (Batch: <?php echo htmlspecialchars($courseInfo['batch_code']); ?>)</h2>

    <div class="materials-table">
      <h3>Uploaded Materials</h3>
      <?php if (empty($materials)): ?>
        <p class="no-materials">No materials uploaded for this batch.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Description</th>
              <th>File</th>
              <th>Uploaded At</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($materials as $material): ?>
              <tr>
                <td><?php echo htmlspecialchars($material['title']); ?></td>
                <td><?php echo htmlspecialchars($material['description']); ?></td>
                <td>
                  <?php if ($material['file_path']): ?>
                    <a href="<?php echo htmlspecialchars($material['file_path']); ?>" target="_blank">Download</a>
                  <?php else: ?>
                    <span>No file</span>
                  <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($material['uploaded_at']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

</body>
</html>