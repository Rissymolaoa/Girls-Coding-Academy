<?php
session_start();
// Security: Redirect if not admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

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
    $success_message = "Notification sent successfully!";
}

// === All your existing database queries (unchanged) ===
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

$total_payers_stmt = $conn->prepare("SELECT COUNT(DISTINCT payer_user_id) as count FROM payments WHERE status = 'completed'");
$total_payers_stmt->execute();
$total_payers = $total_payers_stmt->get_result()->fetch_assoc()['count'];

// Monthly revenue (last 12 months)
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
    WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) AND status = 'completed'
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

// Payment methods
$payment_methods_stmt = $conn->prepare("
    SELECT payment_method, COUNT(*) as count, SUM(amount) as total
    FROM payments WHERE status = 'completed'
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

// Pending/overdue invoices
$pending_payments_stmt = $conn->prepare("
    SELECT i.invoice_id, i.invoice_number, i.amount, i.due_date, i.status, i.created_at,
           s.student_id, u.firstName, u.lastName, c.courseName, b.batch_code, s.photo as student_photo
    FROM invoices i
    JOIN course_enrollments ce ON i.enrollment_id = ce.enrollment_id
    JOIN students s ON ce.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    WHERE i.status IN ('pending', 'overdue')
    ORDER BY i.due_date ASC LIMIT 15
");
$pending_payments_stmt->execute();
$pending_payments = $pending_payments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Top payers
$top_payers_stmt = $conn->prepare("
    SELECT u.firstName, u.lastName, s.photo, SUM(p.amount) as total_paid, COUNT(p.payment_id) as payment_count
    FROM payments p
    JOIN users u ON p.payer_user_id = u.user_id
    JOIN students s ON u.user_id = s.user_id
    WHERE p.status = 'completed'
    GROUP BY p.payer_user_id
    ORDER BY total_paid DESC LIMIT 5
");
$top_payers_stmt->execute();
$top_payers = $top_payers_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Finance Dashboard - Girls Coding Academy</title>

    <!-- Bootstrap 5.3 + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary: #4361ee;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e293b;
            --gray: #64748b;
            --light: #f8fafc;
            --border: #e2e8f0;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--light);
            color: var(--dark);
            margin: 0;
            min-height: 100vh;
        }

        /* FIX: Remove unwanted underlines from ALL navigation links */
        a, .nav-link, .navbar-nav .nav-link, .dropdown-item {
            text-decoration: none !important;
        }
        a:hover, .nav-link:hover, .dropdown-item:hover {
            text-decoration: underline !important;
            text-underline-offset: 3px;
        }

        .nav-link {
            transition: all 0.2s ease;
            border-radius: 0.375rem;
            padding: 0.5rem 1rem !important;
        }
        .nav-link:hover {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary) !important;
        }

        main.main {
            padding: 2rem;
            padding-top: 100px;
            flex: 1;
        }
        @media (min-width: 992px) {
            main.main { margin-left: 280px; }
        }

        .page-header h2 {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--dark);
        }
        .page-header p { color: var(--gray); }

        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 60px; height: 60px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.9rem;
            color: white;
            margin-bottom: 1rem;
        }
        .stat-icon.revenue { background: var(--success); }
        .stat-icon.pending { background: var(--warning); }
        .stat-icon.overdue { background: var(--danger); }
        .stat-icon.payers  { background: var(--primary); }

        .stat-value { font-size: 2.1rem; font-weight: 700; color: var(--dark); }
        .stat-label { color: var(--gray); font-size: 0.95rem; font-weight: 500; }

        .card {
            background: white;
            border-radius: 1rem;
            padding: 1.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--border);
            margin-bottom: 1.5rem;
        }
        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table thead th {
            background: #f8fafc;
            color: var(--gray);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        .payer-img {
            width: 42px; height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border);
        }

        .collect-btn {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--warning);
            padding: 0.5rem 0.9rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        .collect-btn:hover {
            background: #fffbeb;
            border-color: var(--warning);
            color: #c2410c;
        }

        .chart-container {
            position: relative;
            height: 340px;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: none;
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
        }
    </style>
</head>
<body>

