<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $teacher_id = $_POST['teacher_id'];
    $batch_id   = $_POST['batch_id'];

    // Insert into teacher_batches
    $stmt = $conn->prepare("INSERT INTO teacher_batches (teacher_id, batch_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $teacher_id, $batch_id);

    if ($stmt->execute()) {
        echo "✅ Course & batch assigned to teacher successfully!";
    } else {
        echo "❌ Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
