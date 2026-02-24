<?php
session_start();
require_once 'config.php'; // your PDO connection

// Only admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get admin name
$stmt = $pdo->prepare("SELECT firstName, lastName FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();
$adminName = $admin ? $admin['firstName'] . ' ' . $admin['lastName'] : 'Admin';

// Auto-add status column if missing
try {
    $pdo->query("ALTER TABLE course_inquiries
                 ADD COLUMN IF NOT EXISTS status ENUM('pending','replied') DEFAULT 'pending',
                 ADD COLUMN IF NOT EXISTS replied_at DATETIME NULL");
} catch (Exception $e) {
    // ignore – column already exists
}

// Mark as replied
if (isset($_POST['mark_replied'])) {
    $id = (int)$_POST['inquiry_id'];
    $pdo->prepare("UPDATE course_inquiries SET status = 'replied', replied_at = NOW() WHERE id = ?")
        ->execute([$id]);
    header("Location: admin_inquiries.php?success=1");
    exit();
}

// Search & filter
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';
$where = "WHERE 1=1";
$params = [];
if ($search !== '') {
    $where .= " AND (name LIKE ? OR email LIKE ? OR message LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
}
if ($statusFilter !== 'all') {
    $where .= " AND (ci.status = ? OR ci.status IS NULL)";
    $params[] = $statusFilter;
}

// Fetch inquiries safely
$stmt = $pdo->prepare("
    SELECT
        ci.id,
        ci.name,
        ci.email,
        ci.phone,
        ci.course_id,
        ci.message,
        ci.created_at,
        COALESCE(ci.status, 'pending') AS status,
        ci.replied_at,
        c.courseName
    FROM course_inquiries ci
    LEFT JOIN courses c ON ci.course_id = c.course_id
    $where
    ORDER BY ci.created_at DESC
");
$stmt->execute($params);
$inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" class="min-h-screen bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Inquiries • Girls Coding Academy</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
        }

        /* Loading Screen – White + Rotating Ring Only */
        #loading-screen {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            transition: opacity 0.8s ease, visibility 0.8s ease;
        }

        .loaded #loading-screen {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .logo-ring-container {
            position: relative;
            width: 110px;
            height: 110px;
        }

        .logo-ring-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            animation: pulse 2.8s infinite ease-in-out;
        }

        .rotating-ring {
            position: absolute;
            inset: -10px;
            border: 3px solid transparent;
            border-top-color: #3b82f6;
            border-right-color: #60a5fa;
            border-radius: 50%;
            animation: spin 7s linear infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.07); }
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }

        /* Page styles */
        .main-content {
            margin-left: 0;
            padding: 6rem 1.5rem 4rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        @media (min-width: 992px) {
            .main-content {
                margin-left: 280px;
            }
        }

        .card {
            border-radius: 1rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: none;
            overflow: hidden;
        }

        .table thead th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }

        .table-row:hover {
            background: #f0f9ff;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
            padding: 0.35rem 1rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .badge-replied {
            background: #d1fae5;
            color: #065f46;
            padding: 0.35rem 1rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .reply-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            padding: 0.6rem 1.25rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .reply-btn:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(59,130,246,0.25);
        }

        .inquiry-msg {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 0.75rem;
            border-left: 4px solid #3b82f6;
            font-size: 0.95rem;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
    </style>
</head>
<body class="transition-opacity duration-600">

<!-- Loading Screen – White background + small logo + rotating ring only -->
<div id="loading-screen">
    <div class="logo-ring-container">
        <img 
            src="imageuploads/logo.png" 
            alt="GCA Logo" 
            class="rounded-full"
            onerror="this.src='imageuploads/default_logo.png';"
        />
        <div class="rotating-ring"></div>
    </div>
</div>

<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="main-content">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Course Inquiries</h1>
                <p class="text-gray-600 mt-1">Manage and respond to website inquiries</p>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert-success px-6 py-3 rounded-lg font-medium flex items-center gap-2">
                    <i class="bi bi-check-circle-fill text-xl"></i>
                    Inquiry marked as replied successfully
                </div>
            <?php endif; ?>
        </div>

        <!-- Filter & Search -->
        <div class="card mb-8">
            <div class="p-6">
                <form class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-6">
                        <input 
                            type="text" 
                            name="search" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                            placeholder="Search by name, email or message..." 
                            value="<?= htmlspecialchars($search) ?>"
                        >
                    </div>
                    <div class="md:col-span-3">
                        <select name="status" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                            <option value="all">All Status</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="replied" <?= $statusFilter === 'replied' ? 'selected' : '' ?>>Replied</option>
                        </select>
                    </div>
                    <div class="md:col-span-3 flex gap-3">
                        <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 px-6 rounded-lg transition">
                            Search
                        </button>
                        <a href="admin_inquiries.php" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium py-3 px-6 rounded-lg transition text-center">
                            Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Inquiries Table -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
                        <tr>
                            <th class="px-6 py-4">Date</th>
                            <th class="px-6 py-4">Name</th>
                            <th class="px-6 py-4">Contact</th>
                            <th class="px-6 py-4">Course</th>
                            <th class="px-6 py-4">Message</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($inquiries)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-16 text-gray-500">
                                    <i class="bi bi-inbox text-5xl block mb-4 opacity-40"></i>
                                    No inquiries found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($inquiries as $inq): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-5 whitespace-nowrap text-gray-600">
                                        <?= date('d M Y · H:i', strtotime($inq['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-5 font-medium text-gray-900">
                                        <?= htmlspecialchars($inq['name']) ?>
                                    </td>
                                    <td class="px-6 py-5">
                                        <?= htmlspecialchars($inq['email']) ?><br>
                                        <span class="text-gray-500 text-xs"><?= htmlspecialchars($inq['phone'] ?? '—') ?></span>
                                    </td>
                                    <td class="px-6 py-5">
                                        <?= $inq['courseName'] ? htmlspecialchars($inq['courseName']) : '<span class="text-gray-500 italic">General</span>' ?>
                                    </td>
                                    <td class="px-6 py-5 max-w-md">
                                        <div class="inquiry-msg">
                                            <?= nl2br(htmlspecialchars($inq['message'])) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <?php if ($inq['status'] === 'replied'): ?>
                                            <span class="badge-replied">Replied</span>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <?= date('d M Y · H:i', strtotime($inq['replied_at'])) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge-pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-5 text-right whitespace-nowrap">
                                        <?php if ($inq['status'] !== 'replied'): ?>
                                            <form method="POST" class="inline-block mb-2">
                                                <input type="hidden" name="inquiry_id" value="<?= $inq['id'] ?>">
                                                <button name="mark_replied" class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                                                    Mark Replied
                                                </button>
                                            </form>
                                            <br>
                                        <?php endif; ?>
                                        <a href="mailto:<?= urlencode($inq['email']) ?>?subject=Re:%20Girls%20Coding%20Academy%20Inquiry&body=Hi%20<?= urlencode($inq['name']) ?>,%0A%0AThank%20you%20for%20your%20interest...%0A%0A"
                                           class="reply-btn text-sm inline-flex items-center gap-2" target="_blank">
                                            <i class="bi bi-envelope-fill"></i>
                                            Reply
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Hide loading screen when ready -->
<script>
    window.addEventListener('load', () => {
        document.body.classList.add('loaded');
    });

    // Safety fallback
    setTimeout(() => {
        document.body.classList.add('loaded');
    }, 5000);
</script>

</body>
</html>