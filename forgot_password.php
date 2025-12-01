<?php
session_start();
include 'db.php';

// Uncomment these lines when you install PHPMailer via Composer
 use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $message = 'Please enter your email address.';
        $message_type = 'error';
    } else {
        // Check if email exists in database
        $stmt = $conn->prepare("SELECT user_id, username, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Generate unique token
            $token = bin2hex(random_bytes(32));
            $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?");
            $update_stmt->bind_param("ssi", $token, $token_expiry, $user['user_id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Create reset link
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/GirlsCodingAcademy/reset_password.php?token=" . $token;
            
            // Try to send email with PHPMailer
            $emailSent = false;
            
            try {
                $mail = new PHPMailer(true);
                
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com'; // Your SMTP server
                $mail->SMTPAuth = true;
                $mail->Username = 'rethabilemackenzie70@gmail.com'; // Your Gmail address
                $mail->Password = 'vxss fson asfi srkr'; // Your Gmail App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                
                // Recipients
                $mail->setFrom('noreply@girlscodingacademy.com', 'Girls Coding Academy');
                $mail->addAddress($email, $user['username']);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request - Girls Coding Academy';
                $mail->Body = "
                    <html>
                    <body style='font-family: Arial, sans-serif;'>
                        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                            <h2 style='color: #1e3a8a;'>Password Reset Request</h2>
                            <p>Hello {$user['username']},</p>
                            <p>You have requested to reset your password. Click the button below to reset your password:</p>
                            <p style='text-align: center; margin: 30px 0;'>
                                <a href='{$reset_link}' style='background: linear-gradient(145deg, #3b82f6 0%, #1d4ed8 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; display: inline-block;'>Reset Password</a>
                            </p>
                            <p>Or copy and paste this link into your browser:</p>
                            <p style='word-break: break-all; color: #3b82f6;'>{$reset_link}</p>
                            <p><strong>This link will expire in 1 hour.</strong></p>
                            <p>If you did not request this password reset, please ignore this email.</p>
                            <hr style='margin: 30px 0; border: none; border-top: 1px solid #e2e8f0;'>
                            <p style='color: #64748b; font-size: 14px;'>Best regards,<br>Girls Coding Academy Team</p>
                        </div>
                    </body>
                    </html>
                ";
                $mail->AltBody = "Hello {$user['username']},\n\nYou have requested to reset your password. Copy and paste this link into your browser:\n\n{$reset_link}\n\nThis link will expire in 1 hour.\n\nIf you did not request this password reset, please ignore this email.\n\nBest regards,\nGirls Coding Academy";
                
                $mail->send();
                $emailSent = true;
                
                $message = 'Password reset instructions have been sent to your email address.';
                $message_type = 'success';
                
            } catch (Exception $e) {
                // Email failed, show link instead
                $emailSent = false;
            }
            
          
            
            // Development mode: Show link directly
            if (!$emailSent) {
                $_SESSION['reset_link'] = $reset_link;
                $_SESSION['reset_email'] = $email;
                
                $message = '<strong>Password reset link generated!</strong><br><br>';
                $message .= '<div style="background: #dbeafe; padding: 15px; border-radius: 8px; margin: 15px 0;">';
                $message .= '<p style="margin: 0 0 10px 0; font-weight: bold;">📧 Development Mode Active</p>';
                $message .= '<p style="margin: 0 0 10px 0;">Click the link below to reset your password:</p>';
                $message .= '<a href="' . $reset_link . '" style="color: #1e40af; font-weight: bold; word-break: break-all;">' . $reset_link . '</a>';
                $message .= '</div>';
                $message .= '<small style="color: #64748b;">Note: In production mode with email configured, this link would be sent to ' . htmlspecialchars($email) . '</small>';
                $message_type = 'success';
            }
        } else {
            // Don't reveal if email exists or not (security best practice)
            $message = 'If an account exists with this email, you will receive password reset instructions.';
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
    <title>Forgot Password - Girls Coding Academy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --secondary-blue: #3b82f6;
            --accent-blue: #1d4ed8;
            --white: #ffffff;
            --light-gray: #f8fafc;
            --border-light: #e2e8f0;
            --shadow-light: 0 4px 20px rgba(0,0,0,0.1);
            --success-green: #10b981;
            --error-red: #ef4444;
            --info-blue: #3b82f6;
        }
        
        * { box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, var(--primary-blue) 0%, #1e40af 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: var(--white);
            max-width: 600px;
            width: 100%;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: var(--shadow-light);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo i {
            font-size: 4rem;
            color: var(--secondary-blue);
            margin-bottom: 1rem;
        }
        
        h1 {
            text-align: center;
            color: var(--primary-blue);
            margin-bottom: 0.5rem;
            font-size: 1.75rem;
        }
        
        .subtitle {
            text-align: center;
            color: #64748b;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }
        
        .message {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        
        .message.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid var(--success-green);
        }
        
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid var(--error-red);
        }
        
        .message.info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid var(--info-blue);
        }
        
        form {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        
        label {
            font-weight: 500;
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        input {
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        input:focus {
            outline: none;
            border-color: var(--secondary-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        button {
            background: linear-gradient(145deg, var(--secondary-blue) 0%, var(--accent-blue) 100%);
            color: var(--white);
            border: none;
            padding: 1rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-light);
        }
        
        .links {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .links a {
            color: var(--secondary-blue);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .links a:hover {
            color: var(--accent-blue);
            text-decoration: underline;
        }
        
        .info-box {
            background: #f1f5f9;
            border-left: 4px solid var(--info-blue);
            padding: 1rem;
            margin-top: 1.5rem;
            border-radius: 8px;
        }
        
        .info-box h4 {
            margin: 0 0 0.5rem 0;
            color: var(--primary-blue);
            font-size: 0.9rem;
        }
        
        .info-box p {
            margin: 0;
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.5;
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <i class="fas fa-key"></i>
        </div>
        
        <h1>Forgot Password?</h1>
        <p class="subtitle">Enter your email address and we'll send you instructions to reset your password.</p>
        
        <?php if (!empty($message)): ?>
            <div class="message <?= $message_type ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div>
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="your.email@example.com" required>
            </div>
            
            <button type="submit">
                <i class="fas fa-paper-plane mr-2"></i>Send Reset Link
            </button>
        </form>
        
        <div class="info-box">
            <h4><i class="fas fa-info-circle"></i> Development Mode</h4>
            <p>
                Email functionality is currently in development mode. The password reset link will be displayed directly on this page.
                <br><br>
                <strong>To enable email sending:</strong>
                <ol style="margin: 10px 0; padding-left: 20px;">
                    <li>Install PHPMailer: <code>composer require phpmailer/phpmailer</code></li>
                    <li>Uncomment the PHPMailer section in the code</li>
                    <li>Configure your email credentials</li>
                </ol>
            </p>
        </div>
        
        <div class="links">
            <a href="login.html"><i class="fas fa-arrow-left"></i> Back to Login</a>
        </div>
    </div>
</body>
</html>