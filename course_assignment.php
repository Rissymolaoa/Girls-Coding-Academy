<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}
require_once 'db.php';

// Stats
$total_teachers = $conn->query("SELECT COUNT(*) FROM users WHERE role='teacher'")->fetch_row()[0];
$total_batches = $conn->query("SELECT COUNT(*) FROM batches")->fetch_row()[0];
$total_assignments = $conn->query("SELECT COUNT(*) FROM course_assignments")->fetch_row()[0];

// Handle add assignment
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_assignment'])) {
    $teacher_user_id = intval($_POST['teacher_user_id']);
    $batch_id = intval($_POST['batch_id']);

    $teacher = $conn->query("SELECT teacher_id FROM teachers WHERE user_id = $teacher_user_id")->fetch_assoc();
    if (!$teacher) {
        $error = "Selected user is not a registered teacher.";
    } else {
        $teacher_id = $teacher['teacher_id'];
        $check = $conn->prepare("SELECT assignment_id FROM course_assignments WHERE teacher_id = ? AND batch_id = ?");
        $check->bind_param("ii", $teacher_id, $batch_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "This teacher is already assigned to this batch.";
        } else {
            $stmt = $conn->prepare("INSERT INTO course_assignments (teacher_id, batch_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $teacher_id, $batch_id);
            $success = $stmt->execute() ? "Teacher assigned successfully!" : "Error: " . $stmt->error;
            $stmt->close();
        }
        $check->close();
    }
}

// Handle edit
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_assignment'])) {
    $assignment_id = intval($_POST['assignment_id']);
    $teacher_user_id = intval($_POST['teacher_user_id']);
    $batch_id = intval($_POST['batch_id']);

    $teacher = $conn->query("SELECT teacher_id FROM teachers WHERE user_id = $teacher_user_id")->fetch_assoc();
    if (!$teacher) {
        $error = "Invalid teacher.";
    } else {
        $teacher_id = $teacher['teacher_id'];
        $check = $conn->prepare("SELECT assignment_id FROM course_assignments WHERE teacher_id = ? AND batch_id = ? AND assignment_id != ?");
        $check->bind_param("iii", $teacher_id, $batch_id, $assignment_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "This teacher is already assigned to this batch.";
        } else {
            $stmt = $conn->prepare("UPDATE course_assignments SET teacher_id = ?, batch_id = ? WHERE assignment_id = ?");
            $stmt->bind_param("iii", $teacher_id, $batch_id, $assignment_id);
            $success = $stmt->execute() ? "Assignment updated!" : "Error: " . $stmt->error;
            $stmt->close();
        }
        $check->close();
    }
}

// Fetch data
$teachers = $conn->query("SELECT user_id, CONCAT(firstName,' ',lastName) as name FROM users WHERE role='teacher' ORDER BY firstName");
$batches = $conn->query("SELECT batch_id, batch_code FROM batches ORDER BY batch_code DESC");

