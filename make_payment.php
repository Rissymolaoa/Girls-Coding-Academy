<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

include("db.php");

$user_id = $_SESSION['user_id'];
$currentPage = basename($_SERVER['PHP_SELF']);

// Fetch pending invoices for the student
$stmt = $conn->prepare("
    SELECT i.*, u.firstName, u.lastName, ce.batch_id, c.courseName, b.batch_code
    FROM invoices i 
    JOIN course_enrollments ce ON i.enrollment_id = ce.enrollment_id 
    JOIN students s ON ce.student_id = s.student_id 
    JOIN users u ON s.user_id = u.user_id 
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    WHERE s.user_id = ?
    AND i.status IN ('pending', 'overdue')
    ORDER BY i.due_date ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch all payments history
$paymentsStmt = $conn->prepare("
    SELECT p.*, i.invoice_number, i.amount as invoice_amount, i.due_date
    FROM payments p
    JOIN invoices i ON p.invoice_id = i.invoice_id
    JOIN course_enrollments ce ON i.enrollment_id = ce.enrollment_id
    JOIN students s ON ce.student_id = s.student_id
    WHERE s.user_id = ?
    ORDER BY p.payment_date DESC
");
$paymentsStmt->bind_param("i", $user_id);
$paymentsStmt->execute();
$allPayments = $paymentsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group payments by invoice
$paymentsByInvoice = [];
foreach ($allPayments as $payment) {
    $invId = $payment['invoice_id'];
    if (!isset($paymentsByInvoice[$invId])) {
        $paymentsByInvoice[$invId] = [];
    }
    $paymentsByInvoice[$invId][] = $payment;
}

// Monthly payment data
$monthlyPayments = [];
$currentDate = new DateTime();
for ($i = 11; $i >= 0; $i--) {
    $date = clone $currentDate;
    $date->modify("-$i months");
    $monthKey = $date->format('Y-m');
    $monthlyPayments[$monthKey] = 0;
}

foreach ($allPayments as $payment) {
    $paymentDate = new DateTime($payment['payment_date']);
    $monthKey = $paymentDate->format('Y-m');
    if (isset($monthlyPayments[$monthKey])) {
        $monthlyPayments[$monthKey] += $payment['amount'];
    }
}

$months = array_keys($monthlyPayments);
$amounts = array_values($monthlyPayments);

// Fetch payment notifications
$notifStmt = $conn->prepare("
    SELECT * FROM notifications
    WHERE student_id = ? AND type = 'Payment'
    ORDER BY date DESC
    LIMIT 5
");
$notifStmt->bind_param("i", $_SESSION['user_id']);
$notifStmt->execute();
$notifications = $notifStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$totalDue = array_sum(array_map(function($inv) use ($paymentsByInvoice) {
    $paid = array_sum(array_column($paymentsByInvoice[$inv['invoice_id']] ?? [], 'amount'));
    return $inv['amount'] - $paid;
}, $invoices));

$totalPaid = array_sum(array_column($allPayments, 'amount'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Make Payment - Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            height: 100vh;
            overflow: hidden;
            color: #1e293b;
        }

        .container-flex {
            display: flex;
            height: 100vh;
        }

        .content {
            flex: 1;
            padding: 40px;
            margin-left: 250px;
            overflow-y: auto;
            height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .content::-webkit-scrollbar {
            width: 8px;
        }

        .content::-webkit-scrollbar-thumb {
            background: #00d9ff;
            border-radius: 4px;
        }

        .header {
            margin-bottom: 35px;
            padding-bottom: 25px;
            border-bottom: 2px solid rgba(0, 217, 255, 0.3);
        }

        .header h2 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #1e293b 0%, #00d9ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        .header p {
            color: #64748b;
            margin: 0;
        }

        .alert {
            border: none;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 25px;
            backdrop-filter: blur(10px);
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-left-color: #10b981;
            color: #059669;
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border-left-color: #f59e0b;
            color: #d97706;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-left-color: #ef4444;
            color: #dc2626;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 217, 255, 0.2);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0, 217, 255, 0.15);
        }

        .stat-label {
            font-size: 0.9rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 12px;
        }

        .stat-card.due .stat-icon {
            color: #ef4444;
        }

        .stat-card.paid .stat-icon {
            color: #10b981;
        }

        .stat-card.invoices .stat-icon {
            color: #00d9ff;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 30px;
        }

        .card-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 217, 255, 0.2);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .card-section:hover {
            box-shadow: 0 12px 40px rgba(0, 217, 255, 0.15);
            transform: translateY(-2px);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e2e8f0;
        }

        .section-header h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }

        .section-header i {
            font-size: 1.5rem;
            color: #00d9ff;
        }

        .invoice-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 16px;
            border-left: 4px solid #00d9ff;
            transition: all 0.3s ease;
        }

        .invoice-card.overdue {
            border-left-color: #ef4444;
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
        }

        .invoice-card:hover {
            transform: translateX(6px);
            box-shadow: 0 4px 12px rgba(0, 217, 255, 0.15);
        }

        .invoice-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }

        .invoice-num {
            font-weight: 700;
            color: #1e293b;
            font-size: 1.1rem;
        }

        .invoice-course {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 4px;
        }

        .badge {
            font-size: 0.75rem;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-overdue {
            background: #fee2e2;
            color: #991b1b;
        }

        .amount-display {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: 12px 0;
            font-size: 0.9rem;
        }

        .amount-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .amount-label {
            color: #64748b;
        }

        .amount-value {
            font-weight: 700;
            color: #1e293b;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #1e293b;
            font-size: 0.95rem;
        }

        .form-control,
        .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 16px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #00d9ff;
            box-shadow: 0 0 0 0.2rem rgba(0, 217, 255, 0.25);
            outline: none;
        }

        .btn-submit {
            background: linear-gradient(135deg, #00d9ff 0%, #0099cc 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #0099cc 0%, #006699 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 217, 255, 0.3);
            color: white;
        }

        .no-invoices {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }

        .no-invoices i {
            font-size: 2.5rem;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        .notification-item {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 12px;
            border-left: 4px solid #f59e0b;
            transition: all 0.3s ease;
        }

        .notification-item:hover {
            transform: translateX(4px);
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.1);
        }

        .notif-title {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.9rem;
            margin-bottom: 4px;
        }

        .notif-text {
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.4;
        }

        .notif-date {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 6px;
        }

        .chart-container {
            position: relative;
            height: 200px;
            margin-top: 16px;
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 2rem;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        @media (max-width: 1024px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 20px;
            }

            .header h2 {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="container-flex">
    <?php include("student_navigation.php"); ?>

    <div class="content">
        <div class="header">
            <h2><i class="bi bi-credit-card"></i> View & Pay Invoices</h2>
            <p>Manage your payments and keep track of your financial obligations</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> Payment recorded successfully! It will reflect in your account shortly.
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card invoices">
                <div class="stat-label"><i class="bi bi-receipt"></i> Pending Invoices</div>
                <div class="stat-value"><?= count($invoices) ?></div>
            </div>
            <div class="stat-card due">
                <div class="stat-label"><i class="bi bi-exclamation-circle"></i> Total Due</div>
                <div class="stat-value">LSL <?= number_format($totalDue, 2) ?></div>
            </div>
            <div class="stat-card paid">
                <div class="stat-label"><i class="bi bi-check-circle"></i> Total Paid</div>
                <div class="stat-value">LSL <?= number_format($totalPaid, 2) ?></div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="main-grid">
            <!-- Left: Invoices -->
            <div class="card-section">
                <div class="section-header">
                    <i class="bi bi-receipt"></i>
                    <h3>Your Invoices</h3>
                </div>

                <?php if (empty($invoices)): ?>
                    <div class="empty-state">
                        <i class="bi bi-check-circle"></i>
                        <p><strong>All Clear!</strong></p>
                        <p>You have no pending invoices at the moment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($invoices as $invoice): ?>
                        <?php
                        $invPayments = $paymentsByInvoice[$invoice['invoice_id']] ?? [];
                        $totalPaidForInv = array_sum(array_column($invPayments, 'amount'));
                        $remaining = $invoice['amount'] - $totalPaidForInv;
                        ?>
                        <div class="invoice-card <?= $invoice['status'] === 'overdue' ? 'overdue' : '' ?>">
                            <div class="invoice-header-flex">
                                <div>
                                    <div class="invoice-num"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
                                    <div class="invoice-course"><?= htmlspecialchars($invoice['courseName']) ?> - <?= htmlspecialchars($invoice['batch_code']) ?></div>
                                </div>
                                <span class="badge badge-<?= $invoice['status'] === 'pending' ? 'pending' : 'overdue' ?>">
                                    <?= ucfirst($invoice['status']) ?>
                                </span>
                            </div>

                            <div class="amount-display">
                                <div class="amount-item">
                                    <span class="amount-label">Amount Due</span>
                                    <span class="amount-value" style="color: #ef4444;">LSL <?= number_format($remaining, 2) ?></span>
                                </div>
                                <div class="amount-item">
                                    <span class="amount-label">Due Date</span>
                                    <span class="amount-value"><?= date('M d, Y', strtotime($invoice['due_date'])) ?></span>
                                </div>
                                <div class="amount-item">
                                    <span class="amount-label">Paid</span>
                                    <span class="amount-value" style="color: #10b981;">LSL <?= number_format($totalPaidForInv, 2) ?></span>
                                </div>
                                <div class="amount-item">
                                    <span class="amount-label">Original</span>
                                    <span class="amount-value">LSL <?= number_format($invoice['amount'], 2) ?></span>
                                </div>
                            </div>

                            <form method="POST" action="payment_gateway.php">
                                <input type="hidden" name="invoice_id" value="<?= $invoice['invoice_id'] ?>">
                                
                                <div class="form-group">
                                    <label class="form-label"><i class="bi bi-cash"></i> Amount to Pay</label>
                                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01" max="<?= $remaining ?>" value="<?= $remaining ?>" required>
                                    <small style="color: #64748b;">Maximum: LSL <?= number_format($remaining, 2) ?></small>
                                </div>

                                <div class="form-group">
                                    <label class="form-label"><i class="bi bi-wallet2"></i> Payment Method</label>
                                    <select name="payment_method" class="form-select" required>
                                        <option value="">Select Payment Method</option>
                                        <option value="cash">💵 Cash Payment</option>
                                        <option value="bank_transfer">🏦 Bank Transfer</option>
                                        <option value="card">💳 Credit/Debit Card</option>
                                        <option value="mobile_money">📱 Mobile Money</option>
                                    </select>
                                </div>

                                <button type="submit" class="btn-submit">
                                    <i class="bi bi-lock-fill"></i> Proceed to Payment
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Right Sidebar -->
            <div>
                <!-- Notifications -->
                <div class="card-section">
                    <div class="section-header">
                        <i class="bi bi-bell"></i>
                        <h3>Notifications</h3>
                    </div>

                    <?php if (empty($notifications)): ?>
                        <div class="empty-state" style="padding: 20px;">
                            <i class="bi bi-bell-slash"></i>
                            <p style="font-size: 0.9rem;">No notifications</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notif): ?>
                            <div class="notification-item">
                                <div class="notif-title"><?= htmlspecialchars($notif['title']) ?></div>
                                <div class="notif-text"><?= htmlspecialchars(substr($notif['description'], 0, 60)) ?>...</div>
                                <div class="notif-date">
                                    <i class="bi bi-calendar2"></i> <?= date('M d, Y', strtotime($notif['date'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Payment Chart -->
                <div class="card-section">
                    <div class="section-header">
                        <i class="bi bi-graph-up"></i>
                        <h3>Payment Trends</h3>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>

                <!-- Recent Payments -->
                <div class="card-section">
                    <div class="section-header">
                        <i class="bi bi-clock-history"></i>
                        <h3>Recent Payments</h3>
                    </div>

                    <?php if (empty($allPayments)): ?>
                        <div class="empty-state" style="padding: 20px;">
                            <i class="bi bi-inbox"></i>
                            <p style="font-size: 0.9rem;">No payments yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($allPayments, 0, 4) as $payment): ?>
                            <div class="notification-item" style="border-left-color: #10b981;">
                                <div class="notif-title"><?= htmlspecialchars($payment['invoice_number']) ?></div>
                                <div class="notif-text">
                                    LSL <?= number_format($payment['amount'], 2) ?> via <?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?>
                                </div>
                                <div class="notif-date">
                                    <i class="bi bi-check-circle"></i> <?= date('M d, Y', strtotime($payment['payment_date'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const ctx = document.getElementById('paymentChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(function($m) { return date('M', strtotime($m . '-01')); }, $months)) ?>,
            datasets: [{
                label: 'Payments (LSL)',
                data: <?= json_encode($amounts) ?>,
                backgroundColor: 'rgba(0, 217, 255, 0.1)',
                borderColor: '#00d9ff',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: '#00d9ff',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'LSL ' + value;
                        },
                        color: '#64748b'
                    },
                    grid: { color: '#e2e8f0' }
                },
                x: {
                    ticks: { color: '#64748b' },
                    grid: { display: false }
                }
            }
        }
    });
</script>
</body>
</html>