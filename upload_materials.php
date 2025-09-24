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

$user_id = (int)$_SESSION['user_id'];

// Fetch teacher info from users table
$teacherQuery = $conn->prepare("SELECT username, email, gender, phone FROM users WHERE user_id = ? AND role = 'teacher'");
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

// Handle material upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_material') {
    $batch_id = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
    $title = trim($conn->real_escape_string($_POST['title'] ?? ''));
    $description = trim($conn->real_escape_string($_POST['description'] ?? ''));
    $file_path = null;

    if (!$batch_id || !$title || !$description) {
        echo "<div class='alert alert-danger'>All fields are required for material upload.</div>";
    } else {
        // Validate batch_id exists
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

            // Handle file upload
            if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'Uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                $file = $_FILES['material_file'];
                if (!in_array($file['type'], $allowed_types)) {
                    echo "<div class='alert alert-danger'>Allowed file types: PDF, JPG, PNG.</div>";
                } elseif ($file['size'] > 200 * 1024 * 1024) {
                    echo "<div class='alert alert-danger'>File size exceeds 200MB.</div>";
                } else {
                    $original_name = basename($file['name']);
                    $file_path = $upload_dir . $original_name;
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        // File path stored as Uploads/filename
                    } else {
                        echo "<div class='alert alert-danger'>Error uploading file. Please try again.</div>";
                    }
                }
            }

            if (!isset($error)) {
                $materialQuery = $conn->prepare("
                    INSERT INTO materials (batch_id, teacher_id, title, description, file_path, uploaded_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                if (!$materialQuery) {
                    die("Material query preparation failed: " . $conn->error);
                }
                $file_path = $file_path ?? '';
                $materialQuery->bind_param("iisss", $batch_id, $teacher_id, $title, $description, $file_path);
                if ($materialQuery->execute()) {
                    echo "<div class='alert alert-success'>Material uploaded successfully.</div>";
                } else {
                    echo "<div class='alert alert-danger'>Error uploading material: " . htmlspecialchars($conn->error) . "</div>";
                }
                $materialQuery->close();
            }
        }
    }
}

// Handle material update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_material') {
    $material_id = filter_input(INPUT_POST, 'material_id', FILTER_VALIDATE_INT);
    $batch_id = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
    $title = trim($conn->real_escape_string($_POST['title'] ?? ''));
    $description = trim($conn->real_escape_string($_POST['description'] ?? ''));
    $file_path = null;

    if (!$material_id || !$batch_id || !$title || !$description) {
        echo "<div class='alert alert-danger'>All fields are required for material update.</div>";
    } else {
        // Fetch existing file path to preserve if no new file is uploaded
        $existingFileQuery = $conn->prepare("SELECT file_path FROM materials WHERE material_id = ?");
        if (!$existingFileQuery) {
            die("Existing file query preparation failed: " . $conn->error);
        }
        $existingFileQuery->bind_param("i", $material_id);
        $existingFileQuery->execute();
        $existingFileResult = $existingFileQuery->get_result()->fetch_assoc();
        $file_path = $existingFileResult['file_path'] ?? '';
        $existingFileQuery->close();

        // Handle file upload
        if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'Uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
            $file = $_FILES['material_file'];
            if (!in_array($file['type'], $allowed_types)) {
                echo "<div class='alert alert-danger'>Allowed file types: PDF, JPG, PNG.</div>";
            } elseif ($file['size'] > 200 * 1024 * 1024) {
                echo "<div class='alert alert-danger'>File size exceeds 200MB.</div>";
            } else {
                $original_name = basename($file['name']);
                $file_path = $upload_dir . $original_name;
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    // Delete old file if it exists and is different
                    if ($existingFileResult['file_path'] && file_exists($existingFileResult['file_path']) && $existingFileResult['file_path'] !== $file_path) {
                        unlink($existingFileResult['file_path']);
                    }
                } else {
                    echo "<div class='alert alert-danger'>Error uploading file. Please try again.</div>";
                }
            }
        }

        if (!isset($error)) {
            $query = "UPDATE materials SET title = ?, description = ?, file_path = ? WHERE material_id = ?";
            $materialUpdateQuery = $conn->prepare($query);
            if (!$materialUpdateQuery) {
                die("Material update query preparation failed: " . $conn->error);
            }
            $materialUpdateQuery->bind_param("sssi", $title, $description, $file_path, $material_id);
            if ($materialUpdateQuery->execute()) {
                echo "<div class='alert alert-success'>Material updated successfully.</div>";
            } else {
                echo "<div class='alert alert-danger'>Error updating material: " . htmlspecialchars($conn->error) . "</div>";
            }
            $materialUpdateQuery->close();
        }
    }
}

