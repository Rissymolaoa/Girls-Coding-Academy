<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

include("db.php");

// Get student info
$user_id = $_SESSION['user_id'];
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
$stmt_student->close();

// Get current date and time for real-time filtering
$current_datetime = date('Y-m-d H:i:s');
$current_date = date('Y-m-d');

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';

// Build query based on filter
$query = "
    SELECT 
        cs.schedule_id, cs.class_date, cs.start_time, cs.end_time,
        cs.room_number, cs.room_building, cs.room_capacity, cs.topic, cs.description,
        b.batch_code, b.batch_id, c.courseName, t.teacher_id,
        u.firstName, u.lastName, u.email as teacher_email,
        ce.enrollment_id,
        CONCAT(cs.class_date, ' ', cs.start_time) as class_datetime
    FROM class_schedules cs
    INNER JOIN batches b ON cs.batch_id = b.batch_id
    INNER JOIN courses c ON b.course_id = c.course_id
    INNER JOIN teachers t ON cs.teacher_id = t.teacher_id
    INNER JOIN users u ON t.user_id = u.user_id
    INNER JOIN course_enrollments ce ON b.batch_id = ce.batch_id
    WHERE ce.student_id = ?
";

// Add filter condition
if ($filter === 'upcoming') {
    $query .= " AND CONCAT(cs.class_date, ' ', cs.start_time) > NOW()";
} elseif ($filter === 'past') {
    $query .= " AND CONCAT(cs.class_date, ' ', cs.start_time) <= NOW()";
}

$query .= " ORDER BY cs.class_date ASC, cs.start_time ASC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$classes = [];
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}
$stmt->close();

// Get counts for statistics
$upcoming_query = "
    SELECT COUNT(*) as count
    FROM class_schedules cs
    INNER JOIN batches b ON cs.batch_id = b.batch_id
    INNER JOIN course_enrollments ce ON b.batch_id = ce.batch_id
    WHERE ce.student_id = ?
    AND CONCAT(cs.class_date, ' ', cs.start_time) > NOW()
";

$stmt_upcoming = $conn->prepare($upcoming_query);
$stmt_upcoming->bind_param("i", $student_id);
$stmt_upcoming->execute();
$result_upcoming = $stmt_upcoming->get_result();
$upcoming_count = $result_upcoming->fetch_assoc()['count'];
$stmt_upcoming->close();

$total_query = "
    SELECT COUNT(*) as count
    FROM class_schedules cs
    INNER JOIN batches b ON cs.batch_id = b.batch_id
    INNER JOIN course_enrollments ce ON b.batch_id = ce.batch_id
    WHERE ce.student_id = ?
";

