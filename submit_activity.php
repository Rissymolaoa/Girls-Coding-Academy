<?php
session_start();

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Only allow students
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.html");
    exit();
}

include("db.php"); // DB connection

$user_id = $_SESSION['user_id'];

// Fetch student_id
$studentIdQuery = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
if (!$studentIdQuery) {
    die("Student ID query preparation failed: " . $conn->error);
}
$studentIdQuery->bind_param("i", $user_id);
$studentIdQuery->execute();
$studentIdResult = $studentIdQuery->get_result();
if ($studentIdResult->num_rows === 0) {
    die("Error: Student profile not found. Please contact the administrator.");
}
$student = $studentIdResult->fetch_assoc();
$student_id = (int)$student['student_id'];
$studentIdQuery->close();

// Get activity_id, course_id, and batch_id from URL
$activity_id = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
if ($activity_id <= 0 || $course_id <= 0 || $batch_id <= 0) {
    die("Error: Invalid or missing activity_id, course_id, or batch_id in URL.");
}

// Verify enrollment
$enrollmentQuery = $conn->prepare("
    SELECT ce.enrollment_id
    FROM course_enrollments ce
    INNER JOIN batches b ON ce.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    WHERE ce.student_id = ? AND ce.batch_id = ? AND c.course_id = ?
");
if (!$enrollmentQuery) {
    die("Enrollment query preparation failed: " . $conn->error);
}
$enrollmentQuery->bind_param("iii", $student_id, $batch_id, $course_id);
$enrollmentQuery->execute();
$enrollmentResult = $enrollmentQuery->get_result();
if ($enrollmentResult->num_rows === 0) {
    die("Error: You are not enrolled in this course or batch.");
}
$enrollment = $enrollmentResult->fetch_assoc();
$enrollment_id = (int)$enrollment['enrollment_id'];
$enrollmentQuery->close();

// Fetch activity details
$activityQuery = $conn->prepare("
    SELECT a.title, a.description, a.due_date, a.resource_file, a.status,
           s.submission_id, s.submission_text, s.submission_file, s.submitted_at
    FROM activities a
    LEFT JOIN activity_submissions s ON a.activity_id = s.activity_id AND s.enrollment_id = ?
    WHERE a.activity_id = ? AND a.status = 'active'
");
if (!$activityQuery) {
    die("Activity query preparation failed: " . $conn->error);
}
$activityQuery->bind_param("ii", $enrollment_id, $activity_id);
$activityQuery->execute();
$activityResult = $activityQuery->get_result();
if ($activityResult->num_rows === 0) {
    die("Error: Activity not found or is inactive.");
}
$activity = $activityResult->fetch_assoc();
$activityQuery->close();

// Handle activity submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_activity') {
    $submission_text = trim($conn->real_escape_string($_POST['submission_text'] ?? ''));
    $submission_file = null;

    // Check if submission already exists
    $checkSubmission = $conn->prepare("SELECT submission_id FROM activity_submissions WHERE activity_id = ? AND enrollment_id = ?");
    $checkSubmission->bind_param("ii", $activity_id, $enrollment_id);
    $checkSubmission->execute();
    $checkResult = $checkSubmission->get_result();
    if ($checkResult->num_rows > 0) {
        echo "<div class='alert alert-warning'>You have already submitted this activity.</div>";
    } else {
        // Check if activity is still open for submission
        $today = date('Y-m-d');
        if ($today > $activity['due_date']) {
            echo "<div class='alert alert-danger'>Submission period for this activity has closed.</div>";
        } else {
            if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'Uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                $file = $_FILES['submission_file'];
                if (!in_array($file['type'], $allowed_types)) {
                    echo "<div class='alert alert-danger'>Allowed file types: PDF, JPG, PNG.</div>";
                } elseif ($file['size'] > 200 * 1024 * 1024) {
                    echo "<div class='alert alert-danger'>File size exceeds 200MB.</div>";
                } else {
                    $original_name = basename($file['name']);
                    $filepath = $upload_dir . $original_name;
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $submission_file = $filepath;
                    } else {
                        echo "<div class='alert alert-danger'>Error uploading file.</div>";
                    }
                }
            }

            if (!isset($submission_file) || $submission_file || $submission_text) {
                try {
                    $stmt = $conn->prepare("INSERT INTO activity_submissions (activity_id, enrollment_id, submission_text, submission_file, submitted_at) VALUES (?, ?, ?, ?, NOW())");
                    $submission_file = $submission_file ?? '';
                    $stmt->bind_param("iiss", $activity_id, $enrollment_id, $submission_text, $submission_file);
                    $stmt->execute();
                    $stmt->close();
                    echo "<div class='alert alert-success'>Activity submitted successfully.</div>";
                    // Refresh activity data
                    $activityQuery->execute();
                    $activityResult = $activityQuery->get_result();
                    $activity = $activityResult->fetch_assoc();
                } catch (Exception $e) {
                    error_log("Error submitting activity: " . $e->getMessage());
                    echo "<div class='alert alert-danger'>Error submitting activity. Please try again.</div>";
                }
            } else {
                echo "<div class='alert alert-danger'>Please provide either a submission text or file.</div>";
            }
        }
    }
    $checkSubmission->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Submit Activity - <?php echo htmlspecialchars($activity['title']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial, sans-serif; background:#f9f9f9; color:#333; }
header { background:#fff; color:#333; padding:15px 30px; text-align:center; border-bottom:1px solid #ddd; }
.container { padding:30px; }
.activity-details { background:white; border-radius:8px; padding:15px; box-shadow:0 2px 6px rgba(0,0,0,0.1); border:1px solid #eee; margin-bottom:30px; }
.activity-details h3 { color:#5a189a; margin-bottom:15px; }
.activity-details p { margin-bottom:8px; }
.activity-details a { color:#5a189a; text-decoration:none; }
.activity-details a:hover { text-decoration:underline; }
.no-submission { color:#6c757d; font-style:italic; }
</style>
</head>
<body>

<header>
  <h1>Girls Coding Academy - Submit Activity</h1>
</header>

<div class="container">
  <h2>Activity: <?php echo htmlspecialchars($activity['title']); ?></h2>

  <div class="activity-details">
    <h3>Activity Details</h3>
    <p><strong>Description:</strong> <?php echo htmlspecialchars($activity['description']); ?></p>
    <p><strong>Due Date:</strong> <?php echo htmlspecialchars($activity['due_date']); ?></p>
    <p><strong>Resource File:</strong>
      <?php if ($activity['resource_file'] && file_exists($activity['resource_file'])): ?>
        <a href="<?php echo htmlspecialchars($activity['resource_file']); ?>" target="_blank">Download</a>
      <?php elseif ($activity['resource_file']): ?>
        <span class="text-danger">File not found: <?php echo htmlspecialchars($activity['resource_file']); ?></span>
      <?php else: ?>
        <span>No file</span>
      <?php endif; ?>
    </p>

    <?php if ($activity['submission_id']): ?>
      <h3>Your Submission</h3>
      <p><strong>Submission Text:</strong> <?php echo $activity['submission_text'] ? htmlspecialchars($activity['submission_text']) : 'No text submitted'; ?></p>
      <p><strong>Submission File:</strong>
        <?php if ($activity['submission_file'] && file_exists($activity['submission_file'])): ?>
          <a href="<?php echo htmlspecialchars($activity['submission_file']); ?>" target="_blank">View Submission</a>
        <?php elseif ($activity['submission_file']): ?>
          <span class="text-danger">File not found: <?php echo htmlspecialchars($activity['submission_file']); ?></span>
        <?php else: ?>
          <span>No file</span>
        <?php endif; ?>
      </p>
      <p><strong>Status:</strong>
        <?php
        $status = ($activity['submitted_at'] > $activity['due_date'] . ' 23:59:59') ? 'Late' : 'Submitted';
        $badge_class = ($status === 'Late') ? 'bg-danger' : 'bg-success';
        ?>
        <span class="badge <?php echo $badge_class; ?>"><?php echo $status; ?></span>
      </p>
      <p><strong>Submitted At:</strong> <?php echo htmlspecialchars($activity['submitted_at']); ?></p>
    <?php else: ?>
      <h3>Submit Activity</h3>
      <?php
      $today = date('Y-m-d');
      if ($today <= $activity['due_date']): ?>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="submit_activity">
          <div class="mb-3">
            <label for="submission_text" class="form-label">Submission Text</label>
            <textarea name="submission_text" id="submission_text" class="form-control" rows="4" placeholder="Enter your submission text"></textarea>
          </div>
          <div class="mb-3">
            <label for="submission_file" class="form-label">Upload File (PDF, JPG, PNG, max 200MB)</label>
            <input type="file" name="submission_file" id="submission_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
          </div>
          <button type="submit" class="btn btn-primary">Submit</button>
        </form>
      <?php else: ?>
        <p class="no-submission">Submission period closed. Status: <span class="badge bg-danger">Not Submitted</span></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <a href="course_dashboard.php?course_id=<?php echo $course_id; ?>&batch_id=<?php echo $batch_id; ?>" class="btn btn-secondary">Back to Dashboard</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>