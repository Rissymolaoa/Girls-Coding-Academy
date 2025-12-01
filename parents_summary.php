<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}
require_once 'db.php';

// Summary stats
$total_parents = $conn->query("SELECT COUNT(*) FROM users WHERE role='parent'")->fetch_row()[0];
$total_relations = $conn->query("SELECT COUNT(*) FROM parent_students")->fetch_row()[0];
$total_students = $conn->query("SELECT COUNT(DISTINCT student_id) FROM parent_students")->fetch_row()[0];

// Recent parents with linked students
$parents_sql = "
    SELECT u.user_id, u.firstName, u.lastName, u.gender, u.phone, u.email,
           su.firstName AS sFirst, su.lastName AS sLast, s.photo AS studentPhoto
    FROM users u
    JOIN parents p ON p.user_id = u.user_id
    LEFT JOIN parent_students ps ON ps.parent_id = p.parent_id
    LEFT JOIN students s ON ps.student_id = s.student_id
    LEFT JOIN users su ON s.user_id = su.user_id
    WHERE u.role = 'parent'
    ORDER BY u.created_at DESC
    LIMIT 6
";
$parents = $conn->query($parents_sql);
$parents_data = $parents->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parents Dashboard - Girls Coding Academy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: #4f46e5; --primary-light: #6366f1; --primary-dark: #4338ca; }
        .bg-primary { background-color: var(--primary); }
        .text-primary { color: var(--primary); }
        .hover\:bg-primary-dark:hover { background-color: var(--primary-dark); }
        .card-hover:hover { transform: translateY(-8px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .avatar { @apply w-16 h-16 rounded-full object-cover border-4 border-white shadow-lg; }
    </style>
</head>
<body class="h-full bg-gray-50">

<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="ml-64 mt-16 min-h-screen">
    <div class="p-8 max-w-7xl mx-auto">

        <!-- Header -->
        <div class="mb-10">
            <h1 class="text-4xl font-bold text-gray-800">Parents & Guardians</h1>
            <p class="text-xl text-gray-600 mt-3">Overview of parent accounts and student relationships</p>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-10">
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8 card-hover transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Parents</p>
                        <p class="text-5xl font-bold text-gray-800 mt-4"><?= $total_parents ?></p>
                    </div>
                    <div class="w-20 h-20 bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-user-tie text-4xl text-white"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8 card-hover transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Active Relations</p>
                        <p class="text-5xl font-bold text-indigo-600 mt-4"><?= $total_relations ?></p>
                    </div>
                    <div class="w-20 h-20 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-link text-4xl text-white"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8 card-hover transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Linked Students</p>
                        <p class="text-5xl font-bold text-green-600 mt-4"><?= $total_students ?></p>
                    </div>
                    <div class="w-20 h-20 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-user-graduate text-4xl text-white"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Recent Parents Table -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white p-6">
                        <div class="flex justify-between items-center">
                            <h2 class="text-2xl font-bold">Recent Parents</h2>
                            <a href="manage_parents.php" class="text-indigo-100 hover:text-white underline text-sm font-medium">
                                View All Parents
                            </a>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Parent</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Gender</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Contact</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Linked Student</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($parents_data as $p): ?>
                                    <tr class="border-b hover:bg-gray-50 transition">
                                        <td class="px-6 py-5">
                                            <div class="font-medium text-gray-800">
                                                <?= htmlspecialchars($p['firstName'] . ' ' . $p['lastName']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-5 text-gray-600">
                                            <?= ucfirst($p['gender'] ?? '—') ?>
                                        </td>
                                        <td class="px-6 py-5">
                                            <div class="text-sm">
                                                <div class="text-gray-600"><?= htmlspecialchars($p['phone'] ?? '—') ?></div>
                                                <div class="text-indigo-600"><?= htmlspecialchars($p['email']) ?></div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-5">
                                            <?php if ($p['sFirst']): ?>
                                                <span class="inline-flex items-center gap-2">
                                                    <?php if ($p['studentPhoto'] && file_exists($p['studentPhoto'])): ?>
                                                        <img src="<?= htmlspecialchars($p['studentPhoto']) ?>" class="w-10 h-10 rounded-full object-cover border-2 border-indigo-200">
                                                    <?php else: ?>
                                                        <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 font-bold text-sm">
                                                            <?= strtoupper(substr($p['sFirst'],0,1).substr($p['sLast'],0,1)) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <span class="font-medium text-gray-800">
                                                        <?= htmlspecialchars($p['sFirst'] . ' ' . $p['sLast']) ?>
                                                    </span>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-400 italic">No student linked</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Student Photos Grid -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Recently Linked Students</h2>
                    <div class="grid grid-cols-2 gap-6">
                        <?php 
                        $displayed = 0;
                        foreach ($parents_data as $p): 
                            if ($p['studentPhoto'] && file_exists($p['studentPhoto']) && $displayed < 6):
                        ?>
                            <div class="text-center card-hover transition-all">
                                <img src="<?= htmlspecialchars($p['studentPhoto']) ?>" 
                                     class="w-24 h-24 rounded-full mx-auto object-cover border-4 border-indigo-100 shadow-lg hover:border-indigo-400 transition">
                                <p class="mt-3 font-medium text-gray-800 text-sm">
                                    <?= htmlspecialchars($p['sFirst'] . ' ' . $p['sLast']) ?>
                                </p>
                            </div>
                        <?php 
                                $displayed++;
                            endif;
                        endforeach; 
                        // Fill empty slots if needed
                        while ($displayed < 6):
                            $displayed++;
                        ?>
                            <div class="text-center opacity-50">
                                <div class="w-24 h-24 bg-gray-100 rounded-full mx-auto border-4 border-dashed border-gray-300 flex items-center justify-center">
                                    <i class="fas fa-user text-3xl text-gray-400"></i>
                                </div>
                                <p class="mt-3 text-sm text-gray-500">No photo</p>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-10 flex justify-center gap-6">
            <a href="manage_parents.php" class="bg-white border-2 border-indigo-600 text-indigo-600 px-10 py-5 rounded-2xl font-bold text-lg hover:bg-indigo-50 transition shadow-lg flex items-center gap-4">
                <i class="fas fa-users-cog text-2xl"></i>
                Manage All Parents
            </a>
            <a href="assign_parent_student.php" class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white px-10 py-5 rounded-2xl font-bold text-lg hover:shadow-2xl transition flex items-center gap-4">
                <i class="fas fa-user-plus text-2xl"></i>
                Assign Student to Parent
            </a>
        </div>
    </div>
</div>
</body>
</html>