$stmt_total = $conn->prepare($total_query);
$stmt_total->bind_param("i", $student_id);
$stmt_total->execute();
$result_total = $stmt_total->get_result();
$total_count = $result_total->fetch_assoc()['count'];
$stmt_total->close();

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Scheduled Classes - Girls Coding Academy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
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
        padding: 30px;
        overflow-y: auto;
    }
    h2 {
        margin-bottom: 10px;
        color: #2c3e50;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    }
    .page-header {
        margin-bottom: 30px;
    }
    .page-header p {
        color: #7f8c8d;
        font-size: 0.95rem;
    }
    .filter-buttons {
        display: flex;
        gap: 10px;
        margin-bottom: 25px;
        flex-wrap: wrap;
    }
    .filter-btn {
        padding: 8px 16px;
        border: 2px solid #e9ecef;
        background: white;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        color: #7f8c8d;
    }
    .filter-btn:hover {
        border-color: #e74c3c;
        color: #e74c3c;
    }
    .filter-btn.active {
        background: linear-gradient(135deg, #e74c3c, #f39c12);
        color: white;
        border-color: #e74c3c;
        box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
    }
    .class-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 3px 12px rgba(0,0,0,0.08);
        border-left: 5px solid #3498db;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    .class-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    }
    .class-card.past {
        border-left-color: #95a5a6;
        opacity: 0.85;
    }
    .class-card.past::after {
        content: 'PAST CLASS';
        position: absolute;
        top: 10px;
        right: 10px;
        background: #95a5a6;
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: bold;
    }
    .class-card.today {
        border-left-color: #27ae60;
        background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);
    }
    .class-card.today::after {
        content: 'TODAY';
        position: absolute;
        top: 10px;
        right: 10px;
        background: #27ae60;
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: bold;
    }
    .class-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }
    .class-header h3 {
        margin: 0;
        color: #2c3e50;
        font-weight: 600;
    }
    .course-badge {
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    .topic-section {
        background: #f8f9fa;
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 15px;
        border-left: 3px solid #3498db;
    }
    .topic-section strong {
        display: block;
        color: #2c3e50;
        margin-bottom: 5px;
    }
    .topic-section p {
        margin: 0;
        color: #7f8c8d;
        font-size: 0.95rem;
    }
    .class-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }
    .detail-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    .detail-item i {
        font-size: 1.2rem;
        color: #3498db;
        min-width: 20px;
    }
    .detail-item .label {
        font-size: 0.85rem;
        color: #7f8c8d;
        display: block;
    }
    .detail-item .value {
        font-size: 1rem;
        font-weight: 600;
        color: #2c3e50;
        display: block;
    }
    .teacher-info {
        background: linear-gradient(135deg, #ecf0f1 0%, #bdc3c7 100%);
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 15px;
    }
    .teacher-info strong {
        display: block;
        color: #2c3e50;
        margin-bottom: 5px;
    }
    .teacher-info p {
        margin: 0;
        color: #34495e;
        font-size: 0.95rem;
    }
    .class-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .action-btn {
        padding: 8px 14px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 5px;
        text-decoration: none;
        white-space: nowrap;
    }
    .btn-add-calendar {
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
    }
    .btn-add-calendar:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        color: white;
    }
    .btn-contact-teacher {
        background: linear-gradient(135deg, #9b59b6, #8e44ad);
        color: white;
    }
    .btn-contact-teacher:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(155, 89, 182, 0.3);
        color: white;
    }
    .btn-directions {
        background: linear-gradient(135deg, #27ae60, #229954);
        color: white;
    }
    .btn-directions:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        color: white;
    }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: rgba(255,255,255,0.8);
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .empty-state i {
        font-size: 4rem;
        color: #bdc3c7;
        margin-bottom: 20px;
    }
    .empty-state h3 {
        color: #7f8c8d;
        font-weight: 500;
    }
    .empty-state p {
        color: #95a5a6;
    }
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    .stat-box {
        background: white;
        padding: 15px;
        border-radius: 10px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border-top: 3px solid #3498db;
    }
    .stat-box i {
        font-size: 1.5rem;
        color: #3498db;
        margin-bottom: 8px;
    }
    .stat-box .label {
        font-size: 0.85rem;
        color: #7f8c8d;
    }
    .stat-box .value {
        font-size: 1.8rem;
        font-weight: bold;
        color: #2c3e50;
    }
    @media (max-width: 768px) {
        .container-flex {
            flex-direction: column;
        }
        .content {
            padding: 20px;
        }
        .class-details {
            grid-template-columns: 1fr;
        }
        .filter-buttons {
            flex-direction: column;
        }
        .filter-btn {
            width: 100%;
            text-align: center;
        }
    }
</style>
</head>
<body>

