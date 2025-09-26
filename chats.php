<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Africa/Johannesburg');

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include("db.php");

$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? null;

// Redirect if not logged in or invalid role
if (!$user_id || !in_array($role, ['student', 'teacher', 'parent', 'admin'])) {
    session_destroy();
    header("Location: login.html?redirected=true");
    exit();
}

// Fetch user info with photo
$userQuery = $conn->prepare("
    SELECT u.username,
           COALESCE(s.photo, t.photo, p.photo, '') AS photo
    FROM users u
    LEFT JOIN students s ON u.user_id = s.user_id AND u.role = 'student'
    LEFT JOIN teachers t ON u.user_id = t.user_id AND u.role = 'teacher'
    LEFT JOIN parents p ON u.user_id = p.user_id AND u.role = 'parent'
    WHERE u.user_id = ?
");
if (!$userQuery) {
    error_log("User Query Error: " . $conn->error);
    die("Database error. Please contact support.");
}
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$userInfo = $userQuery->get_result()->fetch_assoc();
$userQuery->close();

// Handle sending a new message
if (isset($_POST['send_message'], $_POST['receiver_id'], $_POST['receiver_role'], $_POST['message_text'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $receiver_role = trim($_POST['receiver_role']);
    $message_text = trim($_POST['message_text']);

    if (!empty($message_text)) {
        // Check if chat is active
        $chatStatusQuery = $conn->prepare("
            SELECT is_active
            FROM chat_status
            WHERE (controller_role = 'teacher' AND controller_id = ? AND target_id = ? AND target_role = ?)
               OR (controller_role = 'admin' AND target_id = ? AND target_role = ?)
        ");
        if ($role === 'teacher' || $role === 'admin') {
            $chatStatusQuery->bind_param("iisis", $receiver_id, $user_id, $role, $user_id, $role);
        } else {
            $chatStatusQuery->bind_param("iisis", $user_id, $receiver_id, $receiver_role, $receiver_id, $receiver_role);
        }
        $chatStatusQuery->execute();
        $chatStatusResult = $chatStatusQuery->get_result()->fetch_assoc();
        $chatStatusQuery->close();

        if (!$chatStatusResult || $chatStatusResult['is_active']) {
            $sendQuery = $conn->prepare("
                INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, subject, message_text, sent_at, is_read)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), 0)
            ");
            $subject = ''; // Default empty subject
            $sendQuery->bind_param("isiss", $user_id, $role, $receiver_id, $receiver_role, $subject, $message_text);
            $sendQuery->execute();
            $sendQuery->close();
            $_SESSION['message_success'] = "Message sent successfully.";
        } else {
            $_SESSION['message_error'] = "Chat is disabled by the teacher or admin.";
        }
    } else {
        $_SESSION['message_error'] = "Message cannot be empty.";
    }
    header("Location: chats.php?chat_with=$receiver_id&chat_role=" . urlencode($receiver_role));
    exit();
}

// Handle message deletion (database for teacher/admin, client-side for others)
if (isset($_POST['delete_message']) && is_numeric($_POST['delete_message'])) {
    $message_id = (int)$_POST['delete_message'];
    if ($role === 'teacher' || $role === 'admin') {
        $deleteQuery = $conn->prepare("DELETE FROM messages WHERE message_id = ? AND sender_id = ? AND sender_role = ?");
        $deleteQuery->bind_param("iis", $message_id, $user_id, $role);
        $deleteQuery->execute();
        $deleteQuery->close();
        $_SESSION['message_success'] = "Message deleted successfully.";
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// Handle message editing (teacher/admin only)
if (isset($_POST['edit_message']) && is_numeric($_POST['edit_message'])) {
    $message_id = (int)$_POST['edit_message'];
    $message_text = trim($_POST['message_text']);
    if (!empty($message_text) && ($role === 'teacher' || $role === 'admin')) {
        $editQuery = $conn->prepare("UPDATE messages SET message_text = ? WHERE message_id = ? AND sender_id = ? AND sender_role = ?");
        $editQuery->bind_param("siis", $message_text, $message_id, $user_id, $role);
        $editQuery->execute();
        $editQuery->close();
        $_SESSION['message_success'] = "Message updated successfully.";
    } else {
        $_SESSION['message_error'] = "Message cannot be empty.";
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// Handle chat activation/deactivation (teacher/admin)
if (isset($_POST['toggle_chat']) && is_numeric($_POST['target_id']) && in_array($role, ['teacher', 'admin'])) {
    $target_id = (int)$_POST['target_id'];
    $target_role = trim($_POST['target_role']);
    $is_active = isset($_POST['activate']) ? 1 : 0;

    $toggleQuery = $conn->prepare("
        INSERT INTO chat_status (controller_id, controller_role, target_id, target_role, is_active)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE is_active = ?
    ");
    $toggleQuery->bind_param("iisiii", $user_id, $role, $target_id, $target_role, $is_active, $is_active);
    $toggleQuery->execute();
    $toggleQuery->close();
    $_SESSION['message_success'] = "Chat " . ($is_active ? "enabled" : "disabled") . " successfully.";
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// Fetch allowed recipients
$allowedReceivers = [];
if ($role === 'student') {
    $query = $conn->prepare("
        SELECT u.user_id, u.username, u.role, COALESCE(t.photo, '') AS photo
        FROM users u
        JOIN teachers t ON u.user_id = t.user_id
        JOIN course_assignments ca ON t.teacher_id = ca.teacher_id
        JOIN course_enrollments ce ON ca.batch_id = ce.batch_id
        JOIN students s ON ce.student_id = s.student_id
        WHERE s.user_id = ? AND u.role = 'teacher'
    ");
    $query->bind_param("i", $user_id);
} elseif ($role === 'teacher') {
    $query = $conn->prepare("
        SELECT u.user_id, u.username, u.role, COALESCE(s.photo, '') AS photo
        FROM users u
        JOIN students s ON u.user_id = s.user_id
        JOIN course_enrollments ce ON s.student_id = ce.student_id
        JOIN course_assignments ca ON ce.batch_id = ca.batch_id
        JOIN teachers t ON ca.teacher_id = t.teacher_id
        WHERE t.user_id = ? AND u.role = 'student'
        UNION
        SELECT u.user_id, u.username, u.role, COALESCE(p.photo, '') AS photo
        FROM users u
        JOIN parent_students ps ON u.user_id = ps.parent_id
        JOIN students s ON ps.student_id = s.student_id
        JOIN course_enrollments ce ON s.student_id = ce.student_id
        JOIN course_assignments ca ON ce.batch_id = ca.batch_id
        JOIN teachers t ON ca.teacher_id = t.teacher_id
        WHERE t.user_id = ? AND u.role = 'parent'
        UNION
        SELECT u.user_id, u.username, u.role, '' AS photo
        FROM users u
        WHERE u.role = 'admin'
    ");
    $query->bind_param("ii", $user_id, $user_id);
} elseif ($role === 'parent') {
    $query = $conn->prepare("
        SELECT u.user_id, u.username, u.role, COALESCE(s.photo, '') AS photo
        FROM users u
        JOIN students s ON u.user_id = s.user_id
        JOIN parent_students ps ON s.student_id = ps.student_id
        JOIN parents p ON ps.parent_id = p.user_id
        WHERE p.user_id = ? AND u.role = 'student'
        UNION
        SELECT u.user_id, u.username, u.role, COALESCE(t.photo, '') AS photo
        FROM users u
        JOIN teachers t ON u.user_id = t.user_id
        JOIN course_assignments ca ON t.teacher_id = ca.teacher_id
        JOIN course_enrollments ce ON ca.batch_id = ce.batch_id
        JOIN students s ON ce.student_id = s.student_id
        JOIN parent_students ps ON s.student_id = ps.student_id
        JOIN parents p ON ps.parent_id = p.user_id
        WHERE p.user_id = ? AND u.role = 'teacher'
    ");
    $query->bind_param("ii", $user_id, $user_id);
} elseif ($role === 'admin') {
    $query = $conn->prepare("
        SELECT u.user_id, u.username, u.role, COALESCE(s.photo, t.photo, p.photo, '') AS photo
        FROM users u
        LEFT JOIN students s ON u.user_id = s.user_id AND u.role = 'student'
        LEFT JOIN teachers t ON u.user_id = t.user_id AND u.role = 'teacher'
        LEFT JOIN parents p ON u.user_id = p.user_id AND u.role = 'parent'
        WHERE u.role IN ('student', 'teacher', 'parent')
    ");
}
if (!$query) {
    error_log("Receivers Query Error: " . $conn->error);
    die("Database error. Please contact support.");
}
$query->execute();
$allowedReceivers = $query->get_result()->fetch_all(MYSQLI_ASSOC);
$query->close();

// Fetch chat messages
$chatMessages = [];
$chat_with_id = isset($_GET['chat_with']) && is_numeric($_GET['chat_with']) ? (int)$_GET['chat_with'] : null;
$chat_with_role = isset($_GET['chat_role']) ? trim($_GET['chat_role']) : null;

if ($chat_with_id && $chat_with_role) {
    $messagesQuery = $conn->prepare("
        SELECT m.message_id, m.sender_id, m.sender_role, m.receiver_id, m.receiver_role, m.subject, m.message_text, m.sent_at, m.is_read,
               u1.username AS sender_name,
               COALESCE(s1.photo, t1.photo, p1.photo, '') AS sender_photo,
               u2.username AS receiver_name,
               COALESCE(s2.photo, t2.photo, p2.photo, '') AS receiver_photo
        FROM messages m
        JOIN users u1 ON m.sender_id = u1.user_id
        JOIN users u2 ON m.receiver_id = u2.user_id
        LEFT JOIN students s1 ON m.sender_id = s1.user_id AND m.sender_role = 'student'
        LEFT JOIN teachers t1 ON m.sender_id = t1.user_id AND m.sender_role = 'teacher'
        LEFT JOIN parents p1 ON m.sender_id = p1.user_id AND m.sender_role = 'parent'
        LEFT JOIN students s2 ON m.receiver_id = s2.user_id AND m.receiver_role = 'student'
        LEFT JOIN teachers t2 ON m.receiver_id = t2.user_id AND m.receiver_role = 'teacher'
        LEFT JOIN parents p2 ON m.receiver_id = p2.user_id AND m.receiver_role = 'parent'
        WHERE ((m.sender_id = ? AND m.sender_role = ? AND m.receiver_id = ? AND m.receiver_role = ?)
            OR (m.sender_id = ? AND m.sender_role = ? AND m.receiver_id = ? AND m.receiver_role = ?))
        AND EXISTS (
            SELECT 1 FROM chat_status cs
            WHERE (cs.controller_role = 'teacher' AND cs.controller_id = ? AND cs.target_id = ? AND cs.target_role = ? AND cs.is_active = 1)
               OR (cs.controller_role = 'admin' AND cs.target_id = ? AND cs.target_role = ? AND cs.is_active = 1)
               OR NOT EXISTS (
                   SELECT 1 FROM chat_status cs2
                   WHERE (cs2.controller_role = 'teacher' AND cs2.controller_id = ? AND cs2.target_id = ? AND cs2.target_role = ?)
                      OR (cs2.controller_role = 'admin' AND cs2.target_id = ? AND cs2.target_role = ?)
               )
        )
        ORDER BY m.sent_at ASC
    ");
    $messagesQuery->bind_param(
        "isisisisisisisisis",
        $user_id, $role, $chat_with_id, $chat_with_role,
        $chat_with_id, $chat_with_role, $user_id, $role,
        $user_id, $chat_with_id, $chat_with_role,
        $chat_with_id, $chat_with_role,
        $user_id, $chat_with_id, $chat_with_role,
        $chat_with_id, $chat_with_role
    );
    $messagesQuery->execute();
    $chatMessages = $messagesQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    $messagesQuery->close();
}

// Fetch chat status for toggle (for teachers/admins)
$chatStatus = [];
if ($role === 'teacher' || $role === 'admin') {
    $statusQuery = $conn->prepare("
        SELECT target_id, target_role, is_active
        FROM chat_status
        WHERE controller_id = ? AND controller_role = ?
    ");
    $statusQuery->bind_param("is", $user_id, $role);
    $statusQuery->execute();
    $chatStatus = $statusQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    $statusQuery->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chats - Girls Coding Academy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { font-family: Arial, sans-serif; background:#f9f9f9; color:#333; }
.container { display:flex; min-height:90vh; }
.sidebar {
    width:240px; background:#1a1a1a; padding:20px; min-height:100vh; color:#fff;
}
.sidebar h3 { margin-bottom:15px; text-align:center; }
.sidebar a {
    color:#fff; display:flex; align-items:center; gap:10px; padding:10px; text-decoration:none; border-radius:6px;
}
.sidebar a:hover { background:#333; }
.content { flex:1; padding:30px; }
.message { margin-bottom:15px; position:relative; display:flex; align-items:flex-start; }
.message.sent { justify-content:flex-end; }
.message.received { justify-content:flex-start; }
.message-bubble {
    display:inline-block; padding:10px 15px; border-radius:10px; max-width:70%;
    background:#e8f5e9; /* Sent messages */
}
.message.received .message-bubble { background:#f1f1f1; /* Received messages */ }
.avatar, .avatar-initial {
    width:40px; height:40px; border-radius:50%; margin:5px;
}
.avatar { object-fit:cover; }
.avatar-initial {
    background:#5a189a; color:#fff; display:flex; align-items:center; justify-content:center;
    font-weight:bold; font-size:16px; text-transform:uppercase;
}
.message-actions { display:none; position:absolute; top:0; }
.message.sent .message-actions { right:0; }
.message.received .message-actions { left:0; }
.message:hover .message-actions { display:block; }
.message-time { font-size:0.8em; color:#666; margin-top:5px; }
.chat-form { margin-top:20px; }
.toggle-chat { margin-bottom:20px; }
.alert { margin-bottom:20px; }
.message-content { display:flex; flex-direction:column; }
</style>
</head>
<body>
<div class="container">
    <div class="sidebar">
        <div class="avatar-initial"><?php echo htmlspecialchars(strtoupper(substr($userInfo['username'], 0, 1))); ?></div>
        <h3><?php echo htmlspecialchars($userInfo['username']); ?></h3>
        <a href="<?php echo $role === 'student' ? 'student.php' : ($role === 'teacher' ? 'teacher_dashboard.php' : ($role === 'parent' ? 'parent_dashboard.php' : 'admin_dashboard.php')); ?>"><i class="bi bi-house-door"></i> Home</a>
        <?php if ($role === 'student'): ?>
            <a href="student_courses.php"><i class="bi bi-journal-bookmark"></i> My Courses</a>
        <?php endif; ?>
        <a href="messages.php"><i class="bi bi-envelope"></i> Messages</a>
        <?php if ($role === 'student'): ?>
            <a href="attendance.php"><i class="bi bi-card-checklist"></i> Attendance</a>
            <a href="student_profile.php"><i class="bi bi-person-circle"></i> My Profile</a>
        <?php endif; ?>
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
    <div class="content">
        <h2>Chat Messages</h2>

        <!-- Display Success/Error Messages -->
        <?php if (isset($_SESSION['message_success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['message_success']); unset($_SESSION['message_success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['message_error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['message_error']); unset($_SESSION['message_error']); ?></div>
        <?php endif; ?>

        <!-- Chat Toggle for Teachers/Admins -->
        <?php if ($role === 'teacher' || $role === 'admin'): ?>
            <div class="toggle-chat">
                <h4>Manage Chats</h4>
                <form method="POST">
                    <select name="target_id" required>
                        <option value="">Select User</option>
                        <?php foreach ($allowedReceivers as $receiver): ?>
                            <?php if (($role === 'teacher' && in_array($receiver['role'], ['student', 'parent', 'admin'])) || $role === 'admin'): ?>
                                <option value="<?php echo $receiver['user_id']; ?>" data-role="<?php echo htmlspecialchars($receiver['role']); ?>">
                                    <?php echo htmlspecialchars($receiver['username'] . ' (' . ucfirst($receiver['role']) . ')'); ?>
                                </option>
                            <?php endif; ?>
                        </select>
                        <input type="hidden" name="target_role" id="target_role">
                        <button type="submit" name="toggle_chat" value="activate" class="btn btn-success">Enable Chat</button>
                        <button type="submit" name="toggle_chat" class="btn btn-danger">Disable Chat</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Chat Messages -->
        <?php if (!empty($chatMessages)): ?>
            <?php foreach ($chatMessages as $msg): ?>
                <div class="message <?php echo ($msg['sender_id'] == $user_id && $msg['sender_role'] == $role) ? 'sent' : 'received'; ?>" data-message-id="<?php echo $msg['message_id']; ?>">
                    <?php
                    $avatar = ($msg['sender_id'] == $user_id && $msg['sender_role'] == $role) ? $userInfo['photo'] : $msg['sender_photo'];
                    $initial = $avatar ? '' : strtoupper(substr($msg['sender_name'], 0, 1));
                    ?>
                    <?php if ($msg['sender_id'] != $user_id || $msg['sender_role'] != $role): ?>
                        <div class="avatar-initial"><?php echo htmlspecialchars($initial); ?></div>
                    <?php endif; ?>
                    <div class="message-content">
                        <div class="message-bubble">
                            <?php echo nl2br(htmlspecialchars($msg['message_text'])); ?>
                            <?php if ($msg['subject']): ?>
                                <div><strong>Subject:</strong> <?php echo htmlspecialchars($msg['subject']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="message-time"><?php echo htmlspecialchars($msg['sent_at']); ?></div>
                        <?php if ($msg['sender_id'] == $user_id && $msg['sender_role'] == $role): ?>
                            <div class="message-actions">
                                <?php if ($role === 'teacher' || $role === 'admin'): ?>
                                    <button class="btn btn-sm btn-primary edit-btn" data-message-id="<?php echo $msg['message_id']; ?>" data-text="<?php echo htmlspecialchars($msg['message_text']); ?>" data-subject="<?php echo htmlspecialchars($msg['subject']); ?>">Edit</button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="delete_message" value="<?php echo $msg['message_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-danger client-delete" data-message-id="<?php echo $msg['message_id']; ?>">Delete</button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No messages yet.</p>
        <?php endif; ?>

        <!-- Message Form -->
        <?php if ($chat_with_id && $chat_with_role): ?>
            <div class="chat-form">
                <form method="POST" action="chats.php?chat_with=<?php echo $chat_with_id; ?>&chat_role=<?php echo urlencode($chat_with_role); ?>">
                    <div class="mb-3">
                        <textarea name="message_text" rows="3" class="form-control" placeholder="Type your message" required></textarea>
                    </div>
                    <input type="hidden" name="receiver_id" value="<?php echo $chat_with_id; ?>">
                    <input type="hidden" name="receiver_role" value="<?php echo htmlspecialchars($chat_with_role); ?>">
                    <button type="submit" name="send_message" class="btn btn-primary">Send</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.toggle-chat select').forEach(select => {
    select.addEventListener('change', function() {
        document.getElementById('target_role').value = this.options[this.selectedIndex].getAttribute('data-role');
    });
});

// Client-side deletion for students/parents
document.querySelectorAll('.client-delete').forEach(button => {
    button.addEventListener('click', function() {
        const messageId = this.getAttribute('data-message-id');
        document.querySelector(`.message[data-message-id="${messageId}"]`)?.remove();
    });
});

// Edit message modal
document.querySelectorAll('.edit-btn').forEach(button => {
    button.addEventListener('click', function() {
        const messageId = this.getAttribute('data-message-id');
        const messageText = this.getAttribute('data-text');
        const messageSubject = this.getAttribute('data-subject');
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Message</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label>Subject</label>
                                <input type="text" name="subject" class="form-control" value="${messageSubject}">
                            </div>
                            <div class="mb-3">
                                <label>Message</label>
                                <textarea name="message_text" class="form-control" required>${messageText}</textarea>
                            </div>
                            <input type="hidden" name="edit_message" value="${messageId}">
                            <button type="submit" class="btn btn-primary">Save</button>
                        </form>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        new bootstrap.Modal(modal).show();
        modal.addEventListener('hidden.bs.modal', () => modal.remove());
    });
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>