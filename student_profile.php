<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: login.html");
    exit();
}

include("db.php");

$user_id = $_SESSION['user_id'];

// Fetch user info and linked address
$stmt = $conn->prepare("
    SELECT u.user_id, u.firstName, u.lastName, u.email, u.phone, u.gender, u.IDNumber,
           u.username, u.document, a.address1, a.streetName, a.postalCode, a.district, a.country, s.student_id, s.photo
    FROM users u
    LEFT JOIN addresses a ON u.address_id = a.address_id
    LEFT JOIN students s ON s.user_id = u.user_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$student_id = $student['student_id'];

// Fetch medical info
$stmt_med = $conn->prepare("SELECT * FROM student_medical_info WHERE student_id = ?");
$stmt_med->bind_param("i", $student_id);
$stmt_med->execute();
$medical = $stmt_med->get_result()->fetch_assoc();

// Fetch transport info
$stmt_tr = $conn->prepare("SELECT * FROM student_transport_info WHERE student_id = ?");
$stmt_tr->bind_param("i", $student_id);
$stmt_tr->execute();
$transport = $stmt_tr->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Profile</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background:#f9f9f9; font-family:Arial,sans-serif; }
  header{background:linear-gradient(90deg,#7b2cbf,#5a189a);color:#fff;padding:18px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,0.12)}
  header h1{margin:0;font-size:22px;font-weight:600}
.sidebar { width: 240px; background:#5a189a; padding:20px; min-height:100vh; color:#fff; }
.sidebar h3 { color:white; margin-bottom:15px; text-align:center; }
.sidebar a { display:flex; align-items:center; gap:10px; color:white; text-decoration:none; padding:10px; margin:5px 0; border-radius:6px; transition:background 0.2s; }
.sidebar a:hover, .sidebar a.active { background:#9d4edd; }
.admin-pic { width:90px; height:90px; border-radius:50%; margin-bottom:15px; border:2px solid #1abc9c; object-fit:cover; display:block; margin:auto; }

.content { flex:1; padding:30px; }
.profile-photo-container { text-align:center; margin-bottom:20px; }
.profile-photo-container img { width:150px; height:150px; border-radius:50%; border:3px solid #1abc9c; object-fit:cover; margin-bottom:10px; }
.profile-photo-container label { display:inline-block; padding:5px 10px; background:#7b2cbf; color:white; border-radius:4px; cursor:pointer; }
.profile-photo-container label:hover { background:#5a189a; }
input#photoInput { display:none; }
form { background:white; padding:20px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1); max-width:1000px; margin:auto; }
form h3 { margin-bottom:15px; color:#5a189a; }
button { background:#7b2cbf; color:white; padding:10px 20px; border:none; border-radius:4px; cursor:pointer; }
button:hover { background:#5a189a; }
</style>
</head>
<body>

<header>
<h1>Student Profile</h1>
</header>

<div class="d-flex">
  <!-- Sidebar -->
  <div class="sidebar">
    <img src="admin.png" alt="Student Picture" class="admin-pic">
    <h3 style="text-align:center;margin-bottom:10px;">Navigation</h3>
    <a href="student.php"><i class="bi bi-house-door"></i> Home</a>
     <a href="student_profile.php"class="active"><i class="bi bi-person-circle"></i> My Profile</a>
    <a href="student_courses.php"><i class="bi bi-journal-bookmark"></i> My Courses</a>
     <a href="#"><i class="bi bi-megaphone"></i> Announcements</a>
     <a href="#"><i class="bi bi-calendar-event"></i> My Calendar</a>
    <a href="attendance.php" ><i class="bi bi-card-checklist"></i> My Schedule</a>
    <a href="student_marks.php"><i class="bi bi-bar-chart-line-fill"></i> My Grades</a> 
    <a href="student_gradebook.php"><i class="bi bi-graph-up"></i> My Performance</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </div>

  <!-- Content -->
  <div class="content flex-fill">
    <form action="student_profile_update.php" method="POST" enctype="multipart/form-data">
      
      <!-- Profile Photo -->
      <div class="profile-photo-container">
        <img src="<?php echo !empty($student['photo']) ? $student['photo'] : 'admin.jpg'; ?>" alt="Student Photo">
        <label for="photoInput">Change Photo</label>
        <input type="file" name="photo" id="photoInput">
      </div>

      <!-- Personal Info -->
      <h3>Personal Information</h3>
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" class="form-control form-control-sm" name="firstName" placeholder="First Name" value="<?php echo $student['firstName']; ?>" required>
          </div>
        </div>
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" class="form-control form-control-sm" name="lastName" placeholder="Last Name" value="<?php echo $student['lastName']; ?>" required>
          </div>
        </div>
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control form-control-sm" name="email" placeholder="Email" value="<?php echo $student['email']; ?>" required>
          </div>
        </div>
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-phone"></i></span>
            <input type="text" class="form-control form-control-sm" name="phone" placeholder="Phone" value="<?php echo $student['phone']; ?>">
          </div>
        </div>
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-gender-ambiguous"></i></span>
            <input type="text" class="form-control form-control-sm" name="gender" placeholder="Gender" value="<?php echo $student['gender']; ?>">
          </div>
        </div>
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-credit-card"></i></span>
            <input type="text" class="form-control form-control-sm" name="IDNumber" placeholder="ID Number" value="<?php echo $student['IDNumber']; ?>">
          </div>
        </div>
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
            <input type="text" class="form-control form-control-sm" name="username" placeholder="Username" value="<?php echo $student['username']; ?>">
          </div>
        </div>
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-file-earmark-text"></i></span>
            <input type="text" class="form-control form-control-sm" name="document" placeholder="Document" value="<?php echo $student['document']; ?>">
          </div>
        </div>
      </div>

      <!-- Address Info -->
      <h3>Address Information</h3>
      <div class="row g-3 mb-4">
        <?php 
        $address_fields = ['address1'=>'House/Flat No.', 'streetName'=>'Street Name', 'postalCode'=>'Postal Code', 'district'=>'District', 'country'=>'Country'];
        $icons = ['address1'=>'bi-house', 'streetName'=>'bi-geo-alt', 'postalCode'=>'bi-mailbox', 'district'=>'bi-map', 'country'=>'bi-globe'];
        foreach($address_fields as $field=>$label): ?>
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text"><i class="bi <?php echo $icons[$field]; ?>"></i></span>
            <input type="text" class="form-control form-control-sm" name="<?php echo $field; ?>" placeholder="<?php echo $label; ?>" value="<?php echo $student[$field] ?? ''; ?>">
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Transport Info -->
      <h3>Transport Information</h3>
      <div class="row g-3 mb-4">
        <?php
        $transport_fields = ['transport_mode'=>'Transport Mode','route_number'=>'Route Number','pick_up_point'=>'Pick-up Point','drop_off_point'=>'Drop-off Point','guardian_contact'=>'Guardian Contact'];
        $icons_tr = ['transport_mode'=>'bi-truck','route_number'=>'bi-hash','pick_up_point'=>'bi-signpost-split','drop_off_point'=>'bi-signpost','guardian_contact'=>'bi-person-lines-fill'];
        foreach($transport_fields as $field=>$label): ?>
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text"><i class="bi <?php echo $icons_tr[$field]; ?>"></i></span>
            <input type="text" class="form-control form-control-sm" name="<?php echo $field; ?>" placeholder="<?php echo $label; ?>" value="<?php echo $transport[$field] ?? ''; ?>">
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Medical Info -->
      <h3>Medical Information</h3>
      <div class="row g-3 mb-4">
        <?php
        $medical_fields = ['blood_type'=>'Blood Type','allergies'=>'Allergies','chronic_conditions'=>'Chronic Conditions','medications'=>'Medications','emergency_contact_name'=>'Emergency Contact Name','emergency_contact_phone'=>'Emergency Contact Phone'];
        $icons_med = ['blood_type'=>'bi-droplet','allergies'=>'bi-emoji-sunglasses','chronic_conditions'=>'bi-heart-pulse','medications'=>'bi-capsule','emergency_contact_name'=>'bi-person','emergency_contact_phone'=>'bi-telephone'];
        foreach($medical_fields as $field=>$label): ?>
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text"><i class="bi <?php echo $icons_med[$field]; ?>"></i></span>
            <input type="text" class="form-control form-control-sm" name="<?php echo $field; ?>" placeholder="<?php echo $label; ?>" value="<?php echo $medical[$field] ?? ''; ?>">
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="text-center">
        <button type="submit"><i class="bi bi-save"></i> Update Profile</button>
      </div>

    </form>
  </div>
</div>

<script>
document.getElementById('photoInput').addEventListener('change', function(e){
    const img = document.querySelector('.profile-photo-container img');
    if(this.files && this.files[0]){
        img.src = URL.createObjectURL(this.files[0]);
    }
});
</script>

</body>
</html>
