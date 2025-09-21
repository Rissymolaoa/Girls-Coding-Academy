<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.html");
    exit();
}

include("db.php");

// Get the student_id corresponding to the logged-in user
$user_id = $_SESSION['user_id'];
$studentQuery = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
$studentQuery->bind_param("i", $user_id);
$studentQuery->execute();
$studentResult = $studentQuery->get_result();

if ($studentResult->num_rows === 0) {
    die("Student record not found.");
}

$studentRow = $studentResult->fetch_assoc();
$student_id = $studentRow['student_id'];

// Fetch courses enrolled by this student
$sql = "
SELECT c.course_id, c.courseName, c.description, c.image_path,
       CONCAT(u.firstName, ' ', u.lastName) AS teacherName,
       b.batch_id, b.batch_code AS batchCode,
       IFNULL(ce.status, 'active') AS enrollment_status
FROM course_enrollments ce
JOIN batches b ON ce.batch_id = b.batch_id
JOIN courses c ON b.course_id = c.course_id
LEFT JOIN course_assignments ca ON ca.batch_id = b.batch_id
LEFT JOIN teachers t ON ca.teacher_id = t.teacher_id
LEFT JOIN users u ON t.user_id = u.user_id
WHERE ce.student_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$courses = [];
while ($row = $result->fetch_assoc()) {
    if (empty($row['image_path'])) $row['image_path'] = 'Uploads/courses/course1.jpg';
    if (empty($row['teacherName'])) $row['teacherName'] = 'TBA';
    $courses[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Courses - Student Dashboard</title>
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
.sidebar a.active { background:#333; }
.student-pic {
  width:90px; height:90px; border-radius:50%;
  margin-bottom:15px; border:2px solid #fff;
  object-fit:cover; display:block; margin-left:auto; margin-right:auto;
}

/* Content */
.content { flex:1; padding:30px; }
h2 { margin-bottom:20px; color:#5a189a; }

/* Courses grid */
.grid-view {
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
  gap:20px;
}
.course {
  background:white;
  border-radius:8px;
  padding:15px;
  text-align:center;
  box-shadow:0 2px 6px rgba(0,0,0,0.1);
  border:1px solid #eee;
  transition: transform 0.2s;
}
.course:hover { transform: translateY(-5px); }
.course img {
  width:100%; height:150px; object-fit:cover;
  border-radius:5px; margin-bottom:10px;
}
.course h3 { color:#5a189a; margin-bottom:5px; }
.course p { margin:2px 0; font-size:14px; }
</style>
</head>
<body>

<header>
  <h1>Girls Coding Academy - Student Dashboard</h1>
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
    <h2><i class="bi bi-journal-bookmark"></i> My Courses</h2>

    <div class="grid-view">
      <?php if (empty($courses)): ?>
        <p>No courses enrolled yet.</p>
      <?php else: ?>
        <?php foreach ($courses as $course): ?>
          <a href="course_dashboard.php?course_id=<?php echo htmlspecialchars($course['course_id']); ?>&batch_id=<?php echo htmlspecialchars($course['batch_id']); ?>" style="text-decoration:none; color:inherit;">
            <div class="course">
              <img src="<?php echo htmlspecialchars($course['image_path']); ?>" alt="<?php echo htmlspecialchars($course['courseName']); ?>">
              <h3><?php echo htmlspecialchars($course['courseName']); ?></h3>
              <p><i class="bi bi-person-workspace"></i> Teacher: <?php echo htmlspecialchars($course['teacherName']); ?></p>
              <p><i class="bi bi-123"></i> Batch: <?php echo htmlspecialchars($course['batchCode']); ?></p>
              <p><i class="bi bi-check2-circle"></i> Status: <?php echo htmlspecialchars($course['enrollment_status']); ?></p>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

</body>
</html>