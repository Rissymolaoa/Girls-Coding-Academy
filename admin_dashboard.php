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

// DB connection with error handling
$host = "localhost";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb";

try {
    $conn = new mysqli($host, $user, $pass);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Check if database exists, create if not
    $conn->query("CREATE DATABASE IF NOT EXISTS `$db`");
    $conn->select_db($db);
    
    // Create tables if they don't exist (basic structure for the academy management system)
    $conn->query("CREATE TABLE IF NOT EXISTS `users` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        firstName VARCHAR(50),
        lastName VARCHAR(50),
        role ENUM('admin', 'student', 'teacher', 'parent'),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending', 'approved') DEFAULT 'pending'
    )");
    
    $conn->query("CREATE TABLE IF NOT EXISTS `courses` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        status ENUM('active', 'inactive') DEFAULT 'active'
    )");
    
} catch (Exception $e) {
    die("Database connection/setup failed: " . $e->getMessage() . "<br>Tip: Ensure XAMPP MySQL is running on port 3306. Check XAMPP Control Panel and start MySQL module.");
}

// Fetch summary counts (with fallback to 0 if query fails)
function getCount($conn, $query) {
    $result = $conn->query($query);
    return $result ? $result->fetch_assoc()['count'] : 0;
}

$total_students = getCount($conn, "SELECT COUNT(*) as count FROM users WHERE role='student'");
$total_teachers = getCount($conn, "SELECT COUNT(*) as count FROM users WHERE role='teacher'");
$total_parents  = getCount($conn, "SELECT COUNT(*) as count FROM users WHERE role='parent'");
$total_users    = getCount($conn, "SELECT COUNT(*) as count FROM users");
$total_courses  = getCount($conn, "SELECT COUNT(*) as count FROM courses");
$active_courses = getCount($conn, "SELECT COUNT(*) as count FROM courses WHERE status='active'");

// Fetch recent activities (latest 5 students registered)
$recent_students = $conn->query("
    SELECT firstName, lastName, created_at 
    FROM users 
    WHERE role='student' 
    ORDER BY created_at DESC 
    LIMIT 5
");
if (!$recent_students) {
    $recent_students = new mysqli_result($conn); // Empty result if query fails
}
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
  :root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --warning-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    --info-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.1), 0 2px 4px rgba(0,0,0,0.06);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.1), 0 4px 6px rgba(0,0,0,0.05);
  }

  body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
    padding-top: 56px;
  }

  .content {
    min-height: calc(100vh - 56px);
    transition: all 0.3s ease;
  }

  .main {
    padding: 2rem 2rem 2rem 1rem;
  }

  .welcome-section {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-lg);
    border: 1px solid rgba(255, 255, 255, 0.2);
  }

  .welcome-section h1 {
    font-size: 2.5rem;
    font-weight: 700;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
  }

  .welcome-section p {
    color: #6b7280;
    font-size: 1.1rem;
  }

  .summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
  }

  .summary-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 1.5rem;
    border-radius: 16px;
    box-shadow: var(--shadow-md);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.2);
    position: relative;
    overflow: hidden;
  }

  .summary-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-gradient);
  }

  .summary-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
  }

  .summary-card .icon {
    font-size: 2.5rem;
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--primary-gradient);
    color: white;
    flex-shrink: 0;
  }

  .summary-card .icon.secondary { background: var(--secondary-gradient); }
  .summary-card .icon.success { background: var(--success-gradient); }
  .summary-card .icon.warning { background: var(--warning-gradient); }
  .summary-card .icon.danger { background: var(--danger-gradient); }
  .summary-card .icon.info { background: var(--info-gradient); }

  .summary-card h6 {
    margin: 0;
    font-size: 0.95rem;
    color: #6b7280;
    font-weight: 600;
  }

  .summary-card p {
    margin: 0;
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
  }

  .quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
  }

  .quick-action {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 1.5rem;
    text-align: center;
    border-radius: 16px;
    text-decoration: none;
    color: #374151;
    font-weight: 600;
    box-shadow: var(--shadow-md);
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.2);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
  }

  .quick-action:hover {
    color: #1f2937;
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    background: white;
  }

  .quick-action i {
    font-size: 2.5rem;
    color: var(--primary-gradient);
    background: rgba(102, 126, 234, 0.1);
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .recent-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid rgba(255, 255, 255, 0.2);
  }

  .recent-section h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1.5rem;
  }

  .table {
    margin-bottom: 0;
  }

  .table th {
    background: var(--primary-gradient);
    color: white;
    border: none;
    font-weight: 600;
    padding: 1rem;
  }

  .table td {
    padding: 1rem;
    vertical-align: middle;
    border-color: rgba(0,0,0,0.05);
  }

  .table-hover tbody tr:hover {
    background-color: rgba(102, 126, 234, 0.05);
  }

  footer {
    background: rgba(31, 41, 55, 0.8);
    color: #fff;
    text-align: center;
    padding: 1.5rem;
    margin-top: 2rem;
    border-radius: 16px 16px 0 0;
  }

  /* Enhanced Sidebar Styles - Adjusted for Dashboard */
  .sidebar {
    width: 280px;
    background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
    color: #fff;
    position: fixed;
    top: 56px;
    height: calc(100vh - 56px);
    left: 0;
    overflow-y: auto;
    transition: all 0.3s ease;
    box-shadow: 4px 0 15px rgba(0,0,0,0.2);
    z-index: 1030;
  }

  @media (min-width: 992px) {
    .main {
      padding-left: 1rem;
      padding-right: 2rem;
    }
    .content {
      margin-left: 280px;
    }
  }

  @media (max-width: 991px) {
    .sidebar {
      top: 0;
      height: 100vh;
      left: -280px;
    }
    .sidebar.show {
      left: 0;
    }
    .main {
      padding: 1rem;
    }
  }

  /* Responsive adjustments */
  @media (max-width: 768px) {
    .summary-cards {
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
    }
    .quick-actions {
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 1rem;
    }
  }
