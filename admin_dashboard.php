<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

// Check if role is admin
if ($_SESSION['role'] !== 'admin') {
    echo "<h2>Access Denied! You are not authorized to view this page.</h2>";
    exit();
}

// DB connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch summary counts
$total_students = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='student'")->fetch_assoc()['count'];
$total_teachers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='teacher'")->fetch_assoc()['count'];
$total_parents  = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='parent'")->fetch_assoc()['count'];
$total_users    = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_courses  = $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'];
$active_courses = $conn->query("SELECT COUNT(*) as count FROM courses WHERE status='active'")->fetch_assoc()['count'];

// Fetch recent activities (latest 5 students registered)
$recent_students = $conn->query("
    SELECT firstName, lastName, created_at 
    FROM users 
    WHERE role='student' 
    ORDER BY created_at DESC 
    LIMIT 5
");
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
  body{font-family:Inter,Arial,Helvetica,sans-serif;background:#f4f6f9}
  header{background:linear-gradient(90deg,#7b2cbf,#5a189a);color:#fff;padding:18px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,0.12)}
  header h1{margin:0;font-size:22px;font-weight:600}
  .search-bar{max-width:500px;margin:10px auto}
  .layout{display:flex;min-height:calc(100vh - 70px)}
  .sidebar{width:230px;background:#2c3e50;padding:20px;color:#fff}
  .sidebar img{width:90px;height:90px;border-radius:50%;margin-bottom:12px;border:3px solid #1abc9c}
  .sidebar .nav a{display:block;color:#fff;padding:10px;border-radius:6px;margin:5px 0;text-decoration:none}
  .sidebar .nav a:hover, .sidebar .nav a.active{background:#1abc9c;color:#062018}
  .main{flex:1;padding:26px}
  .summary-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:30px}
  .summary-card{background:#fff;padding:18px;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,0.1);display:flex;align-items:center;gap:12px}
  .summary-card .icon{font-size:28px}
  .border-left-purple{border-left:6px solid #7b2cbf}
  .border-left-blue{border-left:6px solid #3498db}
  .border-left-green{border-left:6px solid #1abc9c}
  .border-left-orange{border-left:6px solid #e67e22}
  .border-left-pink{border-left:6px solid #e84393}
  .border-left-red{border-left:6px solid #c0392b}
  .quick-actions{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:30px}
  .quick-actions a{background:#fff;padding:16px;text-align:center;border-radius:10px;text-decoration:none;color:#333;font-weight:600;box-shadow:0 2px 6px rgba(0,0,0,0.1);transition:.3s}
  .quick-actions a:hover{color:#1abc9c;transform:translateY(-4px)}
  .quick-actions i{font-size:22px;display:block;margin-bottom:8px}
  .table-card{background:#fff;padding:16px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.08)}
  footer{background:#2c3e50;color:#fff;text-align:center;padding:15px;margin-top:20px}
</style>
</head>
<body>
<header>
  <form class="search-bar d-flex">
    <input class="form-control me-2" type="search" placeholder="Search..." aria-label="Search">
    <button class="btn btn-light" type="submit"><i class="bi bi-search"></i></button>
  </form>
</header>

<div class="layout">
  <aside class="sidebar">
    <img src="admin.png" alt="Admin">
    <h5 class="text-center">Administration</h5>
    <nav class="nav">
      <a href="admin_dashboard.php" class="active"><i class="bi bi-house-door"></i> Dashboard</a>
      <a href="approve_users.php"><i class="bi bi-person-check"></i> Approve Users</a>
      <a href="manage_courses.php"><i class="bi bi-journal-bookmark"></i> Manage Courses</a>
      <a href="manage_students.php"><i class="bi bi-people"></i> Manage Students</a>
      <a href="manage_teachers.php"><i class="bi bi-person-badge"></i> Manage Teachers</a>
      <a href="parents_summary.php"><i class="bi bi-people-fill"></i> Parent Summary</a>
      <a href="manage_parents.php"><i class="bi bi-person-lines-fill"></i> Manage Parents</a>
      <a href="assign_parent_student.php"><i class="bi bi-person-plus"></i> Assign Students</a>
      <a href="course_assignment.php"><i class="bi bi-book"></i> Assign Courses</a>
      <a href="add_batch.php"><i class="bi bi-plus-circle"></i> Add Batch</a>
      <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
  </aside>

  <main class="main">
    <h2>Summary</h2>
    <div class="summary-cards">
      <div class="summary-card border-left-purple">
        <i class="bi bi-mortarboard-fill icon text-purple"></i>
        <div><h6>Total Students</h6><p><?= $total_students ?></p></div>
      </div>
      <div class="summary-card border-left-blue">
        <i class="bi bi-person-badge-fill icon text-primary"></i>
        <div><h6>Total Teachers</h6><p><?= $total_teachers ?></p></div>
      </div>
      <div class="summary-card border-left-green">
        <i class="bi bi-people-fill icon text-success"></i>
        <div><h6>Total Parents</h6><p><?= $total_parents ?></p></div>
      </div>
      <div class="summary-card border-left-orange">
        <i class="bi bi-people icon text-warning"></i>
        <div><h6>Total Users</h6><p><?= $total_users ?></p></div>
      </div>
      <div class="summary-card border-left-pink">
        <i class="bi bi-journal-text icon text-danger"></i>
        <div><h6>Total Courses</h6><p><?= $total_courses ?></p></div>
      </div>
      <div class="summary-card border-left-red">
        <i class="bi bi-journal-check icon text-danger"></i>
        <div><h6>Active Courses</h6><p><?= $active_courses ?></p></div>
      </div>
    </div>

    <h2>Quick Actions</h2>
    <div class="quick-actions">
      <a href="approve_users.php"><i class="bi bi-person-check"></i> Approve Users</a>
      <a href="manage_courses.php"><i class="bi bi-journal"></i> Manage Courses</a>
      <a href="manage_students.php"><i class="bi bi-people"></i> Manage Students</a>
      <a href="manage_teachers.php"><i class="bi bi-person-badge"></i> Manage Teachers</a>
      <a href="course_assignment.php"><i class="bi bi-book"></i> Assign Courses</a>
      <a href="add_batch.php"><i class="bi bi-plus-circle"></i> Add Batch</a>
    </div>

    <h2>Recent Students</h2>
    <div class="table-card">
      <table class="table table-hover align-middle">
        <thead class="table-dark">
          <tr><th>Name</th><th>Registered On</th></tr>
        </thead>
        <tbody>
          <?php while($s = $recent_students->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($s['firstName']." ".$s['lastName']) ?></td>
              <td><?= htmlspecialchars(date("d F Y, H:i", strtotime($s['created_at']))) ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<footer>
  <p>&copy; <?= date('Y') ?> Girls Coding Academy. All rights reserved.</p>
</footer>
</body>
</html>
