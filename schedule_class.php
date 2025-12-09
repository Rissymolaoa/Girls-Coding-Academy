<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}
include 'db.php';

$user_id = $_SESSION['user_id'];

// Fetch teacher info
$teacherInfo = $conn->query("SELECT username, email FROM users WHERE user_id = $user_id")->fetch_assoc();
$teacher_id = $conn->query("SELECT teacher_id FROM teachers WHERE user_id = $user_id")->fetch_assoc()['teacher_id'];

// Fetch teacher's assigned batches
$batches = $conn->query("
    SELECT DISTINCT b.batch_id, b.batch_code, c.courseName
    FROM course_assignments ca
    JOIN batches b ON ca.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    WHERE ca.teacher_id = $teacher_id AND b.status = 'active'
    ORDER BY b.batch_code
")->fetch_all(MYSQLI_ASSOC);

// Fetch all ACTIVE rooms with floor info
$rooms = $conn->query("
    SELECT r.id, r.room_name, r.room_type, r.capacity,
           COALESCE(f.floor_name, 'Ground Floor') as floor_name,
           f.building
    FROM school_rooms r
    LEFT JOIN school_floors f ON r.floor_id = f.id
    WHERE r.status = 'Active'
    ORDER BY r.room_name
")->fetch_all(MYSQLI_ASSOC);

$message = "";

// Handle scheduling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'schedule_class') {
    $batch_id      = (int)$_POST['batch_id'];
    $class_date    = $_POST['class_date'];
    $start_time    = $_POST['start_time'];
    $end_time      = $_POST['end_time'];
    $room_id       = (int)$_POST['room_id'];
    $topic         = trim($_POST['topic']);
    $description   = trim($_POST['description'] ?? '');

    // Validation
    if (!$batch_id || !$class_date || !$start_time || !$end_time || !$room_id || !$topic) {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>All fields are required.</div>";
    } elseif ($end_time <= $start_time) {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>End time must be after start time.</div>";
    } elseif ($class_date < date('Y-m-d')) {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>Cannot schedule in the past.</div>";
    } else {
        // Get room details
        $room_stmt = $conn->prepare("SELECT room_name, capacity FROM school_rooms WHERE id = ? AND status = 'Active'");
        $room_stmt->bind_param("i", $room_id);
        $room_stmt->execute();
        $room = $room_stmt->get_result()->fetch_assoc();
        $room_stmt->close();

        if (!$room) {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>Invalid room selected.</div>";
        } else {
            $room_name = $room['room_name'];
            $room_capacity = $room['capacity'];

            // Check teacher conflict
            $teacher_conflict = $conn->query("
                SELECT * FROM class_schedules
                WHERE teacher_id = $teacher_id AND class_date = '$class_date'
                AND NOT (end_time <= '$start_time' OR start_time >= '$end_time')
            ");
            if ($teacher_conflict->num_rows > 0) {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>You are already teaching at this time.</div>";
            }
            // Check room conflict
            elseif ($conn->query("
                SELECT * FROM class_schedules
                WHERE room_number = '$room_name' AND class_date = '$class_date'
                AND NOT (end_time <= '$start_time' OR start_time >= '$end_time')
            ")->num_rows > 0) {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>Room <strong>$room_name</strong> is already booked at this time.</div>";
            } else {
                // Insert schedule
                $stmt = $conn->prepare("
                    INSERT INTO class_schedules 
                    (batch_id, teacher_id, class_date, start_time, end_time, room_number, room_building, room_capacity, topic, description)
                    VALUES (?, ?, ?, ?, ?, ?, 'Main Building', ?, ?, ?)
                ");
                $stmt->bind_param("iisssisss", $batch_id, $teacher_id, $class_date, $start_time, $end_time, $room_name, $room_capacity, $topic, $description);

                if ($stmt->execute()) {
                    $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6'>Class scheduled successfully in <strong>$room_name</strong>!</div>";
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6'>Error saving schedule.</div>";
                }
                $stmt->close();
            }
        }
    }
}

// Fetch teacher's scheduled classes
$scheduled = $conn->query("
    SELECT cs.*, b.batch_code, c.courseName
    FROM class_schedules cs
    JOIN batches b ON cs.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    WHERE cs.teacher_id = $teacher_id
    ORDER BY cs.class_date DESC, cs.start_time DESC
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Class • Teacher Portal</title>
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
        .sidebar-link.active {background: rgba(255,255,255,0.25); border-left: 4px solid white; font-weight:600;}
        .main-content {margin-left: 250px; transition: margin-left 0.3s ease;}
        .card:hover {transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0,0,0,0.15);}
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

<!-- SIDEBAR - SAME AS YOUR DASHBOARD -->
<aside class="sidebar" id="sidebar">
    <div class="p-6">
        <div class="flex items-center mb-8">
            <i class="fas fa-graduation-cap text-white text-3xl mr-3"></i>
            <h2 class="text-white text-xl font-bold">GCA Portal</h2>
        </div>
        <nav>
            <a href="teacher_dashboard.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                <i class="fas fa-home mr-3"></i> Dashboard
            </a>
            <a href="manage_teacher_courses.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                <i class="fas fa-chalkboard-teacher mr-3"></i> My Courses
            </a>
            <a href="schedule_class.php" class="sidebar-link active flex items-center text-white py-3 px-4 rounded mb-2">
                <i class="fas fa-calendar-plus mr-3"></i> Schedule Class
            </a>
            <a href="upload_materials.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                <i class="fas fa-book mr-3"></i> Upload Materials
            </a>
            <a href="grades.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                <i class="fas fa-clipboard-check mr-3"></i> Grade Students
            </a>
            <a href="mark_attendance.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                <i class="fas fa-calendar-check mr-3"></i> Mark Attendance
            </a>
            <a href="message_students.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                <i class="fas fa-envelope mr-3"></i> Message Students
            </a>
            <a href="teacher_profile.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                <i class="fas fa-user mr-3"></i> My Profile
            </a>
            <a href="logout.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                <i class="fas fa-sign-out-alt mr-3"></i> Logout
            </a>
        </nav>
    </div>
</aside>

<!-- HEADER -->
<header class="gradient-header text-white py-4 px-6 flex justify-between items-center fixed top-0 left-0 right-0 z-40">
    <button class="mobile-toggle text-white text-2xl" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <div class="text-right">
        <h1 class="text-xl font-bold">Welcome, <?= htmlspecialchars($teacherInfo['username']) ?>!</h1>
        <p class="text-sm opacity-90"><?= htmlspecialchars($teacherInfo['email']) ?></p>
    </div>
</header>

<!-- MAIN CONTENT -->
<div class="main-content pt-20" id="mainContent">
    <main class="p-6 max-w-7xl mx-auto">

        <h2 class="text-4xl font-bold text-gray-800 mb-8">Schedule Class</h2>

        <?= $message ?>

        <!-- Schedule Form -->
        <div class="bg-white rounded-2xl shadow-lg p-8 mb-10">
            <h3 class="text-2xl font-bold text-purple-700 mb-8">Create New Class Schedule</h3>
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="schedule_class">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block font-semibold mb-2">Batch *</label>
                        <select name="batch_id" required class="w-full px-4 py-3 border rounded-lg focus:ring-4 focus:ring-purple-200">
                            <option value="">Select batch...</option>
                            <?php foreach($batches as $b): ?>
                                <option value="<?= $b['batch_id'] ?>">
                                    <?= htmlspecialchars($b['batch_code'] . ' - ' . $b['courseName']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold mb-2">Date *</label>
                        <input type="date" name="class_date" required min="<?= date('Y-m-d') ?>" class="w-full px-4 py-3 border rounded-lg">
                    </div>

                    <div>
                        <label class="block font-semibold mb-2">Start Time *</label>
                        <input type="time" name="start_time" required class="w-full px-4 py-3 border rounded-lg">
                    </div>

                    <div>
                        <label class="block font-semibold mb-2">End Time *</label>
                        <input type="time" name="end_time" required class="w-full px-4 py-3 border rounded-lg">
                    </div>

                    <div>
                        <label class="block font-semibold mb-2">Room *</label>
                        <select name="room_id" required class="w-full px-4 py-3 border rounded-lg">
                            <option value="">Choose room...</option>
                            <?php foreach($rooms as $room): ?>
                                <option value="<?= $room['id'] ?>">
                                    <?= htmlspecialchars($room['room_name']) ?> 
                                    (<?= $room['room_type'] ?>, <?= $room['floor_name'] ?>, <?= $room['capacity'] ?> seats)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold mb-2">Topic *</label>
                        <input type="text" name="topic" required placeholder="e.g. Introduction to HTML" class="w-full px-4 py-3 border rounded-lg">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold mb-2">Description (Optional)</label>
                    <textarea name="description" rows="3" placeholder="What will be covered..." class="w-full px-4 py-3 border rounded-lg"></textarea>
                </div>

                <button type="submit" class="w-full bg-gradient-to-r from-purple-600 to-pink-600 text-white font-bold py-4 rounded-xl hover:from-purple-700 hover:to-pink-700 transition transform hover:scale-105 transition">
                    Schedule Class
                </button>
            </form>
        </div>

        <!-- Scheduled Classes -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white p-6">
                <h3 class="text-2xl font-bold">My Scheduled Classes</h3>
            </div>
            <div class="p-6">
                <?php if (empty($scheduled)): ?>
                    <p class="text-center text-gray-500 py-12 text-lg">No classes scheduled yet.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach($scheduled as $s): ?>
                            <div class="bg-gray-50 p-6 rounded-xl border-l-4 border-purple-600 flex justify-between items-center">
                                <div>
                                    <div>
                                        <div class="font-bold text-purple-700">
                                            <?= date('l, M d, Y', strtotime($s['class_date'])) ?> 
                                            | <?= date('h:i A', strtotime($s['start_time'])) ?> – <?= date('h:i A', strtotime($s['end_time'])) ?>
                                        </div>
                                        <div class="text-xl font-bold mt-2"><?= htmlspecialchars($s['topic']) ?></div>
                                        <div class="text-gray-600 mt-1">
                                            <strong><?= htmlspecialchars($s['batch_code']) ?></strong> • 
                                            Room: <strong><?= htmlspecialchars($s['room_number']) ?></strong> • 
                                            <?= $s['room_capacity'] ?> seats
                                        </div>
                                        <?php if($s['description']): ?>
                                            <p class="text-sm text-gray-600 mt-2 italic">"<?= htmlspecialchars($s['description']) ?>"</p>
                                        <?php endif; ?>
                                    </div>
                                    <?php if(strtotime($s['class_date']) >= strtotime('today')): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="delete_class">
                                            <input type="hidden" name="schedule_id" value="<?= $s['schedule_id'] ?>">
                                            <button type="submit" onclick="return confirm('Delete this class?')" 
                                                    class="text-red-600 hover:text-red-800 font-medium">
                                                Delete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('mobile-open');
}
</script>
</body>
</html>