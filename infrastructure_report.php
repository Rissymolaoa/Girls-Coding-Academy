<?php
session_start();
if ($_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }
include 'db.php';

// === COMPREHENSIVE STATISTICS ===
$total_rooms = $conn->query("SELECT COUNT(*) as cnt FROM school_rooms")->fetch_assoc()['cnt'];
$active_rooms = $conn->query("SELECT COUNT(*) as cnt FROM school_rooms WHERE status='Active'")->fetch_assoc()['cnt'];
$maintenance_rooms = $conn->query("SELECT COUNT(*) as cnt FROM school_rooms WHERE status='Maintenance'")->fetch_assoc()['cnt'];
$closed_rooms = $conn->query("SELECT COUNT(*) as cnt FROM school_rooms WHERE status='Closed'")->fetch_assoc()['cnt'];

$total_equipment = $conn->query("SELECT SUM(quantity) as cnt FROM school_equipment")->fetch_assoc()['cnt'] ?? 0;
$working_equipment = $conn->query("SELECT SUM(quantity) as cnt FROM school_equipment WHERE `conditions`='Working'")->fetch_assoc()['cnt'] ?? 0;
$repair_equipment = $conn->query("SELECT SUM(quantity) as cnt FROM school_equipment WHERE `conditions`='Needs Repair'")->fetch_assoc()['cnt'] ?? 0;
$broken_equipment = $conn->query("SELECT SUM(quantity) as cnt FROM school_equipment WHERE `conditions`='Broken'")->fetch_assoc()['cnt'] ?? 0;

$total_furniture = $conn->query("SELECT SUM(quantity) as cnt FROM school_furniture")->fetch_assoc()['cnt'] ?? 0;
$good_furniture = $conn->query("SELECT SUM(quantity) as cnt FROM school_furniture WHERE `conditions`='Good'")->fetch_assoc()['cnt'] ?? 0;
$fair_furniture = $conn->query("SELECT SUM(quantity) as cnt FROM school_furniture WHERE `conditions`='Fair'")->fetch_assoc()['cnt'] ?? 0;
$poor_furniture = $conn->query("SELECT SUM(quantity) as cnt FROM school_furniture WHERE `conditions`='Poor'")->fetch_assoc()['cnt'] ?? 0;

