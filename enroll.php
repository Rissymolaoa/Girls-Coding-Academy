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

// Enroll action - Now creates invoice and redirects to payment
$enrollMessage = "";
if (isset($_POST['enroll'])) {
    $batch_id = intval($_POST['batch_id']);
    
    // Fetch batch and course info including fee
    $batch_info_query = $conn->query("
        SELECT c.course_id, c.price 
        FROM batches b 
        INNER JOIN courses c ON b.course_id = c.course_id 
        WHERE b.batch_id = $batch_id
    ");
    
    if ($batch_info_query->num_rows == 0) {
        $enrollMessage = "<div class='alert alert-danger'>Batch not found.</div>";
    } else {
        $batch_info = $batch_info_query->fetch_assoc();
        $course_id = $batch_info['course_id'];
        $fee = $batch_info['price'];
        
        if ($fee <= 0) {
            $enrollMessage = "<div class='alert alert-danger'>Invalid course fee.</div>";
        } else {
            // Check if already enrolled
            $check_enroll = $conn->query("SELECT * FROM course_enrollments WHERE student_id=$student_id AND batch_id=$batch_id");
            if ($check_enroll->num_rows > 0) {
                $enrollMessage = "<div class='alert alert-danger'>You are already enrolled in this batch.</div>";
            } else {
                // Check if there's a pending invoice for this batch
                $check_invoice = $conn->query("SELECT invoice_id FROM invoices WHERE student_id=$student_id AND batch_id=$batch_id AND status='pending'");
                if ($check_invoice->num_rows > 0) {
                    $pending_invoice = $check_invoice->fetch_assoc();
                    header("Location: payment.php?invoice_id=" . $pending_invoice['invoice_id']);
                    exit();
                } else {
                    // Create new invoice
                    $insert_invoice = $conn->query("
                        INSERT INTO invoices (student_id, batch_id, course_id, amount, status, due_date, created_date) 
                        VALUES ($student_id, $batch_id, $course_id, $fee, 'pending', DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())
                    ");
                    
                    if ($conn->affected_rows > 0) {
                        $invoice_id = $conn->insert_id;
                        $invoice_number = 'INV-' . date('Y') . '-' . str_pad($invoice_id, 6, '0', STR_PAD_LEFT);
                        $conn->query("UPDATE invoices SET invoice_number = '$invoice_number' WHERE invoice_id = $invoice_id");
                        
                        // Redirect to payment gateway handler
                        header("Location: payment.php?invoice_id=" . $invoice_id);
                        exit();
                    } else {
                        $enrollMessage = "<div class='alert alert-danger'>Failed to create invoice. Please try again.</div>";
                    }
                }
            }
        }
    }
}

// Get batches with course info including fee
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
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enroll in Batches - Student Dashboard</title>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    margin: 0;
    padding: 0;
    min-height: 100vh;
}
.container-flex {
    display: flex;
    min-height: 100vh;
}
.content { 
    flex: 1; 
    padding: 40px 50px;
    margin-left: 280px;
    overflow-y: auto;
}
h2 {
    margin-bottom: 20px;
    color: #2c3e50;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
}
.batch-card { 
    background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(44, 62, 80, 0.1);
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.batch-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3498db, #2980b9);
}
.batch-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 10px 30px rgba(52, 73, 94, 0.2);
}
.batch-card img { 
    width: 100%; 
    height: 180px; 
    object-fit: cover; 
    border-radius: 10px; 
    margin-bottom: 18px; 
    flex-shrink: 0;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}