<main class="main">

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill"></i>
            <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>

    <div class="page-header mb-5">
        <h2><i class="bi bi-graph-up-arrow text-primary"></i> Finance Dashboard</h2>
        <p>Real-time financial overview, revenue trends, and payment management.</p>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-5">
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon revenue"><i class="bi bi-currency-dollar"></i></div>
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value">LSL <?= number_format($total_revenue, 2) ?></div>
                <small class="text-muted">All time earnings</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon pending"><i class="bi bi-clock-history"></i></div>
                <div class="stat-label">Pending Invoices</div>
                <div class="stat-value">LSL <?= number_format($pending_amount, 2) ?></div>
                <small class="text-muted"><?= $pending_count ?> pending</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon overdue"><i class="bi bi-exclamation-triangle"></i></div>
                <div class="stat-label">Overdue Payments</div>
                <div class="stat-value">LSL <?= number_format($overdue_amount, 2) ?></div>
                <small class="text-muted"><?= $overdue_count ?> overdue</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon payers"><i class="bi bi-people"></i></div>
                <div class="stat-label">Total Payers</div>
                <div class="stat-value"><?= $total_payers ?></div>
                <small class="text-muted">Unique students</small>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-5">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-title"><i class="bi bi-bar-chart-line"></i> Revenue Trends (Last 12 Months)</div>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-title"><i class="bi bi-pie-chart"></i> Payment Methods</div>
                <div class="chart-container">
                    <canvas id="paymentMethodChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Payments & Top Payers -->
    <div class="row g-4 mb-5">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-title"><i class="bi bi-clock-history"></i> Recent Payments</div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
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
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= htmlspecialchars($payment['photo'] ?? 'default.jpg') ?>" class="payer-img" alt="">
                                        <span><?= htmlspecialchars($payment['payer_first'] . ' ' . $payment['payer_last']) ?></span>
                                    </div>
                                </td>
                                <td><strong class="text-success">LSL <?= number_format($payment['amount'], 2) ?></strong></td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <?php
                                        $icons = ['cash' => 'Cash', 'bank_transfer' => 'Bank', 'card' => 'Card', 'mobile_money' => 'Mobile'];
                                        echo $icons[$payment['payment_method']] ?? 'Other';
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($payment['courseName']) ?>
                                    <small class="text-muted d-block"><?= htmlspecialchars($payment['batch_code']) ?></small>
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
                <div class="card-title"><i class="bi bi-trophy"></i> Top Payers</div>
                <?php foreach($top_payers as $index => $payer): ?>
                <div class="d-flex align-items-center gap-3 p-3 border-bottom">
                    <div class="fw-bold text-primary" style="width: 32px;">#<?= $index + 1 ?></div>
                    <img src="<?= htmlspecialchars($payer['photo'] ?? 'default.jpg') ?>" class="payer-img" alt="">
                    <div class="flex-grow-1">
                        <div class="fw-semibold"><?= htmlspecialchars($payer['firstName'] . ' ' . $payer['lastName']) ?></div>
                        <small class="text-muted"><?= $payer['payment_count'] ?> payments</small>
                    </div>
                    <div class="text-success fw-bold">LSL <?= number_format($payer['total_paid'], 2) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Outstanding Invoices -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-title"><i class="bi bi-exclamation-circle"></i> Outstanding Invoices</div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Course/Batch</th>
                                <th>Invoice</th>
                                <th>Amount</th>
                                <th>Issued</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($pending_payments as $invoice): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= htmlspecialchars($invoice['student_photo'] ?? 'default.jpg') ?>" class="payer-img" alt="">
                                        <span><?= htmlspecialchars($invoice['firstName'] . ' ' . $invoice['lastName']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?= htmlspecialchars($invoice['courseName']) ?>
                                    <small class="text-muted d-block"><?= htmlspecialchars($invoice['batch_code']) ?></small>
                                </td>
                                <td><code><?= htmlspecialchars($invoice['invoice_number']) ?></code></td>
                                <td><strong>LSL <?= number_format($invoice['amount'], 2) ?></strong></td>
                                <td><?= date('M j, Y', strtotime($invoice['created_at'])) ?></td>
                                <td><?= date('M j, Y', strtotime($invoice['due_date'])) ?></td>
                                <td>
                                    <span class="badge bg-<?= $invoice['status'] === 'pending' ? 'warning' : 'danger' ?> text-white">
                                        <?= ucfirst($invoice['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="student_id" value="<?= $invoice['student_id'] ?>">
                                        <input type="hidden" name="course_name" value="<?= htmlspecialchars($invoice['courseName']) ?>">
                                        <input type="hidden" name="invoice_number" value="<?= htmlspecialchars($invoice['invoice_number']) ?>">
                                        <button type="submit" name="send_notification" class="collect-btn" onclick="return confirm('Send reminder to student?')">
                                            Notify
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Revenue Line Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(fn($m) => date('M Y', strtotime($m . '-01')), array_keys($monthly_revenue))) ?>,
            datasets: [{
                label: 'Revenue (LSL)',
                data: <?= json_encode(array_values($monthly_revenue)) ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderColor: '#10b981',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: value => 'LSL ' + value.toLocaleString() }
                }
            }
        }
    });

    // Payment Method Doughnut Chart
    const methodCtx = document.getElementById('paymentMethodChart').getContext('2d');
    new Chart(methodCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_map(fn($m) => ucfirst(str_replace('_', ' ', $m['payment_method'])), $payment_methods)) ?>,
            datasets: [{
                data: <?= json_encode(array_column($payment_methods, 'count')) ?>,
                backgroundColor: ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b'],
                borderWidth: 3,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });
</script>

</body>
</html>