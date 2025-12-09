<?php
// view_attendance.php
// Admin page to view attendance records: Filter by date, batch, status; display in table with summaries.

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

// Handle filters
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_batch = (int)($_GET['batch'] ?? 0);
$filter_status = $_GET['status'] ?? '';

// Validate date inputs (allow only YYYY-MM-DD). If invalid, clear them.
if (!empty($filter_date_from)) {
  $d = DateTime::createFromFormat('Y-m-d', $filter_date_from);
  if (!$d || $d->format('Y-m-d') !== $filter_date_from) {
    $filter_date_from = '';
  }
}
if (!empty($filter_date_to)) {
  $d = DateTime::createFromFormat('Y-m-d', $filter_date_to);
  if (!$d || $d->format('Y-m-d') !== $filter_date_to) {
    $filter_date_to = '';
  }
}

$where_conditions = [];
$params = [];
$types = '';

if (!empty($filter_date_from)) {
    $where_conditions[] = "a.session_id >= ?";
    $params[] = $filter_date_from;
    $types .= 's';
}
if (!empty($filter_date_to)) {
    $where_conditions[] = "a.session_id <= ?";
    $params[] = $filter_date_to;
    $types .= 's';
}
if ($filter_batch > 0) {
    $where_conditions[] = "b.batch_id = ?";
    $params[] = $filter_batch;
    $types .= 'i';
}
if (!empty($filter_status)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Fetch attendance records
$sql = "
    SELECT a.*, 
           u.firstName as student_first, u.lastName as student_last,
           b.batch_code, c.courseName,
           ut.firstName as teacher_first, ut.lastName as teacher_last
    FROM attendance a
    JOIN students s ON a.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN batches b ON a.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    LEFT JOIN teachers t ON a.marked_by = t.teacher_id
    LEFT JOIN users ut ON t.user_id = ut.user_id
    $where_clause
    ORDER BY a.session_id DESC, a.marked_at DESC
    LIMIT 100
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
  die('Prepare failed: ' . htmlspecialchars($conn->error));
}
if (!empty($types) && count($params) > 0) {
  // mysqli::bind_param requires references. Build an array of references.
  $bind_names = array_merge([$types], $params);
  $refs = [];
  foreach ($bind_names as $key => $value) {
    $refs[$key] = &$bind_names[$key];
  }
  call_user_func_array([$stmt, 'bind_param'], $refs);
}
if (!$stmt->execute()) {
  die('Execute failed: ' . htmlspecialchars($stmt->error));
}
$result = $stmt->get_result();
$attendance_records = [];
while ($row = $result->fetch_assoc()) {
    $attendance_records[] = $row;
}
$stmt->close();

// Fetch batches for filter dropdown
$batches_query = $conn->query("SELECT batch_id, batch_code FROM batches WHERE status='active' ORDER BY batch_code");
$batches = [];
while ($batch = $batches_query->fetch_assoc()) {
    $batches[] = $batch;
}

// Fetch status summary for dashboard card (fixed: use prepared statement)
$summary_sql = "
    SELECT a.status, COUNT(*) as cnt 
    FROM attendance a
    JOIN batches b ON a.batch_id = b.batch_id
    $where_clause 
    GROUP BY a.status
";

