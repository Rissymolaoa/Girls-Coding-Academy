<?php
session_start();

// Check if logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

include("db.php");

// Fetch pending invoices for the student
$user_id = $_SESSION['user_id'];
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

// Fetch all payments history for the student
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

// Group payments by invoice for easy access
$paymentsByInvoice = [];
foreach ($allPayments as $payment) {
    $invId = $payment['invoice_id'];
    if (!isset($paymentsByInvoice[$invId])) {
        $paymentsByInvoice[$invId] = [];
    }
    $paymentsByInvoice[$invId][] = $payment;
}

// Prepare data for bar graph (monthly payments in last 12 months)
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Make Payment - Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
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
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        
        .container-flex {
            display: flex;
            min-height: 100vh;
        }
        
        .content { 
            flex: 1; 
            padding: 32px;
            margin-left: 280px;
            overflow-y: auto;
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
        
        .main-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 24px;
        }
        
        .payment-card { 
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            position: relative;
            overflow: hidden;
        }
        
        .payment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .payment-card:hover {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }
        
        .payment-card.overdue::before {
            background: var(--danger);
        }
        
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .invoice-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 4px;
        }
        
        .invoice-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 4px;
        }
        
        .detail-value {
            font-weight: 600;
            color: var(--dark);
        }
        
        .amount-highlight {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .form-control, .form-select {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 16px;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 14px 24px;
            border-radius: 8px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .badge {
            font-size: 0.75rem;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        
        .no-invoices {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .no-invoices i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 16px;
        }
        
        .sidebar-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .sidebar-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .payment-history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .payment-history-item:last-child {
            border-bottom: none;
        }
        
        .payment-method-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-right: 12px;
        }
        
        .method-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 16px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 16px;
        }
        
        .method-cash { background: linear-gradient(135deg, #10b981, #059669); }
        .method-bank { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .method-card-pay { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .method-mobile { background: linear-gradient(135deg, #f59e0b, #d97706); }
        
        @media (max-width: 992px) {
            .main-container {
                grid-template-columns: 1fr;
            }
            .content {
                margin-left: 0;
                padding: 20px;
            }
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 16px 20px;
            margin-bottom: 24px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>

<div class="container-flex">
    <?php include("student_navigation.php"); ?>

    <div class="content">
        <div class="page-header">
            <h2><i class="bi bi-credit-card"></i> Make a Payment</h2>
            <p>Review and pay your pending invoices securely through our payment gateway.</p>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> Payment recorded successfully! It will reflect in your account.
            </div>
        <?php endif; ?>

        <div class="main-container">
            <!-- Left Section: Invoices -->
            <div class="left-section">
                <?php if (empty($invoices)): ?>
                    <div class="no-invoices">
                        <i class="bi bi-check-circle"></i>
                        <h4>All Clear!</h4>
                        <p class="text-muted">You have no pending invoices at the moment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($invoices as $invoice): ?>
                        <?php
                        $invPayments = $paymentsByInvoice[$invoice['invoice_id']] ?? [];
                        $totalPaidForInv = array_sum(array_column($invPayments, 'amount'));
                        $remaining = $invoice['amount'] - $totalPaidForInv;
                        ?>
                        <div class="payment-card <?= $invoice['status'] === 'overdue' ? 'overdue' : '' ?>">
                            <div class="invoice-header">
                                <div>
                                    <div class="invoice-title">
                                        <?= htmlspecialchars($invoice['invoice_number']) ?>
                                    </div>
                                    <small class="text-muted"><?= htmlspecialchars($invoice['courseName']) ?> - <?= htmlspecialchars($invoice['batch_code']) ?></small>
                                </div>
                                <span class="badge bg-<?= $invoice['status'] === 'pending' ? 'warning' : 'danger' ?>">
                                    <?= ucfirst($invoice['status']) ?>
                                </span>
                            </div>

                            <div class="invoice-details">
                                <div class="detail-item">
                                    <span class="detail-label">Amount Due</span>
                                    <span class="detail-value amount-highlight">LSL <?= number_format($remaining, 2) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Due Date</span>
                                    <span class="detail-value"><?= date('M j, Y', strtotime($invoice['due_date'])) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Total Paid</span>
                                    <span class="detail-value text-success">LSL <?= number_format($totalPaidForInv, 2) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Original Amount</span>
                                    <span class="detail-value">LSL <?= number_format($invoice['amount'], 2) ?></span>
                                </div>
                            </div>

                            <form method="POST" action="payment_gateway.php">
                                <input type="hidden" name="invoice_id" value="<?= $invoice['invoice_id'] ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="bi bi-cash"></i> Amount to Pay</label>
                                    <input type="number" name="amount" class="form-control" step="0.01" min="0" max="<?= $remaining ?>" value="<?= $remaining ?>" required>
                                    <small class="text-muted">Maximum: LSL <?= number_format($remaining, 2) ?></small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="bi bi-wallet2"></i> Payment Method</label>
                                    <select name="payment_method" class="form-select" required>
                                        <option value="">Select Payment Method</option>
                                        <option value="cash">💵 Cash Payment</option>
                                        <option value="bank_transfer">🏦 Bank Transfer</option>
                                        <option value="card">💳 Credit/Debit Card</option>
                                        <option value="mobile_money">📱 Mobile Money</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-submit">
                                    <i class="bi bi-lock-fill"></i> Proceed to Secure Payment
                                </button>
                            </form>

                            <?php if (!empty($invPayments)): ?>
                                <div class="mt-3 pt-3" style="border-top: 1px solid #e2e8f0;">
                                    <small class="text-muted">
                                        <i class="bi bi-clock-history"></i> 
                                        <?= count($invPayments) ?> payment(s) made for this invoice
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Right Sidebar -->
            <div class="right-section">
                <!-- Payment History -->
                <div class="sidebar-card">
                    <div class="sidebar-title">
                        <i class="bi bi-clock-history"></i>
                        Recent Payments
                    </div>
                    <?php if (empty($allPayments)): ?>
                        <p class="text-muted small text-center py-3">No payment history yet.</p>
                    <?php else: ?>
                        <?php foreach (array_slice($allPayments, 0, 5) as $payment): ?>
                            <div class="payment-history-item">
                                <div>
                                    <div class="small fw-semibold"><?= htmlspecialchars($payment['invoice_number']) ?></div>
                                    <div class="small text-muted"><?= date('M j, Y', strtotime($payment['payment_date'])) ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-success">LSL <?= number_format($payment['amount'], 2) ?></div>
                                    <div class="small text-muted"><?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($allPayments) > 5): ?>
                            <div class="text-center mt-3">
                                <small class="text-muted">+ <?= count($allPayments) - 5 ?> more payments</small>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Payment Chart -->
                <div class="sidebar-card">
                    <div class="sidebar-title">
                        <i class="bi bi-bar-chart"></i>
                        Payment Trends
                    </div>
                    <canvas id="paymentChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const ctx = document.getElementById('paymentChart').getContext('2d');
    const paymentChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(function($m) { return date('M', strtotime($m . '-01')); }, $months)) ?>,
            datasets: [{
                label: 'Payments (LSL)',
                data: <?= json_encode($amounts) ?>,
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                borderColor: 'rgb(102, 126, 234)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
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
                        }
                    }
                }
            }
        }
    });
</script>
</body>
</html>