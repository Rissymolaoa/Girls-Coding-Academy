<?php
// help_desk.php
session_start();
require_once 'config.php'; // Database connection file

// Check if user is logged in to personalize content
$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'guest';
$user_name = 'Guest';
if ($user_id) {
    try {
        $stmt = $pdo->prepare("SELECT firstName, lastName FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if ($user) {
            $user_name = $user['firstName'] . ' ' . $user['lastName'];
        }
    } catch (PDOException $e) {
        $error = "Error fetching user data: " . $e->getMessage();
    }
}

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_inquiry'])) {
    try {
        $subject = $_POST['subject'] ?? '';
        $message = $_POST['message'] ?? '';
        $priority = $_POST['priority'] ?? 'medium';

        if (empty($subject) || empty($message)) {
            throw new Exception("Subject and message are required.");
        }

        // Insert inquiry into a hypothetical `support_inquiries` table
        // Note: You'll need to create this table in your database
        $stmt = $pdo->prepare("INSERT INTO support_inquiries (user_id, role, subject, message, priority, created_at) 
                               VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id ?: null, $role, $subject, $message, $priority]);

        $success = "Inquiry submitted successfully. Our team will respond soon.";
    } catch (Exception $e) {
        $error = "Error submitting inquiry: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Desk - Girls Coding Academy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --secondary-blue: #3b82f6;
            --accent-blue: #1d4ed8;
            --accent-hover: #1e40af;
            --white: #ffffff;
            --light-gray: #f8fafc;
            --border-light: #e2e8f0;
            --shadow-light: 0 4px 20px rgba(0,0,0,0.1);
            --shadow-hover: 0 8px 30px rgba(0,0,0,0.15);
            --error-red: #e74c3c;
            --text-dark: #1e293b;
            --text-light: #64748b;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, var(--primary-blue) 0%, #1e40af 100%);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            color: var(--text-dark);
        }

        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: var(--white);
            border-radius: 20px;
            box-shadow: var(--shadow-light);
            transition: box-shadow 0.3s ease;
        }

        .container:hover {
            box-shadow: var(--shadow-hover);
        }

        h2 {
            color: var(--text-dark);
            font-size: 1.8rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .section-title {
            color: var(--text-dark);
            font-size: 1.5rem;
            font-weight: 500;
            margin: 1.5rem 0 1rem;
        }

        form {
            display: grid;
            gap: 1.25rem;
        }

        label {
            font-weight: 500;
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        input, textarea, select {
            padding: 0.875rem;
            border: 2px solid var(--border-light);
            border-radius: 12px;
            width: 100%;
            font-size: 1rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            background: var(--white);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        input:focus, textarea:focus, select:focus {
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
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        button:hover::before {
            left: 100%;
        }

        button:hover {
            background: linear-gradient(145deg, var(--accent-hover) 0%, var(--primary-blue) 100%);
            transform: translateY(-1px);
            box-shadow: var(--shadow-light);
        }

        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .faq-list {
            list-style: none;
            padding: 0;
        }

        .faq-item {
            margin-bottom: 1rem;
        }

        .faq-item h4 {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .faq-item p {
            font-size: 0.9rem;
            color: var(--text-light);
            margin: 0;
        }

        footer {
            background: rgba(30, 58, 138, 0.95);
            backdrop-filter: blur(10px);
            color: var(--white);
            text-align: center;
            padding: 1rem;
            margin-top: auto;
            border-top: 1px solid var(--border-light);
            font-size: 0.85rem;
        }

        @media (max-width: 768px) {
            .container {
                width: 95%;
                margin: 1rem auto;
                padding: 1.5rem;
            }

            h2 {
                font-size: 1.5rem;
            }

            .section-title {
                font-size: 1.3rem;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 1rem;
            }

            button {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'login_navigation.php'; ?>

    <div class="container">
        <h2>Help Desk</h2>
        <p>Welcome, <?php echo htmlspecialchars($user_name); ?>. How can we assist you with your construction-related course inquiries?</p>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php elseif (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Contact Form -->
        <div class="section-title">Submit an Inquiry</div>
        <form method="POST">
            <div>
                <label for="subject">Subject</label>
                <input type="text" id="subject" name="subject" required>
            </div>
            <div>
                <label for="priority">Priority</label>
                <select id="priority" name="priority">
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                </select>
            </div>
            <div>
                <label for="message">Message</label>
                <textarea id="message" name="message" required></textarea>
            </div>
            <button type="submit" name="submit_inquiry">Submit Inquiry</button>
        </form>

        <!-- FAQ Section -->
        <div class="section-title">Frequently Asked Questions</div>
        <ul class="faq-list">
            <li class="faq-item">
                <h4>How do I access course materials for construction coding?</h4>
                <p>Log in as a student or teacher to access materials via the course dashboard. Contact us if you encounter issues.</p>
            </li>
            <li class="faq-item">
                <h4>Can parents monitor their child's progress in construction courses?</h4>
                <p>Yes, parents can view progress reports through the parent dashboard after linking their account to their child.</p>
            </li>
            <li class="faq-item">
                <h4>How do I upload construction project plans as a teacher?</h4>
                <p>Use the "Upload Construction Material" option on your profile page to share plans or resources.</p>
            </li>
            <li class="faq-item">
                <h4>What should I do if I forget my password?</h4>
                <p>Use the "Forgot Password" link on the login page to reset your password via email.</p>
            </li>
        </ul>
    </div>

    <footer>
        &copy; 2025 Girls Coding Academy. All rights reserved.
    </footer>
</body>
</html>