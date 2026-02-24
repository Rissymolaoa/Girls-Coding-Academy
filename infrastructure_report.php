<?php
session_start();
if ($_SESSION['role'] !== 'admin') { 
    header("Location: login.php"); 
    exit(); 
}
include 'db.php';

// === COMPREHENSIVE STATISTICS ===
$total_rooms        = $conn->query("SELECT COUNT(*) as cnt FROM school_rooms")->fetch_assoc()['cnt'] ?? 0;
$active_rooms       = $conn->query("SELECT COUNT(*) as cnt FROM school_rooms WHERE status='Active'")->fetch_assoc()['cnt'] ?? 0;
$maintenance_rooms  = $conn->query("SELECT COUNT(*) as cnt FROM school_rooms WHERE status='Maintenance'")->fetch_assoc()['cnt'] ?? 0;
$closed_rooms       = $conn->query("SELECT COUNT(*) as cnt FROM school_rooms WHERE status='Closed'")->fetch_assoc()['cnt'] ?? 0;

$total_equipment    = $conn->query("SELECT SUM(quantity) as cnt FROM school_equipment")->fetch_assoc()['cnt'] ?? 0;
$working_equipment  = $conn->query("SELECT SUM(quantity) as cnt FROM school_equipment WHERE `conditions`='Working'")->fetch_assoc()['cnt'] ?? 0;
$repair_equipment   = $conn->query("SELECT SUM(quantity) as cnt FROM school_equipment WHERE `conditions`='Needs Repair'")->fetch_assoc()['cnt'] ?? 0;
$broken_equipment   = $conn->query("SELECT SUM(quantity) as cnt FROM school_equipment WHERE `conditions`='Broken'")->fetch_assoc()['cnt'] ?? 0;

