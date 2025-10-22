<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}
include("db.php");

$user_id = $_SESSION['user_id']; // or student_id if different field

// Fetch admin announcements
$sql_admin = "SELECT announcement_id, message, picture_path, file_path, created_at FROM admin_announcements ORDER BY created_at DESC";
$admin_announcements = $conn->query($sql_admin);

// Fetch student messages, including broadcasts (broadcast=1 => sent to all, student_id=NULL)
$sql_student_msg = "SELECT * FROM student_messages WHERE broadcast = 1 OR student_id = ? ORDER BY created_at DESC";
$stmt_msg = $conn->prepare($sql_student_msg);
$stmt_msg->bind_param("i", $user_id);
$stmt_msg->execute();
$student_messages = $stmt_msg->get_result();

// Fetch posted events
$sql_events = "SELECT event_id, title, event_date, event_time_start, event_time_end, category, location, photo, description FROM events WHERE is_posted=1 ORDER BY event_date DESC, event_time_start";
$posted_events = $conn->query($sql_events);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Student Announcements and Events</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
<style>
  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    margin: 0; padding: 0;
    height: 100vh;
    overflow: hidden;
  }
  .container-flex {
    display: flex;
    height: 100vh;
  }
  main.main-content {
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
  .message-card, .event-card {
    background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(44, 62, 80, 0.1);
    margin-bottom: 25px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    border: 1px solid #e9ecef;
  }
  .message-card::before, .event-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3498db, #2980b9);
  }
  .message-card:hover, .event-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(52, 73, 94, 0.15);
  }
  .message-card h5 {
    margin-bottom: 10px;
    color: #2c3e50;
    font-weight: 600;
  }
  .message-card p {
    margin: 0;
    font-size: 0.95rem;
    color: #7f8c8d;
    line-height: 1.5;
  }
  .timestamp {
    font-size: 0.8rem;
    color: #95a5a6;
    margin-top: 10px;
    font-style: italic;
    display: block;
  }
  .message-attachment img {
    max-width: 100%;
    max-height: 200px;
    border-radius: 8px;
    margin-top: 10px;
  }
  .event-card {
    display: flex;
    gap: 20px;
  }
  .event-card img {
    width: 180px;
    height: 100px;
    object-fit: cover;
    border-radius: 10px;
    flex-shrink: 0;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
  }
  .event-details {
    flex: 1;
  }
  .event-details h5 {
    margin-top: 0;
    margin-bottom: 8px;
    color: #2c3e50;
    font-weight: 600;
  }
  .event-details p {
    margin: 4px 0;
    font-size: 0.9rem;
    color: #7f8c8d;
  }
  .no-content {
    text-align: center;
    color: #7f8c8d;
    font-style: italic;
    padding: 50px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(44, 62, 80, 0.1);
  }

  /* Right sidebar modal styles */
  .modal.right-sidebar .modal-dialog {
    position: fixed;
    right: 0;
    height: 100%;
    margin: 0;
    width: 450px;
    max-width: 100%;
    transform: translateX(100%);
    transition: transform 0.3s ease-out;
  }
  .modal.right-sidebar.show .modal-dialog {
    transform: translateX(0);
  }
  .modal.right-sidebar .modal-content {
    height: 100%;
    border: none;
    border-radius: 0;
    background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
    box-shadow: -3px 0 10px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
  }
  .modal.right-sidebar .modal-header {
    border-bottom: 1px solid #e9ecef;
    background: #f8f9fa;
  }
  .modal.right-sidebar .modal-footer {
    margin-top: auto;
    border-top: 1px solid #e9ecef;
    background: #f8f9fa;
  }
  .modal-body-content {
    overflow-y: auto;
    padding: 25px;
    flex-grow: 1;
  }
  @media (max-width: 768px) {
    main.main-content {
      margin-left: 0;
      padding: 20px;
    }
    .event-card {
      flex-direction: column;
      text-align: center;
    }
    .event-card img {
      width: 100%;
      height: 200px;
    }
  }
</style>
</head>
<body>

