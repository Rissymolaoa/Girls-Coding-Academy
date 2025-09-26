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

// Fetch allowed receivers with photos
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages - Girls Coding Academy</title>
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
h2 { margin-bottom:20px; color:#5a189a; }
.messages-table { background:white; border-radius:8px; padding:15px; box-shadow:0 2px 6px rgba(0,0,0,0.1); margin-bottom:30px; }
.avatar, .avatar-initial {
    width:40px; height:40px; border-radius:50%; margin-right:10px;
}
.avatar { object-fit:cover; }
.avatar-initial {
    background:#5a189a; color:#fff; display:flex; align-items:center; justify-content:center;
    font-weight:bold; font-size:16px; text-transform:uppercase;
}
.alert { margin-bottom:20px; }
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
        <a href="messages.php" class="active"><i class="bi bi-envelope"></i> Messages</a>
        <?php if ($role === 'student'): ?>
            <a href="attendance.php"><i class="bi bi-card-checklist"></i> Attendance</a>
            <a href="student_profile.php"><i class="bi bi-person-circle"></i> My Profile</a>
        <?php endif; ?>
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
    <div class="content">
        <h2><i class="bi bi-envelope"></i> Compose Message</h2>

        <!-- Display Success/Error Messages -->
        <?php if (isset($_SESSION['message_success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['message_success']); unset($_SESSION['message_success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['message_error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['message_error']); unset($_SESSION['message_error']); ?></div>
        <?php endif; ?>

        <div class="messages-table">
            <h3>Send a Message</h3>
            <form method="POST" action="send_message.php">
                <input type="hidden" name="sender_id" value="<?php echo $user_id; ?>">
                <input type="hidden" name="sender_role" value="<?php echo $role; ?>">
                <div class="mb-3">
                    <label for="receiver_id" class="form-label">Recipient</label>
                    <select name="receiver_id" id="receiver_id" class="form-select" required>
                        <option value="">Select Recipient</option>
                        <?php foreach ($allowedReceivers as $receiver): ?>
                            <option value="<?php echo $receiver['user_id']; ?>" data-role="<?php echo htmlspecialchars($receiver['role']); ?>">
                                <div class="avatar-initial" style="display:inline-flex; vertical-align:middle; margin-right:5px;">
                                    <?php echo htmlspecialchars(strtoupper(substr($receiver['username'], 0, 1))); ?>
                                </div>
                                <?php echo htmlspecialchars($receiver['username'] . ' (' . ucfirst($receiver['role']) . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="receiver_role" id="receiver_role">
                </div>
                <div class="mb-3">
                    <label for="subject" class="form-label">Subject</label>
                    <input type="text" name="subject" id="subject" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="message_text" class="form-label">Message</label>
                    <textarea name="message_text" id="message_text" class="form-control" rows="5" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Send</button>
                <a href="chats.php" class="btn btn-secondary"><i class="bi bi-chat"></i> Go to Chats</a>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('receiver_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const role = selectedOption.getAttribute('data-role');
    document.getElementById('receiver_role').value = role;
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>