<?php
session_start();
if ($_SESSION['role'] !== 'admin') { 
    header("Location: login.php"); 
    exit(); 
}
include 'db.php';

// Fetch stats
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
    
    <!-- Tailwind + Icons + Font -->
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
            background-color: #ffffff;
            border-right: 1px solid #e2e8f0;
        }

        .layout-topnav {
            grid-column: 2;
            grid-row: 1;
            position: sticky;
            top: 0;
            z-index: 100;
            background-color: #ffffff;
            border-bottom: 1px solid #e2e8f0;
        }

        .layout-content {
            grid-column: 2;
            grid-row: 2;
            padding: 2rem;
            overflow-y: auto;
        }

        @media (max-width: 992px) {
            .layout-wrapper { grid-template-columns: 1fr; grid-template-rows: auto auto 1fr; }
            .layout-sidebar { position: relative; width: 100%; height: auto; }
        }

        .max-w-container { max-width: 1400px; margin: 0 auto; }

        .card {
            @apply rounded-2xl shadow-lg transition-all duration-300 cursor-pointer;
        }

        .hover-lift:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }

        /* Modern Compact Hover Modal */
        .hover-modal {
            position: fixed;
            top: 50%;
            right: -26rem;
            transform: translateY(-50%);
            width: 24rem;
            max-height: 70vh;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            border: 1px solid rgba(229,231,235,0.8);
            overflow: hidden;
            z-index: 999;
            opacity: 0;
            transition: all 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .hover-modal.visible {
            right: 1.5rem;
            opacity: 1;
        }

        .modal-header {
            height: 7rem;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
            position: relative;
        }

        .modal-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 30% 70%, rgba(255,255,255,0.25), transparent 60%);
            opacity: 0.7;
        }

        .modal-content {
            padding: 1.5rem;
        }
    </style>
