<?php
session_start();
if ($_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }
include 'db.php';
$rooms = $conn->query("SELECT COUNT(*) FROM school_rooms")->fetch_row()[0];
$equipment = $conn->query("SELECT SUM(quantity) FROM school_equipment")->fetch_row()[0] ?? 0;
$furniture = $conn->query("SELECT SUM(quantity) FROM school_furniture")->fetch_row()[0] ?? 0;
$in_maintenance = $conn->query("SELECT COUNT(*) FROM school_rooms WHERE status='Maintenance'")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Infrastructure • Girls Coding Academy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
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

        .glass {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.5);
        }

        .card {
            @apply rounded-2xl shadow-lg transition-all duration-500;
        }

        .hover-lift:hover {
            transform: translateY(-12px);
        }
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
                <div class="text-center mb-16">
                    <h1 class="text-4xl md:text-5xl font-bold bg-gradient-to-r from-sky-600 via-indigo-600 to-purple-700 bg-clip-text text-transparent mb-4">
                        School Infrastructure
                    </h1>
                    <p class="text-lg md:text-xl text-gray-600">Manage rooms, equipment, and furniture across all facilities</p>
                </div>

                <!-- Stats Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
                    
                    <!-- Rooms Card -->
                    <a href="manage_rooms.php" class="glass card p-8 text-center hover-lift group cursor-pointer">
                        <div class="mb-6 transform group-hover:scale-110 transition-transform duration-300">
                            <i class="bi bi-building text-6xl text-sky-600"></i>
                        </div>
                        <p class="text-5xl font-black text-gray-900"><?= $rooms ?></p>
                        <p class="text-lg text-gray-700 mt-3 font-semibold">Total Rooms</p>
                        <?php if ($in_maintenance > 0): ?>
                            <p class="text-sm text-orange-600 mt-4 font-bold">
                                <i class="bi bi-exclamation-triangle mr-1"></i><?= $in_maintenance ?> in maintenance
                            </p>
                        <?php endif; ?>
                    </a>

                    <!-- Equipment Card -->
                    <a href="manage_equipments.php" class="glass card p-8 text-center hover-lift group cursor-pointer">
                        <div class="mb-6 transform group-hover:scale-110 transition-transform duration-300">
                            <i class="bi bi-cpu-fill text-6xl text-indigo-600"></i>
                        </div>
                        <p class="text-5xl font-black text-gray-900"><?= $equipment ?></p>
                        <p class="text-lg text-gray-700 mt-3 font-semibold">Equipment Items</p>
                    </a>

                    <!-- Furniture Card -->
                    <a href="manage_furniture.php" class="glass card p-8 text-center hover-lift group cursor-pointer">
                        <div class="mb-6 transform group-hover:scale-110 transition-transform duration-300">
                            <i class="bi bi-chair text-6xl text-purple-600"></i>
                        </div>
                        <p class="text-5xl font-black text-gray-900"><?= $furniture ?></p>
                        <p class="text-lg text-gray-700 mt-3 font-semibold">Furniture Items</p>
                    </a>

                    <!-- Report Card -->
                    <a href="infrastructure_report.php" class="card p-8 text-center hover-lift group cursor-pointer bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg">
                        <div class="mb-6 transform group-hover:scale-110 transition-transform duration-300">
                            <i class="bi bi-file-earmark-bar-graph text-6xl"></i>
                        </div>
                        <p class="text-3xl font-black">View Report</p>
                        <p class="text-base mt-3 font-semibold">Full inventory summary</p>
                    </a>

                </div>

                <!-- Footer Text -->
                <div class="text-center bg-white rounded-2xl shadow-md border border-gray-100 p-8">
                    <p class="text-gray-700 text-lg">
                        <i class="bi bi-info-circle mr-2 text-indigo-600"></i>
                        Click any card to manage that infrastructure section
                    </p>
                </div>

            </div>
        </div>

    </div>

</body>
</html>