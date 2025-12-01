<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}
require_once 'db.php';

// Handle messages
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_batch'])) {
    $course_id  = $_POST['course_id'];
    $batch_code = trim($_POST['batch_code']);
    $start_date = $_POST['start_date'];
    $end_date   = $_POST['end_date'];
    $status     = in_array($_POST['status'], ['active','completed','inactive']) ? $_POST['status'] : 'active';

    $stmt = $conn->prepare("INSERT INTO batches (batch_code, course_id, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sisss", $batch_code, $course_id, $start_date, $end_date, $status);
    $message = $stmt->execute() ? "Batch created successfully!" : "Error: " . $stmt->error;
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_batch'])) {
    $batch_id   = intval($_POST['batch_id']);
    $course_id  = $_POST['course_id'];
    $batch_code = trim($_POST['batch_code']);
    $start_date = $_POST['start_date'];
    $end_date   = $_POST['end_date'];
    $status     = in_array($_POST['status'], ['active','completed','inactive']) ? $_POST['status'] : 'active';

    $stmt = $conn->prepare("UPDATE batches SET course_id=?, batch_code=?, start_date=?, end_date=?, status=? WHERE batch_id=?");
    $stmt->bind_param("issssi", $course_id, $batch_code, $start_date, $end_date, $status, $batch_id);
    $message = $stmt->execute() ? "Batch updated successfully!" : "Error updating: " . $stmt->error;
    $stmt->close();
}