</style>
</head>
<body>
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="content">
  <main class="main">
    <div class="welcome-section">
      <h1>Administration</h1>
    </div>

    <div class="summary-cards">
      <div class="summary-card">
        <i class="bi bi-mortarboard-fill icon"></i>
        <div>
          <h6>Total Students</h6>
          <p><?= $total_students ?></p>
        </div>
      </div>
      <div class="summary-card">
        <i class="bi bi-person-badge-fill icon secondary"></i>
        <div>
          <h6>Total Teachers</h6>
          <p><?= $total_teachers ?></p>
        </div>
      </div>
      <div class="summary-card">
        <i class="bi bi-people-fill icon success"></i>
        <div>
          <h6>Total Parents</h6>
          <p><?= $total_parents ?></p>
        </div>
      </div>
      <div class="summary-card">
        <i class="bi bi-people icon warning"></i>
        <div>
          <h6>Total Users</h6>
          <p><?= $total_users ?></p>
        </div>
      </div>
      <div class="summary-card">
        <i class="bi bi-journal-text icon danger"></i>
        <div>
          <h6>Total Courses</h6>
          <p><?= $total_courses ?></p>
        </div>
      </div>
      <div class="summary-card">
        <i class="bi bi-journal-check icon info"></i>
        <div>
          <h6>Active Courses</h6>
          <p><?= $active_courses ?></p>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-8">
        <div class="recent-section">
          <h2>Recent Students</h2>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Registered On</th>
                </tr>
              </thead>
              <tbody>
                <?php while($s = $recent_students->fetch_assoc()): ?>
                  <tr>
                    <td><?= htmlspecialchars($s['firstName']." ".$s['lastName']) ?></td>
                    <td><?= htmlspecialchars(date("d F Y, H:i", strtotime($s['created_at']))) ?></td>
                  </tr>
                <?php endwhile; ?>
                <?php if ($recent_students->num_rows === 0): ?>
                  <tr><td colspan="2" class="text-center text-muted">No recent students yet. Start by approving some users!</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <h2>Quick Actions</h2>
        <div class="quick-actions">
          <a href="approve_users.php" class="quick-action">
            <i class="bi bi-person-check"></i>
            <span>Approve Users</span>
          </a>
          <a href="manage_courses.php" class="quick-action">
            <i class="bi bi-journal"></i>
            <span>Manage Courses</span>
          </a>
          <a href="manage_students.php" class="quick-action">
            <i class="bi bi-people"></i>
            <span>Manage Students</span>
          </a>
          <a href="manage_teachers.php" class="quick-action">
            <i class="bi bi-person-badge"></i>
            <span>Manage Teachers</span>
          </a>
          <a href="course_assignment.php" class="quick-action">
            <i class="bi bi-book"></i>
            <span>Assign Courses</span>
          </a>
          <a href="add_batch.php" class="quick-action">
            <i class="bi bi-plus-circle"></i>
            <span>Add Batch</span>
          </a>
        </div>
      </div>
    </div>
  </main>
</div>

<footer class="text-center py-3">
  <p>&copy; 2025 Girls Coding Academy. All rights reserved.</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
