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
<style>
  :root{
    --primary:#7b2cbf;
    --accent:#5a189a;
    --muted:#f4f4f8;
    --card:#ffffff;
    --text:#222;
  }
  *{box-sizing:border-box}
  body{font-family:Inter,Arial,Helvetica,sans-serif;margin:0;background:var(--muted);color:var(--text)}
  header{background:linear-gradient(90deg,var(--primary),var(--accent));color:#fff;padding:18px 24px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,0.12)}
  header h1{margin:0;font-size:20px;font-weight:600}
  .layout{display:flex;min-height:calc(100vh - 72px)}
  .sidebar{width:220px;background:#34495e;padding:20px;display:flex;flex-direction:column;align-items:center;color:#fff}
  .sidebar img{width:92px;height:92px;border-radius:50%;object-fit:cover;border:3px solid #1abc9c;margin-bottom:12px}
  .sidebar h3{font-size:13px;margin:0 0 12px}
  .nav a{width:100%;display:block;color:#fff;text-decoration:none;padding:10px;border-radius:6px;margin:6px 0;text-align:left}
  .nav a.active, .nav a:hover{background:#1abc9c;color:#062018}
  .main{flex:1;padding:26px}
  h2{margin-bottom:16px;color:#333}
  .summary-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:28px}
  .summary-card{background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;padding:18px;border-radius:10px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,0.15)}
  .summary-card h3{margin:0;font-size:16px;font-weight:500}
  .summary-card p{margin-top:8px;font-size:22px;font-weight:700}
  .quick-actions{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px}
  .quick-actions a{display:block;background:linear-gradient(135deg,#9b59b6,#8e44ad);color:#fff;text-align:center;padding:15px;border-radius:10px;text-decoration:none;font-weight:600;box-shadow:0 2px 6px rgba(0,0,0,0.15);transition:.3s}
  .quick-actions a:hover{background:linear-gradient(135deg,#8e44ad,#732d91)}
  .table-card{background:var(--card);padding:14px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.06)}
  table{width:100%;border-collapse:collapse;font-size:14px}
  th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left}
  th{background:linear-gradient(90deg,var(--primary),var(--accent));color:#fff}
  footer{background:#2c3e50;color:#fff;text-align:center;padding:15px;margin-top:20px}
  @media(max-width:900px){.sidebar{display:none}}
</style>
</head>
<body>
<header>
  <h1>Girls Coding Academy - Admin Dashboard</h1>
</header>

<div class="layout">
  <aside class="sidebar">
    <img src="admin.jpg" alt="Admin">
    <h3>GIRLS CODING ACADEMY</h3>
    <nav class="nav">
      <a href="admin_dashboard.php" class="active">🏠 Dashboard</a>
      <a href="approve_users.php">📝 Approve Users</a>
      <a href="manage_courses.php">📚 Manage Courses</a>
      <a href="manage_students.php">👩‍🎓 Manage Students</a>
      <a href="manage_teachers.php">👨‍🏫 Manage Teachers</a>
      <a href="parents_summary.php">👪 Parents Summary</a>
     <a href="manage_parents.php">👪 Manage Parents</a>
      <a href="assign_parent_student.php">👨‍🏫 Assign Students</a>
      <a href="course_assignment.php">📌 Assign Courses</a>
      <a href="add_batch.php">➕ Add Batch</a>
      <a href="logout.php">🚪 Logout</a>
    </nav>
  </aside>

  <main class="main">
    <h2>Summary</h2>
    <div class="summary-cards">
      <div class="summary-card"><h3>👩‍🎓  Total Students</h3><p><?= $total_students ?></p></div>
      <div class="summary-card"><h3>👨‍🏫  Total Teachers</h3><p><?= $total_teachers ?></p></div>
      <div class="summary-card"><h3>👪  Total Parents</h3><p><?= $total_parents ?></p></div>
      <div class="summary-card"><h3>Total Users</h3><p><?= $total_users ?></p></div>
      <div class="summary-card"><h3>📚  Total Courses</h3><p><?= $total_courses ?></p></div>
      <div class="summary-card"><h3>📚  Active Courses</h3><p><?= $active_courses ?></p></div>
    </div>

    <h2>Quick Actions</h2>
    <div class="quick-actions">
      <a href="approve_users.php">Approve Users</a>
      <a href="manage_courses.php">Manage Courses</a>
      <a href="manage_students.php">Manage Students</a>
      <a href="manage_teachers.php">Manage Teachers</a>
      <a href="course_assignment.php">Assign Courses</a>
      <a href="add_batch.php">Add Batch</a>
    </div>

    <h2>Recent Students</h2>
    <div class="table-card">
      <table>
        <thead><tr><th>Name</th><th>Registered On</th></tr></thead>
        <tbody>
          <?php while($s = $recent_students->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($s['firstName']." ".$s['lastName']) ?></td>
              <td><?= htmlspecialchars(date("d F Y", strtotime($s['created_at']))) ?> <?= date("H:i", strtotime($s['created_at'])) ?></td>

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
