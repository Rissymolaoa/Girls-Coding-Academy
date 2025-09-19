<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])){
    header("Location: login.html");
    exit();
}

// Get user info from session
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// DEMO data
$stats = [
  "Enrolled Courses" => 5,
  "Pending Assignments" => 2,
  "Upcoming Classes" => 3,
  "Overall Grade" => "B+"
];

$announcements = [
  "Exam schedule will be released next week.",
  "New coding challenge available in your Python course.",
  "School will remain closed on Friday for maintenance."
];

$upcoming = [
  ["date" => "2025-09-01", "event" => "Math Virtual Class"],
  ["date" => "2025-09-03", "event" => "Python Assignment Due"],
  ["date" => "2025-09-05", "event" => "Hackathon Workshop"]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Dashboard - Girls Coding Academy</title>
  <!-- ✅ Bootstrap Icons CDN -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; background: #f9f9f9; color: #333; }
  header{background:linear-gradient(90deg,#7b2cbf,#5a189a);color:#fff;padding:18px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,0.12)}
  header h1{margin:0;font-size:22px;font-weight:600}

    .container { display: flex; }

    /* Sidebar */
    .sidebar {
      width: 240px;
      background: #343a40;
      min-height: 100vh;
      padding: 20px 15px;
      color: white;
    }

    .sidebar h3 {
      color: #adb5bd;
      margin-bottom: 15px;
      font-size: 16px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .sidebar a {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #f8f9fa;
      text-decoration: none;
      padding: 10px 15px;
      margin: 6px 0;
      border-radius: 6px;
      transition: background 0.3s, color 0.3s;
    }

    .sidebar a i {
      font-size: 18px;
    }

    .sidebar a:hover {
      background: #495057;
      color: #fff;
    }

    .admin-pic {
      width: 90px;
      height: 90px;
      border-radius: 50%;
      margin-bottom: 15px;
      border: 2px solid #ddd;
      object-fit: cover;
      display: block;
      margin-left: auto;
      margin-right: auto;
    }

    /* Content area */
    .content {
      flex: 1;
      padding: 30px;
    }
    h2 { margin-bottom: 15px; color: #333; }
    p { font-size: 16px; margin-bottom: 20px; }

    footer {
      background: #fff;
      color: #333;
      text-align: center;
      padding: 15px;
      margin-top: 20px;
      border-top: 1px solid #ddd;
    }

    /* Dashboard sections */
    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fill,minmax(180px,1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    .card {
      background: white;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
      text-align: center;
      border: 1px solid #eee;
    }
    .card h3 { margin-bottom: 10px; color: #333; }
    .card p { font-size: 20px; font-weight: bold; }

    .section {
      background: white;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
      margin-bottom: 20px;
      border: 1px solid #eee;
    }
    .section h3 { margin-bottom: 10px; color: #333; }
    .section ul { list-style: none; padding-left: 0; }
    .section ul li {
      padding: 8px 0;
      border-bottom: 1px solid #eee;
    }
  </style>
</head>
<body>
  <!-- Header -->
  <header>
    <h1>Girls Coding Academy - Student Dashboard</h1>
  </header>

  <div class="container">
    <!-- Sidebar -->
    <div class="sidebar">
      <img src="admin.png" alt="Student Picture" class="admin-pic">
      <h3>Navigation</h3>
    <a href="student.php"><i class="bi bi-house-door"></i> Home</a>
    <a href="student_courses.php"><i class="bi bi-journal-bookmark"></i> My Courses</a>
    <a href="#"><i class="bi bi-megaphone"></i> Announcements</a>
    <a href="#"><i class="bi bi-calendar-event"></i> My Calendar</a>
    <a href="attendance.php" class="active"><i class="bi bi-card-checklist"></i> Attendance</a>
    <a href="student_profile.php"><i class="bi bi-person-circle"></i> My Profile</a>
    <a href="enroll.php"><i class="bi bi-person-circle"></i> Enrollments</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="content">
      <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
      <p>You are logged in as a <strong><?php echo htmlspecialchars($role); ?></strong>.</p>

      <!-- Quick Stats -->
      <div class="stats">
        <?php foreach($stats as $label => $value): ?>
          <div class="card">
            <h3><?php echo $label; ?></h3>
            <p><?php echo $value; ?></p>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Announcements -->
      <div class="section">
        <h3>Recent Announcements</h3>
        <ul>
          <?php foreach($announcements as $a): ?>
            <li><?php echo $a; ?></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Upcoming Schedule -->
      <div class="section">
        <h3>Upcoming Schedule</h3>
        <ul>
          <?php foreach($upcoming as $u): ?>
            <li><strong><?php echo $u['date']; ?>:</strong> <?php echo $u['event']; ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer>
    &copy; <?php echo date("Y"); ?> Girls Coding Academy. All rights reserved.
  </footer>
</body>
</html>
