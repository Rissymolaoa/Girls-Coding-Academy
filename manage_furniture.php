<?php
session_start();
if ($_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }
include 'db.php';

$success = $error = '';

// === SAVE FURNITURE (Add or Edit) ===
if ($_POST['action'] ?? '' === 'save') {
    $id         = (int)($_POST['id'] ?? 0);
    $name       = trim($_POST['name']);
    $category   = $_POST['category'];
    $quantity   = (int)$_POST['quantity'];
    $room_id    = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
    $condition  = $_POST['condition'];
    $notes      = trim($_POST['notes'] ?? '');

    if (empty($name) || $quantity < 1) {
        $error = "Item name and quantity are required.";
    } else {
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE school_furniture SET item_name=?, category=?, quantity=?, room_id=?, `condition`=?, notes=? WHERE id=?");
            $stmt->bind_param("ssissssi", $name, $category, $quantity, $room_id, $condition, $notes, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO school_furniture (item_name, category, quantity, room_id, `condition`, notes) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("ssissss", $name, $category, $quantity, $room_id, $condition, $notes);
        }
        if ($stmt->execute()) {
            $success = $id ? "Furniture updated successfully!" : "Furniture added successfully!";
            if ($id > 0) {
                header("Location: manage_furniture.php?success=1");
                exit();
            }
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// === DELETE ===
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM school_furniture WHERE id = $id");
    $success = "Furniture deleted successfully!";
}

// === FILTERS ===
$search = trim($_GET['search'] ?? '');
$category_filter = $_GET['category'] ?? '';
$condition_filter = $_GET['condition'] ?? '';

$where = [];
$params = [];
$types = '';

if ($search) {
    $where[] = "(f.item_name LIKE ? OR f.notes LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
    $types .= "ss";
}
if ($category_filter) {
    $where[] = "f.category = ?";
    $params[] = $category_filter;
    $types .= "s";
}
if ($condition_filter) {
    $where[] = "f.`condition` = ?";
    $params[] = $condition_filter;
    $types .= "s";
}

$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

// === GET FURNITURE WITH ROOM INFO ===
$sql = "
    SELECT f.*, r.room_name, fl.floor_name, fl.building
    FROM school_furniture f
    LEFT JOIN school_rooms r ON f.room_id = r.id
    LEFT JOIN school_floors fl ON r.floor_id = fl.id
    $where_clause
    ORDER BY f.item_name
";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$furniture = $stmt->get_result();

// === ROOMS FOR DROPDOWN ===
$rooms = $conn->query("
    SELECT r.id, r.room_name, COALESCE(fl.floor_name, 'No Floor') as floor_name
    FROM school_rooms r
    LEFT JOIN school_floors fl ON r.floor_id = fl.id
    ORDER BY fl.floor_name, r.room_name
")->fetch_all(MYSQLI_ASSOC);

// === EDIT MODE ===
$edit = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM school_furniture WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Furniture • Girls Coding Academy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
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
                        <h1 class="text-3xl font-bold text-gray-900">Manage Furniture & Fixtures</h1>
                        <p class="text-gray-600 mt-1">Track all furniture items across classrooms and facilities</p>
                    </div>
                    <button onclick="document.getElementById('furnitureForm').scrollIntoView({behavior: 'smooth'})"
                            class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold py-3 px-6 rounded-xl shadow-lg transition transform hover:scale-105">
                        <i class="bi bi-plus-lg mr-2"></i> Add New Item
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
                <div id="furnitureForm" class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8 mb-10">
                    <h2 class="text-2xl font-bold text-gray-800 mb-8 flex items-center">
                        <i class="bi bi-chair mr-3 text-indigo-600"></i>
                        <?= $edit ? 'Edit Furniture' : 'Add New Furniture' ?>
                    </h2>

                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Item Name</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($edit['item_name'] ?? '') ?>" placeholder="e.g., Student Desk" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200 focus:border-indigo-500 transition">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                            <select name="category" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                                <option value="">Select Category</option>
                                <option value="Desk" <?= ($edit['category'] ?? '') == 'Desk' ? 'selected' : '' ?>>Desk</option>
                                <option value="Chair" <?= ($edit['category'] ?? '') == 'Chair' ? 'selected' : '' ?>>Chair</option>
                                <option value="Table" <?= ($edit['category'] ?? '') == 'Table' ? 'selected' : '' ?>>Table</option>
                                <option value="Whiteboard" <?= ($edit['category'] ?? '') == 'Whiteboard' ? 'selected' : '' ?>>Whiteboard</option>
                                <option value="Cabinet" <?= ($edit['category'] ?? '') == 'Cabinet' ? 'selected' : '' ?>>Cabinet</option>
                                <option value="Shelf" <?= ($edit['category'] ?? '') == 'Shelf' ? 'selected' : '' ?>>Shelf</option>
                                <option value="Bookcase" <?= ($edit['category'] ?? '') == 'Bookcase' ? 'selected' : '' ?>>Bookcase</option>
                                <option value="Other" <?= ($edit['category'] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Quantity</label>
                            <input type="number" name="quantity" value="<?= $edit['quantity'] ?? 1 ?>" min="1" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Location (Room)</label>
                            <select name="room_id" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                                <option value="">Not Assigned</option>
                                <?php foreach($rooms as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= ($edit['room_id'] ?? '') == $r['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['room_name']) ?> — <?= $r['floor_name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Condition</label>
                            <select name="condition" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                                <option value="Good" <?= ($edit['condition'] ?? 'Good') == 'Good' ? 'selected' : '' ?>>Good</option>
                                <option value="Worn" <?= ($edit['condition'] ?? '') == 'Worn' ? 'selected' : '' ?>>Worn</option>
                                <option value="Broken" <?= ($edit['condition'] ?? '') == 'Broken' ? 'selected' : '' ?>>Broken</option>
                            </select>
                        </div>

                        <div class="lg:col-span-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Notes (Optional)</label>
                            <textarea name="notes" rows="3" placeholder="Additional details, damage description, etc."
                                      class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200"><?= htmlspecialchars($edit['notes'] ?? '') ?></textarea>
                        </div>

                        <div class="lg:col-span-3 flex gap-4 mt-4">
                            <button type="submit" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold py-4 px-10 rounded-xl shadow-lg transition transform hover:scale-105">
                                <i class="bi bi-save mr-2"></i>
                                <?= $edit ? 'Update Furniture' : 'Save Furniture' ?>
                            </button>
                            <?php if ($edit): ?>
                                <a href="manage_furniture.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-4 px-8 rounded-xl transition">
                                    Cancel
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Furniture Filters & Table -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden mb-8">
                    <div class="px-8 py-6 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-2xl font-bold text-gray-800 mb-4">All Furniture (<?= $furniture->num_rows ?> items)</h2>
                        
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search item or notes..."
                                   class="px-4 py-3 border border-gray-300 rounded-lg focus:ring-4 focus:ring-indigo-200">
                            <select name="category" class="px-4 py-3 border border-gray-300 rounded-lg focus:ring-4 focus:ring-indigo-200">
                                <option value="">All Categories</option>
                                <option value="Desk" <?= $category_filter === 'Desk' ? 'selected' : '' ?>>Desk</option>
                                <option value="Chair" <?= $category_filter === 'Chair' ? 'selected' : '' ?>>Chair</option>
                                <option value="Table" <?= $category_filter === 'Table' ? 'selected' : '' ?>>Table</option>
                                <option value="Whiteboard" <?= $category_filter === 'Whiteboard' ? 'selected' : '' ?>>Whiteboard</option>
                                <option value="Cabinet" <?= $category_filter === 'Cabinet' ? 'selected' : '' ?>>Cabinet</option>
                                <option value="Shelf" <?= $category_filter === 'Shelf' ? 'selected' : '' ?>>Shelf</option>
                                <option value="Bookcase" <?= $category_filter === 'Bookcase' ? 'selected' : '' ?>>Bookcase</option>
                            </select>
                            <select name="condition" class="px-4 py-3 border border-gray-300 rounded-lg focus:ring-4 focus:ring-indigo-200">
                                <option value="">All Conditions</option>
                                <option value="Good" <?= $condition_filter === 'Good' ? 'selected' : '' ?>>Good</option>
                                <option value="Worn" <?= $condition_filter === 'Worn' ? 'selected' : '' ?>>Worn</option>
                                <option value="Broken" <?= $condition_filter === 'Broken' ? 'selected' : '' ?>>Broken</option>
                            </select>
                            <button type="submit" class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold rounded-lg transition">
                                <i class="bi bi-funnel mr-2"></i>Filter
                            </button>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-8 py-5 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Item Name</th>
                                    <th class="px-8 py-5 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Category</th>
                                    <th class="px-8 py-5 text-center text-sm font-semibold text-gray-600 uppercase tracking-wider">Qty</th>
                                    <th class="px-8 py-5 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Location</th>
                                    <th class="px-8 py-5 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Condition</th>
                                    <th class="px-8 py-5 text-right text-sm font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($f = $furniture->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-8 py-6 font-medium text-gray-900"><?= htmlspecialchars($f['item_name']) ?></td>
                                    <td class="px-8 py-6 text-gray-600"><?= htmlspecialchars($f['category']) ?></td>
                                    <td class="px-8 py-6 text-center font-semibold text-gray-900"><?= $f['quantity'] ?></td>
                                    <td class="px-8 py-6 text-gray-600">
                                        <?php if ($f['room_name']): ?>
                                            <div><?= htmlspecialchars($f['room_name']) ?></div>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($f['floor_name'] ?? 'N/A') ?></div>
                                        <?php else: ?>
                                            <span class="text-gray-400 italic">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-8 py-6">
                                        <span class="px-4 py-2 text-xs font-bold rounded-full
                                            <?= $f['condition'] == 'Good' ? 'bg-green-100 text-green-800' :
                                               ($f['condition'] == 'Worn' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                            <?= $f['condition'] ?>
                                        </span>
                                    </td>
                                    <td class="px-8 py-6 text-right space-x-4">
                                        <a href="?edit=<?= $f['id'] ?>#furnitureForm" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</a>
                                        <a href="?delete=<?= $f['id'] ?>" 
                                           onclick="return confirm('Delete this furniture permanently?')"
                                           class="text-red-600 hover:text-red-800 font-medium">Delete</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>

                                <?php if ($furniture->num_rows === 0): ?>
                                <tr>
                                    <td colspan="6" class="px-8 py-16 text-center text-gray-500 text-lg">
                                        No furniture items yet. Click "Add New Item" to get started!
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