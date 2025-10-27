<?php
session_start();

// Check if logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

include("db.php");

// Get payment details from POST
if (!isset($_POST['invoice_id']) || !isset($_POST['amount']) || !isset($_POST['payment_method'])) {
    header("Location: make_payment.php");
    exit();
}

$invoice_id = intval($_POST['invoice_id']);
$amount = floatval($_POST['amount']);
$payment_method = $_POST['payment_method'];
$user_id = $_SESSION['user_id'];

// Fetch invoice details
$stmt = $conn->prepare("
    SELECT i.*, u.firstName, u.lastName, c.courseName, b.batch_code
    FROM invoices i 
    JOIN course_enrollments ce ON i.enrollment_id = ce.enrollment_id 
    JOIN students s ON ce.student_id = s.student_id 
    JOIN users u ON s.user_id = u.user_id
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    WHERE i.invoice_id = ? AND s.user_id = ?
");
$stmt->bind_param("ii", $invoice_id, $user_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

if (!$invoice) {
    header("Location: make_payment.php?error=invalid_invoice");
    exit();
}

// Generate a unique transaction ID
$transaction_id = 'TXN-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 8));

// Store payment details in session for processing
$_SESSION['pending_payment'] = [
    'invoice_id' => $invoice_id,
    'amount' => $amount,
    'payment_method' => $payment_method,
    'transaction_id' => $transaction_id,
    'timestamp' => time()
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Gateway - Testing Mode</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .payment-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .payment-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .payment-header h2 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .payment-header .badge {
            font-size: 0.9rem;
            padding: 8px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .payment-details {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #6c757d;
        }
        .detail-value {
            font-weight: 700;
            color: #2c3e50;
        }
        .amount-highlight {
            font-size: 2rem;
            color: #667eea;
            text-align: center;
            margin: 20px 0;
            font-weight: 700;
        }
        .payment-method-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            text-align: center;
        }
        .payment-method-card i {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .payment-method-card h4 {
            margin: 0;
            text-transform: capitalize;
        }
        .btn-pay {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
            margin-bottom: 10px;
        }
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-size: 1rem;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-cancel:hover {
            background: #5a6268;
        }
        .security-notice {
            background: #d1ecf1;
            border-left: 4px solid #0c5460;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .security-notice i {
            color: #0c5460;
            margin-right: 8px;
        }
        .test-mode-banner {
            background: #fff3cd;
            border: 2px dashed #ffc107;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin-bottom: 25px;
            font-weight: 600;
            color: #856404;
        }
        .test-mode-banner i {
            font-size: 1.5rem;
            display: block;
            margin-bottom: 10px;
        }
        .processing {
            display: none;
            text-align: center;
            padding: 40px;
        }
        .processing.active {
            display: block;
        }
        .spinner-border {
            width: 4rem;
            height: 4rem;
            border-width: 0.4rem;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .payment-container {
            animation: fadeIn 0.5s ease;
        }
    </style>
</head>
<body>

<div class="payment-container">
    <div class="test-mode-banner">
        <i class="bi bi-exclamation-triangle"></i>
        <strong>TEST MODE:</strong> This is a simulated payment gateway for testing purposes only. No real transactions will be processed.
    </div>

    <div class="payment-header">
        <h2><i class="bi bi-lock-fill"></i> Secure Payment Gateway</h2>
        <span class="badge bg-success">SSL Encrypted</span>
    </div>

    <div id="paymentForm">
        <div class="payment-method-card">
            <i class="bi bi-<?= $payment_method === 'cash' ? 'cash-stack' : ($payment_method === 'bank_transfer' ? 'bank' : ($payment_method === 'card' ? 'credit-card' : 'phone')) ?>"></i>
            <h4><?= str_replace('_', ' ', ucwords($payment_method)) ?></h4>
        </div>

        <div class="amount-highlight">
            $<?= number_format($amount, 2) ?>
        </div>

        <div class="payment-details">
            <div class="detail-row">
                <span class="detail-label">Invoice Number:</span>
                <span class="detail-value"><?= htmlspecialchars($invoice['invoice_number']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Student:</span>
                <span class="detail-value"><?= htmlspecialchars($invoice['firstName'] . ' ' . $invoice['lastName']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Course:</span>
                <span class="detail-value"><?= htmlspecialchars($invoice['courseName']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Batch:</span>
                <span class="detail-value"><?= htmlspecialchars($invoice['batch_code']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Transaction ID:</span>
                <span class="detail-value"><?= htmlspecialchars($transaction_id) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Payment Method:</span>
                <span class="detail-value"><?= ucwords(str_replace('_', ' ', $payment_method)) ?></span>
            </div>
        </div>

        <div class="security-notice">
            <i class="bi bi-shield-check"></i>
            <strong>Secure Transaction:</strong> Your payment information is encrypted and secure. This transaction will be processed through our secure payment gateway.
        </div>

        <?php if ($payment_method === 'card'): ?>
            <div class="mb-3">
                <label class="form-label">Card Number</label>
                <input type="text" class="form-control" placeholder="1234 5678 9012 3456" maxlength="19" id="cardNumber">
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Expiry Date</label>
                    <input type="text" class="form-control" placeholder="MM/YY" maxlength="5" id="cardExpiry">
                </div>
                <div class="col-md-6">
                    <label class="form-label">CVV</label>
                    <input type="text" class="form-control" placeholder="123" maxlength="3" id="cardCvv">
                </div>
            </div>
        <?php elseif ($payment_method === 'bank_transfer'): ?>
            <div class="mb-3">
                <label class="form-label">Bank Account Number</label>
                <input type="text" class="form-control" placeholder="Enter your account number" id="bankAccount">
            </div>
            <div class="mb-3">
                <label class="form-label">Bank Name</label>
                <select class="form-select" id="bankName">
                    <option value="">Select Bank</option>
                    <option value="FNB">First National Bank</option>
                    <option value="Nedbank">Nedbank</option>
                    <option value="Standard Bank">Standard Bank</option>
                    <option value="Lesotho PostBank">Lesotho PostBank</option>
                </select>
            </div>
        <?php elseif ($payment_method === 'mobile_money'): ?>
            <div class="mb-3">
                <label class="form-label">Mobile Money Provider</label>
                <select class="form-select" id="mobileProvider">
                    <option value="">Select Provider</option>
                    <option value="Mpesa">M-Pesa</option>
                    <option value="Ecocash">Ecocash</option>
                    <option value="MTN Mobile Money">MTN Mobile Money</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Mobile Number</label>
                <input type="text" class="form-control" placeholder="+266 XXXX XXXX" id="mobileNumber">
            </div>
        <?php elseif ($payment_method === 'cash'): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> For cash payments, please visit the school office during business hours (8:00 AM - 5:00 PM). Present this transaction ID: <strong><?= $transaction_id ?></strong>
            </div>
        <?php endif; ?>

        <button type="button" class="btn btn-pay" onclick="processPayment()">
            <i class="bi bi-check-circle"></i> Confirm Payment
        </button>
        <button type="button" class="btn btn-cancel" onclick="cancelPayment()">
            <i class="bi bi-x-circle"></i> Cancel
        </button>
    </div>

    <div class="processing" id="processingScreen">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Processing...</span>
        </div>
        <h4 class="mt-3">Processing Payment...</h4>
        <p class="text-muted">Please wait while we process your payment securely.</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function processPayment() {
    // Show processing screen
    document.getElementById('paymentForm').style.display = 'none';
    document.getElementById('processingScreen').classList.add('active');

    // Simulate payment processing (2-3 seconds)
    setTimeout(function() {
        // Redirect to payment processing page
        window.location.href = 'process_payment.php';
    }, 2500);
}

function cancelPayment() {
    if (confirm('Are you sure you want to cancel this payment?')) {
        window.location.href = 'make_payment.php';
    }
}

// Format card number input
<?php if ($payment_method === 'card'): ?>
document.getElementById('cardNumber')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\s/g, '');
    let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
    e.target.value = formattedValue;
});

document.getElementById('cardExpiry')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length >= 2) {
        value = value.slice(0, 2) + '/' + value.slice(2, 4);
    }
    e.target.value = value;
});
<?php endif; ?>
</script>

</body>
</html>