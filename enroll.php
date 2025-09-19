<?php
session_start();

// DB connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Check if logged in student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Find student_id linked to this user
$res = $conn->query("SELECT student_id FROM students WHERE user_id = $user_id");
if ($res->num_rows > 0) {
    $student = $res->fetch_assoc();
    $student_id = $student['student_id'];
} else {
    die("Error: Student not found.");
}

// Enroll action
$enrollMessage = "";
if (isset($_POST['enroll'])) {
    $batch_id = intval($_POST['batch_id']);

    $check = $conn->query("SELECT * FROM course_enrollments WHERE student_id=$student_id AND batch_id=$batch_id");
    if ($check->num_rows == 0) {
        $conn->query("INSERT INTO course_enrollments (student_id, batch_id) VALUES ($student_id, $batch_id)");
        $enrollMessage = "<p style='color:green;'>Enrolled successfully!</p>";
    } else {
        $enrollMessage = "<p style='color:red;'>You are already enrolled in this batch.</p>";
    }
}

// Get batches with course info
$batches = $conn->query("
    SELECT 
        b.batch_id, 
        b.batch_code, 
        b.start_date, 
        b.end_date, 
        b.status, 
        c.courseName, 
        c.image_path, 
        c.description
    FROM batches b
    INNER JOIN courses c ON b.course_id = c.course_id
    WHERE b.status='active'
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enroll in Batches - Student Dashboard</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; background: #f6f5f8; }

  header { background: #968f9bff; color: white; padding: 15px 30px; text-align: center; }
  .container { display: flex; }

  /* Sidebar */
  .sidebar { width: 240px; background: #b0abb6ff; min-height: 100vh; padding: 20px; }
  .sidebar h3 { color: white; margin-bottom: 15px; }
  .sidebar a { display: block; color: white; text-decoration: none; padding: 10px; margin: 5px 0; border-radius: 4px; }
  .sidebar a:hover, .sidebar a.active { background: #5a189a; }
  .dropdown { position: relative; }
  .dropdown-content { display: none; flex-direction: column; margin-left: 15px; }
  .dropdown:hover .dropdown-content { display: flex; }
  .dropdown-content a { background: #7b2cbf; font-size: 14px; }
  .dropdown-content a:hover { background: #5a189a; }

  /* Main Content */
  .content { flex: 1; padding: 30px; }
  h2 { margin-bottom: 20px; color: #7b2cbf; }
  p { margin-bottom: 15px; }

  /* Grid of batches */
  .batch-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
  .batch-card { background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 15px; text-align: center; }
  .admin-pic { width: 100px; height: 100px; border-radius: 50%; margin-bottom: 15px; border: 3px solid #1abc9c; object-fit: cover; }
  .batch-card img { max-width: 100%; height: 150px; object-fit: cover; border-radius: 6px; margin-bottom: 10px; }
  .batch-card h3 { color: #7b2cbf; margin-bottom: 10px; }
  .batch-card p { font-size: 14px; color: #333; height: 60px; overflow: hidden; margin-bottom: 10px; }
  .batch-card small { display: block; margin-bottom: 10px; color: #555; }
  .batch-card form button { background: #7b2cbf; color: white; border: none; padding: 8px 15px; cursor: pointer; border-radius: 4px; }
  .batch-card form button:hover { background: #5a189a; }

  footer { background: #ab9eb7ff; color: white; text-align: center; padding: 15px; margin-top: 20px; }
</style>
</head>
<body>
  <header>
    <h1>Girls Coding Academy - Student Dashboard</h1>
  </header>

  <div class="container">
    <div class="sidebar">
      <img src="admin.jpg" alt="Admin Picture" class="admin-pic">
      <h3>Navigation</h3>
            <a href="student.php">🏠 Home</a>
      <a href="student_courses.php">📚 My Courses</a>
      <a href="#">📢 Announcements</a>
      <a href="#">📅 My Calendar</a>
      <a href="enroll.php">📅 Enroll</a>
      <a href="student_profile.php">👤 My Profile</a>
      <a href="logout.php">🚪 Logout</a>
    </div>

    <div class="content">
      <h2>Available Batches</h2>
      <?php echo $enrollMessage; ?>

      <div class="batch-grid">
        <?php while($row = $batches->fetch_assoc()) { 
            $imgPath = !empty($row['image_path']) ? $row['image_path'] : 'course1.jpg';
        ?>
          <div class="batch-card">
            <img src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($row['courseName']); ?>">
            <h3><?php echo htmlspecialchars($row['courseName']); ?></h3>
            <p><?php echo htmlspecialchars($row['description']); ?></p>
            <small>Batch Code: <?php echo htmlspecialchars($row['batch_code']); ?></small>
            <small>Start: <?php echo htmlspecialchars($row['start_date']); ?> | End: <?php echo htmlspecialchars($row['end_date']); ?></small>
            <form method="POST" action="">
              <input type="hidden" name="batch_id" value="<?php echo $row['batch_id']; ?>">
              <button type="submit" name="enroll">Enroll</button>
            </form>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>

  <footer>
    &copy; <?php echo date("Y"); ?> Girls Coding Academy. All rights reserved.
  </footer>
</body>
</html>
