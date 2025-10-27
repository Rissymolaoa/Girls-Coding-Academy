<?php
session_start();

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// Database connection
include("db.php");
include("top_navigation.php");
include("admin_navigation.php");

// Handle notification sending
if (isset($_POST['send_notification'])) {
    $student_id = intval($_POST['student_id']);
    $course_name = $_POST['course_name'];
    $invoice_number = $_POST['invoice_number'];

    $title = "Outstanding Balance Alert";
    $description = "You have an outstanding balance for $course_name (Invoice: $invoice_number). Please make your payment as soon as possible.";

    $notify_stmt = $conn->prepare("
        INSERT INTO notifications (student_id, type, title, description, date, is_read) 
        VALUES (?, 'Payment', ?, ?, CURDATE(), 0)
    ");
    $notify_stmt->bind_param("iss", $student_id, $title, $description);
    $notify_stmt->execute();
    $notify_stmt->close();

    $success_message = "Notification sent to student successfully!";
}

// Fetch finance summaries
$total_revenue_stmt = $conn->prepare("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'");
$total_revenue_stmt->execute();
$total_revenue = $total_revenue_stmt->get_result()->fetch_assoc()['total'] ?? 0;

$pending_invoices_stmt = $conn->prepare("SELECT SUM(amount) as total FROM invoices WHERE status = 'pending'");
$pending_invoices_stmt->execute();
$pending_amount = $pending_invoices_stmt->get_result()->fetch_assoc()['total'] ?? 0;

$pending_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM invoices WHERE status = 'pending'");
$pending_count_stmt->execute();
$pending_count = $pending_count_stmt->get_result()->fetch_assoc()['count'];

$overdue_invoices_stmt = $conn->prepare("SELECT SUM(amount) as total FROM invoices WHERE status = 'overdue'");
$overdue_invoices_stmt->execute();
$overdue_amount = $overdue_invoices_stmt->get_result()->fetch_assoc()['total'] ?? 0;

$overdue_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM invoices WHERE status = 'overdue'");
$overdue_count_stmt->execute();
$overdue_count = $overdue_count_stmt->get_result()->fetch_assoc()['count'];

// Total payers count
$total_payers_stmt = $conn->prepare("SELECT COUNT(DISTINCT payer_user_id) as count FROM payments");
$total_payers_stmt->execute();
$total_payers = $total_payers_stmt->get_result()->fetch_assoc()['count'];

// Monthly revenue data for chart (last 12 months)
$monthly_revenue = [];
$currentDate = new DateTime();
for ($i = 11; $i >= 0; $i--) {
    $date = clone $currentDate;
    $date->modify("-$i months");
    $monthKey = $date->format('Y-m');
    $monthly_revenue[$monthKey] = 0;
}

$revenue_chart_stmt = $conn->prepare("
    SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total
    FROM payments
    WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    AND status = 'completed'
    GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
    ORDER BY month ASC
");
$revenue_chart_stmt->execute();
$revenue_data = $revenue_chart_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($revenue_data as $row) {
    if (isset($monthly_revenue[$row['month']])) {
        $monthly_revenue[$row['month']] = floatval($row['total']);
    }
}

// Payment method breakdown
$payment_methods_stmt = $conn->prepare("
    SELECT payment_method, COUNT(*) as count, SUM(amount) as total
    FROM payments
    WHERE status = 'completed'
    GROUP BY payment_method
");
$payment_methods_stmt->execute();
$payment_methods = $payment_methods_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Recent payments
$recent_payments_stmt = $conn->prepare("
    SELECT p.payment_id, p.amount, p.payment_method, p.payment_date, p.reference_number,
           u.firstName as payer_first, u.lastName as payer_last, i.invoice_number,
           s.photo, c.courseName, b.batch_code
    FROM payments p
    JOIN invoices i ON p.invoice_id = i.invoice_id
    JOIN users u ON p.payer_user_id = u.user_id
    JOIN course_enrollments ce ON i.enrollment_id = ce.enrollment_id
    JOIN students s ON ce.student_id = s.student_id
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    WHERE p.status = 'completed'
    ORDER BY p.payment_date DESC LIMIT 10
");
$recent_payments_stmt->execute();
$recent_payments = $recent_payments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Pending payments
$pending_payments_stmt = $conn->prepare("
    SELECT i.invoice_id, i.invoice_number, i.amount, i.due_date, i.status, i.created_at,
           s.student_id, u.firstName, u.lastName, c.courseName, b.batch_code,
           s.photo as student_photo
    FROM invoices i
    JOIN course_enrollments ce ON i.enrollment_id = ce.enrollment_id
    JOIN students s ON ce.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    WHERE i.status IN ('pending', 'overdue')
    ORDER BY i.due_date ASC LIMIT 10
");
$pending_payments_stmt->execute();
$pending_payments = $pending_payments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Top paying students
$top_payers_stmt = $conn->prepare("
    SELECT u.firstName, u.lastName, s.photo, SUM(p.amount) as total_paid, COUNT(p.payment_id) as payment_count
    FROM payments p
    JOIN users u ON p.payer_user_id = u.user_id
    JOIN students s ON u.user_id = s.user_id
    WHERE p.status = 'completed'
    GROUP BY p.payer_user_id
    ORDER BY total_paid DESC
    LIMIT 5
");
$top_payers_stmt->execute();
$top_payers = $top_payers_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Finance Dashboard - Girls Coding Academy</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
        --primary: #667eea;
        --secondary: #764ba2;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --info: #06b6d4;
        --light: #f8fafc;
        --dark: #1e293b;
    }
    
    body { 
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
        background: var(--light); 
        margin:0; 
        padding: 0; 
        display: flex; 
        min-height: 100vh; 
    }
    
    main.main {
        padding: 32px;
        flex: 1;
        min-height: 100vh;
        padding-top: 100px;
    }
    
    @media (min-width: 992px) {
        .main {
            margin-left: 280px !important;
        }
    }
    
    .page-header {
        margin-bottom: 32px;
    }
    
    .page-header h2 {
        font-size: 2rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 8px;
    }
    
    .page-header p {
        color: #64748b;
        font-size: 1rem;
    }
    
    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
    }
    
    .stat-card.revenue::before { background: linear-gradient(90deg, var(--success), #059669); }
    .stat-card.pending::before { background: linear-gradient(90deg, var(--warning), #d97706); }
    .stat-card.overdue::before { background: linear-gradient(90deg, var(--danger), #dc2626); }
    .stat-card.payers::before { background: linear-gradient(90deg, var(--info), #0891b2); }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    }
    
    .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        margin-bottom: 16px;
    }
    
    .stat-icon.revenue { background: linear-gradient(135deg, var(--success), #059669); color: white; }
    .stat-icon.pending { background: linear-gradient(135deg, var(--warning), #d97706); color: white; }
    .stat-icon.overdue { background: linear-gradient(135deg, var(--danger), #dc2626); color: white; }
    .stat-icon.payers { background: linear-gradient(135deg, var(--info), #0891b2); color: white; }
    
    .stat-label {
        font-size: 0.875rem;
        color: #64748b;
        margin-bottom: 8px;
        font-weight: 500;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 4px;
    }
    
    .stat-subtitle {
        font-size: 0.875rem;
        color: #64748b;
    }
    
    .card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #e2e8f0;
    }
    
    .card-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .table {
        margin-bottom: 0;
    }
    
    .table thead th {
        background: #f8fafc;
        border-top: none;
        font-weight: 600;
        font-size: 0.875rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 12px;
    }
    
    .table tbody td {
        padding: 16px 12px;
        vertical-align: middle;
        color: var(--dark);
    }
    
    .table-hover tbody tr:hover {
        background: #f8fafc;
    }
    
    .payer-img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #e2e8f0;
    }
    
    .badge {
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.75rem;
        letter-spacing: 0.3px;
    }
    
    .collect-btn {
        background: none;
        border: 1px solid #e2e8f0;
        color: var(--warning);
        cursor: pointer;
        padding: 8px 12px;
        border-radius: 8px;
        transition: all 0.2s ease;
    }
    
    .collect-btn:hover {
        background: #fef3c7;
        border-color: var(--warning);
    }
    
    .alert-success {
        background: #d1fae5;
        border: none;
        color: #065f46;
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 24px;
    }
    
    .payment-method-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 500;
    }
    
    .method-cash { background: #d1fae5; color: #065f46; }
    .method-bank { background: #dbeafe; color: #1e40af; }
    .method-card { background: #ede9fe; color: #6b21a8; }
    .method-mobile { background: #fef3c7; color: #92400e; }
    
    .top-payer-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 0;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .top-payer-item:last-child {
        border-bottom: none;
    }
    
    .top-payer-rank {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.875rem;
    }
    
    .chart-container {
        position: relative;
        height: 300px;
    }
  </style>
</head>
<body>

<main class="main">
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i> <?= $success_message ?>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h2><i class="bi bi-graph-up"></i> Finance Dashboard</h2>
        <p>Monitor revenue, track payments, and manage outstanding invoices.</p>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="stat-card revenue">
                <div class="stat-icon revenue">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value">LSL <?= number_format($total_revenue, 2) ?></div>
                <div class="stat-subtitle">Lifetime earnings</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card pending">
                <div class="stat-icon pending">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="stat-label">Pending Invoices</div>
                <div class="stat-value">LSL <?= number_format($pending_amount, 2) ?></div>
                <div class="stat-subtitle"><?= $pending_count ?> invoice(s) pending</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card overdue">
                <div class="stat-icon overdue">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div class="stat-label">Overdue Payments</div>
                <div class="stat-value">LSL <?= number_format($overdue_amount, 2) ?></div>
                <div class="stat-subtitle"><?= $overdue_count ?> invoice(s) overdue</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card payers">
                <div class="stat-icon payers">
                    <i class="bi bi-people"></i>
                </div>
                <div class="stat-label">Total Payers</div>
                <div class="stat-value"><?= $total_payers ?></div>
                <div class="stat-subtitle">Unique students</div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-title">
                    <i class="bi bi-bar-chart-line"></i>
                    Revenue Trends (Last 12 Months)
                </div>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-title">
                    <i class="bi bi-pie-chart"></i>
                    Payment Methods
                </div>
                <div class="chart-container">
                    <canvas id="paymentMethodChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-title">
                    <i class="bi bi-clock-history"></i>
                    Recent Payments
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Course</th>
                                <th>Invoice</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_payments as $payment): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= htmlspecialchars($payment['photo'] ?? 'default.jpg') ?>" alt="Student" class="payer-img">
                                        <span><?= htmlspecialchars($payment['payer_first'] . ' ' . $payment['payer_last']) ?></span>
                                    </div>
                                </td>
                                <td><strong class="text-success">LSL <?= number_format($payment['amount'], 2) ?></strong></td>
                                <td>
                                    <span class="payment-method-badge method-<?= $payment['payment_method'] ?>">
                                        <?php 
                                        $icons = ['cash' => '💵', 'bank_transfer' => '🏦', 'card' => '💳', 'mobile_money' => '📱'];
                                        echo $icons[$payment['payment_method']] ?? '💰';
                                        ?>
                                        <?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($payment['courseName']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($payment['batch_code']) ?></small>
                                </td>
                                <td><code><?= htmlspecialchars($payment['invoice_number']) ?></code></td>
                                <td><?= date('M j, Y', strtotime($payment['payment_date'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-title">
                    <i class="bi bi-trophy"></i>
                    Top Payers
                </div>
                <?php foreach($top_payers as $index => $payer): ?>
                    <div class="top-payer-item">
                        <div class="top-payer-rank"><?= $index + 1 ?></div>
                        <img src="<?= htmlspecialchars($payer['photo'] ?? 'default.jpg') ?>" alt="Student" class="payer-img">
                        <div class="flex-grow-1">
                            <div class="fw-semibold"><?= htmlspecialchars($payer['firstName'] . ' ' . $payer['lastName']) ?></div>
                            <small class="text-muted"><?= $payer['payment_count'] ?> payment(s)</small>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-success">LSL <?= number_format($payer['total_paid'], 2) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Pending Payments -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-title">
                    <i class="bi bi-exclamation-circle"></i>
                    Outstanding Invoices
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Course/Batch</th>
                                <th>Invoice</th>
                                <th>Amount</th>
                                <th>Issued</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($pending_payments as $invoice): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= htmlspecialchars($invoice['student_photo'] ?? 'default.jpg') ?>" alt="Student" class="payer-img">
                                        <span><?= htmlspecialchars($invoice['firstName'] . ' ' . $invoice['lastName']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($invoice['courseName']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($invoice['batch_code']) ?></small>
                                </td>
                                <td><code><?= htmlspecialchars($invoice['invoice_number']) ?></code></td>
                                <td><strong>LSL <?= number_format($invoice['amount'], 2) ?></strong></td>
                                <td><?= date('M j, Y', strtotime($invoice['created_at'])) ?></td>
                                <td><?= date('M j, Y', strtotime($invoice['due_date'])) ?></td>
                                <td>
                                    <span class="badge bg-<?= $invoice['status'] === 'pending' ? 'warning' : 'danger' ?>">
                                        <?= ucfirst($invoice['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="student_id" value="<?= $invoice['student_id'] ?>">
                                        <input type="hidden" name="course_name" value="<?= htmlspecialchars($invoice['courseName']) ?>">
                                        <input type="hidden" name="invoice_number" value="<?= htmlspecialchars($invoice['invoice_number']) ?>">
                                        <button type="submit" name="send_notification" class="collect-btn" title="Send Payment Reminder" onclick="return confirm('Send notification to student?')">
                                            <i class="bi bi-bell"></i> Notify
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(function($m) { return date('M Y', strtotime($m . '-01')); }, array_keys($monthly_revenue))) ?>,
            datasets: [{
                label: 'Revenue (LSL)',
                data: <?= json_encode(array_values($monthly_revenue)) ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderColor: 'rgb(16, 185, 129)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'LSL ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    // Payment Method Chart
    const methodCtx = document.getElementById('paymentMethodChart').getContext('2d');
    const methodChart = new Chart(methodCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_map(function($m) { return ucfirst(str_replace('_', ' ', $m['payment_method'])); }, $payment_methods)) ?>,
            datasets: [{
                data: <?= json_encode(array_column($payment_methods, 'count')) ?>,
                backgroundColor: [
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(139, 92, 246, 0.8)',
                    'rgba(245, 158, 11, 0.8)'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
</script>
</body>
</html>