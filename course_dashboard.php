<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

include("db.php");

$user_id = $_SESSION['user_id'];
$currentPage = basename($_SERVER['PHP_SELF']);

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
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    height: 100vh;
    overflow: hidden;
    color: #2c3e50;
}

.container-flex {
    display: flex;
    height: 100vh;
}

/* Main content */
.content {
    flex: 1;
    padding: 40px;
    margin-left: 250px;
    overflow-y: auto;
    height: 100vh;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}

.content::-webkit-scrollbar {
    width: 8px;
}

.content::-webkit-scrollbar-track {
    background: transparent;
}

.content::-webkit-scrollbar-thumb {
    background: #00d9ff;
    border-radius: 4px;
}

.content::-webkit-scrollbar-thumb:hover {
    background: #0099cc;
}

.header {
    margin-bottom: 40px;
    padding-bottom: 25px;
    border-bottom: 2px solid rgba(0, 217, 255, 0.3);
}

.header h2 {
    font-size: 2.2rem;
    font-weight: 700;
    background: linear-gradient(135deg, #1e293b 0%, #00d9ff 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 8px;
}

.header .breadcrumb {
    font-size: 0.95rem;
    color: #64748b;
}

/* Section Cards */
.section {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 35px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0, 217, 255, 0.2);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.section:hover {
    box-shadow: 0 12px 40px rgba(0, 217, 255, 0.15);
    transform: translateY(-2px);
}

.section h3 {
    color: #1e293b;
    margin-bottom: 25px;
    font-weight: 700;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    gap: 12px;
}

.section h3 i {
    color: #00d9ff;
}

.table-responsive {
    border-radius: 12px;
    overflow: hidden;
}

.table {
    margin-bottom: 0;
}

.table thead {
    background: linear-gradient(90deg, #1e293b 0%, #334155 100%);
}

.table th {
    color: white;
    font-weight: 600;
    border: none;
    padding: 16px;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.table td {
    padding: 14px 16px;
    border-color: #e2e8f0;
    vertical-align: middle;
}

.table tbody tr:hover {
    background-color: rgba(0, 217, 255, 0.08);
}

.table td a {
    color: #00d9ff;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.table td a:hover {
    color: #0099cc;
    text-decoration: underline;
}

.text-muted {
    color: #94a3b8 !important;
    font-style: italic;
}

/* Cards Grid */
.card {
    border: none;
    border-radius: 14px;
    padding: 24px;
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    height: 100%;
    border-left: 4px solid #00d9ff;
    position: relative;
    overflow: hidden;
}

.card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, #00d9ff, transparent);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 30px rgba(0, 217, 255, 0.2);
}

.card:hover::before {
    opacity: 1;
}

.card h5 {
    color: #1e293b;
    margin-bottom: 12px;
    font-weight: 700;
    font-size: 1.1rem;
}

.card p {
    color: #64748b;
    margin-bottom: 10px;
    font-size: 0.95rem;
    line-height: 1.6;
}

.card p strong {
    color: #1e293b;
}

.card .btn {
    margin-top: 12px;
    margin-right: 8px;
    border-radius: 8px;
    font-weight: 600;
    padding: 8px 16px;
    transition: all 0.3s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #00d9ff 0%, #0099cc 100%);
    border: none;
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #0099cc 0%, #006699 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 217, 255, 0.3);
    color: white;
}

.btn-primary:active {
    transform: translateY(0);
}

.btn-outline-primary {
    color: #00d9ff;
    border: 2px solid #00d9ff;
    background: transparent;
}

.btn-outline-primary:hover {
    background: #00d9ff;
    border-color: #00d9ff;
    color: #1e293b;
    transform: translateY(-2px);
}

.text-danger {
    color: #ef4444 !important;
    font-weight: 600;
}

.row {
    margin-bottom: 0;
}

.col-md-4 {
    margin-bottom: 24px;
}

@media (max-width: 768px) {
    .content {
        margin-left: 0;
        padding: 20px;
    }
    
    .header h2 {
        font-size: 1.5rem;
    }
    
    .section {
        padding: 20px;
    }
    
    .col-md-4 {
        flex: 0 0 100%;
        max-width: 100%;
    }
}
</style>
</head>
<body>

<div class="container-flex">
    <!-- Include Navigation -->
    <?php include("student_navigation.php"); ?>

    <!-- Main content -->
    <main class="content">
        <div class="header">
            <h2><i class="bi bi-journal-bookmark"></i> <?= htmlspecialchars($courseInfo['courseName']) ?></h2>
            <p class="breadcrumb"><i class="bi bi-bookmark-check"></i> Batch: <strong><?= htmlspecialchars($courseInfo['batch_code']) ?></strong></p>
        </div>

        <!-- Materials -->
        <div class="section">
            <h3><i class="bi bi-file-earmark-pdf"></i> Course Materials</h3>
            <?php if (empty($materials)): ?>
                <p class="text-muted"><i class="bi bi-info-circle"></i> No materials uploaded for this batch yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><i class="bi bi-file-text"></i> Title</th>
                                <th>Description</th>
                                <th class="text-center">Download</th>
                                <th>Uploaded At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materials as $m): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($m['title']) ?></strong></td>
                                <td><?= htmlspecialchars($m['description']) ?></td>
                                <td class="text-center">
                                    <?php if ($m['file_path'] && file_exists($m['file_path'])): ?>
                                        <a href="<?= htmlspecialchars($m['file_path']) ?>" target="_blank"><i class="bi bi-download"></i> Download</a>
                                    <?php else: ?>
                                        <span class="text-danger"><i class="bi bi-exclamation-circle"></i> Missing</span>
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
                <p class="text-muted"><i class="bi bi-info-circle"></i> No activities available at this time.</p>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($activities as $a): ?>
                    <div class="col-md-4">
                        <div class="card">
                            <h5><?= htmlspecialchars($a['title']) ?></h5>
                            <p><strong><i class="bi bi-calendar-check"></i> Due:</strong> <?= htmlspecialchars($a['due_date']) ?></p>
                            <p><?= htmlspecialchars($a['description']) ?></p>
                            <div>
                                <?php if ($a['resource_file'] && file_exists($a['resource_file'])): ?>
                                    <a href="<?= htmlspecialchars($a['resource_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> Resource</a>
                                <?php else: ?>
                                    <span class="text-muted"><i class="bi bi-file-slash"></i> No file</span>
                                <?php endif; ?>
                            </div>
                            <a href="submit_activity.php?activity_id=<?= $a['activity_id'] ?>&course_id=<?= $course_id ?>&batch_id=<?= $batch_id ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil-square"></i> Submit</a>
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
                <p class="text-muted"><i class="bi bi-info-circle"></i> No active tests for this batch.</p>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($tests as $t): ?>
                    <div class="col-md-4">
                        <div class="card">
                            <h5><?= htmlspecialchars($t['title']) ?></h5>
                            <p><strong><i class="bi bi-calendar-check"></i> Due:</strong> <?= htmlspecialchars($t['due_date']) ?></p>
                            <p><strong><i class="bi bi-star-fill"></i> Max Score:</strong> <?= htmlspecialchars($t['max_score']) ?> pts</p>
                            <p><?= htmlspecialchars($t['description']) ?></p>
                            <div>
                                <?php if ($t['resource_file'] && file_exists($t['resource_file'])): ?>
                                    <a href="<?= htmlspecialchars($t['resource_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> Test File</a>
                                <?php else: ?>
                                    <span class="text-muted"><i class="bi bi-file-slash"></i> No file</span>
                                <?php endif; ?>
                            </div>
                            <a href="submit_test.php?test_id=<?= $t['test_id'] ?>&course_id=<?= $course_id ?>&batch_id=<?= $batch_id ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil-square"></i> Take Test</a>
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