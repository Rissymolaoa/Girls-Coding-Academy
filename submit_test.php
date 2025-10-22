<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.html");
    exit();
}

include("db.php");

$user_id = $_SESSION['user_id'];

// Fetch student info for photo and username in sidebar
$stmt_student = $conn->prepare("
    SELECT s.student_id, s.photo, u.username
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    WHERE s.user_id = ?
");
$stmt_student->bind_param("i", $user_id);
$stmt_student->execute();
$result_student = $stmt_student->get_result();
$studentInfo = $result_student->fetch_assoc();
$student_id = (int)$studentInfo['student_id'];
$photo = $studentInfo['photo'];
$username = $studentInfo['username'];
$stmt_student->close();

// Get url parameters
$test_id = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;

if ($test_id <= 0 || $course_id <= 0 || $batch_id <= 0) {
    die("Error: Invalid or missing test_id, course_id, or batch_id in URL.");
}

// Verify enrollment and fetch course/batch info for header
$stmt_enroll = $conn->prepare("
    SELECT ce.enrollment_id, b.batch_code, c.courseName
    FROM course_enrollments ce
    INNER JOIN batches b ON ce.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    WHERE ce.student_id = ? AND ce.batch_id = ? AND c.course_id = ?
");
$stmt_enroll->bind_param("iii", $student_id, $batch_id, $course_id);
$stmt_enroll->execute();
$enroll_res = $stmt_enroll->get_result();
if ($enroll_res->num_rows === 0) {
    die("Error: You are not enrolled in this course or batch.");
}
$enroll_info = $enroll_res->fetch_assoc();
$enrollment_id = (int)$enroll_info['enrollment_id'];
$batch_code = $enroll_info['batch_code'];
$course_name = $enroll_info['courseName'];
$stmt_enroll->close();

// Fetch test details and any existing submission
$stmt_test = $conn->prepare("
    SELECT t.test_id, t.title, t.description, t.due_date, t.max_score, t.resource_file,
           s.submission_id, s.submission_text, s.submission_file, s.submitted_at
    FROM tests t
    LEFT JOIN test_submissions s ON t.test_id = s.test_id AND s.student_id = ?
    WHERE t.test_id = ? AND t.status = 'active'
");
$stmt_test->bind_param("ii", $student_id, $test_id);
$stmt_test->execute();
$res_test = $stmt_test->get_result();
if ($res_test->num_rows === 0) {
    die("Error: Test not found or inactive.");
}
$test = $res_test->fetch_assoc();
$stmt_test->close();

// Handle test submission
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_test') {
    $submission_text = trim($conn->real_escape_string($_POST['submission_text'] ?? ''));
    $submission_file = null;

    $check_sub = $conn->prepare("SELECT submission_id FROM test_submissions WHERE test_id = ? AND student_id = ?");
    $check_sub->bind_param("ii", $test_id, $student_id);
    $check_sub->execute();
    $check_res = $check_sub->get_result();
    if ($check_res->num_rows > 0) {
        $msg = "<div class='alert alert-warning'>You have already submitted this test.</div>";
    } else {
        $today = date('Y-m-d');
        if ($today > $test['due_date']) {
            $msg = "<div class='alert alert-danger'>Submission period for this test has closed.</div>";
        } else {
            if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'Uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                $file = $_FILES['submission_file'];
                if (!in_array($file['type'], $allowed_types)) {
                    $msg = "<div class='alert alert-danger'>Allowed file types: PDF, JPG, PNG.</div>";
                } elseif ($file['size'] > 200 * 1024 * 1024) {
                    $msg = "<div class='alert alert-danger'>File size exceeds 200MB.</div>";
                } else {
                    $original_name = basename($file['name']);
                    $filepath = $upload_dir . time() . "_" . $original_name;
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $submission_file = $filepath;
                    } else {
                        $msg = "<div class='alert alert-danger'>Error uploading file.</div>";
                    }
                }
            }

            if (!isset($submission_file) || $submission_file || $submission_text) {
                try {
                    $stmt_insert = $conn->prepare("INSERT INTO test_submissions (test_id, student_id, submission_text, submission_file, submitted_at) VALUES (?, ?, ?, ?, NOW())");
                    $file_path_val = $submission_file ?? '';
                    $stmt_insert->bind_param("iiss", $test_id, $student_id, $submission_text, $file_path_val);
                    $stmt_insert->execute();
                    $stmt_insert->close();

                    $msg = "<div class='alert alert-success'>Test submitted successfully.</div>";

                    // Refresh test data
                    $stmt_test = $conn->prepare("
                        SELECT t.test_id, t.title, t.description, t.due_date, t.max_score, t.resource_file,
                               s.submission_id, s.submission_text, s.submission_file, s.submitted_at
                        FROM tests t
                        LEFT JOIN test_submissions s ON t.test_id = s.test_id AND s.student_id = ?
                        WHERE t.test_id = ? AND t.status = 'active'
                    ");
                    $stmt_test->bind_param("ii", $student_id, $test_id);
                    $stmt_test->execute();
                    $res_test = $stmt_test->get_result();
                    $test = $res_test->fetch_assoc();
                    $stmt_test->close();
                } catch (Exception $e) {
                    error_log("Error submitting test: " . $e->getMessage());
                    $msg = "<div class='alert alert-danger'>Error submitting test. Please try again.</div>";
                }
            } else {
                $msg = "<div class='alert alert-danger'>Please provide either a submission text or file.</div>";
            }
        }
    }
    $check_sub->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Submit Test - <?= htmlspecialchars($test['title']) ?></title>
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

.sidebar a.active::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 4px;
    background: white;
    border-radius: 0 4px 4px 0;
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
    margin-bottom: 10px;
    color: black;
}
h5 {
    margin-bottom: 25px;
    color: #555;
}
.test-details {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: 1px solid #eee;
    margin-bottom: 20px;
}
.test-details h3 {
    color: black;
    margin-bottom: 15px;
}
.test-details p {
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
        <h2><i class="bi bi-card-checklist"></i> Submit Test - <?= htmlspecialchars($test['title']) ?></h2>
        <h5><?= htmlspecialchars($course_name) ?> (Batch: <?= htmlspecialchars($batch_code) ?>)</h5>

        <?= $msg ?>

        <div class="test-details">
            <h3>Test Details</h3>
            <p><strong>Description:</strong> <?= htmlspecialchars($test['description']) ?></p>
            <p><strong>Due Date:</strong> <?= htmlspecialchars($test['due_date']) ?></p>
            <p><strong>Max Score:</strong> <?= htmlspecialchars($test['max_score']) ?></p>
            <p><strong>Resource File:</strong>
                <?php if ($test['resource_file'] && file_exists($test['resource_file'])): ?>
                    <a href="<?= htmlspecialchars($test['resource_file']) ?>" target="_blank">Download</a>
                <?php elseif ($test['resource_file']): ?>
                    <span class="text-danger">File not found: <?= htmlspecialchars($test['resource_file']) ?></span>
                <?php else: ?>
                    <span>No file</span>
                <?php endif; ?>
            </p>

            <?php if ($test['submission_id']):
                $status = ($test['submitted_at'] > $test['due_date'] . ' 23:59:59') ? 'Late' : 'Submitted';
                $badge_class = ($status === 'Late') ? 'bg-danger' : 'bg-success';
            ?>
                <h3>Your Submission</h3>
                <p><strong>Submission Text:</strong> <?= $test['submission_text'] ? htmlspecialchars($test['submission_text']) : 'No text submitted' ?></p>
                <p>
                    <strong>Submission File:</strong>
                    <?php if ($test['submission_file'] && file_exists($test['submission_file'])): ?>
                        <a href="<?= htmlspecialchars($test['submission_file']) ?>" target="_blank">View Submission</a>
                    <?php elseif ($test['submission_file']): ?>
                        <span class="text-danger">File not found: <?= htmlspecialchars($test['submission_file']) ?></span>
                    <?php else: ?>
                        <span>No file</span>
                    <?php endif; ?>
                </p>
                <p><strong>Status:</strong> <span class="badge <?= $badge_class ?>"><?= $status ?></span></p>
                <p><strong>Submitted At:</strong> <?= htmlspecialchars($test['submitted_at']) ?></p>
            <?php else:
                $today = date('Y-m-d');
                if ($today <= $test['due_date']):
            ?>
                <h3>Submit Test</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="submit_test">
                    <div class="mb-3">
                        <label for="submission_text" class="form-label">Submission Text</label>
                        <textarea name="submission_text" id="submission_text" class="form-control" rows="4" placeholder="Enter your submission text"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="submission_file" class="form-label">Upload File (PDF, JPG, PNG, max 200MB)</label>
                        <input type="file" name="submission_file" id="submission_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" />
                    </div>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </form>
            <?php else: ?>
                <p class="no-submission">Submission period closed. Status: <span class="badge bg-danger">Not Submitted</span></p>
            <?php endif; endif; ?>
        </div>

        <a href="course_dashboard.php?course_id=<?= $course_id ?>&batch_id=<?= $batch_id ?>" class="btn btn-secondary mt-3">Back to Dashboard</a>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
