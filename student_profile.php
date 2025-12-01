<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: login.html");
    exit();
}

include("db.php");

// Include navigation early for consistency
include("student_navigation.php");

// Fetch user info and linked address
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT u.user_id, u.firstName, u.lastName, u.email, u.phone, u.gender, u.IDNumber,
           u.username, u.document, a.address_id, a.address1, a.streetName, a.postalCode, a.district, a.country, s.student_id, s.photo
    FROM users u
    LEFT JOIN addresses a ON u.address_id = a.address_id
    LEFT JOIN students s ON s.user_id = u.user_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$student_id = $student['student_id'] ?? null;

// Fetch medical info
$medical = [];
if ($student_id) {
    $stmt_med = $conn->prepare("SELECT * FROM student_medical_info WHERE student_id = ?");
    $stmt_med->bind_param("i", $student_id);
    $stmt_med->execute();
    $medical = $stmt_med->get_result()->fetch_assoc();
}

// Fetch transport info
$transport = [];
if ($student_id) {
    $stmt_tr = $conn->prepare("SELECT * FROM student_transport_info WHERE student_id = ?");
    $stmt_tr->bind_param("i", $student_id);
    $stmt_tr->execute();
    $transport = $stmt_tr->get_result()->fetch_assoc();
}

// Handle profile update
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $IDNumber = trim($_POST['IDNumber'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $document = trim($_POST['document'] ?? '');

    // Address fields
    $address1 = trim($_POST['address1'] ?? '');
    $streetName = trim($_POST['streetName'] ?? '');
    $postalCode = trim($_POST['postalCode'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $country = trim($_POST['country'] ?? '');

    // Transport fields
    $transport_mode = trim($_POST['transport_mode'] ?? '');
    $route_number = trim($_POST['route_number'] ?? '');
    $pick_up_point = trim($_POST['pick_up_point'] ?? '');
    $drop_off_point = trim($_POST['drop_off_point'] ?? '');
    $guardian_contact = trim($_POST['guardian_contact'] ?? '');

    // Medical fields
    $blood_type = trim($_POST['blood_type'] ?? '');
    $allergies = trim($_POST['allergies'] ?? '');
    $chronic_conditions = trim($_POST['chronic_conditions'] ?? '');
    $medications = trim($_POST['medications'] ?? '');
    $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');

    $errors = [];

    // Update users table
    $stmt_user = $conn->prepare("UPDATE users SET firstName=?, lastName=?, email=?, phone=?, gender=?, IDNumber=?, username=?, document=? WHERE user_id=?");
    $stmt_user->bind_param("ssssssssi", $firstName, $lastName, $email, $phone, $gender, $IDNumber, $username, $document, $user_id);
    if (!$stmt_user->execute()) {
        $errors[] = "Failed to update personal info.";
    }
    $stmt_user->close();

    // Handle address (update or insert)
    if (!empty($address1) || !empty($streetName) || !empty($postalCode) || !empty($district) || !empty($country)) {
        if (empty($student['address_id'])) {
            // Insert new address
            $stmt_addr = $conn->prepare("INSERT INTO addresses (address1, streetName, postalCode, district, country, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt_addr->bind_param("sssss", $address1, $streetName, $postalCode, $district, $country);
            if ($stmt_addr->execute()) {
                $new_addr_id = $conn->insert_id;
                $conn->query("UPDATE users SET address_id = $new_addr_id WHERE user_id = $user_id");
            } else {
                $errors[] = "Failed to add address.";
            }
            $stmt_addr->close();
        } else {
            // Update existing
            $stmt_addr = $conn->prepare("UPDATE addresses SET address1=?, streetName=?, postalCode=?, district=?, country=?, updated_at=NOW() WHERE address_id=?");
            $stmt_addr->bind_param("sssssi", $address1, $streetName, $postalCode, $district, $country, $student['address_id']);
            if (!$stmt_addr->execute()) {
                $errors[] = "Failed to update address.";
            }
            $stmt_addr->close();
        }
    }

    // Handle photo upload for students
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK && $student_id) {
        $upload_dir = 'uploads/students/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $file_ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $new_filename = $student_id . '_' . time() . '.' . $file_ext;
        $target_file = $upload_dir . $new_filename;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
            $photo_path = 'uploads/students/' . $new_filename;
            $stmt_photo = $conn->prepare("UPDATE students SET photo=? WHERE student_id=?");
            $stmt_photo->bind_param("si", $photo_path, $student_id);
            $stmt_photo->execute();
            $stmt_photo->close();
            // Refresh student data
            $student['photo'] = $photo_path;
        } else {
            $errors[] = "Failed to upload photo.";
        }
    }

    // Update transport
    if ($student_id) {
        $transport_exists = $conn->query("SELECT COUNT(*) as cnt FROM student_transport_info WHERE student_id = $student_id")->fetch_assoc()['cnt'];
        if ($transport_exists > 0) {
            $stmt_tr = $conn->prepare("UPDATE student_transport_info SET transport_mode=?, route_number=?, pick_up_point=?, drop_off_point=?, guardian_contact=?, updated_at=NOW() WHERE student_id=?");
            $stmt_tr->bind_param("sssssi", $transport_mode, $route_number, $pick_up_point, $drop_off_point, $guardian_contact, $student_id);
        } else {
            $stmt_tr = $conn->prepare("INSERT INTO student_transport_info (student_id, transport_mode, route_number, pick_up_point, drop_off_point, guardian_contact, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt_tr->bind_param("issssi", $student_id, $transport_mode, $route_number, $pick_up_point, $drop_off_point, $guardian_contact);
        }
        if (!$stmt_tr->execute()) {
            $errors[] = "Failed to update transport info.";
        }
        $stmt_tr->close();
    }

    // Update medical
    if ($student_id) {
        $medical_exists = $conn->query("SELECT COUNT(*) as cnt FROM student_medical_info WHERE student_id = $student_id")->fetch_assoc()['cnt'];
        if ($medical_exists > 0) {
            $stmt_med = $conn->prepare("UPDATE student_medical_info SET blood_type=?, allergies=?, chronic_conditions=?, medications=?, emergency_contact_name=?, emergency_contact_phone=?, updated_at=NOW() WHERE student_id=?");
            $stmt_med->bind_param("ssssssi", $blood_type, $allergies, $chronic_conditions, $medications, $emergency_contact_name, $emergency_contact_phone, $student_id);
        } else {
            $stmt_med = $conn->prepare("INSERT INTO student_medical_info (student_id, blood_type, allergies, chronic_conditions, medications, emergency_contact_name, emergency_contact_phone, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt_med->bind_param("isssssi", $student_id, $blood_type, $allergies, $chronic_conditions, $medications, $emergency_contact_name, $emergency_contact_phone);
        }
        if (!$stmt_med->execute()) {
            $errors[] = "Failed to update medical info.";
        }
        $stmt_med->close();
    }

    if (empty($errors)) {
        $message = '<div class="alert alert-success">Profile updated successfully!</div>';
        // Refresh fetched data
        header("Location: " . $_SERVER['PHP_SELF'] . "?updated=1");
        exit();
    } else {
        $message = '<div class="alert alert-danger">' . implode('<br>', $errors) . '</div>';
    }
}

// Refresh data if updated
if (isset($_GET['updated'])) {
    // Re-fetch to show updated values
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    // Re-fetch medical and transport...
    if ($student_id) {
        $stmt_med->execute();
        $medical = $stmt_med->get_result()->fetch_assoc();
        $stmt_tr->execute();
        $transport = $stmt_tr->get_result()->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Student Profile</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
<style>
    body {
        font-family: Arial, sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        margin: 0;
        padding: 0;
        min-height: 100vh;
    }
    .container-flex {
        display: flex;
        min-height: 100vh;
    }
    main.content {
        flex: 1;
        padding: 40px;
        overflow-y: auto;
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(10px);
        margin-left: 250px;
    }
    h2 {
        margin-bottom: 30px;
        color: #2c3e50;
        font-weight: 600;
        text-align: center;
        position: relative;
    }
    h2::after {
        content: '';
        display: block;
        width: 50px;
        height: 3px;
        background: linear-gradient(90deg, #3498db, #2980b9);
        margin: 10px auto 0;
        border-radius: 2px;
    }
    form {
        max-width: 100%;
        margin: auto;
        background: white;
        padding: 30px;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .profile-photo-container {
        text-align: center;
        margin-bottom: 30px;
    }
    .profile-photo-container img {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        border: 4px solid #3498db;
        object-fit: cover;
        margin-bottom: 15px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .profile-photo-container img:hover {
        transform: scale(1.05);
        box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
    }
    .profile-photo-container label {
        display: inline-block;
        padding: 8px 16px;
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        border-radius: 25px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    .profile-photo-container label:hover {
        background: linear-gradient(135deg, #2980b9, #1f618d);
        transform: translateY(-2px);
    }
    input#photoInput {
        display: none;
    }
    form h3 {
        margin-bottom: 20px;
        color: #2c3e50;
        font-weight: 600;
        border-bottom: 2px solid #ecf0f1;
        padding-bottom: 8px;
        position: relative;
    }
    form h3::before {
        content: '';
        position: absolute;
        left: 0;
        bottom: -2px;
        width: 40px;
        height: 3px;
        background: linear-gradient(90deg, #e74c3c, #c0392b);
    }
    .input-group {
        margin-bottom: 15px;
    }
    .input-group-text {
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        border: none;
        border-radius: 8px 0 0 8px;
        min-width: 50px;
    }
    .form-control {
        border-radius: 0 8px 8px 0;
        border: 1px solid #bdc3c7;
        padding: 12px 16px;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
        width: 100%;
    }
    .form-control:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
    }
    button[type="submit"] {
        background: linear-gradient(135deg, #27ae60, #229954);
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 25px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 600;
        width: 100%;
        margin-top: 20px;
    }
    button[type="submit"]:hover {
        background: linear-gradient(135deg, #229954, #1e8449);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
    }
    .alert {
        border-radius: 8px;
        border: none;
        margin-bottom: 20px;
    }
</style>
</head>
<body>

<div class="container-flex">
    <!-- Main Content -->
    <main class="content" role="main">
        <h2>My Profile</h2>
        <?= $message ?? '' ?>

        <form method="POST" enctype="multipart/form-data">

            <!-- Profile Photo -->
            <div class="profile-photo-container">
                <img src="<?= !empty($student['photo']) ? htmlspecialchars($student['photo']) : 'admin.png'; ?>" alt="Student Photo" />
                <label for="photoInput"><i class="bi bi-camera"></i> Change Photo</label>
                <input type="file" name="photo" id="photoInput" accept="image/*" />
            </div>

            <!-- Personal Info -->
            <h3>Personal Information</h3>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" class="form-control" name="firstName" placeholder="First Name" value="<?= htmlspecialchars($student['firstName'] ?? '') ?>" required />
            </div>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" class="form-control" name="lastName" placeholder="Last Name" value="<?= htmlspecialchars($student['lastName'] ?? '') ?>" required />
            </div>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input type="email" class="form-control" name="email" placeholder="Email" value="<?= htmlspecialchars($student['email'] ?? '') ?>" required />
            </div>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-phone"></i></span>
                <input type="text" class="form-control" name="phone" placeholder="Phone" value="<?= htmlspecialchars($student['phone'] ?? '') ?>" />
            </div>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-gender-ambiguous"></i></span>
                <input type="text" class="form-control" name="gender" placeholder="Gender" value="<?= htmlspecialchars($student['gender'] ?? '') ?>" />
            </div>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-credit-card"></i></span>
                <input type="text" class="form-control" name="IDNumber" placeholder="ID Number" value="<?= htmlspecialchars($student['IDNumber'] ?? '') ?>" />
            </div>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                <input type="text" class="form-control" name="username" placeholder="Username" value="<?= htmlspecialchars($student['username'] ?? '') ?>" />
            </div>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-file-earmark-text"></i></span>
                <input type="text" class="form-control" name="document" placeholder="Document" value="<?= htmlspecialchars($student['document'] ?? '') ?>" />
            </div>

            <!-- Address Info -->
            <h3>Address Information</h3>
            <?php 
            $address_fields = ['address1'=>'House/Flat No.', 'streetName'=>'Street Name', 'postalCode'=>'Postal Code', 'district'=>'District', 'country'=>'Country'];
            $icons = ['address1'=>'bi-house', 'streetName'=>'bi-geo-alt', 'postalCode'=>'bi-mailbox', 'district'=>'bi-map', 'country'=>'bi-globe'];
            foreach($address_fields as $field=>$label): ?>
            <div class="input-group">
                <span class="input-group-text"><i class="bi <?= $icons[$field] ?>"></i></span>
                <input type="text" class="form-control" name="<?= $field ?>" placeholder="<?= $label ?>" value="<?= htmlspecialchars($student[$field] ?? '') ?>" />
            </div>
            <?php endforeach; ?>

            <!-- Transport Info -->
            <h3>Transport Information</h3>
            <?php
            $transport_fields = ['transport_mode'=>'Transport Mode','route_number'=>'Route Number','pick_up_point'=>'Pick-up Point','drop_off_point'=>'Drop-off Point','guardian_contact'=>'Guardian Contact'];
            $icons_tr = ['transport_mode'=>'bi-truck','route_number'=>'bi-hash','pick_up_point'=>'bi-signpost-split','drop_off_point'=>'bi-signpost','guardian_contact'=>'bi-person-lines-fill'];
            foreach($transport_fields as $field=>$label): ?>
            <div class="input-group">
                <span class="input-group-text"><i class="bi <?= $icons_tr[$field] ?>"></i></span>
                <input type="text" class="form-control" name="<?= $field ?>" placeholder="<?= $label ?>" value="<?= htmlspecialchars($transport[$field] ?? '') ?>" />
            </div>
            <?php endforeach; ?>

            <!-- Medical Info -->
            <h3>Medical Information</h3>
            <?php
            $medical_fields = ['blood_type'=>'Blood Type','allergies'=>'Allergies','chronic_conditions'=>'Chronic Conditions','medications'=>'Medications','emergency_contact_name'=>'Emergency Contact Name','emergency_contact_phone'=>'Emergency Contact Phone'];
            $icons_med = ['blood_type'=>'bi-droplet','allergies'=>'bi-emoji-sunglasses','chronic_conditions'=>'bi-heart-pulse','medications'=>'bi-capsule','emergency_contact_name'=>'bi-person','emergency_contact_phone'=>'bi-telephone'];
            foreach($medical_fields as $field=>$label): ?>
            <div class="input-group">
                <span class="input-group-text"><i class="bi <?= $icons_med[$field] ?>"></i></span>
                <input type="text" class="form-control" name="<?= $field ?>" placeholder="<?= $label ?>" value="<?= htmlspecialchars($medical[$field] ?? '') ?>" />
            </div>
            <?php endforeach; ?>

            <div class="text-center mt-4">
                <button type="submit"><i class="bi bi-save"></i> Update Profile</button>
            </div>

        </form>
    </main>
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