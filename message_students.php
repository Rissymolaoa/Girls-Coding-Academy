<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'db.php';

// Ensure teacher is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch teacher info for header
$teacherQuery = $conn->prepare("SELECT username, email, gender, phone FROM users WHERE user_id=? AND role='teacher'");
$teacherQuery->bind_param("i", $user_id);
$teacherQuery->execute();
$teacherInfo = $teacherQuery->get_result()->fetch_assoc();
$teacherQuery->close();

// Handle sending a message
$success_message = '';
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_id = intval($_POST['receiver_id']);
    $subject = trim($_POST['subject']);
    $body = trim($_POST['body']);
    $attachment = null;

    if (empty($subject) || empty($body) || empty($receiver_id)) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Handle file upload if any
        if (!empty($_FILES['attachment']['name'])) {
            $target_dir = "uploads/messages/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $filename = time() . '_' . basename($_FILES['attachment']['name']);
            $attachment_path = $target_dir . $filename;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $attachment_path)) {
                $attachment = $attachment_path;
            } else {
                $error_message = "Failed to upload attachment.";
            }
        }

        if (empty($error_message)) {
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, body, attachments, status, sent_at) VALUES (?, ?, ?, ?, ?, 'sent', NOW())");
            $stmt->bind_param("iisss", $user_id, $receiver_id, $subject, $body, $attachment);
            if ($stmt->execute()) {
                $success_message = "Message sent successfully!";
                // Clear form data
                $_POST = array();
            } else {
                $error_message = "Failed to send message. Please try again.";
            }
            $stmt->close();
        }
    }
}

// Fetch all students for dropdown (with search-friendly display)
$student_query = $conn->prepare("SELECT s.student_id, u.firstName, u.lastName, u.email FROM students s INNER JOIN users u ON s.user_id = u.user_id ORDER BY u.firstName");
$student_query->execute();
$student_result = $student_query->get_result();
$students = [];
while ($row = $student_result->fetch_assoc()) {
    $students[] = $row;
}
$student_query->close();

