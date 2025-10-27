<?php

?>

<!-- Teacher Navigation -->
<nav class="bg-gradient-to-r from-purple-800 to-purple-600 text-white shadow-lg">
    <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
        <!-- Left: Logo -->
        <div class="flex items-center space-x-3">
            <i class="fas fa-chalkboard-teacher text-2xl text-white"></i>
            <span class="text-xl font-semibold">Teacher Portal</span>
        </div>

        <!-- Center: Navigation Links -->
        <div class="hidden md:flex space-x-8 font-medium">
            <a href="teacher_dashboard.php" class="hover:text-yellow-300 transition-colors">
                <i class="fas fa-home mr-1"></i> Dashboard
            </a>
            <a href="teacher_batches.php" class="hover:text-yellow-300 transition-colors">
                <i class="fas fa-layer-group mr-1"></i> Batches
            </a>
            <a href="teacher_activities.php" class="hover:text-yellow-300 transition-colors">
                <i class="fas fa-tasks mr-1"></i> Activities
            </a>
            <a href="teacher_grades.php" class="hover:text-yellow-300 transition-colors">
                <i class="fas fa-clipboard-check mr-1"></i> Internal Grades
            </a>
            <a href="teacher_students.php" class="hover:text-yellow-300 transition-colors">
                <i class="fas fa-users mr-1"></i> Students
            </a>
            <a href="teacher_reports.php" class="hover:text-yellow-300 transition-colors">
                <i class="fas fa-file-alt mr-1"></i> Reports
            </a>
        </div>

        <!-- Right: Profile Dropdown -->
        <div class="relative group">
            <button class="flex items-center space-x-2 focus:outline-none">
                <div class="w-9 h-9 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                    <i class="fas fa-user text-white"></i>
                </div>
                <span class="font-medium"><?= $username ?></span>
                <i class="fas fa-chevron-down text-sm"></i>
            </button>
            <!-- Dropdown -->
            <div class="absolute right-0 mt-2 w-48 bg-white text-gray-800 rounded-lg shadow-lg py-2 opacity-0 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto transition-all duration-300">
                <a href="teacher_profile.php" class="block px-4 py-2 hover:bg-gray-100">
                    <i class="fas fa-id-card mr-2 text-purple-600"></i> Profile
                </a>
                <a href="teacher_settings.php" class="block px-4 py-2 hover:bg-gray-100">
                    <i class="fas fa-cog mr-2 text-purple-600"></i> Settings
                </a>
                <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </div>
        </div>

        <!-- Mobile menu button -->
        <button id="menu-toggle" class="md:hidden text-white focus:outline-none">
            <i class="fas fa-bars text-2xl"></i>
        </button>
    </div>

    <!-- Mobile Dropdown Menu -->
    <div id="mobile-menu" class="hidden md:hidden bg-purple-700 text-white">
        <a href="teacher_dashboard.php" class="block px-6 py-3 border-t border-purple-600 hover:bg-purple-800"><i class="fas fa-home mr-1"></i> Dashboard</a>
        <a href="teacher_batches.php" class="block px-6 py-3 border-t border-purple-600 hover:bg-purple-800"><i class="fas fa-layer-group mr-1"></i> Batches</a>
        <a href="teacher_activities.php" class="block px-6 py-3 border-t border-purple-600 hover:bg-purple-800"><i class="fas fa-tasks mr-1"></i> Activities</a>
        <a href="teacher_grades.php" class="block px-6 py-3 border-t border-purple-600 hover:bg-purple-800"><i class="fas fa-clipboard-check mr-1"></i> Grades</a>
        <a href="teacher_students.php" class="block px-6 py-3 border-t border-purple-600 hover:bg-purple-800"><i class="fas fa-users mr-1"></i> Students</a>
        <a href="teacher_reports.php" class="block px-6 py-3 border-t border-purple-600 hover:bg-purple-800"><i class="fas fa-file-alt mr-1"></i> Reports</a>
        <a href="teacher_profile.php" class="block px-6 py-3 border-t border-purple-600 hover:bg-purple-800"><i class="fas fa-id-card mr-1"></i> Profile</a>
        <a href="teacher_settings.php" class="block px-6 py-3 border-t border-purple-600 hover:bg-purple-800"><i class="fas fa-cog mr-1"></i> Settings</a>
        <a href="logout.php" class="block px-6 py-3 border-t border-purple-600 hover:bg-red-700 text-red-200"><i class="fas fa-sign-out-alt mr-1"></i> Logout</a>
    </div>
</nav>

<script>
    // Toggle mobile menu
    document.getElementById('menu-toggle').addEventListener('click', () => {
        const menu = document.getElementById('mobile-menu');
        menu.classList.toggle('hidden');
    });
</script>
