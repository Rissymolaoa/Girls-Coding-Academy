<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'db.php';

// Ensure teacher is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Fetch teacher info
$teacher_info = [];
try {
    $teacher_query = $conn->prepare("SELECT username, email, gender, phone FROM users WHERE user_id = ? AND role = 'teacher'");
    $teacher_query->bind_param("i", $user_id);
    $teacher_query->execute();
    $teacher_info = $teacher_query->get_result()->fetch_assoc();
    $teacher_query->close();

    if (!$teacher_info) {
        die("Teacher profile not found");
    }
} catch (Exception $e) {
    error_log("Error fetching teacher info: " . $e->getMessage());
    die("Error loading teacher profile");
}

// Fetch teacher_id
try {
    $teacher_id_res = $conn->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
    $teacher_id_res->bind_param("i", $user_id);
    $teacher_id_res->execute();
    $res = $teacher_id_res->get_result();
    if ($res->num_rows === 0) {
        die("Teacher profile not set up yet");
    }
    $teacher_id = (int)$res->fetch_assoc()['teacher_id'];
    $teacher_id_res->close();
} catch (Exception $e) {
    error_log("Error fetching teacher ID: " . $e->getMessage());
    die("Error loading teacher profile");
}

// Handle activity update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_activity') {
    $activity_id = filter_input(INPUT_POST, 'activity_id', FILTER_VALIDATE_INT);
    $title = trim($conn->real_escape_string($_POST['title'] ?? ''));
    $description = trim($conn->real_escape_string($_POST['description'] ?? ''));
    $due_date = trim($conn->real_escape_string($_POST['due_date'] ?? ''));
    $status = trim($conn->real_escape_string($_POST['status'] ?? 'active'));
    $resource_file = null;

    if (!$activity_id || !$title || !$description || !$due_date || !in_array($status, ['active', 'inactive'])) {
        echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>All fields are required, and status must be 'active' or 'inactive'.</div>";
    } else {
        if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'Uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
            $file = $_FILES['resource_file'];
            if (!in_array($file['type'], $allowed_types)) {
                echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>Allowed file types: PDF, JPG, PNG.</div>";
            } elseif ($file['size'] > 200 * 1024 * 1024) {
                echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>File size exceeds 200MB.</div>";
            } else {
                $original_name = basename($file['name']);
                $filepath = $upload_dir . $original_name;
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Delete old file if it exists
                    $old_file_query = $conn->prepare("SELECT resource_file FROM activities WHERE activity_id = ?");
                    $old_file_query->bind_param("i", $activity_id);
                    $old_file_query->execute();
                    $old_file = $old_file_query->get_result()->fetch_assoc()['resource_file'];
                    $old_file_query->close();
                    if ($old_file && file_exists($old_file) && $old_file !== $filepath) {
                        unlink($old_file);
                    }
                    $resource_file = $filepath;
                } else {
                    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>Error uploading file. Please try again.</div>";
                }
            }
        }

        if (!isset($error)) {
            try {
                $query = "UPDATE activities SET title = ?, description = ?, due_date = ?, status = ?";
                if ($resource_file) {
                    $query .= ", resource_file = ?";
                }
                $query .= " WHERE activity_id = ?";
                $stmt = $conn->prepare($query);
                $params = [$title, $description, $due_date, $status];
                $types = "ssss";
                if ($resource_file) {
                    $params[] = $resource_file;
                    $types .= "s";
                }
                $params[] = $activity_id;
                $types .= "i";
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $stmt->close();
                echo "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'>Activity updated successfully.</div>";
            } catch (Exception $e) {
                error_log("Error updating activity: " . $e->getMessage());
                echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>Error updating activity: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }
}

