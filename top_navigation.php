<?php
// Check if user is logged in
// DB connection
$host = "localhost";
$user = "root";
$pass = "";
$db = "girlscodingacademydb";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
// Fetch notification count: using recent admin announcements as proxy for admin notifications (e.g., last 7 days)
$notification_result = $conn->query("SELECT COUNT(*) as count FROM admin_announcements WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
$notification_count = $notification_result ? $notification_result->fetch_assoc()['count'] : 0;
// Fetch recent notifications for dropdown
$recent_notifications = $conn->query("SELECT message, recipients, created_at FROM admin_announcements ORDER BY created_at DESC LIMIT 3");
$username = htmlspecialchars($_SESSION['username'] ?? 'User');
$profile_photo = htmlspecialchars($_SESSION['photo'] ?? 'admin.png');
?>
<nav class="fixed top-0 left-0 right-0 z-50 shadow-lg" style="background: linear-gradient(90deg, #1e3a8a, #3b82f6);">
    <div class="max-w-full px-6">
        <div class="flex items-center justify-between h-16">
            <!-- Left side - Empty space for sidebar -->
            <div class="flex items-center" style="width: 250px;">
                <!-- Empty space to align with sidebar width -->
            </div>
            
            <!-- Center - Search Bar -->
            <div class="flex-1 max-w-2xl mx-auto">
                <form method="GET" action="search.php" class="relative">
                    <div class="relative">
                        <input 
                            type="search" 
                            name="q" 
                            value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                            placeholder="Search students, courses, activities..." 
                            class="w-full px-4 py-2 pl-12 pr-4 text-gray-900 bg-white border-2 border-transparent rounded-full focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-300 transition-all"
                            style="box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);"
                        >
                        <button 
                            type="submit" 
                            class="absolute left-0 top-0 h-full px-4 text-gray-500 hover:text-purple-600 transition-colors"
                        >
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Right side - Notifications and Profile -->
            <div class="flex items-center space-x-6 ml-6">
                <!-- Notifications -->
                <div class="relative" x-data="{ open: false }">
                    <button 
                        @click="open = !open"
                        @click.away="open = false"
                        class="relative p-2 text-white hover:bg-blue-700 rounded-full transition-all duration-200"
                    >
                        <i class="fas fa-bell text-xl"></i>
                        <?php if ($notification_count > 0): ?>
                            <span class="absolute top-0 right-0 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full transform translate-x-1 -translate-y-1">
                                <?= $notification_count ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    
                    <!-- Notifications Dropdown -->
                    <div 
                        x-show="open"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 transform scale-95"
                        x-transition:enter-end="opacity-100 transform scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 transform scale-100"
                        x-transition:leave-end="opacity-0 transform scale-95"
                        class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl overflow-hidden"
                        style="display: none;"
                    >
                        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-4 py-3">
                            <h3 class="text-white font-semibold">Notifications (<?= $notification_count ?>)</h3>
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            <?php if ($recent_notifications && $recent_notifications->num_rows > 0): ?>
                                <?php while ($notif = $recent_notifications->fetch_assoc()): ?>
                                    <a href="notifications.php" class="block px-4 py-3 hover:bg-gray-50 transition-colors border-b border-gray-100">
                                        <p class="text-gray-800 text-sm mb-1">
                                            <?= htmlspecialchars(substr($notif['message'], 0, 80)) ?>...
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <span class="font-medium">To: <?= ucfirst(htmlspecialchars($notif['recipients'])) ?></span> • 
                                            <?= date('M j, Y', strtotime($notif['created_at'])) ?>
                                        </p>
                                    </a>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="px-4 py-8 text-center text-gray-500">
                                    <i class="fas fa-bell-slash text-3xl mb-2"></i>
                                    <p class="text-sm">No recent notifications</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <a href="notifications.php" class="block px-4 py-3 text-center text-blue-600 hover:bg-blue-50 font-medium text-sm transition-colors">
                            <i class="fas fa-list mr-2"></i>View All Notifications
                        </a>
                    </div>
                </div>
                
                <!-- Profile Dropdown -->
                <div class="relative" x-data="{ open: false }">
                    <button 
                        @click="open = !open"
                        @click.away="open = false"
                        class="flex items-center space-x-3 px-3 py-2 rounded-full hover:bg-blue-700 transition-all duration-200"
                    >
                        <img 
                            src="<?= $profile_photo ?>" 
                            alt="Profile" 
                            class="w-10 h-10 rounded-full border-2 border-white object-cover"
                            style="box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);"
                        >
                        <span class="text-white font-medium hidden md:block"><?= $username ?></span>
                        <i class="fas fa-chevron-down text-white text-sm hidden md:block"></i>
                    </button>
                    
                    <!-- Profile Dropdown Menu -->
                    <div 
                        x-show="open"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 transform scale-95"
                        x-transition:enter-end="opacity-100 transform scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 transform scale-100"
                        x-transition:leave-end="opacity-0 transform scale-95"
                        class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl overflow-hidden"
                        style="display: none;"
                    >
                        <div class="px-4 py-3 bg-gradient-to-r from-blue-600 to-blue-700">
                            <p class="text-white font-semibold"><?= $username ?></p>
                            <p class="text-blue-100 text-xs"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></p>
                        </div>
                        <div class="py-2">
                            <a href="profile.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-blue-50 transition-colors">
                                <i class="fas fa-user w-5 text-blue-600"></i>
                                <span class="ml-3">My Profile</span>
                            </a>
                            <a href="settings.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-blue-50 transition-colors">
                                <i class="fas fa-cog w-5 text-blue-600"></i>
                                <span class="ml-3">Settings</span>
                            </a>
                             <a href="recycle_bin.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-blue-50 transition-colors">
                                <i class="fas fa-bin w-5 text-blue-600"></i>
                                <span class="ml-3">Recycle Bin</span>
                            </a>
                            <hr class="my-2 border-gray-200">
                            <a href="logout.php" class="flex items-center px-4 py-2 text-red-600 hover:bg-red-50 transition-colors">
                                <i class="fas fa-sign-out-alt w-5"></i>
                                <span class="ml-3">Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Add Alpine.js for dropdown functionality -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<!-- Additional Styling -->
<style>
    /* Ensure body has proper padding for fixed navbar */
    body {
        padding-top: 64px;
    }
    
    /* Custom scrollbar for notifications */
    .max-h-96::-webkit-scrollbar {
        width: 6px;
    }
    
    .max-h-96::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    
    .max-h-96::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
    }
    
    .max-h-96::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    
    /* Smooth transitions */
    * {
        transition-property: background-color, border-color, color, fill, stroke;
        transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        transition-duration: 150ms;
    }
    
    /* Mobile responsiveness */
    @media (max-width: 768px) {
        nav > div > div {
            padding: 0 1rem;
        }
        
        .max-w-2xl {
            max-width: 100%;
            margin: 0 1rem;
        }
        
        nav > div > div > div:first-child {
            width: auto;
        }
    }
    
    /* Focus states for accessibility */
    button:focus, a:focus, input:focus {
        outline: 2px solid rgba(147, 51, 234, 0.5);
        outline-offset: 2px;
    }
    
    /* Animation for notification badge */
    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.8;
        }
    }
    
    .animate-pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
</style>

<!-- Font Awesome (if not already included) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Tailwind CSS (if not already included) -->
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">