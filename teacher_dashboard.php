<?php
session_start();

// Enable error reporting for debugging (optional, remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Only allow teachers
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.html");
    exit();
}

include("db.php"); // DB connection

$user_id = $_SESSION['user_id'];

// Fetch teacher info from users table
$teacherQuery = $conn->prepare("SELECT username, email, gender, phone FROM users WHERE user_id=? AND role='teacher'");
if (!$teacherQuery) {
    die("Teacher query preparation failed: " . $conn->error);
}
$teacherQuery->bind_param("i", $user_id);
$teacherQuery->execute();
$teacherInfo = $teacherQuery->get_result()->fetch_assoc();
if (!$teacherInfo) {
    die("No teacher found for user_id: $user_id");
}
$teacherQuery->close();

// Fetch teacher_id from teachers table
$teacherIdQuery = $conn->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
if (!$teacherIdQuery) {
    die("Teacher ID query preparation failed: " . $conn->error);
}
$teacherIdQuery->bind_param("i", $user_id);
$teacherIdQuery->execute();
$teacherIdResult = $teacherIdQuery->get_result();
if ($teacherIdResult->num_rows === 0) {
    die("Error: Teacher profile not found. Please contact the administrator to set up your teacher profile.");
}
$teacher = $teacherIdResult->fetch_assoc();
$teacher_id = (int)$teacher['teacher_id'];
$teacherIdQuery->close();

// Handle batch selection
$selected_batch_id = isset($_POST['selected_batch_id']) ? (int)$_POST['selected_batch_id'] : null;

