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
        $enrollMessage = "<div class='alert alert-success'>Enrolled successfully!</div>";
    } else {
        $enrollMessage = "<div class='alert alert-danger'>You are already enrolled in this batch.</div>";
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

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
body { background:#f6f5f8; font-family: Arial,sans-serif; }
header { background:#343a40; color: #fff; padding: 18px 24px; text-align: center; box-shadow:0 2px 6px rgba(0,0,0,0.12);}
header h1 { margin:0; font-size:22px; }

.d-flex { display:flex; flex-wrap:nowrap; min-height:100vh; }
.sidebar { width:250px; background:#343a40; padding:20px; color:#fff; }
.sidebar h3 { text-align:center; margin-bottom:20px; font-weight:bold; }
.sidebar a { display:flex; align-items:center; gap:10px; color:white; text-decoration:none; padding:10px; margin:5px 0; border-radius:6px; transition:0.2s; }
.sidebar a:hover, .sidebar a.active { background:#495057; }
.admin-pic { width:90px; height:90px; border-radius:50%; display:block; margin:auto; margin-bottom:15px; border:2px solid #1abc9c; object-fit:cover; }

.content { flex:1; padding:30px; }

.batch-card img { max-width:100%; height:150px; object-fit:cover; border-radius:6px; margin-bottom:10px; }
.batch-card h5 { color:#7b2cbf; margin-bottom:10px; }
.batch-card p { font-size:14px; color:#333; height:60px; overflow:hidden; margin-bottom:10px; }
.batch-card small { display:block; margin-bottom:10px; color:#555; }
.batch-card form button { background:#7b2cbf; color:white; border:none; padding:6px 12px; cursor:pointer; border-radius:4px; }
.batch-card form button:hover { background:#5a189a; }

.view-toggle { margin-bottom:20px; }

/* Grid View (default) */
.batch-grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(250px,1fr)); gap:20px; }

/* List View */
.list-view {
    display:flex;
    flex-direction:column;
    gap:15px;
}
.list-view .batch-card {
    display:flex;
    flex-direction:row;
    align-items:center;
    gap:15px;
    height:auto;
}
.list-view .batch-card img {
    width:150px;
    height:100px;
    object-fit:cover;
    border-radius:6px;
}
.list-view .card-body {
    flex:1;
}
</style>
</head>
<body>

<header>
    <h1>Girls Coding Academy - Student Dashboard</h1>
</header>

<div class="d-flex">
  <!-- Sidebar -->
  <div class="sidebar">
    <img src="admin.png" alt="Student Picture" class="admin-pic">
    <h3 style="text-align:center;margin-bottom:10px;">Navigation</h3>
    <a href="student.php"><i class="bi bi-house-door"></i> Home</a>
     <a href="student_profile.php"><i class="bi bi-person-circle"></i> My Profile</a>
    <a href="student_courses.php"><i class="bi bi-journal-bookmark"></i> My Courses</a>
     <a href="#"><i class="bi bi-megaphone"></i> Announcements</a>
     <a href="#"><i class="bi bi-calendar-event"></i> My Calendar</a>
    <a href="attendance.php" class="active"><i class="bi bi-card-checklist"></i> My Schedule</a>
    <a href="student_marks.php"><i class="bi bi-bar-chart-line-fill"></i> My Grades</a> 
    <a href="student_gradebook.php"><i class="bi bi-graph-up"></i> My Performance</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </div>

  <!-- Content -->
  <div class="content flex-fill">
      <h2>Available Batches</h2>
      <?php echo $enrollMessage; ?>

      <!-- View Toggle Buttons -->
      <div class="view-toggle mb-3">
        <button id="gridView" class="btn btn-sm btn-primary me-2"><i class="bi bi-grid-fill"></i> Grid</button>
        <button id="listView" class="btn btn-sm btn-secondary"><i class="bi bi-list-ul"></i> List</button>
      </div>

      <!-- Batch Container -->
      <div id="batchContainer" class="batch-grid">
        <?php while($row = $batches->fetch_assoc()) { 
            $imgPath = !empty($row['image_path']) ? $row['image_path'] : 'course1.jpg';
        ?>
        <div class="card batch-card p-3">
          <img src="<?php echo htmlspecialchars($imgPath); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($row['courseName']); ?>">
          <div class="card-body">
            <h5 class="card-title"><?php echo htmlspecialchars($row['courseName']); ?></h5>
            <p class="card-text"><?php echo htmlspecialchars($row['description']); ?></p>
            <small>Batch Code: <?php echo htmlspecialchars($row['batch_code']); ?></small><br>
            <small>Start: <?php echo htmlspecialchars($row['start_date']); ?> | End: <?php echo htmlspecialchars($row['end_date']); ?></small>
            <form method="POST" action="" class="mt-2">
                <input type="hidden" name="batch_id" value="<?php echo $row['batch_id']; ?>">
                <button type="submit" name="enroll" class="btn btn-sm"><i class="bi bi-person-plus"></i> Enroll</button>
            </form>
          </div>
        </div>
        <?php } ?>
      </div>
  </div>
</div>

<footer class="text-center text-white mt-4 p-3" style="background:#343a40;">
    &copy; <?php echo date("Y"); ?> Girls Coding Academy. All rights reserved.
</footer>

<script>
const batchContainer = document.getElementById('batchContainer');
const gridViewBtn = document.getElementById('gridView');
const listViewBtn = document.getElementById('listView');

gridViewBtn.addEventListener('click', () => {
    batchContainer.classList.remove('list-view');
    gridViewBtn.classList.replace('btn-secondary','btn-primary');
    listViewBtn.classList.replace('btn-primary','btn-secondary');
});

listViewBtn.addEventListener('click', () => {
    batchContainer.classList.add('list-view');
    listViewBtn.classList.replace('btn-secondary','btn-primary');
    gridViewBtn.classList.replace('btn-primary','btn-secondary');
});
</script>

</body>
</html>
