<?php
// verify.php

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb"; // your DB name

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$messageTitle = "";
$messageBody  = "";
$messageType  = ""; // success | error | warning

// Check if token is provided in the URL
if (isset($_GET['token'])) {
    $token = $conn->real_escape_string($_GET['token']);

    // 1. Find the user_id with this token and pending status
    $sql = "SELECT uv.user_id 
            FROM user_verifications uv
            WHERE uv.verification_token='$token' AND uv.status='pending'
            LIMIT 1";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $user_id = $row['user_id'];

        // 2. Update verification status to "verified"
        $updateVerify = "UPDATE user_verifications 
                         SET status='verified', verified_at=NOW() 
                         WHERE user_id='$user_id'";
        $conn->query($updateVerify);

        // 3. Update user status from "pending" → "await_approval"
        $updateUser = "UPDATE users 
                       SET status='pending'
                       WHERE user_id='$user_id'";
        $conn->query($updateUser);

        $messageTitle = "✅ Email Verification Successful";
        $messageBody  = "Your account has been verified successfully. You can now proceed to <a href='login.html'>Login</a>.";
        $messageType  = "success";
    } else {
        $messageTitle = "❌ Invalid or Expired Link";
        $messageBody  = "This verification link is either invalid or has already been used.";
        $messageType  = "error";
    }
} else {
    $messageTitle = "⚠️ No Token Provided";
    $messageBody  = "The verification link is missing. Please check your email again.";
    $messageType  = "warning";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Verification</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #4e54c8, #8f94fb);
            font-family: Arial, sans-serif;
        }
        .verify-container {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(15px);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            text-align: center;
            color: #fff;
            width: 400px;
            animation: fadeIn 1s ease-in-out;
        }
        .verify-container h2 {
            margin-bottom: 20px;
            font-size: 22px;
            font-weight: bold;
        }
        .verify-container p {
            font-size: 14px;
            margin-bottom: 20px;
        }
        .verify-container a {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 18px;
            border-radius: 6px;
            text-decoration: none;
            background: #6a11cb;
            color: #fff;
            font-weight: bold;
            transition: 0.3s;
        }
        .verify-container a:hover {
            background: #2575fc;
        }
        footer {
            position: absolute;
            bottom: 12px;
            width: 100%;
            text-align: center;
            font-size: 12px;
            color: #eee;
        }
        /* Success, error, warning colors */
        .success h2 { color: #00ffae; }
        .error h2   { color: #ff6b6b; }
        .warning h2 { color: #ffd93d; }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>
</head>
<body>
    <div class="verify-container <?php echo $messageType; ?>">
        <h2><?php echo $messageTitle; ?></h2>
        <p><?php echo $messageBody; ?></p>
        <?php if ($messageType !== "success") { ?>
            <a href="login.html">Go to Login</a>
        <?php } ?>
    </div>
    <footer>
        &copy; <?php echo date("Y"); ?> Girls Coding Academy. All Rights Reserved.
    </footer>
</body>
</html>