// Handle POST requests for grades and activities
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'record_grade') {
        $enrollment_id = (int)$_POST['enrollment_id'];
        $internal_number = (int)$_POST['internal_number'];
        $score = (float)$_POST['score'];

        // Validate inputs
        if ($internal_number < 1 || $internal_number > 8) {
            echo "<div class='alert alert-danger'>Error: Invalid internal number.</div>";
        } elseif ($score < 0 || $score > 100) {
            echo "<div class='alert alert-danger'>Error: Score must be between 0 and 100.</div>";
        } else {
            // Update or insert grade for the specific internal
            $gradeQuery = $conn->prepare("
                REPLACE INTO internal_grades (enrollment_id, internal_number, score)
                VALUES (?, ?, ?)
            ");
            if (!$gradeQuery) {
                die("Grade query preparation failed: " . $conn->error);
            }
            $gradeQuery->bind_param("iid", $enrollment_id, $internal_number, $score);
            $gradeQuery->execute();
            $gradeQuery->close();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'assign_activity') {
        // Ensure batch_id is set and valid
        if (!isset($_POST['batch_id']) || !is_numeric($_POST['batch_id']) || (int)$_POST['batch_id'] <= 0) {
            echo "<div class='alert alert-danger'>Error: Invalid batch ID.</div>";
        } else {
            $batch_id = (int)$_POST['batch_id'];

            // Validate batch_id exists in batches table
            $batchCheckQuery = $conn->prepare("SELECT batch_id FROM batches WHERE batch_id = ?");
            if (!$batchCheckQuery) {
                die("Batch check query preparation failed: " . $conn->error);
            }
            $batchCheckQuery->bind_param("i", $batch_id);
            $batchCheckQuery->execute();
            $batchCheckResult = $batchCheckQuery->get_result();
            if ($batchCheckResult->num_rows === 0) {
                echo "<div class='alert alert-danger'>Error: Selected batch does not exist.</div>";
            } else {
                $batchCheckQuery->close();

                $title = $conn->real_escape_string($_POST['title']);
                $description = $conn->real_escape_string($_POST['description']);
                $due_date = $conn->real_escape_string($_POST['due_date']);
                $resource_file = null;

                // Handle file upload
                if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'Uploads/';
                    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    $file = $_FILES['resource_file'];
                    
                    if (!in_array($file['type'], $allowed_types)) {
                        echo "<div class='alert alert-danger'>Error: Only PDF, JPG, and PNG files are allowed.</div>";
                    } elseif ($file['size'] > $max_size) {
                        echo "<div class='alert alert-danger'>Error: File size exceeds 5MB limit.</div>";
                    } else {
                        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $file_name = uniqid('activity_') . '.' . $file_ext;
                        $file_path = $upload_dir . $file_name;
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        if (move_uploaded_file($file['tmp_name'], $file_path)) {
                            $resource_file = '/' . $file_path;
                        } else {
                            echo "<div class='alert alert-danger'>Error: Failed to upload file.</div>";
                        }
                    }
                }

                // Insert activity only if no errors
                if (!isset($_SESSION['error'])) {
                    $activityQuery = $conn->prepare("
                        INSERT INTO activities (batch_id, teacher_id, title, description, due_date, resource_file, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    if (!$activityQuery) {
                        die("Activity query preparation failed: " . $conn->error);
                    }
                    $activityQuery->bind_param("iissss", $batch_id, $teacher_id, $title, $description, $due_date, $resource_file);
                    if ($activityQuery->execute()) {
                        echo "<div class='alert alert-success'>Activity assigned successfully.</div>";
                    } else {
                        echo "<div class='alert alert-danger'>Error: Failed to assign activity. " . $conn->error . "</div>";
                    }
                    $activityQuery->close();
                }
            }
        }
    }
}

// Get courses assigned to teacher
$courseQuery = $conn->prepare("
    SELECT ca.assignment_id, b.batch_id, b.batch_code, b.start_date, b.end_date, b.status, c.courseName
    FROM course_assignments ca
    INNER JOIN batches b ON ca.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    INNER JOIN teachers t ON ca.teacher_id = t.teacher_id
    WHERE t.user_id = ?
    ORDER BY b.start_date DESC
");
if (!$courseQuery) {
    die("Course query preparation failed: " . $conn->error);
}
$courseQuery->bind_param("i", $user_id);
$courseQuery->execute();
$assignedCourses = $courseQuery->get_result();
$courseQuery->close();

// Fetch available batches for selector
$batchSelectorQuery = $conn->prepare("
    SELECT b.batch_id, b.batch_code, c.courseName
    FROM course_assignments ca
    INNER JOIN batches b ON ca.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    WHERE ca.teacher_id IN (SELECT teacher_id FROM teachers WHERE user_id = ?)
    ORDER BY b.start_date DESC
");
$batchSelectorQuery->bind_param("i", $user_id);
$batchSelectorQuery->execute();
$batchSelectorResult = $batchSelectorQuery->get_result();

// If a batch is selected, fetch data for that batch only
if ($selected_batch_id) {
    // Get enrolled students for selected batch
    $studentQuery = $conn->prepare("
        SELECT ce.enrollment_id, ce.batch_id, ce.status, u.username, u.email
        FROM course_enrollments ce
        INNER JOIN students s ON ce.student_id = s.student_id
        INNER JOIN users u ON s.user_id = u.user_id
        WHERE ce.batch_id = ? AND ce.status = 'active'
        ORDER BY u.username
    ");
    if (!$studentQuery) {
        die("Student query preparation failed: " . $conn->error);
    }
    $studentQuery->bind_param("i", $selected_batch_id);
    $studentQuery->execute();
    $enrolledStudents = $studentQuery->get_result();
    $studentsByBatch = [$selected_batch_id => []];
    while ($student = $enrolledStudents->fetch_assoc()) {
        $studentsByBatch[$selected_batch_id][] = $student;
    }
    $studentQuery->close();

    // Get existing internal grades for selected batch
    $gradeQuery = $conn->prepare("
        SELECT g.enrollment_id, g.internal_number, g.score
        FROM internal_grades g
        INNER JOIN course_enrollments ce ON g.enrollment_id = ce.enrollment_id
        WHERE ce.batch_id = ?
    ");
    if (!$gradeQuery) {
        die("Grade query preparation failed: " . $conn->error);
    }
    $gradeQuery->bind_param("i", $selected_batch_id);
    $gradeQuery->execute();
    $gradesResult = $gradeQuery->get_result();
    $gradesByEnrollment = [];
    while ($grade = $gradesResult->fetch_assoc()) {
        $gradesByEnrollment[$grade['enrollment_id']][$grade['internal_number']] = $grade['score'];
    }
    $gradeQuery->close();

    // Get activities and their submissions for selected batch
    $activityQuery = $conn->prepare("
        SELECT a.activity_id, a.batch_id, a.title, a.description, a.due_date, a.resource_file
        FROM activities a
        WHERE a.batch_id = ?
        ORDER BY a.created_at DESC
    ");
    if (!$activityQuery) {
        die("Activity query preparation failed: " . $conn->error);
    }
    $activityQuery->bind_param("i", $selected_batch_id);
    $activityQuery->execute();
    $activitiesResult = $activityQuery->get_result();
    $activitiesByBatch = [$selected_batch_id => []];
    while ($activity = $activitiesResult->fetch_assoc()) {
        $activitiesByBatch[$selected_batch_id][] = $activity;
    }
    $activityQuery->close();

    // Get submissions for activities for selected batch
    $submissionQuery = $conn->prepare("
        SELECT s.submission_id, s.activity_id, s.enrollment_id, s.submission_text, s.submission_file, s.submitted_at,
               a.due_date, u.username, ce.batch_id
        FROM activity_submissions s
        INNER JOIN activities a ON s.activity_id = a.activity_id
        INNER JOIN course_enrollments ce ON s.enrollment_id = ce.enrollment_id
        INNER JOIN students st ON ce.student_id = st.student_id
        INNER JOIN users u ON st.user_id = u.user_id
        WHERE ce.batch_id = ?
        ORDER BY a.activity_id, s.submitted_at
    ");
    if (!$submissionQuery) {
        die("Submission query preparation failed: " . $conn->error);
    }
    $submissionQuery->bind_param("i", $selected_batch_id);
    $submissionQuery->execute();
    $submissionsResult = $submissionQuery->get_result();
    $submissionsByActivity = [];
    while ($submission = $submissionsResult->fetch_assoc()) {
        $submissionsByActivity[$submission['activity_id']][] = $submission;
    }
    $submissionQuery->close();
} else {
    $studentsByBatch = [];
    $gradesByEnrollment = [];
    $activitiesByBatch = [];
    $submissionsByActivity = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Teacher Dashboard</title>
<!-- Bootstrap CSS & Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

<style>
body {
    font-family: Inter, Arial, Helvetica, sans-serif;
    background: #f4f6f9;
}
header {
    background: linear-gradient(90deg, #7b2cbf, #5a189a);
    color: #fff;
}
header h1 {
    margin: 0;
    font-size: 22px;
}
</style>
</head>
<body>

<header class="py-3 px-4 text-center">
    <h1>Welcome, <?= htmlspecialchars($teacherInfo['username']) ?></h1>
    <p class="mb-0">Email: <?= htmlspecialchars($teacherInfo['email']) ?> | Gender: <?= htmlspecialchars($teacherInfo['gender']) ?> | Phone: <?= htmlspecialchars($teacherInfo['phone']) ?></p>
</header>

<div class="container-fluid d-flex flex-nowrap" style="min-height: calc(100vh - 70px);">
    <!-- Sidebar -->
    <nav class="col-md-3 col-xl-2 bg-dark text-white p-3 vh-100" style="min-width:220px;">
        <div class="text-center mb-4">
            <img src="admin.png" class="rounded-circle border border-info mb-2" width="92" height="92" alt="Teacher">
            <h5>Teacher Dashboard</h5>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item mb-2">
                <a class="nav-link text-white active" href="teacher_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link text-white" href="manage_teacher_courses.php"><i class="bi bi-journal-bookmark"></i> Manage Courses</a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link text-white" href="upload_materials.php"><i class="bi bi-folder"></i> Upload Materials</a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link text-white" href="grades.php"><i class="bi bi-pencil-square"></i> Grade</a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link text-white" href="mark_attendance.php"><i class="bi bi-check-circle"></i> Mark Attendance</a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link text-white" href="message_students.php"><i class="bi bi-chat-dots"></i> Message Students</a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link text-white" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="col py-4">
        <!-- Batch Selection -->
        <h2>Select Batch</h2>
        <form method="POST" class="d-flex mb-4">
            <select name="selected_batch_id" class="form-select me-2" required>
                <option value="">Select a batch to view and manage</option>
                <?php while ($row = $batchSelectorResult->fetch_assoc()): ?>
                <option value="<?= $row['batch_id'] ?>" <?= $selected_batch_id === $row['batch_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['batch_code']) ?> (<?= htmlspecialchars($row['courseName']) ?>)
                </option>
                <?php endwhile; ?>
            </select>
            <button type="submit" class="btn btn-primary">Select Batch</button>
        </form>

        <?php if ($selected_batch_id): ?>
        <?php
        // Fetch selected batch details
        $selectedBatchQuery = $conn->prepare("
            SELECT b.batch_code, c.courseName
            FROM batches b
            INNER JOIN courses c ON b.course_id = c.course_id
            WHERE b.batch_id = ?
        ");
        $selectedBatchQuery->bind_param("i", $selected_batch_id);
        $selectedBatchQuery->execute();
        $selectedBatchResult = $selectedBatchQuery->get_result()->fetch_assoc();
        $selectedBatchQuery->close();
        ?>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4>Batch: <?= htmlspecialchars($selectedBatchResult['batch_code']) ?> (<?= htmlspecialchars($selectedBatchResult['courseName']) ?>)</h4>
            </div>
            <div class="card-body">
                <!-- Enrolled Students -->
                <h5>Enrolled Students</h5>
                <?php if (isset($studentsByBatch[$selected_batch_id]) && count($studentsByBatch[$selected_batch_id]) > 0): ?>
                <div class="table-responsive mb-4">
                    <table class="table table-striped table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentsByBatch[$selected_batch_id] as $student): ?>
                            <tr>
                                <td><?= htmlspecialchars($student['username']) ?></td>
                                <td><?= htmlspecialchars($student['email']) ?></td>
                                <td><span class="badge <?= $student['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= htmlspecialchars($student['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">No students enrolled in this batch.</div>
                <?php endif; ?>

                <!-- Record Internal Grades -->
                <h5>Record Internal Assignments</h5>
                <?php if (isset($studentsByBatch[$selected_batch_id]) && count($studentsByBatch[$selected_batch_id]) > 0): ?>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Student Name</th>
                                <?php for ($i=1; $i<=8; $i++): ?>
                                <th>Internal <?= $i ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentsByBatch[$selected_batch_id] as $student): ?>
                            <tr>
                                <td><?= htmlspecialchars($student['username']) ?></td>
                                <?php for ($i=1; $i<=8; $i++): ?>
                                <td>
                                    <form method="POST" class="d-flex gap-2">
                                        <input type="hidden" name="action" value="record_grade" />
                                        <input type="hidden" name="selected_batch_id" value="<?= $selected_batch_id ?>">
                                        <input type="hidden" name="enrollment_id" value="<?= $student['enrollment_id'] ?>">
                                        <input type="hidden" name="internal_number" value="<?= $i ?>">
                                        <input class="form-control form-control-sm" type="number" name="score" value="<?= isset($gradesByEnrollment[$student['enrollment_id']][$i]) ? htmlspecialchars($gradesByEnrollment[$student['enrollment_id']][$i]) : '' ?>" min="0" max="100" step="0.1" placeholder="0-100" style="width:70px;">
                                        <button type="submit" class="btn btn-sm btn-success">Save</button>
                                    </form>
                                </td>
                                <?php endfor; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">No students enrolled in this batch.</div>
                <?php endif; ?>

                <!-- Assign Activities -->
                <h5>Assign Class Activity / Homework</h5>
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" class="row g-3">
                            <input type="hidden" name="action" value="assign_activity" />
                            <input type="hidden" name="batch_id" value="<?= $selected_batch_id ?>" />
                            <div class="col-md-6">
                                <label class="form-label">Title</label>
                                <input class="form-control" type="text" name="title" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Due Date</label>
                                <input class="form-control" type="date" name="due_date" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3" required></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Resource File (PDF, JPG, PNG, max 5MB)</label>
                                <input class="form-control" type="file" name="resource_file" accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary" type="submit">Assign</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- List Activities and Submissions -->
                <h5>Assigned Activities / Homeworks</h5>
                <?php if (isset($activitiesByBatch[$selected_batch_id]) && count($activitiesByBatch[$selected_batch_id]) > 0): ?>
                <?php foreach ($activitiesByBatch[$selected_batch_id] as $activity): ?>
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><?= htmlspecialchars($activity['title']) ?> (Due: <?= htmlspecialchars($activity['due_date']) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <p><?= htmlspecialchars($activity['description']) ?></p>
                        <?php if ($activity['resource_file']): ?>
                        <a href="<?= htmlspecialchars($activity['resource_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mb-3">View Resource File</a>
                        <?php endif; ?>
                        <!-- Submissions table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Status</th>
                                        <th>Submitted At</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $students = $studentsByBatch[$selected_batch_id] ?? [];
                                    $activity_submissions = $submissionsByActivity[$activity['activity_id']] ?? [];
                                    $current_date = date('Y-m-d');
                                    foreach ($students as $student):
                                        $submission = null;
                                        foreach ($activity_submissions as $sub) {
                                            if ($sub['enrollment_id'] == $student['enrollment_id']) {
                                                $submission = $sub;
                                                break;
                                            }
                                        }
                                        $status_text = 'Pending';
                                        if ($submission) {
                                            if ($submission['submitted_at'] > $activity['due_date'] . ' 23:59:59') {
                                                $status_text = 'Late';
                                            } else {
                                                $status_text = 'Submitted';
                                            }
                                        } else {
                                            $status_text = ($current_date > $activity['due_date']) ? 'Not Submitted' : 'Pending';
                                        }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($student['username']) ?></td>
                                        <td>
                                            <?php if ($status_text === 'Late'): ?>
                                                <span class="badge bg-danger"><?= $status_text ?></span>
                                            <?php elseif ($status_text === 'Not Submitted'): ?>
                                                <span class="badge bg-warning text-dark"><?= $status_text ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><?= $status_text ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $submission ? htmlspecialchars($submission['submitted_at']) : '-' ?></td>
                                        <td>
                                            <?php if ($submission): ?>
                                                <?php if ($submission['submission_text']): ?>
                                                    <p><?= htmlspecialchars($submission['submission_text']) ?></p>
                                                <?php endif; ?>
                                                <?php if ($submission['submission_file']): ?>
                                                    <a href="<?= htmlspecialchars($submission['submission_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">View File</a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <p>No submission</p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="alert alert-info">No activities assigned for this batch.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info">Please select a batch to view and manage records.</div>
        <?php endif; ?>
    </main>
</div>

<!-- Footer -->
<footer class="bg-dark text-white text-center py-3 mt-4">
    &copy; <?= date('Y') ?> Girls Coding Academy
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>