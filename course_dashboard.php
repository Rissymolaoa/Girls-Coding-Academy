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
    SELECT c.courseName, b.batch_code, ce.enrollment_id
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
$enrollment_id = (int)$courseInfo['enrollment_id'];
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

// Fetch activities for the batch
$activityQuery = $conn->prepare("
    SELECT a.activity_id, a.title, a.description, a.due_date, a.resource_file, a.created_at, a.status
    FROM activities a
    WHERE a.batch_id = ? AND a.status = 'active'
    ORDER BY a.created_at DESC
");
if (!$activityQuery) {
    die("Activity query preparation failed: " . $conn->error);
}
$activityQuery->bind_param("i", $batch_id);
$activityQuery->execute();
$activitiesResult = $activityQuery->get_result();
$activities = [];
while ($activity = $activitiesResult->fetch_assoc()) {
    $activities[] = $activity;
}
$activityQuery->close();

// Fetch tests for the batch
$testQuery = $conn->prepare("
    SELECT t.test_id, t.title, t.description, t.due_date, t.max_score, t.resource_file, t.created_at
    FROM tests t
    WHERE t.batch_id = ? AND t.status = 'active'
    ORDER BY t.created_at DESC
");
if (!$testQuery) {
    die("Test query preparation failed: " . $conn->error);
}
$testQuery->bind_param("i", $batch_id);
$testQuery->execute();
$testsResult = $testQuery->get_result();
$tests = [];
while ($test = $testsResult->fetch_assoc()) {
    $tests[] = $test;
}
$testQuery->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Course Materials - <?php echo htmlspecialchars($courseInfo['courseName']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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

/* Tables and Grids */
.materials-table, .activities-grid, .tests-grid {
  background:white;
  border-radius:8px;
  padding:15px;
  box-shadow:0 2px 6px rgba(0,0,0,0.1);
  border:1px solid #eee;
  margin-bottom:30px;
}
.materials-table h3, .activities-grid h3, .tests-grid h3 { color:#5a189a; margin-bottom:15px; }
.materials-table table { width:100%; border-collapse:collapse; }
.materials-table th, .materials-table td {
  padding:10px;
  border:1px solid #ddd;
  text-align:left;
}
.materials-table th { background:#1a1a1a; color:#fff; }
.materials-table td a, .activities-grid a, .tests-grid a { color:#5a189a; text-decoration:none; }
.materials-table td a:hover, .activities-grid a:hover, .tests-grid a:hover { text-decoration:underline; }
.no-materials, .no-activities, .no-tests { color:#6c757d; font-style:italic; }

/* Grid Card Styling */
.activity-card, .test-card {
  border:1px solid #ddd;
  border-radius:8px;
  padding:15px;
  margin-bottom:15px;
  background:white;
  box-shadow:0 1px 3px rgba(0,0,0,0.1);
}
.activity-card h5, .test-card h5 { color:#5a189a; margin-bottom:10px; }
.activity-card p, .test-card p { margin-bottom:8px; }
.submit-btn { margin-top:10px; }
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

    <!-- Materials Section -->
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
                  <?php if ($material['file_path'] && file_exists($material['file_path'])): ?>
                    <a href="<?php echo htmlspecialchars($material['file_path']); ?>" target="_blank">Download</a>
                  <?php elseif ($material['file_path']): ?>
                    <span class="text-danger">File not found: <?php echo htmlspecialchars($material['file_path']); ?></span>
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

    <!-- Activities Section -->
    <div class="activities-grid">
      <h3>Assigned Activities</h3>
      <?php if (empty($activities)): ?>
        <p class="no-activities">No active activities assigned for this batch.</p>
      <?php else: ?>
        <div class="row">
          <?php foreach ($activities as $activity): ?>
            <div class="col-md-4">
              <div class="activity-card">
                <h5><?php echo htmlspecialchars($activity['title']); ?></h5>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($activity['description']); ?></p>
                <p><strong>Due Date:</strong> <?php echo htmlspecialchars($activity['due_date']); ?></p>
                <p><strong>Resource File:</strong>
                  <?php if ($activity['resource_file'] && file_exists($activity['resource_file'])): ?>
                    <a href="<?php echo htmlspecialchars($activity['resource_file']); ?>" target="_blank">Download</a>
                  <?php elseif ($activity['resource_file']): ?>
                    <span class="text-danger">File not found: <?php echo htmlspecialchars($activity['resource_file']); ?></span>
                  <?php else: ?>
                    <span>No file</span>
                  <?php endif; ?>
                </p>
                <a href="submit_activity.php?activity_id=<?php echo $activity['activity_id']; ?>&course_id=<?php echo $course_id; ?>&batch_id=<?php echo $batch_id; ?>" class="btn btn-primary btn-sm submit-btn">View/Submit</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Tests Section -->
    <div class="tests-grid">
      <h3>Assigned Tests</h3>
      <?php if (empty($tests)): ?>
        <p class="no-tests">No active tests assigned for this batch.</p>
      <?php else: ?>
        <div class="row">
          <?php foreach ($tests as $test): ?>
            <div class="col-md-4">
              <div class="test-card">
                <h5><?php echo htmlspecialchars($test['title']); ?></h5>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($test['description']); ?></p>
                <p><strong>Due Date:</strong> <?php echo htmlspecialchars($test['due_date']); ?></p>
                <p><strong>Max Score:</strong> <?php echo htmlspecialchars($test['max_score']); ?></p>
                <p><strong>Resource File:</strong>
                  <?php if ($test['resource_file'] && file_exists($test['resource_file'])): ?>
                    <a href="<?php echo htmlspecialchars($test['resource_file']); ?>" target="_blank">Download</a>
                  <?php elseif ($test['resource_file']): ?>
                    <span class="text-danger">File not found: <?php echo htmlspecialchars($test['resource_file']); ?></span>
                  <?php else: ?>
                    <span>No file</span>
                  <?php endif; ?>
                </p>
                <a href="submit_test.php?test_id=<?php echo $test['test_id']; ?>&course_id=<?php echo $course_id; ?>&batch_id=<?php echo $batch_id; ?>" class="btn btn-primary btn-sm submit-btn">View/Submit</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>