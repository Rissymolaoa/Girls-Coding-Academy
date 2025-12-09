<?php
// manage_enrollments.php
// Admin page to manage course enrollments: View, filter, approve/drop, edit status.
// Integrates with course_enrollments, students, users, batches, courses tables.

session_start();

// Check if user is logged in and admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// DB connection
$host = "localhost:3307";
$user = "root";
$pass = "";
$db = "girlscodingacademydb";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Handle actions (update status, drop enrollment)
$message = '';
if ($_POST) {
    $enrollment_id = (int)$_POST['enrollment_id'];
    $new_status = trim($_POST['status']);
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status' && in_array($new_status, ['active', 'completed', 'dropped'])) {
        $sql = "UPDATE course_enrollments SET status=? WHERE enrollment_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_status, $enrollment_id);
        if ($stmt->execute()) {
            $message = "Enrollment status updated successfully!";
        } else {
            $message = "Error updating status: " . $conn->error;
        }
        $stmt->close();
    } elseif ($action === 'drop') {
        $sql = "UPDATE course_enrollments SET status='dropped' WHERE enrollment_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $enrollment_id);
        if ($stmt->execute()) {
            $message = "Enrollment dropped successfully!";
        } else {
            $message = "Error dropping enrollment: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch enrollments with joins for display
$filter_status = $_GET['status'] ?? '';
$filter_batch = (int)($_GET['batch'] ?? 0);

$where_conditions = [];
$params = [];
$types = '';

if (!empty($filter_status)) {
    $where_conditions[] = "ce.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
if ($filter_batch > 0) {
    $where_conditions[] = "ce.batch_id = ?";
    $params[] = $filter_batch;
    $types .= 'i';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$sql = "
    SELECT ce.enrollment_id, ce.student_id, ce.batch_id, ce.enrolled_at, ce.status,
           u.firstName, u.lastName, u.email,
           b.batch_code, c.courseName, c.title as course_title
    FROM course_enrollments ce
    JOIN students s ON ce.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    $where_clause
    ORDER BY ce.enrolled_at DESC
    LIMIT 50
";

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$enrollments = [];
while ($row = $result->fetch_assoc()) {
    $enrollments[] = $row;
}
$stmt->close();

// Fetch batches for filter dropdown
$batches_query = $conn->query("SELECT batch_id, batch_code FROM batches WHERE status='active' ORDER BY batch_code");
$batches = [];
while ($batch = $batches_query->fetch_assoc()) {
    $batches[] = $batch;
}

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Manage Enrollments - Admin Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
  :root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --warning-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
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
  }

  .main {
    padding: 2rem;
  }

  .page-header {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-lg);
    border: 1px solid rgba(255, 255, 255, 0.2);
  }

  .page-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
  }

  .filters {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-md);
    border: 1px solid rgba(255, 255, 255, 0.2);
  }

  .table-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid rgba(255, 255, 255, 0.2);
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

  .status-badge {
    font-size: 0.8rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
  }

  .status-active { background: #d1edff; color: #0c5460; }
  .status-completed { background: #d4edda; color: #155724; }
  .status-dropped { background: #f8d7da; color: #721c24; }

  .btn-action {
    margin: 0 0.25rem;
  }

  .modal-content {
    border-radius: 16px;
    border: none;
    box-shadow: var(--shadow-lg);
  }

  .modal-header {
    background: var(--primary-gradient);
    color: white;
    border-radius: 16px 16px 0 0;
  }
</style>
</head>
<body>
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="content">
  <main class="main">
    <div class="page-header">
      <h1>Manage Enrollments</h1>
      <p class="text-muted">View and manage student enrollments across batches and courses.</p>
      <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>
    </div>

    <div class="filters">
      <form method="GET" class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Filter by Status</label>
          <select name="status" class="form-select">
            <option value="">All</option>
            <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="dropped" <?= $filter_status === 'dropped' ? 'selected' : '' ?>>Dropped</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Filter by Batch</label>
          <select name="batch" class="form-select">
            <option value="">All Batches</option>
            <?php foreach ($batches as $batch): ?>
              <option value="<?= $batch['batch_id'] ?>" <?= $filter_batch === $batch['batch_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($batch['batch_code']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6 d-flex align-items-end">
          <button type="submit" class="btn btn-primary me-2">Filter</button>
          <a href="manage_enrollments.php" class="btn btn-secondary">Clear Filters</a>
        </div>
      </form>
    </div>

    <div class="table-section">
      <h2>Enrollments (<?= count($enrollments) ?>)</h2>
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>ID</th>
              <th>Student</th>
              <th>Email</th>
              <th>Course</th>
              <th>Batch</th>
              <th>Enrolled At</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($enrollments as $enroll): ?>
              <tr>
                <td><?= $enroll['enrollment_id'] ?></td>
                <td><?= htmlspecialchars($enroll['firstName'] . ' ' . $enroll['lastName']) ?></td>
                <td><?= htmlspecialchars($enroll['email']) ?></td>
                <td><?= htmlspecialchars($enroll['course_title'] . ' (' . $enroll['courseName'] . ')') ?></td>
                <td><span class="badge bg-info"><?= htmlspecialchars($enroll['batch_code']) ?></span></td>
                <td><?= date('M j, Y', strtotime($enroll['enrolled_at'])) ?></td>
                <td><span class="status-badge status-<?= $enroll['status'] ?>"><?= ucfirst($enroll['status']) ?></span></td>
                <td>
                  <button class="btn btn-sm btn-outline-primary btn-action" data-bs-toggle="modal" data-bs-target="#editModal" onclick="editEnrollment(<?= $enroll['enrollment_id'] ?>, '<?= addslashes($enroll['status']) ?>')">
                    <i class="bi bi-pencil"></i> Edit Status
                  </button>
                  <?php if ($enroll['status'] !== 'dropped'): ?>
                    <a href="?drop=<?= $enroll['enrollment_id'] ?>" class="btn btn-sm btn-outline-danger btn-action" onclick="return confirm('Drop this enrollment?')">
                      <i class="bi bi-trash"></i> Drop
                    </a>
                  <?php endif; ?>
                  <a href="enrollment_details.php?id=<?= $enroll['enrollment_id'] ?>" class="btn btn-sm btn-outline-info btn-action">
                    <i class="bi bi-eye"></i> View
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($enrollments)): ?>
              <tr><td colspan="8" class="text-center text-muted">No enrollments found. <?= !empty($filter_status) || $filter_batch > 0 ? 'Try adjusting filters.' : 'Enroll some students to get started!' ?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- Edit Status Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Enrollment Status</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="enrollment_id" id="edit_id">
        <input type="hidden" name="action" value="update_status">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">New Status</label>
            <select name="status" id="edit_status" class="form-select">
              <option value="active">Active</option>
              <option value="completed">Completed</option>
              <option value="dropped">Dropped</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function editEnrollment(id, status) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_status').value = status;
  }
</script>
</body>
</html>