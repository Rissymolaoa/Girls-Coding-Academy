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

// Handle POST request for uploading materials
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_material') {
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
            $file_path = null;

            // Handle file upload
            if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'Uploads/';
                $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                $max_size = 5 * 1024 * 1024; // 5MB
                $file = $_FILES['material_file'];

                if (!in_array($file['type'], $allowed_types)) {
                    echo "<div class='alert alert-danger'>Error: Only PDF, JPG, and PNG files are allowed.</div>";
                } elseif ($file['size'] > $max_size) {
                    echo "<div class='alert alert-danger'>Error: File size exceeds 5MB limit.</div>";
                } else {
                    $file_name = $file['name']; // Preserve original file name
                    $file_path = $upload_dir . $file_name;
                    $full_path = $_SERVER['DOCUMENT_ROOT'] . '/Girls-Coding-Academy/' . $file_path;
                    if (!is_dir($_SERVER['DOCUMENT_ROOT'] . '/Girls-Coding-Academy/Uploads')) {
                        mkdir($_SERVER['DOCUMENT_ROOT'] . '/Girls-Coding-Academy/Uploads', 0755, true);
                    }
                    if (move_uploaded_file($file['tmp_name'], $full_path)) {
                        // File path stored without leading slash
                    } else {
                        echo "<div class='alert alert-danger'>Error: Failed to upload file.</div>";
                    }
                }
            }

            // Insert material only if no errors
            if (!isset($_SESSION['error'])) {
                $materialQuery = $conn->prepare("
                    INSERT INTO materials (batch_id, teacher_id, title, description, file_path, uploaded_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                if (!$materialQuery) {
                    die("Material query preparation failed: " . $conn->error);
                }
                $materialQuery->bind_param("iisss", $batch_id, $teacher_id, $title, $description, $file_path);
                if ($materialQuery->execute()) {
                    echo "<div class='alert alert-success'>Material uploaded successfully.</div>";
                } else {
                    echo "<div class='alert alert-danger'>Error: Failed to upload material. " . $conn->error . "</div>";
                }
                $materialQuery->close();
            }
        }
    }
}

