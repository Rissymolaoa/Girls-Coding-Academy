<?php
session_start();

// Restrict to teachers only (or admins if you want)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

include("db.php"); // adjust path if needed

$teacher_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Fetch classes/batches this teacher is assigned to
$classes = array();
$stmt = $conn->prepare("
    SELECT DISTINCT b.batch_id, b.batch_code, c.courseName
    FROM course_enrollments ce
    INNER JOIN batches b ON ce.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    WHERE ce.teacher_id = ?
    ORDER BY b.batch_code DESC
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['share_class_link'])) {
    $batch_id     = isset($_POST['batch_id'])     ? intval($_POST['batch_id']) : 0;
    $meeting_link = isset($_POST['meeting_link']) ? trim($_POST['meeting_link']) : '';
    $meeting_title = isset($_POST['meeting_title']) ? trim($_POST['meeting_title']) : 'Online Class Session';
    $meeting_time = isset($_POST['meeting_time']) ? trim($_POST['meeting_time']) : '';

    if ($batch_id <= 0 || empty($meeting_link) || !filter_var($meeting_link, FILTER_VALIDATE_URL)) {
        $error_message = "Please select a valid class and enter a proper meeting link.";
    } else {
        // Optional: Log the link
        $stmt = $conn->prepare("
            INSERT INTO class_meeting_links 
            (batch_id, teacher_id, meeting_link, meeting_title, scheduled_time, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iisss", $batch_id, $teacher_id, $meeting_link, $meeting_title, $meeting_time);
        $stmt->execute();
        $stmt->close();

        // Get enrolled students
        $students = array();
        $stmt = $conn->prepare("
            SELECT u.email, u.firstName, u.lastName
            FROM users u
            INNER JOIN students s ON u.user_id = s.user_id
            INNER JOIN course_enrollments ce ON s.student_id = ce.student_id
            WHERE ce.batch_id = ? AND u.status = 'active'
        ");
        $stmt->bind_param("i", $batch_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();

        $sent_count = 0;
        foreach ($students as $student) {
            $to = $student['email'];
            $subject = "Online Class: $meeting_title";

            $body = "
Dear {$student['firstName']} {$student['lastName']},

Your teacher has scheduled an online class.

Topic: $meeting_title
" . ($meeting_time ? "Date & Time: $meeting_time\n" : "") . "
Join Link: $meeting_link

Please join on time. Contact your teacher if you face any issues.

Best regards,
{$_SESSION['firstName'] ?? 'Your Teacher'}
Girls Coding Academy
            ";

            $headers = "From: no-reply@girlscodingacademy.org\r\n";
            $headers .= "Reply-To: " . (isset($_SESSION['email']) ? $_SESSION['email'] : 'teacher@girlscodingacademy.org') . "\r\n";

            if (mail($to, $subject, $body, $headers)) {
                $sent_count++;
            }
        }

        if ($sent_count > 0) {
            $success_message = "Link successfully sent to $sent_count students!";
        } else {
            $error_message = "No students found in this class, or email sending failed.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Online Class - Teacher Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }
        @media (max-width: 992px) {
            .main-content { margin-left: 0; }
        }
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 6px 20px rgba(0,0,0,0.08);
        }
        .btn-send {
            background: linear-gradient(135deg, #198754, #157347);
            border: none;
            padding: 0.75rem 2rem;
            font-size: 1.1rem;
        }
        .btn-send:hover {
            background: linear-gradient(135deg, #157347, #146c43);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(25,135,84,0.3);
        }
        /* Loading Screen */
        #loading-screen {
            position: fixed; inset: 0; z-index: 9999;
            display: flex; align-items: center; justify-content: center;
            background: white;
            transition: opacity 0.8s ease, visibility 0.8s ease;
        }
        .loaded #loading-screen {
            opacity: 0; visibility: hidden; pointer-events: none;
        }
        .logo-ring-container {
            position: relative;
            width: 100px; height: 100px;
        }
        @media (min-width: 768px) {
            .logo-ring-container { width: 140px; height: 140px; }
        }
        img {
            width: 100%; height: 100%; object-fit: contain; border-radius: 50%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            animation: pulse 2.8s infinite ease-in-out;
        }
        .rotating-ring {
            position: absolute; inset: -12px;
            border: 4px solid transparent; border-top-color: #198754;
            border-radius: 50%; animation: spin 7s linear infinite;
        }
        @keyframes pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.07); } }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<!-- Loading Screen -->
<div id="loading-screen">
    <div class="logo-ring-container">
        <img src="../imageuploads/logo.png" alt="GCA Logo"
             onerror="this.src='https://via.placeholder.com/140/198754/ffffff?text=GCA';">
        <div class="rotating-ring"></div>
    </div>
</div>

<?php include '../admin_navigation.php'; ?> <!-- Change to teacher_navigation.php if exists -->
<?php include '../top_navigation.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <h1 class="h3 fw-bold mb-4 text-success">Schedule & Share Online Class</h1>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($classes)): ?>
            <div class="alert alert-info text-center p-5">
                <h5>No classes assigned to you yet.</h5>
                <p>Contact admin to assign you to a batch or course.</p>
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-0">
                <div class="card-body p-5">
                    <form method="post">
                        <input type="hidden" name="share_class_link" value="1">

                        <div class="mb-4">
                            <label class="form-label fw-bold fs-5">Select Class/Batch</label>
                            <select name="batch_id" class="form-select form-select-lg" required>
                                <option value="">-- Choose your class --</option>
                                <?php foreach ($classes as $cls): ?>
                                    <option value="<?= $cls['batch_id'] ?>">
                                        <?= htmlspecialchars($cls['batch_code'] . ' - ' . $cls['courseName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Meeting Title (optional)</label>
                            <input type="text" name="meeting_title" class="form-control form-control-lg" 
                                   placeholder="e.g. Web Development - Live Coding Session">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Date & Time (optional but recommended)</label>
                            <input type="datetime-local" name="meeting_time" class="form-control form-control-lg">
                        </div>

                        <div class="mb-5">
                            <label class="form-label fw-bold fs-5 required">Meeting Link</label>
                            <textarea name="meeting_link" class="form-control form-control-lg" rows="4" 
                                      placeholder="Paste the full join link here (e.g. https://zoom.us/j/123456789 or https://meet.google.com/xxx-yyy-zzz)" 
                                      required></textarea>
                            <small class="form-text text-muted mt-2 d-block">
                                Make sure students can join without login if possible.
                            </small>
                        </div>

                        <button type="submit" class="btn btn-send btn-lg px-5 py-3 w-100 w-md-auto">
                            <i class="bi bi-send-fill me-2"></i> Send Link to All Students in Class
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Loading Screen Hide -->
<script>
    function hideLoader() {
        if (document.body) document.body.classList.add('loaded');
    }
    window.addEventListener('load', hideLoader);
    window.addEventListener('DOMContentLoaded', hideLoader);
    setTimeout(hideLoader, 800);
    setTimeout(hideLoader, 2000);
    setTimeout(hideLoader, 5000);
</script>

</body>
</html>