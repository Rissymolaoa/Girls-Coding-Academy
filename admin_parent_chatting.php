<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include("db.php");

$admin_id = $_SESSION['user_id'];

// Get all messages
$stmt_messages = $conn->prepare("
    SELECT gm.message_id, gm.sender_user_id, gm.body, gm.sent_at,
           gm.attachment_type, gm.attachment_path, gm.reply_to,
           u.username, u.role, IFNULL(p.photo,'default_profile.png') AS photo
    FROM parents_groupchat_messages gm
    JOIN users u ON gm.sender_user_id = u.user_id
    LEFT JOIN parents p ON u.user_id = p.user_id
    ORDER BY gm.sent_at ASC
");
$stmt_messages->execute();
$res = $stmt_messages->get_result();
$messages = $res->fetch_all(MYSQLI_ASSOC);
$stmt_messages->close();

// Get group chat settings (blocked or not)
$settings = $conn->query("SELECT * FROM groupchat_settings LIMIT 1")->fetch_assoc();
$is_blocked = $settings['is_blocked'] ?? 0;

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>Admin - Parent Group Chat</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {display:flex; min-height:100vh; margin:0; font-family:Segoe UI, Tahoma; background-color: #f8f9fa;}
.sidebar {width:250px; background:#2c3e50; color:#fff; position:fixed; height:100vh; padding:20px; overflow-y:auto;}
.sidebar a {color:white; text-decoration:none; display:flex; align-items:center; padding:10px 15px; margin:5px 0; border-radius:6px; transition:.3s;}
.sidebar a i {margin-right:10px;}
.sidebar a:hover, .sidebar a.active {background:#34495e;}
.main-content {margin-left:250px; flex:1; display:flex; flex-direction:column; height:100vh;}
.chat-header {background:#e67e22; color:white; text-align:center; padding:15px; font-size:1.3rem; font-weight:bold; display:flex; justify-content:space-between; align-items:center;}
.chat-messages {flex:1; padding:20px; overflow-y:auto; background:#ecf0f1;}
.message {display:flex; margin-bottom:15px; max-width:70%; position:relative;}
.message.other .content {background:#fff; padding:10px 15px; border-radius:8px 8px 8px 0; box-shadow:0 1px 3px rgba(0,0,0,0.1);}
.message.self {flex-direction:row-reverse; margin-left:auto;}
.message.self .content {background:#f39c12; color:#fff; padding:10px 15px; border-radius:8px 8px 0 8px; box-shadow:0 1px 3px rgba(0,0,0,0.1);}
.message.admin {justify-content:center; max-width:100%;}
.message.admin .content {background:#bdc3c7; font-style:italic; padding:8px 14px; border-radius:12px; text-align:center;}
.avatar {width:40px; height:40px; border-radius:50%; object-fit:cover; cursor:pointer;}
.msg-username {font-weight:bold; font-size:0.85rem; margin-bottom:5px; color:#e67e22;}
.msg-body {white-space:pre-wrap;}
.msg-reply {background:#f0f0f0; padding:5px 10px; border-left:3px solid #e67e22; margin-bottom:5px; font-size:0.8rem;}
.attachment img, .attachment video, .attachment audio {max-width:250px; border-radius:8px; margin-top:5px;}
.attachment a {background:#e67e22; color:white; padding:5px 10px; border-radius:5px; text-decoration:none;}
.chat-input {padding:10px; background:#fff; display:flex; align-items:center; border-top:1px solid #ddd;}
.chat-input textarea {flex:1; resize:none; border:none; padding:10px 15px; border-radius:25px; outline:none;}
.chat-input button {background:#e67e22; color:#fff; border:none; border-radius:50%; width:44px; height:44px; margin-left:10px; cursor:pointer;}
.chat-input button:disabled {opacity:0.5;}
.profile-modal {display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center; z-index:9999;}
.profile-modal .modal-content {background:#fff; padding:20px; border-radius:8px; text-align:center; width:300px;}
.profile-modal img {width:100px; height:100px; border-radius:50%; margin-bottom:10px;}
</style>
</head>
<body>

<div class="sidebar">
    <h3>Admin Panel</h3>
    <a href="admin_dashboard.php" class="<?= $currentPage === 'admin_dashboard.php' ? 'active' : '' ?>"><i class="bi bi-house-door"></i> Dashboard</a>
    <a href="parents_groupchat_admin.php" class="active"><i class="bi bi-chat"></i> Parent Chat</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<div class="main-content">
    <div class="chat-header">
        Parent Group Chat
        <button class="btn btn-sm btn-warning" id="toggleBlock">
            <?= $is_blocked ? 'Unblock Parents' : 'Block Parents' ?>
        </button>
    </div>

    <div class="chat-messages" id="chatMessages">
        <?php foreach($messages as $msg):
            $isAdmin = $msg['role'] === 'admin';
            $classes = $isAdmin ? 'message admin' : ($msg['sender_user_id']==$admin_id ? 'message self' : 'message other');
            $sentAt = date('M j, Y, g:i A', strtotime($msg['sent_at']));
            $repliedMessage = null;
            if(!empty($msg['reply_to'])){
                foreach($messages as $m){
                    if($m['message_id']==$msg['reply_to']) { $repliedMessage = $m; break; }
                }
            }
        ?>
        <div class="<?= $classes ?>" data-id="<?= $msg['message_id'] ?>" data-username="<?= htmlspecialchars($msg['username']) ?>" data-body="<?= htmlspecialchars($msg['body']) ?>">
            <?php if(!$isAdmin): ?>
                <img src="<?= htmlspecialchars($msg['photo']) ?>" class="avatar parent-avatar">
            <?php endif; ?>
            <div class="content">
                <div class="msg-username"><?= $isAdmin ? 'Admin' : htmlspecialchars($msg['username']) ?></div>
                <?php if($repliedMessage): ?>
                    <div class="msg-reply"><?= htmlspecialchars($repliedMessage['username']) ?>: <?= nl2br(htmlspecialchars($repliedMessage['body'])) ?></div>
                <?php endif; ?>
                <div class="msg-body"><?= nl2br(htmlspecialchars($msg['body'])) ?></div>
                <?php if(!empty($msg['attachment_type']) && !empty($msg['attachment_path'])): ?>
                    <div class="attachment">
                        <?php
                        $path = htmlspecialchars($msg['attachment_path']);
                        switch($msg['attachment_type']){
                            case 'picture': echo "<img src='$path'>"; break;
                            case 'video': echo "<video controls><source src='$path' type='video/mp4'></video>"; break;
                            case 'audio': echo "<audio controls><source src='$path' type='audio/mpeg'></audio>"; break;
                            default: $file=basename($path); echo "<a href='$path' target='_blank'>Download $file</a>";
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
        <input type="file" id="attachmentInput" name="attachment" style="display:none;">
        <button type="button" id="attachBtn">📎</button>
        <button type="submit" id="sendBtn">▶</button>
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
const chatForm = document.getElementById('chatForm');
const msgInput = document.getElementById('msgInput');
const sendBtn = document.getElementById('sendBtn');
const attachmentInput = document.getElementById('attachmentInput');
const attachBtn = document.getElementById('attachBtn');
const chatMessages = document.getElementById('chatMessages');
let replyToId = null;

// Enable attachment
attachBtn.addEventListener('click', ()=>attachmentInput.click());

// Right-click on message
chatMessages.addEventListener('contextmenu', e=>{
    e.preventDefault();
    const messageDiv = e.target.closest('.message');
    if(!messageDiv) return;

    const senderRole = messageDiv.dataset.username.toLowerCase() === 'admin' ? 'admin' : 'parent';

    // Shift+RightClick => Delete, otherwise Reply
    if(senderRole !== 'admin' && e.shiftKey){
        if(confirm('Delete this message?')){
            const formData = new FormData();
            formData.append('message_id', messageDiv.dataset.id);
            fetch('delete_parent_message.php',{method:'POST', body:formData})
            .then(res=>res.json())
            .then(data=>{
                if(data.success) messageDiv.remove();
                else alert(data.error);
            });
        }
    } else if(senderRole !== 'admin'){
        replyToId = messageDiv.dataset.id;
        alert(`Replying to: ${messageDiv.dataset.username}\n"${messageDiv.dataset.body}"`);
    }
});

// Send message
chatForm.addEventListener('submit', async e=>{
    e.preventDefault();
    if(msgInput.value.trim()==='' && attachmentInput.files.length===0) return;
    const formData = new FormData();
    formData.append('message', msgInput.value.trim());
    if(attachmentInput.files.length>0) formData.append('attachment', attachmentInput.files[0]);
    if(replyToId) formData.append('reply_to', replyToId);
    try{
        const res = await fetch('send_parent_groupchat_message.php',{method:'POST', body:formData});
        const data = await res.json();
        if(data.success) location.reload();
        else alert(data.error);
    }catch(err){alert('Network error');}
});

// Block/unblock parents
document.getElementById('toggleBlock').addEventListener('click', async ()=>{
    const res = await fetch('toggle_parent_block.php');
    const data = await res.json();
    if(data.success) location.reload();
    else alert(data.error);
});

// Profile modal
const profileModal = document.getElementById('profileModal');
const modalPhoto = document.getElementById('modalPhoto');
const modalUsername = document.getElementById('modalUsername');
document.querySelectorAll('.parent-avatar').forEach(av=>{
    av.addEventListener('click', ()=>{
        modalPhoto.src = av.src;
        modalUsername.textContent = av.alt;
        profileModal.style.display='flex';
    });
});
document.getElementById('closeModal').addEventListener('click', ()=>profileModal.style.display='none');
</script>
</body>
</html>