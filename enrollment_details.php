<?php
// enrollment_details.php
// Admin page to view detailed information for a specific enrollment.
// Includes student details, course/batch info, status, invoices, payments, and actions.

session_start();

// Check if user is logged in and admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// DB connection
$host = "localhost";
$user = "root";
$pass = "";
$db = "girlscodingacademydb";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$enrollment_id = (int)($_GET['id'] ?? 0);
$enrollment = null;
$invoices = [];
$payments = [];
$grades = [];

// Fetch enrollment details
$sql = "
    SELECT ce.*, 
           u.firstName, u.lastName, u.email, u.phone,
           b.batch_code, b.start_date, b.end_date, b.status as batch_status,
           c.courseName, c.title as course_title, c.price
    FROM course_enrollments ce
    JOIN students s ON ce.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    WHERE ce.enrollment_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $enrollment_id);
$stmt->execute();
$result = $stmt->get_result();
$enrollment = $result->fetch_assoc();
$stmt->close();

if (!$enrollment) {
    die("Enrollment not found.");
}

// Fetch related invoices
$invoice_sql = "SELECT * FROM invoices WHERE enrollment_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($invoice_sql);
$stmt->bind_param("i", $enrollment_id);
$stmt->execute();
$invoice_result = $stmt->get_result();
while ($inv = $invoice_result->fetch_assoc()) {
    $invoices[] = $inv;
}
$stmt->close();

// Fetch related payments
$payment_sql = "SELECT * FROM payments WHERE invoice_id IN (SELECT invoice_id FROM invoices WHERE enrollment_id = ?) ORDER BY payment_date DESC";
$stmt = $conn->prepare($payment_sql);
$stmt->bind_param("i", $enrollment_id);
$stmt->execute();
$payment_result = $stmt->get_result();
while ($pay = $payment_result->fetch_assoc()) {
    $payments[] = $pay;
}
$stmt->close();

// Fetch related grades (if any)
$grade_sql = "
    SELECT ig.*, c.courseName
    FROM internal_grades ig
    JOIN course_enrollments ce ON ig.student_id = ce.student_id AND ig.batch_id = ce.batch_id
    JOIN courses c ON ce.batch_id = (SELECT batch_id FROM batches WHERE course_id = c.course_id LIMIT 1)
    WHERE ce.enrollment_id = ?