// Fetch available batches for selector
$batchSelectorQuery = $conn->prepare("
    SELECT b.batch_id, b.batch_code, c.courseName
    FROM course_assignments ca
    INNER JOIN batches b ON ca.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    WHERE ca.teacher_id = ?
    ORDER BY b.start_date DESC
");
if (!$batchSelectorQuery) {
    die("Batch selector query preparation failed: " . $conn->error);
}
$batchSelectorQuery->bind_param("i", $teacher_id);
$batchSelectorQuery->execute();
$batchSelectorResult = $batchSelectorQuery->get_result();

// Fetch materials for the selected batch
$materialsByBatch = [];
if ($selected_batch_id) {
    $materialQuery = $conn->prepare("
        SELECT m.material_id, m.title, m.description, m.file_path, m.uploaded_at
        FROM materials m
        WHERE m.batch_id = ?
        ORDER BY m.uploaded_at DESC
    ");
    if (!$materialQuery) {
        die("Material query preparation failed: " . $conn->error);
    }
    $materialQuery->bind_param("i", $selected_batch_id);
    $materialQuery->execute();
    $materialsResult = $materialQuery->get_result();
    $materialsByBatch[$selected_batch_id] = [];
    while ($material = $materialsResult->fetch_assoc()) {
        // Check if file exists without modifying file_path
        $material['file_exists'] = $material['file_path'] && file_exists($_SERVER['DOCUMENT_ROOT'] . '/Girls-Coding-Academy/' . $material['file_path']);
        $materialsByBatch[$selected_batch_id][] = $material;
    }
    $materialQuery->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Materials - Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f9f9f9; color: #333; }
        header { background: #fff; color: #333; padding: 15px 30px; text-align: center; border-bottom: 1px solid #ddd; }
        header h1 { margin: 0; font-size: 22px; }
        header p { font-size: 14px; }
        .container { display: flex; min-height: 90vh; }
        .sidebar {
            width: 240px; background: #1a1a1a; padding: 20px; min-height: 100vh; color: #fff;
        }
        .sidebar h5 { color: #fff; margin-bottom: 15px; font-size: 18px; text-align: center; }
        .sidebar a {
            display: flex; align-items: center; gap: 10px; color: #fff; text-decoration: none;
            padding: 10px; margin: 5px 0; border-radius: 6px; transition: background 0.2s;
        }
        .sidebar a:hover { background: #333; }
        .sidebar a.active { background: #333; }
        .teacher-pic {
            width: 92px; height: 92px; border-radius: 50%; margin-bottom: 15px;
            border: 2px solid #fff; object-fit: cover; display: block; margin-left: auto; margin-right: auto;
        }
        .content { flex: 1; padding: 30px; }
        h2 { margin-bottom: 20px; color: #5a189a; }
        .card {
            background: white; border-radius: 8px; padding: 15px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            border: 1px solid #eee; margin-bottom: 20px;
        }
        .card-header { background: #1a1a1a; color: #fff; padding: 10px; border-radius: 6px 6px 0 0; }
        .card-header h4 { margin: 0; }
        .form-label { font-weight: bold; }
        .form-control, .form-select {
            border: 1px solid #ddd; border-radius: 4px; padding: 8px; width: 100%;
        }
        .form-control:focus, .form-select:focus { border-color: #5a189a; outline: none; }
        .btn-primary {
            background: #5a189a; border: none; padding: 8px 15px; border-radius: 4px; color: #fff;
        }
        .btn-primary:hover { background: #7b2cbf; }
        .table-responsive { margin-top: 10px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        .table th { background: #1a1a1a; color: #fff; }
        .table td a { color: #5a189a; text-decoration: none; }
        .table td a:hover { text-decoration: underline; }
        .alert { padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
    </style>
</head>
<body>
    <header>
        <h1>Girls Coding Academy - Upload Materials</h1>
        <p>Email: <?= htmlspecialchars($teacherInfo['email']) ?> | Gender: <?= htmlspecialchars($teacherInfo['gender']) ?> | Phone: <?= htmlspecialchars($teacherInfo['phone']) ?></p>
    </header>

    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <img src="admin.png" alt="Teacher Picture" class="teacher-pic">
            <h5>Teacher Dashboard</h5>
            <a href="teacher_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
            <a href="manage_teacher_courses.php"><i class="bi bi-journal-bookmark"></i> Manage Courses</a>
            <a href="upload_materials.php" class="active"><i class="bi bi-folder"></i> Upload Materials</a>
            <a href="grades.php"><i class="bi bi-pencil-square"></i> Grade</a>
            <a href="mark_attendance.php"><i class="bi bi-check-circle"></i> Mark Attendance</a>
            <a href="message_students.php"><i class="bi bi-chat-dots"></i> Message Students</a>
            <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>

        <!-- Main Content -->
        <div class="content">
            <h2>Upload Materials</h2>
            <!-- Batch Selection -->
            <form method="POST" class="d-flex mb-4">
                <select name="selected_batch_id" class="form-select me-2" required>
                    <option value="">Select a batch to upload materials</option>
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

            <div class="card">
                <div class="card-header">
                    <h4>Batch: <?= htmlspecialchars($selectedBatchResult['batch_code']) ?> (<?= htmlspecialchars($selectedBatchResult['courseName']) ?>)</h4>
                </div>
                <div class="card-body">
                    <!-- Upload Material Form -->
                    <h5>Upload New Material</h5>
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" class="row g-3">
                                <input type="hidden" name="action" value="upload_material">
                                <input type="hidden" name="batch_id" value="<?= $selected_batch_id ?>">
                                <div class="col-md-6">
                                    <label class="form-label">Title</label>
                                    <input class="form-control" type="text" name="title" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="3" required></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Material File (PDF, JPG, PNG, max 5MB)</label>
                                    <input class="form-control" type="file" name="material_file" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary" type="submit">Upload</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- List Uploaded Materials -->
                    <h5>Uploaded Materials</h5>
                    <?php if (isset($materialsByBatch[$selected_batch_id]) && count($materialsByBatch[$selected_batch_id]) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>File</th>
                                    <th>Uploaded At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materialsByBatch[$selected_batch_id] as $material): ?>
                                <tr>
                                    <td><?= htmlspecialchars($material['title']) ?></td>
                                    <td><?= htmlspecialchars($material['description']) ?></td>
                                    <td>
                                        <?php if ($material['file_path'] && $material['file_exists']): ?>
                                        <a href="/Girls-Coding-Academy/<?= htmlspecialchars($material['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">Download</a>
                                        <?php else: ?>
                                        <span>File not found</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($material['uploaded_at']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">No materials uploaded for this batch.</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-info">Please select a batch to upload and view materials.</div>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        &copy; <?= date('Y') ?> Girls Coding Academy
    </footer>
</body>
</html>