$assignments = $conn->query("
    SELECT ca.assignment_id, u.firstName, u.lastName, u.user_id, b.batch_code, b.batch_id, ca.created_at
    FROM course_assignments ca
    JOIN teachers t ON ca.teacher_id = t.teacher_id
    JOIN users u ON t.user_id = u.user_id
    JOIN batches b ON ca.batch_id = b.batch_id
    ORDER BY ca.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Teachers to Batches</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .course-card {
            transition: all 0.3s ease;
        }
        .course-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
        }
        .course-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        .main-content {
            margin-left: 220px;
            transition: margin-left 0.3s ease;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
        :root { --primary: #4f46e5; --primary-light: #6366f1; --primary-dark: #4338ca; }
        .bg-primary { background-color: var(--primary); }
        .text-primary { color: var(--primary); }
        .hover\:bg-primary-dark:hover { background-color: var(--primary-dark); }
        .card-hover:hover { transform: translateY(-8px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        
    </style>

</head>
<body class="h-full bg-gray-50">

<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="ml-64 mt-16 p-8 min-h-screen">
    <div class="max-w-7xl mx-auto">

        <div class="mb-10">
            <h1 class="text-4xl font-bold text-gray-800">Assign Teachers to Batches</h1>
            <p class="text-gray-600 mt-2">Manage which teachers are responsible for each batch</p>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-10">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 card-hover transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Teachers</p>
                        <p class="text-4xl font-bold text-gray-800 mt-2"><?= $total_teachers ?></p>
                    </div>
                    <div class="w-14 h-14 bg-indigo-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-chalkboard-teacher text-2xl text-indigo-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 card-hover transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Active Batches</p>
                        <p class="text-4xl font-bold text-gray-800 mt-2"><?= $total_batches ?></p>
                    </div>
                    <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-layer-group text-2xl text-blue-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 card-hover transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Assignments</p>
                        <p class="text-4xl font-bold text-gray-800 mt-2"><?= $total_assignments ?></p>
                    </div>
                    <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-link text-2xl text-green-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Assignment Form -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 mb-10">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Create New Assignment</h2>
            <?php if (isset($success)): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-lg mb-6"><?= $success ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-lg mb-6"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Teacher</label>
                    <select name="teacher_user_id" required class="w-full px-5 py-4 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200 focus:border-indigo-500 transition">
                        <option value="">Choose Teacher</option>
                        <?php while ($t = $teachers->fetch_assoc()): ?>
                            <option value="<?= $t['user_id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                        <?php endwhile; $teachers->data_seek(0); ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Batch</label>
                    <select name="batch_id" required class="w-full px-5 py-4 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200 focus:border-indigo-500 transition">
                        <option value="">Choose Batch</option>
                        <?php while ($b = $batches->fetch_assoc()): ?>
                            <option value="<?= $b['batch_id'] ?>"><?= htmlspecialchars($b['batch_code']) ?></option>
                        <?php endwhile; $batches->data_seek(0); ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" name="add_assignment" 
                            class="w-full bg-primary hover:bg-primary-dark text-white font-semibold py-4 px-8 rounded-xl shadow-lg transition flex items-center justify-center gap-3">
                        <i class="fas fa-plus"></i> Assign Teacher
                    </button>
                </div>
            </form>
        </div>

        <!-- Assignments Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-8 border-b border-gray-200">
                <h2 class="text-2xl font-bold text-gray-800">Current Assignments</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white">
                        <tr>
                            <th class="px-8 py-5 text-left">Teacher</th>
                            <th class="px-8 py-5 text-left">Batch</th>
                            <th class="px-8 py-5 text-left">Assigned On</th>
                            <th class="px-8 py-5 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($assignments->num_rows === 0): ?>
                            <tr>
                                <td colspan="4" class="text-center py-16 text-gray-500">No assignments yet</td>
                            </tr>
                        <?php else: while ($a = $assignments->fetch_assoc()): ?>
                            <tr class="border-b hover:bg-gray-50 transition">
                                <td class="px-8 py-6 font-medium">
                                    <?= htmlspecialchars($a['firstName'] . ' ' . $a['lastName']) ?>
                                </td>
                                <td class="px-8 py-6">
                                    <span class="inline-block px-4 py-2 bg-indigo-100 text-indigo-700 rounded-full text-sm font-medium">
                                        <?= htmlspecialchars($a['batch_code']) ?>
                                    </span>
                                </td>
                                <td class="px-8 py-6 text-gray-600">
                                    <?= date("d M Y, H:i", strtotime($a['created_at'])) ?>
                                </td>
                                <td class="px-8 py-6 text-center">
                                    <button onclick='openEditModal(<?= json_encode($a) ?>)' 
                                            class="text-indigo-600 hover:text-indigo-800 font-medium mr-4">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="delete_assignment.php?id=<?= $a['assignment_id'] ?>" 
                                       onclick="return confirm('Remove this assignment?')"
                                       class="text-red-600 hover:text-red-800 font-medium">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4" onclick="if(event.target===this) this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full p-8">
        <h3 class="text-2xl font-bold text-gray-800 mb-6">Edit Assignment</h3>
        <form method="POST">
            <input type="hidden" name="assignment_id" id="edit-id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Teacher</label>
                    <select name="teacher_user_id" id="edit-teacher" required class="w-full px-5 py-4 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                        <option value="">Choose Teacher</option>
                        <?php $teachers->data_seek(0); while ($t = $teachers->fetch_assoc()): ?>
                            <option value="<?= $t['user_id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Batch</label>
                    <select name="batch_id" id="edit-batch" required class="w-full px-5 py-4 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                        <option value="">Choose Batch</option>
                        <?php $batches->data_seek(0); while ($b = $batches->fetch_assoc()): ?>
                            <option value="<?= $b['batch_id'] ?>"><?= htmlspecialchars($b['batch_code']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-4">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" 
                        class="px-8 py-4 border border-gray-300 rounded-xl font-medium hover:bg-gray-50">Cancel</button>
                <button type="submit" name="edit_assignment" 
                        class="px-8 py-4 bg-primary text-white font-medium rounded-xl hover:bg-primary-dark transition">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(data) {
    document.getElementById('edit-id').value = data.assignment_id;
    document.getElementById('edit-teacher').value = data.user_id;
    document.getElementById('edit-batch').value = data.batch_id;
    document.getElementById('editModal').classList.remove('hidden');
}
</script>
</body>
</html>