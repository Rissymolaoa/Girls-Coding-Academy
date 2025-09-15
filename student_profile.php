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
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial,sans-serif; background:#f9f9f9; }
header { background:#7b2cbf; color:white; padding:15px 30px; text-align:center; }
.container { display:flex; min-height:90vh; }
.sidebar { width: 240px; background:#5a189a; padding:20px; min-height:100vh; }
.sidebar h3 { color:white; margin-bottom:15px; }
.sidebar a { display:block; color:white; text-decoration:none; padding:10px; margin:5px 0; border-radius:4px; }
.sidebar a:hover, .sidebar a.active { background:#9d4edd; }
.content { flex:1; padding:30px; }
h2 { color:#5a189a; margin-bottom:20px; text-align:center; }
form { background:white; padding:20px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1); max-width:800px; margin:auto; }
form h3 { margin-bottom:15px; color:#5a189a; }
form .form-group { margin-bottom:15px; }
form label { display:block; margin-bottom:5px; font-weight:bold; }
form input, form select, form textarea { width:100%; padding:10px; border-radius:4px; border:1px solid #ccc; }
form input[type="file"] { padding:3px; }
form button { background:#7b2cbf; color:white; padding:10px 20px; border:none; border-radius:4px; cursor:pointer; }
form button:hover { background:#5a189a; }

/* Profile photo top */
.profile-photo-container {
    text-align:center;
    margin-bottom:20px;
}
.profile-photo-container img {
    width:150px;
    height:150px;
    border-radius:50%;
    border:3px solid #1abc9c;
    object-fit:cover;
    margin-bottom:10px;
}
.profile-photo-container label {
    display:inline-block;
    margin-top:5px;
    padding:5px 10px;
    background:#7b2cbf;
    color:white;
    border-radius:4px;
    cursor:pointer;
}
.profile-photo-container label:hover {
    background:#5a189a;
}
input#photoInput { display:none; }

.section { margin-bottom:30px; }
  .admin-pic { width: 100px; height: 100px; border-radius: 50%; margin-bottom: 15px; border: 3px solid #1abc9c; object-fit: cover; }

</style>
</head>
<body>

<header>
<h1>Student Profile</h1>
</header>

<div class="container">
  <div class="sidebar">
     <img src="admin.jpg" alt="Admin Picture" class="admin-pic">
    <h3>Navigation</h3>
      <a href="student.php">🏠 Home</a>
      <a href="student_courses.php">📚 My Courses</a>
      <a href="#">📢 Announcements</a>
      <a href="#">📅 My Calendar</a>
      <a href="enroll.php">📅 Enroll</a>
      <a href="student_profile.php" class="active">👤 My Profile</a>
      <a href="logout.php">🚪 Logout</a>
  </div>

  <div class="content">
    <form action="student_profile_update.php" method="POST" enctype="multipart/form-data">
      
      <!-- Profile Photo -->
      <div class="profile-photo-container">
        <img src="<?php echo !empty($student['photo']) ? $student['photo'] : 'admin.jpg'; ?>" alt="Student Photo">
        <label for="photoInput">Change Photo</label>
        <input type="file" name="photo" id="photoInput">
      </div>

      <!-- Personal Info -->
      <div class="section">
        <h3>Personal Information</h3>
        <div class="form-group"><label>First Name</label><input type="text" name="firstName" value="<?php echo $student['firstName']; ?>" required></div>
        <div class="form-group"><label>Last Name</label><input type="text" name="lastName" value="<?php echo $student['lastName']; ?>" required></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo $student['email']; ?>" required></div>
        <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?php echo $student['phone']; ?>"></div>
        <div class="form-group"><label>Gender</label><input type="text" name="gender" value="<?php echo $student['gender']; ?>"></div>
        <div class="form-group"><label>ID Number</label><input type="text" name="IDNumber" value="<?php echo $student['IDNumber']; ?>"></div>
        <div class="form-group"><label>Username</label><input type="text" name="username" value="<?php echo $student['username']; ?>"></div>
        <div class="form-group"><label>Document</label><input type="text" name="document" value="<?php echo $student['document']; ?>"></div>
      </div>

      <!-- Address Info -->
      <div class="section">
        <h3>Address Information</h3>
        <div class="form-group"><label>Address 1</label><input type="text" name="address1" value="<?php echo $student['address1'] ?? ''; ?>"></div>
        <div class="form-group"><label>Street Name</label><input type="text" name="streetName" value="<?php echo $student['streetName'] ?? ''; ?>"></div>
        <div class="form-group"><label>Postal Code</label><input type="text" name="postalCode" value="<?php echo $student['postalCode'] ?? ''; ?>"></div>
        <div class="form-group"><label>District</label><input type="text" name="district" value="<?php echo $student['district'] ?? ''; ?>"></div>
        <div class="form-group"><label>Country</label><input type="text" name="country" value="<?php echo $student['country'] ?? ''; ?>"></div>
      </div>

      <!-- Transport Info -->
      <div class="section">
        <h3>Transport Information</h3>
        <div class="form-group"><label>Transport Mode</label><input type="text" name="transport_mode" value="<?php echo $transport['transport_mode'] ?? ''; ?>"></div>
        <div class="form-group"><label>Route Number</label><input type="text" name="route_number" value="<?php echo $transport['route_number'] ?? ''; ?>"></div>
        <div class="form-group"><label>Pick-up Point</label><input type="text" name="pick_up_point" value="<?php echo $transport['pick_up_point'] ?? ''; ?>"></div>
        <div class="form-group"><label>Drop-off Point</label><input type="text" name="drop_off_point" value="<?php echo $transport['drop_off_point'] ?? ''; ?>"></div>
        <div class="form-group"><label>Guardian Contact</label><input type="text" name="guardian_contact" value="<?php echo $transport['guardian_contact'] ?? ''; ?>"></div>
      </div>

      <!-- Medical Info -->
      <div class="section">
        <h3>Medical Information</h3>
        <div class="form-group"><label>Blood Type</label><input type="text" name="blood_type" value="<?php echo $medical['blood_type'] ?? ''; ?>"></div>
        <div class="form-group"><label>Allergies</label><input type="text" name="allergies" value="<?php echo $medical['allergies'] ?? ''; ?>"></div>
        <div class="form-group"><label>Chronic Conditions</label><input type="text" name="chronic_conditions" value="<?php echo $medical['chronic_conditions'] ?? ''; ?>"></div>
        <div class="form-group"><label>Medications</label><input type="text" name="medications" value="<?php echo $medical['medications'] ?? ''; ?>"></div>
        <div class="form-group"><label>Emergency Contact Name</label><input type="text" name="emergency_contact_name" value="<?php echo $medical['emergency_contact_name'] ?? ''; ?>"></div>
        <div class="form-group"><label>Emergency Contact Phone</label><input type="text" name="emergency_contact_phone" value="<?php echo $medical['emergency_contact_phone'] ?? ''; ?>"></div>
      </div>

      <button type="submit">Update Profile</button>
    </form>
  </div>
</div>

<script>
// Optional: preview photo immediately when selected
document.getElementById('photoInput').addEventListener('change', function(e){
    const img = document.querySelector('.profile-photo-container img');
    if(this.files && this.files[0]){
        img.src = URL.createObjectURL(this.files[0]);
    }
});
</script>

</body>
</html>
