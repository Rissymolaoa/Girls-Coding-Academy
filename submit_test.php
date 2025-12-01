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
$currentPage = basename($_SERVER['PHP_SELF']);

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
        $msg = "<div class='alert alert-warning alert-dismissible fade show' role='alert'><i class='bi bi-exclamation-triangle'></i> You have already submitted this test.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } else {
        $today = date('Y-m-d');
        if ($today > $test['due_date']) {
            $msg = "<div class='alert alert-danger alert-dismissible fade show' role='alert'><i class='bi bi-exclamation-circle'></i> Submission period for this test has closed.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'Uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                $file = $_FILES['submission_file'];
                if (!in_array($file['type'], $allowed_types)) {
                    $msg = "<div class='alert alert-danger alert-dismissible fade show' role='alert'><i class='bi bi-exclamation-circle'></i> Allowed file types: PDF, JPG, PNG.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
                } elseif ($file['size'] > 200 * 1024 * 1024) {
                    $msg = "<div class='alert alert-danger alert-dismissible fade show' role='alert'><i class='bi bi-exclamation-circle'></i> File size exceeds 200MB.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
                } else {
                    $original_name = basename($file['name']);
                    $filepath = $upload_dir . time() . "_" . $original_name;
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $submission_file = $filepath;
                    } else {
                        $msg = "<div class='alert alert-danger alert-dismissible fade show' role='alert'><i class='bi bi-exclamation-circle'></i> Error uploading file.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
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

                    $msg = "<div class='alert alert-success alert-dismissible fade show' role='alert'><i class='bi bi-check-circle'></i> Test submitted successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";

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
                    $msg = "<div class='alert alert-danger alert-dismissible fade show' role='alert'><i class='bi bi-exclamation-circle'></i> Error submitting test. Please try again.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
                }
            } else {
                $msg = "<div class='alert alert-danger alert-dismissible fade show' role='alert'><i class='bi bi-exclamation-circle'></i> Please provide either a submission text or file.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
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
    margin-bottom: 35px;
    padding-bottom: 25px;
    border-bottom: 2px solid rgba(0, 217, 255, 0.3);
}

.header h2 {
    font-size: 2rem;
    font-weight: 700;
    background: linear-gradient(135deg, #1e293b 0%, #00d9ff 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 8px;
}

.header p {
    color: #64748b;
    margin: 0;
    font-size: 0.95rem;
}

.breadcrumb-info {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.breadcrumb-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #64748b;
    font-size: 0.95rem;
}

.breadcrumb-item strong {
    color: #1e293b;
}

/* Alert Styles */
.alert {
    border: none;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 25px;
    backdrop-filter: blur(10px);
    border-left: 4px solid;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    border-left-color: #10b981;
    color: #059669;
}

.alert-danger {
    background: rgba(239, 68, 68, 0.1);
    border-left-color: #ef4444;
    color: #dc2626;
}

.alert-warning {
    background: rgba(245, 158, 11, 0.1);
    border-left-color: #f59e0b;
    color: #d97706;
}

.alert i {
    font-size: 1.2rem;
    flex-shrink: 0;
}

/* Card Sections */
.card-section {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 16px;
    padding: 32px;
    margin-bottom: 30px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0, 217, 255, 0.2);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.card-section:hover {
    box-shadow: 0 12px 40px rgba(0, 217, 255, 0.15);
    transform: translateY(-2px);
}

.card-section h3 {
    color: #1e293b;
    margin-bottom: 25px;
    font-weight: 700;
    font-size: 1.4rem;
    display: flex;
    align-items: center;
    gap: 12px;
}

.card-section h3 i {
    color: #00d9ff;
    font-size: 1.6rem;
}

.test-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.detail-item {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 18px;
    border-radius: 12px;
    border-left: 4px solid #00d9ff;
}

.detail-item strong {
    display: block;
    color: #1e293b;
    margin-bottom: 8px;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
}

.detail-item span,
.detail-item p {
    color: #1e293b;
    font-size: 1rem;
    margin: 0;
}

.detail-item a {
    color: #00d9ff;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.detail-item a:hover {
    color: #0099cc;
    text-decoration: underline;
}

.badge {
    font-size: 0.85rem;
    padding: 8px 14px;
    border-radius: 8px;
    font-weight: 600;
    display: inline-block;
}

.badge.bg-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
}