$stmt_summary = $conn->prepare($summary_sql);
if ($stmt_summary === false) {
  die('Prepare failed: ' . htmlspecialchars($conn->error));
}
if (!empty($types) && count($params) > 0) {
  $bind_names = array_merge([$types], $params);
  $refs = [];
  foreach ($bind_names as $key => $value) {
    $refs[$key] = &$bind_names[$key];
  }
  call_user_func_array([$stmt_summary, 'bind_param'], $refs);
}
if (!$stmt_summary->execute()) {
  die('Execute failed: ' . htmlspecialchars($stmt_summary->error));
}
$summary_result = $stmt_summary->get_result();
$summary = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Sick' => 0];
while ($sum = $summary_result->fetch_assoc()) {
    if (array_key_exists($sum['status'], $summary)) {
        $summary[$sum['status']] = $sum['cnt'];
    }
}
$stmt_summary->close();
$total_attendance = array_sum($summary);
$avg_attendance = $total_attendance > 0 ? round(($summary['Present'] / $total_attendance) * 100, 1) : 0;

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>View Attendance - Admin Portal</title>
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

  .summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
  }

  .summary-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: var(--shadow-md);
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.2);
  }

  .summary-card.success { border-left: 4px solid #28a745; }
  .summary-card.warning { border-left: 4px solid #ffc107; }
  .summary-card.danger { border-left: 4px solid #dc3545; }

  .summary-card h6 { color: #6b7280; font-weight: 600; margin-bottom: 0.5rem; }
  .summary-card p { font-size: 2rem; font-weight: 700; margin: 0; }

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

  .status-Present { background: #d4edda; color: #155724; }
  .status-Absent { background: #f8d7da; color: #721c24; }
  .status-Late { background: #fff3cd; color: #856404; }
  .status-Sick { background: #d1ecf1; color: #0c5460; }
</style>
</head>
<body>
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="content">
  <main class="main">
    <div class="page-header">
      <h1>View Attendance</h1>
      <p class="text-muted">Monitor student attendance across batches and sessions.</p>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards">
      <div class="summary-card success">
        <h6>Present</h6>
        <p><?= $summary['Present'] ?></p>
      </div>
      <div class="summary-card warning">
        <h6>Late</h6>
        <p><?= $summary['Late'] ?></p>
      </div>
      <div class="summary-card danger">
        <h6>Absent</h6>
        <p><?= $summary['Absent'] ?></p>
      </div>
      <div class="summary-card">
        <h6>Sick</h6>
        <p><?= $summary['Sick'] ?></p>
      </div>
      <div class="summary-card">
        <h6>Total Sessions</h6>
        <p><?= $total_attendance ?></p>
      </div>
      <div class="summary-card success">
        <h6>Avg Attendance</h6>
        <p><?= $avg_attendance ?>%</p>
      </div>
    </div>

    <div class="filters">
      <form method="GET" class="row g-3">
        <div class="col-md-2">
          <label class="form-label">From Date</label>
          <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filter_date_from) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">To Date</label>
          <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filter_date_to) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Batch</label>
          <select name="batch" class="form-select">
            <option value="">All Batches</option>
            <?php foreach ($batches as $batch): ?>
              <option value="<?= $batch['batch_id'] ?>" <?= $filter_batch == $batch['batch_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($batch['batch_code']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="">All Status</option>
            <option value="Present" <?= $filter_status === 'Present' ? 'selected' : '' ?>>Present</option>
            <option value="Absent" <?= $filter_status === 'Absent' ? 'selected' : '' ?>>Absent</option>
            <option value="Late" <?= $filter_status === 'Late' ? 'selected' : '' ?>>Late</option>
            <option value="Sick" <?= $filter_status === 'Sick' ? 'selected' : '' ?>>Sick</option>
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
      </form>
    </div>

    <div class="table-section">
      <h2>Attendance Records (<?= count($attendance_records) ?>)</h2>
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Date</th>
              <th>Student</th>
              <th>Batch / Course</th>
              <th>Status</th>
              <th>Marked By</th>
              <th>Marked At</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($attendance_records as $record): ?>
              <tr>
                <td><?= date('M j, Y', strtotime($record['session_id'])) ?></td>
                <td><?= htmlspecialchars($record['student_first'] . ' ' . $record['student_last']) ?></td>
                <td><?= htmlspecialchars($record['batch_code'] . ' / ' . $record['courseName']) ?></td>
                <td><span class="status-badge status-<?= $record['status'] ?>"><?= $record['status'] ?></span></td>
                <td><?= htmlspecialchars(($record['teacher_first'] ?? 'N/A') . ' ' . ($record['teacher_last'] ?? '')) ?></td>
                <td><?= date('M j, Y H:i', strtotime($record['marked_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($attendance_records)): ?>
              <tr><td colspan="6" class="text-center text-muted">No attendance records found. <?= !empty($filter_date_from) || !empty($filter_date_to) || $filter_batch > 0 || !empty($filter_status) ? 'Try adjusting filters.' : 'Mark some attendance to get started!' ?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if (!empty($filter_date_from) || !empty($filter_date_to) || $filter_batch > 0 || !empty($filter_status)): ?>
        <div class="text-end mt-3">
          <a href="view_attendance.php" class="btn btn-secondary">Clear Filters</a>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>