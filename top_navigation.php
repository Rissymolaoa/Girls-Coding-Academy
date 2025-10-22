<?php
// top_navigation.php
// Modernized top navbar for Girls Coding Academy school management system: fixed-top, responsive, with logo, centered search,
// notifications with dropdown, profile avatar dropdown. Search form styled in a complementary color to side navigation.
// Uses database schema context for notifications and user data.

$notification_count = 42; // Replace with DB query for unread notifications (e.g., new assignments, announcements)
$username = htmlspecialchars($_SESSION['username'] ?? 'User');
?>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background: linear-gradient(90deg, #2B2D42, #4B5EAA); box-shadow: 0 2px 10px rgba(0,0,0,0.2);">
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
            <!-- Centered Search Form -->
            <form class="d-flex mx-auto my-2 my-lg-0" style="max-width: 500px; width: 100%;">
                <input class="form-control me-2" type="search" placeholder="Search students, courses, activities..." aria-label="Search" style="background-color: #F3E8FF; border-color: #A855F7; color: #2B2D42;">
                <button class="btn" type="submit" style="background-color: #A855F7; color: #FFF; border: none;">
                    <i class="bi bi-search"></i>
                </button>
            </form>
            
            <!-- Right-side items -->
            <ul class="navbar-nav ms-auto align-items-center">
                <!-- Notifications Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell-fill" style="font-size: 1.2rem; color: #F3E8FF;"></i>
                        <?php if ($notification_count > 0): ?>
                            <span class="badge bg-danger position-absolute top-0 start-100 translate-middle rounded-pill"><?php echo $notification_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" style="width: 320px; background-color: #FFF;">
                        <li class="dropdown-header" style="color: #2B2D42;">Notifications</li>
                        <!-- Dynamic notification items from DB -->
                        <li><a class="dropdown-item" href="#">New assignment: Microsoft Access due 2025-09-25</a></li>
                        <li><a class="dropdown-item" href="#">Event: Freshers Ball on 2025-10-11</a></li>
                        <li><a class="dropdown-item" href="#">New announcement from admin</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="notifications.php">View all notifications</a></li>
                    </ul>
                </li>
                
                <!-- Profile Dropdown -->
                <li class="nav-item dropdown ms-3">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?php echo htmlspecialchars($_SESSION['photo'] ?? 'default_user.png'); ?>" alt="Profile" class="rounded-circle me-2" style="width: 32px; height: 32px;">
                        <span style="color: #F3E8FF;"><?php echo $username; ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown" style="background-color: #FFF;">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
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
</style>