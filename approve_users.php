<?php
session_start();

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// DB connection
$host = "localhost:3307";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "<br>Tip: Ensure XAMPP MySQL is running and the database exists.");
}

// Handle actions
if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    $action = $_GET['action'];

    if ($action === "approve") {
        $conn->query("UPDATE users SET status='active' WHERE user_id=$user_id");
    } elseif ($action === "reject") {
        $conn->query("UPDATE users SET status='rejected' WHERE user_id=$user_id");
    } elseif ($action === "waitlist") {
        $conn->query("UPDATE users SET status='waitlist' WHERE user_id=$user_id");
    } elseif ($action === "delete") {
        $conn->query("DELETE FROM users WHERE user_id=$user_id");
    }
    header("Location: approve_users.php");
    exit();
}

// Fetch users by status
$pending = $conn->query("SELECT user_id, firstName, lastName, email, created_at FROM users WHERE status='pending'");
$waitlist = $conn->query("SELECT user_id, firstName, lastName, email, created_at FROM users WHERE status='waitlist'");
$rejected = $conn->query("SELECT user_id, firstName, lastName, email, created_at FROM users WHERE status='rejected'");
$recent = $conn->query("SELECT user_id, firstName, lastName, email, updated_at FROM users WHERE status='active' ORDER BY updated_at DESC LIMIT 5");

