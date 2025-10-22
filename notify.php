<?php
// notify.php - Handles PayFast ITN (Instant Transaction Notification)
header('HTTP/1.0 200 OK');
flush();

include 'config.php'; // Your DB conn (adapt to mysqli)

if (!pf_valid_ip()) die('IP Mismatch');
if (!isset($_POST['payment_status']) || $_POST['payment_status'] !== 'COMPLETE') die('Invalid Status');

$pfData = $_POST;
if (pf_valid_signature($pfData, PAYFAST_PASSPHRASE)) {
    $user_id = $_POST['custom_str1']; // From pfData
    $payment_id = $_POST['pf_payment_id'];
    
    // Update user to active
    $update = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ? AND status = 'pending'");
    $update->bind_param("i", $user_id);
    $update->execute();
    
    // Optional: Log payment
    // INSERT INTO payments (user_id, pf_payment_id, amount, status) VALUES (?, ?, ?, 'paid')
    
    echo 'OK'; // Acknowledge to PayFast
} else {
    die('Signature Fail');
}
?>