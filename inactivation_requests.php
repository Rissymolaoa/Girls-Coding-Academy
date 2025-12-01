<?php
session_start();

// Check login & role
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
if ($_SESSION['role'] !== 'admin') {
    echo "<h2>Access Denied! Only admins can view this page.</h2>";
    exit();
}

// ========== DATABASE CONNECTION ==========
$host = "localhost";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb";

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) throw new Exception("Connection failed: " . $conn->connect_error);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = "";

// ================== HANDLE APPROVE / REJECT ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'approve') {
        $request_id    = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
        $enrollment_id = filter_input(INPUT_POST, 'enrollment_id', FILTER_VALIDATE_INT);

        if ($request_id && $enrollment_id) {
            try {
                $conn->autocommit(false);
                $conn->begin_transaction();

                // Inactivate enrollment
                $stmt = $conn->prepare("UPDATE course_enrollments SET status = 'inactive' WHERE enrollment_id = ?");
                $stmt->bind_param("i", $enrollment_id);
                $stmt->execute();
                $stmt->close();

                // Approve request
                $admin_id = (int)$_SESSION['user_id'];
                $stmt = $conn->prepare("
                    UPDATE inactivation_requests 
                    SET status = 'approved', 
                        processed_by = ?, 
                        processed_at = NOW() 
                    WHERE request_id = ?
                ");
                $stmt->bind_param("ii", $admin_id, $request_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                $conn->autocommit(true);
                $message = "<div class='alert alert-success alert-dismissible fade show'>
                    Inactivation approved successfully!
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>";
            } catch (Exception $e) {
                $conn->rollback();
                $conn->autocommit(true);
                $message = "<div class='alert alert-danger alert-dismissible fade show'>
                    Error approving: " . htmlspecialchars($e->getMessage()) . "
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>";
            }
        }
    }

    elseif ($action === 'reject') {
        $request_id       = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
        $rejection_reason = trim($_POST['rejection_reason'] ?? '');

        if ($request_id && !empty($rejection_reason)) {
            try {
                $admin_id = (int)$_SESSION['user_id'];
                $stmt = $conn->prepare("
                    UPDATE inactivation_requests 
                    SET status = 'rejected',
                        rejection_reason = ?,
                        processed_by = ?,
                        processed_at = NOW()
                    WHERE request_id = ?
                ");
                $stmt->bind_param("sii", $rejection_reason, $admin_id, $request_id);
                $stmt->execute();
                $stmt->close();

                $message = "<div class='alert alert-info alert-dismissible fade show'>
                    Request rejected successfully.
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>";
            } catch (Exception $e) {
                $message = "<div class='alert alert-danger alert-dismissible fade show'>
                    Error rejecting request.
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>";
            }
        } else {
            $message = "<div class='alert alert-warning alert-dismissible fade show'>
                Rejection reason is required!
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        }
    }
}

// ================== COUNTS ==================
$pending_count = $approved_count = $rejected_count = 0;
$result = $conn->query("SELECT status, COUNT(*) AS cnt FROM inactivation_requests GROUP BY status");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        switch ($row['status']) {
            case 'pending':   $pending_count   = $row['cnt']; break;
            case 'approved':  $approved_count  = $row['cnt']; break;
            case 'rejected':  $rejected_count  = $row['cnt']; break;
        }
    }
}

// ================== FILTER ==================
$filter = in_array($_GET['filter'] ?? '', ['pending','approved','rejected']) ? $_GET['filter'] : 'pending';

