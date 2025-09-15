<?php
session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: login.html");
    exit();
}

include("db.php");

// First, get the student_id corresponding to the logged-in user
$user_id = $_SESSION['user_id'];
$studentQuery = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
$studentQuery->bind_param("i", $user_id);
$studentQuery->execute();
$studentResult = $studentQuery->get_result();

if($studentResult->num_rows === 0){
    die("Student record not found.");
}

$studentRow = $studentResult->fetch_assoc();
$student_id = $studentRow['student_id'];

// Now fetch courses enrolled by this student
$sql = "
SELECT c.course_id, c.courseName, c.description, c.image_path,
       CONCAT(u.firstName, ' ', u.lastName) AS teacherName,
       b.batch_code AS batchCode,
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
while($row = $result->fetch_assoc()){
    if(empty($row['image_path'])) $row['image_path'] = 'uploads/courses/course1.jpg';
    if(empty($row['teacherName'])) $row['teacherName'] = 'TBA';
    $courses[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Courses - Student Dashboard</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial,sans-serif; background:#f9f9f9; }
header { background:#7b2cbf; color:white; padding:15px 30px; text-align:center; }
.container { display:flex; min-height:90vh; }
.sidebar { width: 240px; background:#5a189a; padding:20px; min-height:100vh; }
.sidebar h3 { color:white; margin-bottom:15px; }
.sidebar a { display:block; color:white; text-decoration:none; padding:10px; margin:5px 0; border-radius:4px; }
.sidebar a:hover { background:#9d4edd; }
.admin-pic { width:100px; height:100px; border-radius:50%; margin-bottom:15px; border:3px solid #1abc9c; object-fit:cover; }
.content { flex:1; padding:30px; }
h2 { color:#5a189a; margin-bottom:20px; }
.grid-view { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:20px; }
.grid-view .course { background:white; border-radius:8px; padding:15px; text-align:center; box-shadow:0 2px 6px rgba(0,0,0,0.1); }
.grid-view .course img { width:100%; height:150px; object-fit:cover; border-radius:5px; margin-bottom:10px; }
.grid-view h3 { color:#5a189a; margin-bottom:5px; }
</style>
</head>
<body>

<header>
<h1>Girls Coding Academy - Student Dashboard</h1>
</header>

<div class="container">
  <div class="sidebar">
    <img src="admin.jpg" alt="Student Picture" class="admin-pic">
    <h3>Navigation</h3>
      <a href="student.php">🏠 Home</a>
      <a href="student_courses.php">📚 My Courses</a>
      <a href="#">📢 Announcements</a>
      <a href="#">📅 My Calendar</a>
      <a href="enroll.php">📅 Enroll</a>
      <a href="student_profile">👤 My Profile</a>
      <a href="logout.php">🚪 Logout</a>
  </div>

  <div class="content">
    <h2>📚 My Courses</h2>

<div class="grid-view">
  <?php if(empty($courses)): ?>
    <p>No courses enrolled yet.</p>
  <?php else: ?>
    <?php foreach($courses as $course): ?>
      <a href="course_dashboard.php?course_id=<?php echo $course['course_id']; ?>&batch_id=<?php echo $course['batchCode']; ?>" style="text-decoration:none; color:inherit;">
        <div class="course">
          <img src="<?php echo $course['image_path']; ?>" alt="<?php echo $course['courseName']; ?>">
          <h3><?php echo $course['courseName']; ?></h3>
          <p>Teacher: <?php echo $course['teacherName']; ?></p>
          <p>Batch: <?php echo $course['batchCode']; ?></p>
          <p>Status: <?php echo $course['enrollment_status']; ?></p>
        </div>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

  </div>
</div>

</body>
</html>
