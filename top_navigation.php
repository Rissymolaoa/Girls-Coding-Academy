<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once 'db.php';

// Get user info from database for consistency
$username = 'User';
$email = 'user@academy.com';
$profile_photo = 'admin.png';
$user_role = $_SESSION['role'] ?? 'user';

// Fetch current user data from database
if (isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
    $user_query = $conn->query("SELECT firstName, lastName, email FROM users WHERE user_id = $user_id LIMIT 1");
    
    if ($user_query && $user_query->num_rows > 0) {
        $user_data = $user_query->fetch_assoc();
        $username = htmlspecialchars($user_data['firstName'] . ' ' . $user_data['lastName']);
        $email = htmlspecialchars($user_data['email']);
    }
    
    // Get profile photo based on role
    if ($user_role === 'teacher') {
        $photo_query = $conn->query("SELECT photo FROM teachers WHERE user_id = $user_id LIMIT 1");
        if ($photo_query && $photo_query->num_rows > 0) {
            $photo_data = $photo_query->fetch_assoc();
            $profile_photo = $photo_data['photo'] !== 'null' ? htmlspecialchars($photo_data['photo']) : 'imageuploads/default_avatar.png';
        }
    } elseif ($user_role === 'student') {
        $photo_query = $conn->query("SELECT photo FROM students WHERE user_id = $user_id LIMIT 1");
        if ($photo_query && $photo_query->num_rows > 0) {
            $photo_data = $photo_query->fetch_assoc();
            $profile_photo = $photo_data['photo'] !== 'null' ? htmlspecialchars($photo_data['photo']) : 'imageuploads/default_avatar.png';
        }
    } elseif ($user_role === 'parent') {
        $photo_query = $conn->query("SELECT photo FROM parents WHERE user_id = $user_id LIMIT 1");
        if ($photo_query && $photo_query->num_rows > 0) {
            $photo_data = $photo_query->fetch_assoc();
            $profile_photo = $photo_data['photo'] !== 'null' ? htmlspecialchars($photo_data['photo']) : 'imageuploads/default_avatar.png';
        }
    } elseif ($user_role === 'admin') {
        $profile_photo = 'admin.png';
    }
}