";
$stmt = $conn->prepare($grade_sql);
$stmt->bind_param("i", $enrollment_id);
$stmt->execute();
$grade_result = $stmt->get_result();
while ($grade = $grade_result->fetch_assoc()) {
    $grades[] = $grade;
}
$stmt->close();

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Enrollment Details #<?= $enrollment['enrollment_id'] ?> - Admin Portal</title>
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

  .detail-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid rgba(255, 255, 255, 0.2);
    margin-bottom: 2rem;
  }

  .status-badge {
    font-size: 0.9rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
  }

  .status-active { background: #d1edff; color: #0c5460; }
  .status-completed { background: #d4edda; color: #155724; }
  .status-dropped { background: #f8d7da; color: #721c24; }

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

  .btn-action {
    margin: 0 0.25rem;
  }
</style>
</head>
<body>
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="content">
  <main class="main">
    <div class="page-header">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h1>Enrollment Details #<?= $enrollment['enrollment_id'] ?></h1>
          <p class="text-muted">Student: <?= htmlspecialchars($enrollment['firstName'] . ' ' . $enrollment['lastName']) ?></p>
        </div>
        <a href="manage_enrollments.php" class="btn btn-secondary">
          <i class="bi bi-arrow-left"></i> Back to Enrollments
        </a>
      </div>
    </div>

    <!-- Enrollment Overview -->
    <div class="detail-card">
      <h3>Overview</h3>
      <div class="row">
        <div class="col-md-6">
          <h5>Student Information</h5>
          <p><strong>Name:</strong> <?= htmlspecialchars($enrollment['firstName'] . ' ' . $enrollment['lastName']) ?></p>
          <p><strong>Email:</strong> <?= htmlspecialchars($enrollment['email']) ?></p>
          <p><strong>Phone:</strong> <?= htmlspecialchars($enrollment['phone'] ?? 'N/A') ?></p>
        </div>
        <div class="col-md-6">
          <h5>Course & Batch</h5>
          <p><strong>Course:</strong> <?= htmlspecialchars($enrollment['course_title'] . ' (' . $enrollment['courseName'] . ')') ?></p>
          <p><strong>Batch:</strong> <?= htmlspecialchars($enrollment['batch_code']) ?></p>
          <p><strong>Batch Dates:</strong> <?= date('M j, Y', strtotime($enrollment['start_date'])) ?> - <?= date('M j, Y', strtotime($enrollment['end_date'])) ?></p>
          <p><strong>Batch Status:</strong> <span class="badge bg-info"><?= ucfirst($enrollment['batch_status']) ?></span></p>
        </div>
      </div>
      <hr>
      <div class="row">
        <div class="col-md-4">
          <p><strong>Enrollment Date:</strong> <?= date('M j, Y H:i', strtotime($enrollment['enrolled_at'])) ?></p>
        </div>
        <div class="col-md-4">
          <p><strong>Status:</strong> <span class="status-badge status-<?= $enrollment['status'] ?>"><?= ucfirst($enrollment['status']) ?></span></p>
        </div>
        <div class="col-md-4">
          <p><strong>Course Price:</strong> $<?= number_format($enrollment['price'], 2) ?></p>
        </div>
      </div>
      <div class="mt-3">
        <a href="?edit_status=<?= $enrollment['enrollment_id'] ?>" class="btn btn-outline-primary btn-action">Edit Status</a>
        <?php if ($enrollment['status'] !== 'dropped'): ?>
          <a href="manage_enrollments.php?drop=<?= $enrollment['enrollment_id'] ?>" class="btn btn-outline-danger btn-action" onclick="return confirm('Drop this enrollment?')">Drop Enrollment</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Invoices -->
    <div class="detail-card">
      <h3>Invoices (<?= count($invoices) ?>)</h3>
      <?php if (!empty($invoices)): ?>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Invoice #</th>
                <th>Amount</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($invoices as $inv): ?>
                <tr>
                  <td><a href="invoice_details.php?id=<?= $inv['invoice_id'] ?>"><?= htmlspecialchars($inv['invoice_number']) ?></a></td>
                  <td>$<?= number_format($inv['amount'], 2) ?></td>
                  <td><?= date('M j, Y', strtotime($inv['due_date'])) ?></td>
                  <td><span class="badge bg-<?= $inv['status'] === 'paid' ? 'success' : ($inv['status'] === 'overdue' ? 'danger' : 'warning') ?>"><?= ucfirst($inv['status']) ?></span></td>
                  <td><?= date('M j, Y H:i', strtotime($inv['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-muted">No invoices associated with this enrollment.</p>
      <?php endif; ?>
    </div>

    <!-- Payments -->
    <div class="detail-card">
      <h3>Payments (<?= count($payments) ?>)</h3>
      <?php if (!empty($payments)): ?>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Payment ID</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($payments as $pay): ?>
                <tr>
                  <td><?= $pay['payment_id'] ?></td>
                  <td>$<?= number_format($pay['amount'], 2) ?></td>
                  <td><?= htmlspecialchars($pay['payment_method'] ?? 'N/A') ?></td>
                  <td><span class="badge bg-<?= $pay['status'] === 'completed' ? 'success' : 'danger' ?>"><?= ucfirst($pay['status']) ?></span></td>
                  <td><?= date('M j, Y H:i', strtotime($pay['payment_date'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-muted">No payments recorded for this enrollment.</p>
      <?php endif; ?>
    </div>

    <!-- Grades -->
    <div class="detail-card">
      <h3>Grades & Progress (<?= count($grades) ?>)</h3>
      <?php if (!empty($grades)): ?>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Test 1</th>
                <th>Test 2</th>
                <th>Test 3</th>
                <th>Test 4</th>
                <th>Test 5</th>
                <th>End Exam</th>
                <th>Average</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($grades as $grade): ?>
                <tr>
                  <td><?= $grade['test_1'] ?? 'N/A' ?></td>
                  <td><?= $grade['test_2'] ?? 'N/A' ?></td>
                  <td><?= $grade['test_3'] ?? 'N/A' ?></td>
                  <td><?= $grade['test_4'] ?? 'N/A' ?></td>
                  <td><?= $grade['test_5'] ?? 'N/A' ?></td>
                  <td><?= $grade['end_examination'] ?? 'N/A' ?></td>
                  <td><?= isset($grade['test_1']) && $grade['test_1'] > 0 ? round(($grade['test_1'] + ($grade['test_2'] ?? 0) + ($grade['test_3'] ?? 0) + ($grade['test_4'] ?? 0) + ($grade['test_5'] ?? 0) + ($grade['end_examination'] ?? 0)) / 6, 1) : 'N/A' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-muted">No grades recorded yet.</p>
      <?php endif; ?>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>