// Fetch counts for stats
$totalUsers = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$activeUsers = $conn->query("SELECT COUNT(*) as total FROM users WHERE status='active'")->fetch_assoc()['total'];
$waitlistedUsers = $conn->query("SELECT COUNT(*) as total FROM users WHERE status='waitlist'")->fetch_assoc()['total'];
$rejectedUsers = $conn->query("SELECT COUNT(*) as total FROM users WHERE status='rejected'")->fetch_assoc()['total'];
$pendingUsers = $conn->query("SELECT COUNT(*) as total FROM users WHERE status='pending'")->fetch_assoc()['total'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Admin - Approve Users</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

  .section-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid rgba(255, 255, 255, 0.2);
  }

  .section-card h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1.5rem;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
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

  .btn {
    padding: 0.5rem 1rem;
    text-decoration: none;
    border-radius: 8px;
    color: white;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.3s ease;
    margin: 0 0.25rem;
    display: inline-block;
  }

  .btn-success { background: var(--success-gradient); }
  .btn-danger { background: var(--danger-gradient); }
  .btn-warning { background: var(--warning-gradient); }

  .btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-sm);
  }

  .no-data {
    text-align: center;
    color: #6b7280;
    font-style: italic;
    padding: 2rem;
  }

  .stats-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
  }

  .stat-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 1.5rem;
    border-radius: 16px;
    text-align: center;
    box-shadow: var(--shadow-md);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
  }

  .stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
  }

  .stat-card h6 {
    margin: 0;
    font-size: 0.95rem;
    color: #6b7280;
    font-weight: 600;
  }

  .stat-card p {
    margin: 0;
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
  }

  .charts-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid rgba(255, 255, 255, 0.2);
    margin-bottom: 2rem;
  }

  .charts-section h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1.5rem;
  }

  .chart-container {
    position: relative;
    height: 300px;
    margin-bottom: 2rem;
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
    .stats-section {
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
    <div class="stats-section">
      <div class="stat-card">
        <h6>Total Users</h6>
        <p><?= $totalUsers ?></p>
      </div>
      <div class="stat-card">
        <h6>Pending</h6>
        <p><?= $pendingUsers ?></p>
      </div>
      <div class="stat-card">
        <h6>Waitlisted</h6>
        <p><?= $waitlistedUsers ?></p>
      </div>
      <div class="stat-card">
        <h6>Rejected</h6>
        <p><?= $rejectedUsers ?></p>
      </div>
      <div class="stat-card">
        <h6>Active</h6>
        <p><?= $activeUsers ?></p>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-8">
        <div class="section-card">
          <h2>Awaiting Approval</h2>
          <?php if ($pending->num_rows > 0) { ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Registration Date</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($row = $pending->fetch_assoc()) { ?>
                    <tr>
                      <td><?= htmlspecialchars($row['firstName'].' '.$row['lastName']) ?></td>
                      <td><?= htmlspecialchars($row['email']) ?></td>
                      <td><?= htmlspecialchars(date("M j, Y", strtotime($row['created_at']))) ?></td>
                      <td>
                        <a class="btn btn-success" href="approve_users.php?action=approve&user_id=<?= $row['user_id'] ?>">Approve</a>
                        <a class="btn btn-danger" href="approve_users.php?action=reject&user_id=<?= $row['user_id'] ?>">Reject</a>
                        <a class="btn btn-warning" href="approve_users.php?action=waitlist&user_id=<?= $row['user_id'] ?>">Waitlist</a>
                      </td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          <?php } else { ?>
            <p class="no-data">No users awaiting approval.</p>
          <?php } ?>
        </div>

        <div class="section-card">
          <h2>Waiting List</h2>
          <?php if ($waitlist->num_rows > 0) { ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Registered At</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($row = $waitlist->fetch_assoc()) { ?>
                    <tr>
                      <td><?= htmlspecialchars($row['firstName'].' '.$row['lastName']) ?></td>
                      <td><?= htmlspecialchars($row['email']) ?></td>
                      <td><?= htmlspecialchars(date("M j, Y", strtotime($row['created_at']))) ?></td>
                      <td>
                        <a class="btn btn-success" href="approve_users.php?action=approve&user_id=<?= $row['user_id'] ?>">Approve</a>
                        <a class="btn btn-danger" href="approve_users.php?action=delete&user_id=<?= $row['user_id'] ?>">Delete</a>
                      </td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          <?php } else { ?>
            <p class="no-data">No waitlisted users.</p>
          <?php } ?>
        </div>

        <div class="section-card">
          <h2>Rejections</h2>
          <?php if ($rejected->num_rows > 0) { ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Registered At</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($row = $rejected->fetch_assoc()) { ?>
                    <tr>
                      <td><?= htmlspecialchars($row['firstName'].' '.$row['lastName']) ?></td>
                      <td><?= htmlspecialchars($row['email']) ?></td>
                      <td><?= htmlspecialchars(date("M j, Y", strtotime($row['created_at']))) ?></td>
                      <td>
                        <a class="btn btn-success" href="approve_users.php?action=approve&user_id=<?= $row['user_id'] ?>">Approve</a>
                        <a class="btn btn-danger" href="approve_users.php?action=delete&user_id=<?= $row['user_id'] ?>">Delete</a>
                      </td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          <?php } else { ?>
            <p class="no-data">No rejected users.</p>
          <?php } ?>
        </div>

        <div class="section-card">
          <h2>Recent Approvals</h2>
          <?php if ($recent->num_rows > 0) { ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Approved At</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($row = $recent->fetch_assoc()) { ?>
                    <tr>
                      <td><?= htmlspecialchars($row['firstName'].' '.$row['lastName']) ?></td>
                      <td><?= htmlspecialchars($row['email']) ?></td>
                      <td><?= htmlspecialchars(date("M j, Y", strtotime($row['updated_at']))) ?></td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          <?php } else { ?>
            <p class="no-data">No recent approvals.</p>
          <?php } ?>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="charts-section">
          <h2>Application Statistics</h2>
          <div class="chart-container">
            <canvas id="barChart"></canvas>
          </div>
          <div class="chart-container">
            <canvas id="pieChart"></canvas>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const barCtx = document.getElementById('barChart').getContext('2d');
  const barChart = new Chart(barCtx, {
    type: 'bar',
    data: {
      labels: ['Pending', 'Waitlisted', 'Rejected', 'Active'],
      datasets: [{
        label: 'Users',
        data: [<?= $pendingUsers ?>, <?= $waitlistedUsers ?>, <?= $rejectedUsers ?>, <?= $activeUsers ?>],
        backgroundColor: ['#1abc9c','#f39c12','#e74c3c','#27ae60'],
        borderRadius: 8,
        borderSkipped: false,
      }]
    },
    options: { 
      responsive: true, 
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true }
      }
    }
  });

  const pieCtx = document.getElementById('pieChart').getContext('2d');
  const pieChart = new Chart(pieCtx, {
    type: 'doughnut',
    data: {
      labels: ['Pending', 'Waitlisted', 'Rejected', 'Active'],
      datasets: [{
        data: [<?= $pendingUsers ?>, <?= $waitlistedUsers ?>, <?= $rejectedUsers ?>, <?= $activeUsers ?>],
        backgroundColor: ['#1abc9c','#f39c12','#e74c3c','#27ae60'],
        borderWidth: 0,
      }]
    },
    options: { 
      responsive: true,
      cutout: '50%'
    }
  });
</script>
</body>
</html>