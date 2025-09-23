<?php
session_start();
include("db.php");

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
if (!$student_id) {
    echo json_encode(null);
    exit();
}

$stmt = $conn->prepare("SELECT transport_mode, route_number, pick_up_point, drop_off_point, guardian_contact, transport_image FROM student_transport_info WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$res = $stmt->get_result();
$data = $res->fetch_assoc();
$stmt->close();

header('Content-Type: application/json');
echo json_encode($data);
exit();