<div class="container-flex">
    <?php include("student_navigation.php"); ?>

    <main class="content" role="main">
        <div class="page-header">
            <h2><i class="bi bi-calendar3-range"></i> Scheduled Classes</h2>
            <p>View all your upcoming class sessions and room locations</p>
        </div>

        <div class="stats-row">
            <div class="stat-box">
                <i class="bi bi-calendar-check"></i>
                <div class="label">Upcoming Classes</div>
                <div class="value"><?= $upcoming_count ?></div>
            </div>
            <div class="stat-box">
                <i class="bi bi-book"></i>
                <div class="label">Total Scheduled</div>
                <div class="value"><?= $total_count ?></div>
            </div>
        </div>

        <div class="filter-buttons">
            <button class="filter-btn <?= $filter === 'upcoming' ? 'active' : '' ?>" onclick="location.href='?filter=upcoming'">
                <i class="bi bi-arrow-right-circle"></i> Upcoming Classes
            </button>
            <button class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>" onclick="location.href='?filter=all'">
                <i class="bi bi-list"></i> All Classes
            </button>
            <button class="filter-btn <?= $filter === 'past' ? 'active' : '' ?>" onclick="location.href='?filter=past'">
                <i class="bi bi-archive"></i> Past Classes
            </button>
        </div>

        <?php if (!empty($classes)): ?>
            <div class="classes-container">
                <?php foreach ($classes as $class):
                    $class_datetime = strtotime($class['class_datetime']);
                    $is_past = $class_datetime < time();
                    $is_today = date('Y-m-d', $class_datetime) === date('Y-m-d');
                    $class_status = $is_past ? 'past' : ($is_today ? 'today' : '');
                ?>
                    <div class="class-card <?= $class_status ?>">
                        <div class="class-header">
                            <div>
                                <h3><?= htmlspecialchars($class['topic']) ?></h3>
                                <span class="course-badge"><?= htmlspecialchars($class['batch_code']) ?></span>
                            </div>
                        </div>

                        <?php if ($class['description']): ?>
                            <div class="topic-section">
                                <strong>Description</strong>
                                <p><?= htmlspecialchars(substr($class['description'], 0, 100)) . (strlen($class['description']) > 100 ? '...' : '') ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="class-details">
                            <div class="detail-item">
                                <i class="bi bi-calendar-event"></i>
                                <div>
                                    <span class="label">Date</span>
                                    <span class="value"><?= date('M d, Y', strtotime($class['class_date'])) ?></span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-clock"></i>
                                <div>
                                    <span class="label">Time</span>
                                    <span class="value"><?= date('H:i', strtotime($class['start_time'])) ?> - <?= date('H:i', strtotime($class['end_time'])) ?></span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-door-closed"></i>
                                <div>
                                    <span class="label">Room</span>
                                    <span class="value">Room <?= htmlspecialchars($class['room_number']) ?></span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-building"></i>
                                <div>
                                    <span class="label">Building</span>
                                    <span class="value"><?= htmlspecialchars($class['room_building']) ?></span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-people"></i>
                                <div>
                                    <span class="label">Capacity</span>
                                    <span class="value"><?= $class['room_capacity'] ?> Students</span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-book-half"></i>
                                <div>
                                    <span class="label">Course</span>
                                    <span class="value"><?= htmlspecialchars($class['courseName']) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="teacher-info">
                            <strong><i class="bi bi-person-fill"></i> Instructor</strong>
                            <p><?= htmlspecialchars($class['firstName'] . ' ' . $class['lastName']) ?></p>
                            <small><i class="bi bi-envelope"></i> <?= htmlspecialchars($class['teacher_email']) ?></small>
                        </div>

                        <?php if (!$is_past): ?>
                            <div class="class-actions">
                                <button class="action-btn btn-add-calendar" onclick="addToCalendar('<?= addslashes($class['topic']) ?>', '<?= $class['class_date'] ?>', '<?= $class['start_time'] ?>', '<?= $class['end_time'] ?>', 'Room <?= $class['room_number'] ?>, <?= $class['room_building'] ?>')">
                                    <i class="bi bi-calendar-plus"></i> Add to Calendar
                                </button>
                                <a href="mailto:<?= htmlspecialchars($class['teacher_email']) ?>" class="action-btn btn-contact-teacher">
                                    <i class="bi bi-envelope"></i> Contact Teacher
                                </a>
                                <a href="#" class="action-btn btn-directions" onclick="showDirections('<?= addslashes($class['room_building']) ?>', '<?= $class['room_number'] ?>'); return false;">
                                    <i class="bi bi-geo-alt"></i> Get Directions
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <h3>No <?= $filter === 'past' ? 'Past' : ($filter === 'upcoming' ? 'Upcoming' : '') ?> Classes</h3>
                <p><?= $filter === 'past' ? 'You don\'t have any past classes.' : ($filter === 'upcoming' ? 'All your classes are complete or you have no upcoming sessions.' : 'No scheduled classes found.') ?></p>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
    function addToCalendar(title, date, startTime, endTime, location) {
        const eventDate = date.replace(/-/g, '');
        const startDateTime = eventDate + 'T' + startTime.replace(/:/g, '');
        const endDateTime = eventDate + 'T' + endTime.replace(/:/g, '');
        
        const icsContent = `BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Girls Coding Academy//Class Schedule//EN
BEGIN:VEVENT
UID:${Date.now()}@girlscodingacademy.com
DTSTAMP:${new Date().toISOString().replace(/[-:]/g, '').split('.')[0]}Z
DTSTART:${startDateTime}
DTEND:${endDateTime}
SUMMARY:${title}
LOCATION:${location}
DESCRIPTION:Class session for Girls Coding Academy
END:VEVENT
END:VCALENDAR`;

        const blob = new Blob([icsContent], { type: 'text/calendar' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `${title.replace(/\s+/g, '_')}.ics`;
        link.click();
        
        alert('Calendar event downloaded! Import it into your calendar application.');
    }

    function showDirections(building, room) {
        const address = `${building} Room ${room}, Girls Coding Academy`;
        const mapsUrl = `https://www.google.com/maps/search/${encodeURIComponent(address)}`;
        window.open(mapsUrl, '_blank');
    }
</script>

</body>
</html>