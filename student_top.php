<?php
// student_navigation.php
// This file contains the student top navigation and sidebar
// Include this file at the top of your student pages after session_start()

// Make sure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DB connection (if not already connected)
if (!isset($conn)) {
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db = "girlscodingacademydb";
    
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
}

// Check if logged in student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user and student data
$user_res = $conn->query("SELECT * FROM users WHERE user_id = $user_id");
$user_data = $user_res->fetch_assoc();

$student_res = $conn->query("SELECT * FROM students WHERE user_id = $user_id");
$student_data = $student_res->fetch_assoc();

$student_photo = $student_data['photo'] ?? 'default.jpg';
$student_name = htmlspecialchars($user_data['firstName'] . ' ' . $user_data['lastName']);
$first_name = htmlspecialchars($user_data['firstName']);

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Helper function to check if page is active
function is_active($page) {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}
?>

<!-- Student Top Navigation -->
<div class="student-top-nav">
    <div class="nav-left">
        <button class="toggle-sidebar" id="toggleSidebar">
            <i class="bi bi-list"></i>
        </button>
        <div class="logo">
            <i class="bi bi-code-slash"></i>
            <span>GCA</span>
        </div>
    </div>

    <div class="nav-center">
        <div class="search-bar">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="Search courses, announcements...">
        </div>
    </div>

    <div class="nav-right">
        <div class="nav-icons">
            <button class="nav-icon-btn" data-bs-toggle="tooltip" title="Notifications">
                <i class="bi bi-bell"></i>
                <span class="notification-badge">3</span>
            </button>
            <button class="nav-icon-btn" data-bs-toggle="tooltip" title="Messages">
                <i class="bi bi-chat-dots"></i>
                <span class="notification-badge">2</span>
            </button>
        </div>

        <div class="user-dropdown">
            <button class="user-menu-btn" data-bs-toggle="dropdown">
                <img src="<?= htmlspecialchars($student_photo) ?>" alt="Profile" class="user-avatar-sm">
                <span class="user-name"><?= $first_name ?></span>
                <i class="bi bi-chevron-down"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="student_profile.php">
                        <i class="bi bi-person-circle"></i> My Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="student_settings.php">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- Student Sidebar Navigation -->
