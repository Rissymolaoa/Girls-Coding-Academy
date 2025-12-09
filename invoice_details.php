<?php
// invoice_details.php
// Admin page to view detailed information for a specific invoice.
// Includes invoice details, student/course info, payments, and actions.

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

$invoice_id = (int)($_GET['id'] ?? 0);
$invoice = null;
$payments = [];
$enrollment = null;

// Fetch invoice details with joins
$sql = "
    SELECT i.*, 
           ce.enrollment_id,
           u.firstName, u.lastName, u.email, u.phone,
           b.batch_code, b.start_date, b.end_date,
           c.courseName, c.title as course_title
    FROM invoices i
    JOIN course_enrollments ce ON i.enrollment_id = ce.enrollment_id
    JOIN students s ON ce.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    WHERE i.invoice_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$result = $stmt->get_result();
$invoice = $result->fetch_assoc();
$stmt->close();

if (!$invoice) {
    die("Invoice not found.");
}

// Fetch related payments
$payment_sql = "SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC";
$stmt = $conn->prepare($payment_sql);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$payment_result = $stmt->get_result();
while ($pay = $payment_result->fetch_assoc()) {
    $payments[] = $pay;
}
$stmt->close();

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Invoice Details #<?= $invoice['invoice_number'] ?> - Admin Portal</title>
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

  .status-pending { background: #fff3cd; color: #856404; }
  .status-paid { background: #d4edda; color: #155724; }
  .status-overdue { background: #f8d7da; color: #721c24; }
  .status-cancelled { background: #e2e3e5; color: #495057; }

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

  .invoice-amount {
    font-size: 2rem;
    font-weight: 700;
    color: #28a745;
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
          <h1>Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?></h1>
          <p class="text-muted">For: <?= htmlspecialchars($invoice['course_title'] . ' (' . $invoice['courseName'] . ')') ?></p>
        </div>
        <a href="manage_invoices.php" class="btn btn-secondary">
          <i class="bi bi-arrow-left"></i> Back to Invoices
        </a>
      </div>
    </div>

    <!-- Invoice Overview -->
    <div class="detail-card">
      <h3>Invoice Overview</h3>
      <div class="row">
        <div class="col-md-6">
          <h5>Student Information</h5>
          <p><strong>Name:</strong> <?= htmlspecialchars($invoice['firstName'] . ' ' . $invoice['lastName']) ?></p>
          <p><strong>Email:</strong> <?= htmlspecialchars($invoice['email']) ?></p>
          <p><strong>Phone:</strong> <?= htmlspecialchars($invoice['phone'] ?? 'N/A') ?></p>
        </div>
        <div class="col-md-6">
          <h5>Course & Batch</h5>
          <p><strong>Course:</strong> <?= htmlspecialchars($invoice['course_title'] . ' (' . $invoice['courseName'] . ')') ?></p>
          <p><strong>Batch:</strong> <?= htmlspecialchars($invoice['batch_code']) ?></p>
          <p><strong>Batch Dates:</strong> <?= date('M j, Y', strtotime($invoice['start_date'])) ?> - <?= date('M j, Y', strtotime($invoice['end_date'])) ?></p>
        </div>
      </div>
      <hr>
      <div class="row">
        <div class="col-md-3">
          <p><strong>Invoice Date:</strong> <?= date('M j, Y H:i', strtotime($invoice['created_at'])) ?></p>
        </div>
        <div class="col-md-3">
          <p><strong>Due Date:</strong> <?= date('M j, Y', strtotime($invoice['due_date'])) ?></p>
        </div>
        <div class="col-md-3">
          <p><strong>Status:</strong> <span class="status-badge status-<?= $invoice['status'] ?>"><?= ucfirst($invoice['status']) ?></span></p>
        </div>
        <div class="col-md-3">
          <p><strong>Total Amount:</strong> <span class="invoice-amount">$<?= number_format($invoice['amount'], 2) ?></span></p>
        </div>
      </div>
      <div class="mt-3">
        <?php if ($invoice['status'] === 'pending'): ?>
          <form method="POST" style="display: inline;">
            <input type="hidden" name="action" value="mark_paid">
            <input type="hidden" name="invoice_id" value="<?= $invoice['invoice_id'] ?>">
            <button type="submit" class="btn btn-success btn-action">Mark as Paid</button>
          </form>
          <a href="send_invoice_reminder.php?id=<?= $invoice['invoice_id'] ?>" class="btn btn-warning btn-action">Send Reminder</a>
        <?php elseif ($invoice['status'] === 'overdue'): ?>
          <a href="send_invoice_reminder.php?id=<?= $invoice['invoice_id'] ?>" class="btn btn-warning btn-action">Send Overdue Notice</a>
        <?php endif; ?>
        <a href="print_invoice.php?id=<?= $invoice['invoice_id'] ?>" class="btn btn-info btn-action" target="_blank">Print PDF</a>
      </div>
    </div>

    <!-- Payments -->
    <div class="detail-card">
      <h3>Payment History (<?= count($payments) ?>)</h3>
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
                  <td><span class="badge bg-<?= $pay['status'] === 'completed' ? 'success' : ($pay['status'] === 'failed' ? 'danger' : 'warning') ?>"><?= ucfirst($pay['status']) ?></span></td>
                  <td><?= date('M j, Y H:i', strtotime($pay['payment_date'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-muted">No payments recorded for this invoice.</p>
      <?php endif; ?>
    </div>

    <!-- Enrollment Link -->
    <div class="detail-card">
      <h3>Related Enrollment</h3>
      <p><a href="enrollment_details.php?id=<?= $invoice['enrollment_id'] ?>" class="btn btn-primary">View Enrollment Details</a></p>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>