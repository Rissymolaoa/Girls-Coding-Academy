<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}
include("db.php");

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

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
";

if($search){
    $search_param = "%$search%";
    $stmt = $conn->prepare($parents_sql . " AND (u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ?) ORDER BY u.created_at DESC");
    $stmt->bind_param("sss",$search_param,$search_param,$search_param);
    $stmt->execute();
    $parents = $stmt->get_result();
} else {
    $parents = $conn->query($parents_sql . " ORDER BY u.created_at DESC");
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>All Parents</title>
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

  .search-form {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
  }

  .search-form .form-control {
    flex: 1;
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

  .student-photo {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 50%;
    border: 2px solid var(--primary-gradient);
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
    .search-form {
      flex-direction: column;
    }
  }
</style>
</head>
<body>
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="content">
  <main class="main">
    <div class="section-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Girls Coding Academy - All Parents</h2>
        <form method="get" class="search-form">
          <input type="text" name="search" class="form-control" placeholder="Search parents..." value="<?= htmlspecialchars($search) ?>">
          <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
        </form>
      </div>

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
              <th>Photo</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($p = $parents->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($p['firstName']) ?></td>
              <td><?= htmlspecialchars($p['lastName']) ?></td>
              <td><?= htmlspecialchars($p['gender']) ?></td>
              <td><?= htmlspecialchars($p['phone']) ?></td>
              <td><?= htmlspecialchars($p['email']) ?></td>
              <td><?= htmlspecialchars($p['studentFirstName'] . ' ' . $p['studentLastName']) ?></td>
              <td>
                <?php if (!empty($p['studentPhoto'])): ?>
                  <img src="<?= htmlspecialchars($p['studentPhoto']) ?>" class="student-photo">
                <?php else: ?>
                  <span class="text-muted">No Photo</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>