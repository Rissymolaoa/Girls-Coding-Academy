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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Course Inquiries</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f6f9; margin:0; }
        .main-content { margin-left: 280px; padding: 100px 30px 50px; min-height: 100vh; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding-top: 90px; } }
        .card { border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .badge-pending { background:#fff7ed; color:#c2410c; padding:0.5em 1em; border-radius:50px; }
        .badge-replied { background:#ecfdf5; color:#065f46; padding:0.5em 1em; border-radius:50px; }
        .inquiry-msg { background:#f8fafc; padding:1rem; border-radius:12px; border-left:4px solid #6366f1; }
        .reply-btn {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white; border: none; padding: 0.6rem 1.2rem;
            border-radius: 10px; font-weight: 600; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .reply-btn:hover { background:#4f46e5; color:white; }
    </style>
</head>
<body>

<?php include 'admin_navigation.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <h2 class="h3 fw-bold mb-4">Course Inquiries from Website</h2>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                Inquiry marked as replied successfully!
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <form class="row g-3">
                    <div class="col-md-6">
                        <input type="text" name="search" class="form-control" placeholder="Search name, email, message..." value="<?=htmlspecialchars($search)?>">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="all">All Status</option>
                            <option value="pending" <?= $statusFilter==='pending'?'selected':'' ?>>Pending</option>
                            <option value="replied" <?= $statusFilter==='replied'?'selected':'' ?>>Replied</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="admin_inquiries.php" class="btn btn-outline-secondary ms-2">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Course</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inquiries)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    No inquiries found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($inquiries as $inq): ?>
                            <tr>
                                <td><?= date('d M Y H:i', strtotime($inq['created_at'])) ?></td>
                                <td><strong><?= htmlspecialchars($inq['name']) ?></strong></td>
                                <td>
                                    <?= htmlspecialchars($inq['email']) ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($inq['phone'] ?? '—') ?></small>
                                </td>
                                <td><?= $inq['courseName'] ? htmlspecialchars($inq['courseName']) : 'General Inquiry' ?></td>
                                <td><div class="inquiry-msg"><?= nl2br(htmlspecialchars($inq['message'])) ?></div></td>
                                <td>
                                    <?php if ($inq['status'] === 'replied'): ?>
                                        <span class="badge badge-replied">Replied</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($inq['status'] !== 'replied'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="inquiry_id" value="<?= $inq['id'] ?>">
                                            <button name="mark_replied" class="btn btn-sm btn-success">Mark Replied</button>
                                        </form>
                                        <br>
                                    <?php endif; ?>
                                    <a href="mailto:<?= urlencode($inq['email']) ?>?subject=Re:%20Girls%20Coding%20Academy&body=Hi%20<?= urlencode($inq['name']) ?>,%0A%0AThank%20you..."
                                       class="reply-btn btn-sm d-block mt-2" target="_blank">
                                        Reply via Email
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>