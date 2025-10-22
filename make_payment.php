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
    SELECT i.*, u.firstName, u.lastName 
    FROM invoices i 
    JOIN course_enrollments ce ON i.enrollment_id = ce.enrollment_id 
    JOIN students s ON ce.student_id = s.student_id 
    JOIN users u ON s.user_id = u.user_id 
    WHERE s.user_id = ?
    AND i.status IN ('pending', 'overdue')
    ORDER BY i.due_date ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle payment submission
$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_id = intval($_POST['invoice_id']);
    $amount = floatval($_POST['amount']);
    $method = $_POST['payment_method'];
    $reference = trim($_POST['reference']);

    // Validate (basic; add more for production)
    if ($amount <= 0 || empty($method)) {
        $error = "Invalid payment details.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO payments (invoice_id, payer_user_id, amount, payment_method, reference_number) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisss", $invoice_id, $user_id, $amount, $method, $reference);
        if ($stmt->execute()) {
            $success = true;
            // Refresh invoices
            $stmt->close();
            header("Location: make_payment.php?success=1");
            exit();
        } else {
            $error = "Payment failed. Try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Make Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
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
            padding: 40px 50px;
            margin-left: 280px;
            overflow-y: auto;
        }
        h2 {
            margin-bottom: 20px;
            color: #2c3e50;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        .payment-card { 
            background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(44, 62, 80, 0.1);
            margin-bottom: 25px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid #e9ecef;
        }
        .payment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3498db, #2980b9);
        }
        .payment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(52, 73, 94, 0.15);
        }
        .payment-card.overdue {
            border-left: 5px solid #e74c3c;
        }
        .payment-card h5 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .payment-card p {
            font-size: 1rem;
            color: #7f8c8d;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 15px;
            transition: border-color 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        .btn-submit {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            width: 100%;
            transition: background 0.3s ease;
        }
        .btn-submit:hover {
            background: #2980b9;
        }
        .badge {
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 20px;
        }
        .no-invoices {
            text-align: center;
            color: #7f8c8d;
            font-style: italic;
            padding: 50px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(44, 62, 80, 0.1);
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 20px;
            }
            .payment-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<div class="container-flex">
    <!-- Include the consistent navigation -->
    <?php include("student_navigation.php"); ?>

    <div class="content">
        <h2><i class="bi bi-credit-card"></i> Make a Payment</h2>
        <p class="mb-4">Review and pay your pending invoices securely.</p>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Payment recorded successfully! It will reflect in the finance reports.</div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="row">
            <?php foreach ($invoices as $invoice): ?>
                <div class="col-md-6">
                    <div class="payment-card <?= $invoice['status'] === 'overdue' ? 'overdue' : '' ?>">
                        <h5><i class="bi bi-receipt"></i> <?= htmlspecialchars($invoice['firstName'] . ' ' . $invoice['lastName']) ?> - <?= htmlspecialchars($invoice['invoice_number']) ?></h5>
                        <p><strong>Amount Due:</strong> $<?= number_format($invoice['amount'], 2) ?></p>
                        <p><strong>Due Date:</strong> <?= date('M j, Y', strtotime($invoice['due_date'])) ?></p>
                        <p><strong>Status:</strong> <span class="badge bg-<?= $invoice['status'] === 'pending' ? 'warning' : 'danger' ?>"><?= ucfirst($invoice['status']) ?></span></p>
                        
                        <form method="POST">
                            <input type="hidden" name="invoice_id" value="<?= $invoice['invoice_id'] ?>">
                            <div class="mb-3">
                                <label class="form-label">Amount to Pay</label>
                                <input type="number" name="amount" class="form-control" step="0.01" min="0" max="<?= $invoice['amount'] ?>" value="<?= $invoice['amount'] ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Payment Method</label>
                                <select name="payment_method" class="form-select" required>
                                    <option value="">Select Method</option>
                                    <option value="cash">Cash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="card">Credit/Debit Card</option>
                                    <option value="mobile_money">Mobile Money</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reference/Transaction ID</label>
                                <input type="text" name="reference" class="form-control" required placeholder="Enter transaction reference">
                            </div>
                            <button type="submit" class="btn btn-submit"><i class="bi bi-lock"></i> Submit Payment</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($invoices)): ?>
            <div class="no-invoices">No pending invoices found. You're all set!</div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>