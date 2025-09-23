<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

include("db.php");

$parent_user_id = $_SESSION['user_id'];
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get parent_id
$parent_query = $conn->prepare("SELECT parent_id FROM parents WHERE user_id = ?");
$parent_query->bind_param("i", $parent_user_id);
$parent_query->execute();
$parent_result = $parent_query->get_result();
$parent = $parent_result->fetch_assoc();
if (!$parent) exit("Parent record not found.");
$parent_id = $parent['parent_id'];

// Fetch student info linked to this parent
$stmt = $conn->prepare("
    SELECT u.user_id, u.firstName, u.lastName, u.email, u.phone, u.gender, u.IDNumber,
           u.username, u.document, a.address1, a.streetName, a.postalCode, a.district, a.country,
           s.student_id, s.photo
    FROM students s
    INNER JOIN parent_students ps ON s.student_id = ps.student_id
    INNER JOIN users u ON s.user_id = u.user_id
    LEFT JOIN addresses a ON u.address_id = a.address_id
    WHERE ps.parent_id = ? AND s.student_id = ?
");
$stmt->bind_param("ii", $parent_id, $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
if (!$student) exit("You are not authorized to view this student.");

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
<title><?= htmlspecialchars($student['firstName'] . " " . $student['lastName']) ?> - Profile</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background:#f9f9f9; font-family:Arial,sans-serif; display:flex; min-height:100vh; margin:0; }
.sidebar { width:250px; background:#343a40; color:white; flex-shrink:0; }
.sidebar h4 { text-align:center; padding:15px 0; border-bottom:1px solid #495057; }
.sidebar img { width:80px; border-radius:50%; margin:10px auto; display:block; }
.sidebar a { display:block; color:white; padding:12px 20px; text-decoration:none; }
.sidebar a:hover, .sidebar a.active { background:#495057; }
.content { flex-grow:1; padding:30px; }
.profile-photo-container { text-align:center; margin-bottom:20px; }
.profile-photo-container img { width:150px; height:150px; border-radius:50%; border:3px solid #1abc9c; object-fit:cover; margin-bottom:10px; }
form { background:white; padding:20px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1); max-width:1000px; margin:auto; }
form h3 { margin-bottom:15px; color:#343a40; }
.input-group-text i { color:#343a40; width:20px; }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <img src="admin.png" alt="Parent Image">
    <h4>Parent Dashboard</h4>
    <a href="parents_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <a href="children.php" class="active"><i class="bi bi-person-lines-fill"></i> Children Profiles</a>
    <a href="parent_messages.php"><i class="bi bi-envelope"></i> Messages</a>
    <a href="parent_settings.php"><i class="bi bi-gear"></i> Settings</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<!-- Content -->
<div class="content flex-fill">
    <form>
      <div class="profile-photo-container">
        <img src="<?= !empty($student['photo']) ? $student['photo'] : 'default_student.png'; ?>" alt="Student Photo">
      </div>

      <!-- Personal Info -->
      <h3>Personal Information</h3>
      <div class="row g-3 mb-4">
        <?php
        $fields = ['firstName'=>'First Name','lastName'=>'Last Name','email'=>'Email','phone'=>'Phone','gender'=>'Gender','IDNumber'=>'ID Number','username'=>'Username','document'=>'Document'];
        $icons = ['firstName'=>'bi-person','lastName'=>'bi-person','email'=>'bi-envelope','phone'=>'bi-telephone','gender'=>'bi-gender-ambiguous','IDNumber'=>'bi-credit-card','username'=>'bi-person-badge','document'=>'bi-file-earmark-text'];
        foreach($fields as $f=>$label): ?>
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text"><i class="bi <?= $icons[$f]; ?>"></i></span>
            <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($student[$f] ?? ''); ?>" disabled>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Address Info -->
      <h3>Address Information</h3>
      <div class="row g-3 mb-4">
        <?php
        $address_fields = ['address1'=>'House/Flat No.', 'streetName'=>'Street Name', 'postalCode'=>'Postal Code', 'district'=>'District', 'country'=>'Country'];
        $icons_addr = ['address1'=>'bi-house','streetName'=>'bi-geo-alt','postalCode'=>'bi-mailbox','district'=>'bi-map','country'=>'bi-globe'];
        foreach($address_fields as $f=>$label): ?>
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text"><i class="bi <?= $icons_addr[$f]; ?>"></i></span>
            <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($student[$f] ?? ''); ?>" disabled>
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
        foreach($transport_fields as $f=>$label): ?>
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text"><i class="bi <?= $icons_tr[$f]; ?>"></i></span>
            <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($transport[$f] ?? ''); ?>" disabled>
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
        foreach($medical_fields as $f=>$label): ?>
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text"><i class="bi <?= $icons_med[$f]; ?>"></i></span>
            <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($medical[$f] ?? ''); ?>" disabled>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </form>
</div>
</body>
</html>
