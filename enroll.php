<?php
session_start();
// DB connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
// Check if logged in student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.html");
    exit();
}
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
// Find student_id linked to this user
$res = $conn->query("SELECT student_id FROM students WHERE user_id = $user_id");
if ($res->num_rows > 0) {
    $student = $res->fetch_assoc();
    $student_id = $student['student_id'];
} else {
    die("Error: Student not found.");
}
// Enroll action - Creates enrollment only, invoice created by database trigger
$enrollMessage = "";
if (isset($_POST['enroll'])) {
    $batch_id = intval($_POST['batch_id']);
   
    // Fetch batch and course info
    $batch_info_query = $conn->query("
        SELECT c.course_id, c.price
        FROM batches b
        INNER JOIN courses c ON b.course_id = c.course_id
        WHERE b.batch_id = $batch_id
    ");
   
    if ($batch_info_query->num_rows == 0) {
        $enrollMessage = "<div class='alert alert-danger'><i class='bi bi-exclamation-circle'></i> Batch not found.</div>";
    } else {
        $batch_info = $batch_info_query->fetch_assoc();
        $fee = $batch_info['price'];
        
        if ($fee <= 0) {
            $enrollMessage = "<div class='alert alert-danger'><i class='bi bi-exclamation-circle'></i> Invalid course fee.</div>";
        } else {
            // Check if already enrolled
            $check_enroll = $conn->query("SELECT enrollment_id FROM course_enrollments WHERE student_id = $student_id AND batch_id = $batch_id");
            
            if ($check_enroll->num_rows > 0) {
                $enrollMessage = "<div class='alert alert-danger'><i class='bi bi-info-circle'></i> You are already enrolled in this batch.</div>";
            } else {
                // Generate unique enrollment_number upfront
                do {
                    $random_suffix = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                    $enroll_num = "ENR-" . date('Y') . "-" . date('m') . "-" . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
                    $check_unique = $conn->query("SELECT enrollment_id FROM course_enrollments WHERE enrollment_number = '" . $conn->real_escape_string($enroll_num) . "'");
                } while ($check_unique->num_rows > 0);
                
                // Insert enrollment with enrollment_number
                $insert_enrollment = $conn->query("
                    INSERT INTO course_enrollments (student_id, batch_id, enrolled_at, status, enrollment_number)
                    VALUES ($student_id, $batch_id, NOW(), 'active', '" . $conn->real_escape_string($enroll_num) . "')
                ");
                
                if ($conn->affected_rows > 0) {
                    $enrollment_id = $conn->insert_id;
                    
                    // Update with final number using enrollment_id for consistency
                    $final_enroll_num = "ENR-" . date('Y') . "-" . date('m') . "-" . str_pad($enrollment_id, 6, '0');
                    $conn->query("UPDATE course_enrollments SET enrollment_number = '" . $conn->real_escape_string($final_enroll_num) . "' WHERE enrollment_id = $enrollment_id");
                    
                    // Get the invoice that was just created by the trigger
                    $invoice_query = $conn->query("
                        SELECT invoice_id FROM invoices
                        WHERE enrollment_id = $enrollment_id
                        ORDER BY invoice_id DESC
                        LIMIT 1
                    ");
                    
                    if ($invoice_query->num_rows > 0) {
                        $invoice = $invoice_query->fetch_assoc();
                        $invoice_id = $invoice['invoice_id'];
                        
                        // Redirect to payment
                        header("Location: make_payment.php?invoice_id=" . $invoice_id);
                        exit();
                    } else {
                        $enrollMessage = "<div class='alert alert-danger'><i class='bi bi-exclamation-circle'></i> Enrollment successful but invoice not created. Please contact support.</div>";
                    }
                } else {
                    $enrollMessage = "<div class='alert alert-danger'><i class='bi bi-exclamation-circle'></i> Failed to enroll in batch. Please try again.</div>";
                }
            }
        }
    }
}
// Get batches with course info
$batches = $conn->query("
    SELECT
        b.batch_id,
        b.batch_code,
        b.start_date,
        b.end_date,
        b.status,
        c.courseName,
        c.image_path,
        c.description,
        c.price
    FROM batches b
    INNER JOIN courses c ON b.course_id = c.course_id
    WHERE b.status='active'
    ORDER BY b.start_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enroll in Courses - Girls Coding Academy</title>
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --primary: #667eea;
        --primary-dark: #764ba2;
        --success: #10b981;
        --danger: #ef4444;
        --light-bg: #f9fafb;
        --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        --transition: all 0.3s ease;
    }
    * {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    body {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: 100vh;
    }
    .main-container {
        display: flex;
        min-height: 100vh;
    }
    .content-area {
        flex: 1;
        margin-left: 280px;
        padding: 2rem;
        overflow-y: auto;
    }
    /* Page Header */
    .page-header {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--card-shadow);
    }
    .page-header h1 {
        font-size: 2rem;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .page-header h1 i {
        font-size: 2.5rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .page-header p {
        color: #6b7280;
        margin: 0;
    }
    /* View Toggle */
    .view-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: var(--card-shadow);
    }
    .view-toggle-group {
        display: flex;
        gap: 0.5rem;
    }
    .view-toggle-btn {
        background: var(--light-bg);
        border: 2px solid transparent;
        color: #6b7280;
        font-size: 1.25rem;
        cursor: pointer;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        transition: var(--transition);
    }
    .view-toggle-btn:hover {
        background: white;
        border-color: var(--primary);
        color: var(--primary);
    }
    .view-toggle-btn.active {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        border-color: var(--primary);
    }
    .filter-info {
        color: #6b7280;
        font-size: 0.9rem;
        font-weight: 500;
    }
    /* Batch Grid */
    .batch-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 2rem;
        margin-bottom: 2rem;
    }
    .batch-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: var(--card-shadow);
        transition: var(--transition);
        display: flex;
        flex-direction: column;
        border-top: 4px solid var(--primary);
    }
    .batch-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15);
    }
    .batch-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
        background: var(--light-bg);
    }
    .batch-body {
        padding: 1.5rem;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    .batch-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 0.5rem;
    }
    .batch-description {
        color: #6b7280;
        font-size: 0.9rem;
        line-height: 1.5;
        margin-bottom: 1rem;
        flex: 1;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .batch-meta {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }
    .meta-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #6b7280;
        font-size: 0.85rem;
    }
    .meta-item i {
        color: var(--primary);
        font-size: 1rem;
    }
    .batch-fee {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 1rem;
        text-align: center;
    }
    .fee-label {
        color: #6b7280;
        font-size: 0.85rem;
        margin-bottom: 0.25rem;
    }
    .fee-amount {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--success);
    }
    .batch-actions {
        display: flex;
        gap: 0.75rem;
    }
    .enroll-btn {
        flex: 1;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        border: none;
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    .enroll-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px rgba(102, 126, 234, 0.3);
    }
    .enroll-btn:active {
        transform: translateY(0);
    }
    /* List View */
    .batch-grid.list-view {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    .batch-grid.list-view .batch-card {
        flex-direction: row;
    }
    .batch-grid.list-view .batch-image {
        width: 250px;
        height: 150px;
        flex-shrink: 0;
    }
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 16px;
        box-shadow: var(--card-shadow);
    }
    .empty-icon {
        font-size: 4rem;
        color: var(--light-bg);
        margin-bottom: 1rem;
    }
    .empty-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 0.5rem;
    }
    .empty-text {
        color: #6b7280;
    }
    /* Alerts */
    .alert {
        border: none;
        border-radius: 12px;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
    }
    .alert-danger {
        background: #fee2e2;
        color: #991b1b;
    }
    .alert i {
        margin-right: 0.5rem;
    }
    /* Responsive */
    @media (max-width: 768px) {
        .content-area {
            margin-left: 0;
            padding: 1rem;
        }
        .batch-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        .batch-grid.list-view .batch-image {
            width: 150px;
            height: 120px;
        }
        .view-controls {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        .page-header h1 {
            font-size: 1.5rem;
        }
    }
</style>
</head>
<body>
<div class="main-container">
    <!-- Include Navigation -->
    <?php include("student_navigation.php"); ?>
    <!-- Content Area -->
    <div class="content-area">
        <div class="page-header">
            <h1><i class="bi bi-book"></i> Available Courses</h1>
            <p>Enroll in courses and proceed to payment</p>
        </div>
        <!-- Messages -->
        <?php if (!empty($enrollMessage)) echo $enrollMessage; ?>
        <!-- View Controls -->
        <div class="view-controls">
            <span class="filter-info">
                <i class="bi bi-grid-3x3"></i> Showing available batches
            </span>
            <div class="view-toggle-group">
                <button class="view-toggle-btn active" id="gridViewBtn" title="Grid View">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                </button>
                <button class="view-toggle-btn" id="listViewBtn" title="List View">
                    <i class="bi bi-list-ul"></i>
                </button>
            </div>
        </div>
        <!-- Batch Container -->
        <div class="batch-grid" id="batchContainer">
            <?php if ($batches->num_rows > 0): ?>
                <?php while ($row = $batches->fetch_assoc()):
                    $imgPath = !empty($row['image_path']) ? htmlspecialchars($row['image_path']) : 'uploads/courses/course1.jpg';
                ?>
                <div class="batch-card">
                    <img src="<?= $imgPath ?>" alt="<?= htmlspecialchars($row['courseName']) ?>" class="batch-image">
                    
                    <div class="batch-body">
                        <h5 class="batch-title"><?= htmlspecialchars($row['courseName']) ?></h5>
                        <p class="batch-description"><?= htmlspecialchars($row['description']) ?></p>
                        <div class="batch-meta">
                            <div class="meta-item">
                                <i class="bi bi-tag"></i>
                                <span><?= htmlspecialchars($row['batch_code']) ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="bi bi-calendar-event"></i>
                                <span><?= htmlspecialchars($row['start_date']) ?> to <?= htmlspecialchars($row['end_date']) ?></span>
                            </div>
                        </div>
                        <div class="batch-fee">
                            <div class="fee-label">Course Fee</div>
                            <div class="fee-amount">M <?= number_format($row['price'], 2) ?></div>
                        </div>
                        <div class="batch-actions">
                            <form method="POST" action="" style="width: 100%;">
                                <input type="hidden" name="batch_id" value="<?= $row['batch_id'] ?>">
                                <button type="submit" name="enroll" class="enroll-btn">
                                    <i class="bi bi-credit-card"></i> Enroll & Pay
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <div class="empty-icon">
                        <i class="bi bi-inbox"></i>
                    </div>
                    <h3 class="empty-title">No Courses Available</h3>
                    <p class="empty-text">There are no active batches available at the moment. Please check back soon!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const batchContainer = document.getElementById('batchContainer');
    const gridViewBtn = document.getElementById('gridViewBtn');
    const listViewBtn = document.getElementById('listViewBtn');
    gridViewBtn.addEventListener('click', () => {
        batchContainer.classList.remove('list-view');
        gridViewBtn.classList.add('active');
        listViewBtn.classList.remove('active');
    });
    listViewBtn.addEventListener('click', () => {
        batchContainer.classList.add('list-view');
        listViewBtn.classList.add('active');
        gridViewBtn.classList.remove('active');
    });
</script>
</body>
</html>