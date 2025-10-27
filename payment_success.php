<?php
session_start();

// Check if logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$transaction_id = isset($_GET['txn']) ? htmlspecialchars($_GET['txn']) : 'N/A';
$amount = isset($_GET['amount']) ? number_format(floatval($_GET['amount']), 2) : '0.00';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Successful</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .success-container {
            background: white;
            border-radius: 20px;
            padding: 50px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
            animation: fadeInUp 0.6s ease;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: scaleIn 0.5s ease 0.3s both;
        }
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        .success-icon i {
            font-size: 50px;
            color: white;
        }
        h2 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .amount-display {
            font-size: 3rem;
            font-weight: 700;
            color: #11998e;
            margin: 30px 0;
        }
        .transaction-details {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            text-align: left;
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
        .btn-primary {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            margin: 10px;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(17, 153, 142, 0.4);
        }
        .btn-secondary {
            background: #6c757d;
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            margin: 10px;
        }
        .success-message {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 20px;
        }
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background: #f0f;
            position: absolute;
            animation: confetti-fall 3s linear infinite;
        }
        @keyframes confetti-fall {
            to {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0;
            }
        }
    </style>
</head>
<body>

<div class="success-container">
    <div class="success-icon">
        <i class="bi bi-check-lg"></i>
    </div>
    
    <h2>Payment Successful!</h2>
    <p class="success-message">Your payment has been processed successfully.</p>
    
    <div class="amount-display">
        M<?= $amount ?>
    </div>
    
    <div class="transaction-details">
        <div class="detail-row">
            <span class="detail-label">Transaction ID:</span>
            <span class="detail-value"><?= $transaction_id ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Payment Status:</span>
            <span class="detail-value"><span class="badge bg-success">Completed</span></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Date & Time:</span>
            <span class="detail-value"><?= date('F j, Y - g:i A') ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Amount Paid:</span>
            <span class="detail-value">$<?= $amount ?></span>
        </div>
    </div>
    
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> A confirmation email has been sent to your registered email address. Please save this transaction ID for your records.
    </div>
    
    <div class="mt-4">
        <a href="make_payment.php" class="btn btn-primary">
            <i class="bi bi-arrow-left"></i> Back to Payments
        </a>
        <a href="student_dashboard.php" class="btn btn-secondary">
            <i class="bi bi-house"></i> Go to Dashboard
        </a>
    </div>
    
    <p class="mt-4 text-muted small">
        <i class="bi bi-shield-check"></i> This transaction is secure and encrypted.
    </p>
</div>

<script>
// Create confetti effect
function createConfetti() {
    for (let i = 0; i < 50; i++) {
        let confetti = document.createElement('div');
        confetti.className = 'confetti';
        confetti.style.left = Math.random() * 100 + '%';
        confetti.style.animationDelay = Math.random() * 3 + 's';
        confetti.style.background = `hsl(${Math.random() * 360}, 70%, 60%)`;
        document.body.appendChild(confetti);
        
        setTimeout(() => confetti.remove(), 3000);
    }
}

// Trigger confetti on page load
window.addEventListener('load', createConfetti);
</script>

</body>
</html>