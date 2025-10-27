<?php
session_start();

// Check if logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

include("db.php");

// Check if there's a pending payment
if (!isset($_SESSION['pending_payment'])) {
    header("Location: make_payment.php?error=no_pending_payment");
    exit();
}

$pending_payment = $_SESSION['pending_payment'];
$user_id = $_SESSION['user_id'];

// Simulate payment processing (in production, this would connect to actual payment gateway API)
// For testing, we'll randomly succeed or fail (90% success rate for testing)
$payment_success = (rand(1, 100) <= 90);

if ($payment_success) {
    // Insert payment record
    $stmt = $conn->prepare("
        INSERT INTO payments (invoice_id, payer_user_id, amount, payment_method, reference_number, status, payment_date) 
        VALUES (?, ?, ?, ?, ?, 'completed', NOW())
    ");
    
    $invoice_id = $pending_payment['invoice_id'];
    $amount = $pending_payment['amount'];
    $payment_method = $pending_payment['payment_method'];
    $reference = $pending_payment['transaction_id'];
    $status = 'completed';
    
    $stmt->bind_param("iidss", $invoice_id, $user_id, $amount, $payment_method, $reference);
    
    if ($stmt->execute()) {
        // Check if this payment completes the invoice
        $totalPaidStmt = $conn->prepare("
            SELECT SUM(p.amount) as total_paid, i.amount as invoice_amount
            FROM payments p 
            JOIN invoices i ON p.invoice_id = i.invoice_id
            WHERE p.invoice_id = ? AND p.status = 'completed'
        ");
        $totalPaidStmt->bind_param("i", $invoice_id);
        $totalPaidStmt->execute();
        $result = $totalPaidStmt->get_result()->fetch_assoc();
        $totalPaid = $result['total_paid'] ?? 0;
        $invoiceAmount = $result['invoice_amount'] ?? 0;
        $totalPaidStmt->close();

        // Update invoice status
        $updateStmt = $conn->prepare("UPDATE invoices SET status = ?, updated_at = NOW() WHERE invoice_id = ?");
        $newStatus = ($totalPaid >= $invoiceAmount) ? 'paid' : 'pending';
        $updateStmt->bind_param("si", $newStatus, $invoice_id);
        $updateStmt->execute();
        $updateStmt->close();

        // Clear pending payment from session
        unset($_SESSION['pending_payment']);

        // Redirect to success page
        header("Location: payment_success.php?txn=" . urlencode($reference) . "&amount=" . $amount);
        exit();
    } else {
        // Database error
        $error = "Database error occurred. Please try again.";
        header("Location: payment_failed.php?error=" . urlencode($error));
        exit();
    }
} else {
    // Payment failed (simulated)
    $error = "Payment gateway declined the transaction. Please check your payment details and try again.";
    
    // Clear pending payment from session
    unset($_SESSION['pending_payment']);
    
    header("Location: payment_failed.php?error=" . urlencode($error));
    exit();
}
?>