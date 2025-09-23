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
        $filename = basename($_FILES['attachment']['name']);
        $attachment_path = $target_dir . $filename;
        move_uploaded_file($_FILES['attachment']['tmp_name'], $attachment_path);
        $attachment = $attachment_path;
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
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Message Students</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    :root {
      --primary: #7b2cbf;
      --accent: #5a189a;
      --muted: #f4f4f8;
      --card: #fff;
      --text: #222;
    }
    body {
      font-family: 'Inter', Arial, Helvetica, sans-serif;
      background: var(--muted);
    }
    header {
      background: linear-gradient(90deg, var(--primary), var(--accent));
      color: #fff;
    }
    header h1 {
      margin: 0;
      font-size: 2rem;
    }
  </style>
</head>
<body>
<header class="py-3 px-4 text-center">
  <h1>Teacher Messaging</h1>
</header>

<div class="container-fluid d-flex flex-column flex-lg-row" style="min-height: calc(100vh - 70px);">
  <!-- Sidebar -->
  <nav class="col-lg-3 col-xl-2 bg-dark text-white p-3 vh-100 d-flex flex-column align-items-center">
    <img src="admin.jpg" class="rounded-circle border border-info mb-3" width="92" height="92" alt="Teacher" />
    <h5 class="text-center mb-3">Teacher Dashboard</h5>
    <ul class="nav nav-pills flex-column w-100">
      <li class="nav-item"><a class="nav-link text-white" href="teacher_dashboard.php">🏠 Dashboard</a></li>
      <li class="nav-item"><a class="nav-link text-white" href="manage_teacher_courses.php">📚 Manage Own Courses</a></li>
      <li class="nav-item"><a class="nav-link text-white" href="upload_materials.php">📂 Upload Materials</a></li>
      <li class="nav-item"><a class="nav-link text-white" href="grade.php">📝 Grade</a></li>
      <li class="nav-item"><a class="nav-link active" href="message_students.php">💬 Message Students</a></li>
      <li class="nav-item"><a class="nav-link text-white" href="logout.php">🚪 Logout</a></li>
    </ul>
  </nav>

  <!-- Main Content -->
  <main class="col flex-fill p-4">
    <h2 class="mb-4">Send Message</h2>
    <?php if (isset($_GET['sent'])): ?>
      <div class="alert alert-success">Message sent successfully!</div>
    <?php endif; ?>

    <div class="card mb-4 p-3 shadow-sm">
      <form method="POST" enctype="multipart/form-data" class="row g-3">
        <div class="col-md-4">
          <label for="receiver_id" class="form-label">Student</label>
          <select class="form-select" id="receiver_id" name="receiver_id" required>
            <option value="">Select Student</option>
            <?php foreach ($students as $student): ?>
              <option value="<?= $student['student_id'] ?>"><?= htmlspecialchars($student['firstName'].' '.$student['lastName']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label for="subject" class="form-label">Subject</label>
          <input type="text" class="form-control" id="subject" name="subject" required>
        </div>
        <div class="col-md-4">
          <label for="body" class="form-label">Message</label>
          <textarea class="form-control" id="body" name="body" rows="3" required></textarea>
        </div>
        <div class="col-12">
          <label for="attachment" class="form-label">Attachment</label>
          <input class="form-control" type="file" id="attachment" name="attachment" />
        </div>
        <div class="col-12 mt-3">
          <button type="submit" name="send_message" class="btn btn-primary">Send Message</button>
        </div>
      </form>
    </div>

    <h2 class="mb-3">Sent Messages</h2>
    <?php if (count($messages) > 0): ?>
      <?php foreach ($messages as $msg): ?>
        <div class="card mb-3 p-3 shadow-sm">
          <div class="mb-2"><strong>To:</strong> <?= htmlspecialchars($msg['firstName'].' '.$msg['lastName']) ?></div>
          <div class="mb-2"><strong>Subject:</strong> <?= htmlspecialchars($msg['subject']) ?></div>
          <div class="mb-2"><strong>Message:</strong><br><?= nl2br(htmlspecialchars($msg['body'])) ?></div>
          <div class="mb-2"><strong>Attachment:</strong> 
            <?php if ($msg['attachments']): ?>
              <a href="<?= htmlspecialchars($msg['attachments']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary mt-2">View</a>
            <?php else: ?>
              None
            <?php endif; ?>
          </div>
          <div class="mt-2"><strong>Status:</strong> <span class="text-<?php echo strtolower($msg['status']) === 'sent' ? 'success' : 'primary'; ?>"><?= htmlspecialchars($msg['status']) ?></span> | <strong>Sent at:</strong> <?= htmlspecialchars($msg['sent_at']) ?></div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p>No messages sent yet.</p>
    <?php endif; ?>
  </main>
</div>

<footer class="bg-dark text-white text-center py-3 mt-4">
  &copy; <?= date('Y') ?> Girls Coding Academy
</footer>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>