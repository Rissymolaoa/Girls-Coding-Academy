<?php
session_start();
if(!isset($_SESSION['role']) || $_SESSION['role']!=='admin') exit('Unauthorized');

// Show all PHP errors for debugging
error_reporting(E_ALL);
ini_set('display_errors',1);

include("db.php");

$action = $_POST['action'] ?? '';

if($action==='fetch_student'){
    $id = intval($_POST['id']);
    $res = $conn->query("SELECT u.*, a.address1, a.streetName, a.postalCode, a.district, a.country 
                         FROM users u 
                         LEFT JOIN addresses a ON u.address_id=a.address_id 
                         WHERE u.user_id=$id AND role='student'");
    echo json_encode($res->fetch_assoc());
    exit;
}

if($action==='fetch_medical'){
    $id = intval($_POST['id']);
    $res = $conn->query("SELECT * FROM student_medical WHERE user_id=$id");
    echo json_encode($res->fetch_assoc() ?? []);
    exit;
}

if($action==='fetch_transport'){
    $id = intval($_POST['id']);
    $res = $conn->query("SELECT * FROM student_transport WHERE user_id=$id");
    echo json_encode($res->fetch_assoc() ?? []);
    exit;
}

if($action==='save_medical'){
    $id = intval($_POST['student_id']);
    $blood = $_POST['blood_type'] ?? '';
    $allergies = $_POST['allergies'] ?? '';
    $chronic = $_POST['chronic_conditions'] ?? '';
    $meds = $_POST['medications'] ?? '';

    $check = $conn->query("SELECT * FROM student_medical WHERE user_id=$id");
    if($check->num_rows>0){
        $stmt = $conn->prepare("UPDATE student_medical SET blood_type=?,allergies=?,chronic_conditions=?,medications=? WHERE user_id=?");
        $stmt->bind_param("ssssi",$blood,$allergies,$chronic,$meds,$id);
        $stmt->execute();
        echo "Medical info updated.";
    } else {
        $stmt = $conn->prepare("INSERT INTO student_medical(user_id,blood_type,allergies,chronic_conditions,medications) VALUES(?,?,?,?,?)");
        $stmt->bind_param("issss",$id,$blood,$allergies,$chronic,$meds);
        $stmt->execute();
        echo "Medical info saved.";
    }
    exit;
}

if($action==='save_transport'){
    $id = intval($_POST['student_id']);
    $mode = $_POST['transport_mode'] ?? '';
    $pickup = $_POST['pickup_point'] ?? '';
    $drop = $_POST['dropoff_point'] ?? '';
    $status = $_POST['transport_status'] ?? '';

    $check = $conn->query("SELECT * FROM student_transport WHERE user_id=$id");
    if($check->num_rows>0){
        $stmt = $conn->prepare("UPDATE student_transport SET transport_mode=?,pickup_point=?,dropoff_point=?,transport_status=? WHERE user_id=?");
        $stmt->bind_param("ssssi",$mode,$pickup,$drop,$status,$id);
        $stmt->execute();
        echo "Transport info updated.";
    } else {
        $stmt = $conn->prepare("INSERT INTO student_transport(user_id,transport_mode,pickup_point,dropoff_point,transport_status) VALUES(?,?,?,?,?)");
        $stmt->bind_param("issss",$id,$mode,$pickup,$drop,$status);
        $stmt->execute();
        echo "Transport info saved.";
    }
    exit;
}

if($action==='save_student'){
    $id = intval($_POST['user_id']);
    $username = $_POST['username'] ?? '';
    $firstName= $_POST['firstName'] ?? '';
    $lastName = $_POST['lastName'] ?? '';
    $gender   = $_POST['gender'] ?? '';
    $IDNumber = $_POST['IDNumber'] ?? '';
    $phone    = $_POST['phone'] ?? '';
    $email    = $_POST['email'] ?? '';
    $status   = $_POST['status'] ?? '';
    $address1 = $_POST['address1'] ?? '';
    $streetName = $_POST['streetName'] ?? '';
    $postalCode = $_POST['postalCode'] ?? '';
    $district   = $_POST['district'] ?? '';
    $country    = $_POST['country'] ?? '';
    $password   = $_POST['password'] ?? '';

    // Password update only if filled
    $pass_sql = $password ? ", password='".password_hash($password,PASSWORD_DEFAULT)."'" : "";

    // Update user table
    $stmt = $conn->prepare("UPDATE users SET username=?, firstName=?, lastName=?, gender=?, IDNumber=?, phone=?, email=?, status=? $pass_sql WHERE user_id=?");
    $stmt->bind_param("ssssssssi",$username,$firstName,$lastName,$gender,$IDNumber,$phone,$email,$status,$id);
    $stmt->execute();

    // Update or insert address
    $res = $conn->query("SELECT address_id FROM users WHERE user_id=$id");
    $row = $res->fetch_assoc();
    $address_id = $row['address_id'];

    if($address_id){
        $stmt2 = $conn->prepare("UPDATE addresses SET address1=?, streetName=?, postalCode=?, district=?, country=? WHERE address_id=?");
        $stmt2->bind_param("sssssi",$address1,$streetName,$postalCode,$district,$country,$address_id);
        $stmt2->execute();
    } else {
        $stmt2 = $conn->prepare("INSERT INTO addresses(address1,streetName,postalCode,district,country) VALUES(?,?,?,?,?)");
        $stmt2->bind_param("sssss",$address1,$streetName,$postalCode,$district,$country);
        $stmt2->execute();
        $new_address_id = $conn->insert_id;
        $conn->query("UPDATE users SET address_id=$new_address_id WHERE user_id=$id");
    }

    // Document upload
    if(isset($_FILES['document']) && $_FILES['document']['error']===0){
        $file = $_FILES['document'];
        $dest = "documents/".$file['name'];
        move_uploaded_file($file['tmp_name'],$dest);
        $conn->query("UPDATE users SET document='".$dest."' WHERE user_id=$id");
    }

    echo "Student info updated.";
}
?>