<div class="student-sidebar" id="studentSidebar">
    <div class="sidebar-header">
        <img src="<?= htmlspecialchars($student_photo) ?>" alt="Profile" class="sidebar-avatar">
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= $student_name ?></div>
            <div class="sidebar-user-role">Student</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Main</div>
            <a href="student.php" class="nav-link <?= is_active('student.php') ?>">
                <i class="bi bi-house-door"></i>
                <span>Dashboard</span>
            </a>
            <a href="student_profile.php" class="nav-link <?= is_active('student_profile.php') ?>">
                <i class="bi bi-person-circle"></i>
                <span>My Profile</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Learning</div>
            <a href="student_courses.php" class="nav-link <?= is_active('student_courses.php') ?>">
                <i class="bi bi-journal-bookmark"></i>
                <span>My Courses</span>
            </a>
            <a href="student_materials.php" class="nav-link <?= is_active('student_materials.php') ?>">
                <i class="bi bi-file-earmark-pdf"></i>
                <span>Learning Materials</span>
            </a>
            <a href="student_activities.php" class="nav-link <?= is_active('student_activities.php') ?>">
                <i class="bi bi-list-task"></i>
                <span>Activities</span>
            </a>
            <a href="student_marks.php" class="nav-link <?= is_active('student_marks.php') ?>">
                <i class="bi bi-bar-chart-line-fill"></i>
                <span>My Grades</span>
            </a>
            <a href="student_gradebook.php" class="nav-link <?= is_active('student_gradebook.php') ?>">
                <i class="bi bi-graph-up"></i>
                <span>Performance</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Academic</div>
            <a href="attendance.php" class="nav-link <?= is_active('attendance.php') ?>">
                <i class="bi bi-card-checklist"></i>
                <span>My Attendance</span>
            </a>
            <a href="student_announcements.php" class="nav-link <?= is_active('student_announcements.php') ?>">
                <i class="bi bi-megaphone"></i>
                <span>Announcements</span>
            </a>
            <a href="student_calendar.php" class="nav-link <?= is_active('student_calendar.php') ?>">
                <i class="bi bi-calendar-event"></i>
                <span>Calendar</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Communication</div>
            <a href="student_messages.php" class="nav-link <?= is_active('student_messages.php') ?>">
                <i class="bi bi-chat-dots"></i>
                <span>Messages</span>
                <span class="nav-badge">2</span>
            </a>
            <a href="student_events.php" class="nav-link <?= is_active('student_events.php') ?>">
                <i class="bi bi-calendar2-event"></i>
                <span>Events</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Other</div>
            <a href="student_support.php" class="nav-link <?= is_active('student_support.php') ?>">
                <i class="bi bi-question-circle"></i>
                <span>Support</span>
            </a>
            <a href="student_settings.php" class="nav-link <?= is_active('student_settings.php') ?>">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a>
        </div>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<style>
    :root {
        --primary: #667eea;
        --primary-dark: #764ba2;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --info: #3b82f6;
        --light-bg: #f9fafb;
        --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        --transition: all 0.3s ease;
    }

    * {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    /* Top Navigation */
    .student-top-nav {
        background: white;
        border-bottom: 1px solid #e5e7eb;
        padding: 1rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 2rem;
        position: sticky;
        top: 0;
        z-index: 100;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .nav-left {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        min-width: fit-content;
    }

    .toggle-sidebar {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: var(--primary);
        cursor: pointer;
        display: none;
        padding: 0.5rem;
        transition: var(--transition);
    }

    .toggle-sidebar:hover {
        color: var(--primary-dark);
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1.25rem;
        font-weight: 700;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .nav-center {
        flex: 1;
        max-width: 400px;
    }

    .search-bar {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: var(--light-bg);
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 0.75rem 1rem;
        transition: var(--transition);
    }

    .search-bar:focus-within {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .search-bar input {
        background: none;
        border: none;
        outline: none;
        font-size: 0.9rem;
        width: 100%;
        color: #1f2937;
    }

    .search-bar input::placeholder {
        color: #9ca3af;
    }

    .search-bar i {
        color: #9ca3af;
        font-size: 1rem;
    }

    .nav-right {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }

    .nav-icons {
        display: flex;
        gap: 1rem;
    }

    .nav-icon-btn {
        background: none;
        border: none;
        font-size: 1.25rem;
        color: #6b7280;
        cursor: pointer;
        position: relative;
        padding: 0.5rem;
        transition: var(--transition);
    }

    .nav-icon-btn:hover {
        color: var(--primary);
    }

    .notification-badge {
        position: absolute;
        top: 0;
        right: 0;
        background: var(--danger);
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.65rem;
        font-weight: 700;
    }

    .user-dropdown {
        position: relative;
    }

    .user-menu-btn {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: var(--light-bg);
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 0.5rem 1rem;
        cursor: pointer;
        transition: var(--transition);
        font-weight: 600;
        color: #1f2937;
        font-size: 0.9rem;
    }

    .user-menu-btn:hover {
        background: #f3f4f6;
    }

    .user-avatar-sm {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--primary);
    }

    .dropdown-menu {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        margin-top: 0.5rem;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        color: #1f2937;
        text-decoration: none;
        transition: var(--transition);
    }

    .dropdown-item:hover {
        background: var(--light-bg);
        color: var(--primary);
    }

    .dropdown-item i {
        font-size: 1.1rem;
    }

    /* Sidebar */
    .student-sidebar {
        width: 280px;
        background: white;
        border-right: 1px solid #e5e7eb;
        height: calc(100vh - 80px);
        overflow-y: auto;
        position: fixed;
        left: 0;
        top: 80px;
        z-index: 99;
        display: flex;
        flex-direction: column;
    }

    .sidebar-header {
        padding: 1.5rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .sidebar-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid var(--primary);
    }

    .sidebar-user-info {
        flex: 1;
    }

    .sidebar-user-name {
        font-weight: 700;
        color: #1f2937;
        font-size: 0.95rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sidebar-user-role {
        color: #9ca3af;
        font-size: 0.8rem;
    }

    .sidebar-nav {
        flex: 1;
        padding: 1.5rem 0;
        overflow-y: auto;
    }

    .nav-section {
        margin-bottom: 1.5rem;
    }

    .nav-section-title {
        padding: 0.5rem 1.5rem;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #9ca3af;
        letter-spacing: 0.5px;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1.5rem;
        color: #6b7280;
        text-decoration: none;
        transition: var(--transition);
        position: relative;
        font-weight: 500;
        font-size: 0.95rem;
    }

    .nav-link:hover {
        color: var(--primary);
        background: #f0f4ff;
    }

    .nav-link.active {
        color: var(--primary);
        background: #f0f4ff;
        border-left: 4px solid var(--primary);
        padding-left: calc(1.5rem - 4px);
    }

    .nav-link i {
        font-size: 1.25rem;
        width: 1.5rem;
    }

    .nav-badge {
        margin-left: auto;
        background: var(--danger);
        color: white;
        border-radius: 20px;
        padding: 0.25rem 0.5rem;
        font-size: 0.7rem;
        font-weight: 700;
    }

    .sidebar-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid #e5e7eb;
    }

    .logout-btn {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        width: 100%;
        padding: 0.75rem;
        background: #fee2e2;
        color: var(--danger);
        text-decoration: none;
        border-radius: 12px;
        transition: var(--transition);
        font-weight: 600;
        font-size: 0.95rem;
        justify-content: center;
        border: none;
        cursor: pointer;
    }

    .logout-btn:hover {
        background: #fecaca;
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 98;
    }

    /* Main content adjustment */
    .main-container {
        margin-left: 280px;
        margin-top: 80px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .student-top-nav {
            padding: 1rem;
            gap: 1rem;
        }

        .toggle-sidebar {
            display: block;
        }

        .nav-center {
            display: none;
        }

        .nav-right {
            gap: 1rem;
        }

        .user-name {
            display: none;
        }

        .user-menu-btn {
            padding: 0.5rem;
        }

        .student-sidebar {
            width: 250px;
            transform: translateX(-100%);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }

        .student-sidebar.active {
            transform: translateX(0);
        }

        .sidebar-overlay.active {
            display: block;
        }

        .main-container {
            margin-left: 0;
        }

        .search-bar input::placeholder {
            font-size: 0.8rem;
        }
    }

    @media (max-width: 480px) {
        .student-top-nav {
            flex-wrap: wrap;
        }

        .logo span {
            display: none;
        }

        .student-sidebar {
            width: 100%;
        }

        .sidebar-header {
            padding: 1rem;
        }

        .sidebar-avatar {
            width: 50px;
            height: 50px;
        }

        .sidebar-user-name {
            font-size: 0.9rem;
        }

        .nav-link {
            padding: 0.65rem 1.25rem;
            font-size: 0.9rem;
        }

        .nav-section-title {
            padding: 0.4rem 1.25rem;
            font-size: 0.7rem;
        }
    }

    /* Scrollbar styling */
    .student-sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .student-sidebar::-webkit-scrollbar-track {
        background: transparent;
    }

    .student-sidebar::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 3px;
    }

    .student-sidebar::-webkit-scrollbar-thumb:hover {
        background: #9ca3af;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Toggle sidebar on mobile
        const toggleBtn = document.getElementById('toggleSidebar');
        const sidebar = document.getElementById('studentSidebar');
        const overlay = document.getElementById('sidebarOverlay');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            });

            overlay.addEventListener('click', function () {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });

            // Close sidebar when a link is clicked
            const navLinks = sidebar.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function () {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                });
            });
        }
    });
</script>