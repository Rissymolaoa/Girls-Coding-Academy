<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

include("db.php");

$parent_user_id = $_SESSION['user_id']; 
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate that this student belongs to this parent
$check = $conn->prepare("
    SELECT s.student_id, u.firstName, u.lastName, u.gender, s.photo
    FROM students s
    INNER JOIN parent_students ps ON s.student_id = ps.student_id
    INNER JOIN parents p ON ps.parent_id = p.parent_id
    INNER JOIN users u ON s.user_id = u.user_id
    WHERE p.user_id = ? AND s.student_id = ?
");
$check->bind_param("ii", $parent_user_id, $student_id);
$check->execute();
$student_result = $check->get_result();
$student = $student_result->fetch_assoc();

if (!$student) {
    echo "You are not authorized to view this student.";
    exit();
}

// Fetch attendance records
$attendance_sql = $conn->prepare("
    SELECT a.session_id, a.status, a.marked_at, b.batch_code, c.courseName
    FROM attendance a
    INNER JOIN batches b ON a.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    WHERE a.student_id = ?
    ORDER BY a.session_id DESC
");
$attendance_sql->bind_param("i", $student_id);
$attendance_sql->execute();
$attendance_result = $attendance_sql->get_result();
$attendance = $attendance_result->fetch_all(MYSQLI_ASSOC);

// Fetch tasks (activities) for this student
$tasks_sql = $conn->prepare("
    SELECT a.activity_id, a.title, a.description, a.due_date, a.resource_file, c.courseName
    FROM activities a
    INNER JOIN batches b ON a.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    INNER JOIN course_enrollments ce ON b.batch_id = ce.batch_id
    WHERE ce.student_id = ? AND a.status = 'active'
    ORDER BY a.due_date ASC
");
$tasks_sql->bind_param("i", $student_id);
$tasks_sql->execute();
$tasks_result = $tasks_sql->get_result();
$tasks = $tasks_result->fetch_all(MYSQLI_ASSOC);

$today = date('Y-m-d');
$upcoming_tasks = [];
$overdue_tasks = [];

foreach ($tasks as $task) {
    if ($task['due_date'] >= $today) {
        $upcoming_tasks[] = $task;
    } else {
        $overdue_tasks[] = $task;
    }
}



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($student['firstName'] . " " . $student['lastName']); ?> - Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            min-height: 100vh;
            margin: 0;
        }
        .sidebar {
            width: 250px;
            background: #343a40;
            color: white;
            flex-shrink: 0;
        }
        .sidebar h4 {
            text-align: center;
            padding: 15px 0;
            border-bottom: 1px solid #495057;
        }
        .sidebar img {
            width: 80px;
            border-radius: 50%;
            margin: 10px auto;
            display: block;
        }
        .sidebar a {
            display: block;
            color: white;
            padding: 12px 20px;
            text-decoration: none;
        }
        .sidebar a:hover {
            background: #495057;
        }
        .content {
            flex-grow: 1;
            padding: 20px;
        }
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .profile-header img {
            width: 150px;
            height: 150px;
            border-radius: 10px;
            margin-right: 20px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <img src="admin.png" alt="Parent Image">
        <h4>Parent Dashboard</h4>
        <a href="parents_dashboard.php">Dashboard</a>
        <a href="children.php">Children Profiles</a>
        <a href="parent_messages.php">Messages</a>
        <a href="parent_settings.php">Settings</a>
        <a href="logout.php">Logout</a>
    </div>

    <!-- Main Content -->
    <div class="content">
        <div class="profile-header">
            <img src="<?php echo $student['photo'] ?: 'default_student.png'; ?>" alt="Student Photo">
            <div>
                <h2><?php echo htmlspecialchars($student['firstName'] . " " . $student['lastName']); ?></h2>
                <p><strong>Gender:</strong> <?php echo htmlspecialchars($student['gender']); ?></p>
            </div>
              <a href="temp.php?id=<?php echo $student['student_id']; ?>" class="btn btn-success mt-2">
            View Student Profile
        </a>
        </div>

        <!-- Attendance Section -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                Attendance Records
            </div>
            <div class="card-body">
                <?php if (count($attendance) > 0): ?>
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Course</th>
                                <th>Batch</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['session_id']); ?></td>
                                    <td><?php echo htmlspecialchars($record['courseName']); ?></td>
                                    <td><?php echo htmlspecialchars($record['batch_code']); ?></td>
                                    <td>
                                        <?php 
                                            $status = $record['status'];
                                            $badge = $status === 'Present' ? 'success' : ($status === 'Late' ? 'warning' : 'danger');
                                        ?>
                                        <span class="badge bg-<?php echo $badge; ?>">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No attendance records found.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tasks / Homework / Announcements -->
        <div class="row">
<div class="col-md-4">
    <div class="card h-100" style="min-height: 400px;">
        <div class="card-header bg-info text-white text-center">
            <h5 class="mb-0">Tasks</h5>
        </div>
        <div class="card-body" style="overflow-y:auto; max-height:330px;">
            <?php if (count($upcoming_tasks) > 0): ?>
                <h6 class="text-success">Upcoming</h6>
                <ul class="list-group mb-3">
                    <?php foreach ($upcoming_tasks as $task): ?>
                        <li class="list-group-item text-start">
                            <strong><?php echo htmlspecialchars($task['title']); ?></strong><br>
                            <small><?php echo htmlspecialchars($task['description']); ?></small><br>
                            <span class="badge bg-secondary">
                                Due: <?php echo htmlspecialchars($task['due_date']); ?>
                            </span><br>
                            <em class="text-muted"><?php echo htmlspecialchars($task['courseName']); ?></em>
                            <?php if (!empty($task['resource_file']) && file_exists($task['resource_file'])): ?>
                                <br>
                                <a href="<?php echo htmlspecialchars($task['resource_file']); ?>" 
                                   target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="bi bi-file-earmark-arrow-down"></i> View Resource
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (count($overdue_tasks) > 0): ?>
                <h6 class="text-danger">Overdue</h6>
                <ul class="list-group">
                    <?php foreach ($overdue_tasks as $task): ?>
                        <li class="list-group-item text-start bg-light">
                            <strong><?php echo htmlspecialchars($task['title']); ?></strong><br>
                            <small><?php echo htmlspecialchars($task['description']); ?></small><br>
                            <span class="badge bg-danger">
                                Due: <?php echo htmlspecialchars($task['due_date']); ?>
                            </span><br>
                            <em class="text-muted"><?php echo htmlspecialchars($task['courseName']); ?></em>
                            <?php if (!empty($task['resource_file']) && file_exists($task['resource_file'])): ?>
                                <br>
                                <a href="<?php echo htmlspecialchars($task['resource_file']); ?>" 
                                   target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="bi bi-file-earmark-arrow-down"></i> View Resource
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (count($upcoming_tasks) === 0 && count($overdue_tasks) === 0): ?>
                <p class="text-muted">No tasks assigned yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>


</div>

            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-header bg-warning text-dark">Homeworks</div>
                    <div class="card-body">
                        <p>Homeworks given to student will appear here.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-header bg-success text-white">Announcements</div>
                    <div class="card-body">
                        <p>Announcements for this student will appear here.</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</body>
</html>
