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

// Handle student removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_student'])) {
    $enrollment_id = filter_input(INPUT_POST, 'enrollment_id', FILTER_VALIDATE_INT);
    $selected_course_id = filter_input(INPUT_POST, 'selected_course_id', FILTER_VALIDATE_INT);
    if ($enrollment_id && $selected_course_id) {
        try {
            $stmt = $conn->prepare("UPDATE course_enrollments SET status = 'inactive' WHERE enrollment_id = ?");
            $stmt->bind_param("i", $enrollment_id);
            $stmt->execute();
            $stmt->close();
            header("Location: manage_teacher_courses.php?course_id=$selected_course_id");
            exit();
        } catch (Exception $e) {
            error_log("Error removing student: " . $e->getMessage());
            echo "<div class='alert alert-danger'>Error removing student: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>Invalid input for student removal.</div>";
    }
}

// Handle activity assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_activity') {
    $batch_id = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
    $title = trim($conn->real_escape_string($_POST['title'] ?? ''));
    $description = trim($conn->real_escape_string($_POST['description'] ?? ''));
    $due_date = trim($conn->real_escape_string($_POST['due_date'] ?? ''));
    $resource_file = null;

    if (!$batch_id || !$title || !$description || !$due_date) {
        echo "<div class='alert alert-danger'>All fields are required for activity assignment.</div>";
    } else {
        if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'Uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
            $file = $_FILES['resource_file'];
            if (!in_array($file['type'], $allowed_types)) {
                echo "<div class='alert alert-danger'>Allowed file types: PDF, JPG, PNG.</div>";
            } elseif ($file['size'] > 200 * 1024 * 1024) {
                echo "<div class='alert alert-danger'>File size exceeds 200MB.</div>";
            } else {
                $original_name = basename($file['name']);
                $filepath = $upload_dir . $original_name;
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $resource_file = $filepath;
                } else {
                    echo "<div class='alert alert-danger'>Error uploading file. Please try again.</div>";
                }
            }
        }

        if (!isset($error)) {
            try {
                $stmt = $conn->prepare("INSERT INTO activities (batch_id, teacher_id, title, description, due_date, resource_file, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $resource_file = $resource_file ?? '';
                $stmt->bind_param("iissss", $batch_id, $teacher_id, $title, $description, $due_date, $resource_file);
                $stmt->execute();
                $stmt->close();
                echo "<div class='alert alert-success'>Activity assigned successfully.</div>";
            } catch (Exception $e) {
                error_log("Error assigning activity: " . $e->getMessage());
                echo "<div class='alert alert-danger'>Error assigning activity: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }
}

// Handle test assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_test') {
    $batch_id = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
    $title = trim($conn->real_escape_string($_POST['title'] ?? ''));
    $description = trim($conn->real_escape_string($_POST['description'] ?? ''));
    $due_date = trim($conn->real_escape_string($_POST['due_date'] ?? ''));
    $max_score = filter_var($_POST['max_score'] ?? 0, FILTER_VALIDATE_FLOAT);
    $resource_file = null;

    if (!$batch_id || !$title || !$description || !$due_date || $max_score === false || $max_score <= 0 || $max_score > 100) {
        echo "<div class='alert alert-danger'>All fields are required, and max score must be between 0 and 100.</div>";
    } else {
        if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'Uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
            $file = $_FILES['resource_file'];
            if (!in_array($file['type'], $allowed_types)) {
                echo "<div class='alert alert-danger'>Allowed file types: PDF, JPG, PNG.</div>";
            } elseif ($file['size'] > 200 * 1024 * 1024) {
                echo "<div class='alert alert-danger'>File size exceeds 200MB.</div>";
            } else {
                $original_name = basename($file['name']);
                $filepath = $upload_dir . $original_name;
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $resource_file = $filepath;
                } else {
                    echo "<div class='alert alert-danger'>Error uploading file. Please try again.</div>";
                }
            }
        }

        if (!isset($error)) {
            try {
                $stmt = $conn->prepare("INSERT INTO tests (batch_id, teacher_id, title, description, due_date, max_score, resource_file, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $resource_file = $resource_file ?? '';
                $stmt->bind_param("iisssds", $batch_id, $teacher_id, $title, $description, $due_date, $max_score, $resource_file);
                $stmt->execute();
                $stmt->close();
                echo "<div class='alert alert-success'>Test assigned successfully.</div>";
            } catch (Exception $e) {
                error_log("Error assigning test: " . $e->getMessage());
                echo "<div class='alert alert-danger'>Error assigning test: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }
}

// Fetch courses assigned to teacher
$courses = [];
try {
    $course_stmt = $conn->prepare("
        SELECT ca.batch_id, b.batch_code, c.courseName, b.start_date, b.end_date, b.status
        FROM course_assignments ca
        INNER JOIN batches b ON ca.batch_id = b.batch_id
        INNER JOIN courses c ON b.course_id = c.course_id
        WHERE ca.teacher_id = ?
    ");
    $course_stmt->bind_param("i", $teacher_id);
    $course_stmt->execute();
    $courses_res = $course_stmt->get_result();
    while ($row = $courses_res->fetch_assoc()) {
        $courses[] = $row;
    }
    $course_stmt->close();
} catch (Exception $e) {
    error_log("Error fetching courses: " . $e->getMessage());
    echo "<div class='alert alert-danger'>Error loading courses: " . htmlspecialchars($e->getMessage()) . "</div>";
}

$selected_course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT) ?:
                     filter_input(INPUT_POST, 'selected_course_id', FILTER_VALIDATE_INT);

// Fetch students if course is selected
$students_by_batch = [];
if ($selected_course_id) {
    // Validate batch_id
    try {
        $stmt_check_batch = $conn->prepare("SELECT batch_id FROM batches WHERE batch_id = ?");
        $stmt_check_batch->bind_param("i", $selected_course_id);
        $stmt_check_batch->execute();
        $res_check_batch = $stmt_check_batch->get_result();
        if ($res_check_batch->num_rows === 0) {
            echo "<div class='alert alert-danger'>Invalid batch ID selected.</div>";
            $selected_course_id = null;
        }
        $stmt_check_batch->close();
    } catch (Exception $e) {
        error_log("Error validating batch ID: " . $e->getMessage());
        echo "<div class='alert alert-danger'>Error validating batch ID: " . htmlspecialchars($e->getMessage()) . "</div>";
        $selected_course_id = null;
    }

    if ($selected_course_id) {
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
            echo "<div class='alert alert-danger'>Error fetching students: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Manage Teacher Courses</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        body {
            font-family: Inter, Arial, Helvetica, sans-serif;
            background: #f4f4f8;
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
        <h1>Welcome, <?= htmlspecialchars($teacher_info['username']) ?></h1>
        <p class="mb-0">Email: <?= htmlspecialchars($teacher_info['email']) ?> | Gender: <?= htmlspecialchars($teacher_info['gender']) ?> | Phone: <?= htmlspecialchars($teacher_info['phone']) ?></p>
    </header>

    <div class="container-fluid d-flex flex-nowrap" style="min-height: calc(100vh - 70px);">
        <!-- Sidebar -->
        <nav class="col-md-3 col-xl-2 bg-dark text-white p-3 vh-100" style="min-width:220px;">
            <div class="text-center mb-4">
                <img src="admin.png" class="rounded-circle border border-info mb-2" width="92" height="92" alt="Teacher" />
                <h5>Teacher Dashboard</h5>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item mb-2"><a class="nav-link text-white" href="teacher_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a></li>
                <li class="nav-item mb-2"><a class="nav-link text-white active" href="manage_teacher_courses.php"><i class="bi bi-journal-bookmark"></i> Manage Courses</a></li>
                <li class="nav-item mb-2"><a class="nav-link text-white" href="upload_materials.php"><i class="bi bi-folder"></i> Upload Materials</a></li>
                <li class="nav-item mb-2"><a class="nav-link text-white" href="grades.php"><i class="bi bi-pencil-square"></i> Grade</a></li>
                <li class="nav-item mb-2"><a class="nav-link text-white" href="mark_attendance.php"><i class="bi bi-check-circle"></i> Mark Attendance</a></li>
                <li class="nav-item mb-2"><a class="nav-link text-white" href="messages.php"><i class="bi bi-chat-dots"></i> Message Students</a></li>
                <li class="nav-item mb-2"><a class="nav-link text-white" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="col py-4">
            <h2 class="mb-3">Your Courses / Batches</h2>

            <!-- Course dropdown -->
            <form method="GET" class="mb-4 d-flex align-items-center gap-2">
                <select name="course_id" class="form-select w-auto" onchange="this.form.submit()">
                    <option value="">-- Select a batch --</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= $course['batch_id'] ?>" <?= ($selected_course_id == $course['batch_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($course['courseName']) ?> (<?= htmlspecialchars($course['batch_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if ($selected_course_id): ?>
                <?php
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
                        echo "<div class='alert alert-danger'>Invalid batch selected.</div>";
                        $selected_course_id = null;
                    }
                } catch (Exception $e) {
                    error_log("Error fetching batch details: " . $e->getMessage());
                    echo "<div class='alert alert-danger'>Error fetching batch details: " . htmlspecialchars($e->getMessage()) . "</div>";
                    $selected_course_id = null;
                }
                ?>

                <?php if ($selected_course_id): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h4>Batch: <?= htmlspecialchars($batch_details['batch_code']) ?> (<?= htmlspecialchars($batch_details['courseName']) ?>)</h4>
                        </div>
                        <div class="card-body">
                            <!-- Students enrolled -->
                            <h5>Enrolled Students</h5>
                            <?php if (!empty($students_by_batch[$selected_course_id])): ?>
                                <div class="table-responsive mb-4">
                                    <table class="table table-striped table-bordered mb-0">
                                        <thead class="table-dark">
                                            <tr><th>Name</th><th>Email</th><th>Remove</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students_by_batch[$selected_course_id] as $student): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($student['firstName'] . ' ' . $student['lastName']) ?></td>
                                                    <td><?= htmlspecialchars($student['email']) ?></td>
                                                    <td>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Remove this student?');">
                                                            <input type="hidden" name="enrollment_id" value="<?= $student['enrollment_id'] ?>">
                                                            <input type="hidden" name="selected_course_id" value="<?= $selected_course_id ?>">
                                                            <button type="submit" name="remove_student" class="btn btn-sm btn-danger" onclick="this.disabled=true; this.form.submit();">Remove</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p>No students enrolled.</p>
                            <?php endif; ?>

                            <!-- Assign activity -->
                            <h5>Assign Class Activity / Homework</h5>
                            <div class="card mb-4">
                                <div class="card-body">
                                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                                        <input type="hidden" name="action" value="assign_activity" />
                                        <input type="hidden" name="batch_id" value="<?= $selected_course_id ?>">
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
                                            <label class="form-label">Resource File (PDF, JPG, PNG, max 200MB)</label>
                                            <input class="form-control" type="file" name="resource_file" accept=".pdf,.jpg,.jpeg,.png">
                                        </div>
                                        <div class="col-12 mt-3">
                                            <button class="btn btn-primary" type="submit">Assign</button>
                                            <a href="view_assigned_activities.php?course_id=<?= $selected_course_id ?>" class="btn btn-outline-secondary ms-2">View Assigned Activities</a>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Assign test -->
                            <h5>Assign Test</h5>
                            <div class="card mb-4">
                                <div class="card-body">
                                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                                        <input type="hidden" name="action" value="assign_test" />
                                        <input type="hidden" name="batch_id" value="<?= $selected_course_id ?>">
                                        <div class="col-md-6">
                                            <label class="form-label">Test Title</label>
                                            <input class="form-control" type="text" name="title" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Due Date</label>
                                            <input class="form-control" type="date" name="due_date" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Maximum Score</label>
                                            <input class="form-control" type="number" name="max_score" min="0" max="100" step="0.1" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Description</label>
                                            <textarea class="form-control" name="description" rows="3" required></textarea>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Resource File (PDF, JPG, PNG, max 200MB)</label>
                                            <input class="form-control" type="file" name="resource_file" accept=".pdf,.jpg,.jpeg,.png">
                                        </div>
                                        <div class="col-12 mt-3">
                                            <button class="btn btn-primary" type="submit">Assign Test</button>
                                            <a href="view_assigned_tests.php?course_id=<?= $selected_course_id ?>" class="btn btn-outline-secondary ms-2">View Assigned Tests</a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php elseif (empty($courses)): ?>
                <div class="alert alert-info">No courses assigned to you.</div>
            <?php else: ?>
                <div class='alert alert-info'>Please select a course to manage.</div>
            <?php endif; ?>
        </main>
    </div>

    <footer class="bg-dark text-white text-center py-3 mt-4">
        &copy; <?= date('Y') ?> Girls Coding Academy
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>