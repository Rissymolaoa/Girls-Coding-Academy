<?php
// finance_dashboard.php
// Always start the session at the very top
session_start();

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// Database connection
include("db.php");

// Include navigations
include("top_navigation.php");
include("admin_navigation.php");

// Fetch finance summaries
$total_revenue_stmt = $conn->prepare("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'");
$total_revenue_stmt->execute();
$total_revenue = $total_revenue_stmt->get_result()->fetch_assoc()['total'] ?? 0;

$pending_invoices_stmt = $conn->prepare("SELECT COUNT(*) as count FROM invoices WHERE status = 'pending'");
$pending_invoices_stmt->execute();
$pending_count = $pending_invoices_stmt->get_result()->fetch_assoc()['count'];

$overdue_invoices_stmt = $conn->prepare("SELECT COUNT(*) as count FROM invoices WHERE status = 'overdue'");
$overdue_invoices_stmt->execute();
$overdue_count = $overdue_invoices_stmt->get_result()->fetch_assoc()['count'];

$recent_payments_stmt = $conn->prepare("
    SELECT p.payment_id, p.amount, p.payment_method, p.payment_date, p.reference_number,
           u.username as payer, i.invoice_number
    FROM payments p
    JOIN invoices i ON p.invoice_id = i.invoice_id
    JOIN users u ON p.payer_user_id = u.user_id
    ORDER BY p.payment_date DESC LIMIT 5
");
$recent_payments_stmt->execute();
$recent_payments = $recent_payments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pending_payments_stmt = $conn->prepare("
    SELECT i.invoice_id, i.invoice_number, i.amount, i.due_date, i.status,
           s.student_id, u.firstName, u.lastName
    FROM invoices i
    JOIN course_enrollments ce ON i.enrollment_id = ce.enrollment_id
    JOIN students s ON ce.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    WHERE i.status IN ('pending', 'overdue')
    ORDER BY i.due_date ASC LIMIT 10
");
$pending_payments_stmt->execute();
$pending_payments = $pending_payments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Finance Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
  <style>
    body { font-family: Arial, sans-serif; background: #f8f9fa; margin:0; padding: 0; display: flex; min-height: 100vh; }
    main.main {
      padding: 20px;
      flex: 1;
      min-height: 100vh;
      padding-top: 80px; /* Account for fixed navbar height */
    }
    .stat-card {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      transition: transform 0.3s ease;
    }
    .stat-card:hover {
      transform: translateY(-5px);
    }
    .stat-icon {
      font-size: 2.5rem;
      margin-bottom: 10px;
    }
    .recent-table, .pending-table {
      background: #fff;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      margin-bottom: 20px;
    }
    .table th {
      background: #f8f9fa;
      border-top: none;
    }
    @media (min-width: 992px) {
        .main {
            margin-left: 280px !important;
        }
    }
  </style>
</head>
<body>
<main class="main">
  <div class="row">
    <div class="col-md-3">
      <div class="stat-card text-center">
        <i class="bi bi-currency-dollar stat-icon"></i>
        <h3>Total Revenue</h3>
        <p class="lead">$<?= number_format($total_revenue, 2) ?></p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card text-center">
        <i class="bi bi-clock-history stat-icon"></i>
        <h3>Pending Invoices</h3>
        <p class="lead"><?= $pending_count ?></p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card text-center">
        <i class="bi bi-exclamation-triangle stat-icon"></i>
        <h3>Overdue</h3>
        <p class="lead"><?= $overdue_count ?></p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card text-center">
        <i class="bi bi-people stat-icon"></i>
        <h3>Total Payers</h3>
        <p class="lead">45</p> <!-- Can be queried from unique payers -->
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6">
      <h4>Recent Payments</h4>
      <div class="recent-table">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Amount</th>
              <th>Method</th>
              <th>Date</th>
              <th>Payer</th>
              <th>Invoice</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($recent_payments as $payment): ?>
            <tr>
              <td>$<?= number_format($payment['amount'], 2) ?></td>
              <td><?= ucfirst($payment['payment_method']) ?></td>
              <td><?= date('M j, Y', strtotime($payment['payment_date'])) ?></td>
              <td><?= htmlspecialchars($payment['payer']) ?></td>
              <td><?= htmlspecialchars($payment['invoice_number']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="col-md-6">
      <h4>Pending Payments</h4>
      <div class="pending-table">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Student</th>
              <th>Amount</th>
              <th>Due Date</th>
              <th>Status</th>
              <th>Invoice</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($pending_payments as $invoice): ?>
            <tr class="<?= $invoice['status'] === 'overdue' ? 'table-danger' : '' ?>">
              <td><?= htmlspecialchars($invoice['firstName'] . ' ' . $invoice['lastName']) ?></td>
              <td>$<?= number_format($invoice['amount'], 2) ?></td>
              <td><?= date('M j, Y', strtotime($invoice['due_date'])) ?></td>
              <td><span class="badge bg-<?= $invoice['status'] === 'pending' ? 'warning' : 'danger' ?>"><?= ucfirst($invoice['status']) ?></span></td>
              <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>