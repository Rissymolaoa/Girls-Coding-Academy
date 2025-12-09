<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

$success = $error = '';

// Restore item
// Permanent delete section - COMPREHENSIVE FIX
if (isset($_GET['permadelete']) && isset($_GET['type'])) {
    $id = intval($_GET['permadelete']);
    $type = $_GET['type'];

    $table = '';
    $id_col = '';
    
    switch ($type) {
        case 'user': 
            $table = 'users'; 
            $id_col = 'user_id'; 
            break;
        case 'course': 
            $table = 'courses'; 
            $id_col = 'course_id'; 
            break;
        case 'batch': 
            $table = 'batches'; 
            $id_col = 'batch_id'; 
            break;
        case 'activity': 
            $table = 'activities'; 
            $id_col = 'activity_id'; 
            break;
        case 'material': 
            $table = 'materials'; 
            $id_col = 'material_id'; 
            break;
    }

    if ($table && $id_col) {
        try {
            // Start transaction for data consistency
            $conn->begin_transaction();
            
            // For courses, delete all associated batches and their data
            if ($type === 'course') {
                $batch_result = $conn->query("SELECT batch_id FROM batches WHERE course_id = $id");
                while ($batch = $batch_result->fetch_assoc()) {
                    $bid = $batch['batch_id'];
                    // Delete child records for this batch
                    $conn->query("DELETE FROM activities WHERE batch_id = $bid");
                    $conn->query("DELETE FROM materials WHERE batch_id = $bid");
                    $conn->query("DELETE FROM activity_submissions WHERE activity_id IN (SELECT activity_id FROM activities WHERE batch_id = $bid)");
                    $conn->query("DELETE FROM tests WHERE batch_id = $bid");
                    $conn->query("DELETE FROM test_submissions WHERE test_id IN (SELECT test_id FROM tests WHERE batch_id = $bid)");
                    $conn->query("DELETE FROM course_enrollments WHERE batch_id = $bid");
                    $conn->query("DELETE FROM attendance WHERE batch_id = $bid");
                    $conn->query("DELETE FROM internal_grades WHERE batch_id = $bid");
                    $conn->query("DELETE FROM course_assignments WHERE batch_id = $bid");
                }
                // Delete all batches for this course
                $conn->query("DELETE FROM batches WHERE course_id = $id");
            }
            
            // For batches, delete all associated data
            if ($type === 'batch') {
                $conn->query("DELETE FROM activities WHERE batch_id = $id");
                $conn->query("DELETE FROM materials WHERE batch_id = $id");
                $conn->query("DELETE FROM activity_submissions WHERE activity_id IN (SELECT activity_id FROM activities WHERE batch_id = $id)");
                $conn->query("DELETE FROM tests WHERE batch_id = $id");
                $conn->query("DELETE FROM test_submissions WHERE test_id IN (SELECT test_id FROM tests WHERE batch_id = $id)");
                $conn->query("DELETE FROM course_enrollments WHERE batch_id = $id");
                $conn->query("DELETE FROM attendance WHERE batch_id = $id");
                $conn->query("DELETE FROM internal_grades WHERE batch_id = $id");
                $conn->query("DELETE FROM course_assignments WHERE batch_id = $id");
            }
            
            // For users, delete from role-specific tables and all related data
            if ($type === 'user') {
                // If teacher, delete all their activities, tests, materials, assignments
                $teacher_result = $conn->query("SELECT teacher_id FROM teachers WHERE user_id = $id");
                if ($teacher_result && $teacher_result->num_rows > 0) {
                    $teacher = $teacher_result->fetch_assoc();
                    $tid = $teacher['teacher_id'];
                    
                    // Get all activities by this teacher
                    $conn->query("DELETE FROM activity_submissions WHERE activity_id IN (SELECT activity_id FROM activities WHERE teacher_id = $tid)");
                    $conn->query("DELETE FROM activities WHERE teacher_id = $tid");
                    
                    // Get all materials by this teacher
                    $conn->query("DELETE FROM materials WHERE teacher_id = $tid");
                    
                    // Get all tests by this teacher
                    $conn->query("DELETE FROM test_submissions WHERE test_id IN (SELECT test_id FROM tests WHERE teacher_id = $tid)");
                    $conn->query("DELETE FROM tests WHERE teacher_id = $tid");
                    
                    // Delete course assignments
                    $conn->query("DELETE FROM course_assignments WHERE teacher_id = $tid");
                    
                    // Delete teacher batch assignments
                    $conn->query("DELETE FROM teacher_batches WHERE teacher_id = $tid");
                    
                    // Delete attendance records
                    $conn->query("DELETE FROM attendance WHERE marked_by = $tid");
                    
                    // Delete teacher record
                    $conn->query("DELETE FROM teachers WHERE user_id = $id");
                }
                
                // If student, delete all their enrollments, submissions, grades, attendance
                $student_result = $conn->query("SELECT student_id FROM students WHERE user_id = $id");
                if ($student_result && $student_result->num_rows > 0) {
                    $student = $student_result->fetch_assoc();
                    $sid = $student['student_id'];
                    
                    // Delete activity submissions
                    $conn->query("DELETE FROM activity_submissions WHERE enrollment_id IN (SELECT enrollment_id FROM course_enrollments WHERE student_id = $sid)");
                    
                    // Delete test submissions
                    $conn->query("DELETE FROM test_submissions WHERE student_id = $sid");
                    
                    // Delete enrollments
                    $conn->query("DELETE FROM course_enrollments WHERE student_id = $sid");
                    
                    // Delete attendance
                    $conn->query("DELETE FROM attendance WHERE student_id = $sid");
                    
                    // Delete grades
                    $conn->query("DELETE FROM internal_grades WHERE student_id = $sid");
                    
                    // Delete notifications
                    $conn->query("DELETE FROM notifications WHERE student_id = $sid");
                    
                    // Delete medical info
                    $conn->query("DELETE FROM student_medical_info WHERE student_id = $sid");
                    
                    // Delete transport info
                    $conn->query("DELETE FROM student_transport_info WHERE student_id = $sid");
                    
                    // Delete student messages
                    $conn->query("DELETE FROM student_messages WHERE student_id = $sid");
                    
                    // Delete course favorites
                    $conn->query("DELETE FROM course_favorites WHERE student_id = $sid");
                    
                    // Delete parent-student relationships
                    $conn->query("DELETE FROM parent_students WHERE student_id = $sid");
                    
                    // Delete student record
                    $conn->query("DELETE FROM students WHERE user_id = $id");
                }
                
                // If parent, delete parent-related data
                $parent_result = $conn->query("SELECT parent_id FROM parents WHERE user_id = $id");
                if ($parent_result && $parent_result->num_rows > 0) {
                    $parent = $parent_result->fetch_assoc();
                    $pid = $parent['parent_id'];
                    
                    // Delete parent-student relationships
                    $conn->query("DELETE FROM parent_students WHERE parent_id = $pid");
                    
                    // Delete parent messages
                    $conn->query("DELETE FROM parent_messages WHERE sender_user_id = $id OR recipient_user_id = $id");
                    
                    // Delete groupchat messages
                    $conn->query("DELETE FROM parents_groupchat_messages WHERE sender_user_id = $id");
                    
                    // Delete parent record
                    $conn->query("DELETE FROM parents WHERE user_id = $id");
                }
                
                // Delete messages where user is sender
                $conn->query("DELETE FROM messages WHERE sender_id = $id");
                
                // Delete temporary IDs
                $conn->query("DELETE FROM temporary_ids WHERE user_id = $id");
                
                // Delete user verifications
                $conn->query("DELETE FROM user_verifications WHERE user_id = $id");
                
                // Delete payments
                $conn->query("DELETE FROM payments WHERE payer_user_id = $id");
            }
            
            // For activities, delete submissions
            if ($type === 'activity') {
                $conn->query("DELETE FROM activity_submissions WHERE activity_id = $id");
            }
            
            // For materials - no dependencies typically
            
            // Delete from main table
            $stmt = $conn->prepare("DELETE FROM $table WHERE $id_col = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $conn->commit();
                $success = ucfirst($type) . " permanently deleted!";
            } else {
                $conn->rollback();
                $error = "Failed to delete " . $type . " permanently.";
            }
            
            $stmt->close();
            
            // Redirect to remove GET parameters
            header("Location: recycle_bin.php?success=" . urlencode($success));
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Handle success message from redirect
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Fetch deleted items with error handling
try {
    $deleted_users = $conn->query("SELECT user_id, username, firstName, lastName, email, role, deleted_at FROM users WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
    $deleted_courses = $conn->query("SELECT course_id, title, courseName, deleted_at FROM courses WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
    $deleted_batches = $conn->query("SELECT batch_id, batch_code, deleted_at FROM batches WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
    $deleted_activities = $conn->query("SELECT activity_id, title, deleted_at FROM activities WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
    $deleted_materials = $conn->query("SELECT material_id, title, deleted_at FROM materials WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
} catch (Exception $e) {
    $error = "Error fetching deleted items: " . $e->getMessage();
    $deleted_users = $deleted_courses = $deleted_batches = $deleted_activities = $deleted_materials = null;
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycle Bin - Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .main-content { margin-left: 220px; transition: margin-left 0.3s ease; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body class="h-full bg-gray-50">
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="main-content" style="padding-top: 80px;">
    <div class="p-8 min-h-screen">
        <div class="max-w-7xl mx-auto">
            <div class="mb-8">
                <h1 class="text-4xl font-bold text-gray-800 flex items-center gap-3">
                    <i class="fas fa-recycle text-blue-600"></i> Recycle Bin
                </h1>
                <p class="text-gray-600 mt-2">Deleted items are stored here. You can restore or permanently delete them.</p>
            </div>

            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-check-circle mr-3"></i><?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-exclamation-circle mr-3"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="border-b border-gray-200 mb-8">
                <nav class="flex space-x-8">
                    <button class="tab-btn py-4 px-1 border-b-2 border-blue-600 text-blue-600 font-semibold" data-tab="users">
                        Users (<?= $deleted_users ? $deleted_users->num_rows : 0 ?>)
                    </button>
                    <button class="tab-btn py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700" data-tab="courses">
                        Courses (<?= $deleted_courses ? $deleted_courses->num_rows : 0 ?>)
                    </button>
                    <button class="tab-btn py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700" data-tab="batches">
                        Batches (<?= $deleted_batches ? $deleted_batches->num_rows : 0 ?>)
                    </button>
                    <button class="tab-btn py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700" data-tab="activities">
                        Activities (<?= $deleted_activities ? $deleted_activities->num_rows : 0 ?>)
                    </button>
                    <button class="tab-btn py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700" data-tab="materials">
                        Materials (<?= $deleted_materials ? $deleted_materials->num_rows : 0 ?>)
                    </button>
                </nav>
            </div>

            <!-- Users Tab -->
            <div id="users" class="tab-content active">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Deleted Users</h2>
                <?php if (!$deleted_users || $deleted_users->num_rows == 0): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                        <i class="fas fa-user-slash text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No deleted users.</p>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Name</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Username</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Email</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Role</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Deleted At</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($u = $deleted_users->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4"><?= htmlspecialchars($u['firstName'] . ' ' . $u['lastName']) ?></td>
                                    <td class="px-6 py-4 text-gray-600">@<?= htmlspecialchars($u['username']) ?></td>
                                    <td class="px-6 py-4"><?= htmlspecialchars($u['email']) ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-800">
                                            <?= ucfirst(htmlspecialchars($u['role'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500"><?= date('d M Y, H:i', strtotime($u['deleted_at'])) ?></td>
                                    <td class="px-6 py-4">
                                        <a href="recycle_bin.php?restore=<?= $u['user_id'] ?>&type=user" 
                                           class="text-green-600 hover:text-green-800 mr-4 font-medium" 
                                           onclick="return confirm('Are you sure you want to restore this user?')">
                                            <i class="fas fa-undo mr-1"></i>Restore
                                        </a>
                                        <a href="recycle_bin.php?permadelete=<?= $u['user_id'] ?>&type=user" 
                                           class="text-red-600 hover:text-red-800 font-medium" 
                                           onclick="return confirm('⚠️ PERMANENTLY DELETE this user?\n\nThis action CANNOT be undone!\n\nAll related data will be lost forever.')">
                                            <i class="fas fa-trash-alt mr-1"></i>Delete Forever
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Courses Tab -->
            <div id="courses" class="tab-content">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Deleted Courses</h2>
                <?php if (!$deleted_courses || $deleted_courses->num_rows == 0): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                        <i class="fas fa-book text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No deleted courses.</p>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Title</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Course Name</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Deleted At</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($c = $deleted_courses->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4"><?= htmlspecialchars($c['title']) ?></td>
                                    <td class="px-6 py-4"><?= htmlspecialchars($c['courseName']) ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-500"><?= date('d M Y, H:i', strtotime($c['deleted_at'])) ?></td>
                                    <td class="px-6 py-4">
                                        <a href="recycle_bin.php?restore=<?= $c['course_id'] ?>&type=course" 
                                           class="text-green-600 hover:text-green-800 mr-4 font-medium" 
                                           onclick="return confirm('Restore this course?')">
                                            <i class="fas fa-undo mr-1"></i>Restore
                                        </a>
                                        <a href="recycle_bin.php?permadelete=<?= $c['course_id'] ?>&type=course" 
                                           class="text-red-600 hover:text-red-800 font-medium" 
                                           onclick="return confirm('⚠️ PERMANENTLY DELETE this course? This cannot be undone!')">
                                            <i class="fas fa-trash-alt mr-1"></i>Delete Forever
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Batches Tab -->
            <div id="batches" class="tab-content">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Deleted Batches</h2>
                <?php if (!$deleted_batches || $deleted_batches->num_rows == 0): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                        <i class="fas fa-layer-group text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No deleted batches.</p>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Batch Code</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Deleted At</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($b = $deleted_batches->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4"><?= htmlspecialchars($b['batch_code']) ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-500"><?= date('d M Y, H:i', strtotime($b['deleted_at'])) ?></td>
                                    <td class="px-6 py-4">
                                        <a href="recycle_bin.php?restore=<?= $b['batch_id'] ?>&type=batch" 
                                           class="text-green-600 hover:text-green-800 mr-4 font-medium" 
                                           onclick="return confirm('Restore this batch?')">
                                            <i class="fas fa-undo mr-1"></i>Restore
                                        </a>
                                        <a href="recycle_bin.php?permadelete=<?= $b['batch_id'] ?>&type=batch" 
                                           class="text-red-600 hover:text-red-800 font-medium" 
                                           onclick="return confirm('⚠️ PERMANENTLY DELETE this batch? This cannot be undone!')">
                                            <i class="fas fa-trash-alt mr-1"></i>Delete Forever
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Activities Tab -->
            <div id="activities" class="tab-content">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Deleted Activities</h2>
                <?php if (!$deleted_activities || $deleted_activities->num_rows == 0): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                        <i class="fas fa-tasks text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No deleted activities.</p>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Title</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Deleted At</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($a = $deleted_activities->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4"><?= htmlspecialchars($a['title']) ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-500"><?= date('d M Y, H:i', strtotime($a['deleted_at'])) ?></td>
                                    <td class="px-6 py-4">
                                        <a href="recycle_bin.php?restore=<?= $a['activity_id'] ?>&type=activity" 
                                           class="text-green-600 hover:text-green-800 mr-4 font-medium" 
                                           onclick="return confirm('Restore this activity?')">
                                            <i class="fas fa-undo mr-1"></i>Restore
                                        </a>
                                        <a href="recycle_bin.php?permadelete=<?= $a['activity_id'] ?>&type=activity" 
                                           class="text-red-600 hover:text-red-800 font-medium" 
                                           onclick="return confirm('⚠️ PERMANENTLY DELETE this activity? This cannot be undone!')">
                                            <i class="fas fa-trash-alt mr-1"></i>Delete Forever
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Materials Tab -->
            <div id="materials" class="tab-content">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Deleted Materials</h2>
                <?php if (!$deleted_materials || $deleted_materials->num_rows == 0): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                        <i class="fas fa-file-alt text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No deleted materials.</p>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Title</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Deleted At</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($m = $deleted_materials->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4"><?= htmlspecialchars($m['title']) ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-500"><?= date('d M Y, H:i', strtotime($m['deleted_at'])) ?></td>
                                    <td class="px-6 py-4">
                                        <a href="recycle_bin.php?restore=<?= $m['material_id'] ?>&type=material" 
                                           class="text-green-600 hover:text-green-800 mr-4 font-medium" 
                                           onclick="return confirm('Restore this material?')">
                                            <i class="fas fa-undo mr-1"></i>Restore
                                        </a>
                                        <a href="recycle_bin.php?permadelete=<?= $m['material_id'] ?>&type=material" 
                                           class="text-red-600 hover:text-red-800 font-medium" 
                                           onclick="return confirm('⚠️ PERMANENTLY DELETE this material? This cannot be undone!')">
                                            <i class="fas fa-trash-alt mr-1"></i>Delete Forever
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="text-center py-12 mt-8">
                <i class="fas fa-recycle text-9xl text-gray-300 mb-6"></i>
                <p class="text-xl text-gray-600">This is your safety net. Nothing is lost until you say so.</p>
            </div>
        </div>
    </div>
</div>

<script>
// Tab functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            
            // Remove active classes from all tabs and contents
            tabButtons.forEach(btn => {
                btn.classList.remove('border-blue-600', 'text-blue-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });
            
            tabContents.forEach(content => {
                content.classList.remove('active');
            });
            
            // Add active classes to clicked tab
            this.classList.remove('border-transparent', 'text-gray-500');
            this.classList.add('border-blue-600', 'text-blue-600');
            
            // Show corresponding content
            document.getElementById(tabName).classList.add('active');
        });
    });
});
</script>
</body>
</html>