<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}
require_once 'db.php';

// Stats
$total_parents = $conn->query("SELECT COUNT(*) FROM parents")->fetch_row()[0];
$total_students = $conn->query("SELECT COUNT(*) FROM students")->fetch_row()[0];
$total_assignments = $conn->query("SELECT COUNT(*) FROM parent_students")->fetch_row()[0];

// Handle actions
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if ($_POST['action'] === 'add') {
        $parent_id = intval($_POST['parent_id']);
        $student_id = intval($_POST['student_id']);
        $relationship = $_POST['relationship'];

        $check = $conn->prepare("SELECT id FROM parent_students WHERE parent_id = ? AND student_id = ?");
        $check->bind_param("ii", $parent_id, $student_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "This student is already assigned to this parent.";
        } else {
            $stmt = $conn->prepare("INSERT INTO parent_students (parent_id, student_id, relationship) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $parent_id, $student_id, $relationship);
            $success = $stmt->execute() ? "Student assigned successfully!" : "Error: " . $stmt->error;
            $stmt->close();
        }
        $check->close();
    }

    if ($_POST['action'] === 'edit') {
        $id = intval($_POST['id']);
        $student_id = intval($_POST['student_id']);
        $relationship = $_POST['relationship'];

        $stmt = $conn->prepare("SELECT parent_id FROM parent_students WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $parent_id = $stmt->get_result()->fetch_assoc()['parent_id'] ?? null;
        $stmt->close();

        if ($parent_id) {
            $check = $conn->prepare("SELECT id FROM parent_students WHERE parent_id = ? AND student_id = ? AND id != ?");
            $check->bind_param("iii", $parent_id, $student_id, $id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = "This student is already assigned to this parent.";
            } else {
                $stmt = $conn->prepare("UPDATE parent_students SET student_id = ?, relationship = ? WHERE id = ?");
                $stmt->bind_param("isi", $student_id, $relationship, $id);
                $success = $stmt->execute() ? "Assignment updated!" : "Error: " . $stmt->error;
                $stmt->close();
            }
            $check->close();
        }
    }

    if ($_POST['action'] === 'delete') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM parent_students WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute() ? "Assignment removed!" : "Error: " . $stmt->error;
        $stmt->close();
    }
}

// Fetch data
$parents = $conn->query("SELECT p.parent_id, u.firstName, u.lastName FROM parents p JOIN users u ON p.user_id = u.user_id ORDER BY u.firstName");
$students = $conn->query("SELECT s.student_id, u.firstName, u.lastName FROM students s JOIN users u ON s.user_id = u.user_id ORDER BY u.firstName");

