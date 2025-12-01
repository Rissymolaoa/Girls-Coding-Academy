<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// === Get Teacher Info ===
$stmt = $conn->prepare("
    SELECT u.username, u.email, u.phone, u.gender, t.photo 
    FROM users u 
    LEFT JOIN teachers t ON t.user_id = u.user_id 
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$teacherInfo = $stmt->get_result()->fetch_assoc();
$stmt->close();

// === Get teacher_id ===
$stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$teacher_id_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teacher_id_row) {
    die("Teacher profile not found. Contact admin.");
}
$teacher_id = (int)$teacher_id_row['teacher_id'];

// Handle both batch_id and course_id parameters (course_id is an alias for batch_id)
$selected_batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 
                     (isset($_GET['course_id']) ? (int)$_GET['course_id'] : null);

// === Handle Update & Delete ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_item') {
        $id = (int)$_POST['id'];
        $type = $_POST['type']; // 'test' or 'activity'
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $due_date = $_POST['due_date'];
        $max_score = ($type === 'test') ? (float)$_POST['max_score'] : null;

        $table = $type === 'test' ? 'tests' : 'activities';
        $id_col = $type === 'test' ? 'test_id' : 'activity_id';

        $sql = "UPDATE $table SET title = ?, description = ?, due_date = ?";
        $types = "sss";
        $params = [$title, $description, $due_date];

        if ($type === 'test') {
            $sql .= ", max_score = ?";
            $types .= "d";
            $params[] = $max_score;
        }

        $sql .= " WHERE $id_col = ? AND teacher_id = ?";
        $types .= "ii";
        $params[] = $id;
        $params[] = $teacher_id;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();

        // Refresh page
        header("Location: view_assigned_tests.php?batch_id=$selected_batch_id");
        exit();
    }

    if ($_POST['action'] === 'delete_item') {
        $id = (int)$_POST['id'];
        $type = $_POST['type'];
        $table = $type === 'test' ? 'tests' : 'activities';
        $id_col = $type === 'test' ? 'test_id' : 'activity_id';

        $stmt = $conn->prepare("DELETE FROM $table WHERE $id_col = ? AND teacher_id = ?");
        $stmt->bind_param("ii", $id, $teacher_id);
        $stmt->execute();
        $stmt->close();

        header("Location: view_assigned_tests.php?batch_id=$selected_batch_id");
        exit();
    }
}

// === Fetch All Assigned Batches for Dropdown ===
$batches = [];
$stmt = $conn->prepare("
    SELECT b.batch_id, b.batch_code, c.courseName 
    FROM course_assignments ca 
    JOIN batches b ON ca.batch_id = b.batch_id 
    JOIN courses c ON b.course_id = c.course_id 
    WHERE ca.teacher_id = ? 
    ORDER BY b.start_date DESC
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $batches[] = $row;
}
$stmt->close();