// Get notification count
$notification_result = $conn->query("SELECT COUNT(*) as count FROM admin_announcements WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
$notification_count = $notification_result ? $notification_result->fetch_assoc()['count'] : 0;

// Get recent notifications
$recent_notifications = $conn->query("SELECT message, recipients, created_at FROM admin_announcements ORDER BY created_at DESC LIMIT 3");
?>

<!-- TOP NAVIGATION BAR -->
<nav class="fixed top-0 left-0 right-0 z-50 shadow-lg bg-gradient-to-r from-indigo-800 via-indigo-900 to-purple-900">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">

            <!-- Left Spacer (for sidebar alignment) -->
            <div class="w-64 flex-shrink-0"></div>

            <!-- Compact Modern Search -->
            <div class="flex-1 max-w-md mx-8">
                <form method="GET" action="search.php" class="relative">
                    <input
                        type="search"
                        name="q"
                        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                        placeholder="Search students, courses..."
                        class="w-full pl-11 pr-5 py-3 bg-white/10 backdrop-blur-md border border-white/20 rounded-full text-white placeholder-white/60 focus:outline-none focus:ring-2 focus:ring-white/40 focus:bg-white/15 transition-all duration-200 text-sm"
                        aria-label="Search"
                    />
                    <button type="submit" class="absolute left-3 top-1/2 -translate-y-1/2 text-white/80 hover:text-white transition">
                        <i class="bi bi-search text-lg"></i>
                    </button>
                </form>
            </div>

            <!-- Right: Notifications + Profile -->
            <div class="flex items-center space-x-4">

                <!-- Notifications -->
                <div class="relative" x-data="{ open: false }">
                    <button
                        @click="open = !open"
                        @click.away="open = false"
                        class="relative p-3 text-white/90 hover:text-white hover:bg-white/10 rounded-xl transition-all duration-200"
                        aria-label="Notifications"
                    >
                        <i class="bi bi-bell-fill text-xl"></i>
                        <?php if ($notification_count > 0): ?>
                            <span class="absolute -top-1 -right-1 flex h-5 w-5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-5 w-5 bg-red-500 text-white text-xs font-bold justify-center items-center">
                                    <?= min($notification_count, 99) ?>
                                </span>
                            </span>
                        <?php endif; ?>
                    </button>

                    <!-- Notification Dropdown -->
                    <div
                        x-show="open"
                        x-transition
                        @click.away="open = false"
                        class="absolute right-0 mt-3 w-96 bg-white rounded-2xl shadow-2xl overflow-hidden ring-1 ring-black ring-opacity-5"
                    >
                        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
                            <h3 class="text-white font-bold text-lg">Recent Announcements</h3>
                            <p class="text-white/80 text-sm mt-1"><?= $notification_count ?> new this week</p>
                        </div>
                        <div class="max-h-80 overflow-y-auto">
                            <?php if ($recent_notifications && $recent_notifications->num_rows > 0): ?>
                                <?php while ($n = $recent_notifications->fetch_assoc()): ?>
                                    <a href="announcements.php" class="block px-6 py-4 hover:bg-gray-50 transition border-b border-gray-100 last:border-b-0">
                                        <p class="text-gray-800 font-medium text-sm line-clamp-2">
                                            <?= htmlspecialchars($n['message']) ?>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-2">
                                            <span class="font-semibold capitalize"><?= htmlspecialchars($n['recipients']) ?></span>
                                            • <?= date('M j \a\t g:ia', strtotime($n['created_at'])) ?>
                                        </p>
                                    </a>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-12 text-gray-400">
                                    <i class="bi bi-bell-slash text-4xl mb-3 block"></i>
                                    <p>No new announcements</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <a href="announcements.php" class="block text-center bg-gray-50 hover:bg-gray-100 text-indigo-700 font-semibold py-3 transition border-t border-gray-200">
                            View All Announcements
                        </a>
                    </div>
                </div>

                <!-- Profile Dropdown (Removed Profile Option) -->
                <div class="relative" x-data="{ open: false }">
                    <button
                        @click="open = !open"
                        @click.away="open = false"
                        class="flex items-center space-x-3 p-2 rounded-xl hover:bg-white/10 transition-all duration-200"
                        aria-label="User Menu"
                    >
                        <img
                            src="<?= $profile_photo ?>"
                            alt="Profile"
                            class="w-10 h-10 rounded-full object-cover ring-2 ring-white/30"
                            loading="lazy"
                            onerror="this.src='imageuploads/default_avatar.png'"
                        >
                        <span class="text-white font-medium hidden lg:block text-sm"><?= $username ?></span>
                        <i class="bi bi-chevron-down text-white text-sm hidden lg:block"></i>
                    </button>

                    <!-- User Menu (No Profile Link) -->
                    <div
                        x-show="open"
                        x-transition
                        class="absolute right-0 mt-3 w-56 bg-white rounded-2xl shadow-2xl overflow-hidden ring-1 ring-black ring-opacity-5"
                    >
                        <div class="bg-gradient-to-br from-indigo-600 to-purple-600 px-5 py-4 text-white">
                            <p class="font-bold text-sm"><?= $username ?></p>
                            <p class="text-xs opacity-90 truncate mt-1"><?= $email ?></p>
                            <p class="text-xs opacity-75 mt-2 capitalize"><?= $user_role ?></p>
                        </div>
                        <div class="py-2">
                            <a href="settings.php" class="flex items-center px-5 py-3 text-gray-700 hover:bg-indigo-50 transition text-sm">
                                <i class="bi bi-gear-wide-connected w-5 mr-3 text-indigo-600"></i> Settings
                            </a>
                            <?php if ($user_role === 'admin'): ?>
                                <a href="recycle_bin.php" class="flex items-center px-5 py-3 text-gray-700 hover:bg-indigo-50 transition text-sm">
                                    <i class="bi bi-trash3 w-5 mr-3 text-indigo-600"></i> Recycle Bin
                                </a>
                            <?php endif; ?>
                            <hr class="my-2 border-gray-200">
                            <a href="logout.php" class="flex items-center px-5 py-3 text-red-600 hover:bg-red-50 transition font-medium text-sm">
                                <i class="bi bi-box-arrow-right w-5 mr-3"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</nav>

<!-- Required Scripts -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
    body { 
        padding-top: 64px; 
        font-family: 'Inter', system-ui, sans-serif;
        background: #f9fafb;
    }
    .line-clamp-2 {
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }
    /* Smooth scrollbar */
    .max-h-80::-webkit-scrollbar { width: 6px; }
    .max-h-80::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 3px; }
    .max-h-80::-webkit-scrollbar-thumb { background: #a5b4fc; border-radius: 3px; }
    .max-h-80::-webkit-scrollbar-thumb:hover { background: #818cf8; }
</style>