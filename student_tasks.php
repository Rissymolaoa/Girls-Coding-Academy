<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: login.html");
    exit();
}

include("db.php");

$user_id = $_SESSION['user_id'];

// Fetch student photo and basic info for sidebar
$stmt_user = $conn->prepare("SELECT s.photo, u.username, u.role FROM students s JOIN users u ON s.user_id = u.user_id WHERE s.user_id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$userInfo = $stmt_user->get_result()->fetch_assoc();

// Fetch all notifications for this student (Assignments, Tests, Exams, Events, Messages from teachers)
// For demonstration, assuming a notifications table with columns: notification_id, student_id, type, title, description, date, is_read
// Type can be 'Assignment', 'Test', 'Exam', 'Event', 'Message'
// Order by date descending

$stmt_ntf = $conn->prepare("SELECT notification_id, type, title, description, date, is_read FROM notifications WHERE student_id = ? ORDER BY date DESC");
$stmt_ntf->bind_param("i", $user_id);
$stmt_ntf->execute();
$notifications = $stmt_ntf->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>My Tasks - Notifications</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
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
        margin-bottom: 30px;
        color: #2c3e50;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    }
    .notification {
        background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
        border-radius: 15px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 20px;
        box-shadow: 0 5px 20px rgba(44, 62, 80, 0.1);
        border: 1px solid #e9ecef;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    .notification::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #3498db, #2980b9);
    }
    .notification.unread {
        background: linear-gradient(145deg, #e8f5e8 0%, #f0f8f0 100%);
        border-left: 5px solid #27ae60;
        font-weight: 500;
    }
    .notification:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(52, 73, 94, 0.15);
    }
    .notification-icon {
        font-size: 1.5rem;
        width: 50px;
        height: 50px;
        text-align: center;
        padding: 10px;
        border-radius: 50%;
        color: white;
        flex-shrink: 0;
    }
    .type-Assignment { background-color: #3498db; }
    .type-Test { background-color: #27ae60; }
    .type-Exam { background-color: #e74c3c; }
    .type-Event { background-color: #3498db; }
    .type-Message { background-color: #9b59b6; }
    .notification-details {
        flex: 1;
    }
    .notification-title {
        margin: 0 0 5px 0;
        font-size: 1.1rem;
        color: #2c3e50;
        font-weight: 600;
    }
    .notification-desc {
        margin: 0;
        font-size: 0.95rem;
        color: #7f8c8d;
        line-height: 1.4;
    }
    .notification-date {
        font-size: 0.85rem;
        color: #95a5a6;
        min-width: 120px;
        text-align: right;
        white-space: nowrap;
        font-weight: 500;
    }
    .no-notifications {
        text-align: center;
        color: #7f8c8d;
        font-style: italic;
        padding: 50px 20px;
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(44, 62, 80, 0.1);
    }
    @media (max-width: 768px) {
        main.content {
            margin-left: 0;
            padding: 20px;
        }
        .notification {
            flex-direction: column;
            text-align: center;
            gap: 15px;
        }
        .notification-date {
            align-self: flex-end;
        }
    }
</style>
</head>
<body>

<div class="container-flex">
    <!-- Include the consistent navigation -->
    <?php include("student_navigation.php"); ?>

    <!-- Main Content -->
    <main class="content" role="main" aria-live="polite" aria-relevant="additions removals">
        <h2><i class="bi bi-list-task"></i> My Tasks & Notifications</h2>
        <?php if($notifications->num_rows > 0): ?>
            <?php while($ntf = $notifications->fetch_assoc()): ?>
                <?php
                    $typeClass = 'type-' . htmlspecialchars($ntf['type']);
                    $unreadClass = !$ntf['is_read'] ? 'unread' : '';
                    // Icons per notification type
                    $icons = [
                        'Assignment' => 'bi-file-earmark-text',
                        'Test' => 'bi-pencil-square',
                        'Exam' => 'bi-journal-bookmark',
                        'Event' => 'bi-calendar-event',
                        'Message' => 'bi-chat-dots'
                    ];
                    $iconClass = $icons[$ntf['type']] ?? 'bi-bell';
                ?>
                <article class="notification <?= $unreadClass ?>" tabindex="0" role="article" aria-label="<?= htmlspecialchars($ntf['type']) ?> notification">
                    <div class="notification-icon <?= $typeClass ?>">
                        <i class="bi <?= $iconClass ?>"></i>
                    </div>
                    <div class="notification-details">
                        <p class="notification-title"><?= htmlspecialchars($ntf['title']) ?></p>
                        <p class="notification-desc"><?= htmlspecialchars($ntf['description']) ?></p>
                    </div>
                    <time class="notification-date" datetime="<?= date('c', strtotime($ntf['date'])) ?>">
                        <?= date('M d, Y', strtotime($ntf['date'])) ?>
                    </time>
                </article>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-notifications">No new notifications at the moment. Check back soon!</div>
        <?php endif; ?>
    </main>
</div>

</body>
</html>