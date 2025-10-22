<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

include("db.php");

$user_id = $_SESSION['user_id'];

// Get student_id
$stmt_student = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt_student->bind_param("i", $user_id);
$stmt_student->execute();
$student = $stmt_student->get_result()->fetch_assoc();
$student_id = $student['student_id'];

// Fetch student's attendance sessions
$attendance_sessions = $conn->query("
    SELECT DISTINCT session_id as date, status 
    FROM attendance 
    WHERE student_id = $student_id 
    ORDER BY session_id DESC
");

// Fetch posted events for the student
$events = $conn->query("
    SELECT event_id, title, description, event_date, event_time_start, event_time_end, category, location, photo 
    FROM events 
    WHERE is_posted = 1 
    ORDER BY event_date ASC, event_time_start ASC
");

// Prepare calendar events array for JS
$calendar_events = [];
while ($row = $events->fetch_assoc()) {
    $calendar_events[] = [
        'title' => $row['title'],
        'start' => $row['event_date'] . 'T' . ($row['event_time_start'] ?: '09:00:00'),
        'end' => $row['event_date'] . 'T' . ($row['event_time_end'] ?: '17:00:00'),
        'description' => $row['description'],
        'category' => $row['category'],
        'location' => $row['location'],
        'photo' => $row['photo'],
        'color' => $row['category'] === 'Competition' ? '#e74c3c' : ($row['category'] === 'Festival' ? '#f39c12' : '#3498db')
    ];
}

// Attendance markers
$attendance_markers = [];
while ($row = $attendance_sessions->fetch_assoc()) {
    $color = $row['status'] === 'Present' ? '#27ae60' : ($row['status'] === 'Absent' ? '#e74c3c' : '#f39c12');
    $attendance_markers[] = [
        'title' => 'Attendance: ' . $row['status'],
        'start' => $row['date'],
        'color' => $color,
        'display' => 'background'
    ];
}

$all_events = json_encode(array_merge($calendar_events, $attendance_markers));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>My Calendar - Student Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet" />
<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        margin: 0;
        padding: 0;
        height: 100vh;
        overflow: hidden;
    }
    .container-flex {
        display: flex;
        height: 100vh;
    }
    main.content {
        flex: 1;
        padding: 40px 50px;
        margin-left: 280px;
        overflow-y: auto;
        height: 100vh;
    }
    h2 {
        margin-bottom: 20px;
        color: #2c3e50;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    }
    #calendar {
        background: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 5px 20px rgba(44, 62, 80, 0.1);
        overflow: hidden;
    }
    .fc-theme-standard td, .fc-theme-standard th {
        border-color: #e9ecef;
    }
    .fc-event {
        border-radius: 6px;
        font-size: 0.85rem;
        padding: 2px 5px;
    }
    .fc-daygrid-event-dot {
        border: 2px solid white;
    }
    .event-details {
        position: absolute;
        background: white;
        border-radius: 10px;
        padding: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        z-index: 1000;
        max-width: 300px;
        display: none;
    }
    .event-details.show {
        display: block;
    }
    .event-details img {
        width: 100%;
        height: 100px;
        object-fit: cover;
        border-radius: 8px;
        margin-bottom: 10px;
    }
    .no-events {
        text-align: center;
        color: #7f8c8d;
        font-style: italic;
        padding: 50px;
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(44, 62, 80, 0.1);
    }
    @media (max-width: 768px) {
        main.content {
            margin-left: 0;
            padding: 20px;
        }
        #calendar {
            padding: 10px;
        }
    }
</style>
</head>
<body>

<div class="container-flex">
    <!-- Include the consistent navigation -->
    <?php include("student_navigation.php"); ?>

    <main class="content" role="main">
        <h2><i class="bi bi-calendar-event"></i> My Calendar</h2>
        <p class="mb-4">View your schedule, events, and attendance overview.</p>

        <div id="calendar"></div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('calendar');
        const events = <?php echo $all_events; ?>;

        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            events: events,
            eventClick: function(info) {
                if (info.event.extendedProps.description) {
                    // Show tooltip-like details
                    let tooltip = document.createElement('div');
                    tooltip.className = 'event-details';
                    tooltip.innerHTML = `
                        <img src="${info.event.extendedProps.photo || ''}" alt="Event image" style="display: ${info.event.extendedProps.photo ? 'block' : 'none'};">
                        <h6>${info.event.title}</h6>
                        ${info.event.extendedProps.description ? `<p>${info.event.extendedProps.description}</p>` : ''}
                        <p><strong>Category:</strong> ${info.event.extendedProps.category || 'N/A'}</p>
                        <p><strong>Location:</strong> ${info.event.extendedProps.location || 'N/A'}</p>
                    `;
                    tooltip.style.left = info.jsEvent.pageX + 10 + 'px';
                    tooltip.style.top = info.jsEvent.pageY + 10 + 'px';
                    document.body.appendChild(tooltip);
                    tooltip.classList.add('show');

                    // Remove on next click
                    setTimeout(() => {
                        document.body.removeChild(tooltip);
                    }, 5000);
                }
            },
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek'
            },
            height: 'auto',
            aspectRatio: 1.8,
            eventDisplay: 'block'
        });

        calendar.render();
    });
</script>

</body>
</html>