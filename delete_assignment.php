<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

require_once 'db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_msg'] = "Invalid assignment ID.";
    header("Location: assign_teachers.php"); // ← change to your page name
    exit();
}

$id = (int)$_GET['id'];

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("DELETE FROM course_assignments WHERE assignment_id = ?");
    $stmt->bind_param("i", $id);

    if (!$stmt->execute() || $stmt->affected_rows === 0) {
        throw new Exception("Assignment not found or could not be deleted.");
    }

    $conn->commit();
    $_SESSION['success_msg'] = "Assignment successfully deleted.";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_msg'] = $e->getMessage();
}

$stmt->close();
$conn->close();

header("Location: course_assignment.php"); // ← change to your actual filename
exit();