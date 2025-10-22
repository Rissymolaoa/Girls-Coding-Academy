<?php
// teacher_navigation.php
?>
<nav id="sidebar" class="sidebar bg-gradient-to-b from-gray-900 to-gray-800 text-white w-64 p-6 fixed top-0 left-0 min-h-screen z-50 md:sticky flex flex-col">
    <div class="text-center mb-8">
        <img src="teacher.png" class="rounded-full border-4 border-purple-500 mb-4 mx-auto shadow-lg" width="100" height="100" alt="Teacher Avatar">
        <h5 class="text-xl font-bold text-white">Teacher Dashboard</h5>
        <p class="text-sm text-gray-300">Girls Coding Academy</p>
    </div>

    <div class="flex-1">
        <ul class="space-y-4">
            <!-- Core Actions -->
            <li class="text-xs font-semibold text-purple-300 uppercase tracking-wide mb-2">Core Actions</li>
            <li>
                <a href="teacher_dashboard.php" class="flex items-center p-3 text-white hover:bg-purple-700 hover:shadow-md rounded-lg transition-all duration-300 <?= basename($_SERVER['PHP_SELF']) === 'teacher_dashboard.php' ? 'bg-purple-600 shadow-md' : '' ?>">
                    <i class="fas fa-laptop-code w-6 mr-3 text-purple-300"></i>
                    <span class="font-medium">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="manage_teacher_courses.php" class="flex items-center p-3 text-white hover:bg-purple-700 hover:shadow-md rounded-lg transition-all duration-300 <?= basename($_SERVER['PHP_SELF']) === 'manage_teacher_courses.php' ? 'bg-purple-600 shadow-md' : '' ?>">
                    <i class="fas fa-book-open w-6 mr-3 text-purple-300"></i>
                    <span class="font-medium">Manage Courses</span>
                </a>
            </li>
            <li>
                <a href="upload_materials.php" class="flex items-center p-3 text-white hover:bg-purple-700 hover:shadow-md rounded-lg transition-all duration-300 <?= basename($_SERVER['PHP_SELF']) === 'upload_materials.php' ? 'bg-purple-600 shadow-md' : '' ?>">
                    <i class="fas fa-folder-plus w-6 mr-3 text-purple-300"></i>
                    <span class="font-medium">Upload Materials</span>
                </a>
            </li>
            <li>
                <a href="grades.php" class="flex items-center p-3 text-white hover:bg-purple-700 hover:shadow-md rounded-lg transition-all duration-300 <?= basename($_SERVER['PHP_SELF']) === 'grades.php' ? 'bg-purple-600 shadow-md' : '' ?>">
                    <i class="fas fa-clipboard-list w-6 mr-3 text-purple-300"></i>
                    <span class="font-medium">Grade</span>
                </a>
            </li>
            <li>
                <a href="mark_attendance.php" class="flex items-center p-3 text-white hover:bg-purple-700 hover:shadow-md rounded-lg transition-all duration-300 <?= basename($_SERVER['PHP_SELF']) === 'mark_attendance.php' ? 'bg-purple-600 shadow-md' : '' ?>">
                    <i class="fas fa-check-circle w-6 mr-3 text-purple-300"></i>
                    <span class="font-medium">Mark Attendance</span>
                </a>
            </li>

            <hr class="border-purple-500 opacity-30 my-4">

            <!-- Communication -->
            <li class="text-xs font-semibold text-purple-300 uppercase tracking-wide mb-2">Communication</li>
            <li>
                <a href="messages.php" class="flex items-center p-3 text-white hover:bg-purple-700 hover:shadow-md rounded-lg transition-all duration-300 <?= basename($_SERVER['PHP_SELF']) === 'messages.php' ? 'bg-purple-600 shadow-md' : '' ?>">
                    <i class="fas fa-envelope w-6 mr-3 text-purple-300"></i>
                    <span class="font-medium">Message Students</span>
                </a>
            </li>

            <hr class="border-purple-500 opacity-30 my-4">

            <!-- Account -->
            <li class="text-xs font-semibold text-purple-300 uppercase tracking-wide mb-2">Account</li>
            <li>
                <a href="logout.php" class="flex items-center p-3 text-white hover:bg-purple-700 hover:shadow-md rounded-lg transition-all duration-300 <?= basename($_SERVER['PHP_SELF']) === 'logout.php' ? 'bg-purple-600 shadow-md' : '' ?>">
                    <i class="fas fa-sign-out-alt w-6 mr-3 text-purple-300"></i>
                    <span class="font-medium">Logout</span>
                </a>
            </li>
        </ul>
    </div>
</nav>