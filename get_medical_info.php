<?php
session_start();
include("db.php");

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
if (!$student_id) {
    echo json_encode(null);
    exit();
}

$stmt = $conn->prepare("SELECT blood_type, allergies, chronic_conditions, medications, emergency_contact_name, emergency_contact_phone FROM student_medical_info WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

header('Content-Type: application/json');
echo json_encode($data);
exit();