// === ROOM TYPES BREAKDOWN ===
$room_types = $conn->query("
    SELECT room_type, COUNT(*) as count 
    FROM school_rooms 
    GROUP BY room_type 
    ORDER BY count DESC
")->fetch_all(MYSQLI_ASSOC);

// === EQUIPMENT CATEGORIES ===
$equipment_categories = $conn->query("
    SELECT category, SUM(quantity) as total 
    FROM school_equipment 
    GROUP BY category 
    ORDER BY total DESC
")->fetch_all(MYSQLI_ASSOC);

// === RECENT EQUIPMENT ADDITIONS ===
$recent_equipment = $conn->query("
    SELECT e.*, r.room_name 
    FROM school_equipment e 
    LEFT JOIN school_rooms r ON e.room_id = r.id 
    ORDER BY e.id DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Infrastructure Report • Girls Coding Academy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background-color: #f8fafc;
            margin: 0;
            padding: 0;
        }

        /* Grid Layout */
        .layout-wrapper {
            display: grid;
            grid-template-columns: 280px 1fr;
            grid-template-rows: auto 1fr;
            min-height: 100vh;
        }

        .layout-sidebar {
            grid-column: 1;
            grid-row: 1 / -1;
            position: fixed;
            width: 280px;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .layout-topnav {
            grid-column: 2;
            grid-row: 1;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .layout-content {
            grid-column: 2;
            grid-row: 2;
            padding: 2rem;
            background-color: #f8fafc;
            overflow-y: auto;
        }

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

        /* Professional card styling */
        .report-card {
            @apply bg-white rounded-lg border border-gray-200 p-6 shadow-sm;
        }

        .stat-box {
            @apply bg-white rounded-lg border border-gray-200 p-6 shadow-sm;
        }

        .stat-number {
            @apply text-3xl font-bold text-gray-900;
        }

        .stat-label {
            @apply text-sm font-medium text-gray-600 mt-2;
        }

        .section-title {
            @apply text-xl font-bold text-gray-900 mb-6;
        }

        .table-header {
            @apply bg-gray-50 border-b border-gray-200;
        }

        .print-button {
            @apply bg-slate-600 hover:bg-slate-700 text-white font-semibold py-3 px-8 rounded-lg transition-colors;
        }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Layout Wrapper -->
    <div class="layout-wrapper">

        <!-- Sidebar -->
        <div class="layout-sidebar">
            <?php include 'admin_navigation.php'; ?>
        </div>

        <!-- Top Nav -->
        <div class="layout-topnav">
            <?php include 'top_navigation.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="layout-content">
            <div class="max-w-container">

                <!-- Header -->
                <div class="flex justify-between items-start mb-8">
                    <div>
                        <h1 class="text-4xl font-bold text-gray-900 mb-2">Infrastructure Report</h1>
                        <p class="text-gray-600">Complete inventory and facility status overview</p>
                        <p class="text-sm text-gray-500 mt-1">Generated: <?= date('F d, Y \a\t H:i A') ?></p>
                    </div>
                    <button onclick="window.print()" class="print-button flex items-center gap-2">
                        <i class="bi bi-printer"></i> Print Report
                    </button>
                </div>

                <!-- === ROOMS SECTION === -->
                <div class="mb-12">
                    <h2 class="section-title flex items-center gap-3">
                        <i class="bi bi-building text-gray-600"></i> Rooms & Facilities
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="stat-box">
                            <div class="stat-number"><?= $total_rooms ?></div>
                            <div class="stat-label">Total Rooms</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number text-green-700"><?= $active_rooms ?></div>
                            <div class="stat-label">Active Rooms</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number text-yellow-700"><?= $maintenance_rooms ?></div>
                            <div class="stat-label">Under Maintenance</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number text-red-700"><?= $closed_rooms ?></div>
                            <div class="stat-label">Closed Rooms</div>
                        </div>
                    </div>

                    <!-- Room Types Breakdown -->
                    <div class="report-card">
                        <h3 class="font-semibold text-gray-900 mb-4 text-lg">Breakdown by Room Type</h3>
                        <div class="space-y-3">
                            <?php foreach ($room_types as $rt): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-gray-700 font-medium"><?= htmlspecialchars($rt['room_type']) ?></span>
                                <span class="bg-gray-200 text-gray-800 px-3 py-1 rounded-full font-semibold"><?= $rt['count'] ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- === EQUIPMENT SECTION === -->
                <div class="mb-12">
                    <h2 class="section-title flex items-center gap-3">
                        <i class="bi bi-cpu-fill text-gray-600"></i> Equipment & Devices
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="stat-box">
                            <div class="stat-number"><?= $total_equipment ?></div>
                            <div class="stat-label">Total Items</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number text-green-700"><?= $working_equipment ?></div>
                            <div class="stat-label">Working</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number text-yellow-700"><?= $repair_equipment ?></div>
                            <div class="stat-label">Needs Repair</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number text-red-700"><?= $broken_equipment ?></div>
                            <div class="stat-label">Broken</div>
                        </div>
                    </div>

                    <!-- Equipment by Category -->
                    <div class="report-card">
                        <h3 class="font-semibold text-gray-900 mb-4 text-lg">Equipment by Category</h3>
                        <div class="space-y-3">
                            <?php foreach ($equipment_categories as $ec): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-gray-700 font-medium"><?= htmlspecialchars($ec['category']) ?></span>
                                <span class="bg-gray-200 text-gray-800 px-3 py-1 rounded-full font-semibold"><?= $ec['total'] ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- === FURNITURE SECTION === -->
                <div class="mb-12">
                    <h2 class="section-title flex items-center gap-3">
                        <i class="bi bi-chair text-gray-600"></i> Furniture & Fixtures
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="stat-box">
                            <div class="stat-number"><?= $total_furniture ?></div>
                            <div class="stat-label">Total Items</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number text-green-700"><?= $good_furniture ?></div>
                            <div class="stat-label">Good Condition</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number text-yellow-700"><?= $fair_furniture ?></div>
                            <div class="stat-label">Fair Condition</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number text-red-700"><?= $poor_furniture ?></div>
                            <div class="stat-label">Poor Condition</div>
                        </div>
                    </div>
                </div>

                <!-- === RECENT ADDITIONS === -->
                <div class="mb-12">
                    <h2 class="section-title flex items-center gap-3">
                        <i class="bi bi-clock-history text-gray-600"></i> Recently Added Equipment
                    </h2>

                    <div class="report-card overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="table-header">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Item Name</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Category</th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700">Qty</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Location</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Condition</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($recent_equipment as $eq): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-gray-900"><?= htmlspecialchars($eq['item_name']) ?></td>
                                    <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars($eq['category']) ?></td>
                                    <td class="px-4 py-3 text-center font-semibold text-gray-900"><?= $eq['quantity'] ?></td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <?= $eq['room_name'] ? htmlspecialchars($eq['room_name']) : '—' ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php
                                        $condition = $eq['conditions'];
                                        if ($condition == 'Working') {
                                            echo '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded">Working</span>';
                                        } elseif ($condition == 'Needs Repair') {
                                            echo '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded">Needs Repair</span>';
                                        } else {
                                            echo '<span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded">Broken</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Summary Section -->
                <div class="report-card bg-gray-50 border-l-4 border-slate-600">
                    <div class="flex items-start gap-4">
                        <div class="text-slate-600 text-2xl">
                            <i class="bi bi-info-circle"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 mb-2">Report Summary</h3>
                            <p class="text-gray-700 text-sm leading-relaxed">
                                This comprehensive infrastructure report provides an overview of all school facilities including rooms, 
                                equipment, and furniture. Monitor the condition of assets and plan maintenance schedules accordingly. 
                                For detailed management of individual sections, navigate to the respective management pages.
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <script>
        // Print styling
        @media print {
            .layout-sidebar, .layout-topnav, .print-button {
                display: none !important;
            }
            .layout-wrapper {
                grid-template-columns: 1fr;
                grid-template-rows: 1fr;
            }
            .layout-content {
                grid-column: 1;
                grid-row: 1;
                padding: 0;
            }
            body {
                background: white;
            }
        }
    </script>

</body>
</html>