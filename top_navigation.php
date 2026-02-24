<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once 'db.php';

// === USER INFO ===
$username = 'Admin';
$email = 'admin@girlscoding.academy';
$profile_photo = 'admin.png';
$user_role = $_SESSION['role'] ?? 'admin';

if (isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
    $user_query = $conn->query("SELECT firstName, lastName, email FROM users WHERE user_id = $user_id LIMIT 1");

    if ($user_query && $user_query->num_rows > 0) {
        $user_data = $user_query->fetch_assoc();
        $username = htmlspecialchars($user_data['firstName'] . ' ' . $user_data['lastName']);
        $email = htmlspecialchars($user_data['email']);
    }

    // Profile photo logic
    if ($user_role === 'teacher') {
        $photo_query = $conn->query("SELECT photo FROM teachers WHERE user_id = $user_id LIMIT 1");
        if ($photo_query && $photo_query->num_rows > 0) {
            $photo_data = $photo_query->fetch_assoc();
            $profile_photo = $photo_data['photo'] !== 'null' && !empty($photo_data['photo'])
                ? htmlspecialchars($photo_data['photo'])
                : 'imageuploads/default_avatar.png';
        }
    } elseif ($user_role === 'student') {
        $photo_query = $conn->query("SELECT photo FROM students WHERE user_id = $user_id LIMIT 1");
        if ($photo_query && $photo_query->num_rows > 0) {
            $photo_data = $photo_query->fetch_assoc();
            $profile_photo = $photo_data['photo'] !== 'null' && !empty($photo_data['photo'])
                ? htmlspecialchars($photo_data['photo'])
                : 'imageuploads/default_avatar.png';
        }
    } elseif ($user_role === 'parent') {
        $photo_query = $conn->query("SELECT photo FROM parents WHERE user_id = $user_id LIMIT 1");
        if ($photo_query && $photo_query->num_rows > 0) {
            $photo_data = $photo_query->fetch_assoc();
            $profile_photo = $photo_data['photo'] !== 'null' && !empty($photo_data['photo'])
                ? htmlspecialchars($photo_data['photo'])
                : 'imageuploads/default_avatar.png';
        }
    }
}

