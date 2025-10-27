<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure teacher is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

include 'db.php';

$user_id = $_SESSION['user_id'];

// Fetch teacher info
$teacherQuery = $conn->prepare("
    SELECT u.username, u.email, u.gender, u.phone, u.dob, u.IDNumber,
           t.subject_speciality, t.photo,
           a.address1, a.streetName, a.postalCode, a.district, a.country
    FROM users u
    JOIN teachers t ON u.user_id = t.user_id
    LEFT JOIN addresses a ON u.address_id = a.address_id
    WHERE u.user_id = ?
");
$teacherQuery->bind_param("i", $user_id);
$teacherQuery->execute();
$teacherInfo = $teacherQuery->get_result()->fetch_assoc();
$teacherQuery->close();

if (!$teacherInfo) {
    die("Teacher profile not found.");
}

// Fetch assigned batches/courses
$batchesQuery = $conn->prepare("
    SELECT b.batch_id, b.batch_code, c.courseName, b.start_date, b.end_date, b.status
    FROM course_assignments ca
    JOIN batches b ON ca.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    JOIN teachers t ON ca.teacher_id = t.teacher_id
    JOIN users u ON t.user_id = u.user_id
    WHERE u.user_id = ?
    ORDER BY b.start_date DESC
");
$batchesQuery->bind_param("i", $user_id);
$batchesQuery->execute();
$assignedBatches = $batchesQuery->get_result()->fetch_all(MYSQLI_ASSOC);
$batchesQuery->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Profile - Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-header { background: linear-gradient(90deg, #7b2cbf, #5a189a); }
        .sidebar { width: 250px; background: linear-gradient(180deg, #7b2cbf, #5a189a); position: fixed; height: 100vh; overflow-y: auto; transition: transform 0.3s ease; z-index: 1000; }
        .sidebar.hidden { transform: translateX(-100%); }
        .sidebar-link { transition: all 0.3s ease; }
        .sidebar-link:hover { background: rgba(255, 255, 255, 0.1); padding-left: 1.5rem; }
        .sidebar-link.active { background: rgba(255, 255, 255, 0.2); border-left: 4px solid white; }
        .main-content { margin-left: 250px; transition: margin-left 0.3s ease; }
        .main-content.expanded { margin-left: 0; }
        .card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .card:hover { transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15); }
        .teacher-img { width: 150px; height: 150px; object-fit: cover; border-radius: 50%; }
        .mobile-toggle { display: none; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-toggle { display: block; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="p-6">
            <div class="flex items-center mb-8">
                <i class="fas fa-graduation-cap text-white text-3xl mr-3"></i>
                <h2 class="text-white text-xl font-bold">GCA Portal</h2>
            </div>
            <nav>
                <a href="teacher_dashboard.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-home mr-3"></i> Dashboard
                </a>
                <a href="manage_teacher_courses.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-chalkboard-teacher mr-3"></i> Manage Courses
                </a>
                <a href="upload_materials.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-book mr-3"></i> Upload Materials
                </a>
                <a href="grades.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-clipboard-check mr-3"></i> Grade
                </a>
                <a href="mark_attendance.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-calendar-check mr-3"></i> Mark Attendance
                </a>
                <a href="message_students.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-envelope mr-3"></i> Message Students
                </a>
                <a href="teacher_profile.php" class="sidebar-link flex active items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-user mr-3"></i> Profile
                </a>
                <a href="logout.php" class="sidebar-link flex items-center text-white py-3 px-4 rounded mb-2">
                    <i class="fas fa-sign-out-alt mr-3"></i> Logout
                </a>
            </nav>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Header -->
        <header class="gradient-header text-white py-4 px-6 flex justify-between items-center">
            <button class="mobile-toggle text-white text-2xl" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 class="text-xl font-semibold">Profile</h1>
                <p class="text-sm">Manage your profile information.</p>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="p-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Profile Image and Basic Info -->
                <div class="lg:col-span-1 card bg-white rounded-lg shadow-lg p-6 text-center">
                    <img src="<?= htmlspecialchars($teacherInfo['photo'] ?: 'default-teacher.jpg') ?>" alt="<?= htmlspecialchars($teacherInfo['username']) ?>" class="teacher-img mx-auto mb-4 border-4 border-purple-200">
                    <h2 class="text-2xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($teacherInfo['username']) ?></h2>
                    <p class="text-gray-600 mb-4">Subject Speciality: <?= htmlspecialchars($teacherInfo['subject_speciality'] ?? 'N/A') ?></p>
                    <div class="space-y-2 text-sm text-gray-600">
                        <p><i class="fas fa-envelope mr-2"></i><?= htmlspecialchars($teacherInfo['email']) ?></p>
                        <p><i class="fas fa-phone mr-2"></i><?= htmlspecialchars($teacherInfo['phone']) ?></p>
                        <p><i class="fas fa-venus-mars mr-2"></i><?= htmlspecialchars(ucfirst($teacherInfo['gender'] ?? 'N/A')) ?></p>
                        <p><i class="fas fa-calendar mr-2"></i><?= htmlspecialchars($teacherInfo['dob'] ?? 'N/A') ?></p>
                        <p><i class="fas fa-id-card mr-2"></i><?= htmlspecialchars($teacherInfo['IDNumber'] ?? 'N/A') ?></p>
                    </div>
                </div>

                <!-- Edit Profile Form -->
                <div class="lg:col-span-2 card bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Edit Profile</h3>
                    <form action="update_teacher_profile.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="user_id" value="<?= $user_id ?>">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                                <input type="text" name="username" value="<?= htmlspecialchars($teacherInfo['username']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($teacherInfo['email']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input type="tel" name="phone" value="<?= htmlspecialchars($teacherInfo['phone']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                                <select name="gender" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?= $teacherInfo['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= $teacherInfo['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= $teacherInfo['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subject Speciality</label>
                            <input type="text" name="subject_speciality" value="<?= htmlspecialchars($teacherInfo['subject_speciality'] ?? '') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Profile Photo</label>
                            <input type="file" name="photo" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <textarea name="address" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"><?= htmlspecialchars(($teacherInfo['address1'] ?? '') . ' ' . ($teacherInfo['streetName'] ?? '') . ', ' . ($teacherInfo['district'] ?? '') . ', ' . ($teacherInfo['country'] ?? '')) ?></textarea>
                        </div>
                        <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-md transition duration-200">Update Profile</button>
                    </form>
                </div>
            </div>

            <!-- Assigned Batches -->
            <div class="card bg-white rounded-lg shadow-lg p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Assigned Batches</h3>
                <?php if (empty($assignedBatches)): ?>
                    <p class="text-gray-600">No batches assigned yet.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full table-auto">
                            <thead class="bg-gray-200">
                                <tr>
                                    <th class="px-4 py-2 text-left">Batch Code</th>
                                    <th class="px-4 py-2 text-left">Course</th>
                                    <th class="px-4 py-2 text-left">Start Date</th>
                                    <th class="px-4 py-2 text-left">End Date</th>
                                    <th class="px-4 py-2 text-left">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignedBatches as $batch): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2"><?= htmlspecialchars($batch['batch_code']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($batch['courseName']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($batch['start_date']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($batch['end_date']) ?></td>
                                    <td class="px-4 py-2">
                                        <span class="inline-block px-2 py-1 rounded-full text-sm font-medium <?= $batch['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                            <?= htmlspecialchars($batch['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('mobile-open');
            
            // Close sidebar when clicking outside on mobile
            if (sidebar.classList.contains('mobile-open')) {
                document.addEventListener('click', closeSidebarOnClickOutside);
            } else {
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        }

        function closeSidebarOnClickOutside(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = event.target.closest('.mobile-toggle');
            
            if (!sidebar.contains(event.target) && !toggleBtn) {
                sidebar.classList.remove('mobile-open');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        }
    </script>
</body>
</html>