.batch-card h5 { 
    color: #2c3e50; 
    margin-bottom: 10px; 
    font-size: 1.3rem;
    font-weight: 600;
}
.batch-card p { 
    font-size: 14px; 
    color: #7f8c8d; 
    height: 60px; 
    overflow: hidden; 
    margin-bottom: 10px; 
    line-height: 1.4;
}
.batch-card small { 
    display: block; 
    margin-bottom: 10px; 
    color: #95a5a6; 
    font-size: 0.9rem;
}
.batch-card .fee-info {
    font-weight: bold;
    color: #27ae60;
    margin-bottom: 15px;
    font-size: 1.1rem;
}
.batch-card form button { 
    background: #3498db; 
    color: white; 
    border: none; 
    padding: 12px 24px; 
    cursor: pointer; 
    border-radius: 8px; 
    font-weight: 500;
    transition: background 0.3s ease;
    width: 100%;
}
.batch-card form button:hover { 
    background: #2980b9; 
}
.view-toggle { 
    margin-bottom: 25px; 
    text-align: right;
}
.view-toggle button {
    background: rgba(52, 73, 94, 0.1);
    border: 2px solid #34495e;
    color: #34495e;
    font-size: 1.4rem;
    cursor: pointer;
    margin-left: 12px;
    padding: 8px 12px;
    border-radius: 50%;
    transition: all 0.3s ease;
}
.view-toggle button:hover {
    background: #34495e;
    color: white;
    transform: scale(1.1);
}
.view-toggle button.active {
    background: #3498db;
    color: white;
    border-color: #2980b9;
}
/* Grid View (default) */
.batch-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit,minmax(300px,1fr)); 
    gap: 25px; 
}
/* List View */
.list-view {
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.list-view .batch-card {
    flex-direction: row;
    align-items: center;
    height: auto;
    padding: 20px;
}
.list-view .batch-card img {
    width: 200px;
    height: 140px;
    margin-right: 25px;
    margin-bottom: 0;
    border-radius: 10px;
}
.list-view .card-body {
    flex: 1;
    text-align: left;
}
.no-batches {
    text-align: center;
    color: #7f8c8d;
    font-style: italic;
    padding: 50px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(44, 62, 80, 0.1);
}
@media (max-width: 768px) {
    .content {
        margin-left: 0;
        padding: 20px;
    }
    .batch-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<div class="container-flex">
    <!-- Include the consistent navigation -->
    <?php include("student_navigation.php"); ?>

    <!-- Content -->
    <div class="content">
        <h2><i class="bi bi-list-task"></i> Available Batches</h2>
        <?php echo $enrollMessage; ?>

        <!-- View Toggle Buttons -->
        <div class="view-toggle">
            <button id="gridView" class="active" aria-pressed="true" title="Grid View"><i class="bi bi-grid-3x3-gap-fill"></i></button>
            <button id="listView" aria-pressed="false" title="List View"><i class="bi bi-list-ul"></i></button>
        </div>

        <!-- Batch Container -->
        <div id="batchContainer" class="batch-grid">
            <?php if($batches->num_rows > 0): ?>
                <?php while($row = $batches->fetch_assoc()) { 
                    $imgPath = !empty($row['image_path']) ? $row['image_path'] : 'uploads/courses/course1.jpg';
                ?>
                <div class="batch-card">
                    <img src="<?php echo htmlspecialchars($imgPath); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($row['courseName']); ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($row['courseName']); ?></h5>
                        <p class="card-text"><?php echo htmlspecialchars($row['description']); ?></p>
                        <div class="fee-info">Course Fee: M <?php echo number_format($row['price'], 2); ?></div>
                        <small>Batch Code: <?php echo htmlspecialchars($row['batch_code']); ?></small><br>
                        <small>Start: <?php echo htmlspecialchars($row['start_date']); ?> | End: <?php echo htmlspecialchars($row['end_date']); ?></small>
                        <form method="POST" action="" class="mt-2">
                            <input type="hidden" name="batch_id" value="<?php echo $row['batch_id']; ?>">
                            <button type="submit" name="enroll" class="btn btn-primary btn-sm"><i class="bi bi-credit-card"></i> Enroll & Pay</button>
                        </form>
                    </div>
                </div>
                <?php } ?>
            <?php else: ?>
                <div class="no-batches">No active batches available at the moment. Check back soon!</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const batchContainer = document.getElementById('batchContainer');
const gridViewBtn = document.getElementById('gridView');
const listViewBtn = document.getElementById('listView');

function setGridView() {
    batchContainer.classList.remove('list-view');
    gridViewBtn.classList.add('active');
    gridViewBtn.setAttribute('aria-pressed', 'true');
    listViewBtn.classList.remove('active');
    listViewBtn.setAttribute('aria-pressed', 'false');
}
function setListView() {
    batchContainer.classList.add('list-view');
    listViewBtn.classList.add('active');
    listViewBtn.setAttribute('aria-pressed', 'true');
    gridViewBtn.classList.remove('active');
    gridViewBtn.setAttribute('aria-pressed', 'false');
}

gridViewBtn.addEventListener('click', setGridView);
listViewBtn.addEventListener('click', setListView);
</script>

</body>
</html>