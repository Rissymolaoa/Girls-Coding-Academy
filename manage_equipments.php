<?php
session_start();
if ($_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }
include 'db.php';

$success = $error = '';

// === SAVE EQUIPMENT (Add or Edit) ===
if ($_POST['action'] ?? '' === 'save') {
    $id         = (int)($_POST['id'] ?? 0);
    $name       = trim($_POST['name']);
    $category   = $_POST['category'];
    $quantity   = (int)$_POST['quantity'];
    $room_id    = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
    $purchase   = $_POST['purchase_date'] ?: null;
    $condition  = $_POST['conditions'];
    $notes      = trim($_POST['notes'] ?? '');

    if (empty($name) || $quantity < 1) {
        $error = "Item name and quantity are required.";
    } else {
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE school_equipment SET item_name=?, category=?, quantity=?, room_id=?, purchase_date=?, `conditions`=?, notes=? WHERE id=?");
            $stmt->bind_param("ssissssi", $name, $category, $quantity, $room_id, $purchase, $condition, $notes, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO school_equipment (item_name, category, quantity, room_id, purchase_date, `conditions`, notes) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("ssissss", $name, $category, $quantity, $room_id, $purchase, $condition, $notes);
        }
        if ($stmt->execute()) {
            $success = $id ? "Equipment updated successfully!" : "Equipment added successfully!";
            if ($id > 0) {
                header("Location: manage_equipment.php?success=1");
                exit();
            }
        } else {
            $error = "Database error: " . $stmt->error;
        }
    }
}

// === DELETE ===
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM school_equipment WHERE id = $id");
    $success = "Equipment deleted successfully!";
}

// === FILTERS ===
$search = trim($_GET['search'] ?? '');
$category_filter = $_GET['category'] ?? '';
$condition_filter = $_GET['conditions'] ?? '';

$where = [];
$params = [];
$types = '';

if ($search) {
    $where[] = "(e.item_name LIKE ? OR e.notes LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
    $types .= "ss";
}
if ($category_filter) {
    $where[] = "e.category = ?";
    $params[] = $category_filter;
    $types .= "s";
}
if ($condition_filter) {
    $where[] = "e.`conditions` = ?";
    $params[] = $condition_filter;
    $types .= "s";
}

$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

// === GET EQUIPMENT WITH ROOM + FLOOR INFO ===
$sql = "
    SELECT e.*, r.room_name, f.floor_name, f.building
    FROM school_equipment e
    LEFT JOIN school_rooms r ON e.room_id = r.id
    LEFT JOIN school_floors f ON r.floor_id = f.id
    $where_clause
    ORDER BY e.item_name
";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$equipment = $stmt->get_result();

