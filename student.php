<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}
include("db.php");

$user_id = $_SESSION['user_id'];
$currentPage = basename($_SERVER['PHP_SELF']);

// Get student info
$stmt_student = $conn->prepare("
    SELECT s.student_id, s.photo, u.username, u.email, u.role
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    WHERE s.user_id = ?
");
$stmt_student->bind_param("i", $user_id);
$stmt_student->execute();
$result_student = $stmt_student->get_result();
$studentInfo = $result_student->fetch_assoc();
$student_id = $studentInfo['student_id'];

// === Summary Stats ===
// Activities count
$res = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM activities a
    JOIN course_enrollments e ON a.batch_id = e.batch_id
    WHERE e.student_id = ? AND a.status = 'active'
");
$res->bind_param("i", $student_id);
$res->execute();
$activities = $res->get_result()->fetch_assoc()['total'] ?? 0;

// Attendance %
$res = $conn->prepare("SELECT
    (SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) / COUNT(*)) * 100 AS attendance_pct
    FROM attendance WHERE student_id = ?");
$res->bind_param("i", $student_id);
$res->execute();
$attendance = number_format($res->get_result()->fetch_assoc()['attendance_pct'] ?? 0, 1);

// Overall Performance
$res = $conn->prepare("SELECT
    AVG((COALESCE(test_1,0)+COALESCE(test_2,0)+COALESCE(test_3,0)+COALESCE(test_4,0)+
         COALESCE(test_5,0)+COALESCE(test_6,0)+COALESCE(test_7,0)+COALESCE(end_examination,0)) / 800 * 100)
    AS perf FROM internal_grades WHERE student_id = ?");
$res->bind_param("i", $student_id);
$res->execute();
$performance = number_format($res->get_result()->fetch_assoc()['perf'] ?? 0, 1);

// Fetch upcoming classes (next 5)
$upcoming_classes = [];
$res = $conn->prepare("
    SELECT DISTINCT b.batch_id, b.batch_code, c.courseName, t.day, t.start_time, t.end_time, t.subject, t.room
    FROM course_enrollments ce
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    JOIN teacher_timetables t ON b.batch_id = t.batch_id
    WHERE ce.student_id = ?
    ORDER BY t.day ASC, t.start_time ASC
    LIMIT 5
");
$res->bind_param("i", $student_id);
$res->execute();
$upcoming_classes = $res->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch latest announcements (admin announcements for all or students)
$announcements = [];
$res = $conn->prepare("
    SELECT announcement_id, message, picture_path, recipients, created_at
    FROM admin_announcements
    WHERE recipients IN ('students', 'all')
    ORDER BY created_at DESC
    LIMIT 4
");
$res->execute();
$announcements = $res->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch recent tasks
$tasks = [];
$res = $conn->prepare("
    SELECT a.activity_id, a.title, a.due_date, c.courseName, b.batch_id
    FROM activities a
    JOIN batches b ON a.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    JOIN course_enrollments ce ON b.batch_id = ce.batch_id
    WHERE ce.student_id = ? AND a.status = 'active'
    ORDER BY a.due_date ASC
    LIMIT 5
");
$res->bind_param("i", $student_id);
$res->execute();
$tasks = $res->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch enrolled batches for quick links
$batches = [];
$res = $conn->prepare("
    SELECT DISTINCT b.batch_id, b.batch_code, c.courseName
    FROM course_enrollments ce
    JOIN batches b ON ce.batch_id = b.batch_id
    JOIN courses c ON b.course_id = c.course_id
    WHERE ce.student_id = ?
    LIMIT 4
");
$res->bind_param("i", $student_id);
$res->execute();
$batches = $res->get_result()->fetch_all(MYSQLI_ASSOC);

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

function getDayName($dayIndex) {
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    return $days[$dayIndex] ?? 'Unknown';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Student Dashboard</title>
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

.header {
    margin-bottom: 40px;
    padding-bottom: 25px;
    border-bottom: 2px solid rgba(0, 217, 255, 0.3);
}

.header h2 {
    font-size: 2.2rem;
    font-weight: 700;
    background: linear-gradient(135deg, #1e293b 0%, #00d9ff 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 8px;
}

.header p {
    color: #64748b;
    margin: 0;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0, 217, 255, 0.2);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
    border-top: 4px solid transparent;
    text-align: center;
}

.stat-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 40px rgba(0, 217, 255, 0.15);
}

.stat-card.stat-activities {
    border-top-color: #00d9ff;
}

.stat-card.stat-attendance {
    border-top-color: #10b981;
}

.stat-card.stat-performance {
    border-top-color: #f59e0b;
}

.stat-icon {
    font-size: 2.5rem;
    margin-bottom: 12px;
}

.stat-card.stat-activities .stat-icon {
    color: #00d9ff;
}

.stat-card.stat-attendance .stat-icon {
    color: #10b981;
}

.stat-card.stat-performance .stat-icon {
    color: #f59e0b;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
}

.stat-label {
    font-size: 0.95rem;
    color: #64748b;
    font-weight: 500;
}

/* Main Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 40px;
}

.section-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0, 217, 255, 0.2);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.section-card:hover {
    box-shadow: 0 12px 40px rgba(0, 217, 255, 0.15);
    transform: translateY(-2px);
}

.section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid #e2e8f0;
}

.section-header h3 {
    font-size: 1.3rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.section-header i {
    font-size: 1.5rem;
    color: #00d9ff;
}

/* Announcements */
.announcement-item {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 12px;
    border-left: 4px solid #00d9ff;
    transition: all 0.3s ease;
}

.announcement-item:hover {
    transform: translateX(6px);
    box-shadow: 0 4px 12px rgba(0, 217, 255, 0.15);
}

.announcement-course {
    font-size: 0.8rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.announcement-title {
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 6px;
    display: block;
}

.announcement-text {
    font-size: 0.9rem;
    color: #64748b;
    line-height: 1.5;
    margin-bottom: 8px;
}

.announcement-date {
    font-size: 0.8rem;
    color: #94a3b8;
}

/* Classes */
.class-item {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 14px;
    border-radius: 12px;
    margin-bottom: 12px;
    border-left: 4px solid #10b981;
    transition: all 0.3s ease;
}

.class-item:hover {
    transform: translateX(6px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
}

.class-day {
    font-size: 0.8rem;
    color: #10b981;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.class-time {
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.class-subject {
    font-size: 0.9rem;
    color: #64748b;
    margin-bottom: 4px;
}

.class-room {
    font-size: 0.85rem;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Tasks */
.task-item {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 14px;
    border-radius: 12px;
    margin-bottom: 12px;
    border-left: 4px solid #f59e0b;
    transition: all 0.3s ease;
}

.task-item:hover {
    transform: translateX(6px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.15);
}

.task-course {
    font-size: 0.8rem;
    color: #f59e0b;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.task-title {
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 4px;
    display: block;
}

.task-due {
    font-size: 0.85rem;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 30px 20px;
    color: #94a3b8;
}

.empty-state i {
    font-size: 2.5rem;
    margin-bottom: 12px;
    opacity: 0.5;
}

.empty-state p {
    margin: 0;
}

/* Batch Links */
.batch-links {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
}

.batch-link {
    background: linear-gradient(135deg, #00d9ff 0%, #0099cc 100%);
    color: white;
    padding: 14px;
    border-radius: 10px;
    text-decoration: none;
    text-align: center;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    font-size: 0.9rem;
}

.batch-link:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 20px rgba(0, 217, 255, 0.3);
    color: white;
}

.view-all-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #00d9ff;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    margin-top: 12px;
    transition: all 0.3s ease;
}

.view-all-btn:hover {
    color: #0099cc;
    gap: 10px;
}

@media (max-width: 1024px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .content {
        margin-left: 0;
        padding: 20px;
    }
    
    .header h2 {
        font-size: 1.5rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<div class="container-flex">
    <!-- Include Navigation -->
    <?php include("student_navigation.php"); ?>

    <!-- Main Content -->
    <main class="content">
        <div class="header">
            <h2><i class="bi bi-house-door"></i> Welcome back, <?= htmlspecialchars($studentInfo['username']) ?>!</h2>
            <p>Here's your academic overview for today</p>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card stat-activities">
                <i class="bi bi-list-check stat-icon"></i>
                <div class="stat-value"><?= $activities ?></div>
                <div class="stat-label">Active Tasks</div>
            </div>
            <div class="stat-card stat-attendance">
                <i class="bi bi-calendar-check stat-icon"></i>
                <div class="stat-value"><?= $attendance ?>%</div>
                <div class="stat-label">Attendance Rate</div>
            </div>
            <div class="stat-card stat-performance">
                <i class="bi bi-graph-up stat-icon"></i>
                <div class="stat-value"><?= $performance ?>%</div>
                <div class="stat-label">Overall Performance</div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="dashboard-grid">
            <!-- Announcements Section -->
            <div class="section-card">
                <div class="section-header">
                    <i class="bi bi-megaphone"></i>
                    <h3>Latest Announcements</h3>
                </div>
                
                <?php if (!empty($announcements)): ?>
                    <div>
                        <?php foreach ($announcements as $announce): ?>
                            <div class="announcement-item">
                                <?php if ($announce['picture_path']): ?>
                                    <img src="<?= htmlspecialchars($announce['picture_path']) ?>" alt="Announcement" style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px; margin-bottom: 12px;">
                                <?php endif; ?>
                                <span class="announcement-title"><?= htmlspecialchars(substr($announce['message'], 0, 50)) ?></span>
                                <div class="announcement-text"><?= htmlspecialchars(substr($announce['message'], 0, 80)) ?>...</div>
                                <div class="announcement-date">
                                    <i class="bi bi-calendar2"></i> <?= date('M d, Y', strtotime($announce['created_at'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <a href="student_announcements.php" class="view-all-btn">
                            <i class="bi bi-arrow-right"></i> View All
                        </a>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-megaphone"></i>
                        <p>No announcements yet</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Upcoming Classes Section -->
            <div class="section-card">
                <div class="section-header">
                    <i class="bi bi-calendar2-week"></i>
                    <h3>Upcoming Classes</h3>
                </div>
                
                <?php if (!empty($upcoming_classes)): ?>
                    <div>
                        <?php foreach ($upcoming_classes as $class): ?>
                            <div class="class-item">
                                <div class="class-day"><?= getDayName($class['day']) ?></div>
                                <div class="class-time">
                                    <i class="bi bi-clock"></i> <?= date('h:i A', strtotime($class['start_time'])) ?> - <?= date('h:i A', strtotime($class['end_time'])) ?>
                                </div>
                                <div class="class-subject"><?= htmlspecialchars($class['subject']) ?></div>
                                <div class="class-room">
                                    <i class="bi bi-door-closed"></i> <?= htmlspecialchars($class['room'] ?? 'TBA') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <a href="student_timetable.php" class="view-all-btn">
                            <i class="bi bi-arrow-right"></i> Full Timetable
                        </a>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-calendar2-week"></i>
                        <p>No classes scheduled</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tasks Section -->
            <div class="section-card">
                <div class="section-header">
                    <i class="bi bi-list-task"></i>
                    <h3>Upcoming Tasks</h3>
                </div>
                
                <?php if (!empty($tasks)): ?>
                    <div>
                        <?php foreach ($tasks as $task): ?>
                            <div class="task-item">
                                <div class="task-course"><?= htmlspecialchars($task['courseName']) ?></div>
                                <a href="submit_activity.php?activity_id=<?= $task['activity_id'] ?>&course_id=<?= $task['batch_id'] ?>&batch_id=<?= $task['batch_id'] ?>" class="task-title">
                                    <?= htmlspecialchars($task['title']) ?>
                                </a>
                                <div class="task-due">
                                    <i class="bi bi-calendar-event"></i> Due: <?= date('M d, Y', strtotime($task['due_date'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <a href="student_tasks.php" class="view-all-btn">
                            <i class="bi bi-arrow-right"></i> All Tasks
                        </a>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-list-task"></i>
                        <p>No tasks assigned yet</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- My Courses Section -->
            <div class="section-card">
                <div class="section-header">
                    <i class="bi bi-journal-bookmark"></i>
                    <h3>My Courses</h3>
                </div>
                
                <?php if (!empty($batches)): ?>
                    <div class="batch-links">
                        <?php foreach ($batches as $batch): ?>
                            <a href="course_dashboard.php?course_id=<?= $batch['batch_id'] ?>&batch_id=<?= $batch['batch_id'] ?>" class="batch-link">
                                <i class="bi bi-book"></i> <?= htmlspecialchars(substr($batch['batch_code'], 0, 8)) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <a href="student_courses.php" class="view-all-btn">
                        <i class="bi bi-arrow-right"></i> View All Courses
                    </a>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-journal-bookmark"></i>
                        <p>No courses enrolled</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>