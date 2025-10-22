<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

include('db.php');

$user_id = $_SESSION['user_id'];
$message = trim($_POST['message'] ?? '');
$attachment_type = null;
$attachment_path = null;

// Handle attachment if uploaded
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['attachment'];
    $allowedTypes = [
        'image/jpeg' => 'picture',
        'image/png' => 'picture',
        'image/gif' => 'picture',
        'video/mp4' => 'video',
        'audio/mpeg' => 'audio',
        'application/pdf' => 'document',
        'application/msword' => 'document',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'document',
        'text/plain' => 'document'
    ];

    if (!array_key_exists($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type']);
        exit;
    }

    $attachment_type = $allowedTypes[$file['type']];
    $uploadDir = 'uploads/groupchat/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $filename = time() . '_' . basename($file['name']);
    $targetPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        echo json_encode(['success' => false, 'error' => 'Failed to upload attachment']);
        exit;
    }

    $attachment_path = $targetPath;
}

// Insert message into database
$stmt = $conn->prepare("INSERT INTO parents_groupchat_messages (sender_user_id, body, sent_at, attachment_type, attachment_path) VALUES (?, ?, NOW(), ?, ?)");
$stmt->bind_param("isss", $user_id, $message, $attachment_type, $attachment_path);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database insert failed']);
}

$stmt->close();
$conn->close();
