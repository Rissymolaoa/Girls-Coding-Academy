<?php
// payment_failed.php
// Page for handling failed payment attempts in Girls Coding Academy.
// For users (students/parents): Displays failure message, allows retry.
// For admins: Can view recent failures and update statuses.
// Integrates with invoices and payments tables.

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
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

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'student'; // Default for non-admin

$message = '';
$error = '';

// Handle retry or admin actions
if ($_POST) {
    $invoice_id = (int)($_POST['invoice_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($role === 'admin' && $action === 'mark_overdue') {
        // Admin marks as overdue
        $update_sql = "UPDATE invoices SET status='overdue' WHERE invoice_id=?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("i", $invoice_id);
        if ($stmt->execute()) {
            $message = "Invoice marked as overdue.";
        } else {
            $error = "Failed to update status.";
        }
    } elseif ($action === 'retry') {
        // Redirect to payment gateway (placeholder: e.g., PayPal/Stripe)
        header("Location: payment_gateway.php?invoice_id=" . $invoice_id);
        exit();
    }
}

// Fetch recent failed payments (for admin) or user's pending invoices
$failed_payments = [];
if ($role === 'admin') {
    // Admin views all recent failed (last 7 days)
    $query = "
        SELECT i.invoice_id, i.invoice_number, i.amount, i.due_date, i.status,
               u.firstName, u.lastName, c.courseName, b.batch_code,
               p.payment_date as attempt_date
        FROM invoices i
        LEFT JOIN payments p ON i.invoice_id = p.invoice_id AND p.status = 'failed'
        JOIN course_enrollments ce ON i.enrollment_id = ce.enrollment_id
        JOIN students s ON ce.student_id = s.student_id
        JOIN users u ON s.user_id = u.user_id
        JOIN batches b ON ce.batch_id = b.batch_id
        JOIN courses c ON b.course_id = c.course_id
        WHERE (i.status = 'pending' OR p.status = 'failed') 
        AND (p.payment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) OR p.payment_date IS NULL)
        ORDER BY (p.payment_date OR i.created_at) DESC
        LIMIT 20
    ";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $failed_payments[] = $row;
    }
} else {
    // User views their own pending/failed
    $query = "
        SELECT i.invoice_id, i.invoice_number, i.amount, i.due_date, i.status
        FROM invoices i
        JOIN course_enrollments ce ON i.enrollment_id = ce.enrollment_id
        JOIN students s ON ce.student_id = s.student_id
        WHERE s.user_id = ? AND i.status IN ('pending', 'overdue')
        ORDER BY i.created_at DESC
        LIMIT 5
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $failed_payments[] = $row;
    }
}

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Payment Failed - Girls Coding Academy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
  :root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
    text-align: center;
  }

  .page-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    background: var(--danger-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
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

  .status-pending { background: #fff3cd; color: #856404; }
  .status-overdue { background: #f8d7da; color: #721c24; }
  .status-failed { background: #f5c2c7; color: #721c24; }

  .btn-retry {
    background: var(--primary-gradient);
    border: none;
    color: white;
    border-radius: 8px;
    padding: 0.5rem 1rem;
  }

  .admin-actions .btn {
    margin: 0 0.25rem;
  }
</style>
</head>
<body>
<?php include 'student_navigation.php'; ?>



<div class="content">
  <main class="main">
    <div class="page-header">
      <i class="bi bi-exclamation-triangle-fill" style="font-size: 4rem; color: #fee140; margin-bottom: 1rem;"></i>
      <h1>Payment Failed</h1>
      <p class="text-muted">We're sorry, your payment attempt did not go through. Please try again or contact support.</p>
      <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
    </div>

    <div class="table-section">
      <h2><?php echo $role === 'admin' ? 'Recent Failed Payments' : 'Your Pending Invoices'; ?></h2>
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Invoice #</th>
              <th>Amount</th>
              <?php if ($role === 'admin'): ?>
                <th>Student</th>
                <th>Course/Batch</th>
              <?php endif; ?>
              <th>Status</th>
              <th>Due Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($failed_payments as $payment): ?>
              <tr>
                <td><?= htmlspecialchars($payment['invoice_number'] ?? '#N/A') ?></td>
                <td>$<?= number_format($payment['amount'], 2) ?></td>
                <?php if ($role === 'admin'): ?>
                  <td><?= htmlspecialchars($payment['firstName'] . ' ' . $payment['lastName']) ?></td>
                  <td><?= htmlspecialchars($payment['courseName'] . ' (' . $payment['batch_code'] . ')') ?></td>
                <?php endif; ?>
                <td><span class="status-badge status-<?= $payment['status'] ?? 'pending' ?>"><?= ucfirst($payment['status'] ?? 'Pending') ?></span></td>
                <td><?= date('M j, Y', strtotime($payment['due_date'])) ?></td>
                <td>
                  <?php if ($role !== 'admin'): ?>
                    <form method="POST" style="display: inline;">
                      <input type="hidden" name="invoice_id" value="<?= $payment['invoice_id'] ?>">
                      <input type="hidden" name="action" value="retry">
                      <button type="submit" class="btn-retry">Retry Payment</button>
                    </form>
                  <?php else: ?>
                    <div class="admin-actions">
                      <form method="POST" style="display: inline;">
                        <input type="hidden" name="invoice_id" value="<?= $payment['invoice_id'] ?>">
                        <input type="hidden" name="action" value="mark_overdue">
                        <button type="submit" class="btn btn-sm btn-warning">Mark Overdue</button>
                      </form>
                      <a href="invoice_details.php?id=<?= $payment['invoice_id'] ?>" class="btn btn-sm btn-info">View Details</a>
                    </div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($failed_payments)): ?>
              <tr><td colspan="<?php echo $role === 'admin' ? 7 : 6; ?>" class="text-center text-muted">No failed or pending payments at this time.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      
      <?php if ($role !== 'admin'): ?>
        <div class="text-center mt-4">
          <p class="text-muted">Need help? <a href="contact_support.php">Contact Support</a> or try a different payment method.</p>
          <a href="student.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>