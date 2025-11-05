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
$profile_photo = htmlspecialchars($_SESSION['photo'] ?? 'default_user.png');
?>
<nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background: linear-gradient(90deg, #2B2D42, #4B5EAA); box-shadow: 0 2px 10px rgba(0,0,0,0.2); z-index: 1030;">
    <div class="container-fluid">
        <!-- Brand/Logo -->
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <img src="gca_logo.png" alt="Girls Coding Academy Logo" style="height: 35px; margin-right: 12px;"> <!-- Replace with actual logo -->
        </a>
        
        <!-- Toggle button for sidebar on mobile -->
        <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle sidebar">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Collapse for navbar items -->
        <div class="collapse navbar-collapse" id="navbarContent">
            <!-- Centered Search Form - Now functional with GET to search.php -->
            <form class="d-flex mx-auto my-2 my-lg-0 position-relative" method="GET" action="search.php" style="max-width: 500px; width: 100%; z-index: 1040;">
                <input name="q" class="form-control me-2" type="search" placeholder="Search students, courses, activities..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" aria-label="Search" style="background-color: #F3E8FF; border-color: #A855F7; color: #2B2D42; position: relative; z-index: 1041;">
                <button class="btn position-relative" type="submit" style="background-color: #A855F7; color: #FFF; border: none; z-index: 1042;">
                    <i class="bi bi-search"></i>
                </button>
            </form>
            
            <!-- Right-side items -->
            <ul class="navbar-nav ms-auto align-items-center">
                <!-- Notifications Dropdown - Now dynamic from DB -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="z-index: 1040;">
                        <i class="bi bi-bell-fill" style="font-size: 1.2rem; color: #F3E8FF;"></i>
                        <?php if ($notification_count > 0): ?>
                            <span class="badge bg-danger position-absolute top-0 start-100 translate-middle rounded-pill" style="z-index: 1041;"><?= $notification_count ?></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" style="width: 320px; background-color: #FFF; max-height: 400px; overflow-y: auto; z-index: 1050; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.15);">
                        <li class="dropdown-header" style="color: #2B2D42;">Notifications (<?= $notification_count ?>)</li>
                        <?php if ($recent_notifications && $recent_notifications->num_rows > 0): ?>
                            <?php while ($notif = $recent_notifications->fetch_assoc()): ?>
                                <li><a class="dropdown-item" href="notifications.php" style="z-index: 1051;">
                                    <?= htmlspecialchars(substr($notif['message'], 0, 80)) ?>...
                                    <br><small class="text-muted">To: <?= ucfirst(htmlspecialchars($notif['recipients'])) ?> • <?= date('M j, Y', strtotime($notif['created_at'])) ?></small>
                                </a></li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li><span class="dropdown-item text-center text-muted">No recent notifications</span></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="notifications.php" style="z-index: 1051;"><i class="bi bi-list-ul"></i> View all</a></li>
                    </ul>
                </li>
                
                <!-- Profile Dropdown -->
                <li class="nav-item dropdown ms-3">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="z-index: 1040;">
                        <img src="<?= $profile_photo ?>" alt="Profile" class="rounded-circle me-2" style="width: 32px; height: 32px; z-index: 1041;">
                        <span style="color: #F3E8FF; z-index: 1041;"><?= $username ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown" style="background-color: #FFF; z-index: 1050; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.15);">
                        <li><a class="dropdown-item" href="profile.php" style="z-index: 1051;"><i class="bi bi-person me-2"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php" style="z-index: 1051;"><i class="bi bi-gear me-2"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php" style="z-index: 1051;"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<!-- Additional CSS for hover effects and styling -->
<style>
    .navbar-nav .nav-link:hover, .navbar-nav .dropdown-toggle:hover {
        color: #F3E8FF !important;
        opacity: 0.9;
        transition: opacity 0.3s ease;
    }
    .dropdown-menu {
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        border: none;
        border-radius: 8px;
    }
    .dropdown-item:hover {
        background-color: #EDE9FE;
        color: #4B5EAA;
    }
    .form-control:focus {
        border-color: #9333EA;
        box-shadow: 0 0 0 0.2rem rgba(168, 85, 247, 0.25);
    }
    .btn:hover {
        background-color: #9333EA;
        transition: background-color 0.3s ease;
    }
    /* Ensure no overlap with sidebar */
    .offcanvas {
        z-index: 1045;
    }
    .navbar-collapse {
        position: relative;
        z-index: 1035;
    }
    /* Adjust for fixed navbar padding in content */
    body {
        padding-top: 76px; /* Approximate navbar height */
    }
    @media (max-width: 991.98px) {
        .navbar-collapse {
            background-color: #2B2D42;
            margin-top: 10px;
            border-radius: 8px;
            padding: 15px;
        }
        .form-control, .btn {
            border-radius: 6px;
        }
    }
</style>
<?php
?>