// === ROOMS + FLOORS FOR DROPDOWN ===
$rooms = $conn->query("
    SELECT r.id, r.room_name, COALESCE(f.floor_name, 'No Floor') as floor_name
    FROM school_rooms r
    LEFT JOIN school_floors f ON r.floor_id = f.id
    ORDER BY f.floor_name, r.room_name
")->fetch_all(MYSQLI_ASSOC);

// === EDIT MODE ===
$edit = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM school_equipment WHERE id = ?");
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
    <title>Manage Equipment • Girls Coding Academy</title>
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
                        <h1 class="text-3xl font-bold text-gray-900">Manage Equipment & Assets</h1>
                        <p class="text-gray-600 mt-1">Track devices, tools, and equipment across all facilities</p>
                    </div>
                    <button onclick="document.getElementById('equipmentForm').scrollIntoView({behavior: 'smooth'})"
                            class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold py-3 px-6 rounded-xl shadow-lg transition transform hover:scale-105">
                        <i class="bi bi-plus-lg mr-2"></i> Add New Equipment
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
                <div id="equipmentForm" class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8 mb-10">
                    <h2 class="text-2xl font-bold text-gray-800 mb-8 flex items-center">
                        <i class="bi bi-box-seam mr-3 text-indigo-600"></i>
                        <?= $edit ? 'Edit Equipment' : 'Add New Equipment' ?>
                    </h2>

                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Item Name</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($edit['item_name'] ?? '') ?>" placeholder="e.g., HP ProBook 450 G8" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200 focus:border-indigo-500 transition">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                            <select name="category" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                                <option value="">Select Category</option>
                                <option value="Computer" <?= ($edit['category'] ?? '') == 'Computer' ? 'selected' : '' ?>>Computer</option>
                                <option value="Projector" <?= ($edit['category'] ?? '') == 'Projector' ? 'selected' : '' ?>>Projector</option>
                                <option value="Printer" <?= ($edit['category'] ?? '') == 'Printer' ? 'selected' : '' ?>>Printer</option>
                                <option value="AC" <?= ($edit['category'] ?? '') == 'AC' ? 'selected' : '' ?>>AC</option>
                                <option value="Fan" <?= ($edit['category'] ?? '') == 'Fan' ? 'selected' : '' ?>>Fan</option>
                                <option value="Lab Tool" <?= ($edit['category'] ?? '') == 'Lab Tool' ? 'selected' : '' ?>>Lab Tool</option>
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
                            <select name="room_id" class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                                <option value="">Not Assigned</option>
                                <?php foreach($rooms as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= ($edit['room_id'] ?? '') == $r['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['room_name']) ?> — <?= $r['floor_name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Purchase Date</label>
                            <input type="date" name="purchase_date" value="<?= $edit['purchase_date'] ?? '' ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Condition</label>
                            <select name="conditions" required class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                                <option value="Working" <?= ($edit['conditions'] ?? 'Working') == 'Working' ? 'selected' : '' ?>>Working</option>
                                <option value="Needs Repair" <?= ($edit['conditions'] ?? '') == 'Needs Repair' ? 'selected' : '' ?>>Needs Repair</option>
                                <option value="Broken" <?= ($edit['conditions'] ?? '') == 'Broken' ? 'selected' : '' ?>>Broken</option>
                            </select>
                        </div>

                        <div class="lg:col-span-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Notes (Optional)</label>
                            <textarea name="notes" rows="3" placeholder="Serial number, warranty info, etc."
                                      class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200"><?= htmlspecialchars($edit['notes'] ?? '') ?></textarea>
                        </div>

                        <div class="lg:col-span-3 flex gap-4 mt-4">
                            <button type="submit" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold py-4 px-10 rounded-xl shadow-lg transition transform hover:scale-105">
                                <i class="bi bi-save mr-2"></i>
                                <?= $edit ? 'Update Equipment' : 'Save Equipment' ?>
                            </button>
                            <?php if ($edit): ?>
                                <a href="manage_equipment.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-4 px-8 rounded-xl transition">
                                    Cancel
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Equipment Filters & Table -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden mb-8">
                    <div class="px-8 py-6 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-2xl font-bold text-gray-800 mb-4">All Equipment (<?= $equipment->num_rows ?> items)</h2>
                        
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search item or notes..."
                                   class="px-4 py-3 border border-gray-300 rounded-lg focus:ring-4 focus:ring-indigo-200">
                            <select name="category" class="px-4 py-3 border border-gray-300 rounded-lg">
                                <option value="">All Categories</option>
                                <option value="Computer" <?= $category_filter === 'Computer' ? 'selected' : '' ?>>Computer</option>
                                <option value="Projector" <?= $category_filter === 'Projector' ? 'selected' : '' ?>>Projector</option>
                                <option value="Printer" <?= $category_filter === 'Printer' ? 'selected' : '' ?>>Printer</option>
                                <option value="AC" <?= $category_filter === 'AC' ? 'selected' : '' ?>>AC</option>
                                <option value="Fan" <?= $category_filter === 'Fan' ? 'selected' : '' ?>>Fan</option>
                                <option value="Lab Tool" <?= $category_filter === 'Lab Tool' ? 'selected' : '' ?>>Lab Tool</option>
                            </select>
                            <select name="conditions" class="px-4 py-3 border border-gray-300 rounded-lg">
                                <option value="">All Conditions</option>
                                <option value="Working" <?= $condition_filter === 'Working' ? 'selected' : '' ?>>Working</option>
                                <option value="Needs Repair" <?= $condition_filter === 'Needs Repair' ? 'selected' : '' ?>>Needs Repair</option>
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
                                    <th class="px-8 py-5 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Purchased</th>
                                    <th class="px-8 py-5 text-right text-sm font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($e = $equipment->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-8 py-6 font-medium text-gray-900"><?= htmlspecialchars($e['item_name']) ?></td>
                                    <td class="px-8 py-6 text-gray-600"><?= $e['category'] ?></td>
                                    <td class="px-8 py-6 text-center font-semibold text-gray-900"><?= $e['quantity'] ?></td>
                                    <td class="px-8 py-6 text-gray-600">
                                        <?= $e['room_name'] 
                                            ? htmlspecialchars($e['room_name']) . '<br><span class="text-xs text-gray-500">' . htmlspecialchars($e['floor_name'] ?? 'N/A') . '</span>'
                                            : '<span class="text-gray-400 italic">Not assigned</span>' 
                                        ?>
                                    </td>
                                    <td class="px-8 py-6">
                                        <span class="px-4 py-2 text-xs font-bold rounded-full
                                            <?= $e['conditions'] == 'Working' ? 'bg-green-100 text-green-800' :
                                               ($e['conditions'] == 'Needs Repair' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                            <?= $e['conditions'] ?>
                                        </span>
                                    </td>
                                    <td class="px-8 py-6 text-gray-600">
                                        <?= $e['purchase_date'] ? date('M Y', strtotime($e['purchase_date'])) : '—' ?>
                                    </td>
                                    <td class="px-8 py-6 text-right space-x-4">
                                        <a href="?edit=<?= $e['id'] ?>#equipmentForm" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</a>
                                        <a href="?delete=<?= $e['id'] ?>" 
                                           onclick="return confirm('Delete this equipment permanently?')"
                                           class="text-red-600 hover:text-red-800 font-medium">Delete</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>

                                <?php if ($equipment->num_rows === 0): ?>
                                <tr>
                                    <td colspan="7" class="px-8 py-16 text-center text-gray-500 text-lg">
                                        No equipment yet. Click "Add New Equipment" to get started!
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