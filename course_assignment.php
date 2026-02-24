<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

require_once 'db.php';

// ────────────────────────────────────────────────
//   Fetch statistics
// ────────────────────────────────────────────────
$total_teachers    = $conn->query("SELECT COUNT(*) FROM users WHERE role='teacher'")->fetch_row()[0] ?? 0;
$total_batches     = $conn->query("SELECT COUNT(*) FROM batches")->fetch_row()[0] ?? 0;
$total_assignments = $conn->query("SELECT COUNT(*) FROM course_assignments")->fetch_row()[0] ?? 0;

// ────────────────────────────────────────────────
//   Handle ADD assignment
// ────────────────────────────────────────────────
$success_msg = $error_msg = null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_assignment'])) {
    $teacher_user_id = (int)$_POST['teacher_user_id'];
    $batch_id        = (int)$_POST['batch_id'];

    $teacher = $conn->query("SELECT teacher_id FROM teachers WHERE user_id = $teacher_user_id")->fetch_assoc();
    if (!$teacher) {
        $error_msg = "Selected user is not a registered teacher.";
    } else {
        $teacher_id = $teacher['teacher_id'];

        $check = $conn->prepare("SELECT 1 FROM course_assignments WHERE teacher_id = ? AND batch_id = ?");
        $check->bind_param("ii", $teacher_id, $batch_id);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error_msg = "This teacher is already assigned to this batch.";
        } else {
            $stmt = $conn->prepare("INSERT INTO course_assignments (teacher_id, batch_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $teacher_id, $batch_id);
            if ($stmt->execute()) {
                $success_msg = "Teacher successfully assigned to the batch!";
            } else {
                $error_msg = "Database error: " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}

// ────────────────────────────────────────────────
//   Handle EDIT assignment
// ────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_assignment'])) {
    $assignment_id   = (int)$_POST['assignment_id'];
    $teacher_user_id = (int)$_POST['teacher_user_id'];
    $batch_id        = (int)$_POST['batch_id'];

    $teacher = $conn->query("SELECT teacher_id FROM teachers WHERE user_id = $teacher_user_id")->fetch_assoc();
    if (!$teacher) {
        $error_msg = "Invalid teacher selected.";
    } else {
        $teacher_id = $teacher['teacher_id'];

        $check = $conn->prepare("SELECT 1 FROM course_assignments WHERE teacher_id = ? AND batch_id = ? AND assignment_id != ?");
        $check->bind_param("iii", $teacher_id, $batch_id, $assignment_id);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error_msg = "This teacher is already assigned to this batch.";
        } else {
            $stmt = $conn->prepare("UPDATE course_assignments SET teacher_id = ?, batch_id = ? WHERE assignment_id = ?");
            $stmt->bind_param("iii", $teacher_id, $batch_id, $assignment_id);
            if ($stmt->execute()) {
                $success_msg = "Assignment updated successfully!";
            } else {
                $error_msg = "Database error: " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}

// ────────────────────────────────────────────────
//   Load data for forms & table
// ────────────────────────────────────────────────
$teachers    = $conn->query("SELECT user_id, CONCAT(firstName,' ',lastName) AS name FROM users WHERE role='teacher' ORDER BY firstName");
$batches     = $conn->query("SELECT batch_id, batch_code FROM batches ORDER BY batch_code DESC");
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
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Teacher Assignments • Girls Coding Academy</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .glass { 
            background: rgba(255,255,255,0.97);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(229,231,235,0.7);
        }
        .btn-primary {
            @apply bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-medium py-3 px-6 rounded-xl transition-all shadow-sm hover:shadow-md transform hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-indigo-400;
        }
        tr:hover { background-color: rgba(249,250,251,0.8) !important; }
    </style>
</head>
<body class="min-h-screen bg-gray-50">

    <?php include 'top_navigation.php'; ?>
    <?php include 'admin_navigation.php'; ?>

    <main class="lg:ml-64 pt-16 lg:pt-0 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

            <!-- Header -->
            <div class="mb-10">
                <h1 class="text-3xl font-bold text-gray-900">Teacher – Batch Assignments</h1>
                <p class="mt-2 text-gray-600">Manage which teachers are responsible for each batch of students.</p>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
                <div class="glass rounded-2xl p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Teachers</p>
                            <p class="text-3xl font-bold text-gray-900 mt-1"><?= number_format($total_teachers) ?></p>
                        </div>
                        <div class="w-14 h-14 bg-indigo-100 rounded-xl flex items-center justify-center">
                            <i class="bi bi-person-badge-fill text-2xl text-indigo-600"></i>
                        </div>
                    </div>
                </div>

                <div class="glass rounded-2xl p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Active Batches</p>
                            <p class="text-3xl font-bold text-gray-900 mt-1"><?= number_format($total_batches) ?></p>
                        </div>
                        <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center">
                            <i class="bi bi-collection-fill text-2xl text-blue-600"></i>
                        </div>
                    </div>
                </div>

                <div class="glass rounded-2xl p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Assignments</p>
                            <p class="text-3xl font-bold text-gray-900 mt-1"><?= number_format($total_assignments) ?></p>
                        </div>
                        <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center">
                            <i class="bi bi-link-45deg text-2xl text-green-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Form -->
            <div class="glass rounded-2xl shadow-sm p-8 mb-12">
                <h2 class="text-2xl font-semibold text-gray-900 mb-6">New Assignment</h2>

                <form method="POST" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Teacher</label>
                        <select name="teacher_user_id" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Select teacher…</option>
                            <?php while ($t = $teachers->fetch_assoc()): ?>
                                <option value="<?= $t['user_id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                            <?php endwhile; $teachers->data_seek(0); ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Batch</label>
                        <select name="batch_id" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Select batch…</option>
                            <?php while ($b = $batches->fetch_assoc()): ?>
                                <option value="<?= $b['batch_id'] ?>"><?= htmlspecialchars($b['batch_code']) ?></option>
                            <?php endwhile; $batches->data_seek(0); ?>
                        </select>
                    </div>

                    <div class="flex items-end">
                        <button type="submit" name="add_assignment" class="btn-primary w-full flex items-center justify-center gap-2">
                            <i class="bi bi-plus-lg"></i> Assign
                        </button>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="glass rounded-2xl shadow-sm overflow-hidden">
                <div class="px-8 py-6 border-b border-gray-200">
                    <h2 class="text-2xl font-semibold text-gray-900">Current Assignments</h2>
                </div>

                <?php if ($assignments->num_rows === 0): ?>
                    <div class="p-16 text-center text-gray-500 italic">
                        No assignments created yet.
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-max">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-8 py-4 text-left text-sm font-semibold text-gray-600">Teacher</th>
                                    <th class="px-8 py-4 text-left text-sm font-semibold text-gray-600">Batch</th>
                                    <th class="px-8 py-4 text-left text-sm font-semibold text-gray-600">Assigned</th>
                                    <th class="px-8 py-4 text-center text-sm font-semibold text-gray-600">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($row = $assignments->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50/70 transition">
                                        <td class="px-8 py-5 font-medium text-gray-900">
                                            <?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) ?>
                                        </td>
                                        <td class="px-8 py-5">
                                            <span class="inline-flex px-3 py-1 bg-indigo-50 text-indigo-700 rounded-full text-sm font-medium">
                                                <?= htmlspecialchars($row['batch_code']) ?>
                                            </span>
                                        </td>
                                        <td class="px-8 py-5 text-gray-600 text-sm">
                                            <?= date("d M Y • H:i", strtotime($row['created_at'])) ?>
                                        </td>
                                        <td class="px-8 py-5 text-center whitespace-nowrap">
                                            <button onclick='openEdit(<?= json_encode($row) ?>)'
                                                    class="text-indigo-600 hover:text-indigo-800 mr-5 transition">
                                                <i class="bi bi-pencil-square text-lg"></i>
                                            </button>
                                            <button onclick="confirmDelete(<?= $row['assignment_id'] ?>)"
                                                    class="text-red-600 hover:text-red-800 transition">
                                                <i class="bi bi-trash text-lg"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-8" onclick="event.stopPropagation()">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-semibold text-gray-900">Edit Assignment</h3>
                <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">
                    <i class="bi bi-x-lg text-2xl"></i>
                </button>
            </div>

            <form method="POST">
                <input type="hidden" name="assignment_id" id="edit-id">

                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Teacher</label>
                        <select name="teacher_user_id" id="edit-teacher" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500">
                            <?php $teachers->data_seek(0); while ($t = $teachers->fetch_assoc()): ?>
                                <option value="<?= $t['user_id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Batch</label>
                        <select name="batch_id" id="edit-batch" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500">
                            <?php $batches->data_seek(0); while ($b = $batches->fetch_assoc()): ?>
                                <option value="<?= $b['batch_id'] ?>"><?= htmlspecialchars($b['batch_code']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="flex justify-end gap-4 mt-8">
                        <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')"
                                class="px-6 py-3 border border-gray-300 rounded-xl hover:bg-gray-50 transition font-medium">
                            Cancel
                        </button>
                        <button type="submit" name="edit_assignment" class="btn-primary px-6 py-3">
                            Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEdit(data) {
            document.getElementById('edit-id').value     = data.assignment_id;
            document.getElementById('edit-teacher').value = data.user_id;
            document.getElementById('edit-batch').value   = data.batch_id;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function confirmDelete(id) {
            Swal.fire({
                title: 'Delete Assignment?',
                text: "This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete it',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                customClass: {
                    popup: 'swal2-modern-confirm',
                    confirmButton: 'px-6 py-3 font-medium rounded-xl',
                    cancelButton: 'px-6 py-3 font-medium rounded-xl'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `delete_assignment.php?id=${id}`;
                }
            });
        }

        // Show result messages after redirect
        document.addEventListener("DOMContentLoaded", () => {
            <?php if (isset($_SESSION['success_msg'])): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: <?= json_encode($_SESSION['success_msg']) ?>,
                    timer: 2800,
                    showConfirmButton: false,
                    position: 'center',
                    padding: '2.5rem',
                    customClass: { popup: 'swal2-modern-success' },
                    backdrop: 'rgba(0,0,0,0.65)'
                });
                <?php unset($_SESSION['success_msg']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_msg'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: <?= json_encode($_SESSION['error_msg']) ?>,
                    timer: 4000,
                    showConfirmButton: false,
                    position: 'center',
                    padding: '2.5rem',
                    customClass: { popup: 'swal2-modern-error' },
                    backdrop: 'rgba(0,0,0,0.65)'
                });
                <?php unset($_SESSION['error_msg']); ?>
            <?php endif; ?>
        });
    </script>

    <style>
        .swal2-modern-success,
        .swal2-modern-error,
        .swal2-modern-confirm {
            border-radius: 1.25rem !important;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25) !important;
        }
        .swal2-modern-success { background: linear-gradient(to bottom right, #f0fdf4, #ffffff) !important; }
        .swal2-icon-success { color: #10b981 !important; border-color: #10b981 !important; }
    </style>

</body>
</html>