<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}
include 'db.php';

$user_id = $_SESSION['user_id'];

// Fetch teacher info
$teacherInfo = $conn->query("SELECT username, email, gender, phone FROM users WHERE user_id = $user_id")->fetch_assoc();
$teacher_id = $conn->query("SELECT teacher_id FROM teachers WHERE user_id = $user_id")->fetch_assoc()['teacher_id'];

// Fetch assigned active batches
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
$day_index = array_search($selected_day, $days);

// Load current timetable for selected day
$day_timetable = [];
if ($selected_batch_id) {
    $res = $conn->query("SELECT id, day, start_time, end_time, period, subject, room
                         FROM teacher_timetables
                         WHERE batch_id = $selected_batch_id AND day = $day_index
                         ORDER BY start_time");
    while ($row = $res->fetch_assoc()) {
        $day_timetable[] = $row;
    }
}

// Fetch all ACTIVE rooms with floor name
$all_rooms = $conn->query("
    SELECT r.id, r.room_name, r.room_type, COALESCE(f.floor_name, 'Ground Floor') as floor_name
    FROM school_rooms r
    LEFT JOIN school_floors f ON r.floor_id = f.id
    WHERE r.status = 'Active'
    ORDER BY r.room_name
")->fetch_all(MYSQLI_ASSOC);

$success = $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $batch_id = (int)$_POST['batch_id'];
    $day_index = array_search($_POST['day'], $days);

    if ($_POST['action'] === 'add_class') {
        $start_time = $_POST['start_time'];
        $end_time   = $_POST['end_time'];
        $subject    = trim($_POST['subject']);
        $room_id    = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
        $room_name  = $room_id ? $conn->query("SELECT room_name FROM school_rooms WHERE id = $room_id")->fetch_assoc()['room_name'] : '';

        if (!$start_time || !$end_time || !$subject) {
            $error = "Please fill all required fields.";
        } elseif ($end_time <= $start_time) {
            $error = "End time must be after start time.";
        } else {
            // Check teacher conflict
            $teacher_clash = $conn->query("
                SELECT * FROM teacher_timetables
                WHERE created_by = $teacher_id AND day = $day_index
                AND NOT (end_time <= '$start_time' OR start_time >= '$end_time')
            ");
            if ($teacher_clash->num_rows > 0) {
                $error = "You are already teaching another class at this time.";
            }
            // Check room conflict
            elseif ($room_id && $conn->query("
                SELECT * FROM teacher_timetables
                WHERE room = '$room_name' AND day = $day_index
                AND NOT (end_time <= '$start_time' OR start_time >= '$end_time')
            ")->num_rows > 0) {
                $error = "This room is already booked at this time.";
            } else {
                // Get next period
                $period_res = $conn->query("SELECT MAX(period) as p FROM teacher_timetables WHERE batch_id = $batch_id AND day = $day_index");
                $period = ($period_res->fetch_assoc()['p'] ?? 0) + 1;

                $stmt = $conn->prepare("INSERT INTO teacher_timetables 
                    (batch_id, day, start_time, end_time, period, subject, room, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iississi", $batch_id, $day_index, $start_time, $end_time, $period, $subject, $room_name, $teacher_id);

                if ($stmt->execute()) {
                    $success = "Class scheduled successfully!";
                    // Refresh timetable
                    $day_timetable = [];
                    $res = $conn->query("SELECT id, day, start_time, end_time, period, subject, room
                                         FROM teacher_timetables WHERE batch_id = $batch_id AND day = $day_index ORDER BY start_time");
                    while ($row = $res->fetch_assoc()) $day_timetable[] = $row;
                } else {
                    $error = "Failed to add class.";
                }
                $stmt->close();
            }
        }
    }

    if ($_POST['action'] === 'delete_class') {
        $class_id = (int)$_POST['class_id'];
        $stmt = $conn->prepare("DELETE FROM teacher_timetables WHERE id = ? AND created_by = ?");
        $stmt->bind_param("ii", $class_id, $teacher_id);
        if ($stmt->execute()) {
            $success = "Class deleted successfully!";
            $day_timetable = [];
            $res = $conn->query("SELECT id, day, start_time, end_time, period, subject, room FROM teacher_timetables WHERE batch_id = $batch_id AND day = $day_index ORDER BY start_time");
            while ($row = $res->fetch_assoc()) $day_timetable[] = $row;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Timetable • Teacher Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {font-family:'Inter',sans-serif;background:#f8fafc}
        .gradient-header {background: linear-gradient(90deg, #7b2cbf, #5a189a);}
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #7b2cbf, #5a189a);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        .sidebar.hidden {transform: translateX(-100%);}
        .sidebar-link {transition: all 0.3s ease;}
        .sidebar-link:hover {background: rgba(255,255,255,0.1); padding-left: 1.5rem;}
        .sidebar-link.active {background: rgba(255,255,255,0.2); border-left: 4px solid white; font-weight:600;}
        .main-content {margin-left: 250px; transition: margin-left 0.3s ease;}
        .card:hover {transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0,0,0,0.15);}
        .batch-btn.active, .day-btn.active {background: linear-gradient(90deg,#7b2cbf,#5a189a);color:white}
        @media (max-width: 768px) {
            .sidebar {transform: translateX(-100%);}
            .sidebar.mobile-open {transform: translateX(0);}
            .main-content {margin-left: 0;}
            .mobile-toggle {display: block;}
        }
        .mobile-toggle {display: none;}
    </style>
</head>
<body class="bg-gray-100">

<!-- SIDEBAR - EXACT SAME AS TEACHER DASHBOARD -->
<aside class="sidebar" id="sidebar">
    <div class="p-6">
        <div class="flex items-center mb-8">
            <i class="fas fa-graduation-cap text-white text-3xl mr-3"></i>
            <h2 class="text-white text-xl font-bold">GCA Portal</h2>
        </div>

        <nav>
            <a href="teacher_dashboard.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2 <?= basename($_SERVER['PHP_SELF'])==='teacher_dashboard.php'?'active':'' ?>">
                <i class="fas fa-home mr-3"></i> Dashboard
            </a>
            <a href="manage_timetable.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2 active bg-white bg-opacity-20 border-l-4 border-white">
                <i class="fas fa-calendar-week mr-3"></i> Manage Timetable
            </a>
            <a href="manage_teacher_courses.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2 <?= in_array(basename($_SERVER['PHP_SELF']),['manage_teacher_courses.php','schedule_class.php'])?'active':'' ?>">
                <i class="fas fa-chalkboard-teacher mr-3"></i> My Courses
            </a>
            <a href="upload_materials.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2 <?= basename($_SERVER['PHP_SELF'])==='upload_materials.php'?'active':'' ?>">
                <i class="fas fa-book mr-3"></i> Upload Materials
            </a>
            <a href="grades.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2 <?= basename($_SERVER['PHP_SELF'])==='grades.php'?'active':'' ?>">
                <i class="fas fa-clipboard-check mr-3"></i> Grade Students
            </a>
            <a href="mark_attendance.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2 <?= basename($_SERVER['PHP_SELF'])==='mark_attendance.php'?'active':'' ?>">
                <i class="fas fa-calendar-check mr-3"></i> Mark Attendance
            </a>
            <a href="message_students.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2 <?= basename($_SERVER['PHP_SELF'])==='message_students.php'?'active':'' ?>">
                <i class="fas fa-envelope mr-3"></i> Message Students
            </a>
            <a href="teacher_profile.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2 <?= basename($_SERVER['PHP_SELF'])==='teacher_profile.php'?'active':'' ?>">
                <i class="fas fa-user mr-3"></i> My Profile
            </a>
            <a href="logout.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                <i class="fas fa-sign-out-alt mr-3"></i> Logout
            </a>
        </nav>
    </div>
</aside>

<!-- HEADER - EXACT SAME AS DASHBOARD -->
<header class="gradient-header text-white py-4 px-6 flex justify-between items-center fixed top-0 left-0 right-0 z-40">
    <button class="mobile-toggle text-white text-2xl" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <div>
        <h1 class="text-xl font-semibold">Welcome, <?= htmlspecialchars($teacherInfo['username']) ?>!</h1>
        <p class="text-sm">Email: <?= htmlspecialchars($teacherInfo['email']) ?> | Phone: <?= htmlspecialchars($teacherInfo['phone']) ?></p>
    </div>
</header>

<!-- MAIN CONTENT -->
<div class="main-content pt-20" id="mainContent">
    <main class="p-6">

        <h2 class="text-3xl font-bold text-gray-800 mb-2">Manage Class Timetable</h2>
        <p class="text-gray-600 mb-6">Select a batch and day to schedule your classes using available rooms</p>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                <i class="fas fa-check-circle mr-2"></i><?= $success ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
            </div>
        <?php endif; ?>

        <?php if (empty($batches)): ?>
            <div class="bg-white rounded-lg shadow p-10 text-center">
                <i class="fas fa-calendar-times text-6xl text-gray-300 mb-4"></i>
                <p class="text-xl text-gray-600">No batches assigned to you yet.</p>
            </div>
        <?php else: ?>

            <!-- Batch Selection -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Select Batch</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($batches as $batch): ?>
                        <a href="?batch=<?= $batch['batch_id'] ?>&day=<?= $selected_day ?>"
                           class="batch-btn p-4 text-center rounded-lg border-2 <?= $selected_batch_id == $batch['batch_id'] ? 'active' : 'border-gray-200 hover:border-purple-500' ?>">
                            <p class="text-sm opacity-75"><?= htmlspecialchars($batch['courseName']) ?></p>
                            <p class="font-bold text-lg"><?= htmlspecialchars($batch['batch_code']) ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Day Selection -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Select Day</h3>
                <div class="grid grid-cols-4 sm:grid-cols-7 gap-3">
                    <?php foreach ($days as $d): ?>
                        <a href="?batch=<?= $selected_batch_id ?>&day=<?= urlencode($d) ?>"
                           class="day-btn py-3 text-center rounded-lg font-medium <?= $selected_day === $d ? 'active' : 'bg-gray-100 hover:bg-gray-200' ?>">
                            <?= substr($d,0,3) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Add Class Form -->
            <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
                <h3 class="text-2xl font-bold text-gray-800 mb-6">Add New Class — <?= $selected_day ?></h3>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <input type="hidden" name="action" value="add_class">
                    <input type="hidden" name="batch_id" value="<?= $selected_batch_id ?>">
                    <input type="hidden" name="day" value="<?= $selected_day ?>">

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Start Time *</label>
                        <input type="time" name="start_time" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">End Time *</label>
                        <input type="time" name="end_time" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Subject *</label>
                        <input type="text" name="subject" required placeholder="e.g. Web Development" class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Room (Available)</label>
                        <select name="room_id" class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                            <option value="">No room (theory/online)</option>
                            <?php foreach($all_rooms as $room): ?>
                                <option value="<?= $room['id'] ?>">
                                    <?= htmlspecialchars($room['room_name']) ?> (<?= $room['room_type'] ?>, <?= $room['floor_name'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-2 lg:col-span-4">
                        <button type="submit" class="w-full bg-gradient-to-r from-purple-600 to-purple-800 hover:from-purple-700 hover:to-purple-900 text-white font-bold py-4 rounded-lg shadow-lg transition transform hover:scale-105">
                            Add Class
                        </button>
                    </div>
                </form>
            </div>

            <!-- Scheduled Classes -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-purple-600 to-purple-800 text-white p-6">
                    <h3 class="text-2xl font-bold">Classes on <?= $selected_day ?></h3>
                </div>
                <div class="p-6">
                    <?php if (empty($day_timetable)): ?>
                        <p class="text-center text-gray-500 py-10">No classes scheduled yet for <?= $selected_day ?>.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($day_timetable as $class): ?>
                                <div class="flex justify-between items-center p-5 bg-gray-50 rounded-lg border border-l-4 border-purple-600">
                                    <div>
                                        <div class="font-bold text-purple-700">
                                            <?= date('h:i A', strtotime($class['start_time'])) ?> – <?= date('h:i A', strtotime($class['end_time'])) ?>
                                        </div>
                                        <div class="text-xl font-bold text-gray-800 mt-1"><?= htmlspecialchars($class['subject']) ?></div>
                                        <div class="text-gray-600 mt-1">
                                            Room: <strong><?= htmlspecialchars($class['room'] ?: 'Not assigned') ?></strong>
                                        </div>
                                    </div>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="delete_class">
                                        <input type="hidden" name="class_id" value="<?= $class['id'] ?>">
                                        <input type="hidden" name="batch_id" value="<?= $selected_batch_id ?>">
                                        <input type="hidden" name="day" value="<?= $selected_day ?>">
                                        <button type="submit" onclick="return confirm('Delete this class?')"
                                                class="text-red-600 hover:text-red-800 font-medium">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
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
function closeSidebarOnClickOutside(e) {
    const sidebar = document.getElementById('sidebar');
    const btn = e.target.closest('.mobile-toggle');
    if (!sidebar.contains(e.target) && !btn) {
        sidebar.classList.remove('mobile-open');
        document.removeEventListener('click', closeSidebarOnClickOutside);
    }
}
</script>
</body>
</html>