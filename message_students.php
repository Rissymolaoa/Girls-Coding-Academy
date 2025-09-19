<?php
session_start();
include 'db.php';

// Only allow teachers
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Handle sending a message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_id = intval($_POST['receiver_id']);
    $subject = $_POST['subject'];
    $body = $_POST['body'];
    $attachment = null;

    // Handle file upload if any
    if (!empty($_FILES['attachment']['name'])) {
        $target_dir = "uploads/messages/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $attachment = $target_dir . basename($_FILES['attachment']['name']);
        move_uploaded_file($_FILES['attachment']['tmp_name'], $attachment);
    }

    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, body, attachments, sent_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisss", $teacher_id, $receiver_id, $subject, $body, $attachment);
    $stmt->execute();
    $stmt->close();

    header("Location: message_students.php?sent=1");
    exit();
}

// Fetch all students for dropdown
$student_query = $conn->prepare("SELECT s.student_id, u.firstName, u.lastName FROM students s INNER JOIN users u ON s.user_id = u.user_id ORDER BY u.firstName");
$student_query->execute();
$student_result = $student_query->get_result();
$students = [];
while ($row = $student_result->fetch_assoc()) {
    $students[] = $row;
}
$student_query->close();

// Fetch messages sent by teacher
$message_query = $conn->prepare("
    SELECT m.message_id, m.subject, m.body, m.attachments, m.status, m.sent_at,
           u.firstName, u.lastName
    FROM messages m
    INNER JOIN students s ON m.receiver_id = s.student_id
    INNER JOIN users u ON s.user_id = u.user_id
    WHERE m.sender_id = ?
    ORDER BY m.sent_at DESC
");
$message_query->bind_param("i", $teacher_id);
$message_query->execute();
$message_result = $message_query->get_result();
$messages = [];
while ($row = $message_result->fetch_assoc()) {
    $messages[] = $row;
}
$message_query->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Message Students</title>
<style>
:root{
    --primary:#7b2cbf;
    --accent:#5a189a;
    --muted:#f4f4f8;
    --card:#ffffff;
    --text:#222;
}
*{box-sizing:border-box;}
body{margin:0;font-family:Inter,Arial,Helvetica,sans-serif;background:var(--muted);color:var(--text);}
header{background:linear-gradient(90deg,var(--primary),var(--accent));color:#fff;padding:18px 24px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,0.12);}
header h1{margin:0;font-size:22px;}
header p{margin:4px 0 0;font-size:14px;}
.layout{display:flex;min-height:calc(100vh - 72px);}
.sidebar{width:220px;background:#34495e;padding:20px;display:flex;flex-direction:column;align-items:center;color:#fff;}
.sidebar img{width:92px;height:92px;border-radius:50%;object-fit:cover;border:3px solid #1abc9c;margin-bottom:12px;}
.sidebar h3{font-size:14px;margin:0 0 12px;text-align:center;}
.nav a{width:100%;display:block;color:#fff;text-decoration:none;padding:10px;border-radius:6px;margin:6px 0;text-align:left;}
.nav a.active, .nav a:hover{background:#1abc9c;color:#062018;}
.main{flex:1;padding:26px;}
.table-card{background:var(--card);padding:14px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.06);margin-bottom:20px;}
table{width:100%;border-collapse:collapse;font-size:14px;}
th,td{padding:10px;border-bottom:1px solid #732d91;text-align:left;}
th{background:linear-gradient(90deg,var(--primary),var(--accent));color:#fff;}
footer{background:#34495e;color:#fff;padding:12px;text-align:center;margin-top:auto;}
.status-sent{color:green;font-weight:bold;}
.status-read{color:blue;font-weight:bold;}
.send-btn{display:inline-block;padding:6px 12px;background:#1abc9c;color:#fff;border-radius:4px;text-decoration:none;cursor:pointer;border:none;}
.send-btn:hover{background:#16a085;}
</style>
</head>
<body>
<header>
<h1>Teacher Messaging</h1>
</header>

<div class="layout">
    <aside class="sidebar">
        <img src="admin.jpg" alt="Teacher">
        <h3>Teacher Dashboard</h3>
        <nav class="nav">
            <a href="teacher_dashboard.php">🏠 Dashboard</a>
            <a href="manage_teacher_courses.php">📚 Manage Own Courses</a>
            <a href="upload_materials.php">📂 Upload Materials</a>
            <a href="grade.php">📝 Grade</a>
            <a href="mark_attendance.php">✅ Mark Attendance</a>
            <a href="message_students.php" class="active">💬 Message Students</a>
            <a href="logout.php">🚪 Logout</a>
        </nav>
    </aside>

    <main class="main">
        <h2>Send Message</h2>
        <?php if(isset($_GET['sent'])) echo "<p style='color:green;'>Message sent successfully!</p>"; ?>
        <div class="table-card">
            <form method="POST" enctype="multipart/form-data">
                <label>Student:
                    <select name="receiver_id" required>
                        <option value="">Select Student</option>
                        <?php foreach($students as $student): ?>
                            <option value="<?= $student['student_id'] ?>"><?= htmlspecialchars($student['firstName'].' '.$student['lastName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label><br><br>
                <label>Subject:
                    <input type="text" name="subject" required>
                </label><br><br>
                <label>Message:
                    <textarea name="body" rows="4" required></textarea>
                </label><br><br>
                <label>Attachment:
                    <input type="file" name="attachment">
                </label><br><br>
                <button type="submit" name="send_message" class="send-btn">Send Message</button>
            </form>
        </div>

        <h2>Sent Messages</h2>
        <?php if(count($messages) > 0): ?>
            <?php foreach($messages as $msg): ?>
                <div class="table-card">
                    <p><strong>To:</strong> <?= htmlspecialchars($msg['firstName'].' '.$msg['lastName']) ?></p>
                    <p><strong>Subject:</strong> <?= htmlspecialchars($msg['subject']) ?></p>
                    <p><strong>Message:</strong> <?= nl2br(htmlspecialchars($msg['body'])) ?></p>
                    <p><strong>Attachment:</strong> 
                        <?php if($msg['attachments']): ?>
                            <a href="<?= htmlspecialchars($msg['attachments']) ?>" target="_blank">View</a>
                        <?php else: ?>
                            None
                        <?php endif; ?>
                    </p>
                    <p><strong>Status:</strong> 
                        <span class="status-<?= $msg['status'] ?>"><?= htmlspecialchars($msg['status']) ?></span>
                        | <strong>Sent at:</strong> <?= htmlspecialchars($msg['sent_at']) ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No messages sent yet.</p>
        <?php endif; ?>
    </main>
</div>

<footer>
&copy; <?= date('Y') ?> Girls Coding Academy
</footer>
</body>
</html>
