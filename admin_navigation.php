<?php
$current_page = basename($_SERVER['PHP_SELF']); // Detect current page for active class
?>

<!-- Fixed Sidebar -->
<aside class="sidebar" id="sidebarMenu">
    <div class="p-4">
        <div class="flex items-center mb-6">
            <i class="fas fa-graduation-cap text-white text-2xl mr-2"></i>
            <div>
                <h2 class="text-white text-base font-bold">GCA Portal</h2>
                <p class="text-blue-300 text-xs">Admin Dashboard</p>
            </div>
        </div>
        
        <nav>
            <!-- Dashboard -->
            <a href="admin_dashboard.php" class="sidebar-link flex items-center text-white py-2 px-3 rounded mb-1 <?= ($current_page == 'admin_dashboard.php') ? 'active' : '' ?>">
                <i class="fas fa-home mr-2 text-sm"></i>
                <span class="text-sm whitespace-nowrap">Dashboard</span>
            </a>
            <a href="course_enquiries.php" class="sidebar-link flex items-center text-white py-2 px-3 rounded mb-1 <?= ($current_page == 'course_enquiries.php') ? 'active' : '' ?>">
                <i class="fas fa-home mr-2 text-sm"></i>
                <span class="text-sm whitespace-nowrap">Enquiries</span>
            </a>

            <div class="sidebar-divider my-2"></div>

            <!-- User Management -->
            <a href="approve_users.php" class="sidebar-link flex items-center text-white py-2 px-3 rounded mb-1 <?= ($current_page == 'approve_users.php') ? 'active' : '' ?>">
                <i class="fas fa-user-check mr-2 text-sm"></i>
                <span class="text-sm whitespace-nowrap">Approve Users</span>
            </a>
            <a href="manage_users.php" class="sidebar-link flex items-center text-white py-2 px-3 rounded mb-1 <?= ($current_page == 'manage_users.php') ? 'active' : '' ?>">
                <i class="fas fa-user-check mr-2 text-sm"></i>
                <span class="text-sm whitespace-nowrap">Users Management</span>
            </a>

            <a href="inactivation_requests.php" class="sidebar-link flex items-center text-white py-2 px-3 rounded mb-1 <?= ($current_page == 'inactivation_requets.php') ? 'active' : '' ?>">
                <i class="fas fa-user-check mr-2 text-sm"></i>
                <span class="text-sm whitespace-nowrap">Disciplinary Requests</span>
            </a>
            <a href="manage_students.php" class="sidebar-link flex items-center text-white py-2 px-3 rounded mb-1 <?= ($current_page == 'manage_students.php') ? 'active' : '' ?>">
                <i class="fas fa-user-graduate mr-2 text-sm"></i>
                <span class="text-sm whitespace-nowrap">Students</span>
            </a>
        
             <a href="academics.php" class="sidebar-link flex items-center text-white py-2 px-3 rounded mb-1 <?= ($current_page == 'academics.php') ? 'active' : '' ?>">
                <i class="fas fa-user-graduate mr-2 text-sm"></i>
                <span class="text-sm whitespace-nowrap">Academics</span>
            </a>
            <a href="manage_teachers.php" class="sidebar-link flex items-center text-white py-2 px-3 rounded mb-1 <?= ($current_page == 'manage_teachers.php') ? 'active' : '' ?>">
                <i class="fas fa-chalkboard-teacher mr-2 text-sm"></i>
                <span class="text-sm whitespace-nowrap">Teachers</span>
            </a>
            <a href="manage_parents.php" class="sidebar-link flex items-center text-white py-2 px-3 rounded mb-1 <?= ($current_page == 'manage_parents.php') ? 'active' : '' ?>">
                <i class="fas fa-users mr-2 text-sm"></i>
                <span class="text-sm whitespace-nowrap">Parents</span>
            </a>

            <div class="sidebar-divider my-2"></div>

            <!-- Academic -->
            <a href="manage_courses.php" class="sidebar-link flex items-center text-white py-2 px-3 rounded mb-1 <?= ($current_page == 'manage_courses.php') ? 'active' : '' ?>">
                <i class="fas fa-book-open mr-2 text-sm"></i>
                <span class="text-sm whitespace-nowrap">Courses</span>
            </a>
            <a href="course_assignment.php" class="sidebar-link flex items-center text-white py-2 px-3 rounded mb-1 <?= ($current_page == 'course_assignment.php') ? 'active' : '' ?>">
                <i class="fas fa-book mr-2 text-sm"></i>
                <span class="text-sm whitespace-nowrap">Assign Courses</span>
            </a>
            <a href="add_batch.php" class="sidebar-link flex items-center text-white py-2 px-3 rounded mb-1 <?= ($current_page == 'add_batch.php') ? 'active' : '' ?>">
                <i class="fas fa-plus-circle mr-2 text-sm"></i>
                <span class="text-sm whitespace-nowrap">Add Batch</span>
            </a>
             <a href="reports.php" class="sidebar-link flex items-center text-white py-2 px-3 rounded mb-1 <?= ($current_page == 'reports.php') ? 'active' : '' ?>">
                <i class="fas fa-chart-line mr-2 text-sm"></i>
                <span class="text-sm whitespace-nowrap">Repots</span>
            </a>

            <div class="sidebar-divider my-2"></div>

            <!-- Finance -->
            <a href="finance_dashboard.php" class="sidebar-link flex items-center text-white py-2 px-3 rounded mb-1 <?= ($current_page == 'finance_dashboard.php') ? 'active' : '' ?>">
                <i class="fas fa-chart-line mr-2 text-sm"></i>
                <span class="text-sm whitespace-nowrap">Finance</span>
            </a>

            <div class="sidebar-divider my-2"></div>

            <!-- Parent Relations -->
            <a href="parents_summary.php" class="sidebar-link flex items-center text-white py-2 px-3 rounded mb-1 <?= ($current_page == 'parents_summary.php') ? 'active' : '' ?>">
                <i class="fas fa-user-friends mr-2 text-sm"></i>
                <span class="text-sm whitespace-nowrap">Parent Summary</span>
            </a>
            <a href="assign_parent_student.php" class="sidebar-link flex items-center text-white py-2 px-3 rounded mb-1 <?= ($current_page == 'assign_parent_student.php') ? 'active' : '' ?>">
                <i class="fas fa-user-plus mr-2 text-sm"></i>
                <span class="text-sm whitespace-nowrap">Assign Students</span>
            </a>

            <div class="sidebar-divider my-2"></div>

            <!-- Communications -->
            <a href="admin_announcements.php" class="sidebar-link flex items-center text-white py-2 px-3 rounded mb-1 <?= ($current_page == 'admin_announcements.php') ? 'active' : '' ?>">
                <i class="fas fa-bullhorn mr-2 text-sm"></i>
                <span class="text-sm whitespace-nowrap">Announcements</span>
            </a>
            <a href="events.php" class="sidebar-link flex items-center text-white py-2 px-3 rounded mb-1 <?= ($current_page == 'events.php') ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt mr-2 text-sm"></i>
                <span class="text-sm whitespace-nowrap">Events</span>
            </a>
            <a href="admin_parent_chatting.php" class="sidebar-link flex items-center text-white py-2 px-3 rounded mb-1 <?= ($current_page == 'admin_parent_chatting.php') ? 'active' : '' ?>">
                <i class="fas fa-comments mr-2 text-sm"></i>
                <span class="text-sm whitespace-nowrap">Group Chat</span>
            </a>

            <div class="sidebar-divider my-2"></div>

            <!-- Logout -->
            <a href="logout.php" class="sidebar-link flex items-center text-white py-2 px-3 rounded mb-1 <?= ($current_page == 'logout.php') ? 'active' : '' ?>">
                <i class="fas fa-sign-out-alt mr-2 text-sm"></i>
                <span class="text-sm whitespace-nowrap">Logout</span>
            </a>
        </nav>
    </div>