// === NOTIFICATIONS ===
$notification_result = $conn->query("SELECT COUNT(*) as count FROM admin_announcements WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
$notification_count = $notification_result ? $notification_result->fetch_assoc()['count'] : 0;

$recent_notifications = $conn->query("SELECT message, recipients, created_at FROM admin_announcements ORDER BY created_at DESC LIMIT 3");

// === WEATHER (Maseru, Lesotho) ===
$lat = -29.3167;   // Maseru coordinates
$lon = 27.4833;
$api_key = 'YOUR_OPENWEATHERMAP_API_KEY'; // ← Replace with your free API key from https://openweathermap.org/api

$weather_url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&appid={$api_key}&units=metric";
$forecast_url = "https://api.openweathermap.org/data/2.5/forecast?lat={$lat}&lon={$lon}&appid={$api_key}&units=metric";

$weather_data = @file_get_contents($weather_url);
$forecast_data = @file_get_contents($forecast_url);

$weather = null;
$rain_alert = false;
$humidity = 0;
$temp = 0;
$weather_icon = 'bi-cloud-sun-fill';
$weather_desc = 'Loading...';

if ($weather_data !== false) {
    $weather_json = json_decode($weather_data, true);
    if (isset($weather_json['main'])) {
        $temp = round($weather_json['main']['temp']);
        $humidity = $weather_json['main']['humidity'];
        $weather_desc = $weather_json['weather'][0]['description'] ?? 'Unknown';
        $weather_icon_code = $weather_json['weather'][0]['icon'] ?? '01d';
        $weather_icon = match(substr($weather_icon_code, 0, 2)) {
            '01' => 'bi-sun-fill',
            '02' => 'bi-cloud-sun-fill',
            '03','04' => 'bi-clouds-fill',
            '09','10' => 'bi-cloud-rain-fill',
            '11' => 'bi-lightning-fill',
            '13' => 'bi-snow',
            default => 'bi-cloud-fog-fill'
        };
    }
}

// Rain check (next 3 hours)
if ($forecast_data !== false) {
    $forecast_json = json_decode($forecast_data, true);
    if (isset($forecast_json['list'][0]['pop']) && $forecast_json['list'][0]['pop'] > 0.3) {
        $rain_alert = true;
    }
}
?>

<!-- TOP NAVIGATION BAR -->
<nav class="fixed top-0 left-0 right-0 z-50 shadow-lg bg-gradient-to-r from-indigo-900 via-indigo-800 to-purple-900 border-b border-indigo-700/50">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">

            <!-- Logo + School Name (far left) -->
            <div class="flex items-center gap-3 flex-shrink-0">
                <img src="imageuploads/school_logo.png" alt="GCA Logo" class="h-10 w-10 object-contain rounded-full ring-2 ring-white/30">
                <div class="hidden md:block">
                    <span class="text-xl font-bold text-white tracking-tight">Girls Coding Academy</span>
                    <p class="text-xs text-indigo-200">Empowering Tomorrow's Coders</p>
                </div>
            </div>

            <!-- Weather & Rain Alert -->
            <div class="hidden lg:flex items-center gap-6 text-white/90 text-sm">
                <div class="flex items-center gap-2 bg-white/10 backdrop-blur-md px-4 py-2 rounded-full border border-white/20">
                    <i class="bi <?= $weather_icon ?> text-xl"></i>
                    <span><?= $temp ?>°C • <?= $humidity ?>% humidity</span>
                </div>

                <?php if ($rain_alert): ?>
                    <div class="flex items-center gap-2 bg-amber-500/20 backdrop-blur-md px-4 py-2 rounded-full border border-amber-400/40 text-amber-200">
                        <i class="bi bi-cloud-rain-heavy-fill text-lg animate-pulse"></i>
                        <span>Possible rain coming</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Search (center) -->
            <div class="flex-1 max-w-md mx-6 lg:mx-12 hidden md:block">
                <form method="GET" action="search.php" class="relative">
                    <input
                        type="search"
                        name="q"
                        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                        placeholder="Search students, courses, announcements..."
                        class="w-full pl-11 pr-5 py-2.5 bg-white/10 backdrop-blur-md border border-white/20 rounded-full text-white placeholder-white/60 focus:outline-none focus:ring-2 focus:ring-white/40 focus:bg-white/15 transition-all duration-200 text-sm"
                        aria-label="Search"
                    />
                    <button type="submit" class="absolute left-3 top-1/2 -translate-y-1/2 text-white/80 hover:text-white transition">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
            </div>

            <!-- Right Section: Notifications + Profile -->
            <div class="flex items-center space-x-3 lg:space-x-5">

                <!-- Notifications Dropdown -->
                <div class="relative" x-data="{ open: false }">
                    <button
                        @click="open = !open"
                        @click.away="open = false"
                        class="relative p-2.5 text-white/90 hover:text-white hover:bg-white/10 rounded-full transition-all duration-200"
                        aria-label="Notifications"
                    >
                        <i class="bi bi-bell-fill text-xl"></i>
                        <?php if ($notification_count > 0): ?>
                            <span class="absolute -top-1 -right-1 flex h-5 w-5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-5 w-5 bg-red-500 text-white text-xs font-bold items-center justify-center">
                                    <?= min($notification_count, 99) ?>
                                </span>
                            </span>
                        <?php endif; ?>
                    </button>

                    <!-- Dropdown Panel -->
                    <div
                        x-show="open"
                        x-transition
                        class="absolute right-0 mt-3 w-96 bg-white rounded-2xl shadow-2xl overflow-hidden ring-1 ring-black/10 z-50"
                    >
                        <div class="bg-gradient-to-r from-indigo-600 to-purple-700 px-6 py-4 text-white">
                            <h3 class="font-bold text-lg">Announcements</h3>
                            <p class="text-white/80 text-sm mt-1"><?= $notification_count ?> new this week</p>
                        </div>
                        <div class="max-h-80 overflow-y-auto divide-y divide-gray-100">
                            <?php if ($recent_notifications && $recent_notifications->num_rows > 0): ?>
                                <?php while ($n = $recent_notifications->fetch_assoc()): ?>
                                    <a href="announcements.php" class="block px-6 py-4 hover:bg-gray-50 transition">
                                        <p class="text-gray-800 font-medium text-sm line-clamp-2">
                                            <?= htmlspecialchars($n['message']) ?>
                                        </p>
                                        <div class="mt-1 text-xs text-gray-500 flex items-center gap-2">
                                            <span class="font-semibold capitalize"><?= htmlspecialchars($n['recipients']) ?></span>
                                            • <?= date('M j, g:ia', strtotime($n['created_at'])) ?>
                                        </div>
                                    </a>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-12 text-gray-400">
                                    <i class="bi bi-bell-slash text-4xl mb-3 block"></i>
                                    <p>No recent announcements</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <a href="announcements.php" class="block text-center bg-gray-50 hover:bg-gray-100 text-indigo-700 font-semibold py-3 transition border-t border-gray-200">
                            View All Announcements →
                        </a>
                    </div>
                </div>

                <!-- Profile Dropdown -->
                <div class="relative" x-data="{ open: false }">
                    <button
                        @click="open = !open"
                        @click.away="open = false"
                        class="flex items-center gap-3 p-2 rounded-xl hover:bg-white/10 transition-all duration-200"
                        aria-label="User Menu"
                    >
                        <img
                            src="<?= $profile_photo ?>"
                            alt="Profile"
                            class="w-10 h-10 rounded-full object-cover ring-2 ring-white/40 shadow-sm"
                            loading="lazy"
                            onerror="this.src='imageuploads/default_avatar.png'"
                        >
                        <div class="hidden lg:block text-left">
                            <span class="text-white font-medium text-sm block"><?= $username ?></span>
                            <span class="text-indigo-200 text-xs"><?= ucfirst($user_role) ?></span>
                        </div>
                    </button>

                    <!-- Profile Menu -->
                    <div
                        x-show="open"
                        x-transition
                        class="absolute right-0 mt-3 w-64 bg-white rounded-2xl shadow-2xl overflow-hidden ring-1 ring-black/10 z-50"
                    >
                        <div class="bg-gradient-to-br from-indigo-600 to-purple-700 px-6 py-5 text-white">
                            <p class="font-bold text-base"><?= $username ?></p>
                            <p class="text-xs opacity-90 truncate mt-1"><?= $email ?></p>
                            <p class="text-xs opacity-75 mt-2 capitalize font-medium"><?= $user_role ?></p>
                        </div>

                        <div class="py-2 divide-y divide-gray-100">
                            <a href="settings.php" class="flex items-center px-5 py-3 text-gray-700 hover:bg-indigo-50 transition text-sm">
                                <i class="bi bi-gear-wide-connected w-5 mr-3 text-indigo-600"></i> Settings
                            </a>

                            <?php if ($user_role === 'admin'): ?>
                                <a href="recycle_bin.php" class="flex items-center px-5 py-3 text-gray-700 hover:bg-indigo-50 transition text-sm">
                                    <i class="bi bi-trash3 w-5 mr-3 text-indigo-600"></i> Recycle Bin
                                </a>
                            <?php endif; ?>

                            <hr class="my-2 border-gray-100">

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

<style>
    body { padding-top: 64px; }
    nav { transition: all 0.3s ease; }
    nav.scrolled { background: rgba(49, 46, 129, 0.95) !important; backdrop-filter: blur(12px); }
</style>

<script>
    // Optional: Add subtle scroll effect
    window.addEventListener('scroll', () => {
        document.querySelector('nav').classList.toggle('scrolled', window.scrollY > 10);
    });
</script>