<?php
session_start();
require_once 'config.php';

$user_id = $_SESSION['user_id'] ?? null;
$role    = $_SESSION['role'] ?? 'guest';
$user_name = 'Guest';

if ($user_id) {
    $stmt = $pdo->prepare("SELECT firstName, lastName FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if ($user) {
        $user_name = htmlspecialchars($user['firstName'] . ' ' . $user['lastName']);
    }
}

// Handle form submission
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_inquiry'])) {
    $subject  = trim($_POST['subject'] ?? '');
    $message  = trim($_POST['message'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';

    if (empty($subject) || empty($message)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            // Optional: Create this table if not exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS support_tickets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                name VARCHAR(100),
                email VARCHAR(100),
                subject VARCHAR(255),
                message TEXT,
                priority ENUM('low','medium','high','urgent'),
                status ENUM('open','in-progress','resolved') DEFAULT 'open',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $stmt = $pdo->prepare("INSERT INTO support_tickets 
                (user_id, name, email, subject, message, priority) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id,
                $user_name,
                $_SESSION['email'] ?? 'guest@girlscoding.com',
                $subject,
                $message,
                $priority
            ]);
            $success = "Thank you! Your ticket has been created. We'll reply within <strong>" . 
                      ($priority === 'urgent' ? '1 hour' : ($priority === 'high' ? '4 hours' : '24 hours')) . 
                      "</strong>.";
        } catch (Exception $e) {
            $error = "Sorry, something went wrong. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Desk • Girls Coding Academy</title>
    <meta name="description" content="Get fast, friendly support from our dedicated team. We're here to help you succeed in your coding journey.">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        :root {
            --primary: #8b5cf6;
            --primary-dark: #7c3aed;
            --secondary: #ec4899;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray: #64748b;
            --border: #e2e8f0;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 50%, #ec4899 100%);
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
        }
        .container { max-width: 1100px; margin: 0 auto; padding: 2rem 1rem; }

        /* Hero */
        .hero {
            text-align: center;
            padding: 4rem 1rem 3rem;
            color: white;
        }
        .hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
        }
        .hero p {
            font-size: 1.3rem;
            opacity: 0.95;
            max-width: 700px;
            margin: 0 auto;
        }

        /* Support Grid */
        .support-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin: 3rem 0;
        }
        .support-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .support-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        .support-card i {
            font-size: 3rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .support-card h3 { font-size: 1.4rem; margin: 1rem 0 0.5rem; }
        .support-card p { color: var(--gray); font-size: 0.95rem; }

        /* Ticket Form */
        .ticket-form {
            background: white;
            padding: 2.5rem;
            border-radius: 24px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
            margin: 3rem 0;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }

        .form-group { margin-bottom: 1.5rem; }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }
        input, textarea, select {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-family: inherit;
            font-size: 1rem;
            transition: all 0.3s;
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15);
        }
        textarea { min-height: 140px; resize: vertical; }

        .priority-badge {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .low { background: #dcfce7; color: #166534; }
        .medium { background: #fef3c7; color: #92400e; }
        .high { background: #fee2e2; color: #991b1b; }
        .urgent { background: #f3e8ff; color: #6b21a8; animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:0.8; } }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-top: 1rem;
            transition: all 0.3s;
        }
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(139,92,246,0.4);
        }

        /* Alert */
        .alert {
            padding: 1.2rem;
            border-radius: 16px;
            margin: 2rem 0;
            text-align: center;
            font-weight: 500;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* FAQ Accordion */
        .faq-section { margin: 4rem 0; }
        .faq-item {
            background: white;
            margin-bottom: 1rem;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .faq-question {
            padding: 1.5rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            background: #f8fafc;
        }
        .faq-question:hover { background: #f1f5f9; }
        .faq-answer {
            padding: 0 1.5rem;
            max-height: 0;
            overflow: hidden;
            transition: all 0.4s ease;
            background: white;
        }
        .faq-answer.open {
            padding: 1.5rem;
            max-height: 300px;
        }
        .faq-answer p { color: var(--gray); line-height: 1.7; }

        /* Support Team */
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin: 4rem 0;
        }
        .team-member {
            text-align: center;
        }
        .team-member img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--primary);
            margin-bottom: 1rem;
        }
        .team-member h4 { margin: 0.5rem 0; }
        .team-member p { font-size: 0.9rem; color: var(--gray); }

        footer {
            text-align: center;
            padding: 4rem 1rem 2rem;
            color: white;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>

    <?php include 'login_navigation.php'; ?>

    <div class="hero">
        <h1>Help Desk</h1>
        <p>We’re here 24/7 to support your learning journey. No question is too small.</p>
    </div>

    <div class="container">

        <div class="support-grid">
            <div class="support-card">
                <i class="fas fa-headset"></i>
                <h3>Response Time</h3>
                <p><strong>Urgent:</strong> Within 1 hour<br>
                   <strong>High:</strong> Within 4 hours<br>
                   <strong>Normal:</strong> Within 24 hours</p>
            </div>
            <div class="support-card">
                <i class="fas fa-envelope"></i>
                <h3>Email Us</h3>
                <p>support@girlscodingacademy.com</p>
            </div>
            <div class="support-card">
                <i class="fas fa-phone"></i>
                <h3>Call or WhatsApp</h3>
                <p>+266 5837 5096<br>Mon–Fri, 8AM–5PM</p>
            </div>
        </div>

        <!-- Submit Ticket -->
        <div class="ticket-form">
            <h2 style="text-align:center; margin-bottom:2rem; color:var(--dark);">
                <i class="fas fa-ticket-alt"></i> Submit a Support Ticket
            </h2>
            <p style="text-align:center; color:var(--gray); margin-bottom:2rem;">
                Welcome<?php echo $user_name !== 'Guest' ? ", <strong>$user_name</strong>" : ''; ?>! 
                Tell us how we can help you today.
            </p>

            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <?php elseif ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" name="subject" required placeholder="e.g. Can't access my course materials">
                    </div>
                    <div class="form-group">
                        <label>Priority <span id="priority-text" class="priority-badge medium">Medium</span></label>
                        <select name="priority" id="priority" onchange="updatePriority()">
                            <option value="low">Low – No rush</option>
                            <option value="medium" selected>Medium – Normal issue</option>
                            <option value="high">High – Affects learning</option>
                            <option value="urgent">Urgent – Can't continue</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Describe your issue</label>
                    <textarea name="message" required placeholder="Please explain what you're experiencing and any error messages..."></textarea>
                </div>
                <button type="submit" name="submit_inquiry" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Submit Ticket
                </button>
            </form>
        </div>

        <!-- FAQ -->
        <div class="faq-section">
            <h2 style="text-align:center; margin-bottom:2rem;">Frequently Asked Questions</h2>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    How do I access my course materials? <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>After logging in, go to "My Courses" → select your batch → you'll see all materials, activities, and submissions. Materials are uploaded weekly by your instructor.</p>
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    Can parents see their child's progress? <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>Yes! Parents can link their account to their child and view grades, attendance, and messages from teachers via the Parent Portal.</p>
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    I forgot my password — what do I do? <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>Click "Forgot Password" on the login page. We'll send a reset link to your registered email.</p>
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    When do new batches start? <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>New batches start every month. Check the <a href="prospectus.php">Prospectus</a> for upcoming start dates and enrollment deadlines.</p>
                </div>
            </div>
        </div>

        <!-- Support Team -->
        <h2 style="text-align:center; margin: 4rem 0 2rem;">Meet Your Support Team</h2>
        <div class="team-grid">
            <div class="team-member">
                <img src="teacher3.png" alt="Rebecca">
                <h4>Mary Mostjaba</h4>
                <p>Student Success Manager</p>
            </div>
            <div class="team-member">
                <img src="admin.png" alt="Thato">
                <h4>Thato Leqele</h4>
                <p>Technical Support Lead</p>
            </div>
            <div class="team-member">
                <img src="admin.png" alt="Ntina">
                <h4>Sbusiso Dlamini</h4>
                <p>Admissions & Enrollment</p>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Girls Coding Academy • Lesotho • Empowering Future Women in Tech</p>
        <p style="margin-top:1rem; opacity:0.9;">
            Need immediate help? WhatsApp us at <strong>+266 6837 9878</strong>
        </p>
    </footer>

    <script>
        function toggleFAQ(el) {
            const answer = el.nextElementSibling;
            const icon = el.querySelector('i');
            answer.classList.toggle('open');
            icon.style.transform = answer.classList.contains('open') ? 'rotate(180deg)' : 'rotate(0)';
        }

        function updatePriority() {
            const select = document.getElementById('priority');
            const badge = document.getElementById('priority-text');
            const value = select.value;
            badge.textContent = value.charAt(0).toUpperCase() + value.slice(1);
            badge.className = 'priority-badge ' + value;
        }
    </script>
</body>
</html>