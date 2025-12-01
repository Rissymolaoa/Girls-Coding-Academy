<?php
session_start();
include 'db.php';

$message = '';
$message_type = '';
$token_valid = false;
$token = '';

// Check if token is provided
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verify token and check expiry
    $stmt = $conn->prepare("SELECT user_id, username, email, reset_token_expiry FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Check if token has expired
        if (strtotime($user['reset_token_expiry']) > time()) {
            $token_valid = true;
        } else {
            $message = 'This password reset link has expired. Please request a new one.';
            $message_type = 'error';
        }
    } else {
        $message = 'Invalid password reset link.';
        $message_type = 'error';
    }
    $stmt->close();
}

// Handle password reset submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $token = $_POST['token'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($new_password) || empty($confirm_password)) {
        $message = 'Please fill in all fields.';
        $message_type = 'error';
        $token_valid = true;
    } elseif ($new_password !== $confirm_password) {
        $message = 'Passwords do not match.';
        $message_type = 'error';
        $token_valid = true;
    } elseif (strlen($new_password) < 6) {
        $message = 'Password must be at least 6 characters long.';
        $message_type = 'error';
        $token_valid = true;
    } else {
        // Verify token again
        $stmt = $conn->prepare("SELECT user_id, reset_token_expiry FROM users WHERE reset_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            if (strtotime($user['reset_token_expiry']) > time()) {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password and clear reset token
                $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = ?");
                $update_stmt->bind_param("si", $hashed_password, $user['user_id']);
                
                if ($update_stmt->execute()) {
                    $message = 'Your password has been successfully reset! You can now login with your new password.';
                    $message_type = 'success';
                    $token_valid = false;
                } else {
                    $message = 'An error occurred. Please try again.';
                    $message_type = 'error';
                    $token_valid = true;
                }
                $update_stmt->close();
            } else {
                $message = 'This password reset link has expired. Please request a new one.';
                $message_type = 'error';
            }
        } else {
            $message = 'Invalid password reset link.';
            $message_type = 'error';
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
    <title>Reset Password - Girls Coding Academy</title>
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
        }
        
        .container {
            background: var(--white);
            max-width: 500px;
            width: 90%;
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
            text-align: center;
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
        
        .password-field {
            position: relative;
        }
        
        input {
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: 12px;
            font-size: 1rem;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        input:focus {
            outline: none;
            border-color: var(--secondary-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #64748b;
            transition: color 0.3s ease;
        }
        
        .toggle-password:hover {
            color: var(--secondary-blue);
        }
        
        .password-requirements {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.5rem;
        }
        
        .password-requirements ul {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
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
            <i class="fas fa-lock"></i>
        </div>
        
        <h1>Reset Your Password</h1>
        <p class="subtitle">Enter your new password below.</p>
        
        <?php if (!empty($message)): ?>
            <div class="message <?= $message_type ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <?php if ($token_valid): ?>
        <form method="POST" action="">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            
            <div>
                <label for="new_password">New Password</label>
                <div class="password-field">
                    <input type="password" id="new_password" name="new_password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('new_password')"></i>
                </div>
                <div class="password-requirements">
                    Password must be at least 6 characters long
                </div>
            </div>
            
            <div>
                <label for="confirm_password">Confirm Password</label>
                <div class="password-field">
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password')"></i>
                </div>
            </div>
            
            <button type="submit" name="reset_password">
                <i class="fas fa-check mr-2"></i>Reset Password
            </button>
        </form>
        <?php endif; ?>
        
        <div class="links">
            <?php if ($message_type === 'success'): ?>
                <a href="login.html"><i class="fas fa-sign-in-alt"></i> Login Now</a>
            <?php else: ?>
                <a href="login.html"><i class="fas fa-arrow-left"></i> Back to Login</a>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling;
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>