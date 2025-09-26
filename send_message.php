<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Africa/Johannesburg');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include("db.php");

$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? null;

if (!$user_id || !in_array($role, ['student', 'teacher', 'parent', 'admin'])) {
    session_destroy();
    header("Location: login.html?redirected=true");
    exit();
}

if (isset($_POST['sender_id'], $_POST['sender_role'], $_POST['receiver_id'], $_POST['receiver_role'], $_POST['subject'], $_POST['message_text'])) {
    $sender_id = (int)$_POST['sender_id'];
    $sender_role = trim($_POST['sender_role']);
    $receiver_id = (int)$_POST['receiver_id'];
    $receiver_role = trim($_POST['receiver_role']);
    $subject = trim($_POST['subject']);
    $message_text = trim($_POST['message_text']);

    if ($sender_id === $user_id && $sender_role === $role && !empty($message_text)) {
        // Check chat status
        $chatStatusQuery = $conn->prepare("
            SELECT is_active
            FROM chat_status
            WHERE (controller_role = 'teacher' AND controller_id = ? AND target_id = ? AND target_role = ?)
               OR (controller_role = 'admin' AND target_id = ? AND target_role = ?)
        ");
        if ($role === 'teacher' || $role === 'admin') {
            $chatStatusQuery->bind_param("iisis", $receiver_id, $user_id, $role, $user_id, $role);
        } else {
            $chatStatusQuery->bind_param("iisis", $user_id, $receiver_id, $receiver_role, $receiver_id, $receiver_role);
        }
        $chatStatusQuery->execute();
        $chatStatusResult = $chatStatusQuery->get_result()->fetch_assoc();
        $chatStatusQuery->close();

        if (!$chatStatusResult || $chatStatusResult['is_active']) {
            $sendQuery = $conn->prepare("
                INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, subject, message_text, sent_at, is_read)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), 0)
            ");
            $sendQuery->bind_param("isiss", $sender_id, $sender_role, $receiver_id, $receiver_role, $subject, $message_text);
            if ($sendQuery->execute()) {
                $_SESSION['message_success'] = "Message sent successfully.";
            } else {
                $_SESSION['message_error'] = "Failed to send message.";
            }
            $sendQuery->close();
        } else {
            $_SESSION['message_error'] = "Chat is disabled by the teacher or admin.";
        }
    } else {
        $_SESSION['message_error'] = "Invalid input or empty message.";
    }
    header("Location: chats.php?chat_with=$receiver_id&chat_role=" . urlencode($receiver_role));
    exit();
} else {
    $_SESSION['message_error'] = "Incomplete form data.";
    header("Location: messages.php");
    exit();
}
ob_end_flush();
?>