// Handle activity deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_activity') {
    $activity_id = filter_input(INPUT_POST, 'activity_id', FILTER_VALIDATE_INT);
    $selected_course_id = filter_input(INPUT_POST, 'selected_course_id', FILTER_VALIDATE_INT);
    if ($activity_id) {
        try {
            // Fetch file to delete
            $file_query = $conn->prepare("SELECT resource_file FROM activities WHERE activity_id = ?");
            $file_query->bind_param("i", $activity_id);
            $file_query->execute();
            $file = $file_query->get_result()->fetch_assoc()['resource_file'];
            $file_query->close();

            $stmt = $conn->prepare("DELETE FROM activities WHERE activity_id = ?");
            $stmt->bind_param("i", $activity_id);
            $stmt->execute();
            $stmt->close();

            // Delete file if it exists
            if ($file && file_exists($file)) {
                unlink($file);
            }
            header("Location: view_assigned_activities.php?course_id=$selected_course_id");
            exit();
        } catch (Exception $e) {
            error_log("Error deleting activity: " . $e->getMessage());
            echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>Error deleting activity: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>Invalid activity ID.</div>";
    }
}

// Handle clear activity (set status to inactive)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_activity') {
    $activity_id = filter_input(INPUT_POST, 'activity_id', FILTER_VALIDATE_INT);
    $selected_course_id = filter_input(INPUT_POST, 'selected_course_id', FILTER_VALIDATE_INT);
    if ($activity_id) {
        try {
            $stmt = $conn->prepare("UPDATE activities SET status = 'inactive' WHERE activity_id = ?");
            $stmt->bind_param("i", $activity_id);
            $stmt->execute();
            $stmt->close();
            header("Location: view_assigned_activities.php?course_id=$selected_course_id");
            exit();
        } catch (Exception $e) {
            error_log("Error clearing activity: " . $e->getMessage());
            echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>Error clearing activity: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>Invalid activity ID.</div>";
    }
}

$selected_course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
$students_by_batch = [];
$activitiesByBatch = [];
$submissionsByActivity = [];

