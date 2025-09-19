<?php
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Only allow teachers
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.html");
    exit();
}

include("db.php"); // DB connection

$teacher_id = $_SESSION['user_id'];
echo "Debug: teacher_id (from session) = $teacher_id<br>"; // Debug session

// Debug: Check users table for teacher
$userDebugQuery = $conn->prepare("SELECT user_id, username, email, role FROM users WHERE user_id = ?");
$userDebugQuery->bind_param("i", $teacher_id);
$userDebugQuery->execute();
$userDebugResult = $userDebugQuery->get_result()->fetch_assoc();
echo "Debug: Users table - " . ($userDebugResult ? print_r($userDebugResult, true) : "No user found") . "<br>";
$userDebugQuery->close();

// Debug: Check teachers table
$teacherDebugQuery = $conn->prepare("SELECT teacher_id, user_id, subject_speciality FROM teachers WHERE user_id = ?");
$teacherDebugQuery->bind_param("i", $teacher_id);
$teacherDebugQuery->execute();
$teacherDebugResult = $teacherDebugQuery->get_result()->fetch_assoc();
echo "Debug: Teachers table - " . ($teacherDebugResult ? print_r($teacherDebugResult, true) : "No teacher record found") . "<br>";
$teacherDebugQuery->close();

// Debug: List available teacher_ids in course_assignments
$debugQuery = $conn->query("SELECT DISTINCT teacher_id FROM course_assignments");
$teacherIds = [];
while ($row = $debugQuery->fetch_assoc()) {
    $teacherIds[] = $row['teacher_id'];
}
echo "Debug: Available teacher_ids in course_assignments: " . implode(", ", $teacherIds) . "<br>";

// Get teacher info
$teacherQuery = $conn->prepare("SELECT username, email, gender, phone FROM users WHERE user_id=? AND role='teacher'");
if (!$teacherQuery) {
    die("Teacher query preparation failed: " . $conn->error);
}
$teacherQuery->bind_param("i", $teacher_id);
$teacherQuery->execute();
$teacherInfo = $teacherQuery->get_result()->fetch_assoc();
if (!$teacherInfo) {
    die("No teacher found for user_id: $teacher_id");
}
$teacherQuery->close();

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
            echo "Error: Invalid internal number.";
        } elseif ($score < 0 || $score > 100) {
            echo "Error: Score must be between 0 and 100.";
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
        $batch_id = (int)$_POST['batch_id'];
        $title = $conn->real_escape_string($_POST['title']);
        $description = $conn->real_escape_string($_POST['description']);
        $due_date = $conn->real_escape_string($_POST['due_date']);
        $resource_file = null;

        // Handle file upload
        if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $file = $_FILES['resource_file'];
            
            // Validate file type and size
            if (!in_array($file['type'], $allowed_types)) {
                echo "Error: Only PDF, JPG, and PNG files are allowed.";
            } elseif ($file['size'] > $max_size) {
                echo "Error: File size exceeds 5MB limit.";
            } else {
                // Generate unique filename
                $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $file_name = uniqid('activity_') . '.' . $file_ext;
                $file_path = $upload_dir . $file_name;

                // Move file to uploads directory
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    $resource_file = '/' . $file_path;
                } else {
                    echo "Error: Failed to upload file.";
                }
            }
        }

        // Insert activity
        $activityQuery = $conn->prepare("
            INSERT INTO activities (batch_id, teacher_id, title, description, due_date, resource_file, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$activityQuery) {
            die("Activity query preparation failed: " . $conn->error);
        }
        $activityQuery->bind_param("iissss", $batch_id, $teacher_id, $title, $description, $due_date, $resource_file);
        $activityQuery->execute();
        $activityQuery->close();
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
$courseQuery->bind_param("i", $teacher_id);
$courseQuery->execute();
$assignedCourses = $courseQuery->get_result();
echo "Debug: Number of assigned courses = " . $assignedCourses->num_rows . "<br>";
$courseQuery->close();

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
    echo "Debug: Number of enrolled students for batch $selected_batch_id = " . $enrolledStudents->num_rows . "<br>";

    // Group students for selected batch
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
    // Default to no selection, no data shown
    $studentsByBatch = [];
    $gradesByEnrollment = [];
    $activitiesByBatch = [];
    $submissionsByActivity = [];
}

