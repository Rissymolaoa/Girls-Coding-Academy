<?php
session_start();
include 'db.php'; // your mysqli connection

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

// Only load PHPMailer if it exists (so page doesn't crash in dev)
$phpmailer_autoload = 'vendor/autoload.php';
if (file_exists($phpmailer_autoload)) {
    require $phpmailer_autoload;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $message = 'Please enter your email address.';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'error';
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT user_id, username FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Save token to DB
            $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?");
            $update->bind_param("ssi", $token, $expires, $user['user_id']);
            $update->execute();

            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;

            // Try to send email
            $email_sent = false;

            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                try {
                    $mail = new PHPMailer(true);

                    // Gmail SMTP Settings
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'rissymolaoa216@gmail.com';     // Your Gmail
                    $mail->Password   = 'euuq przd zlwy semf';                // ← YOUR GMAIL APP PASSWORD
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('noreply@girlscodingacademy.com', 'Girls Coding Academy');
                    $mail->addAddress($email, $user['username']);

                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset - Girls Coding Academy';

                    $mail->Body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #eee; border-radius: 12px;'>
                        <h2 style='color: #1e3a8a; text-align: center;'>Password Reset Request</h2>
                        <p>Hello <strong>{$user['username']}</strong>,</p>
                        <p>You requested a password reset for your Girls Coding Academy account.</p>
                        <p style='text-align: center; margin: 30px 0;'>
                            <a href='$reset_link' style='background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; padding: 14px 32px; text-decoration: none; border-radius: 50px; font-weight: bold; display: inline-block;'>
                                Reset My Password
                            </a>
                        </p>
                        <p>Or copy this link:</p>
                        <p style='background: #f1f5f9; padding: 12px; border-radius: 8px; word-break: break-all; font-size: 14px;'>
                            $reset_link
                        </p>
                        <p><small><strong>This link expires in 1 hour.</strong></small></p>
                        <p>If you didn't request this, please ignore this email.</p>
                        <hr style='margin: 30px 0;'>
                        <p style='color: #64748b; font-size: 14px; text-align: center;'>
                            © " . date('Y') . " Girls Coding Academy • Lesotho
                        </p>
                    </div>
                    ";

                    $mail->AltBody = "Hello {$user['username']},\n\nClick this link to reset your password:\n$reset_link\n\nThis link expires in 1 hour.";

                    $mail->send();
                    $email_sent = true;

                } catch (Exception $e) {
                    // Email failed – fall back to showing link
                    error_log("Mailer Error: " . $mail->ErrorInfo);
                }
            }

            // Success message
            if ($email_sent) {
                $message = 'Check your email! We sent you a password reset link.';
                $message_type = 'success';
            } else {
                // Development fallback: show link directly
                $_SESSION['temp_reset_link'] = $reset_link;
                $message = '
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p class="font-bold text-blue-900 mb-3">Development Mode: Email Not Sent</p>
                        <p class="mb-3">Here is your password reset link:</p>
                        <div class="bg-white p-4 rounded border text-center">
                            <a href="' . $reset_link . '" class="text-blue-600 font-bold break-all hover:underline">
                                ' . $reset_link . '
                            </a>
                        </div>
                        <p class="text-sm text-gray-600 mt-3">
                            <strong>Tip:</strong> Install PHPMailer via Composer to enable real email delivery.
                        </p>
                    </div>';
                $message_type = 'info';
            }
        } else {
            // Security: don't reveal if email exists
            $message = 'If an account exists with this email, you will receive reset instructions.';
            $message_type = 'success';
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password • Girls Coding Academy</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            padding: 3rem;
            width: 100%;
            max-width: 480px;
        }
        .btn {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 14px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(59,130,246,0.4);
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
    }
    </style>
</head>
<body>

<div class="card">
    <div class="text-center mb-8">
        <i class="fas fa-lock text-6xl text-blue-600 mb-4"></i>
        <h1 class="text-3xl font-bold text-gray-800">Forgot Password?</h1>
        <p class="text-gray-600 mt-3">No worries! We'll help you get back in.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert-<?= $message_type ?> p-4 rounded-lg mb-6 text-sm">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-6">
            <label class="block text-gray-700 font-medium mb-2">Email Address</label>
            <input 
                type="email" 
                name="email" 
                required 
                placeholder="you@example.com"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-4 focus:ring-blue-200 focus:border-blue-500 outline-none transition"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <button type="submit" class="w-full btn text-lg">
            Send Reset Link
        </button>
    </form>

    <div class="text-center mt-8">
        <a href="login.html" class="text-blue-600 hover:underline font-medium">
            Back to Login
        </a>
    </div>
</div>

</body>
</html>