$total_furniture    = $conn->query("SELECT SUM(quantity) as cnt FROM school_furniture")->fetch_assoc()['cnt'] ?? 0;
$good_furniture     = $conn->query("SELECT SUM(quantity) as cnt FROM school_furniture WHERE `conditions`='Good'")->fetch_assoc()['cnt'] ?? 0;
$fair_furniture     = $conn->query("SELECT SUM(quantity) as cnt FROM school_furniture WHERE `conditions`='Fair'")->fetch_assoc()['cnt'] ?? 0;
$poor_furniture     = $conn->query("SELECT SUM(quantity) as cnt FROM school_furniture WHERE `conditions`='Poor'")->fetch_assoc()['cnt'] ?? 0;

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f8fafc;
            margin: 0;
            color: #1f2937;
        }

        .layout-wrapper {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 100vh;
        }

        @media (max-width: 1024px) {
            .layout-wrapper { grid-template-columns: 1fr; }
            .sidebar { display: none; }
        }

        .glass-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            transition: all 0.25s ease;
        }

        .glass-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e40af;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .status-badge {
            padding: 0.35rem 1rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        /* Print Styles */
        @media print {
            .sidebar, .topnav, .no-print {
                display: none !important;
            }

            body {
                background: white;
                color: black;
            }

            .layout-wrapper {
                display: block;
            }

            .print-header {
                text-align: center;
                margin-bottom: 2rem;
                border-bottom: 2px solid #1e40af;
                padding-bottom: 1rem;
            }

            .print-header h1 {
                color: #1e40af;
                font-size: 2rem;
                margin: 0;
            }

            .print-header p {
                color: #4b5563;
                margin: 0.25rem 0 0;
            }

            .glass-card {
                box-shadow: none;
                border: 1px solid #d1d5db;
            }

            .stat-number {
                font-size: 2.25rem;
            }
        }
    </style>
</head>
<body>

    <div class="layout-wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <?php include 'admin_navigation.php'; ?>
        </div>

        <!-- Top Navigation -->
        <div class="topnav">
            <?php include 'top_navigation.php'; ?>
        </div>

        <!-- Main Content -->
        <main class="p-6 lg:p-10 bg-gray-50 min-h-screen">
            <div class="max-w-7xl mx-auto">

                <!-- Report Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-6">
                    <div>
                        <h1 class="text-4xl font-bold text-indigo-900">Infrastructure Report</h1>
                        <p class="text-lg text-gray-600 mt-2">Girls Coding Academy • Comprehensive Facility Overview</p>
                        <p class="text-sm text-gray-500 mt-1">Generated on <?= date('F d, Y \a\t H:i A') ?></p>
                    </div>
                    <button onclick="window.print()" class="no-print bg-indigo-700 hover:bg-indigo-800 text-white px-6 py-3 rounded-xl font-medium flex items-center gap-2 shadow-md transition">
                        <i class="bi bi-printer-fill"></i> Print Report
                    </button>
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
                    <div class="glass-card p-6 text-center">
                        <div class="text-indigo-600 text-5xl mb-3"><i class="bi bi-building-fill"></i></div>
                        <div class="stat-number text-indigo-900"><?= number_format($total_rooms) ?></div>
                        <div class="text-sm font-medium text-gray-600 mt-1">Total Rooms</div>
                    </div>

                    <div class="glass-card p-6 text-center">
                        <div class="text-green-600 text-5xl mb-3"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="stat-number text-green-700"><?= number_format($active_rooms) ?></div>
                        <div class="text-sm font-medium text-gray-600 mt-1">Active Rooms</div>
                    </div>

                    <div class="glass-card p-6 text-center">
                        <div class="text-yellow-600 text-5xl mb-3"><i class="bi bi-tools"></i></div>
                        <div class="stat-number text-yellow-700"><?= number_format($maintenance_rooms) ?></div>
                        <div class="text-sm font-medium text-gray-600 mt-1">In Maintenance</div>
                    </div>

                    <div class="glass-card p-6 text-center">
                        <div class="text-red-600 text-5xl mb-3"><i class="bi bi-x-circle-fill"></i></div>
                        <div class="stat-number text-red-700"><?= number_format($closed_rooms) ?></div>
                        <div class="text-sm font-medium text-gray-600 mt-1">Closed Rooms</div>
                    </div>
                </div>

                <!-- Equipment Summary -->
                <div class="mb-12">
                    <h2 class="section-title"><i class="bi bi-cpu-fill"></i> Equipment Overview</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="glass-card p-6 text-center">
                            <div class="text-indigo-600 text-4xl mb-3"><i class="bi bi-box-seam-fill"></i></div>
                            <div class="stat-number"><?= number_format($total_equipment) ?></div>
                            <div class="text-sm text-gray-600 mt-1">Total Equipment</div>
                        </div>
                        <div class="glass-card p-6 text-center">
                            <div class="text-green-600 text-4xl mb-3"><i class="bi bi-check2-circle"></i></div>
                            <div class="stat-number text-green-700"><?= number_format($working_equipment) ?></div>
                            <div class="text-sm text-gray-600 mt-1">Working</div>
                        </div>
                        <div class="glass-card p-6 text-center">
                            <div class="text-yellow-600 text-4xl mb-3"><i class="bi bi-wrench-adjustable-circle"></i></div>
                            <div class="stat-number text-yellow-700"><?= number_format($repair_equipment) ?></div>
                            <div class="text-sm text-gray-600 mt-1">Needs Repair</div>
                        </div>
                        <div class="glass-card p-6 text-center">
                            <div class="text-red-600 text-4xl mb-3"><i class="bi bi-exclamation-triangle-fill"></i></div>
                            <div class="stat-number text-red-700"><?= number_format($broken_equipment) ?></div>
                            <div class="text-sm text-gray-600 mt-1">Broken</div>
                        </div>
                    </div>
                </div>

                <!-- Furniture Summary -->
                <div class="mb-12">
                    <h2 class="section-title"><i class="bi bi-chair"></i> Furniture Overview</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="glass-card p-6 text-center">
                            <div class="text-indigo-600 text-4xl mb-3"><i class="bi bi-grid-3x3-gap-fill"></i></div>
                            <div class="stat-number"><?= number_format($total_furniture) ?></div>
                            <div class="text-sm text-gray-600 mt-1">Total Furniture</div>
                        </div>
                        <div class="glass-card p-6 text-center">
                            <div class="text-green-600 text-4xl mb-3"><i class="bi bi-check-lg"></i></div>
                            <div class="stat-number text-green-700"><?= number_format($good_furniture) ?></div>
                            <div class="text-sm text-gray-600 mt-1">Good Condition</div>
                        </div>
                        <div class="glass-card p-6 text-center">
                            <div class="text-yellow-600 text-4xl mb-3"><i class="bi bi-dash-circle"></i></div>
                            <div class="stat-number text-yellow-700"><?= number_format($fair_furniture) ?></div>
                            <div class="text-sm text-gray-600 mt-1">Fair Condition</div>
                        </div>
                        <div class="glass-card p-6 text-center">
                            <div class="text-red-600 text-4xl mb-3"><i class="bi bi-x-octagon-fill"></i></div>
                            <div class="stat-number text-red-700"><?= number_format($poor_furniture) ?></div>
                            <div class="text-sm text-gray-600 mt-1">Poor Condition</div>
                        </div>
                    </div>
                </div>

                <!-- Print Header (only visible when printing) -->
                <div class="print-header hidden print:block">
                    <h1>Girls Coding Academy</h1>
                    <p>Infrastructure & Facilities Report</p>
                    <p class="text-sm">Maseru, Lesotho • Generated: <?= date('F d, Y \a\t H:i A') ?></p>
                </div>

            </div>
        </main>
    </div>

    <script>
        // Optional: Show welcome/info modal once on first load
        // You can remove or comment this if not needed
        /*
        if (!localStorage.getItem('infrastructureReportSeen')) {
            Swal.fire({
                title: 'Infrastructure Report',
                html: 'This report shows real-time status of rooms, equipment, and furniture.<br><br><strong>Girls Coding Academy</strong>',
                icon: 'info',
                confirmButtonText: 'Got it!',
                confirmButtonColor: '#4f46e5',
                customClass: { popup: 'swal-modern' }
            });
            localStorage.setItem('infrastructureReportSeen', 'true');
        }
        */
    </script>

    <style>
        @media print {
            @page {
                margin: 1.5cm;
            }
            .print-header {
                margin-bottom: 2rem;
            }
            h1, h2, h3 {
                color: #1e40af !important;
            }
            .glass-card {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }

        .swal-modern {
            border-radius: 1rem !important;
            padding: 2rem !important;
        }
    </style>

</body>
</html>