// Get all assigned batches for the selector
$batchSelectorQuery = $conn->prepare("
    SELECT b.batch_id, b.batch_code, c.courseName
    FROM course_assignments ca
    INNER JOIN batches b ON ca.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    INNER JOIN teachers t ON ca.teacher_id = t.teacher_id
    WHERE t.user_id = ?
    ORDER BY b.start_date DESC
");
$batchSelectorQuery->bind_param("i", $teacher_id);
$batchSelectorQuery->execute();
$batchSelectorResult = $batchSelectorQuery->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teacher Dashboard</title>
    <style>
        :root {
            --primary: #7b2cbf;
            --accent: #5a189a;
            --muted: #f4f4f8;
            --card: #ffffff;
            --text: #222;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, Arial, Helvetica, sans-serif;
            background: var(--muted);
            color: var(--text);
        }
        header {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            color: #fff;
            padding: 18px 24px;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
        }
        header h1 { margin: 0; font-size: 22px; }
        header p { margin: 4px 0 0; font-size: 14px; }
        .layout {
            display: flex;
            min-height: calc(100vh - 72px);
        }
        .sidebar {
            width: 220px;
            background: #34495e;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #fff;
        }
        .sidebar img {
            width: 92px;
            height: 92px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #1abc9c;
            margin-bottom: 12px;
        }
        .sidebar h3 {
            font-size: 14px;
            margin: 0 0 12px;
            text-align: center;
        }
        .nav a {
            width: 100%;
            display: block;
            color: #fff;
            text-decoration: none;
            padding: 10px;
            border-radius: 6px;
            margin: 6px 0;
            text-align: left;
        }
        .nav a.active, .nav a:hover {
            background: #1abc9c;
            color: #062018;
        }
        .main {
            flex: 1;
            padding: 26px;
        }
        .table-card {
            background: var(--card);
            padding: 14px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th, td {
            padding: 10px;
            border-bottom: 1px solid #732d91;
            text-align: left;
        }
        th {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            color: #fff;
        }
        footer {
            background: #34495e;
            color: #fff;
            padding: 12px;
            text-align: center;
            margin-top: auto;
        }
        .status-active { color: green; font-weight: bold; }
        .status-inactive { color: red; font-weight: bold; }
        .assign-btn {
            display: inline-block;
            padding: 6px 12px;
            background: #1abc9c;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
        }
        .assign-btn:hover { background: #16a085; }
        .grade-input { width: 60px; }
        .grade-form { display: inline; }
        .form-group {
            margin-bottom: 10px;
        }
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .form-group select, .form-group input, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #732d91;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        .error { color: red; font-weight: bold; }
        .batch-selector {
            margin-bottom: 20px;
        }
        .batch-selector form {
            display: inline;
        }
        .batch-selector select {
            padding: 8px;
            border: 1px solid #732d91;
            border-radius: 4px;
            font-size: 14px;
        }
        .batch-selector button {
            padding: 8px 12px;
            background: #1abc9c;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }
        .batch-selector button:hover {
            background: #16a085;
        }
    </style>
</head>
<body>
    <header>
        <h1>Welcome, <?= htmlspecialchars($teacherInfo['username']) ?></h1>
        <p>Email: <?= htmlspecialchars($teacherInfo['email']) ?> | Gender: <?= htmlspecialchars($teacherInfo['gender']) ?> | Phone: <?= htmlspecialchars($teacherInfo['phone']) ?></p>
    </header>

    <div class="layout">
        <aside class="sidebar">
            <img src="admin.jpg" alt="Teacher">
            <h3>Teacher Dashboard</h3>
            <nav class="nav">
                <a href="teacher_dashboard.php" class="active">🏠 Dashboard</a>
                <a href="manage_teacher_courses.php">📚 Manage Own Courses</a>
                <a href="upload_materials.php">📂 Upload Materials</a>
                <a href="grades.php">📝 Grade</a>
                <a href="mark_attendance.php">✅ Mark Attendance</a>
                <a href="message_students.php">💬 Message Students</a>
                <a href="logout.php">🚪 Logout</a>
            </nav>
        </aside>

        <main class="main">
            <h2>Select Batch</h2>
            <div class="batch-selector">
                <form method="POST">
                    <select name="selected_batch_id" required>
                        <option value="">Select a batch to view and manage</option>
                        <?php while ($row = $batchSelectorResult->fetch_assoc()): ?>
                        <option value="<?= $row['batch_id'] ?>" <?= $selected_batch_id === $row['batch_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($row['batch_code']) ?> (<?= htmlspecialchars($row['courseName']) ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit" class="assign-btn">Select Batch</button>
                </form>
            </div>

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
                ?>
                <h2>Batch: <?= htmlspecialchars($selectedBatchResult['batch_code']) ?> (<?= htmlspecialchars($selectedBatchResult['courseName']) ?>)</h2>

                <h3>Enrolled Students</h3>
                <div class="table-card">
                    <?php if (isset($studentsByBatch[$selected_batch_id]) && count($studentsByBatch[$selected_batch_id]) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Enrollment Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentsByBatch[$selected_batch_id] as $student): ?>
                            <tr>
                                <td><?= htmlspecialchars($student['username']) ?></td>
                                <td><?= htmlspecialchars($student['email']) ?></td>
                                <td class="status-active"><?= htmlspecialchars($student['status']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p>No students enrolled in this batch.</p>
                    <?php endif; ?>
                </div>

                <h3>Record Internal Assignments</h3>
                <div class="table-card">
                    <?php if (isset($studentsByBatch[$selected_batch_id]) && count($studentsByBatch[$selected_batch_id]) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Internal 1</th>
                                <th>Internal 2</th>
                                <th>Internal 3</th>
                                <th>Internal 4</th>
                                <th>Internal 5</th>
                                <th>Internal 6</th>
                                <th>Internal 7</th>
                                <th>End Assignment (8)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentsByBatch[$selected_batch_id] as $student): ?>
                            <tr>
                                <td><?= htmlspecialchars($student['username']) ?></td>
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                <td>
                                    <form class="grade-form" method="POST">
                                        <input type="hidden" name="action" value="record_grade">
                                        <input type="hidden" name="selected_batch_id" value="<?= $selected_batch_id ?>">
                                        <input type="hidden" name="enrollment_id" value="<?= $student['enrollment_id'] ?>">
                                        <input type="hidden" name="internal_number" value="<?= $i ?>">
                                        <input class="grade-input" type="number" name="score" value="<?= isset($gradesByEnrollment[$student['enrollment_id']][$i]) ? htmlspecialchars($gradesByEnrollment[$student['enrollment_id']][$i]) : '' ?>" min="0" max="100" step="0.1" placeholder="0-100">
                                        <button type="submit" class="assign-btn">Save</button>
                                    </form>
                                </td>
                                <?php endfor; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p>No students enrolled in this batch.</p>
                    <?php endif; ?>
                </div>

                <h3>Assign Class Activity / Homework</h3>
                <div class="table-card">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="assign_activity">
                        <input type="hidden" name="selected_batch_id" value="<?= $selected_batch_id ?>">
                        <input type="hidden" name="batch_id" value="<?= $selected_batch_id ?>">
                        <div class="form-group">
                            <label>Title:</label>
                            <input type="text" name="title" required>
                        </div>
                        <div class="form-group">
                            <label>Description:</label>
                            <textarea name="description" required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Due Date:</label>
                            <input type="date" name="due_date" required>
                        </div>
                        <div class="form-group">
                            <label>Resource File (PDF, JPG, PNG, max 5MB):</label>
                            <input type="file" name="resource_file" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        <button type="submit" class="assign-btn">Assign</button>
                    </form>
                </div>

                <h3>Assigned Activities / Homeworks</h3>
                <?php if (isset($activitiesByBatch[$selected_batch_id]) && count($activitiesByBatch[$selected_batch_id]) > 0): ?>
                    <?php foreach ($activitiesByBatch[$selected_batch_id] as $activity): ?>
                    <div class="table-card">
                        <h4>Activity: <?= htmlspecialchars($activity['title']) ?> (Due: <?= htmlspecialchars($activity['due_date']) ?>)</h4>
                        <p><?= htmlspecialchars($activity['description']) ?></p>
                        <?php if ($activity['resource_file']): ?>
                        <p><a href="<?= htmlspecialchars($activity['resource_file']) ?>" target="_blank" class="assign-btn">View Resource File</a></p>
                        <?php endif; ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Submission Status</th>
                                    <th>Submitted At</th>
                                    <th>Submission Details</th>
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
                                    $status = $submission ? ($submission['submitted_at'] > $activity['due_date'] . ' 23:59:59' ? 'Late' : 'Submitted') : ($current_date > $activity['due_date'] ? 'Not Submitted' : 'Pending');
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($student['username']) ?></td>
                                    <td class="<?= $status === 'Late' ? 'status-late' : ($status === 'Not Submitted' ? 'status-not-submitted' : 'status-active') ?>">
                                        <?= $status ?>
                                    </td>
                                    <td><?= $submission ? htmlspecialchars($submission['submitted_at']) : '-' ?></td>
                                    <td>
                                        <?php if ($submission): ?>
                                            <?php if ($submission['submission_text']): ?>
                                                <p><?= htmlspecialchars($submission['submission_text']) ?></p>
                                            <?php endif; ?>
                                            <?php if ($submission['submission_file']): ?>
                                                <a href="<?= htmlspecialchars($submission['submission_file']) ?>" target="_blank" class="assign-btn">View File</a>
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
                    <?php endforeach; ?>
                <?php else: ?>
                <p>No activities assigned for this batch.</p>
                <?php endif; ?>
            <?php else: ?>
            <p>Please select a batch to view and manage records.</p>
            <?php endif; ?>
        </main>
    </div>

    <footer>
        &copy; <?= date('Y') ?> Girls Coding Academy
    </footer>
</body>
</html>