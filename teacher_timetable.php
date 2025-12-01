<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}
include 'db.php';

$user_id = $_SESSION['user_id'];
$currentPage = basename($_SERVER['PHP_SELF']);

// Fetch teacher info
$teacherInfo = $conn->query("SELECT username, email FROM users WHERE user_id = $user_id")->fetch_assoc();
$teacher_id = $conn->query("SELECT teacher_id FROM teachers WHERE user_id = $user_id")->fetch_assoc()['teacher_id'];

// Fetch assigned batches
$batches = $conn->query("
    SELECT DISTINCT b.batch_id, b.batch_code, c.courseName 
    FROM course_assignments ca 
    JOIN batches b ON ca.batch_id = b.batch_id 
    JOIN courses c ON b.course_id = c.course_id 
    WHERE ca.teacher_id = $teacher_id AND b.status = 'active'
    ORDER BY b.batch_code
")->fetch_all(MYSQLI_ASSOC);

$selected_batch_id = $_GET['batch'] ?? ($batches[0]['batch_id'] ?? 0);
$selected_day = $_GET['day'] ?? 'Monday';

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

// Load current timetable from DB for selected day
$day_timetable = [];
if ($selected_batch_id) {
    $day_index = array_search($selected_day, $days);
    $res = $conn->query("SELECT id, day, start_time, end_time, period, subject, room 
                         FROM teacher_timetables 
                         WHERE batch_id = $selected_batch_id AND day = $day_index
                         ORDER BY start_time");
    while ($row = $res->fetch_assoc()) {
        $day_timetable[] = $row;
    }
}

$success = $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $batch_id = (int)$_POST['batch_id'];
    $day = $_POST['day'];
    $day_index = array_search($day, $days);
    
    if ($_POST['action'] === 'add_class') {
        $start_time = $conn->real_escape_string($_POST['start_time']);
        $end_time = $conn->real_escape_string($_POST['end_time']);
        $subject = $conn->real_escape_string($_POST['subject']);
        $room = $conn->real_escape_string($_POST['room']);
        
        if (!$start_time || !$end_time || !$subject) {
            $error = "Start time, end time, and subject are required.";
        } else {
            // Check for time clash
            $clash_check = $conn->query("
                SELECT * FROM teacher_timetables 
                WHERE batch_id = $batch_id AND day = $day_index 
                AND NOT (end_time <= '$start_time' OR start_time >= '$end_time')
            ");
            
            if ($clash_check->num_rows > 0) {
                $error = "Time slot conflicts with an existing class on this day.";
            } else {
                // Get next period number
                $period_res = $conn->query("SELECT MAX(period) as max_period FROM teacher_timetables WHERE batch_id = $batch_id AND day = $day_index");
                $period = ($period_res->fetch_assoc()['max_period'] ?? 0) + 1;
                
                $stmt = $conn->prepare("INSERT INTO teacher_timetables (batch_id, day, start_time, end_time, period, subject, room, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iississi", $batch_id, $day_index, $start_time, $end_time, $period, $subject, $room, $teacher_id);
                
                if ($stmt->execute()) {
                    $success = "Class added successfully!";
                    // Reload data
                    $day_timetable = [];
                    $res = $conn->query("SELECT id, day, start_time, end_time, period, subject, room 
                                         FROM teacher_timetables 
                                         WHERE batch_id = $batch_id AND day = $day_index
                                         ORDER BY start_time");
                    while ($row = $res->fetch_assoc()) {
                        $day_timetable[] = $row;
                    }
                } else {
                    $error = "Failed to add class.";
                }
            }
        }
    } elseif ($_POST['action'] === 'delete_class') {
        $class_id = (int)$_POST['class_id'];
        $stmt = $conn->prepare("DELETE FROM teacher_timetables WHERE id = ? AND batch_id = ?");
        $stmt->bind_param("ii", $class_id, $batch_id);
        
        if ($stmt->execute()) {
            $success = "Class deleted successfully!";
            // Reload data
            $day_timetable = [];
            $res = $conn->query("SELECT id, day, start_time, end_time, period, subject, room 
                                 FROM teacher_timetables 
                                 WHERE batch_id = $batch_id AND day = $day_index
                                 ORDER BY start_time");
            while ($row = $res->fetch_assoc()) {
                $day_timetable[] = $row;
            }
        } else {
            $error = "Failed to delete class.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Timetable | Teacher Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
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

        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            color: white;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
            z-index: 1000;
        }

        .sidebar h3 {
            margin: 20px 0;
            font-weight: 700;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #00d9ff;
        }

        .sidebar a {
            width: 100%;
            color: #cbd5e1;
            padding: 14px 16px;
            margin: 8px 0;
            border-radius: 10px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: linear-gradient(90deg, #00d9ff 0%, #0099cc 100%);
            color: #0f172a;
        }

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

        .content::-webkit-scrollbar-thumb {
            background: #00d9ff;
            border-radius: 4px;
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
        }

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
        }

        .batch-selector {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 30px;
        }

        .batch-btn {
            padding: 14px;
            border: 2px solid #e2e8f0;
            background: white;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            color: #1e293b;
            font-weight: 600;
            text-decoration: none;
        }

        .batch-btn:hover {
            border-color: #00d9ff;
            background: rgba(0, 217, 255, 0.05);
            transform: translateY(-2px);
        }

        .batch-btn.active {
            background: linear-gradient(135deg, #00d9ff 0%, #0099cc 100%);
            color: white;
            border-color: #00d9ff;
        }

        .day-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 30px;
        }

        .day-btn {
            padding: 12px;
            border: 2px solid #e2e8f0;
            background: white;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            color: #1e293b;
            font-weight: 600;
            text-decoration: none;
        }

        .day-btn:hover {
            border-color: #00d9ff;
            background: rgba(0, 217, 255, 0.05);
        }

        .day-btn.active {
            background: linear-gradient(135deg, #00d9ff 0%, #0099cc 100%);
            color: white;
            border-color: #00d9ff;
        }

        .form-label {
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #00d9ff;
            box-shadow: 0 0 0 0.2rem rgba(0, 217, 255, 0.25);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .class-item {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            padding: 18px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 4px solid #00d9ff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .class-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 217, 255, 0.15);
        }

        .class-info {
            flex: 1;
        }

        .class-time {
            font-weight: 700;
            color: #00d9ff;
            font-size: 0.95rem;
            margin-bottom: 4px;
        }

        .class-subject {
            color: #1e293b;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 4px;
        }

        .class-room {
            color: #64748b;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .delete-btn {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-left: 12px;
        }

        .delete-btn:hover {
            background: #dc2626;
            transform: scale(1.05);
        }

        .empty-message {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }

        .empty-message i {
            font-size: 2.5rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

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
            color: white;
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 20px;
            }
            
            .header h2 {
                font-size: 1.5rem;
            }
            
            .batch-selector,
            .day-selector {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="container-flex">
    <!-- Sidebar -->
    <nav class="sidebar">
        <h3>Teacher Portal</h3>
        <a href="teacher.php"><i class="bi bi-house-door"></i> Home</a>
        <a href="teacher_dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="manage_timetable.php" class="active"><i class="bi bi-calendar2-week"></i> Manage Timetable</a>
        <a href="manage_batches.php"><i class="bi bi-diagram-3"></i> My Batches</a>
        <a href="mark_attendance.php"><i class="bi bi-check-square"></i> Mark Attendance</a>
        <a href="upload_materials.php"><i class="bi bi-file-earmark-arrow-up"></i> Materials</a>
        <a href="create_activity.php"><i class="bi bi-list-task"></i> Activities</a>
        <a href="create_test.php"><i class="bi bi-pencil-square"></i> Tests</a>
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>

    <!-- Main content -->
    <main class="content">
        <div class="header">
            <h2><i class="bi bi-calendar2-week"></i> Manage Class Timetable</h2>
            <p>Select a batch and day to create or modify the timetable</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= $error ?></div>
        <?php endif; ?>

        <?php if (empty($batches)): ?>
            <div class="card-section">
                <div class="empty-message">
                    <i class="bi bi-diagram-3"></i>
                    <p style="font-size: 1.2rem;">No batches assigned to you yet.</p>
                </div>
            </div>
        <?php else: ?>
            <!-- Batch Selection -->
            <div class="card-section">
                <h3><i class="bi bi-diagram-3"></i> Select Batch</h3>
                <div class="batch-selector">
                    <?php foreach ($batches as $batch): ?>
                        <a href="?batch=<?= $batch['batch_id'] ?>&day=<?= $selected_day ?>" 
                           class="batch-btn <?= ($selected_batch_id === $batch['batch_id']) ? 'active' : '' ?>">
                            <div style="font-size: 0.85rem; color: #64748b;">
                                <?= htmlspecialchars($batch['courseName']) ?>
                            </div>
                            <div style="font-size: 1rem;">
                                <?= htmlspecialchars($batch['batch_code']) ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Day Selection -->
            <div class="card-section">
                <h3><i class="bi bi-calendar-event"></i> Select Day</h3>
                <div class="day-selector">
                    <?php foreach ($days as $d): ?>
                        <a href="?batch=<?= $selected_batch_id ?>&day=<?= $d ?>" 
                           class="day-btn <?= ($selected_day === $d) ? 'active' : '' ?>">
                            <?= $d ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Add Class Form -->
            <div class="card-section">
                <h3><i class="bi bi-plus-circle"></i> Add New Class for <?= $selected_day ?></h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="add_class">
                    <input type="hidden" name="batch_id" value="<?= $selected_batch_id ?>">
                    <input type="hidden" name="day" value="<?= $selected_day ?>">

                    <div class="form-row">
                        <div>
                            <label class="form-label">Start Time</label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>
                        <div>
                            <label class="form-label">End Time</label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div style="grid-column: span 2;">
                            <label class="form-label">Subject/Class Name</label>
                            <input type="text" name="subject" class="form-control" placeholder="e.g., Mathematics" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div>
                            <label class="form-label">Room Number (Optional)</label>
                            <input type="text" name="room" class="form-control" placeholder="e.g., A101">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus"></i> Add Class
                    </button>
                </form>
            </div>

            <!-- Classes for Selected Day -->
            <div class="card-section">
                <h3><i class="bi bi-grid-3x3"></i> Classes on <?= $selected_day ?></h3>
                
                <?php if (empty($day_timetable)): ?>
                    <div class="empty-message">
                        <i class="bi bi-calendar-x"></i>
                        <p>No classes scheduled for <?= $selected_day ?> yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($day_timetable as $class): ?>
                        <div class="class-item">
                            <div class="class-info">
                                <div class="class-time">
                                    <i class="bi bi-clock"></i> <?= date('h:i A', strtotime($class['start_time'])) ?> – <?= date('h:i A', strtotime($class['end_time'])) ?>
                                </div>
                                <div class="class-subject"><?= htmlspecialchars($class['subject']) ?></div>
                                <div class="class-room">
                                    <i class="bi bi-door-closed"></i> <?= htmlspecialchars($class['room'] ?? 'No room specified') ?>
                                </div>
                            </div>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete_class">
                                <input type="hidden" name="class_id" value="<?= $class['id'] ?>">
                                <input type="hidden" name="batch_id" value="<?= $selected_batch_id ?>">
                                <input type="hidden" name="day" value="<?= $selected_day ?>">
                                <button type="submit" class="delete-btn" onclick="return confirm('Delete this class?');">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>