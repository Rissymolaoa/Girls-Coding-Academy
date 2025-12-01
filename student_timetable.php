<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.html");
    exit();
}

include("db.php");

$user_id = $_SESSION['user_id'];
$currentPage = basename($_SERVER['PHP_SELF']);

// Fetch student info
$stmt_student = $conn->prepare("
    SELECT s.student_id, s.photo, u.username
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    WHERE s.user_id = ?
");
$stmt_student->bind_param("i", $user_id);
$stmt_student->execute();
$result_student = $stmt_student->get_result();
$studentInfo = $result_student->fetch_assoc();
$student_id = $studentInfo['student_id'];
$stmt_student->close();

// Fetch all batches enrolled by this student
$stmt_batches = $conn->prepare("
    SELECT DISTINCT b.batch_id, b.batch_code, c.courseName, c.course_id
    FROM course_enrollments ce
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    WHERE ce.student_id = ?
    ORDER BY c.courseName, b.batch_code
");
$stmt_batches->bind_param("i", $student_id);
$stmt_batches->execute();
$batches_result = $stmt_batches->get_result();
$batches = $batches_result->fetch_all(MYSQLI_ASSOC);
$stmt_batches->close();

// Get selected batch from GET parameter
$selected_batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : ($batches[0]['batch_id'] ?? 0);

// Verify student is enrolled in selected batch
$verify_enrolled = false;
$selected_batch_info = null;

if ($selected_batch_id > 0) {
    foreach ($batches as $batch) {
        if ($batch['batch_id'] === $selected_batch_id) {
            $verify_enrolled = true;
            $selected_batch_info = $batch;
            break;
        }
    }
}

// Fetch timetable for selected batch
$timetable_by_day = [];
$all_timetable = [];

if ($verify_enrolled) {
    $stmt_timetable = $conn->prepare("
        SELECT id, day, start_time, end_time, period, subject, room
        FROM teacher_timetables
        WHERE batch_id = ?
        ORDER BY day, start_time
    ");
    $stmt_timetable->bind_param("i", $selected_batch_id);
    $stmt_timetable->execute();
    $timetable_result = $stmt_timetable->get_result();
    $all_timetable = $timetable_result->fetch_all(MYSQLI_ASSOC);
    $stmt_timetable->close();
    
    // Group by day
    foreach ($all_timetable as $entry) {
        $day = (int)$entry['day'];
        if (!isset($timetable_by_day[$day])) {
            $timetable_by_day[$day] = [];
        }
        $timetable_by_day[$day][] = $entry;
    }
}

$days_order = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>View Timetable - Student</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    height: 100vh;
    overflow: hidden;
    color: #2c3e50;
}

.container-flex {
    display: flex;
    height: 100vh;
}

.content {
    flex: 1;
    padding: 40px;
    margin-left: 250px;
    overflow-y: auto;
    height: 100vh;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}

.content::-webkit-scrollbar {
    width: 8px;
}

.content::-webkit-scrollbar-track {
    background: transparent;
}

.content::-webkit-scrollbar-thumb {
    background: #00d9ff;
    border-radius: 4px;
}

.content::-webkit-scrollbar-thumb:hover {
    background: #0099cc;
}

.header {
    margin-bottom: 35px;
    padding-bottom: 25px;
    border-bottom: 2px solid rgba(0, 217, 255, 0.3);
}

.header h2 {
    font-size: 2rem;
    font-weight: 700;
    background: linear-gradient(135deg, #1e293b 0%, #00d9ff 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 12px;
}

.header p {
    color: #64748b;
    margin: 0;
}

.card-section {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 16px;
    padding: 32px;
    margin-bottom: 30px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0, 217, 255, 0.2);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.card-section:hover {
    box-shadow: 0 12px 40px rgba(0, 217, 255, 0.15);
    transform: translateY(-2px);
}

.card-section h3 {
    color: #1e293b;
    margin-bottom: 25px;
    font-weight: 700;
    font-size: 1.4rem;
    display: flex;
    align-items: center;
    gap: 12px;
}

.card-section h3 i {
    color: #00d9ff;
}

.batch-selector {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 14px;
}

.batch-card {
    padding: 16px;
    border: 2px solid #e2e8f0;
    background: white;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    color: #1e293b;
    text-align: center;
}

.batch-card:hover {
    border-color: #00d9ff;
    background: rgba(0, 217, 255, 0.05);
    transform: translateY(-4px);
    box-shadow: 0 6px 20px rgba(0, 217, 255, 0.15);
}

.batch-card.active {
    background: linear-gradient(135deg, #00d9ff 0%, #0099cc 100%);
    color: white;
    border-color: #00d9ff;
    box-shadow: 0 6px 20px rgba(0, 217, 255, 0.3);
}

.batch-course {
    font-size: 0.85rem;
    color: #64748b;
    margin-bottom: 6px;
}

.batch-card.active .batch-course {
    color: rgba(255, 255, 255, 0.9);
}

.batch-code {
    font-size: 1.1rem;
    font-weight: 700;
}

.timetable-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.day-column {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 217, 255, 0.2);
    transition: all 0.3s ease;
}

.day-column:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 30px rgba(0, 217, 255, 0.2);
}

.day-header {
    background: linear-gradient(135deg, #00d9ff 0%, #0099cc 100%);
    color: white;
    padding: 18px;
    font-weight: 700;
    font-size: 1.15rem;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.day-content {
    padding: 16px;
    min-height: 100px;
}

.class-item {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 12px;
    border-left: 4px solid #00d9ff;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.class-item:hover {
    transform: translateX(6px);
    box-shadow: 0 4px 12px rgba(0, 217, 255, 0.15);
}

.class-time {
    font-weight: 700;
    color: #00d9ff;
    font-size: 0.9rem;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.class-subject {
    color: #1e293b;
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 4px;
}

.class-room {
    color: #64748b;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 4px;
}

.class-period {
    display: inline-block;
    background: #00d9ff;
    color: #0f172a;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 700;
    margin-top: 6px;
}

.empty-day {
    color: #94a3b8;
    font-style: italic;
    text-align: center;
    padding: 30px 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.empty-day i {
    font-size: 2rem;
    opacity: 0.5;
}

.no-batch-selected {
    text-align: center;
    padding: 60px 20px;
    color: #94a3b8;
}

.no-batch-selected i {
    font-size: 3rem;
    margin-bottom: 16px;
    opacity: 0.5;
}

.no-timetable {
    text-align: center;
    padding: 40px 20px;
    color: #94a3b8;
}

.no-timetable i {
    font-size: 2.5rem;
    margin-bottom: 16px;
    opacity: 0.5;
}

.batch-info {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    padding: 16px;
    border-radius: 12px;
    border-left: 4px solid #00d9ff;
    margin-bottom: 25px;
}

.batch-info-title {
    color: #1e293b;
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: 4px;
}

.batch-info-subtitle {
    color: #64748b;
    font-size: 0.95rem;
}

.download-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #00d9ff 0%, #0099cc 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.download-btn:hover {
    background: linear-gradient(135deg, #0099cc 0%, #006699 100%);
    transform: translateY(-2px);
    color: white;
}

.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #64748b 0%, #475569 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.back-btn:hover {
    background: linear-gradient(135deg, #475569 0%, #334155 100%);
    transform: translateY(-2px);
    color: white;
}

.button-group {
    display: flex;
    gap: 12px;
    margin-top: 30px;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .content {
        margin-left: 0;
        padding: 20px;
    }
    
    .header h2 {
        font-size: 1.5rem;
    }
    
    .batch-selector {
        grid-template-columns: 1fr;
    }
    
    .timetable-grid {
        grid-template-columns: 1fr;
    }
    
    .button-group {
        flex-direction: column;
    }
    
    .download-btn,
    .back-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
</head>
<body>

<div class="container-flex">
    <!-- Include Navigation -->
    <?php include("student_navigation.php"); ?>

    <!-- Main content -->
    <main class="content">
        <div class="header">
            <h2><i class="bi bi-calendar2-week"></i> My Class Timetable</h2>
            <p>View schedules for all your enrolled courses</p>
        </div>

        <?php if (empty($batches)): ?>
            <div class="card-section">
                <div class="no-batch-selected">
                    <i class="bi bi-diagram-3"></i>
                    <p style="font-size: 1.2rem; margin-top: 16px;">You are not enrolled in any batches yet.</p>
                    <a href="enroll.php" class="download-btn" style="margin-top: 20px;">
                        <i class="bi bi-plus-circle"></i> Enroll in a Course
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Batch Selection -->
            <div class="card-section">
                <h3><i class="bi bi-diagram-3"></i> Select Your Batch</h3>
                <div class="batch-selector">
                    <?php foreach ($batches as $batch): ?>
                        <a href="?batch_id=<?= $batch['batch_id'] ?>" 
                           class="batch-card <?= ($selected_batch_id === $batch['batch_id']) ? 'active' : '' ?>">
                            <div class="batch-course"><?= htmlspecialchars($batch['courseName']) ?></div>
                            <div class="batch-code"><?= htmlspecialchars($batch['batch_code']) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($verify_enrolled && $selected_batch_info): ?>
                <!-- Batch Info -->
                <div class="batch-info">
                    <div class="batch-info-title">
                        <i class="bi bi-journal-bookmark"></i> <?= htmlspecialchars($selected_batch_info['courseName']) ?>
                    </div>
                    <div class="batch-info-subtitle">
                        Batch: <?= htmlspecialchars($selected_batch_info['batch_code']) ?>
                    </div>
                </div>

                <!-- Timetable Display -->
                <div class="card-section">
                    <h3><i class="bi bi-grid-3x3"></i> Weekly Schedule</h3>
                    
                    <?php if (empty($all_timetable)): ?>
                        <div class="no-timetable">
                            <i class="bi bi-calendar-x"></i>
                            <p>No classes scheduled yet for this batch.</p>
                            <p style="font-size: 0.9rem; margin-top: 8px;">Check back later or contact your instructor.</p>
                        </div>
                    <?php else: ?>
                        <div class="timetable-grid">
                            <?php foreach ($days_order as $day_index => $day_name): ?>
                                <div class="day-column">
                                    <div class="day-header">
                                        <i class="bi bi-calendar-event"></i> <?= $day_name ?>
                                    </div>
                                    <div class="day-content">
                                        <?php if (isset($timetable_by_day[$day_index]) && !empty($timetable_by_day[$day_index])): ?>
                                            <?php foreach ($timetable_by_day[$day_index] as $class): ?>
                                                <div class="class-item">
                                                    <div class="class-time">
                                                        <i class="bi bi-clock"></i> 
                                                        <?= date('h:i A', strtotime($class['start_time'])) ?> – <?= date('h:i A', strtotime($class['end_time'])) ?>
                                                    </div>
                                                    <div class="class-subject"><?= htmlspecialchars($class['subject']) ?></div>
                                                    <?php if ($class['room']): ?>
                                                        <div class="class-room">
                                                            <i class="bi bi-door-closed"></i> <?= htmlspecialchars($class['room']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <span class="class-period">Period <?= $class['period'] ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="empty-day">
                                                <i class="bi bi-calendar-x"></i>
                                                <span>No classes</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="button-group">
                <a href="student_courses.php" class="back-btn">
                    <i class="bi bi-arrow-left"></i> Back to My Courses
                </a>
            </div>
        <?php endif; ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>