if ($selected_course_id) {
    // Validate batch_id
    try {
        $stmt_check_batch = $conn->prepare("SELECT batch_id FROM batches WHERE batch_id = ?");
        $stmt_check_batch->bind_param("i", $selected_course_id);
        $stmt_check_batch->execute();
        $res_check_batch = $stmt_check_batch->get_result();
        if ($res_check_batch->num_rows === 0) {
            echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>Invalid batch ID selected.</div>";
            $selected_course_id = null;
        }
        $stmt_check_batch->close();
    } catch (Exception $e) {
        error_log("Error validating batch ID: " . $e->getMessage());
        echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>Error validating batch ID: " . htmlspecialchars($e->getMessage()) . "</div>";
        $selected_course_id = null;
    }

    if ($selected_course_id) {
        // Fetch batch details
        try {
            $stmt_batch = $conn->prepare("
                SELECT b.batch_code, c.courseName
                FROM batches b
                INNER JOIN courses c ON b.course_id = c.course_id
                WHERE b.batch_id = ?
            ");
            $stmt_batch->bind_param("i", $selected_course_id);
            $stmt_batch->execute();
            $batch_details = $stmt_batch->get_result()->fetch_assoc();
            $stmt_batch->close();

            if (!$batch_details) {
                echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>Invalid batch selected.</div>";
                $selected_course_id = null;
            }
        } catch (Exception $e) {
            error_log("Error fetching batch details: " . $e->getMessage());
            echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>Error fetching batch details: " . htmlspecialchars($e->getMessage()) . "</div>";
            $selected_course_id = null;
        }

        // Students
        try {
            $stmt_students = $conn->prepare("
                SELECT ce.enrollment_id, ce.batch_id, ce.student_id, u.firstName, u.lastName, u.email, ce.status
                FROM course_enrollments ce
                INNER JOIN students s ON ce.student_id = s.student_id
                INNER JOIN users u ON s.user_id = u.user_id
                WHERE ce.batch_id = ? AND ce.status = 'active'
                ORDER BY u.firstName
            ");
            $stmt_students->bind_param("i", $selected_course_id);
            $stmt_students->execute();
            $res_students = $stmt_students->get_result();
            $students_by_batch[$selected_course_id] = [];
            while ($row = $res_students->fetch_assoc()) {
                $students_by_batch[$selected_course_id][] = $row;
            }
            $stmt_students->close();
        } catch (Exception $e) {
            error_log("Error fetching students: " . $e->getMessage());
            echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>Error fetching students: " . htmlspecialchars($e->getMessage()) . "</div>";
        }

        // Activities
        try {
            $stmt_activities = $conn->prepare("
                SELECT activity_id, title, description, due_date, resource_file, status
                FROM activities
                WHERE batch_id = ?
                ORDER BY created_at DESC
            ");
            $stmt_activities->bind_param("i", $selected_course_id);
            $stmt_activities->execute();
            $res_activities = $stmt_activities->get_result();
            $activitiesByBatch[$selected_course_id] = [];
            while ($row = $res_activities->fetch_assoc()) {
                $activitiesByBatch[$selected_course_id][] = $row;
            }
            $stmt_activities->close();
        } catch (Exception $e) {
            error_log("Error fetching activities: " . $e->getMessage());
            echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>Error fetching activities: " . htmlspecialchars($e->getMessage()) . "</div>";
        }

        // Activity Submissions
        try {
            $stmt_subs = $conn->prepare("
                SELECT s.submission_id, s.activity_id, s.enrollment_id, s.submission_text, s.submission_file, s.submitted_at
                FROM activity_submissions s
                INNER JOIN activities a ON s.activity_id = a.activity_id
                INNER JOIN course_enrollments ce ON s.enrollment_id = ce.enrollment_id AND ce.batch_id = ?
                WHERE ce.batch_id = ?
                ORDER BY s.submitted_at
            ");
            $stmt_subs->bind_param("ii", $selected_course_id, $selected_course_id);
            $stmt_subs->execute();
            $res_subs = $stmt_subs->get_result();
            $submissionsByActivity = [];
            while ($row = $res_subs->fetch_assoc()) {
                $submissionsByActivity[$row['activity_id']][] = $row;
            }
            $stmt_subs->close();
        } catch (Exception $e) {
            error_log("Error fetching activity submissions: " . $e->getMessage());
            echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>Error fetching activity submissions: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Assigned Activities - Girls Coding Academy</title>
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
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
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
                <a href="manage_teacher_courses.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2 active">
                    <i class="fas fa-chalkboard-teacher mr-3"></i>
                    Manage Courses
                </a>
                <a href="schedule_class.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-calendar-alt mr-3"></i>
                    Schedule Class
                </a>
                <a href="upload_materials.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-book mr-3"></i>
                    Upload Materials
                </a>
                <a href="grades.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
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
                <h1 class="text-xl font-semibold">Welcome, <?= htmlspecialchars($teacher_info['username']) ?>!</h1>
                <p class="text-sm">Email: <?= htmlspecialchars($teacher_info['email']) ?> | Gender: <?= htmlspecialchars($teacher_info['gender']) ?> | Phone: <?= htmlspecialchars($teacher_info['phone']) ?></p>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Assigned Activities / Homeworks</h2>
                <a href="manage_teacher_courses.php?course_id=<?= $selected_course_id ?>" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Manage Courses
                </a>
            </div>

            <?php if ($selected_course_id && isset($batch_details)): ?>
                <div class="bg-white rounded-lg shadow-lg mb-6">
                    <div class="gradient-header text-white p-4 rounded-t-lg">
                        <h4 class="text-xl font-semibold">Batch: <?= htmlspecialchars($batch_details['batch_code']) ?> (<?= htmlspecialchars($batch_details['courseName']) ?>)</h4>
                    </div>
                    <div class="p-6">
                        <?php if (!empty($activitiesByBatch[$selected_course_id])): ?>
                            <?php foreach ($activitiesByBatch[$selected_course_id] as $activity): ?>
                                <div class="card bg-white rounded-lg shadow-md mb-6 overflow-hidden">
                                    <div class="bg-blue-600 text-white p-4 flex justify-between items-center">
                                        <h5 class="text-lg font-semibold mb-0">
                                            <?= htmlspecialchars($activity['title']) ?> 
                                            <span class="text-sm">(Due: <?= htmlspecialchars($activity['due_date']) ?>)</span>
                                            <span class="inline-block px-3 py-1 ml-2 rounded-full text-xs font-medium <?= $activity['status'] === 'active' ? 'bg-green-500' : 'bg-gray-500' ?>">
                                                <?= htmlspecialchars(ucfirst($activity['status'])) ?>
                                            </span>
                                        </h5>
                                        <div class="flex gap-2">
                                            <button class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-1 px-3 rounded text-sm transition" onclick="toggleEditForm('activity_<?= $activity['activity_id'] ?>')">
                                                <i class="fas fa-edit mr-1"></i>Edit
                                            </button>
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this activity?');">
                                                <input type="hidden" name="action" value="delete_activity">
                                                <input type="hidden" name="activity_id" value="<?= $activity['activity_id'] ?>">
                                                <input type="hidden" name="selected_course_id" value="<?= $selected_course_id ?>">
                                                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded text-sm transition">
                                                    <i class="fas fa-trash mr-1"></i>Delete
                                                </button>
                                            </form>
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to clear this activity?');">
                                                <input type="hidden" name="action" value="clear_activity">
                                                <input type="hidden" name="activity_id" value="<?= $activity['activity_id'] ?>">
                                                <input type="hidden" name="selected_course_id" value="<?= $selected_course_id ?>">
                                                <button type="submit" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-1 px-3 rounded text-sm transition">
                                                    <i class="fas fa-times mr-1"></i>Clear
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="p-6">
                                        <p class="text-gray-700 mb-4"><?= htmlspecialchars($activity['description']) ?></p>
                                        <?php if ($activity['resource_file'] && file_exists($activity['resource_file'])): ?>
                                            <a href="<?= htmlspecialchars($activity['resource_file']) ?>" target="_blank" class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded mb-4 transition">
                                                <i class="fas fa-file-download mr-2"></i>View Resource File
                                            </a>
                                        <?php elseif ($activity['resource_file']): ?>
                                            <p class="text-red-500 mb-4">Resource file not found: <?= htmlspecialchars($activity['resource_file']) ?></p>
                                        <?php endif; ?>
                                        
                                        <!-- Edit Form -->
                                        <form method="POST" enctype="multipart/form-data" class="mt-4 p-4 bg-gray-50 rounded-lg" id="activity_<?= $activity['activity_id'] ?>" style="display: none;">
                                            <input type="hidden" name="action" value="update_activity">
                                            <input type="hidden" name="activity_id" value="<?= $activity['activity_id'] ?>">
                                            <input type="hidden" name="batch_id" value="<?= $selected_course_id ?>">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                                <div>
                                                    <label class="block text-gray-700 font-semibold mb-2">Title</label>
                                                    <input class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-purple-600" type="text" name="title" value="<?= htmlspecialchars($activity['title']) ?>" required>
                                                </div>
                                                <div>
                                                    <label class="block text-gray-700 font-semibold mb-2">Due Date</label>
                                                    <input class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-purple-600" type="date" name="due_date" value="<?= htmlspecialchars($activity['due_date']) ?>" required>
                                                </div>
                                                <div>
                                                    <label class="block text-gray-700 font-semibold mb-2">Status</label>
                                                    <select class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-purple-600" name="status" required>
                                                        <option value="active" <?= $activity['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                        <option value="inactive" <?= $activity['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="mb-4">
                                                <label class="block text-gray-700 font-semibold mb-2">Description</label>
                                                <textarea class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-purple-600" name="description" rows="3" required><?= htmlspecialchars($activity['description']) ?></textarea>
                                            </div>
                                            <div class="mb-4">
                                                <label class="block text-gray-700 font-semibold mb-2">Resource File (PDF, JPG, PNG, max 200MB)</label>
                                                <input class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-purple-600" type="file" name="resource_file" accept=".pdf,.jpg,.jpeg,.png">
                                                <?php if ($activity['resource_file']): ?>
                                                    <p class="text-sm text-gray-600 mt-2">Current file: <a href="<?= htmlspecialchars($activity['resource_file']) ?>" target="_blank" class="text-blue-600 hover:underline">View Current File</a></p>
                                                <?php endif; ?>
                                            </div>
                                            <button class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-6 rounded transition" type="submit">
                                                <i class="fas fa-save mr-2"></i>Update Activity
                                            </button>
                                        </form>
                                        
                                        <h6 class="text-lg font-semibold text-gray-800 mt-6 mb-4">Student Submissions</h6>
                                        <div class="overflow-x-auto">
                                            <table class="w-full bg-white rounded-lg overflow-hidden">
                                                <thead class="bg-gray-800 text-white">
                                                    <tr>
                                                        <th class="py-3 px-4 text-left">Student</th>
                                                        <th class="py-3 px-4 text-left">Status</th>
                                                        <th class="py-3 px-4 text-left">Submitted At</th>
                                                        <th class="py-3 px-4 text-left">Details</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200">
                                                    <?php
                                                    $activity_subs = $submissionsByActivity[$activity['activity_id']] ?? [];
                                                    $today = date('Y-m-d');
                                                    foreach ($students_by_batch[$selected_course_id] as $student):
                                                        $submission = null;
                                                        foreach ($activity_subs as $sub) {
                                                            if ($sub['enrollment_id'] == $student['enrollment_id']) {
                                                                $submission = $sub;
                                                                break;
                                                            }
                                                        }
                                                        $status_text = 'Pending';
                                                        $badge_class = 'bg-gray-500';
                                                        if ($submission) {
                                                            if ($submission['submitted_at'] > $activity['due_date'] . ' 23:59:59') {
                                                                $status_text = 'Late';
                                                                $badge_class = 'bg-red-500';
                                                            } else {
                                                                $status_text = 'Submitted';
                                                                $badge_class = 'bg-green-500';
                                                            }
                                                        } else {
                                                            if ($today > $activity['due_date']) {
                                                                $status_text = 'Not Submitted';
                                                                $badge_class = 'bg-red-500';
                                                            } else {
                                                                $status_text = 'Pending';
                                                                $badge_class = 'bg-yellow-500';
                                                            }
                                                        }
                                                    ?>
                                                        <tr class="hover:bg-gray-50 transition-colors">
                                                            <td class="py-3 px-4 font-medium text-gray-800"><?= htmlspecialchars($student['firstName'] . ' ' . $student['lastName']) ?></td>
                                                            <td class="py-3 px-4">
                                                                <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold text-white <?= $badge_class ?>">
                                                                    <?= $status_text ?>
                                                                </span>
                                                            </td>
                                                            <td class="py-3 px-4 text-gray-600"><?= $submission ? htmlspecialchars($submission['submitted_at']) : '-' ?></td>
                                                            <td class="py-3 px-4">
                                                                <?php if ($submission): ?>
                                                                    <?php if ($submission['submission_text']): ?>
                                                                        <p class="text-gray-700 mb-2"><?= htmlspecialchars($submission['submission_text']) ?></p>
                                                                    <?php endif; ?>
                                                                    <?php if ($submission['submission_file'] && file_exists($submission['submission_file'])): ?>
                                                                        <a href="<?= htmlspecialchars($submission['submission_file']) ?>" target="_blank" class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-1 px-3 rounded text-sm transition">
                                                                            <i class="fas fa-file mr-1"></i>View File
                                                                        </a>
                                                                    <?php elseif ($submission['submission_file']): ?>
                                                                        <p class="text-red-500 text-sm">Submission file not found</p>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <p class="text-gray-500 italic">No submission</p>
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
                            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded">
                                <i class="fas fa-info-circle mr-2"></i>No activities assigned for this batch.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded">
                    <i class="fas fa-info-circle mr-2"></i>Please select a valid course to view activities.
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('mobile-open');
            
            // Close sidebar when clicking outside on mobile
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
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }
    </script>
</body>
</html>