.badge.bg-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
}

.submission-text {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 16px;
    border-radius: 10px;
    border-left: 4px solid #00d9ff;
    color: #1e293b;
    line-height: 1.6;
    max-height: 300px;
    overflow-y: auto;
    margin-bottom: 15px;
}

.no-submission {
    color: #94a3b8;
    font-style: italic;
    padding: 16px;
    background: rgba(148, 163, 184, 0.1);
    border-radius: 10px;
    border-left: 4px solid #94a3b8;
}

/* Form Styles */
.form-section {
    margin-top: 30px;
}

.form-label {
    color: #1e293b;
    font-weight: 600;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-control {
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    color: #1e293b;
}

.form-control:focus {
    border-color: #00d9ff;
    box-shadow: 0 0 0 0.2rem rgba(0, 217, 255, 0.25);
    background-color: #ffffff;
}

.form-control::placeholder {
    color: #94a3b8;
}

textarea.form-control {
    resize: vertical;
    min-height: 120px;
}

/* Buttons */
.btn {
    border-radius: 10px;
    font-weight: 600;
    padding: 12px 24px;
    transition: all 0.3s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, #00d9ff 0%, #0099cc 100%);
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

.btn-secondary {
    background: linear-gradient(135deg, #64748b 0%, #475569 100%);
    color: white;
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #475569 0%, #334155 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(100, 116, 139, 0.3);
    color: white;
}

.button-group {
    display: flex;
    gap: 15px;
    margin-top: 25px;
}

.file-upload-info {
    font-size: 0.85rem;
    color: #64748b;
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

@media (max-width: 768px) {
    .content {
        margin-left: 0;
        padding: 20px;
    }
    
    .header h2 {
        font-size: 1.5rem;
    }
    
    .card-section {
        padding: 20px;
    }
    
    .test-details-grid {
        grid-template-columns: 1fr;
    }
    
    .button-group {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
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
            <h2><i class="bi bi-pencil-square"></i> Submit Test</h2>
            <div class="breadcrumb-info">
                <div class="breadcrumb-item">
                    <i class="bi bi-book"></i>
                    <strong><?= htmlspecialchars($course_name) ?></strong>
                </div>
                <div class="breadcrumb-item">
                    <i class="bi bi-tag"></i>
                    Batch: <strong><?= htmlspecialchars($batch_code) ?></strong>
                </div>
            </div>
        </div>

        <?= $msg ?>

        <!-- Test Details -->
        <div class="card-section">
            <h3><i class="bi bi-file-earmark-check"></i> Test Details</h3>
            
            <div class="test-details-grid">
                <div class="detail-item">
                    <strong><i class="bi bi-card-text"></i> Test Title</strong>
                    <p><?= htmlspecialchars($test['title']) ?></p>
                </div>
                <div class="detail-item">
                    <strong><i class="bi bi-calendar-event"></i> Due Date</strong>
                    <p><?= htmlspecialchars($test['due_date']) ?></p>
                </div>
                <div class="detail-item">
                    <strong><i class="bi bi-star-fill"></i> Max Score</strong>
                    <p><?= htmlspecialchars($test['max_score']) ?> Points</p>
                </div>
            </div>

            <div class="detail-item">
                <strong><i class="bi bi-file-text"></i> Description</strong>
                <p><?= htmlspecialchars($test['description']) ?></p>
            </div>

            <div class="detail-item" style="margin-top: 15px;">
                <strong><i class="bi bi-download"></i> Resource File</strong>
                <?php if ($test['resource_file'] && file_exists($test['resource_file'])): ?>
                    <a href="<?= htmlspecialchars($test['resource_file']) ?>" target="_blank">
                        <i class="bi bi-download"></i> Download Test File
                    </a>
                <?php elseif ($test['resource_file']): ?>
                    <span class="text-danger"><i class="bi bi-exclamation-circle"></i> File not found</span>
                <?php else: ?>
                    <span class="text-muted"><i class="bi bi-file-slash"></i> No file provided</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Submission Status or Form -->
        <?php if ($test['submission_id']):
            $status = ($test['submitted_at'] > $test['due_date'] . ' 23:59:59') ? 'Late' : 'Submitted';
            $badge_class = ($status === 'Late') ? 'bg-danger' : 'bg-success';
        ?>
            <!-- Submission Viewed -->
            <div class="card-section">
                <h3><i class="bi bi-check-circle"></i> Your Submission</h3>
                
                <div style="margin-bottom: 20px;">
                    <strong style="display: block; color: #64748b; font-size: 0.9rem; margin-bottom: 8px;">STATUS</strong>
                    <span class="badge <?= $badge_class ?>">
                        <i class="bi bi-<?= ($status === 'Late') ? 'exclamation-circle' : 'check-circle' ?>"></i>
                        <?= $status ?>
                    </span>
                </div>

                <?php if ($test['submission_text']): ?>
                    <div style="margin-bottom: 20px;">
                        <strong style="display: block; color: #64748b; font-size: 0.9rem; margin-bottom: 12px;">SUBMISSION TEXT</strong>
                        <div class="submission-text">
                            <?= nl2br(htmlspecialchars($test['submission_text'])) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($test['submission_file']): ?>
                    <div style="margin-bottom: 20px;">
                        <strong style="display: block; color: #64748b; font-size: 0.9rem; margin-bottom: 12px;">SUBMITTED FILE</strong>
                        <div class="detail-item">
                            <?php if (file_exists($test['submission_file'])): ?>
                                <a href="<?= htmlspecialchars($test['submission_file']) ?>" target="_blank">
                                    <i class="bi bi-file-earmark"></i> View Submission File
                                </a>
                            <?php else: ?>
                                <span class="text-danger"><i class="bi bi-exclamation-circle"></i> File not found</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                    <strong style="display: block; color: #64748b; font-size: 0.9rem; margin-bottom: 8px;">SUBMITTED AT</strong>
                    <p><?= htmlspecialchars($test['submitted_at']) ?></p>
                </div>
            </div>
        <?php else:
            $today = date('Y-m-d');
            if ($today <= $test['due_date']):
        ?>
            <!-- Submission Form -->
            <div class="card-section">
                <h3><i class="bi bi-pencil-fill"></i> Submit Your Test</h3>
                
                <form method="POST" enctype="multipart/form-data" class="form-section">
                    <input type="hidden" name="action" value="submit_test">
                    
                    <div class="mb-3">
                        <label for="submission_text" class="form-label">
                            <i class="bi bi-type"></i> Submission Text
                        </label>
                        <textarea 
                            name="submission_text" 
                            id="submission_text" 
                            class="form-control" 
                            placeholder="Enter your test answers or response here..."
                        ></textarea>
                        <small class="file-upload-info">Optional: You can submit text, a file, or both</small>
                    </div>

                    <div class="mb-3">
                        <label for="submission_file" class="form-label">
                            <i class="bi bi-file-earmark-upload"></i> Upload File
                        </label>
                        <input 
                            type="file" 
                            name="submission_file" 
                            id="submission_file" 
                            class="form-control" 
                            accept=".pdf,.jpg,.jpeg,.png" 
                        />
                        <small class="file-upload-info">
                            <i class="bi bi-info-circle"></i> Supported: PDF, JPG, PNG (Max 200MB)
                        </small>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Submit Test
                        </button>
                        <a href="course_dashboard.php?course_id=<?= $course_id ?>&batch_id=<?= $batch_id ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Submission Closed -->
            <div class="card-section">
                <div class="no-submission">
                    <i class="bi bi-exclamation-circle"></i> 
                    <strong>Submission Closed</strong> - The submission period for this test has ended. Status: <span class="badge bg-danger">Not Submitted</span>
                </div>
                <div style="margin-top: 25px;">
                    <a href="course_dashboard.php?course_id=<?= $course_id ?>&batch_id=<?= $batch_id ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        <?php endif; endif; ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>