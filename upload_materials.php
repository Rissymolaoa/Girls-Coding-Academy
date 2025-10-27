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
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>All fields are required for material upload.</div>";
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
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>Error: Selected batch does not exist.</div>";
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
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>Allowed file types: PDF, JPG, PNG.</div>";
                } elseif ($file['size'] > 200 * 1024 * 1024) {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>File size exceeds 200MB.</div>";
                } else {
                    $original_name = basename($file['name']);
                    $file_path = $upload_dir . $original_name;
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        // File path stored as Uploads/filename
                    } else {
                        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>Error uploading file. Please try again.</div>";
                    }
                }
            }

            if (!isset($message)) {
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
                    $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center'>Material uploaded successfully. <i class='fas fa-check-circle ml-2'></i></div>";
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>Error uploading material: " . htmlspecialchars($conn->error) . "</div>";
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
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>All fields are required for material update.</div>";
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
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>Allowed file types: PDF, JPG, PNG.</div>";
            } elseif ($file['size'] > 200 * 1024 * 1024) {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>File size exceeds 200MB.</div>";
            } else {
                $original_name = basename($file['name']);
                $file_path = $upload_dir . $original_name;
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    // Delete old file if it exists and is different
                    if ($existingFileResult['file_path'] && file_exists($existingFileResult['file_path']) && $existingFileResult['file_path'] !== $file_path) {
                        unlink($existingFileResult['file_path']);
                    }
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>Error uploading file. Please try again.</div>";
                }
            }
        }

        if (!isset($message)) {
            $query = "UPDATE materials SET title = ?, description = ?, file_path = ? WHERE material_id = ?";
            $materialUpdateQuery = $conn->prepare($query);
            if (!$materialUpdateQuery) {
                die("Material update query preparation failed: " . $conn->error);
            }
            $materialUpdateQuery->bind_param("sssi", $title, $description, $file_path, $material_id);
            if ($materialUpdateQuery->execute()) {
                $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center'>Material updated successfully. <i class='fas fa-check-circle ml-2'></i></div>";
            } else {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>Error updating material: " . htmlspecialchars($conn->error) . "</div>";
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
            $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center'>Material deleted successfully. <i class='fas fa-check-circle ml-2'></i></div>";
            header("Location: upload_materials.php?selected_batch_id=$selected_batch_id");
            exit();
        } else {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>Error deleting material: " . htmlspecialchars($conn->error) . "</div>";
        }
        $deleteQuery->close();
    } else {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>Invalid material ID.</div>";
    }
}

