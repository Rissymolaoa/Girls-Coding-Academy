<?php
session_start();
if ($_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
include 'db.php';

$success = $error = '';

// === ADD / EDIT ROOM ===
if ($_POST['action'] ?? '' === 'save') {
    $id        = (int)($_POST['id'] ?? 0);
    $name      = trim($_POST['name'] ?? '');
    $type      = $_POST['type'] ?? 'Classroom';
    $capacity  = (int)($_POST['capacity'] ?? 0);
    $floor_id  = !empty($_POST['floor_id']) ? (int)$_POST['floor_id'] : null;
    $status    = $_POST['status'] ?? 'Active';

    if (empty($name) || $capacity < 1) {
        $error = "Room name and capacity are required.";
    } else {
if ($id > 0) {
    $stmt = $conn->prepare("UPDATE school_rooms SET room_name=?, room_type=?, capacity=?, floor_id=?, `status`=? WHERE id=?");
    $stmt->bind_param("ssisii", $name, $type, $capacity, $floor_id, $status, $id);
} else {
    $stmt = $conn->prepare("INSERT INTO school_rooms (room_name, room_type, capacity, floor_id, `status`) VALUES (?,?,?,?,?)");
    $stmt->bind_param("ssisi", $name, $type, $capacity, $floor_id, $status);
}

        if ($stmt->execute()) {
            $success = $id ? "Room updated successfully!" : "Room added successfully!";
            if ($id > 0) {
                header("Location: manage_rooms.php?success=1");
                exit();
            }
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// === DELETE ROOM ===
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM school_rooms WHERE id = $id");
    $success = "Room deleted successfully!";
}

// Fetch all rooms with floor info
$rooms = $conn->query("
    SELECT r.*, f.floor_name, f.building
    FROM school_rooms r
    LEFT JOIN school_floors f ON r.floor_id = f.id
    ORDER BY COALESCE(f.building, 'ZZZ'), COALESCE(f.floor_name, 'ZZZ'), r.room_name
");

// Fetch floors for dropdown
$floors = $conn->query("SELECT * FROM school_floors ORDER BY building, floor_name")->fetch_all(MYSQLI_ASSOC);

// Edit mode
$edit = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM school_rooms WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Rooms • Girls Coding Academy</title>

    <!-- Tailwind + Icons -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background-color: #f8fafc;
            margin: 0;
            padding: 0;
        }

        /* FIXED: Use CSS Grid for proper layout structure */
        .layout-wrapper {
            display: grid;
            grid-template-columns: 280px 1fr;
            grid-template-rows: auto 1fr;
            min-height: 100vh;
        }

        /* Sidebar spans full height, left column */
        .layout-sidebar {
            grid-column: 1;
            grid-row: 1 / -1;
            position: fixed;
            width: 280px;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        /* Top nav spans width of content area, top row */
        .layout-topnav {
            grid-column: 2;
            grid-row: 1;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        /* Main content in content column, below top nav */
        .layout-content {
            grid-column: 2;
            grid-row: 2;
            padding: 2rem;
            background-color: #f8fafc;
            overflow-y: auto;
        }

        /* Mobile: single column layout */
        @media (max-width: 992px) {
            .layout-wrapper {
                grid-template-columns: 1fr;
                grid-template-rows: auto auto 1fr;
            }

            .layout-sidebar {
                grid-column: 1;
                grid-row: 2;
                position: relative;
                width: 100%;
                height: auto;
            }

            .layout-topnav {
                grid-column: 1;
                grid-row: 1;
            }

            .layout-content {
                grid-column: 1;
                grid-row: 3;
            }
        }

        .max-w-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .alert {
            @apply p-4 rounded-lg font-medium text-sm mb-6;
        }
        .alert-success { @apply bg-green-50 text-green-800 border border-green-200; }
        .alert-error   { @apply bg-red-50 text-red-800 border border-red-200; }
    </style>
</head>
<body class="bg-gray-50">

    <!-- LAYOUT WRAPPER: Grid-based structure -->
    <div class="layout-wrapper">

        <!-- SIDEBAR (fixed left column) -->
        <div class="layout-sidebar">
            <?php include 'admin_navigation.php'; ?>
        </div>

        <!-- TOP NAV (sticky top, right column) -->
        <div class="layout-topnav">
            <?php include 'top_navigation.php'; ?>
        </div>

        <!-- MAIN CONTENT (scrollable, right column) -->
        <div class="layout-content">
            <div class="max-w-container">

                <!-- Header -->
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Manage Rooms & Classrooms</h1>
                        <p class="text-gray-600 mt-1">Organize all school rooms, labs and offices</p>
                    </div>
                    <button onclick="document.getElementById('roomForm').scrollIntoView({behavior: 'smooth'})"
                            class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold py-3 px-6 rounded-xl shadow-lg transition transform hover:scale-105">
                        <i class="bi bi-plus-lg mr-2"></i> Add New Room
                    </button>
                </div>

                <!-- Success / Error Alerts -->
                <?php if ($success || isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill mr-2"></i>
                        <?= $success ?: "Action completed successfully!" ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="bi bi-exclamation-triangle-fill mr-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- Add / Edit Form -->
                <div id="roomForm" class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8 mb-10">
                    <h2 class="text-2xl font-bold text-gray-800 mb-8 flex items-center">
                        <i class="bi bi-building mr-3 text-indigo-600"></i>
                        <?= $edit ? 'Edit Room' : 'Add New Room' ?>
                    </h2>

                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Room Name</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($edit['room_name'] ?? '') ?>" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200 focus:border-indigo-500 transition">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Room Type</label>
                            <select name="type" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                                <option value="Classroom" <?= ($edit['room_type'] ?? '') == 'Classroom' ? 'selected' : '' ?>>Classroom</option>
                                <option value="Computer Lab" <?= ($edit['room_type'] ?? '') == 'Computer Lab' ? 'selected' : '' ?>>Computer Lab</option>
                                <option value="Science Lab" <?= ($edit['room_type'] ?? '') == 'Science Lab' ? 'selected' : '' ?>>Science Lab</option>
                                <option value="Library" <?= ($edit['room_type'] ?? '') == 'Library' ? 'selected' : '' ?>>Library</option>
                                <option value="Office" <?= ($edit['room_type'] ?? '') == 'Office' ? 'selected' : '' ?>>Office</option>
                                <option value="Staff Room" <?= ($edit['room_type'] ?? '') == 'Staff Room' ? 'selected' : '' ?>>Staff Room</option>
                                <option value="Other" <?= ($edit['room_type'] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Floor / Building</label>
                            <select name="floor_id" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                                <option value="">Not Assigned</option>
                                <?php foreach($floors as $f): ?>
                                    <option value="<?= $f['id'] ?>" <?= ($edit['floor_id'] ?? '') == $f['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($f['floor_name']) ?> — <?= htmlspecialchars($f['building'] ?? 'Main Building') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Capacity (students)</label>
                            <input type="number" name="capacity" value="<?= $edit['capacity'] ?? '' ?>" min="1" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                            <select name="status" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                                <option value="Active" <?= ($edit['status'] ?? 'Active') == 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Maintenance" <?= ($edit['status'] ?? '') == 'Maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                                <option value="Closed" <?= ($edit['status'] ?? '') == 'Closed' ? 'selected' : '' ?>>Closed</option>
                            </select>
                        </div>

                        <div class="lg:col-span-3 flex gap-4 mt-4">
                            <button type="submit" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold py-4 px-10 rounded-xl shadow-lg transition transform hover:scale-105">
                                <i class="bi bi-save mr-2"></i>
                                <?= $edit ? 'Update Room' : 'Save Room' ?>
                            </button>
                            <?php if ($edit): ?>
                                <a href="manage_rooms.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-4 px-8 rounded-xl transition">
                                    Cancel
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Rooms Table -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
                    <div class="px-8 py-6 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-2xl font-bold text-gray-800">All Rooms (<?= $rooms->num_rows ?>)</h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-8 py-5 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Room Name</th>
                                    <th class="px-8 py-5 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Type</th>
                                    <th class="px-8 py-5 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Location</th>
                                    <th class="px-8 py-5 text-center text-sm font-semibold text-gray-600 uppercase tracking-wider">Capacity</th>
                                    <th class="px-8 py-5 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                    <th class="px-8 py-5 text-right text-sm font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($r = $rooms->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-8 py-6 font-medium text-gray-900"><?= htmlspecialchars($r['room_name']) ?></td>
                                    <td class="px-8 py-6 text-gray-600"><?= htmlspecialchars($r['room_type']) ?></td>
                                    <td class="px-8 py-6 text-gray-600">
                                        <?php if ($r['floor_name']): ?>
                                            <div><?= htmlspecialchars($r['floor_name']) ?></div>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($r['building'] ?? 'Main Building') ?></div>
                                        <?php else: ?>
                                            <span class="text-gray-400 italic">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-8 py-6 text-center font-semibold text-gray-900"><?= $r['capacity'] ?></td>
                                    <td class="px-8 py-6">
                                        <span class="px-4 py-2 text-xs font-bold rounded-full
                                            <?= $r['status'] == 'Active' ? 'bg-green-100 text-green-800' :
                                               ($r['status'] == 'Maintenance' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                            <?= $r['status'] ?>
                                        </span>
                                    </td>
                                    <td class="px-8 py-6 text-right space-x-4">
                                        <a href="?edit=<?= $r['id'] ?>#roomForm" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</a>
                                        <a href="?delete=<?= $r['id'] ?>" 
                                           onclick="return confirm('Delete this room permanently?')"
                                           class="text-red-600 hover:text-red-800 font-medium">Delete</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>

                                <?php if ($rooms->num_rows === 0): ?>
                                <tr>
                                    <td colspan="6" class="px-8 py-16 text-center text-gray-500 text-lg">
                                        No rooms yet. Click "Add New Room" to get started!
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

    </div>

</body>
</html>