// ================== FETCH REQUESTS ==================
$requests = [];
$stmt = $conn->prepare("
    SELECT ir.*, u.firstName, u.lastName 
    FROM inactivation_requests ir
    LEFT JOIN users u ON ir.processed_by = u.user_id
    WHERE ir.status = ?
    ORDER BY ir.created_at DESC
");
$stmt->bind_param("s", $filter);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}
$stmt->close();
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inactivation Requests - Girls Coding Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #667eea; --secondary: #764ba2; --success: #4facfe; --danger: #fa709a; --warning: #ffa502; --border: #e2e8f0; }
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; padding-top: 60px; color: #2d3748; }
        .content { padding: 2rem; }
        .header-section { background: white; padding: 2.5rem 2rem; border-radius: 20px; margin-bottom: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.08); display: flex; justify-content: space-between; flex-wrap: wrap; gap: 2rem; }
        .header-content h1 { font-size: 2.5rem; font-weight: 800; background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat-badge { padding: 1rem 1.5rem; background: rgba(102,126,234,0.1); border-radius: 12px; border-left: 4px solid var(--primary); text-align: center; }
        .stat-badge strong { font-size: 1.8rem; color: var(--primary); display: block; }
        .stat-badge.warning { border-left-color: var(--warning); } .stat-badge.warning strong { color: var(--warning); }
        .stat-badge.success { border-left-color: var(--success); } .stat-badge.success strong { color: var(--success); }
        .stat-badge.danger { border-left-color: var(--danger); } .stat-badge.danger strong { color: var(--danger); }
        .section-card { background: white; border-radius: 16px; padding: 2.5rem; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid var(--border); }
        .filter-tabs { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
        .filter-btn { padding: 0.75rem 1.5rem; border: 2px solid var(--border); background: white; border-radius: 10px; font-weight: 600; color: #4a5568; }
        .filter-btn:hover { border-color: var(--primary); color: var(--primary); }
        .filter-btn.active { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-color: var(--primary); }
        .request-card { background: rgba(102,126,234,0.05); border: 2px solid var(--border); border-radius: 14px; padding: 1.5rem; margin-bottom: 1.5rem; }
        .request-card:hover { border-color: var(--primary); box-shadow: 0 8px 20px rgba(102,126,234,0.15); }
        .status-badge { padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
        .status-badge.pending { background: rgba(255,165,2,0.15); color: var(--warning); }
        .status-badge.approved { background: rgba(79,172,254,0.15); color: var(--success); }
        .status-badge.rejected { background: rgba(250,112,154,0.15); color: var(--danger); }
        .btn-approve, .btn-reject { padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; font-size: 0.9rem; }
        .btn-approve { background: linear-gradient(135deg, var(--success), #00d4ff); color: white; border: none; }
        .btn-reject { background: rgba(250,112,154,0.1); color: var(--danger); border: 2px solid var(--danger); }
        .btn-reject:hover { background: var(--danger); color: white; }
        .empty-state { text-align: center; padding: 3rem; color: #718096; }
        .empty-state i { font-size: 3rem; color: #cbd5e0; margin-bottom: 1rem; }
    </style>
</head>
<body>
<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="content">
    <div class="header-section">
        <div class="header-content">
            <h1>Disciplinary Requests</h1>
            <p>Review and process student inactivation requests from teachers</p>
        </div>
        <div class="d-flex gap-3 flex-wrap">
            <div class="stat-badge"><strong><?= $pending_count ?></strong><span>Pending</span></div>
            <div class="stat-badge success"><strong><?= $approved_count ?></strong><span>Approved</span></div>
            <div class="stat-badge danger"><strong><?= $rejected_count ?></strong><span>Rejected</span></div>
        </div>
    </div>

    <?= $message ?>

    <div class="section-card">
        <div class="filter-tabs">
            <a href="?filter=pending" class="filter-btn <?= $filter==='pending'?'active':'' ?>">Pending</a>
            <a href="?filter=approved" class="filter-btn <?= $filter==='approved'?'active':'' ?>">Approved</a>
            <a href="?filter=rejected" class="filter-btn <?= $filter==='rejected'?'active':'' ?>">Rejected</a>
        </div>

        <?php if (!empty($requests)): ?>
            <?php foreach ($requests as $req): ?>
                <div class="request-card">
                    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
                        <div>
                            <h4 class="mb-1"><?= htmlspecialchars($req['student_name']) ?></h4>
                            <p class="text-muted mb-1"><?= htmlspecialchars($req['student_email']) ?></p>
                            <p class="mb-0">Batch: <strong><?= htmlspecialchars($req['batch_code']) ?></strong></p>
                        </div>
                        <span class="status-badge <?= $req['status'] ?>"><?= ucfirst($req['status']) ?></span>
                    </div>

                    <div class="p-3 bg-white rounded border-start border-primary border-4 mb-3">
                        <strong>Teacher's Reason:</strong>
                        <p class="mt-2 mb-0"><?= nl2br(htmlspecialchars($req['reason'])) ?></p>
                    </div>

                    <?php if ($req['status'] === 'rejected' && !empty($req['rejection_reason'])): ?>
                        <div class="p-3 bg-white rounded border-start border-danger border-4 mb-3">
                            <strong>Rejection Reason:</strong>
                            <p class="mt-2 mb-0"><?= nl2br(htmlspecialchars($req['rejection_reason'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top flex-wrap gap-3">
                        <small class="text-muted">
                            <?php if ($req['status'] === 'pending'): ?>
                                Requested: <?= date('M d, Y H:i', strtotime($req['created_at'])) ?>
                            <?php else: ?>
                                Processed: <?= date('M d, Y H:i', strtotime($req['processed_at'] ?? $req['created_at'])) ?>
                                <?php if (!empty($req['firstName'])): ?>
                                    by <strong><?= htmlspecialchars($req['firstName'] . ' ' . $req['lastName']) ?></strong>
                                <?php endif; ?>
                            <?php endif; ?>
                        </small>

                        <?php if ($req['status'] === 'pending'): ?>
                            <div class="d-flex gap-2">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
                                    <input type="hidden" name="enrollment_id" value="<?= $req['enrollment_id'] ?>">
                                    <button type="submit" class="btn-approve" onclick="return confirm('Approve inactivation?')">
                                        Approve
                                    </button>
                                </form>
                                <button type="button" class="btn-reject" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $req['request_id'] ?>">
                                    Reject
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Reject Modal -->
                <div class="modal fade" id="rejectModal<?= $req['request_id'] ?>">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title">Reject Request</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <p>Rejecting request for: <strong><?= htmlspecialchars($req['student_name']) ?></strong></p>
                                    <div class="mb-3">
                                        <label class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                                        <textarea class="form-control" name="rejection_reason" rows="4" required placeholder="Explain why this request is being rejected..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
                                    <button type="submit" class="btn btn-danger">Reject Request</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <h5>No <?= ucfirst($filter) ?> Requests</h5>
                <p>There are no <?= $filter ?> inactivation requests at this time.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>