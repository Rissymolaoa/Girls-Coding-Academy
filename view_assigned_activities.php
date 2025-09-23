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
        echo "<div class='alert alert-danger'>All fields are required, and status must be 'active' or 'inactive'.</div>";
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
                    echo "<div class='alert alert-danger'>Error uploading file. Please try again.</div>";
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
                $types = "isss";
                if ($resource_file) {
                    $params[] = $resource_file;
                    $types .= "s";
                }
                $params[] = $activity_id;
                $types .= "i";
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $stmt->close();
                echo "<div class='alert alert-success'>Activity updated successfully.</div>";
            } catch (Exception $e) {
                error_log("Error updating activity: " . $e->getMessage());
                echo "<div class='alert alert-danger'>Error updating activity: " . htmlspecialchars($e->getMessage()) . "</div>";
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
            echo "<div class='alert alert-danger'>Error deleting activity: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>Invalid activity ID.</div>";
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
            echo "<div class='alert alert-danger'>Error clearing activity: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>Invalid activity ID.</div>";
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
            echo "<div class='alert alert-danger'>Error fetching activities: " . htmlspecialchars($e->getMessage()) . "</div>";
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
            echo "<div class='alert alert-danger'>Error fetching activity submissions: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>View Assigned Activities</title>
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
        .badge-active {
            background-color: #28a745;
        }
        .badge-inactive {
            background-color: #6c757d;
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
                <li class="nav-item mb-2"><a class="nav-link text-white" href="message_students.php"><i class="bi bi-chat-dots"></i> Message Students</a></li>
                <li class="nav-item mb-2"><a class="nav-link text-white" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="col py-4">
            <h2 class="mb-3">Assigned Activities / Homeworks</h2>
            <a href="manage_teacher_courses.php?course_id=<?= $selected_course_id ?>" class="btn btn-outline-primary mb-3">Back to Manage Courses</a>

            <?php if ($selected_course_id && $batch_details): ?>
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h4>Batch: <?= htmlspecialchars($batch_details['batch_code']) ?> (<?= htmlspecialchars($batch_details['courseName']) ?>)</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($activitiesByBatch[$selected_course_id])): ?>
                            <?php foreach ($activitiesByBatch[$selected_course_id] as $activity): ?>
                                <div class="card mb-4">
                                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">
                                            <?= htmlspecialchars($activity['title']) ?> (Due: <?= htmlspecialchars($activity['due_date']) ?>)
                                            <span class="badge <?= $activity['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?> ms-2">
                                                <?= htmlspecialchars(ucfirst($activity['status'])) ?>
                                            </span>
                                        </h5>
                                        <div>
                                            <button class="btn btn-sm btn-warning me-2" onclick="toggleEditForm('activity_<?= $activity['activity_id'] ?>')">Edit</button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this activity?');">
                                                <input type="hidden" name="action" value="delete_activity">
                                                <input type="hidden" name="activity_id" value="<?= $activity['activity_id'] ?>">
                                                <input type="hidden" name="selected_course_id" value="<?= $selected_course_id ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to clear this activity?');">
                                                <input type="hidden" name="action" value="clear_activity">
                                                <input type="hidden" name="activity_id" value="<?= $activity['activity_id'] ?>">
                                                <input type="hidden" name="selected_course_id" value="<?= $selected_course_id ?>">
                                                <button type="submit" class="btn btn-sm btn-secondary ms-2">Clear</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <p><?= htmlspecialchars($activity['description']) ?></p>
                                        <?php if ($activity['resource_file'] && file_exists($activity['resource_file'])): ?>
                                            <a href="<?= htmlspecialchars($activity['resource_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mb-3">View Resource File</a>
                                        <?php elseif ($activity['resource_file']): ?>
                                            <p class="text-danger">Resource file not found: <?= htmlspecialchars($activity['resource_file']) ?></p>
                                        <?php endif; ?>
                                        <form method="POST" enctype="multipart/form-data" class="row g-3 mt-3" id="activity_<?= $activity['activity_id'] ?>" style="display: none;">
                                            <input type="hidden" name="action" value="update_activity">
                                            <input type="hidden" name="activity_id" value="<?= $activity['activity_id'] ?>">
                                            <input type="hidden" name="batch_id" value="<?= $selected_course_id ?>">
                                            <div class="col-md-6">
                                                <label class="form-label">Title</label>
                                                <input class="form-control" type="text" name="title" value="<?= htmlspecialchars($activity['title']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Due Date</label>
                                                <input class="form-control" type="date" name="due_date" value="<?= htmlspecialchars($activity['due_date']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Status</label>
                                                <select class="form-select" name="status" required>
                                                    <option value="active" <?= $activity['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                    <option value="inactive" <?= $activity['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Description</label>
                                                <textarea class="form-control" name="description" rows="3" required><?= htmlspecialchars($activity['description']) ?></textarea>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Resource File (PDF, JPG, PNG, max 200MB)</label>
                                                <input class="form-control" type="file" name="resource_file" accept=".pdf,.jpg,.jpeg,.png">
                                                <?php if ($activity['resource_file']): ?>
                                                    <p>Current file: <a href="<?= htmlspecialchars($activity['resource_file']) ?>" target="_blank">View Current File</a></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-12 mt-3">
                                                <button class="btn btn-warning" type="submit">Update Activity</button>
                                            </div>
                                        </form>
                                        <h6>Submissions</h6>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-bordered mb-0">
                                                <thead class="table-dark">
                                                    <tr><th>Student</th><th>Status</th><th>Submitted At</th><th>Details</th></tr>
                                                </thead>
                                                <tbody>
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
                                                        $badge_class = 'bg-secondary';
                                                        if ($submission) {
                                                            if ($submission['submitted_at'] > $activity['due_date'] . ' 23:59:59') {
                                                                $status_text = 'Late';
                                                                $badge_class = 'bg-danger';
                                                            } else {
                                                                $status_text = 'Submitted';
                                                                $badge_class = 'bg-success';
                                                            }
                                                        } else {
                                                            if ($today > $activity['due_date']) {
                                                                $status_text = 'Not Submitted';
                                                                $badge_class = 'bg-danger';
                                                            } else {
                                                                $status_text = 'Pending';
                                                                $badge_class = 'bg-warning text-dark';
                                                            }
                                                        }
                                                    ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($student['firstName'] . ' ' . $student['lastName']) ?></td>
                                                            <td><span class="badge <?= $badge_class ?>"><?= $status_text ?></span></td>
                                                            <td><?= $submission ? htmlspecialchars($submission['submitted_at']) : '-' ?></td>
                                                            <td>
                                                                <?php if ($submission): ?>
                                                                    <?php if ($submission['submission_text']): ?>
                                                                        <p><?= htmlspecialchars($submission['submission_text']) ?></p>
                                                                    <?php endif; ?>
                                                                    <?php if ($submission['submission_file'] && file_exists($submission['submission_file'])): ?>
                                                                        <a href="<?= htmlspecialchars($submission['submission_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">View File</a>
                                                                    <?php elseif ($submission['submission_file']): ?>
                                                                        <p class="text-danger">Submission file not found: <?= htmlspecialchars($submission['submission_file']) ?></p>
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
                <div class="alert alert-info">Please select a valid course to view activities.</div>
            <?php endif; ?>
        </main>
    </div>

    <footer class="bg-dark text-white text-center py-3 mt-4">
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