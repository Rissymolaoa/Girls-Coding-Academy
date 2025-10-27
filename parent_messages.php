<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

include("db.php");

$parent_user_id = $_SESSION['user_id'];

// Fetch parent details for sidebar
$parent_details_sql = "SELECT u.firstName, u.lastName, p.photo FROM users u LEFT JOIN parents p ON u.user_id = p.user_id WHERE u.user_id = ?";
$stmt_details = $conn->prepare($parent_details_sql);
$stmt_details->bind_param("i", $parent_user_id);
$stmt_details->execute();
$parent_details_result = $stmt_details->get_result();
$parent_details = $parent_details_result->fetch_assoc();

// Fetch children of this parent (optional for messages)
$children_stmt = $conn->prepare("
    SELECT s.student_id, u.firstName, u.lastName
    FROM students s
    INNER JOIN parent_students ps ON s.student_id = ps.student_id
    INNER JOIN users u ON s.user_id = u.user_id
    WHERE ps.parent_id = (SELECT parent_id FROM parents WHERE user_id = ?)
    ORDER BY u.firstName
");
$children_stmt->bind_param("i", $parent_user_id);
$children_stmt->execute();
$children_result = $children_stmt->get_result();
$children = $children_result->fetch_all(MYSQLI_ASSOC);

// Handle sending message
$success = '';
$error = '';
if (isset($_POST['send_message'])) {
    $student_id = !empty($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $recipient_user_id = intval($_POST['recipient_user_id']);
    $subject = trim($_POST['subject']);
    $body = trim($_POST['body']);

    if (empty($subject) || empty($body)) {
        $error = "Subject and message body are required.";
    } elseif (empty($recipient_user_id)) {
        $error = "Please select a recipient.";
    } else {
        $insert_stmt = $conn->prepare("
            INSERT INTO parent_messages (sender_user_id, recipient_user_id, student_id, subject, body) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $insert_stmt->bind_param("iiiss", $parent_user_id, $recipient_user_id, $student_id, $subject, $body);
        if ($insert_stmt->execute()) {
            $success = "Message sent successfully!";
        } else {
            $error = "Failed to send message. Please try again.";
        }
    }
}

// Fetch teachers linked to this parent's children
$teachers_stmt = $conn->prepare("
    SELECT DISTINCT t.teacher_id, u.firstName, u.lastName, u.user_id
    FROM teachers t
    INNER JOIN course_assignments ca ON ca.teacher_id = t.teacher_id
    INNER JOIN batches b ON b.batch_id = ca.batch_id
    INNER JOIN course_enrollments ce ON ce.batch_id = b.batch_id
    INNER JOIN students s ON s.student_id = ce.student_id
    INNER JOIN users u ON u.user_id = t.user_id
    INNER JOIN parent_students ps ON ps.student_id = s.student_id
    WHERE ps.parent_id = (SELECT parent_id FROM parents WHERE user_id = ?)
    ORDER BY u.firstName
");
$teachers_stmt->bind_param("i", $parent_user_id);
$teachers_stmt->execute();
$teachers_result = $teachers_stmt->get_result();
$teachers = $teachers_result->fetch_all(MYSQLI_ASSOC);

// Fetch admin users
$admin_stmt = $conn->prepare("SELECT user_id, firstName, lastName FROM users WHERE role = 'admin' ORDER BY firstName");
$admin_stmt->execute();
$admins_result = $admin_stmt->get_result();
$admins = $admins_result->fetch_all(MYSQLI_ASSOC);

// Combine teachers and admins for recipients
$recipients = array_merge($teachers, $admins);

// Fetch all messages sent or received by this parent (with unread count)
$unread_count = 0;
$messages_stmt = $conn->prepare("
    SELECT m.*, u1.firstName AS sender_fname, u1.lastName AS sender_lname, 
           u2.firstName AS recipient_fname, u2.lastName AS recipient_lname,
           CASE WHEN m.recipient_user_id = ? AND m.is_read = 0 THEN 1 ELSE 0 END as unread
    FROM parent_messages m
    INNER JOIN users u1 ON u1.user_id = m.sender_user_id
    INNER JOIN users u2 ON u2.user_id = m.recipient_user_id
    WHERE m.sender_user_id = ? OR m.recipient_user_id = ?
    ORDER BY m.sent_at DESC
");
$messages_stmt->bind_param("iii", $parent_user_id, $parent_user_id, $parent_user_id);
$messages_stmt->execute();
$messages_result = $messages_stmt->get_result();
$messages = $messages_result->fetch_all(MYSQLI_ASSOC);

foreach ($messages as $msg) {
    if ($msg['unread']) $unread_count++;
}

// Total sent messages
$total_sent = count(array_filter($messages, function($m) use ($parent_user_id) {
    return $m['sender_user_id'] == $parent_user_id;
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Messaging - Parent Dashboard | Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --secondary-color: #8b5cf6;
            --accent-color: #06b6d4;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --bg-light: #f8fafc;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
        }
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #1e293b 0%, #334155 100%);
            color: white;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        .sidebar.collapsed {
            transform: translateX(-260px);
        }
        .sidebar-header {
            padding: 2rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.2);
            margin-bottom: 1rem;
            object-fit: cover;
        }
        .sidebar h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.8);
            padding: 0.875rem 1.5rem;
            text-decoration: none;
            border-radius: 0 20px 20px 0;
            margin: 0.25rem 0;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(99, 102, 241, 0.2);
            color: var(--primary-color);
            transform: translateX(5px);
        }
        .sidebar .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            font-size: 1.1rem;
        }
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }
        .main-content.expanded {
            margin-left: 0;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .summary-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.2s ease;
        }
        .summary-card:hover {
            transform: translateY(-4px);
        }
        .summary-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .summary-card.unread .summary-icon { color: var(--warning-color); }
        .summary-card.sent .summary-icon { color: var(--success-color); }
        .summary-card.total .summary-icon { color: var(--primary-color); }
        .summary-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        .summary-label {
            color: var(--text-muted);
            font-size: 0.95rem;
        }
        .compose-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .form-modern label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            display: block;
        }
        .form-modern .form-control {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 0.75rem 1rem;
            transition: border-color 0.2s ease;
        }
        .form-modern .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }
        .btn-send {
            background: linear-gradient(135deg, var(--success-color), #059669);
            border: none;
            border-radius: 8px;
            color: white;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-send:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .history-section {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .history-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem;
        }
        .history-body {
            padding: 1.5rem;
            max-height: 500px;
            overflow-y: auto;
        }
        .message-item {
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .message-item:last-child {
            border-bottom: none;
        }
        .message-meta {
            flex-grow: 1;
        }
        .message-title {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }
        .message-from-to {
            font-size: 0.875rem;
            color: var(--text-muted);
        }
        .message-date {
            font-size: 0.875rem;
            color: var(--text-muted);
        }
        .message-status {
            font-size: 0.75rem;
        }
        .status-read { color: var(--success-color); }
        .status-unread { color: var(--warning-color); }
        .alert-modern {
            border-radius: 8px;
            border: none;
        }
        .toggle-sidebar {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-dark);
            z-index: 1001;
            position: fixed;
            top: 1rem;
            left: 1rem;
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-260px); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .page-header h1 { font-size: 1.5rem; }
            .summary-grid { grid-template-columns: 1fr; }
            .message-item { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
        }
        @media (max-width: 768px) {
            .toggle-sidebar { display: block; }
        }
    </style>
</head>
<body>
    <button class="toggle-sidebar" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="<?= $parent_details['photo'] ?? 'default-parent-avatar.png' ?>" alt="Parent Avatar" onerror="this.src='default-avatar.png'">
            <h3><?= htmlspecialchars($parent_details['firstName'] ?? 'Parent') ?></h3>
        </div>
        <ul class="nav flex-column p-0 m-0">
            <li class="nav-item">
                <a href="parents_dashboard.php" class="nav-link"><i class="bi bi-house-door"></i> Dashboard</a>
            </li>
            <li class="nav-item">
                <a href="children.php" class="nav-link"><i class="bi bi-people"></i> My Children</a>
            </li>
            <li class="nav-item">
                <a href="parent_view_attendance.php" class="nav-link"><i class="bi bi-card-checklist"></i> Attendance</a>
            </li>
            <li class="nav-item">
                <a href="parent_view_performance.php" class="nav-link"><i class="bi bi-graph-up"></i> Performance</a>
            </li>
            <li class="nav-item">
                <a href="parent_view_materials.php" class="nav-link"><i class="bi bi-folder"></i> Materials</a>
            </li>
            <li class="nav-item">
                <a href="parent_messages.php" class="nav-link active"><i class="bi bi-envelope"></i> Messages</a>
            </li>
            <li class="nav-item">
                <a href="parents_chatting.php" class="nav-link"><i class="bi bi-chat"></i> Group Chat</a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content" id="main-content">
        <header class="page-header">
            <div>
                <h1>Secure Messaging</h1>
                <p>Communicate with teachers and administrators professionally. Select a child for student-specific inquiries, or send a general message.</p>
            </div>
        </header>

        <!-- Summary Cards -->
        <section class="summary-section">
            <div class="summary-grid">
                <div class="summary-card unread">
                    <div class="summary-icon"><i class="bi bi-envelope-open"></i></div>
                    <div class="summary-value"><?= $unread_count ?></div>
                    <p class="summary-label">Unread Messages</p>
                </div>
                <div class="summary-card sent">
                    <div class="summary-icon"><i class="bi bi-send-check"></i></div>
                    <div class="summary-value"><?= $total_sent ?></div>
                    <p class="summary-label">Messages Sent</p>
                </div>
                <div class="summary-card total">
                    <div class="summary-icon"><i class="bi bi-chat-dots"></i></div>
                    <div class="summary-value"><?= count($messages) ?></div>
                    <p class="summary-label">Total Conversations</p>
                </div>
            </div>
        </section>

        <?php if ($error): ?>
            <div class="alert alert-modern alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-modern alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Compose Message -->
        <section class="compose-section">
            <h3 class="mb-3"><i class="bi bi-pencil-square"></i> Compose New Message</h3>
            <form method="POST" class="form-modern">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label>Related to Child (Optional)</label>
                        <select class="form-select" name="student_id">
                            <option value="">General Message (No Student)</option>
                            <?php foreach($children as $child): ?>
                                <option value="<?= $child['student_id'] ?>"><?= htmlspecialchars($child['firstName'].' '.$child['lastName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Leave blank for general inquiries.</small>
                    </div>
                    <div class="col-md-6">
                        <label>Recipient *</label>
                        <select class="form-select" name="recipient_user_id" required>
                            <option value="">Select Recipient</option>
                            <?php foreach($recipients as $recipient): ?>
                                <option value="<?= $recipient['user_id'] ?>">
                                    <?= htmlspecialchars($recipient['firstName'].' '.$recipient['lastName']) ?> 
                                    (<?= strpos($recipient['firstName'], 'Teacher') !== false ? 'Teacher' : 'Admin' ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label>Subject *</label>
                        <input type="text" class="form-control" name="subject" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" required maxlength="255">
                    </div>
                    <div class="col-md-12">
                        <label>Message *</label>
                        <textarea class="form-control" name="body" rows="5" required><?= htmlspecialchars($_POST['body'] ?? '') ?></textarea>
                        <small class="text-muted">Keep it professional and concise.</small>
                    </div>
                    <div class="col-md-12 text-end">
                        <button type="submit" name="send_message" class="btn btn-send">
                            <i class="bi bi-send"></i> Send Message
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <!-- Message History -->
        <section class="history-section">
            <div class="history-header">
                <h5><i class="bi bi-clock-history"></i> Message History</h5>
            </div>
            <div class="history-body">
                <?php if(count($messages) > 0): ?>
                    <?php foreach($messages as $msg): 
                        $is_sent = $msg['sender_user_id'] == $parent_user_id;
                        $student_name = ''; // Fetch if needed
                        if ($msg['student_id'] > 0) {
                            $student_query = $conn->prepare("SELECT CONCAT(firstName, ' ', lastName) as name FROM users WHERE user_id = (SELECT user_id FROM students WHERE student_id = ?)");
                            $student_query->bind_param("i", $msg['student_id']);
                            $student_query->execute();
                            $student_result = $student_query->get_result()->fetch_assoc();
                            $student_name = $student_result ? ' (Student: ' . $student_result['name'] . ')' : '';
                        } elseif ($msg['student_id'] == 0) {
                            $student_name = ' (General)';
                        }
                    ?>
                        <div class="message-item <?= $is_sent ? 'bg-light' : '' ?>">
                            <div class="message-meta">
                                <div class="message-title"><?= htmlspecialchars($msg['subject']) ?></div>
                                <div class="message-from-to">
                                    <?= $is_sent ? 'To: ' : 'From: ' ?>
                                    <?= htmlspecialchars($msg['sender_fname'].' '.$msg['sender_lname']) ?> → 
                                    <?= htmlspecialchars($msg['recipient_fname'].' '.$msg['recipient_lname']) ?>
                                    <?= $student_name ?>
                                </div>
                                <small class="message-date"><?= date("M d, Y H:i", strtotime($msg['sent_at'])) ?></small>
                            </div>
                            <div class="text-end">
                                <span class="message-status <?= $msg['is_read'] ? 'status-read' : 'status-unread' ?>">
                                    <?= $msg['is_read'] ? 'Read' : 'Unread' ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center py-4">No messages yet. Start a conversation above!</p>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            sidebar.classList.toggle('show');
            mainContent.classList.toggle('expanded');
        }

        // Close sidebar on outside click (mobile)
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.toggle-sidebar');
            if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target) && window.innerWidth <= 768) {
                sidebar.classList.remove('show');
                document.getElementById('main-content').classList.remove('expanded');
            }
        });
    </script>
</body>
</html>