<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: login.html");
    exit();
}

include("db.php");

$course_id = $_GET['course_id'] ?? null;
$batch_code = $_GET['batch_id'] ?? null; // batch_id or batchCode depending on previous page

if(!$course_id || !$batch_code){
    die("Invalid course or batch selected.");
}

// Fetch course info
$sql = "SELECT * FROM courses WHERE course_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
if(!$course) die("Course not found.");

// Demo placeholders (replace with DB queries later)
$demo_lessons = [
    ['title' => 'Introduction', 'description' => 'Course introduction and overview'],
    ['title' => 'Lesson 1', 'description' => 'Basics of coding'],
    ['title' => 'Lesson 2', 'description' => 'Intermediate concepts'],
];

$demo_assignments = [
    ['title' => 'Assignment 1', 'description' => 'Complete the exercises in Chapter 1', 'due' => '2025-09-20'],
    ['title' => 'Assignment 2', 'description' => 'Complete the exercises in Chapter 2', 'due' => '2025-09-25'],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $course['courseName']; ?> - Dashboard</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial,sans-serif; background:#f9f9f9; }
header { background:#7b2cbf; color:white; padding:15px 30px; text-align:center; }
.container { display:flex; min-height:90vh; }
.sidebar { width: 240px; background:#5a189a; padding:20px; min-height:100vh; }
.sidebar h3 { color:white; margin-bottom:15px; }
.sidebar a { display:block; color:white; text-decoration:none; padding:10px; margin:5px 0; border-radius:4px; }
.sidebar a:hover, .sidebar a.active { background:#9d4edd; }
.admin-pic { width:100px; height:100px; border-radius:50%; margin-bottom:15px; border:3px solid #1abc9c; object-fit:cover; }
.content { flex:1; padding:30px; }
h2 { color:#5a189a; margin-bottom:20px; }
.course-header { display:flex; align-items:center; margin-bottom:30px; }
.course-header img { width:120px; height:120px; object-fit:cover; border-radius:10px; margin-right:20px; border:2px solid #5a189a; }
.course-header div h1 { margin-bottom:5px; }
.course-header div p { color:#555; }
.section { margin-bottom:30px; }
.section h3 { color:#5a189a; margin-bottom:15px; }
.card { background:white; border-radius:8px; padding:15px; box-shadow:0 2px 6px rgba(0,0,0,0.1); margin-bottom:15px; }
.card:hover { box-shadow:0 5px 15px rgba(0,0,0,0.2); }
.card p { color:#333; }
.card .due { font-weight:bold; color:#e63946; }
</style>
</head>
<body>

<header>
<h1>Girls Coding Academy - Course Dashboard</h1>
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
    <div class="course-header">
      <img src="<?php echo !empty($course['image_path']) ? $course['image_path'] : 'uploads/courses/course1.jpg'; ?>" alt="<?php echo $course['courseName']; ?>">
      <div>
        <h1><?php echo $course['courseName']; ?></h1>
        <p><?php echo $course['description']; ?></p>
        <p><strong>Batch:</strong> <?php echo $batch_code; ?></p>
      </div>
    </div>

    <div class="section">
        <h3>📘 Lessons</h3>
        <?php foreach($demo_lessons as $lesson): ?>
        <div class="card">
            <h4><?php echo $lesson['title']; ?></h4>
            <p><?php echo $lesson['description']; ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="section">
        <h3>📄 Assignments</h3>
        <?php foreach($demo_assignments as $assn): ?>
        <div class="card">
            <h4><?php echo $assn['title']; ?></h4>
            <p><?php echo $assn['description']; ?></p>
            <p class="due">Due: <?php echo date("d M Y", strtotime($assn['due'])); ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="section">
        <h3>📝 Schoolwork / Activities</h3>
        <div class="card">
            <p>Demo content for schoolwork and activities. Replace with database-driven content later.</p>
        </div>
        <div class="card">
            <p>More demo schoolwork / project information can be added here.</p>
        </div>
    </div>

  </div>
</div>

</body>
</html>
