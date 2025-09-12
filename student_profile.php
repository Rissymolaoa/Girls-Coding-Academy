<?php
session_start();

// Only admins can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// DB connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Get student ID from URL
$student_id = $_GET['id'] ?? 0;

// Fetch student details
$student = $conn->query("
    SELECT * FROM students WHERE student_id = $student_id
")->fetch_assoc();

// Fetch parents
$parents = $conn->query("
    SELECT * FROM parents WHERE student_id = $student_id
");

// Fetch documents
$documents = $conn->query("
    SELECT * FROM documents WHERE student_id = $student_id
");

// Fetch addresses
$addresses = $conn->query("
    SELECT * FROM addresses WHERE student_id = $student_id
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Profile - Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; font-family: Arial, sans-serif; }
    .profile-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
    .profile-pic { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #007bff; }
    .section-title { font-size: 18px; font-weight: bold; margin-top: 20px; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
    .doc-card, .addr-card { background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0; margin-bottom: 15px; }
  </style>
</head>
<body>
<div class="container mt-4">
  <div class="row">
    <!-- Left column: Student info -->
    <div class="col-md-4">
      <div class="profile-card text-center">
        <img src="<?= $student['photo'] ?? 'default.png' ?>" class="profile-pic mb-2" alt="Student Photo">
        <h5><?= $student['firstName'] . " " . $student['lastName'] ?></h5>
        <p class="text-muted">Roll No: <?= $student['roll_no'] ?></p>
        <p><span class="badge bg-success">Active</span></p>
        <hr>
        <h6>Basic Information</h6>
        <p><strong>Gender:</strong> <?= $student['gender'] ?></p>
        <p><strong>Date of Birth:</strong> <?= $student['dob'] ?></p>
        <p><strong>Blood Group:</strong> <?= $student['blood_group'] ?></p>
        <p><strong>Religion:</strong> <?= $student['religion'] ?></p>
        <p><strong>Category:</strong> <?= $student['category'] ?></p>
        <p><strong>Mother Tongue:</strong> <?= $student['mother_tongue'] ?></p>
        <p><strong>Languages:</strong> <?= $student['languages'] ?></p>
        <a href="fees.php?student_id=<?= $student_id ?>" class="btn btn-primary mt-2">Add Fees</a>
      </div>
    </div>

    <!-- Right column: Parents, Docs, Address -->
    <div class="col-md-8">
      <!-- Parents -->
      <div class="section-title">Parents Information</div>
      <?php while($p = $parents->fetch_assoc()): ?>
        <div class="doc-card d-flex justify-content-between align-items-center">
          <div>
            <strong><?= $p['name'] ?></strong> <br>
            <span class="text-muted"><?= $p['relation'] ?></span>
          </div>
          <div>
            📞 <?= $p['phone'] ?><br>
            ✉️ <?= $p['email'] ?>
          </div>
        </div>
      <?php endwhile; ?>

      <!-- Documents -->
      <div class="section-title">Documents</div>
      <?php while($d = $documents->fetch_assoc()): ?>
        <div class="doc-card d-flex justify-content-between">
          <span><?= $d['doc_name'] ?></span>
          <a href="<?= $d['doc_path'] ?>" class="btn btn-sm btn-dark" download>⬇️</a>
        </div>
      <?php endwhile; ?>

      <!-- Addresses -->
      <div class="section-title">Address</div>
      <?php while($a = $addresses->fetch_assoc()): ?>
        <div class="addr-card">
          <strong><?= $a['type'] ?> Address</strong><br>
          <?= $a['address_line'] ?>, <?= $a['district'] ?>, <?= $a['country'] ?>
        </div>
      <?php endwhile; ?>
    </div>
  </div>
</div>
</body>
</html>