</aside>

<style>
    /* Sidebar Styles */
    .sidebar {
        width: 220px;
        background: linear-gradient(180deg, #1e3a8a, #3b82f6);
        position: fixed;
        height: 100vh;
        overflow-y: auto;
        transition: transform 0.3s ease;
        z-index: 1000;
        box-shadow: 4px 0 15px rgba(0, 0, 0, 0.2);
    }
    
    .sidebar.hidden {
        transform: translateX(-100%);
    }
    
    /* Sidebar Links */
    .sidebar-link {
        transition: all 0.3s ease;
        position: relative;
        font-weight: 500;
        font-size: 0.875rem;
    }
    
    .sidebar-link:hover {
        background: rgba(255, 255, 255, 0.1);
        padding-left: 1rem;
        text-decoration: none;
        color: white !important;
    }
    
    .sidebar-link.active {
        background: rgba(255, 255, 255, 0.2);
        border-left: 3px solid white;
        font-weight: 600;
    }
    
    .sidebar-link i {
        width: 16px;
        text-align: center;
        font-size: 0.875rem;
    }
    
    /* Sidebar Divider */
    .sidebar-divider {
        height: 1px;
        background: rgba(255, 255, 255, 0.1);
        margin-left: 1rem;
        margin-right: 1rem;
    }
    
    /* Section Headers */
    .text-blue-200 {
        color: #bfdbfe;
        font-size: 0.7rem;
    }
    
    /* Custom Scrollbar */
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }
    
    .sidebar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.05);
    }
    
    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 3px;
    }
    
    .sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.3);
    }
    
    /* Push main content right */
    @media (min-width: 992px) {
        .content, .main-content {
            margin-left: 220px;
            transition: margin-left 0.3s ease;
        }
    }
    
    /* Mobile Responsiveness */
    .mobile-toggle {
        display: none;
    }
    
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }
        
        .sidebar.mobile-open {
            transform: translateX(0);
        }
        
        .content, .main-content {
            margin-left: 0 !important;
        }
        
        .mobile-toggle {
            display: block;
        }
    }
    
    /* Smooth Animations */
    * {
        transition-property: background-color, border-color, color, transform;
        transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        transition-duration: 150ms;
    }
    
    /* Focus States for Accessibility */
    .sidebar-link:focus {
        outline: 2px solid rgba(255, 255, 255, 0.5);
        outline-offset: 2px;
    }
    
    /* Logo Gradient Effect */
    .fas.fa-graduation-cap {
        filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
    }
    
    /* Hover Effect for Icons */
    .sidebar-link:hover i {
        transform: scale(1.1);
        transition: transform 0.3s ease;
    }
    
    /* Active Link Glow Effect */
    .sidebar-link.active::after {
        content: '';
        position: absolute;
        right: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 3px;
        height: 60%;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.8), rgba(255, 255, 255, 0.3));
        border-radius: 2px 0 0 2px;
    }
</style>

<!-- Font Awesome (if not already included) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Tailwind CSS Utility Classes (if not already included) -->
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

<!-- Mobile Toggle Script -->
<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebarMenu');
        const mainContent = document.querySelector('.main-content, .content');
        
        sidebar.classList.toggle('mobile-open');
        
        // Close sidebar when clicking outside on mobile
        if (sidebar.classList.contains('mobile-open')) {
            document.addEventListener('click', closeSidebarOnClickOutside);
        } else {
            document.removeEventListener('click', closeSidebarOnClickOutside);
        }
    }

    function closeSidebarOnClickOutside(event) {
        const sidebar = document.getElementById('sidebarMenu');
        const toggleBtn = event.target.closest('.mobile-toggle');
        
        if (!sidebar.contains(event.target) && !toggleBtn) {
            sidebar.classList.remove('mobile-open');
            document.removeEventListener('click', closeSidebarOnClickOutside);
        }
    }
</script>