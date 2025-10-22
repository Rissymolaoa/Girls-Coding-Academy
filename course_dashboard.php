<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

include("db.php");

$user_id = $_SESSION['user_id'];

// Fetch student info
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
$student_id = $studentInfo['student_id'];

// Get course_id and batch_id from URL
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$batch_id = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;
if ($course_id <= 0 || $batch_id <= 0) {
    die("Invalid or missing course/batch ID. Please access this page from 'My Courses'.");
}

// Verify student enrollment
$stmt_verify = $conn->prepare("
    SELECT c.courseName, b.batch_code, ce.enrollment_id
    FROM course_enrollments ce
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    WHERE ce.student_id = ? AND ce.batch_id = ? AND c.course_id = ?
");
$stmt_verify->bind_param("iii", $student_id, $batch_id, $course_id);
$stmt_verify->execute();
$res_verify = $stmt_verify->get_result();
if ($res_verify->num_rows === 0) {
    die("You are not enrolled in this course or batch.");
}
$courseInfo = $res_verify->fetch_assoc();
$enrollment_id = $courseInfo['enrollment_id'];

// Fetch materials
$stmt_materials = $conn->prepare("
    SELECT title, description, file_path, uploaded_at
    FROM materials
    WHERE batch_id = ?
    ORDER BY uploaded_at DESC
");
$stmt_materials->bind_param("i", $batch_id);
$stmt_materials->execute();
$materials = $stmt_materials->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch activities
$stmt_activities = $conn->prepare("
    SELECT activity_id, title, description, due_date, resource_file, status
    FROM activities
    WHERE batch_id = ? AND status = 'active'
    ORDER BY created_at DESC
");
$stmt_activities->bind_param("i", $batch_id);
$stmt_activities->execute();
$activities = $stmt_activities->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch tests
$stmt_tests = $conn->prepare("
    SELECT test_id, title, description, due_date, max_score, resource_file
    FROM tests
    WHERE batch_id = ? AND status = 'active'
    ORDER BY created_at DESC
");
$stmt_tests->bind_param("i", $batch_id);
$stmt_tests->execute();
$tests = $stmt_tests->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= htmlspecialchars($courseInfo['courseName']) ?> - Course Dashboard</title>
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

/* Cards and Tables */
.section {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: 1px solid #eee;
}
.section h3 {
    color: black;
    margin-bottom: 15px;
}
.table th {
    background-color: #343a40;
    color: white;
}
.table td a {
    color: #1abc9c;
    text-decoration: none;
}
.table td a:hover {
    text-decoration: underline;
}
.card {
    border: 1px solid #ddd;
    border-radius: 10px;
    padding: 15px;
    background-color: #fff;
    box-shadow: 0 1px 5px rgba(0,0,0,0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.card:hover {
    transform: translateY(-3px);
    box-shadow: 0 3px 10px rgba(0,0,0,0.15);
}
.card h5 {
    color: #1abc9c;
    margin-bottom: 8px;
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
</style>
</head>
<body>

<div class="container-flex">
    <!-- Sidebar -->
    <nav class="sidebar">
        <img src="uploads/students/<?= htmlspecialchars($studentInfo['photo']) ?>" alt="Student Photo" />
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
        <h2><i class="bi bi-journal-bookmark"></i> <?= htmlspecialchars($courseInfo['courseName']) ?> (Batch: <?= htmlspecialchars($courseInfo['batch_code']) ?>)</h2>

        <!-- Materials -->
        <div class="section">
            <h3><i class="bi bi-file-earmark-text"></i> Uploaded Materials</h3>
            <?php if (empty($materials)): ?>
                <p class="text-muted">No materials uploaded for this batch.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Description</th>
                                <th>File</th>
                                <th>Uploaded At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materials as $m): ?>
                            <tr>
                                <td><?= htmlspecialchars($m['title']) ?></td>
                                <td><?= htmlspecialchars($m['description']) ?></td>
                                <td>
                                    <?php if ($m['file_path'] && file_exists($m['file_path'])): ?>
                                        <a href="<?= htmlspecialchars($m['file_path']) ?>" target="_blank">Download</a>
                                    <?php else: ?>
                                        <span class="text-danger">File missing</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($m['uploaded_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Activities -->
        <div class="section">
            <h3><i class="bi bi-list-check"></i> Assigned Activities</h3>
            <?php if (empty($activities)): ?>
                <p class="text-muted">No activities available.</p>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($activities as $a): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <h5><?= htmlspecialchars($a['title']) ?></h5>
                            <p><strong>Due:</strong> <?= htmlspecialchars($a['due_date']) ?></p>
                            <p><?= htmlspecialchars($a['description']) ?></p>
                            <p>
                                <?php if ($a['resource_file'] && file_exists($a['resource_file'])): ?>
                                    <a href="<?= htmlspecialchars($a['resource_file']) ?>" target="_blank">Download Resource</a>
                                <?php else: ?>
                                    <span>No file</span>
                                <?php endif; ?>
                            </p>
                            <a href="submit_activity.php?activity_id=<?= $a['activity_id'] ?>&course_id=<?= $course_id ?>&batch_id=<?= $batch_id ?>" class="btn btn-sm btn-primary">View/Submit</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tests -->
        <div class="section">
            <h3><i class="bi bi-clipboard2-check"></i> Assigned Tests</h3>
            <?php if (empty($tests)): ?>
                <p class="text-muted">No active tests for this batch.</p>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($tests as $t): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <h5><?= htmlspecialchars($t['title']) ?></h5>
                            <p><strong>Due:</strong> <?= htmlspecialchars($t['due_date']) ?></p>
                            <p><strong>Max Score:</strong> <?= htmlspecialchars($t['max_score']) ?></p>
                            <p><?= htmlspecialchars($t['description']) ?></p>
                            <p>
                                <?php if ($t['resource_file'] && file_exists($t['resource_file'])): ?>
                                    <a href="<?= htmlspecialchars($t['resource_file']) ?>" target="_blank">Download Test</a>
                                <?php else: ?>
                                    <span>No file</span>
                                <?php endif; ?>
                            </p>
                            <a href="submit_test.php?test_id=<?= $t['test_id'] ?>&course_id=<?= $course_id ?>&batch_id=<?= $batch_id ?>" class="btn btn-sm btn-primary">View/Submit</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>
