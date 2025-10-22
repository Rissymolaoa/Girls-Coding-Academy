<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}
include("db.php");

// Get top 5 parents with their linked student name(s)
$parents_sql = "
    SELECT 
        u.user_id, 
        u.firstName, 
        u.lastName, 
        u.gender, 
        u.phone, 
        u.email, 
        su.firstName AS studentFirstName,
        su.lastName  AS studentLastName,
        st.photo     AS studentPhoto
    FROM users u
    INNER JOIN parents p ON p.user_id = u.user_id
    LEFT JOIN parent_students ps ON ps.parent_id = p.parent_id
    LEFT JOIN students st ON ps.student_id = st.student_id
    LEFT JOIN users su ON st.user_id = su.user_id
    WHERE u.role = 'parent'
    ORDER BY u.created_at DESC
    LIMIT 5
";

$parents = $conn->query($parents_sql);

// Collect parents_data and images
$parents_data = [];
$students_images = [];
$i = 0;
while ($row = $parents->fetch_assoc()) {
    if ($i < 4 && !empty($row['studentPhoto'])) {
        $students_images[] = $row['studentPhoto'];
    }
    $parents_data[] = $row;
    $i++;
}

// Summary counts
$total_parents = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='parent'")->fetch_assoc()['total'];
$total_relations = $conn->query("SELECT COUNT(*) AS total FROM parent_students")->fetch_assoc()['total'];
$total_students = $conn->query("SELECT COUNT(DISTINCT student_id) AS total FROM parent_students")->fetch_assoc()['total'];

$username = $_SESSION['username'] ?? "Admin";
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Parents — Admin</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

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

  .view-all {
    text-align: right;
    margin-bottom: 1rem;
  }

  .view-all a {
    color: #1f2937;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.3s ease;
  }

  .view-all a:hover {
    color: #667eea;
  }

  .student-photos {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
  }

  .student-card {
    text-align: center;
    background: white;
    border-radius: 12px;
    padding: 1rem;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
  }

  .student-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
  }

  .student-photo {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 50%;
    border: 3px solid var(--primary-gradient);
    display: block;
    margin: 0 auto 0.5rem;
  }

  .summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-top: 2rem;
  }

  .summary-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: var(--shadow-md);
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.2);
  }

  .summary-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
  }

  .summary-card i {
    font-size: 2rem;
    color: white;
    padding: 0.75rem;
    border-radius: 50%;
    margin-bottom: 0.5rem;
    display: inline-block;
  }

  .summary-card .parents i {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
  }

  .summary-card .relations i {
    background: linear-gradient(135deg, #3498db, #2980b9);
  }

  .summary-card .students i {
    background: linear-gradient(135deg, #27ae60, #229954);
  }

  .summary-card h6 {
    margin: 0;
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 600;
  }

  .summary-card p {
    margin: 0;
    font-size: 1.75rem;
    font-weight: 700;
    color: #1f2937;
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
    .student-photos {
      grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    }
    .summary-cards {
      grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    }
  }
</style>
</head>
<body>
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="content">
  <main class="main">
    <div class="row">
      <div class="col-12 mb-3">
        <div class="d-flex justify-content-between align-items-center">
          <h2>Manage Parents</h2>
          <div class="view-all"><a href="all_parents_summary.php">View All</a></div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-8">
        <div class="section-card">
          <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
              <thead>
                <tr>
                  <th>Firstname</th>
                  <th>Lastname</th>
                  <th>Gender</th>
                  <th>Phone</th>
                  <th>Email</th>
                  <th>Student Name</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($parents_data as $p): ?>
                <tr>
                  <td><?= htmlspecialchars($p['firstName']) ?></td>
                  <td><?= htmlspecialchars($p['lastName']) ?></td>
                  <td><?= htmlspecialchars($p['gender']) ?></td>
                  <td><?= htmlspecialchars($p['phone']) ?></td>
                  <td><?= htmlspecialchars($p['email']) ?></td>
                  <td><?= htmlspecialchars(trim($p['studentFirstName'].' '.$p['studentLastName'])) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="section-card">
          <h3>Students</h3>
          <div class="student-photos">
            <?php foreach ($parents_data as $p): ?>
              <?php if (!empty($p['studentPhoto'])): ?>
                <div class="student-card">
                  <img src="<?= htmlspecialchars($p['studentPhoto']) ?>" alt="" class="student-photo">
                  <div class="small fw-semibold"><?= htmlspecialchars(trim($p['studentFirstName'].' '.$p['studentLastName'])) ?></div>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="summary-cards">
          <div class="summary-card parents">
            <i class="bi bi-people-fill"></i>
            <h6>Parents</h6>
            <p><?= $total_parents ?></p>
          </div>

          <div class="summary-card relations">
            <i class="bi bi-link-45deg"></i>
            <h6>Relations</h6>
            <p><?= $total_relations ?></p>
          </div>

          <div class="summary-card students">
            <i class="bi bi-mortarboard-fill"></i>
            <h6>Students</h6>
            <p><?= $total_students ?></p>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<footer class="text-center py-3">
  <p>&copy; <?= date("Y") ?> Girls Coding Academy. All rights reserved.</p>
</footer>

<!-- Bootstrap JS (optional for interactive components) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>