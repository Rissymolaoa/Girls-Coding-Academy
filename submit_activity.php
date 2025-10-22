<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Student login check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.html");
    exit();
}

include("db.php");

$user_id = $_SESSION['user_id'];

// Fetch student info
$stmt = $conn->prepare("
    SELECT s.student_id, s.photo, u.username
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    WHERE s.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student profile not found.");
}

$student_id = $student['student_id'];
$photo = $student['photo'];
$username = $student['username'];

// Get URL params
$activity_id = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;
$course_id   = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$batch_id    = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;

if ($activity_id <= 0 || $course_id <= 0 || $batch_id <= 0) {
    die("Error: Invalid or missing activity_id, course_id, or batch_id in URL.");
}

// Verify enrollment
$enrollmentQuery = $conn->prepare("
    SELECT ce.enrollment_id, b.batch_code, c.courseName
    FROM course_enrollments ce
    INNER JOIN batches b ON ce.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    WHERE ce.student_id = ? AND ce.batch_id = ? AND c.course_id = ?
");
$enrollmentQuery->bind_param("iii", $student_id, $batch_id, $course_id);
$enrollmentQuery->execute();
$enrollmentResult = $enrollmentQuery->get_result();

if ($enrollmentResult->num_rows === 0) {
    die("Error: You are not enrolled in this course or batch.");
}

$enrollment = $enrollmentResult->fetch_assoc();
$enrollment_id = (int)$enrollment['enrollment_id'];
$batch_code = $enrollment['batch_code'];
$course_name = $enrollment['courseName'];
$enrollmentQuery->close();

// Fetch activity details and submission
function fetchActivity($conn, $activity_id, $enrollment_id) {
    $stmt = $conn->prepare("
        SELECT a.title, a.description, a.due_date, a.resource_file, a.status,
               s.submission_id, s.submission_text, s.submission_file, s.submitted_at
        FROM activities a
        LEFT JOIN activity_submissions s
            ON a.activity_id = s.activity_id AND s.enrollment_id = ?
        WHERE a.activity_id = ? AND a.status = 'active'
    ");
    $stmt->bind_param("ii", $enrollment_id, $activity_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $activity = $result->fetch_assoc();
    $stmt->close();
    return $activity;
}
$activity = fetchActivity($conn, $activity_id, $enrollment_id);

// Handle submission post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'submit_activity') {
    $submission_text = trim($conn->real_escape_string($_POST['submission_text'] ?? ''));
    $submission_file = null;

    $check = $conn->prepare("SELECT submission_id FROM activity_submissions WHERE activity_id = ? AND enrollment_id = ?");
    $check->bind_param("ii", $activity_id, $enrollment_id);
    $check->execute();
    $already = $check->get_result()->num_rows > 0;
    $check->close();

    if ($already) {
        $msg = "<div class='alert alert-warning text-center'>⚠️ You have already submitted this activity.</div>";
    } else {
        $today = date('Y-m-d');
        if ($today > $activity['due_date']) {
            $msg = "<div class='alert alert-danger text-center'>⛔ Submission period has closed.</div>";
        } else {
            if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'Uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                $file = $_FILES['submission_file'];
                $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];

                if (!in_array($file['type'], $allowed_types)) {
                    $msg = "<div class='alert alert-danger text-center'>❌ Allowed file types: PDF, JPG, PNG.</div>";
                } elseif ($file['size'] > 200 * 1024 * 1024) {
                    $msg = "<div class='alert alert-danger text-center'>❌ File size exceeds 200MB.</div>";
                } else {
                    $new_name = time() . "_" . basename($file['name']);
                    $filepath = $upload_dir . $new_name;
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $submission_file = $filepath;
                    } else {
                        $msg = "<div class='alert alert-danger text-center'>❌ Error uploading file.</div>";
                    }
                }
            }

            if ($submission_file || $submission_text) {
                $stmt = $conn->prepare("
                    INSERT INTO activity_submissions (activity_id, enrollment_id, submission_text, submission_file, submitted_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("iiss", $activity_id, $enrollment_id, $submission_text, $submission_file);
                $stmt->execute();
                $stmt->close();

                $msg = "<div class='alert alert-success text-center'>✅ Activity submitted successfully.</div>";
                $activity = fetchActivity($conn, $activity_id, $enrollment_id);
            } else {
                $msg = "<div class='alert alert-danger text-center'>⚠️ Please provide either a submission text or file.</div>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Submit Activity - <?= htmlspecialchars($activity['title']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
<style>
body {
    font-family: Arial, sans-serif;
    background-color: #f8f9fa;
    margin: 0;
    padding: 0;
    height: 100vh;
    overflow: hidden;
}
.container-flex {
    display: flex;
    height: 100vh;
}
/* Sidebar */
.sidebar {
    width: 250px;
    background-color: #343a40;
    color: white;
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    flex-shrink: 0;
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    overflow-y: auto;
}
.sidebar img {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    margin-bottom: 15px;
    object-fit: cover;
    border: 2px solid #1abc9c;
}
.sidebar h3 {
    margin-bottom: 30px;
    font-weight: bold;
    text-align: center;
}
.sidebar a {
    width: 100%;
    color: white;
    padding: 12px 15px;
    margin: 5px 0;
    border-radius: 6px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background-color 0.3s ease;
    font-weight: 500;
}
.sidebar a:hover,
.sidebar a.active {
    background-color: #495057;
}

/* Main content */
.content {
    flex: 1;
    padding: 30px 40px;
    margin-left: 250px;
    overflow-y: auto;
    height: 100vh;
}
h2 {
    margin-bottom: 25px;
    color: black;
}

/* Activity details card */
.activity-details {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: 1px solid #eee;
}
.activity-details h3 {
    color: black;
    margin-bottom: 15px;
}
.activity-details p {
    margin-bottom: 10px;
}
.no-submission {
    color: #6c757d;
    font-style: italic;
}
</style>
</head>
<body>

<div class="container-flex">
    <!-- Sidebar -->
    <nav class="sidebar">
        <img src="<?= $photo ? htmlspecialchars($photo) : 'default_profile.png'; ?>" alt="Student Photo" />
        <h3>Navigation</h3>
        <a href="student.php"><i class="bi bi-house-door"></i> Home</a>
        <a href="student_profile.php"><i class="bi bi-person-circle"></i> My Profile</a>
        <a href="student_courses.php" class="active"><i class="bi bi-journal-bookmark"></i> My Courses</a>
        <a href="student_announcements.php"><i class="bi bi-megaphone"></i> Announcements</a>
        <a href="student_calendar.php"><i class="bi bi-calendar-event"></i> My Calendar</a>
        <a href="attendance.php"><i class="bi bi-card-checklist"></i> My Attendance</a>
        <a href="student_marks.php"><i class="bi bi-bar-chart-line-fill"></i> My Grades</a>
        <a href="student_gradebook.php"><i class="bi bi-graph-up"></i> My Performance</a>
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>

    <!-- Main content -->
    <main class="content">
        <h2><i class="bi bi-journal-check"></i> Submit Activity - <?= htmlspecialchars($activity['title']) ?></h2>
        <h5><?= htmlspecialchars($course_name) ?> (Batch: <?= htmlspecialchars($batch_code) ?>)</h5>

        <?php if (isset($msg)) echo $msg; ?>

        <div class="activity-details">
            <h3>Activity Details</h3>
            <p><strong>Description:</strong> <?= htmlspecialchars($activity['description']) ?></p>
            <p><strong>Due Date:</strong> <?= htmlspecialchars($activity['due_date']) ?></p>
            <p>
                <strong>Resource File:</strong>
                <?php if ($activity['resource_file'] && file_exists($activity['resource_file'])): ?>
                    <a href="<?= htmlspecialchars($activity['resource_file']) ?>" target="_blank">Download</a>
                <?php else: ?>
                    <span>No file</span>
                <?php endif; ?>
            </p>

            <?php if ($activity['submission_id']): 
                $status = ($activity['submitted_at'] > $activity['due_date'] . ' 23:59:59') ? 'Late' : 'Submitted';
                $badge_class = ($status === 'Late') ? 'bg-danger' : 'bg-success';
            ?>
                <h3>Your Submission</h3>
                <p><strong>Submission Text:</strong> <?= $activity['submission_text'] ? htmlspecialchars($activity['submission_text']) : 'No text submitted' ?></p>
                <p>
                    <strong>Submission File:</strong>
                    <?php if ($activity['submission_file'] && file_exists($activity['submission_file'])): ?>
                        <a href="<?= htmlspecialchars($activity['submission_file']) ?>" target="_blank">View Submission</a>
                    <?php else: ?>
                        <span>No file</span>
                    <?php endif; ?>
                </p>
                <p><strong>Status:</strong> <span class="badge <?= $badge_class ?>"><?= $status ?></span></p>
                <p><strong>Submitted At:</strong> <?= htmlspecialchars($activity['submitted_at']) ?></p>
            <?php else: 
                $today = date('Y-m-d'); 
                if ($today <= $activity['due_date']):
            ?>
                <h3>Submit Your Work</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="submit_activity">
                    <div class="mb-3">
                        <label for="submission_text" class="form-label">Submission Text</label>
                        <textarea name="submission_text" id="submission_text" class="form-control" rows="4" placeholder="Write your answer..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="submission_file" class="form-label">Upload File (PDF, JPG, PNG, max 200MB)</label>
                        <input type="file" name="submission_file" id="submission_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Activity</button>
                </form>
            <?php else: ?>
                <p class="no-submission">Submission period closed. Status: <span class="badge bg-danger">Not Submitted</span></p>
            <?php endif; endif; ?>
        </div>

        <a href="course_dashboard.php?course_id=<?= $course_id ?>&batch_id=<?= $batch_id ?>" class="btn btn-secondary mt-3">
            <i class="bi bi-arrow-left"></i> Back to Course Dashboard
        </a>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