// Fetch messages sent by teacher (with recent first, limit to 20 for performance)
$message_query = $conn->prepare("
    SELECT m.message_id, m.subject, m.body, m.attachments, m.status, m.sent_at,
           u.firstName, u.lastName, u.email as receiver_email
    FROM messages m
    INNER JOIN students s ON m.receiver_id = s.student_id
    INNER JOIN users u ON s.user_id = u.user_id
    WHERE m.sender_id = ?
    ORDER BY m.sent_at DESC LIMIT 20
");
$message_query->bind_param("i", $user_id);
$message_query->execute();
$message_result = $message_query->get_result();
$messages = [];
while ($row = $message_result->fetch_assoc()) {
    $messages[] = $row;
}
$message_query->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Students - Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-header { background: linear-gradient(90deg, #7b2cbf, #5a189a); }
        .sidebar { width: 250px; background: linear-gradient(180deg, #7b2cbf, #5a189a); position: fixed; height: 100vh; overflow-y: auto; transition: transform 0.3s ease; z-index: 1000; }
        .sidebar.hidden { transform: translateX(-100%); }
        .sidebar-link { transition: all 0.3s ease; }
        .sidebar-link:hover { background: rgba(255, 255, 255, 0.1); padding-left: 1.5rem; }
        .sidebar-link.active { background: rgba(255, 255, 255, 0.2); border-left: 4px solid white; }
        .main-content { margin-left: 250px; transition: margin-left 0.3s ease; }
        .main-content.expanded { margin-left: 0; }
        .card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .card:hover { transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15); }
        .mobile-toggle { display: none; }
        .message-card { max-height: 400px; overflow-y: auto; }
        .sent-message { background: linear-gradient(135deg, #f0f9ff, #e0f2fe); }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-toggle { display: block; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="p-6">
            <div class="flex items-center mb-8">
                <i class="fas fa-graduation-cap text-white text-3xl mr-3"></i>
                <h2 class="text-white text-xl font-bold">GCA Portal</h2>
            </div>
            <nav>
                <a href="teacher_dashboard.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-home mr-3"></i> Dashboard
                </a>
                <a href="manage_teacher_courses.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-chalkboard-teacher mr-3"></i> Manage Courses
                </a>
                <a href="upload_materials.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-book mr-3"></i> Upload Materials
                </a>
                <a href="grades.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-clipboard-check mr-3"></i> Grade
                </a>
                <a href="mark_attendance.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-calendar-check mr-3"></i> Mark Attendance
                </a>
                <a href="message_students.php" class="sidebar-link flex active items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-envelope mr-3"></i> Message Students
                </a>
                <a href="teacher_profile.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-user mr-3"></i> Profile
                </a>
                <a href="logout.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-sign-out-alt mr-3"></i> Logout
                </a>
            </nav>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Header -->
        <header class="gradient-header text-white py-4 px-6 flex justify-between items-center">
            <button class="mobile-toggle text-white text-2xl" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 class="text-xl font-semibold">Message Students</h1>
                <p class="text-sm">Compose and send messages to individual students.</p>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="p-6">
            <?php if ($success_message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <!-- Compose Message Card -->
            <div class="card bg-white rounded-lg shadow-lg p-6 mb-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Compose New Message</h3>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">To (Student)</label>
                        <select name="receiver_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">Select a Student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['student_id'] ?>" <?= isset($_POST['receiver_id']) && $_POST['receiver_id'] == $student['student_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($student['firstName'] . ' ' . $student['lastName']) ?> (<?= htmlspecialchars($student['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                        <input type="text" name="subject" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                        <textarea name="body" rows="5" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"><?= htmlspecialchars($_POST['body'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Attachment (Optional)</label>
                        <input type="file" name="attachment" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                    <button type="submit" name="send_message" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-6 rounded-md transition duration-200">
                        <i class="fas fa-paper-plane mr-2"></i> Send Message
                    </button>
                </form>
            </div>

            <!-- Sent Messages Section -->
            <div class="card bg-white rounded-lg shadow-lg p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Sent Messages</h3>
                <?php if (empty($messages)): ?>
                    <p class="text-gray-600">No messages sent yet. Start by composing one above.</p>
                <?php else: ?>
                    <div class="space-y-4 message-card">
                        <?php foreach ($messages as $msg): ?>
                            <div class="sent-message p-4 rounded-lg border-l-4 border-blue-300">
                                <div class="flex justify-between items-start mb-2">
                                    <h4 class="font-semibold text-gray-800"><?= htmlspecialchars($msg['subject']) ?></h4>
                                    <span class="text-sm text-gray-500"><?= date('M j, Y g:i A', strtotime($msg['sent_at'])) ?></span>
                                </div>
                                <p class="text-gray-700 mb-3"><?= nl2br(htmlspecialchars($msg['body'])) ?></p>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">To: <?= htmlspecialchars($msg['firstName'] . ' ' . $msg['lastName']) ?> (<?= htmlspecialchars($msg['receiver_email']) ?>)</span>
                                    <span class="text-sm font-medium <?= $msg['status'] === 'sent' ? 'text-green-600' : 'text-blue-600' ?>"><?= ucfirst($msg['status']) ?></span>
                                </div>
                                <?php if ($msg['attachments']): ?>
                                    <div class="mt-2">
                                        <a href="<?= htmlspecialchars($msg['attachments']) ?>" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">
                                            <i class="fas fa-paperclip mr-1"></i> View Attachment
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($messages) >= 20): ?>
                        <p class="text-center text-gray-500 mt-4">Showing recent 20 messages. <a href="#" class="text-blue-600">Load more</a></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('mobile-open');
            
            // Close sidebar when clicking outside on mobile
            if (sidebar.classList.contains('mobile-open')) {
                document.addEventListener('click', closeSidebarOnClickOutside);
            } else {
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        }

        function closeSidebarOnClickOutside(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = event.target.closest('.mobile-toggle');
            
            if (!sidebar.contains(event.target) && !toggleBtn) {
                sidebar.classList.remove('mobile-open');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        }
    </script>
</body>
</html>