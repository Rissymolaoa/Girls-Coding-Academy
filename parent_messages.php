<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

include("db.php");

$parent_user_id = $_SESSION['user_id'];

// Fetch children of this parent
$children_stmt = $conn->prepare("
    SELECT s.student_id, u.firstName, u.lastName
    FROM students s
    INNER JOIN parent_students ps ON s.student_id = ps.student_id
    INNER JOIN users u ON s.user_id = u.user_id
    WHERE ps.parent_id = (SELECT parent_id FROM parents WHERE user_id = ?)
");
$children_stmt->bind_param("i", $parent_user_id);
$children_stmt->execute();
$children_result = $children_stmt->get_result();
$children = $children_result->fetch_all(MYSQLI_ASSOC);

// Handle sending message
if (isset($_POST['send_message'])) {
    $student_id = intval($_POST['student_id']);
    $recipient_user_id = intval($_POST['recipient_user_id']);
    $subject = $_POST['subject'];
    $body = $_POST['body'];

    $insert_stmt = $conn->prepare("
        INSERT INTO parent_messages (sender_user_id, recipient_user_id, student_id, subject, body) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $insert_stmt->bind_param("iiiss", $parent_user_id, $recipient_user_id, $student_id, $subject, $body);
    $insert_stmt->execute();

    $success = "Message sent successfully!";
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
");
$teachers_stmt->bind_param("i", $parent_user_id);
$teachers_stmt->execute();
$teachers_result = $teachers_stmt->get_result();
$teachers = $teachers_result->fetch_all(MYSQLI_ASSOC);

// Fetch admin users
$admin_stmt = $conn->prepare("SELECT user_id, firstName, lastName FROM users WHERE role = 'admin'");
$admin_stmt->execute();
$admins_result = $admin_stmt->get_result();
$admins = $admins_result->fetch_all(MYSQLI_ASSOC);

// Fetch all messages sent or received by this parent
$messages_stmt = $conn->prepare("
    SELECT m.*, u1.firstName AS sender_fname, u1.lastName AS sender_lname, 
           u2.firstName AS recipient_fname, u2.lastName AS recipient_lname
    FROM parent_messages m
    INNER JOIN users u1 ON u1.user_id = m.sender_user_id
    INNER JOIN users u2 ON u2.user_id = m.recipient_user_id
    WHERE m.sender_user_id = ? OR m.recipient_user_id = ?
    ORDER BY m.sent_at DESC
");
$messages_stmt->bind_param("ii", $parent_user_id, $parent_user_id);
$messages_stmt->execute();
$messages_result = $messages_stmt->get_result();
$messages = $messages_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Parent Messaging</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { display: flex; min-height: 100vh; margin:0; font-family:Arial,sans-serif; }
.sidebar { width: 250px; background: #343a40; color:white; flex-shrink:0; }
.sidebar h4 { text-align:center; padding:15px 0; border-bottom:1px solid #495057; }
.sidebar img { width:80px; border-radius:50%; margin:10px auto; display:block; }
.sidebar a { display:block; color:white; padding:12px 20px; text-decoration:none; }
.sidebar a:hover { background:#495057; }
.content { flex-grow:1; padding:20px; background:#f9f9f9; }
.card { margin-bottom:20px; }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <img src="admin.png" alt="Parent Image">
    <h4>Parent Dashboard</h4>
    <a href="parents_dashboard.php">Dashboard</a>
    <a href="children.php">Children Profiles</a>
    <a href="parent_messages.php" class="active">Messages</a>
    <a href="parent_settings.php">Settings</a>
    <a href="logout.php">Logout</a>
</div>

<!-- Main Content -->
<div class="content">
    <h2>Send a Message</h2>

    <?php if(isset($success)) echo '<div class="alert alert-success">'.$success.'</div>'; ?>

    <div class="card p-3">
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label>Child</label>
                    <select class="form-select" name="student_id" required>
                        <option value="">Select Child</option>
                        <?php foreach($children as $child): ?>
                            <option value="<?= $child['student_id'] ?>"><?= htmlspecialchars($child['firstName'].' '.$child['lastName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Recipient</label>
                    <select class="form-select" name="recipient_user_id" required>
                        <option value="">Select Recipient</option>
                        <?php foreach($teachers as $teacher): ?>
                            <option value="<?= $teacher['user_id'] ?>">Teacher: <?= htmlspecialchars($teacher['firstName'].' '.$teacher['lastName']) ?></option>
                        <?php endforeach; ?>
                        <?php foreach($admins as $admin): ?>
                            <option value="<?= $admin['user_id'] ?>">Admin: <?= htmlspecialchars($admin['firstName'].' '.$admin['lastName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <label>Subject</label>
                    <input type="text" class="form-control" name="subject" required>
                </div>
                <div class="col-md-12">
                    <label>Message</label>
                    <textarea class="form-control" name="body" rows="4" required></textarea>
                </div>
                <div class="col-md-12 text-end">
                    <button type="submit" name="send_message" class="btn btn-primary"><i class="bi bi-send"></i> Send</button>
                </div>
            </div>
        </form>
    </div>

    <h2>Message History</h2>
    <div class="card p-3">
        <?php if(count($messages) > 0): ?>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($messages as $msg): ?>
                        <tr>
                            <td><?= htmlspecialchars($msg['sent_at']) ?></td>
                            <td><?= htmlspecialchars($msg['sender_fname'].' '.$msg['sender_lname']) ?></td>
                            <td><?= htmlspecialchars($msg['recipient_fname'].' '.$msg['recipient_lname']) ?></td>
                            <td><?= htmlspecialchars($msg['subject']) ?></td>
                            <td><?= htmlspecialchars($msg['body']) ?></td>
                            <td><?= $msg['is_read'] ? '<span class="badge bg-success">Read</span>' : '<span class="badge bg-warning">Unread</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No messages found.</p>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