// Handle material deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_material') {
    $material_id = filter_input(INPUT_POST, 'material_id', FILTER_VALIDATE_INT);
    $selected_batch_id = filter_input(INPUT_POST, 'selected_batch_id', FILTER_VALIDATE_INT);
    if ($material_id) {
        // Fetch file path to delete the file
        $fileQuery = $conn->prepare("SELECT file_path FROM materials WHERE material_id = ?");
        if (!$fileQuery) {
            die("File query preparation failed: " . $conn->error);
        }
        $fileQuery->bind_param("i", $material_id);
        $fileQuery->execute();
        $fileResult = $fileQuery->get_result()->fetch_assoc();
        $fileQuery->close();

        // Delete the material record
        $deleteQuery = $conn->prepare("DELETE FROM materials WHERE material_id = ?");
        if (!$deleteQuery) {
            die("Delete query preparation failed: " . $conn->error);
        }
        $deleteQuery->bind_param("i", $material_id);
        if ($deleteQuery->execute()) {
            // Delete the file from server if it exists
            if ($fileResult['file_path'] && file_exists($fileResult['file_path'])) {
                unlink($fileResult['file_path']);
            }
            echo "<div class='alert alert-success'>Material deleted successfully.</div>";
            header("Location: upload_materials.php?selected_batch_id=$selected_batch_id");
            exit();
        } else {
            echo "<div class='alert alert-danger'>Error deleting material: " . htmlspecialchars($conn->error) . "</div>";
        }
        $deleteQuery->close();
    } else {
        echo "<div class='alert alert-danger'>Invalid material ID.</div>";
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
$selected_batch_id = filter_input(INPUT_POST, 'selected_batch_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'selected_batch_id', FILTER_VALIDATE_INT);
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
        $material['file_exists'] = $material['file_path'] && file_exists($material['file_path']);
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: Inter, Arial, sans-serif; background: #f4f4f8; }
        header { background: linear-gradient(90deg, #7b2cbf, #5a189a); color: #fff; padding: 15px 30px; text-align: center; }
        header h1 { margin: 0; font-size: 22px; }
        header p { font-size: 14px; margin-bottom: 0; }
        .container { display: flex; min-height: calc(100vh - 70px); }
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
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); border: 1px solid #eee; margin-bottom: 20px; }
        .card-header { background: #1a1a1a; color: #fff; padding: 10px; border-radius: 6px 6px 0 0; }
        .card-header h4 { margin: 0; }
        .form-label { font-weight: bold; }
        .form-control, .form-select { border: 1px solid #ddd; border-radius: 4px; padding: 8px; width: 100%; }
        .form-control:focus, .form-select:focus { border-color: #5a189a; outline: none; }
        .btn-primary { background: #5a189a; border: none; padding: 8px 15px; border-radius: 4px; color: #fff; }
        .btn-primary:hover { background: #7b2cbf; }
        .btn-warning { background: #ffc107; border: none; padding: 8px 15px; border-radius: 4px; color: #000; }
        .btn-warning:hover { background: #e0a800; }
        .btn-danger { background: #dc3545; border: none; padding: 8px 15px; border-radius: 4px; color: #fff; }
        .btn-danger:hover { background: #c82333; }
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
        <h1>Welcome, <?= htmlspecialchars($teacherInfo['username']) ?></h1>
        <p>Email: <?= htmlspecialchars($teacherInfo['email']) ?> | Gender: <?= htmlspecialchars($teacherInfo['gender']) ?> | Phone: <?= htmlspecialchars($teacherInfo['phone']) ?></p>
    </header>

    <div class="container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <img src="admin.png" alt="Teacher Picture" class="teacher-pic">
            <h5>Teacher Dashboard</h5>
            <a href="teacher_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
            <a href="manage_teacher_courses.php"><i class="bi bi-journal-bookmark"></i> Manage Courses</a>
            <a href="upload_materials.php" class="active"><i class="bi bi-folder"></i> Upload Materials</a>
            <a href="grades.php"><i class="bi bi-pencil-square"></i> Grade</a>
            <a href="mark_attendance.php"><i class="bi bi-check-circle"></i> Mark Attendance</a>
            <a href="message_students.php"><i class="bi bi-chat-dots"></i> Message Students</a>
            <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>

        <!-- Main Content -->
        <div class="content">
            <h2>Upload Materials</h2>
            <!-- Batch Selection -->
            <form method="POST" class="d-flex mb-4">
                <select name="selected_batch_id" class="form-select me-2" onchange="this.form.submit()" required>
                    <option value="">Select a batch to upload materials</option>
                    <?php while ($row = $batchSelectorResult->fetch_assoc()): ?>
                    <option value="<?= $row['batch_id'] ?>" <?= $selected_batch_id === $row['batch_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['batch_code']) ?> (<?= htmlspecialchars($row['courseName']) ?>)
                    </option>
                    <?php endwhile; ?>
                    <?php $batchSelectorResult->data_seek(0); // Reset result pointer ?>
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
            if (!$selectedBatchQuery) {
                die("Selected batch query preparation failed: " . $conn->error);
            }
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
                                    <label class="form-label">Material File (PDF, JPG, PNG, max 200MB)</label>
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
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materialsByBatch[$selected_batch_id] as $material): ?>
                                <tr>
                                    <td><?= htmlspecialchars($material['title']) ?></td>
                                    <td><?= htmlspecialchars($material['description']) ?></td>
                                    <td>
                                        <?php if ($material['file_path'] && $material['file_exists']): ?>
                                        <a href="<?= htmlspecialchars($material['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">Download</a>
                                        <?php else: ?>
                                        <span>File not found</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($material['uploaded_at']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning me-2" onclick="toggleEditForm('material_<?= $material['material_id'] ?>')">Edit</button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this material?');">
                                            <input type="hidden" name="action" value="delete_material">
                                            <input type="hidden" name="material_id" value="<?= $material['material_id'] ?>">
                                            <input type="hidden" name="selected_batch_id" value="<?= $selected_batch_id ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="5">
                                        <form method="POST" enctype="multipart/form-data" class="row g-3 mt-3" id="material_<?= $material['material_id'] ?>" style="display: none;">
                                            <input type="hidden" name="action" value="update_material">
                                            <input type="hidden" name="material_id" value="<?= $material['material_id'] ?>">
                                            <input type="hidden" name="batch_id" value="<?= $selected_batch_id ?>">
                                            <div class="col-md-6">
                                                <label class="form-label">Title</label>
                                                <input class="form-control" type="text" name="title" value="<?= htmlspecialchars($material['title']) ?>" required>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Description</label>
                                                <textarea class="form-control" name="description" rows="3" required><?= htmlspecialchars($material['description']) ?></textarea>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Material File (PDF, JPG, PNG, max 200MB)</label>
                                                <input class="form-control" type="file" name="material_file" accept=".pdf,.jpg,.jpeg,.png">
                                                <?php if ($material['file_path'] && $material['file_exists']): ?>
                                                <p>Current file: <a href="<?= htmlspecialchars($material['file_path']) ?>" target="_blank">View Current File</a></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-12">
                                                <button class="btn btn-warning" type="submit">Update Material</button>
                                            </div>
                                        </form>
                                    </td>
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

    <footer class="bg-dark text-white text-center py-3">
        &copy; <?= date('Y') ?> Girls Coding Academy
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleEditForm(id) {
            var form = document.getElementById(id);
            form.style.display = (form.style.display === 'none' || form.style.display === '') ? 'block' : 'none';
        }
    </script>
</body>
</html>