<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// Initialize message variables to prevent undefined warnings
$message = '';
$success_message = '';
$error_message = '';

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
                $success_message = "Inactivation approved successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $conn->autocommit(true);
                $error_message = "Error approving: " . htmlspecialchars($e->getMessage());
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
                $success_message = "Request rejected successfully.";
            } catch (Exception $e) {
                $error_message = "Error rejecting request.";
            }
        } else {
            $error_message = "Rejection reason is required!";
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --gray: #6b7280;
            --light: #f8fafc;
            --border: #e2e8f0;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            padding-top: 60px;
            color: #1f2937;
        }
        .content {
            padding: 2rem;
        }
        .header-section {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .header-content h1 {
            font-size: 2.25rem;
            font-weight: 700;
            margin: 0;
            color: var(--primary);
        }
        .stat-badge {
            padding: 1rem 1.5rem;
            background: rgba(99,102,241,0.08);
            border-radius: 12px;
            border-left: 4px solid var(--primary);
            text-align: center;
            min-width: 140px;
        }
        .stat-badge strong {
            font-size: 1.75rem;
            color: var(--primary);
            display: block;
        }
        .stat-badge.approved { border-left-color: var(--success); }
        .stat-badge.approved strong { color: var(--success); }
        .stat-badge.rejected { border-left-color: var(--danger); }
        .stat-badge.rejected strong { color: var(--danger); }
        .section-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid var(--border);
        }
        .filter-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 0.75rem 1.5rem;
            border: 2px solid var(--border);
            background: white;
            border-radius: 10px;
            font-weight: 600;
            color: var(--gray);
            transition: all 0.25s ease;
        }
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .request-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.25s ease;
        }
        .request-card:hover {
            border-color: var(--primary);
            box-shadow: 0 10px 25px rgba(99,102,241,0.12);
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.approved { background: #d1fae5; color: #065f46; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        .btn-approve {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            border: none;
            padding: 0.6rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.25s ease;
        }
        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(16,185,129,0.3);
        }
        .btn-reject {
            background: white;
            color: var(--danger);
            border: 2px solid var(--danger);
            padding: 0.6rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.25s ease;
        }
        .btn-reject:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-2px);
        }
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }
        .empty-state i {
            font-size: 4rem;
            color: #cbd5e0;
            margin-bottom: 1.5rem;
        }

        /* Loading Screen */
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
            width: 90px;
            height: 90px;
        }
        @media (min-width: 768px) {
            .logo-ring-container {
                width: 120px;
                height: 120px;
            }
        }
        .logo-ring-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            animation: pulse 2.8s infinite ease-in-out;
        }
        .rotating-ring {
            position: absolute;
            inset: -12px;
            border: 4px solid transparent;
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

        /* SweetAlert2 Modern Styling */
        .swal2-popup {
            border-radius: 16px !important;
            font-family: 'Poppins', sans-serif !important;
            padding: 2rem !important;
        }
        .swal2-title {
            font-size: 1.6rem !important;
            font-weight: 700 !important;
        }
        .swal2-html-container {
            font-size: 1.1rem !important;
        }
        .swal2-confirm, .swal2-cancel {
            padding: 12px 32px !important;
            font-size: 1rem !important;
            border-radius: 10px !important;
        }
        .swal2-confirm {
            background: linear-gradient(135deg, #10b981, #059669) !important;
        }
        .swal2-cancel {
            background: #6b7280 !important;
        }
    </style>
</head>
<body>

<!-- Loading Screen -->
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

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

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
                                <button type="button" class="btn-approve" onclick="confirmApprove(<?= $req['request_id'] ?>, <?= $req['enrollment_id'] ?>)">
                                    Approve
                                </button>
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
                        <div class="modal-content rounded-3 shadow-xl">
                            <div class="modal-header bg-danger text-white border-0 rounded-top">
                                <h5 class="modal-title">Reject Request</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <p class="mb-3">Rejecting request for: <strong><?= htmlspecialchars($req['student_name']) ?></strong></p>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Reason for Rejection <span class="text-danger">*</span></label>
                                        <textarea class="form-control rounded-lg" name="rejection_reason" rows="4" required placeholder="Explain why this request is being rejected..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer border-0">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
                                    <button type="submit" class="btn btn-danger px-4">Reject Request</button>
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

<!-- Loading Screen Hide -->
<script>
    window.addEventListener('load', function () {
        document.body.classList.add('loaded');
    });
    setTimeout(() => {
        document.body.classList.add('loaded');
    }, 5000);
</script>

<!-- SweetAlert2 Confirmations -->
<script>
    // Confirm Approve
    function confirmApprove(requestId, enrollmentId) {
        Swal.fire({
            title: 'Approve Inactivation?',
            html: "This will <b>deactivate</b> the student's enrollment permanently.<br><br>Are you sure?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, Approve',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            customClass: {
                popup: 'swal2-modern-confirm',
                confirmButton: 'px-6 py-3 font-medium rounded-xl shadow-md',
                cancelButton: 'px-6 py-3 font-medium rounded-xl'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="request_id" value="${requestId}">
                    <input type="hidden" name="enrollment_id" value="${enrollmentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    // Show success/error toasts
    document.addEventListener('DOMContentLoaded', () => {
        <?php if ($success_message): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '<?= addslashes($success_message) ?>',
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end',
                customClass: { popup: 'swal2-modern-success' }
            });
        <?php endif; ?>

        <?php if ($error_message): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= addslashes($error_message) ?>',
                timer: 4000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end',
                customClass: { popup: 'swal2-modern-error' }
            });
        <?php endif; ?>
    });
</script>

</body>
</html>