if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $conn->begin_transaction();
    try {
        $tables = ['test_submissions','tests','internal_grades','materials','course_assignments','activity_submissions','activities','attendance','course_enrollments'];
        foreach ($tables as $table) {
            if ($table === 'test_submissions' || $table === 'activity_submissions') continue;
            $stmt = $conn->prepare("DELETE FROM $table WHERE batch_id = ?");
            $stmt->bind_param("i", $delete_id);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $conn->prepare("DELETE FROM batches WHERE batch_id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        $message = "Batch and all related data deleted!";
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error: " . $e->getMessage();
    }
}

// Search
$search = trim($_GET['search'] ?? '');
$where = $search ? "AND (b.batch_code LIKE ? OR c.courseName LIKE ?)" : "";
$like = $search ? "%$search%" : "";

// Stats
$total_batches = $conn->query("SELECT COUNT(*) FROM batches")->fetch_row()[0];
$active_batches = $conn->query("SELECT COUNT(*) FROM batches WHERE status='active'")->fetch_row()[0];
$completed_batches = $conn->query("SELECT COUNT(*) FROM batches WHERE status='completed'")->fetch_row()[0];

// Fetch batches
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$sql = "SELECT b.*, c.courseName FROM batches b JOIN courses c ON b.course_id = c.course_id WHERE 1 $where ORDER BY b.start_date DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if ($search) $stmt->bind_param("sssii", $like, $like, $limit, $offset);
else $stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$batches = $stmt->get_result();

$total_result = $conn->query("SELECT COUNT(*) as total FROM batches b JOIN courses c ON b.course_id = c.course_id WHERE 1 $where" . ($search ? " AND (b.batch_code LIKE '%$search%' OR c.courseName LIKE '%$search%')" : ""));
$total_pages = ceil($total_result->fetch_assoc()['total'] / $limit);

$courses = $conn->query("SELECT course_id, courseName FROM courses ORDER BY courseName");
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Batches - Girls Coding Academy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
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

<div class="ml-64 mt-16 min-h-screen">
    <div class="p-8 max-w-7xl mx-auto">

        <div class="mb-10">
            <h1 class="text-4xl font-bold text-gray-800">Manage Batches</h1>
            <p class="text-gray-600 mt-2">Create and manage course batches</p>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-10">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 card-hover transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Batches</p>
                        <p class="text-4xl font-bold text-gray-800 mt-2"><?= $total_batches ?></p>
                    </div>
                    <div class="w-14 h-14 bg-indigo-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-layer-group text-2xl text-indigo-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 card-hover transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Active Batches</p>
                        <p class="text-4xl font-bold text-green-600 mt-2"><?= $active_batches ?></p>
                    </div>
                    <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-play text-2xl text-green-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 card-hover transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Completed</p>
                        <p class="text-4xl font-bold text-gray-500 mt-2"><?= $completed_batches ?></p>
                    </div>
                    <div class="w-14 h-14 bg-gray-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-check-circle text-2xl text-gray-500"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Batch Form -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 mb-10">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Create New Batch</h2>
            <?php if ($message): ?>
                <div class="mb-6 px-6 py-4 rounded-lg <?= strpos($message, 'successfully') ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <select name="course_id" required class="px-5 py-4 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                    <option value="">Select Course</option>
                    <?php while ($c = $courses->fetch_assoc()): ?>
                        <option value="<?= $c['course_id'] ?>"><?= htmlspecialchars($c['courseName']) ?></option>
                    <?php endwhile; $courses->data_seek(0); ?>
                </select>
                <input type="text" name="batch_code" placeholder="Batch Code (e.g. B2025-01)" required class="px-5 py-4 border border-gray-300 rounded-xl">
                <input type="date" name="start_date" required class="px-5 py-4 border border-gray-300 rounded-xl">
                <input type="date" name="end_date" required class="px-5 py-4 border border-gray-300 rounded-xl">
                <select name="status" class="px-5 py-4 border border-gray-300 rounded-xl">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="completed">Completed</option>
                </select>
                <div class="col-span-full lg:col-span-1">
                    <button type="submit" name="add_batch" class="w-full bg-primary hover:bg-primary-dark text-white font-semibold py-4 px-8 rounded-xl shadow-lg transition flex items-center justify-center gap-3">
                        <i class="fas fa-plus"></i> Create Batch
                    </button>
                </div>
            </form>
        </div>

        <!-- Search -->
        <form method="get" class="mb-8">
            <div class="flex gap-4">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search batches or courses..." 
                       class="flex-1 px-6 py-4 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200 text-lg">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-8 py-4 rounded-xl font-semibold transition flex items-center gap-3">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
        </form>

        <!-- Batches Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-8 border-b border-gray-200">
                <h2 class="text-2xl font-bold text-gray-800">All Batches</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white">
                        <tr>
                            <th class="px-8 py-5 text-left">Course</th>
                            <th class="px-8 py-5 text-left">Batch Code</th>
                            <th class="px-8 py-5 text-left">Duration</th>
                            <th class="px-8 py-5 text-left">Status</th>
                            <th class="px-8 py-5 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($batches->num_rows === 0): ?>
                            <tr>
                                <td colspan="5" class="text-center py-16 text-gray-500">No batches found</td>
                            </tr>
                        <?php else: while ($b = $batches->fetch_assoc()): ?>
                            <tr class="border-b hover:bg-gray-50 transition">
                                <td class="px-8 py-6 font-medium"><?= htmlspecialchars($b['courseName']) ?></td>
                                <td class="px-8 py-6">
                                    <span class="inline-block px-4 py-2 bg-indigo-100 text-indigo-700 rounded-full font-medium">
                                        <?= htmlspecialchars($b['batch_code']) ?>
                                    </span>
                                </td>
                                <td class="px-8 py-6 text-gray-600">
                                    <?= date("d M Y", strtotime($b['start_date'])) ?> - <?= date("d M Y", strtotime($b['end_date'])) ?>
                                </td>
                                <td class="px-8 py-6">
                                    <span class="px-4 py-2 rounded-full text-sm font-bold
                                        <?= $b['status']=='active' ? 'bg-green-100 text-green-700' : 
                                            ($b['status']=='completed' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700') ?>">
                                        <?= ucfirst($b['status']) ?>
                                    </span>
                                </td>
                                <td class="px-8 py-6 text-center">
                                    <button onclick='openEdit(<?= json_encode($b) ?>)' 
                                            class="text-indigo-600 hover:text-indigo-800 font-medium mr-6">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="?delete=<?= $b['batch_id'] ?>" 
                                       onclick="return confirm('Delete this batch and ALL related data?')"
                                       class="text-red-600 hover:text-red-800 font-medium">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center gap-3 p-6 bg-gray-50">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?><?= $search ? '&search='.urlencode($search) : '' ?>"
                           class="px-5 py-3 rounded-lg font-medium <?= $i === $page ? 'bg-primary text-white' : 'bg-white border border-gray-300 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4" onclick="if(event.target===this) this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full p-8">
        <h3 class="text-2xl font-bold text-gray-800 mb-6">Edit Batch</h3>
        <form method="POST">
            <input type="hidden" name="batch_id" id="edit-id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <select name="course_id" id="edit-course" required class="px-5 py-4 border border-gray-300 rounded-xl">
                    <option value="">Select Course</option>
                    <?php $courses->data_seek(0); while ($c = $courses->fetch_assoc()): ?>
                        <option value="<?= $c['course_id'] ?>"><?= htmlspecialchars($c['courseName']) ?></option>
                    <?php endwhile; ?>
                </select>
                <input type="text" name="batch_code" id="edit-code" required placeholder="Batch Code" class="px-5 py-4 border border-gray-300 rounded-xl">
                <input type="date" name="start_date" id="edit-start" required class="px-5 py-4 border border-gray-300 rounded-xl">
                <input type="date" name="end_date" id="edit-end" required class="px-5 py-4 border border-gray-300 rounded-xl">
                <select name="status" id="edit-status" class="px-5 py-4 border border-gray-300 rounded-xl">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            <div class="flex justify-end gap-4">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" 
                        class="px-8 py-4 border border-gray-300 rounded-xl font-medium hover:bg-gray-50">Cancel</button>
                <button type="submit" name="update_batch" 
                        class="px-8 py-4 bg-primary text-white font-medium rounded-xl hover:bg-primary-dark transition">Update Batch</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(data) {
    document.getElementById('edit-id').value = data.batch_id;
    document.getElementById('edit-course').value = data.course_id;
    document.getElementById('edit-code').value = data.batch_code;
    document.getElementById('edit-start').value = data.start_date;
    document.getElementById('edit-end').value = data.end_date;
    document.getElementById('edit-status').value = data.status;
    document.getElementById('editModal').classList.remove('hidden');
}
</script>
</body>
</html>