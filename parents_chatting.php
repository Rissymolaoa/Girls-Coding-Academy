<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

include("db.php");

$user_id = $_SESSION['user_id'];

// Get parent info
$stmt_user = $conn->prepare("
    SELECT u.username, p.photo, u.firstName
    FROM users u
    LEFT JOIN parents p ON u.user_id = p.user_id
    WHERE u.user_id = ?
");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$currentUser = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

// Get all messages
$stmt_messages = $conn->prepare("
    SELECT gm.message_id, gm.sender_user_id, gm.body, gm.sent_at,
           gm.attachment_type, gm.attachment_path, gm.reply_to,
           u.username, p.photo, u.role
    FROM parents_groupchat_messages gm
    JOIN users u ON gm.sender_user_id = u.user_id
    LEFT JOIN parents p ON u.user_id = p.user_id
    ORDER BY gm.sent_at ASC
");
$stmt_messages->execute();
$messagesResult = $stmt_messages->get_result();
$messages = [];
while ($row = $messagesResult->fetch_assoc()) {
    if (empty($row['photo'])) $row['photo'] = 'default_profile.png';
    $messages[] = $row;
}
$stmt_messages->close();

// Get admin user ID
$stmt_admin = $conn->prepare("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
$stmt_admin->execute();
$res_admin = $stmt_admin->get_result();
$adminUserId = $res_admin->fetch_assoc()['user_id'] ?? null;
$stmt_admin->close();

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>Parent Group Chat</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {display:flex; min-height:100vh; margin:0; font-family:Segoe UI, Tahoma;}
.sidebar {width:250px; background:#343a40; color:#fff; position:fixed; height:100vh; padding:20px; overflow-y:auto;}
.sidebar img {width:100px; height:100px; border-radius:50%; object-fit:cover; display:block; margin:0 auto 15px; cursor:pointer;}
.sidebar h3 {text-align:center; font-weight:bold; margin-bottom:20px;}
.sidebar a {color:white; text-decoration:none; display:flex; align-items:center; padding:10px 15px; margin:5px 0; border-radius:6px; transition:.3s;}
.sidebar a i {margin-right:10px;}
.sidebar a:hover, .sidebar a.active {background:#495057;}
.main-content {margin-left:250px; flex:1; background:#f5f5f5; display:flex; flex-direction:column; height:100vh;}
.chat-header {background:#075e54; color:white; text-align:center; padding:15px; font-size:1.3rem; font-weight:bold;}
.chat-messages {flex:1; padding:20px; overflow-y:auto; background:#f5f5f5;}
.message {display:flex; margin-bottom:15px; max-width:70%;}
.message.other .content {background:#fff; padding:10px 15px; border-radius:8px 8px 8px 0; box-shadow:0 1px 3px rgba(0,0,0,0.1);}
.message.self {flex-direction:row-reverse; margin-left:auto;}
.message.self .content {background:#dcf8c6; padding:10px 15px; border-radius:8px 8px 0 8px; box-shadow:0 1px 3px rgba(0,0,0,0.1);}
.message.admin {justify-content:center; max-width:100%;}
.message.admin .content {background:#eef7fb; font-style:italic; padding:8px 14px; border-radius:12px; text-align:center;}
.avatar {width:40px; height:40px; border-radius:50%; object-fit:cover; cursor:pointer;}
.msg-username {font-weight:bold; font-size:0.85rem; margin-bottom:5px; color:#075e54;}
.msg-body {white-space:pre-wrap;}
.msg-meta {font-size:0.75rem; color:gray; text-align:right; margin-top:5px;}
.msg-reply {background:#f0f0f0; padding:5px 10px; border-left:3px solid #075e54; margin-bottom:5px; font-size:0.8rem;}
.attachment img, .attachment video, .attachment audio {max-width:250px; border-radius:8px; margin-top:5px;}
.attachment a {background:#007bff; color:white; padding:5px 10px; border-radius:5px; text-decoration:none;}
.chat-input {padding:10px; background:#fff; display:flex; align-items:center; border-top:1px solid #ddd;}
.chat-input textarea {flex:1; resize:none; border:none; padding:10px 15px; border-radius:25px; outline:none;}
.chat-input button {background:#075e54; color:#fff; border:none; border-radius:50%; width:44px; height:44px; margin-left:10px; cursor:pointer;}
.chat-input button:disabled {opacity:0.5;}
/* Modal styles */
.profile-modal {display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center; z-index:9999;}
.profile-modal .modal-content {background:#fff; padding:20px; border-radius:8px; text-align:center; width:300px;}
.profile-modal img {width:100px; height:100px; border-radius:50%; margin-bottom:10px;}
.profile-modal button {margin-top:10px;}
</style>
</head>
<body>

<div class="sidebar">
    <img src="<?= htmlspecialchars($currentUser['photo'] ?? 'default_profile.png') ?>" alt="Profile" id="profilePic">
    <h3>Parent Panel</h3>
    <a href="parents_dashboard.php" class="<?= $currentPage === 'parent_dashboard.php' ? 'active' : '' ?>"><i class="bi bi-house-door"></i> Dashboard</a>
    <a href="children.php" class="<?= $currentPage === 'children.php' ? 'active' : '' ?>"><i class="bi bi-people"></i> My Children</a>
    <a href="parent_view_attendance.php" class="<?= $currentPage === 'parent_view_attendance.php' ? 'active' : '' ?>"><i class="bi bi-card-checklist"></i> Attendance</a>
    <a href="parent_view_performance.php" class="<?= $currentPage === 'parent_view_performance.php' ? 'active' : '' ?>"><i class="bi bi-graph-up"></i> Performance</a>
    <a href="parent_view_materials.php" class="<?= $currentPage === 'parent_view_materials.php' ? 'active' : '' ?>"><i class="bi bi-folder"></i> Materials</a>
    <a href="parent_messages.php" class="<?= $currentPage === 'parent_messages.php' ? 'active' : '' ?>"><i class="bi bi-envelope"></i> Messages</a>
    <a href="parents_chatting.php" class="<?= $currentPage === 'parents_chatting.php' ? 'active' : '' ?>"><i class="bi bi-chat"></i> Group Chat</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<div class="main-content">
    <div class="chat-header">Parent Group Chat</div>
    <div class="chat-messages" id="chatMessages">
        <?php foreach ($messages as $msg):
            $isSelf = $msg['sender_user_id'] == $user_id;
            $isAdmin = $msg['sender_user_id'] == $adminUserId;
            $classes = "message " . ($isAdmin ? "admin" : ($isSelf ? "self" : "other"));
            $sentAt = date('M j, Y, g:i A', strtotime($msg['sent_at']));
            
            $repliedMessage = null;
            if(!empty($msg['reply_to'])){
                foreach($messages as $m){
                    if($m['message_id'] == $msg['reply_to']){
                        $repliedMessage = $m;
                        break;
                    }
                }
            }
        ?>
        <div class="<?= $classes ?>" data-id="<?= $msg['message_id'] ?>" data-username="<?= htmlspecialchars($msg['username']) ?>" data-body="<?= htmlspecialchars($msg['body']) ?>">
            <?php if (!$isAdmin): ?>
                <img src="<?= htmlspecialchars($msg['photo']) ?>" alt="avatar" class="avatar parent-avatar">
            <?php endif; ?>
            <div class="content">
                <div class="msg-username"><?= $isAdmin ? 'Admin' : htmlspecialchars($msg['username']) ?></div>
                <?php if($repliedMessage): ?>
                    <div class="msg-reply"><?= htmlspecialchars($repliedMessage['username']) ?>: <?= nl2br(htmlspecialchars($repliedMessage['body'])) ?></div>
                <?php endif; ?>
                <div class="msg-body"><?= nl2br(htmlspecialchars($msg['body'])) ?></div>
                <?php if (!empty($msg['attachment_type']) && !empty($msg['attachment_path'])): ?>
                    <div class="attachment">
                        <?php 
                        $path = htmlspecialchars($msg['attachment_path']);
                        switch ($msg['attachment_type']) {
                            case 'picture': echo "<img src='$path' alt='Attachment'>"; break;
                            case 'video': echo "<video controls><source src='$path' type='video/mp4'></video>"; break;
                            case 'audio': echo "<audio controls><source src='$path' type='audio/mpeg'></audio>"; break;
                            default:
                                $file = basename($path);
                                echo "<a href='$path' target='_blank'>Download $file</a>";
                        }
                        ?>
                    </div>
                <?php endif; ?>
                <div class="msg-meta"><?= $sentAt ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <form class="chat-input" id="chatForm" enctype="multipart/form-data">
        <textarea id="msgInput" name="message" placeholder="Type a message"></textarea>
        <input type="file" id="attachmentInput" name="attachment" accept="image/*,audio/*,video/*,.pdf,.doc,.docx,.txt" style="display:none;">
        <button type="button" id="attachBtn" title="Attach file" style="background:#6c757d;">📎</button>
        <button type="submit" id="sendBtn" disabled>▶</button>
    </form>
</div>

<div class="profile-modal" id="profileModal">
    <div class="modal-content">
        <img src="" id="modalPhoto">
        <h5 id="modalUsername"></h5>
        <button class="btn btn-secondary" id="closeModal">Close</button>
    </div>
</div>

<script>
let replyToId = null;

const msgInput = document.getElementById('msgInput');
const sendBtn = document.getElementById('sendBtn');
const attachmentInput = document.getElementById('attachmentInput');
const attachBtn = document.getElementById('attachBtn');
const chatForm = document.getElementById('chatForm');
const chatMessages = document.getElementById('chatMessages');

function toggleSendBtn() {
    sendBtn.disabled = msgInput.value.trim() === '' && attachmentInput.files.length === 0;
}
msgInput.addEventListener('input', toggleSendBtn);
attachmentInput.addEventListener('change', toggleSendBtn);
attachBtn.addEventListener('click', () => attachmentInput.click());

// Right-click to reply
chatMessages.addEventListener('contextmenu', e => {
    e.preventDefault();
    const messageDiv = e.target.closest('.message');
    if(messageDiv && !messageDiv.classList.contains('admin')){
        replyToId = messageDiv.dataset.id;
        alert(`Replying to: ${messageDiv.dataset.username}\n"${messageDiv.dataset.body}"`);
    }
});

// Send message via AJAX
chatForm.addEventListener('submit', async function(e){
    e.preventDefault();
    if(msgInput.value.trim() === '' && attachmentInput.files.length === 0) return;

    sendBtn.disabled = true;
    const formData = new FormData();
    formData.append('message', msgInput.value.trim());
    if(attachmentInput.files.length > 0) formData.append('attachment', attachmentInput.files[0]);
    if(replyToId) formData.append('reply_to', replyToId);

    try {
        const res = await fetch('send_parent_groupchat_message.php', {method:'POST', body:formData});
        const data = await res.json();
        if(data.success){
            const div = document.createElement('div');
            div.className = 'message self';
            div.innerHTML = `<div class="content">
                <div class="msg-username">You</div>
                <div class="msg-body">${msgInput.value.trim().replace(/\n/g,'<br>')}</div>
                <div class="msg-meta">Just now</div>
            </div>`;
            chatMessages.appendChild(div);
            chatMessages.scrollTop = chatMessages.scrollHeight;

            msgInput.value = '';
            attachmentInput.value = '';
            replyToId = null;
            sendBtn.disabled = true;
        } else {
            alert('Error sending message: '+data.error);
        }
    } catch(err){
        alert('Network error.');
    } finally{
        sendBtn.disabled = false;
    }
});

// Profile modal
const profileModal = document.getElementById('profileModal');
const modalPhoto = document.getElementById('modalPhoto');
const modalUsername = document.getElementById('modalUsername');
document.querySelectorAll('.parent-avatar').forEach(av => {
    av.addEventListener('click', ()=>{
        modalPhoto.src = av.src;
        modalUsername.textContent = av.alt;
        profileModal.style.display = 'flex';
    });
});
document.getElementById('closeModal').addEventListener('click', ()=>profileModal.style.display='none');
window.addEventListener('click', e=>{if(e.target===profileModal) profileModal.style.display='none';});
</script>
</body>
</html>