// Fetch available batches for selector
$batchSelectorQuery = $conn->prepare("
    SELECT b.batch_id, b.batch_code, c.courseName, c.title as courseCode
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
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .gradient-header {
            background: linear-gradient(90deg, #7b2cbf, #5a189a);
        }
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #7b2cbf, #5a189a);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        .sidebar.hidden {
            transform: translateX(-100%);
        }
        .sidebar-link {
            transition: all 0.3s ease;
        }
        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.1);
            padding-left: 1.5rem;
        }
        .sidebar-link.active {
            background: rgba(255, 255, 255, 0.2);
            border-left: 4px solid white;
        }
        .main-content {
            margin-left: 250px;
            transition: margin-left 0.3s ease;
        }
        .main-content.expanded {
            margin-left: 0;
        }
        .batch-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .batch-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(123, 44, 191, 0.2);
            border-color: #7b2cbf;
        }
        .batch-card.selected {
            border-color: #7b2cbf;
            background: linear-gradient(135deg, #f3e7ff 0%, #ffffff 100%);
        }
        .mobile-toggle {
            display: none;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .mobile-toggle {
                display: block;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="p-6">
            <div class="flex items-center mb-8">
                <i class="fas fa-graduation-cap text-white text-3xl mr-3"></i>
                <h2 class="text-white text-xl font-bold">GCA Portal</h2>
            </div>
            
            <nav>
                <a href="teacher_dashboard.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-home mr-3"></i>
                    Dashboard
                </a>
                <a href="manage_teacher_courses.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-chalkboard-teacher mr-3"></i>
                    Manage Courses
                </a>
                <a href="upload_materials.php" class="sidebar-link flex active items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-book mr-3"></i>
                    Upload Materials
                </a>
                <a href="grades.php" class="sidebar-link  flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-clipboard-check mr-3"></i>
                    Grade
                </a>
                <a href="mark_attendance.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-calendar-check mr-3"></i>
                    Mark Attendance
                </a>
                <a href="message_students.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-envelope mr-3"></i>
                    Message Students
                </a>
                <a href="teacher_profile.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-user mr-3"></i>
                    Profile
                </a>
                <a href="logout.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Logout
                </a>
            </nav>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Header -->
        <header class="gradient-header text-white py-4 px-6 flex justify-between items-center">
            <button class="mobile-toggle text-white text-2xl" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 class="text-xl font-semibold">Welcome, <?= htmlspecialchars($teacherInfo['username']) ?>!</h1>
                <p class="text-sm">Email: <?= htmlspecialchars($teacherInfo['email']) ?> | Gender: <?= htmlspecialchars($teacherInfo['gender']) ?> | Phone: <?= htmlspecialchars($teacherInfo['phone']) ?></p>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="p-6">
            <?php if (isset($message)): echo $message; endif; ?>

            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Upload Materials</h2>
                <p class="text-gray-600">Select a batch to upload and manage materials</p>
            </div>

            <!-- Batch Selection Cards -->
            <div class="mb-8">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-folder-open mr-2 text-purple-600"></i>Select Batch
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php 
                    $batchSelectorResult->data_seek(0);
                    while ($batch = $batchSelectorResult->fetch_assoc()): 
                    ?>
                        <a href="?selected_batch_id=<?= $batch['batch_id'] ?>" 
                           class="batch-card bg-white rounded-lg shadow-lg p-6 no-underline <?= $batch['batch_id'] == $selected_batch_id ? 'selected' : '' ?>">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <h4 class="text-lg font-semibold text-gray-800 mb-1">
                                        <?= htmlspecialchars($batch['courseCode']) ?>
                                    </h4>
                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($batch['courseName']) ?></p>
                                </div>
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Active
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-book mr-2 text-purple-600"></i>
                                    <span class="text-sm">Materials</span>
                                </div>
                                <div class="text-purple-600">
                                    <span class="text-xs font-medium"><?= htmlspecialchars($batch['batch_code']) ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Upload and Materials Management -->
            <?php if ($selected_batch_id): ?>
                <?php
                // Fetch selected batch details
                $selectedBatchQuery = $conn->prepare("
                    SELECT b.batch_code, c.courseName, c.title as courseCode
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

                <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-gray-800">
                            <i class="fas fa-book mr-2 text-purple-600"></i>Batch: <?= htmlspecialchars($selectedBatchResult['batch_code']) ?> (<?= htmlspecialchars($selectedBatchResult['courseName']) ?>)
                        </h3>
                    </div>

                    <!-- Upload Material Form -->
                    <div class="mb-8">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4">Upload New Material</h4>
                        <form method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="action" value="upload_material">
                            <input type="hidden" name="batch_id" value="<?= $selected_batch_id ?>">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                                    <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                    <textarea name="description" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Material File (PDF, JPG, PNG, max 200MB)</label>
                                <input type="file" name="material_file" accept=".pdf,.jpg,.jpeg,.png" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-md font-medium hover:bg-purple-700 transition-colors flex items-center">
                                    <i class="fas fa-upload mr-2"></i>
                                    Upload
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- List Uploaded Materials -->
                    <h4 class="text-lg font-semibold text-gray-800 mb-4">Uploaded Materials</h4>
                    <?php if (isset($materialsByBatch[$selected_batch_id]) && count($materialsByBatch[$selected_batch_id]) > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uploaded At</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($materialsByBatch[$selected_batch_id] as $material): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($material['title']) ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($material['description']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($material['file_path'] && $material['file_exists']): ?>
                                                    <a href="<?= htmlspecialchars($material['file_path']) ?>" target="_blank" class="inline-flex items-center px-3 py-1 bg-purple-100 text-purple-800 text-sm font-medium rounded-md hover:bg-purple-200">
                                                        <i class="fas fa-download mr-1"></i>Download
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-sm text-gray-500">File not found</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= htmlspecialchars($material['uploaded_at']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="toggleEditForm('material_<?= $material['material_id'] ?>')" class="text-yellow-600 hover:text-yellow-900 mr-3">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this material?');">
                                                    <input type="hidden" name="action" value="delete_material">
                                                    <input type="hidden" name="material_id" value="<?= $material['material_id'] ?>">
                                                    <input type="hidden" name="selected_batch_id" value="<?= $selected_batch_id ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <tr id="material_<?= $material['material_id'] ?>" class="hidden">
                                            <td colspan="5" class="p-4 bg-gray-50">
                                                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                                                    <input type="hidden" name="action" value="update_material">
                                                    <input type="hidden" name="material_id" value="<?= $material['material_id'] ?>">
                                                    <input type="hidden" name="batch_id" value="<?= $selected_batch_id ?>">
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                        <div>
                                                            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                                                            <input type="text" name="title" value="<?= htmlspecialchars($material['title']) ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                                                        </div>
                                                        <div>
                                                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                                            <textarea name="description" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"><?= htmlspecialchars($material['description']) ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Material File (PDF, JPG, PNG, max 200MB)</label>
                                                        <input type="file" name="material_file" accept=".pdf,.jpg,.jpeg,.png" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                                                        <?php if ($material['file_path'] && $material['file_exists']): ?>
                                                            <p class="text-sm text-gray-500 mt-1">Current file: <a href="<?= htmlspecialchars($material['file_path']) ?>" target="_blank" class="text-purple-600 hover:underline">View Current File</a></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="flex justify-end space-x-3">
                                                        <button type="button" onclick="toggleEditForm('material_<?= $material['material_id'] ?>')" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md font-medium hover:bg-gray-400">Cancel</button>
                                                        <button type="submit" class="px-6 py-2 bg-yellow-600 text-white rounded-md font-medium hover:bg-yellow-700">Update Material</button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="bg-blue-50 border border-blue-200 text-blue-800 px-6 py-4 rounded-lg flex items-center">
                            <i class="fas fa-info-circle mr-3 text-xl"></i>
                            <p>No materials uploaded for this batch yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="bg-blue-50 border border-blue-200 text-blue-800 px-6 py-4 rounded-lg flex items-center">
                    <i class="fas fa-info-circle mr-3 text-xl"></i>
                    <p>Please select a batch to upload and view materials.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('mobile-open');
            
            if (sidebar.classList.contains('mobile-open')) {
                document.addEventListener('click', closeSidebarOnClickOutside);
            } else {
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        }

        function closeSidebarOnClickOutside(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = event.target.closest('.mobile-toggle');
            
            if (!sidebar.contains(event.target) && !toggleBtn) {
                sidebar.classList.remove('mobile-open');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        }

        function toggleEditForm(id) {
            var form = document.getElementById(id);
            form.classList.toggle('hidden');
        }
    </script>
</body>
</html>