</head>
<body class="bg-gray-50">

    <div class="layout-wrapper">
        <!-- Sidebar -->
        <div class="layout-sidebar">
            <?php include 'admin_navigation.php'; ?>
        </div>

        <!-- Top Navigation -->
        <div class="layout-topnav">
            <?php include 'top_navigation.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="layout-content">
            <div class="max-w-container">
                <!-- Header -->
                <div class="text-center mb-12">
                    <h1 class="text-4xl md:text-5xl font-bold bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 bg-clip-text text-transparent mb-4">
                        School Infrastructure
                    </h1>
                    <p class="text-lg md:text-xl text-gray-600 max-w-2xl mx-auto">
                        Hover over cards for quick preview • Click to manage resources
                    </p>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
                    
                    <!-- Rooms -->
                    <div class="glass card p-6 text-center hover-lift group bg-white"
                         data-type="rooms"
                         data-title="Rooms Overview"
                         data-count="<?= $rooms ?>"
                         data-summary="Total classrooms, labs, offices and other spaces across all campuses."
                         data-extra="<?= $in_maintenance > 0 ? "$in_maintenance in maintenance" : '' ?>"
                         data-link="manage_rooms.php">
                        <div class="mb-4 inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 group-hover:scale-105 transition-transform">
                            <i class="bi bi-building text-3xl text-blue-600"></i>
                        </div>
                        <p class="text-4xl font-bold text-gray-900 mb-2"><?= $rooms ?></p>
                        <p class="text-base text-gray-700 font-medium">Total Rooms</p>
                        <?php if ($in_maintenance > 0): ?>
                            <p class="text-sm text-orange-600 mt-2 font-medium">
                                <i class="bi bi-exclamation-triangle mr-1"></i><?= $in_maintenance ?> in maintenance
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Equipment -->
                    <div class="glass card p-6 text-center hover-lift group bg-white"
                         data-type="equipment"
                         data-title="Equipment Overview"
                         data-count="<?= $equipment ?>"
                         data-summary="Computers, projectors, lab tools and other learning equipment currently available."
                         data-extra=""
                         data-link="manage_equipments.php">
                        <div class="mb-4 inline-flex items-center justify-center w-16 h-16 rounded-full bg-indigo-100 group-hover:scale-105 transition-transform">
                            <i class="bi bi-cpu-fill text-3xl text-indigo-600"></i>
                        </div>
                        <p class="text-4xl font-bold text-gray-900 mb-2"><?= $equipment ?></p>
                        <p class="text-base text-gray-700 font-medium">Equipment Items</p>
                    </div>

                    <!-- Furniture -->
                    <div class="glass card p-6 text-center hover-lift group bg-white"
                         data-type="furniture"
                         data-title="Furniture Overview"
                         data-count="<?= $furniture ?>"
                         data-summary="Desks, chairs, tables, cabinets and other furniture across all facilities."
                         data-extra=""
                         data-link="manage_furniture.php">
                        <div class="mb-4 inline-flex items-center justify-center w-16 h-16 rounded-full bg-purple-100 group-hover:scale-105 transition-transform">
                            <i class="bi bi-chair text-3xl text-purple-600"></i>
                        </div>
                        <p class="text-4xl font-bold text-gray-900 mb-2"><?= $furniture ?></p>
                        <p class="text-base text-gray-700 font-medium">Furniture Items</p>
                    </div>

                    <!-- Report (direct link - no modal) -->
                    <a href="infrastructure_report.php" 
                       class="card p-6 text-center hover-lift group bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-xl">
                        <div class="mb-4 inline-flex items-center justify-center w-16 h-16 rounded-full bg-white/20 group-hover:scale-105 transition-transform">
                            <i class="bi bi-file-earmark-bar-graph text-3xl text-white"></i>
                        </div>
                        <p class="text-2xl font-bold mb-2">Full Report</p>
                        <p class="text-base font-medium opacity-90">Inventory • Status • Analytics</p>
                    </a>
                </div>

                <!-- Footer hint -->
                <div class="text-center bg-white rounded-2xl shadow-md border border-gray-100 p-6">
                    <p class="text-gray-600 text-base">
                        <i class="bi bi-info-circle mr-2 text-indigo-600"></i>
                        Hover over cards to see summary • Click “View Details” to manage section
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- ────────────────────────────────────────────────
         MODERN HOVER MODAL (compact, right side, beautiful)
    ──────────────────────────────────────────────── -->
    <div id="hoverModal" class="hover-modal">
        <div class="modal-header">
            <div id="modalIcon" class="absolute inset-0 flex items-center justify-center text-white text-7xl drop-shadow-2xl z-10"></div>
        </div>

        <div class="modal-content">
            <h3 id="modalTitle" class="text-xl font-bold text-gray-800 mb-2"></h3>
            
            <div class="flex items-baseline gap-3 mb-4">
                <span id="modalCount" class="text-4xl font-extrabold text-indigo-600"></span>
                <span id="modalExtra" class="text-sm text-orange-600 font-medium"></span>
            </div>

            <p id="modalSummary" class="text-gray-600 text-sm leading-relaxed mb-6"></p>

            <a id="modalLink" href="#" 
               class="inline-flex items-center justify-center w-full bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-medium py-3 px-6 rounded-xl transition-all duration-200 shadow-sm hover:shadow-md transform hover:-translate-y-0.5">
                View Details <i class="bi bi-arrow-right ml-2"></i>
            </a>
        </div>

        <button id="closeModal" class="absolute top-3 right-3 text-white/80 hover:text-white bg-black/30 hover:bg-black/50 rounded-full w-8 h-8 flex items-center justify-center transition-all backdrop-blur-sm z-20">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <script>
        const cards = document.querySelectorAll('.card[data-type]');
        const modal = document.getElementById('hoverModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalCount = document.getElementById('modalCount');
        const modalSummary = document.getElementById('modalSummary');
        const modalExtra = document.getElementById('modalExtra');
        const modalLink = document.getElementById('modalLink');
        const modalIcon = document.getElementById('modalIcon');
        const closeBtn = document.getElementById('closeModal');

        let hideTimeout;

        const icons = {
            rooms:     'bi-building-fill',
            equipment: 'bi-cpu-fill',
            furniture: 'bi-chair-fill'
        };

        function showModal(card) {
            clearTimeout(hideTimeout);

            const type = card.dataset.type;
            modalTitle.textContent   = card.dataset.title;
            modalCount.textContent   = card.dataset.count;
            modalSummary.textContent = card.dataset.summary;
            modalExtra.textContent   = card.dataset.extra;
            modalLink.href           = card.dataset.link;

            // Set big icon
            modalIcon.innerHTML = `<i class="bi ${icons[type] || 'bi-question-circle-fill'}"></i>`;

            modal.classList.add('visible');
        }

        function hideModal() {
            hideTimeout = setTimeout(() => {
                modal.classList.remove('visible');
            }, 180);
        }

        cards.forEach(card => {
            card.addEventListener('mouseenter', () => showModal(card));
            card.addEventListener('mouseleave', hideModal);
        });

        modal.addEventListener('mouseenter', () => clearTimeout(hideTimeout));
        modal.addEventListener('mouseleave', hideModal);

        closeBtn.addEventListener('click', () => {
            modal.classList.remove('visible');
        });
    </script>

</body>
</html>