$assignments = $conn->query("
    SELECT ps.id, p.parent_id, u1.firstName AS pFirst, u1.lastName AS pLast,
           s.student_id, u2.firstName AS sFirst, u2.lastName AS sLast, ps.relationship
    FROM parent_students ps
    JOIN parents p ON ps.parent_id = p.parent_id
    JOIN users u1 ON p.user_id = u1.user_id
    JOIN students s ON ps.student_id = s.student_id
    JOIN users u2 ON s.user_id = u2.user_id
    ORDER BY ps.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Students to Parents</title>
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
            <h1 class="text-4xl font-bold text-gray-800">Assign Students to Parents</h1>
            <p class="text-gray-600 mt-2">Link students with their parents or guardians</p>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-10">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 card-hover transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Parents</p>
                        <p class="text-4xl font-bold text-gray-800 mt-2"><?= $total_parents ?></p>
                    </div>
                    <div class="w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-user-tie text-2xl text-purple-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 card-hover transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Students</p>
                        <p class="text-4xl font-bold text-gray-800 mt-2"><?= $total_students ?></p>
                    </div>
                    <div class="w-14 h-14 bg-indigo-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-user-graduate text-2xl text-indigo-600"></i>
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

        <!-- Assign Form -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 mb-10">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Assign Student to Parent</h2>
            <?php if (isset($success)): ?>
                <div class="mb-6 px-6 py-4 bg-green-50 border border-green-200 text-green-700 rounded-lg"><?= $success ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="mb-6 px-6 py-4 bg-red-50 border border-red-200 text-red-700 rounded-lg"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <input type="hidden" name="action" value="add">
                <select name="parent_id" required class="px-5 py-4 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                    <option value="">Select Parent</option>
                    <?php while ($p = $parents->fetch_assoc()): ?>
                        <option value="<?= $p['parent_id'] ?>"><?= htmlspecialchars($p['firstName'] . ' ' . $p['lastName']) ?></option>
                    <?php endwhile; $parents->data_seek(0); ?>
                </select>
                <select name="student_id" required class="px-5 py-4 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                    <option value="">Select Student</option>
                    <?php while ($s = $students->fetch_assoc()): ?>
                        <option value="<?= $s['student_id'] ?>"><?= htmlspecialchars($s['firstName'] . ' ' . $s['lastName']) ?></option>
                    <?php endwhile; $students->data_seek(0); ?>
                </select>
                <select name="relationship" required class="px-5 py-4 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                    <option value="">Relationship</option>
                    <option value="Mother">Mother</option>
                    <option value="Father">Father</option>
                    <option value="Guardian">Guardian</option>
                </select>
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-semibold py-4 px-8 rounded-xl shadow-lg transition flex items-center justify-center gap-3">
                    <i class="fas fa-plus"></i> Assign
                </button>
            </form>
        </div>

        <!-- Assignments Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-8 border-b border-gray-200">
                <h2 class="text-2xl font-bold text-gray-800">Current Parent-Student Links</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white">
                        <tr>
                            <th class="px-8 py-5 text-left">Parent</th>
                            <th class="px-8 py-5 text-left">Student</th>
                            <th class="px-8 py-5 text-left">Relationship</th>
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
                                    <?= htmlspecialchars($a['pFirst'] . ' ' . $a['pLast']) ?>
                                </td>
                                <td class="px-8 py-6">
                                    <?= htmlspecialchars($a['sFirst'] . ' ' . $a['sLast']) ?>
                                </td>
                                <td class="px-8 py-6">
                                    <span class="inline-block px-4 py-2 bg-purple-100 text-purple-700 rounded-full text-sm font-medium">
                                        <?= htmlspecialchars($a['relationship']) ?>
                                    </span>
                                </td>
                                <td class="px-8 py-6 text-center">
                                    <button onclick='openEdit(<?= json_encode($a) ?>)' 
                                            class="text-indigo-600 hover:text-indigo-800 font-medium mr-6">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                        <button type="submit" onclick="return confirm('Remove this link?')"
                                                class="text-red-600 hover:text-red-800 font-medium">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
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
        <h3 class="text-2xl font-bold text-gray-800 mb-6">Edit Parent-Student Link</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <select name="student_id" id="edit-student" required class="px-5 py-4 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                    <option value="">Select Student</option>
                    <?php $students->data_seek(0); while ($s = $students->fetch_assoc()): ?>
                        <option value="<?= $s['student_id'] ?>"><?= htmlspecialchars($s['firstName'] . ' ' . $s['lastName']) ?></option>
                    <?php endwhile; ?>
                </select>
                <select name="relationship" id="edit-relation" required class="px-5 py-4 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                    <option value="Mother">Mother</option>
                    <option value="Father">Father</option>
                    <option value="Guardian">Guardian</option>
                </select>
            </div>
            <div class="flex justify-end gap-4">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" 
                        class="px-8 py-4 border border-gray-300 rounded-xl font-medium hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-8 py-4 bg-primary text-white font-medium rounded-xl hover:bg-primary-dark transition">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(data) {
    document.getElementById('edit-id').value = data.id;
    document.getElementById('edit-student').value = data.student_id;
    document.getElementById('edit-relation').value = data.relationship;
    document.getElementById('editModal').classList.remove('hidden');
}
</script>
</body>
</html>