<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

// ===============================
// DATABASE CONNECTION
// ===============================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb"; 

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ===============================
// PAYFAST CONFIGURATION (Sandbox)
// ===============================
define('PAYFAST_MERCHANT_ID', '10042885');
define('PAYFAST_MERCHANT_KEY', 'hmvdh7zqseco8');
define('PAYFAST_PASSPHRASE', 'GirlsCoding124'); // Use exactly the same as in your sandbox account
define('PAYFAST_SANDBOX', true);
define('PAYFAST_URL', 'https://sandbox.payfast.co.za/eng/process');
define('REGISTRATION_FEE', 250.00);

// ===============================
// SIGNATURE FUNCTION (Official Format)
// ===============================
function pf_generate_signature($data, $passPhrase = null) {
    // Remove signature if present
    if (isset($data['signature'])) {
        unset($data['signature']);
    }

    // Sort the array alphabetically by key (case-sensitive)
    ksort($data);

    // Build the parameter string
    $pfOutput = '';
    foreach ($data as $key => $val) {
        if ($val !== '') {
            $pfOutput .= $key . '=' . urlencode(trim($val)) . '&';
        }
    }
    $pfOutput = substr($pfOutput, 0, -1);

    // Add passphrase if defined
    if ($passPhrase !== null && $passPhrase !== '') {
        $pfOutput .= '&passphrase=' . urlencode($passPhrase);
    }

    return md5($pfOutput);
}

