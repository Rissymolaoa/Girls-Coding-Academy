<?php
$current_page = basename($_SERVER['PHP_SELF']); // Detect current page for active class
?>

<!-- Fixed Sidebar -->
<aside class="sidebar" id="sidebarMenu">
    <div class="sidebar-header text-center py-4">
        <div class="logo-text mb-3">
            <h4 class="text-white fw-bold">Girls Coding</h4>
            <p class="text-muted small mb-0">School Management</p>
        </div>
        <hr style="border-color: rgba(255,255,255,0.15); max-width: 150px; margin: 0.5rem auto;">
    </div>
    <nav class="nav flex-column px-3">
        <!-- Dashboard Section -->
        <a href="admin_dashboard.php" class="nav-link <?= ($current_page == 'admin_dashboard.php') ? 'active' : '' ?>">
            <i class="bi bi-house-door me-2"></i> Dashboard
        </a>

        <hr class="divider my-2" style="border-color: rgba(255,255,255,0.1);">

        <!-- User Management Section -->
        <div class="section-header small fw-bold text-uppercase text-muted mb-2">User Management</div>
        <a href="approve_users.php" class="nav-link <?= ($current_page == 'approve_users.php') ? 'active' : '' ?>">
            <i class="bi bi-person-check me-2"></i> Approve Users
        </a>
        <a href="manage_students.php" class="nav-link <?= ($current_page == 'manage_students.php') ? 'active' : '' ?>">
            <i class="bi bi-people me-2"></i> Manage Students
        </a>
        <a href="manage_teachers.php" class="nav-link <?= ($current_page == 'manage_teachers.php') ? 'active' : '' ?>">
            <i class="bi bi-person-badge me-2"></i> Manage Teachers
        </a>
        <a href="manage_parents.php" class="nav-link <?= ($current_page == 'manage_parents.php') ? 'active' : '' ?>">
            <i class="bi bi-person-lines-fill me-2"></i> Manage Parents
        </a>

        <hr class="divider my-2" style="border-color: rgba(255,255,255,0.1);">

        <!-- Academic Management Section -->
        <div class="section-header small fw-bold text-uppercase text-muted mb-2">Academic Management</div>
        <a href="manage_courses.php" class="nav-link <?= ($current_page == 'manage_courses.php') ? 'active' : '' ?>">
            <i class="bi bi-journal-bookmark me-2"></i> Manage Courses
        </a>
        <a href="course_assignment.php" class="nav-link <?= ($current_page == 'course_assignment.php') ? 'active' : '' ?>">
            <i class="bi bi-book me-2"></i> Assign Courses
        </a>
        <a href="add_batch.php" class="nav-link <?= ($current_page == 'add_batch.php') ? 'active' : '' ?>">
            <i class="bi bi-plus-circle me-2"></i> Add Batch
        </a>

        <hr class="divider my-2" style="border-color: rgba(255,255,255,0.1);">

        <!-- Parent-Student Relations Section -->
        <div class="section-header small fw-bold text-uppercase text-muted mb-2">Parent-Student Relations</div>
        <a href="parents_summary.php" class="nav-link <?= ($current_page == 'parents_summary.php') ? 'active' : '' ?>">
            <i class="bi bi-people-fill me-2"></i> Parent Summary
        </a>
        <a href="assign_parent_student.php" class="nav-link <?= ($current_page == 'assign_parent_student.php') ? 'active' : '' ?>">
            <i class="bi bi-person-plus me-2"></i> Assign Students
        </a>

        <hr class="divider my-2" style="border-color: rgba(255,255,255,0.1);">

        <!-- Communications Section -->
        <div class="section-header small fw-bold text-uppercase text-muted mb-2">Communications</div>
        <a href="admin_announcements.php" class="nav-link <?= ($current_page == 'admin_announcements.php') ? 'active' : '' ?>">
            <i class="bi bi-megaphone-fill me-2"></i> Announcements
        </a>
        <a href="events.php" class="nav-link <?= ($current_page == 'events.php') ? 'active' : '' ?>">
            <i class="bi bi-calendar-event me-2"></i> Post Events
        </a>
        <a href="admin_parent_chatting.php" class="nav-link <?= ($current_page == 'admin_parent_chatting.php') ? 'active' : '' ?>">
            <i class="bi bi-chat me-2"></i> Parents Group Chat
        </a>
        <!-- Add this in the Communications or a new Finance section in admin_navigation.php -->
<div class="section-header small fw-bold text-uppercase text-muted mb-2">Finance Management</div>
<a href="finance_dashboard.php" class="nav-link <?= ($current_page == 'finance_dashboard.php') ? 'active' : '' ?>">
    <i class="bi bi-graph-up me-2"></i> Finance Dashboard
</a>

        <hr class="divider my-3" style="border-color: rgba(255,255,255,0.1);">

        <!-- Logout -->
        <a href="logout.php" class="nav-link <?= ($current_page == 'logout.php') ? 'active' : '' ?>">
            <i class="bi bi-box-arrow-right me-2"></i> Logout
        </a>
    </nav>
</aside>

<style>
    /* Enhanced Fixed Sidebar Styles */
    .sidebar {
        width: 280px;
        background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
        color: #fff;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        overflow-y: auto;
        box-shadow: 4px 0 15px rgba(0,0,0,0.2);
        transition: all 0.3s ease;
        z-index: 1030;
        padding-bottom: 2rem;
    }

    .logo-text h4 {
        background: linear-gradient(135deg, #3b82f6, #06b6d4);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin: 0;
        font-size: 1.8rem;
    }

    .sidebar-header {
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .section-header {
        color: #94a3b8;
        letter-spacing: 1px;
        padding-left: 1rem;
    }

    .divider {
        width: 80%;
        margin-left: auto;
        margin-right: auto;
        opacity: 0.5;
    }

    .sidebar .nav-link {
        color: #cbd5e1;
        padding: 14px 20px;
        font-size: 1rem;
        font-weight: 500;
        border-radius: 8px;
        margin-bottom: 4px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .sidebar .nav-link:hover {
        background-color: rgba(59, 130, 246, 0.1);
        color: #fff;
        transform: translateX(4px);
        text-decoration: none;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
    }

    .sidebar .nav-link.active {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        color: #fff;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .sidebar .nav-link.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, #60a5fa, #3b82f6);
        border-radius: 0 2px 2px 0;
    }

    .sidebar .nav-link i {
        font-size: 1.1rem;
        width: 20px;
        text-align: center;
        vertical-align: middle;
    }

    /* Push main content right */
    @media (min-width: 992px) {
        .content {
            margin-left: 280px;
            transition: margin-left 0.3s ease;
        }
    }

    /* Scrollbar styling */
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }
    .sidebar::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #3b82f6, #1d4ed8);
        border-radius: 3px;
    }
    .sidebar::-webkit-scrollbar-track {
        background: rgba(255,255,255,0.05);
    }
</style>