// === Get Selected Batch Name ===
$batch_name = "All Batches";
if ($selected_batch_id) {
    $stmt = $conn->prepare("
        SELECT b.batch_code, c.courseName 
        FROM batches b 
        JOIN courses c ON b.course_id = c.course_id 
        WHERE b.batch_id = ?
    ");
    $stmt->bind_param("i", $selected_batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $batch_info = $result->fetch_assoc();
    $stmt->close();

    if ($batch_info) {
        $batch_name = htmlspecialchars($batch_info['batch_code']) . " - " . htmlspecialchars($batch_info['courseName']);
    }
}

// === Fetch Tests + Activities ===
$items = [];
if ($selected_batch_id) {
    $stmt = $conn->prepare("
        (SELECT 'test' as type, test_id as id, title, description, due_date, max_score, resource_file, created_at 
         FROM tests 
         WHERE batch_id = ? AND teacher_id = ?)
        UNION ALL
        (SELECT 'activity' as type, activity_id as id, title, description, due_date, NULL as max_score, resource_file, created_at 
         FROM activities 
         WHERE batch_id = ? AND teacher_id = ?)
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("iiii", $selected_batch_id, $teacher_id, $selected_batch_id, $teacher_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Tests & Activities - Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-header { background: linear-gradient(90deg, #7b2cbf, #5a189a); }
        .sidebar { width: 250px; background: linear-gradient(180deg, #7b2cbf, #5a189a); position: fixed; height: 100vh; overflow-y: auto; transition: transform 0.3s ease; z-index: 1000; }
        .sidebar.hidden { transform: translateX(-100%); }
        .sidebar-link:hover { background: rgba(255,255,255,0.1); padding-left: 1.5rem; }
        .sidebar-link.active { background: rgba(255,255,255,0.2); border-left: 4px solid white; }
        .main-content { margin-left: 250px; transition: margin-left 0.3s ease; }
        .main-content.expanded { margin-left: 0; }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(0,0,0,0.15); transition: 0.3s; }
        .mobile-toggle { display: none; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-toggle { display: block; }
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
                <i class="fas fa-home mr-3"></i> Dashboard
            </a>
            <a href="manage_teacher_courses.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                <i class="fas fa-chalkboard-teacher mr-3"></i> Manage Courses
            </a>
            <a href="upload_materials.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                <i class="fas fa-book mr-3"></i> Upload Materials
            </a>
            <a href="view_assigned_tests.php" class="sidebar-link active flex items-center text-white py-3 px-4 rounded mb-2">
                <i class="fas fa-list-check mr-3"></i> Tests & Activities
            </a>
            <a href="grades.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                <i class="fas fa-clipboard-check mr-3"></i> Grades
            </a>
            <a href="mark_attendance.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                <i class="fas fa-calendar-check mr-3"></i> Attendance
            </a>
            <a href="logout.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                <i class="fas fa-sign-out-alt mr-3"></i> Logout
            </a>
        </nav>
    </div>
</aside>

<!-- Main Content -->
<div class="main-content" id="mainContent">
    <!-- Header -->
    <header class="gradient-header text-white py-4 px-6 flex flex-col sm:flex-row justify-between items-start sm:items-center">
        <button class="mobile-toggle text-white text-2xl mb-3 sm:mb-0" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <div class="text-right sm:text-left">
            <h1 class="text-xl font-semibold">Tests & Activities</h1>
            <p class="text-sm">Teacher: <?= htmlspecialchars($teacherInfo['username'] ?? 'Teacher') ?> | <?= htmlspecialchars($teacherInfo['email'] ?? '') ?></p>
        </div>
    </header>

    <main class="p-6">
        <!-- Batch Selector -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Select Batch</h3>
            <form method="GET" class="flex flex-col sm:flex-row gap-4">
                <select name="batch_id" class="form-select block w-full sm:w-96 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600" onchange="this.form.submit()" required>
                    <option value="">-- Choose a Batch --</option>
                    <?php foreach ($batches as $b): ?>
                        <option value="<?= $b['batch_id'] ?>" <?= $selected_batch_id == $b['batch_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['batch_code'] . " - " . $b['courseName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-8 rounded-lg">
                    View Items
                </button>
            </form>
        </div>

        <!-- Items List -->
        <?php if ($selected_batch_id): ?>
            <?php if (!empty($items)): ?>
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-2xl font-bold text-gray-800"><?= $batch_name ?></h2>
                        <p class="text-gray-600"><?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?> found</p>
                    </div>

                    <div class="p-6 space-y-6">
                        <?php foreach ($items as $item): 
                            $isTest = $item['type'] === 'test';
                            $icon = $isTest ? 'fa-file-alt' : 'fa-clipboard-list';
                            $color = $isTest ? 'blue' : 'green';
                        ?>
                            <div class="border border-gray-200 rounded-lg p-6 card-hover bg-gray-50">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex-1">
                                        <h4 class="text-xl font-bold text-gray-800 flex items-center">
                                            <i class="fas <?= $icon ?> text-<?= $color ?>-600 mr-3"></i>
                                            <?= htmlspecialchars($item['title']) ?>
                                            <span class="ml-3 text-sm font-normal text-gray-500">(<?= ucfirst($item['type']) ?>)</span>
                                        </h4>
                                        <p class="text-gray-700 mt-2"><?= nl2br(htmlspecialchars($item['description'])) ?></p>

                                        <div class="flex flex-wrap gap-6 mt-4 text-sm text-gray-600">
                                            <span><strong>Due Date:</strong> <?= date('d M Y', strtotime($item['due_date'])) ?></span>
                                            <?php if ($isTest): ?>
                                                <span><strong>Max Score:</strong> <?= htmlspecialchars($item['max_score']) ?></span>
                                            <?php endif; ?>
                                            <span><strong>Posted:</strong> <?= date('d M Y', strtotime($item['created_at'])) ?></span>
                                        </div>

                                        <?php if (!empty($item['resource_file']) && file_exists($item['resource_file'])): ?>
                                            <a href="<?= htmlspecialchars($item['resource_file']) ?>" target="_blank" class="inline-block mt-3 text-purple-600 hover:underline text-sm">
                                                <i class="fas fa-paperclip mr-1"></i> View Attached File
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <div class="flex gap-3 ml-4">
                                        <button onclick="toggleEdit(<?= $item['id'] ?>)" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                            Edit
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this <?= $item['type'] ?>?');" class="inline">
                                            <input type="hidden" name="action" value="delete_item">
                                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                            <input type="hidden" name="type" value="<?= $item['type'] ?>">
                                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <!-- Edit Form -->
                                <div id="edit-<?= $item['id'] ?>" style="display:none;" class="bg-white border border-gray-300 rounded-lg p-5 mt-4">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_item">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <input type="hidden" name="type" value="<?= $item['type'] ?>">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Title</label>
                                                <input type="text" name="title" value="<?= htmlspecialchars($item['title']) ?>" class="w-full border rounded-lg px-3 py-2 mt-1" required>
                                            </div>
                                            <?php if ($isTest): ?>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">Max Score</label>
                                                    <input type="number" step="0.1" name="max_score" value="<?= $item['max_score'] ?>" class="w-full border rounded-lg px-3 py-2 mt-1" required>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Due Date</label>
                                                <input type="date" name="due_date" value="<?= $item['due_date'] ?>" class="w-full border rounded-lg px-3 py-2 mt-1" required>
                                            </div>
                                            <div class="md:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700">Description</label>
                                                <textarea name="description" rows="3" class="w-full border rounded-lg px-3 py-2 mt-1" required><?= htmlspecialchars($item['description']) ?></textarea>
                                            </div>
                                        </div>
                                        <div class="mt-5 text-right">
                                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg">
                                                Save Changes
                                            </button>
                                            <button type="button" onclick="toggleEdit(<?= $item['id'] ?>)" class="ml-3 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-6 rounded-lg">
                                                Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow-lg p-12 text-center">
                    <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                    <p class="text-xl text-gray-600">No tests or activities found for this batch.</p>
                    <a href="manage_teacher_courses.php?course_id=<?= $selected_batch_id ?>" class="mt-4 inline-block bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-6 rounded-lg">
                        Create Your First One →
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('mobile-open');
    }
    function toggleEdit(id) {
        const el = document.getElementById('edit-' + id);
        el.style.display = el.style.display === 'block' ? 'none' : 'block';
    }
</script>

</body>
</html>