// ===============================
// REGISTRATION PROCESS
// ===============================
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Sanitize input
    $firstName = $conn->real_escape_string($_POST['firstName']);
    $lastName  = $conn->real_escape_string($_POST['lastName']);
    $email     = $conn->real_escape_string($_POST['email']);
    $username  = $conn->real_escape_string($_POST['username']);
    $gender    = $conn->real_escape_string($_POST['gender']);
    $dob       = $conn->real_escape_string($_POST['dob']);
    $IDNumber  = $conn->real_escape_string($_POST['IDNumber']);
    $phone     = $conn->real_escape_string($_POST['phone']);
    $role      = $conn->real_escape_string($_POST['role']);
    $status    = "pending";
    $password  = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $subject_speciality = isset($_POST['subject_speciality']) ? $conn->real_escape_string($_POST['subject_speciality']) : null;

    // File upload
    $documentPath = "";
    if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $documentPath = $targetDir . basename($_FILES["document"]["name"]);
        move_uploaded_file($_FILES["document"]["tmp_name"], $documentPath);
    }

    // Address details
    $address1   = $conn->real_escape_string($_POST['address1']); 
    $streetName = $conn->real_escape_string($_POST['streetName']);
    $postalCode = $conn->real_escape_string($_POST['postalCode']);
    $district   = $conn->real_escape_string($_POST['district']);
    $country    = $conn->real_escape_string($_POST['country']);

    // Insert address
    $sqlAddress = "INSERT INTO addresses (address1, streetName, postalCode, district, country, created_at, updated_at) 
                   VALUES ('$address1', '$streetName', '$postalCode', '$district', '$country', NOW(), NOW())";
    if (!$conn->query($sqlAddress)) {
        die("Error inserting address: " . $conn->error);
    }
    $address_id = $conn->insert_id;

    $temporary_code    = uniqid("TMP_");
    $verification_token = bin2hex(random_bytes(16));

    // Insert user
    $sqlUser = "INSERT INTO users 
        (firstName, lastName, email, password, username, role, gender, dob, IDNumber, phone, document, status, address_id, created_at, updated_at)
        VALUES 
        ('$firstName', '$lastName', '$email', '$password', '$username', '$role', '$gender', '$dob', '$IDNumber', '$phone', '$documentPath', '$status', '$address_id', NOW(), NOW())";

    if ($conn->query($sqlUser)) {
        $user_id = $conn->insert_id;

        // Additional records
        $conn->query("INSERT INTO temporary_ids (user_id, temporary_code, created_at) VALUES ('$user_id', '$temporary_code', NOW())");
        $conn->query("INSERT INTO user_verifications (user_id, verification_token, status, created_at) VALUES ('$user_id', '$verification_token', 'pending', NOW())");

        // Role-specific table insertions
        if ($role === "student") {
            $conn->query("INSERT INTO students (user_id) VALUES ('$user_id')");
        } elseif ($role === "teacher") {
            $conn->query("INSERT INTO teachers (user_id, subject_speciality) VALUES ('$user_id','$subject_speciality')");
        } elseif ($role === "parents") {
            $conn->query("INSERT INTO parents (user_id) VALUES ('$user_id')");
        }

        // Save pending session
        session_start();
        $_SESSION['pending_user_id'] = $user_id;
        $_SESSION['pending_email'] = $email;
        $_SESSION['pending_firstname'] = $firstName;
        $_SESSION['pending_lastname'] = $lastName;

        // ===============================
        // EMAIL VERIFICATION
        // ===============================
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'rethabilemackenzie70@gmail.com';
            $mail->Password   = 'vxss fson asfi srkr';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('rethabilemackenzie70@gmail.com', 'Girls Coding Academy');
            $mail->addAddress($email, $firstName);

            $mail->isHTML(true);
            $mail->Subject = 'Verify Your Email & Complete Registration';
            $verify_link = "http://localhost/GirlsCodingAcademy/verify.php?token=$verification_token";
            $mail->Body = "
                <p>Hi $firstName $lastName,</p>
                <p>Thank you for registering at Girls Coding Academy!</p>
                <p>Your Temporary ID: <b>$temporary_code</b></p>
                <p>Click here to verify your email: <a href='$verify_link'>$verify_link</a></p>
                <p><strong>Next: Complete your $role registration with a one-time fee of R " . number_format(REGISTRATION_FEE, 2) . " via our secure gateway.</strong></p>
                <p>Regards,<br>Girls Coding Academy Team</p>
            ";
            $mail->send();
        } catch (Exception $e) {
            // Suppress email errors for testing
        }

        // ===============================
        // PAYMENT DATA (PAYFAST)
        // ===============================
        $pfData = [
            'merchant_id'    => PAYFAST_MERCHANT_ID,
            'merchant_key'   => PAYFAST_MERCHANT_KEY,
            'return_url'     => 'http://localhost/GirlsCodingAcademy/registration.php?return=1',
            'cancel_url'     => 'http://localhost/GirlsCodingAcademy/registration.php?cancel=1',
            'notify_url'     => 'http://localhost/GirlsCodingAcademy/notify.php',
            'name_first'     => $firstName,
            'name_last'      => $lastName,
            'email_address'  => $email,
            'cell_number'    => $phone,
            'amount'         => number_format(REGISTRATION_FEE, 2, '.', ''),
            'item_name'      => 'Girls Coding Academy Registration Fee',
            'item_description' => "One-time fee for $role access to courses and resources",
            'custom_str1'    => $user_id,
        ];

        // Create signature
        $pfData['signature'] = pf_generate_signature($pfData, PAYFAST_PASSPHRASE);

        // Optional debug for local testing
        file_put_contents('payfast_debug.log', print_r($pfData, true), FILE_APPEND);

        // Local dev shortcut (simulate payment success)
        if ($_SERVER['SERVER_NAME'] === 'localhost') {
            header("Location: registration.php?return=1&pf_payment_id=TESTLOCAL");
            exit;
        }

        // ===============================
        // REDIRECT TO PAYFAST
        // ===============================
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="UTF-8" />
          <meta name="viewport" content="width=device-width, initial-scale=1" />
          <title>Redirecting to PayFast - Girls Coding Academy</title>
          <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
          <style>
            body { background: #f7f7f7; }
            .container { max-width: 500px; margin: 100px auto; text-align: center; }
            .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #7b2cbf; border-radius: 50%; width: 50px; height: 50px; animation: spin 2s linear infinite; margin: 20px auto; }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
          </style>
        </head>
        <body>
          <div class="container">
            <h2>Registration Saved! Now Secure Your Spot</h2>
            <p>Redirecting to PayFast for your R <?= number_format(REGISTRATION_FEE, 2) ?> registration fee...</p>
            <div class="spinner"></div>
            <p><small>This is secure. You'll return here after payment.</small></p>
            <form action="<?= PAYFAST_URL; ?>" method="post" id="payfast-form" style="display:none;">
              <?php foreach ($pfData as $key => $val): ?>
                <input type="hidden" name="<?= htmlspecialchars($key); ?>" value="<?= htmlspecialchars($val); ?>" />
              <?php endforeach; ?>
            </form>
          </div>
          <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.getElementById('payfast-form').submit();
            });
          </script>
        </body>
        </html>
        <?php
        exit;
    } else {
        echo "Error: " . $conn->error;
    }
}

// ===============================
// PAYFAST RETURN HANDLER
// ===============================
if (isset($_GET['return']) || isset($_GET['cancel'])) {
    session_start();
    if (isset($_SESSION['pending_user_id'])) {
        $user_id = $_SESSION['pending_user_id'];
        if (isset($_GET['pf_payment_id']) && !isset($_GET['cancel'])) {
            $update = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ? AND status = 'pending'");
            $update->bind_param("i", $user_id);
            if ($update->execute()) {
                unset($_SESSION['pending_user_id']);
                echo "<script>alert('Payment confirmed! Your account is now active.'); window.location='login.html';</script>";
            }
        } else {
            echo "<script>alert('Payment cancelled. Your registration remains pending.'); window.location='login.html';</script>";
        }
    }
}

$conn->close();
?>
