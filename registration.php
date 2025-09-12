<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb"; // change if needed

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if form submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $firstName = $conn->real_escape_string($_POST['firstName']);
    $lastName  = $conn->real_escape_string($_POST['lastName']);
    $email     = $conn->real_escape_string($_POST['email']);
    $username  = $conn->real_escape_string($_POST['username']);
    $gender    = $conn->real_escape_string($_POST['gender']);
    $IDNumber  = $conn->real_escape_string($_POST['IDNumber']);
    $phone     = $conn->real_escape_string($_POST['phone']);
    $role      = $conn->real_escape_string($_POST['role']);
    $status    = "pending";

    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $subject_speciality = isset($_POST['subject_speciality']) ? 
                      $conn->real_escape_string($_POST['subject_speciality']) : null;

    // Document upload
    $documentPath = "";
    if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $documentPath = $targetDir . basename($_FILES["document"]["name"]);
        move_uploaded_file($_FILES["document"]["tmp_name"], $documentPath);
    }

    // Address fields
    $address1 = $conn->real_escape_string($_POST['address1']); 
    $streetName = $conn->real_escape_string($_POST['streetName']);
    $postalCode = $conn->real_escape_string($_POST['postalCode']);
    $district   = $conn->real_escape_string($_POST['district']);
    $country    = $conn->real_escape_string($_POST['country']);

    // Insert address
    $sqlAddress = "INSERT INTO addresses (address1, streetName, postalCode, district, country, created_at, updated_at) 
                   VALUES ('$address1', '$streetName', '$postalCode', '$district', '$country', NOW(), NOW())";
    if ($conn->query($sqlAddress)) {
        $address_id = $conn->insert_id;
    } else {
        die("Error inserting address: " . $conn->error);
    }

    // Generate temporary_id and verification_token
    $temporary_code    = uniqid("TMP_");
    $verification_token = bin2hex(random_bytes(16));

    // Insert user (without temporary_id and verification_token now)
    $sqlUser = "INSERT INTO users 
        (firstName, lastName, email, password, username, role, gender, IDNumber, phone, document, status, address_id, created_at, updated_at)
        VALUES 
        ('$firstName', '$lastName', '$email', '$password', '$username', '$role', '$gender', '$IDNumber', '$phone', '$documentPath', '$status', '$address_id', NOW(), NOW())";

    if ($conn->query($sqlUser) === TRUE) {
        $user_id = $conn->insert_id; // get the newly created user id

        // Insert into temporary_ids
        $sqlTemp = "INSERT INTO temporary_ids (user_id, temporary_code, created_at) 
                    VALUES ('$user_id', '$temporary_code', NOW())";
        $conn->query($sqlTemp);

        // Insert into user_verifications
        $sqlVerify = "INSERT INTO user_verifications (user_id, verification_token, status, created_at) 
                      VALUES ('$user_id', '$verification_token', 'pending', NOW())";
        $conn->query($sqlVerify);

        // 🔹 Insert into students or teachers table depending on role
        if ($role === "student") {
            $sqlStudent = "INSERT INTO students (user_id) 
                           VALUES ('$user_id')";
            $conn->query($sqlStudent);
        } elseif ($role === "teacher") {
            $sqlTeacher = "INSERT INTO teachers (user_id,subject_speciality) 
                           VALUES ('$user_id','$subject_speciality')";
            $conn->query($sqlTeacher);
        } elseif ($role === "parents") {
            $sqlParent = "INSERT INTO parents (user_id) 
                           VALUES ('$user_id')";
            $conn->query($sqlParent);
        }

        // PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; 
            $mail->SMTPAuth   = true;
            $mail->Username   = 'rethabilemackenzie70@gmail.com';
         //   $mail->Password   = 'vabm hbsz svgh rhbh';  
            $mail->Password   = 'vxss fson asfi srkr';  
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('rethabilemackenzie70@gmail.com', 'School Management System');
            $mail->addAddress($email, $firstName);

            $mail->isHTML(true);
            $mail->Subject = 'Verify Your Email';
            $verify_link = "http://localhost/GirlsCodingAcademy/verify.php?token=$verification_token";
            $mail->Body    = "
                <p>Hi $firstName $lastName,</p>
                <p>Thank you for registering.</p>
                <p>Your Temporary ID: <b>$temporary_code</b></p>
                <p>Click here to verify your email: <a href='$verify_link'>$verify_link</a></p>
                <p>Regards,<br>Girls Coding Academy Management Team</p>
            ";

            $mail->send();
            echo "<script>alert('Registration successful! Verification email sent.'); window.location='login.html';</script>";

        } catch (Exception $e) {
            echo "<script>alert('Registration successful but email could not be sent. Mailer Error: {$mail->ErrorInfo}'); window.location='login.html';</script>";
        }

    } else {
        echo "Error: " . $conn->error;
    }
}
$conn->close();
?>