<div class="container-flex">
  <!-- Include the consistent navigation -->
  <?php include("student_navigation.php"); ?>

  <main class="main-content" aria-label="Announcements and events">

    <section aria-labelledby="adminAnnouncementsHeading" class="mb-5">
      <h2 id="adminAnnouncementsHeading"><i class="bi bi-megaphone"></i> Admin Announcements</h2>
      <?php if ($admin_announcements->num_rows > 0): ?>
        <?php while($row = $admin_announcements->fetch_assoc()): ?>
          <article class="message-card" tabindex="0" data-type="announcement" data-content="<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>">
            <h5>Admin</h5>
            <p><?= nl2br(htmlspecialchars(substr($row['message'], 0, 100))) ?><?= strlen($row['message']) > 100 ? '...' : '' ?></p>
            <time class="timestamp" datetime="<?= htmlspecialchars($row['created_at']) ?>">
              Posted on <?= date("F j, Y, g:i A", strtotime($row['created_at'])) ?>
            </time>
          </article>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="no-content">No admin announcements at this time.</div>
      <?php endif; ?>
    </section>

    <section aria-labelledby="myMessagesHeading" class="mb-5">
      <h2 id="myMessagesHeading"><i class="bi bi-chat-dots"></i> My Messages</h2>
      <?php if ($student_messages->num_rows > 0): ?>
        <?php while ($msg = $student_messages->fetch_assoc()): ?>
          <article class="message-card" tabindex="0" data-type="message" data-content="<?= htmlspecialchars(json_encode($msg), ENT_QUOTES) ?>">
            <p><?= nl2br(htmlspecialchars(substr($msg['message'], 0, 100))) ?><?= strlen($msg['message']) > 100 ? '...' : '' ?></p>
            <time class="timestamp" datetime="<?= htmlspecialchars($msg['created_at']) ?>">
              Sent on <?= date("F j, Y, g:i A", strtotime($msg['created_at'])) ?>
            </time>
          </article>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="no-content">You have no messages.</div>
      <?php endif; ?>
    </section>

    <section aria-labelledby="upcomingEventsHeading">
      <h2 id="upcomingEventsHeading"><i class="bi bi-calendar-event"></i> Upcoming Events</h2>
      <?php if ($posted_events->num_rows > 0): ?>
        <?php while ($event = $posted_events->fetch_assoc()): ?>
          <article class="event-card" tabindex="0" data-type="event" data-content="<?= htmlspecialchars(json_encode($event), ENT_QUOTES) ?>">
            <?php if (!empty($event['photo'])): ?>
              <img src="<?= htmlspecialchars($event['photo']) ?>" alt="Image for <?= htmlspecialchars($event['title']) ?>">
            <?php else: ?>
              <div style="width:180px; height:100px; background:#eee; display:flex; align-items:center; justify-content:center; color:#bdc3c7; border-radius:10px;">
                <i class="bi bi-image" style="font-size:3rem;"></i>
              </div>
            <?php endif; ?>
            <div class="event-details">
              <h5><?= htmlspecialchars($event['title']) ?></h5>
              <p><strong>Date:</strong> <?= htmlspecialchars(date("d M Y", strtotime($event['event_date']))) ?></p>
              <p><strong>Time:</strong> <?= htmlspecialchars($event['event_time_start'] ?: '-') ?> - <?= htmlspecialchars($event['event_time_end'] ?: '-') ?></p>
              <p><strong>Category:</strong> <?= htmlspecialchars($event['category']) ?></p>
              <p><strong>Location:</strong> <?= htmlspecialchars($event['location']) ?></p>
            </div>
          </article>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="no-content">No posted events available.</div>
      <?php endif; ?>
    </section>

  </main>
</div>

<!-- Right sidebar modal -->
<div class="modal fade right-sidebar" id="rightSidebarModal" tabindex="-1" aria-labelledby="rightSidebarModalLabel" aria-hidden="true" data-bs-backdrop="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="rightSidebarModalLabel">Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body modal-body-content">
        <!-- dynamic content goes here -->
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const modal = new bootstrap.Modal(document.getElementById('rightSidebarModal'));
  const modalTitle = document.getElementById('rightSidebarModalLabel');
  const modalBody = document.querySelector('.modal-body-content');

  document.querySelectorAll('.message-card, .event-card').forEach(card => {
    card.addEventListener('click', () => {
      const type = card.dataset.type;
      const data = JSON.parse(card.dataset.content);

      modalTitle.textContent = type.charAt(0).toUpperCase() + type.slice(1) + ' Details';

      let html = '';
      if (type === 'announcement' || type === 'message') {
        html += `<p>${data.message.replace(/\n/g, '<br>')}</p>`;
        if (data.image_path) {
          html += `<img src="${data.image_path}" alt="Attached image" style="max-width:100%; max-height:300px; margin-top:10px; border-radius:8px;">`;
        }
        if (data.file_path) {
          html += `<p><a href="${data.file_path}" target="_blank" rel="noopener noreferrer">Download attachment</a></p>`;
        }
        html += `<p class="timestamp" style="font-style:italic; color:gray;">${new Date(data.created_at).toLocaleString()}</p>`;
      } else if (type === 'event') {
        html += `<img src="${data.photo || ''}" alt="Event photo" style="max-width: 100%; max-height:220px; object-fit:cover; border-radius:8px; margin-bottom:15px;">`;
        html += `<h4>${data.title}</h4>`;
        html += `<p><strong>Date:</strong> ${data.event_date}</p>`;
        html += `<p><strong>Time:</strong> ${data.event_time_start || '-'} - ${data.event_time_end || '-'}</p>`;
        if (data.description) html += `<p>${data.description.replace(/\n/g,'<br>')}</p>`;
        html += `<p><strong>Category:</strong> ${data.category}</p>`;
        html += `<p><strong>Location:</strong> ${data.location}</p>`;
      }

      modalBody.innerHTML = html;
      modal.show();
    });
  });
</script>

</body>
</html>