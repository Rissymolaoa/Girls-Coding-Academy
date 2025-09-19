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
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
 :root {
  --primary:#7b2cbf;
  --accent:#5a189a;
  --muted:#f4f4f8;
  --card:#fff;
  --text:#222;
  --border1:#e63946;
  --border2:#1d3557;
  --border3:#2a9d8f;
}

body {
  font-family: Inter, system-ui, sans-serif;
  background: var(--muted);
  color: var(--text);
}

/* Topbar */
.topbar {
  background: var(--primary);
  padding: 10px 20px;
  color: #fff;
}
.topbar .search-input {
  max-width: 420px;
}
.topbar .icon-btn {
  width:42px; height:42px; border-radius:8px;
  display:inline-flex; align-items:center; justify-content:center;
  background: rgba(255,255,255,0.12); color: #fff; border: none;
}

/* Sidebar */
.sidebar {
  background:#34495e;
  min-height:calc(100vh - 56px);
  padding:20px;
  color:#fff;
}
.sidebar a {
  color:#fff; text-decoration:none;
  display:block; padding:8px 10px; border-radius:6px;
}
.sidebar a.active,
.sidebar a:hover {
  background:#1abc9c; color:#062018;
}

/* Table */
.table-card {
  padding:16px;
  border-radius:10px;
  background:#fff;
  box-shadow:0 6px 18px rgba(12,12,24,0.06);
}
.table thead th {
  background: linear-gradient(90deg,var(--primary),var(--accent));
  color:#fff;
}
.table td, .table th {
  vertical-align: middle;
}

/* View all link */
.view-all {
  margin-bottom: 8px;
  text-align: right;
}
.view-all a {
  text-decoration:none;
  color:var(--primary);
  font-weight:600;
}

/* Student images */
.student-card { text-align:center; }
.student-photo {
  width:110px; height:110px; object-fit:cover;
  border-radius: 50% / 40%;
  border: 3px solid var(--primary);
  display:block; margin:0 auto 8px;
}

/* Summary cards (below table) */
.summary {
  margin-top: 20px;
  display: flex;
  gap: 20px;
  justify-content: center;
}
.summary .summary-card {
  width:120px; height:120px;
  display:flex; flex-direction:column;
  align-items:center; justify-content:center;
  border-radius:12px; background:#fff;
  box-shadow:0 6px 16px rgba(12,12,24,0.06);
  font-weight:600;
}
.summary .summary-card i {
  font-size:22px; color:#fff;
  padding:8px; border-radius:6px; margin-bottom:8px;
}
.summary .parents { border:3px solid var(--border1); }
.summary .parents i { background:var(--border1); }
.summary .relations { border:3px solid var(--border2); }
.summary .relations i { background:var(--border2); }
.summary .students { border:3px solid var(--border3); }
.summary .students i { background:var(--border3); }


</style>
</head>
<body>

<div class="topbar d-flex align-items-center justify-content-between">
  <form class="d-flex align-items-center" method="get" action="">
    <input type="search" name="search" class="form-control form-control-sm search-input me-2" placeholder="Search parents, students, phone or email...">
    <button class="btn btn-light btn-sm" type="submit"><i class="bi bi-search"></i></button>
  </form>

  <div class="d-flex align-items-center gap-2">
    <button class="icon-btn" title="Notifications"><i class="bi bi-bell-fill" aria-hidden="true"></i></button>
    <button class="icon-btn" title="Messages"><i class="bi bi-envelope-fill" aria-hidden="true"></i></button>
    <div class="ms-2 fw-semibold"><?= htmlspecialchars($username) ?></div>
  </div>
</div>

<div class="d-flex">
  <!-- SIDEBAR -->
  <aside class="sidebar pe-3" style="width:240px;">
    <div class="text-center mb-3">
      <img src="admin.png" alt="Admin" class="rounded-circle" style="width:92px; height:92px; object-fit:cover; border:3px solid #1abc9c;">
    </div>
    <nav class="mt-3">
    <h4 class="text-center mb-4">Administration</h4>
    <a href="admin_dashboard.php" class="active"><i class="bi bi-house-door-fill"></i> Dashboard</a>
    <a href="approve_users.php"><i class="bi bi-person-check-fill"></i> Approve Users</a>
    <a href="manage_courses.php"><i class="bi bi-journal-bookmark-fill"></i> Manage Courses</a>
    <a href="manage_students.php"><i class="bi bi-people-fill"></i> Manage Students</a>
    <a href="manage_teachers.php"><i class="bi bi-person-badge-fill"></i> Manage Teachers</a>
    <a href="parents_summary.php"><i class="bi bi-people"></i> Parent Summary</a>
    <a href="manage_parents.php"><i class="bi bi-person-lines-fill"></i> Manage Parents</a>
    <a href="assign_parent_student.php"><i class="bi bi-person-plus-fill"></i> Assign Students</a>
    <a href="course_assignment.php"><i class="bi bi-book-half"></i> Assign Courses</a>
    <a href="add_batch.php"><i class="bi bi-plus-circle-fill"></i> Add Batch</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
  </aside>

  <!-- MAIN -->
  <main class="flex-fill p-4">
    <div class="row">
      <div class="col-12 mb-3 d-flex justify-content-between align-items-center">
        <h4 class="m-0">Manage Parents</h4>
        <div class="view-all"><a href="all_parents_summary.php">View All</a></div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-8">
        <div class="table-card">
          <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered align-middle mb-0">
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
        <!-- student photos grid -->
        <div class="card mb-3">
          <div class="card-body">
            <h6 class="card-title">Students</h6>
            <div class="row g-3">
              <?php foreach ($parents_data as $p): ?>
                <?php if (!empty($p['studentPhoto'])): ?>
                  <div class="col-6 text-center">
                    <div class="student-card">
                      <img src="<?= htmlspecialchars($p['studentPhoto']) ?>" alt="" class="student-photo">
                      <div class="small"><?= htmlspecialchars(trim($p['studentFirstName'].' '.$p['studentLastName'])) ?></div>
                    </div>
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- summary cards -->
        <div class="d-flex summary">
          <div class="summary-card parents text-center">
            <i class="bi bi-people-fill"></i>
            <div class="small mt-1">Parents</div>
            <div class="h5 mt-1"><?= $total_parents ?></div>
          </div>

          <div class="summary-card relations text-center ms-3">
            <i class="bi bi-link-45deg"></i>
            <div class="small mt-1">Relations</div>
            <div class="h5 mt-1"><?= $total_relations ?></div>
          </div>

          <div class="summary-card students text-center ms-3">
            <i class="bi bi-mortarboard-fill"></i>
            <div class="small mt-1">Students</div>
            <div class="h5 mt-1"><?= $total_students ?></div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Bootstrap JS (optional for interactive components) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
