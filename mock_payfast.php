<?php
session_start();

if (!isset($_SESSION['pending_user_id'])) {
    die("No pending user found.");
}

// Simulate a short delay like a real gateway
sleep(2);

// Simulate PayFast returning successful transaction
header("Location: registration.php?return=1&pf_payment_id=TEST123456");
exit;
?>
