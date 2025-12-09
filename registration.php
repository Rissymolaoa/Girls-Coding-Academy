<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// ===============================
// DATABASE CONNECTION
// ===============================
$host = "localhost:3307";
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
define('PAYFAST_PASSPHRASE', 'Girlscoding124');
define('PAYFAST_SANDBOX', true);
define('PAYFAST_URL', PAYFAST_SANDBOX ? 'https://sandbox.payfast.co.za/eng/process' : 'https://www.payfast.co.za/eng/process');
define('REGISTRATION_FEE', 250.00);

// ===============================
// SIGNATURE FUNCTION
// ===============================
function pf_generate_signature($data, $passPhrase = null) {
    if (isset($data['signature'])) unset($data['signature']);
    ksort($data);
    $pfOutput = '';
    foreach ($data as $key => $val) {
        if ($val !== '') {
            $pfOutput .= $key . '=' . urlencode(trim($val)) . '&';
        }
    }
    $pfOutput = substr($pfOutput, 0, -1);
    if ($passPhrase !== null && $passPhrase !== '') {
        $pfOutput .= '&passphrase=' . urlencode($passPhrase);
    }
    return md5($pfOutput);
}

// ===============================
// REGISTRATION PROCESS
// ===============================
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_GET['return']) && !isset($_GET['cancel'])) {

    // Sanitize inputs
    $firstName = $conn->real_escape_string($_POST['firstName']);
    $lastName  = $conn->real_escape_string($_POST['lastName']);
    $email     = $conn->real_escape_string($_POST['email']);
    $username  = $conn->real_escape_string($_POST['username']);
    $gender    = $conn->real_escape_string($_POST['gender']);
    $dob       = $conn->real_escape_string($_POST['dob']);
    $IDNumber  = $conn->real_escape_string($_POST['IDNumber']);
    $phone     = $conn->real_escape_string($_POST['phone']);
    $role      = $conn->real_escape_string($_POST['role']);
    $status    = 'pending'; // Initial status
    $password  = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $subject_speciality = isset($_POST['subject_speciality']) ? $conn->real_escape_string($_POST['subject_speciality']) : null;

    // File upload
    $documentPath = "";
    if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $documentPath = $targetDir . time() . "_" . basename($_FILES["document"]["name"]);
        move_uploaded_file($_FILES["document"]["tmp_name"], $documentPath);
    }

    // Address
    $address1   = $conn->real_escape_string($_POST['address1']);
    $streetName = $conn->real_escape_string($_POST['streetName']);
    $postalCode = $conn->real_escape_string($_POST['postalCode']);
    $district   = $conn->real_escape_string($_POST['district']);
    $country    = $conn->real_escape_string($_POST['country']);

    $sqlAddress = "INSERT INTO addresses (address1, streetName, postalCode, district, country, created_at, updated_at)
                   VALUES ('$address1', '$streetName', '$postalCode', '$district', '$country', NOW(), NOW())";
    if (!$conn->query($sqlAddress)) {
        die("Address error: " . $conn->error);
    }
    $address_id = $conn->insert_id;

    $temporary_code     = uniqid("TMP_");
    $verification_token = bin2hex(random_bytes(16));

    // Insert user
    $sqlUser = "INSERT INTO users 
        (firstName, lastName, email, password, username, role, gender, dob, IDNumber, phone, document, status, address_id, created_at, updated_at)
        VALUES 
        ('$firstName', '$lastName', '$email', '$password', '$username', '$role', '$gender', '$dob', '$IDNumber', '$phone', '$documentPath', '$status', $address_id, NOW(), NOW())";

    if ($conn->query($sqlUser)) {
        $user_id = $conn->insert_id;

        // Additional tables
        $conn->query("INSERT INTO temporary_ids (user_id, temporary_code, created_at) VALUES ($user_id, '$temporary_code', NOW())");
        $conn->query("INSERT INTO user_verifications (user_id, verification_token, status, created_at) VALUES ($user_id, '$verification_token', 'pending', NOW())");

        if ($role === "student") {
            $conn->query("INSERT INTO students (user_id) VALUES ($user_id)");
        } elseif ($role === "teacher") {
            $conn->query("INSERT INTO teachers (user_id, subject_speciality) VALUES ($user_id, '$subject_speciality')");
        } elseif ($role === "parents") {
            $conn->query("INSERT INTO parents (user_id) VALUES ($user_id)");
        }

        // Store in session for payment flow
        session_start();
        $_SESSION['pending_user_id']   = $user_id;
        $_SESSION['pending_email']     = $email;
        $_SESSION['pending_firstname'] = $firstName;
        $_SESSION['pending_lastname']  = $lastName;

        // Send verification email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'rethabilemackenzie70@gmail.com';
            $mail->Password   = 'vxss fson asfi srkr'; // Use App Password in production!
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('noreply@gmail.com', 'Girls Coding Academy');
            $mail->addAddress($email, "$firstName $lastName");
            $mail->isHTML(true);
            $mail->Subject = 'Verify Your Email – Girls Coding Academy';

            $verify_link = "http://localhost/GirlsCodingAcademy/verify.php?token=$verification_token";

            $mail->Body = "
                <h3>Hi $firstName $lastName,</h3>
                <p>Thank you for registering with <strong>Girls Coding Academy</strong>!</p>
                <p><strong>Your Temporary ID:</strong> $temporary_code</p>
                <p>Please verify your email by clicking the link below:<br>
                <a href='$verify_link'><strong>Verify Email Address</strong></a></p>
                <p>After verification, you will be redirected to complete your R " . number_format(REGISTRATION_FEE, 2) . " registration fee.</p>
                <p>Thank you!<br>Girls Coding Academy Team</p>
            ";

            $mail->send();
        } catch (Exception $e) {
            // Log but don't stop
        }

        // Prepare PayFast data
        $pfData = [
            'merchant_id'      => PAYFAST_MERCHANT_ID,
            'merchant_key'     => PAYFAST_MERCHANT_KEY,
            'return_url'       => 'http://localhost/GirlsCodingAcademy/registration.php?payfast_return=1',
            'cancel_url'       => 'http://localhost/GirlsCodingAcademy/registration.php?payfast_cancel=1',
            'notify_url'       => 'http://localhost/GirlsCodingAcademy/notify.php',
            'name_first'       => $firstName,
            'name_last'        => $lastName,
            'email_address'    => $email,
            'cell_number'      => $phone,
            'amount'           => number_format(REGISTRATION_FEE, 2, '.', ''),
            'item_name'        => 'Girls Coding Academy Registration Fee',
            'item_description' => "One-time registration fee ($role account)",
            'custom_str1'      => $user_id,           // Important: user ID
            'custom_str2'      => $temporary_code,
        ];

        $pfData['signature'] = pf_generate_signature($pfData, PAYFAST_PASSPHRASE);

        // Auto-submit form to PayFast
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Redirecting to Payment...</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                .spinner { border: 5px solid #f3f3f3; border-top: 5px solid #7b2cbf; border-radius: 50%; width: 60px; height: 60px; animation: spin 1s linear infinite; margin: 30px auto; }
                @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            </style>
        </head>
        <body class="bg-light">
            <div class="container text-center mt-5">
                <h3>Registration Successful – Redirecting to Payment</h3>
                <p>You are being redirected to PayFast to pay R <?= number_format(REGISTRATION_FEE, 2) ?> securely.</p>
                <div class="spinner"></div>
                <form id="payfastForm" action="<?= PAYFAST_URL ?>" method="post">
                    <?php foreach ($pfData as $k => $v): ?>
                        <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                    <?php endforeach; ?>
                </form>
                <script>
                    window.onload = () => document.getElementById('payfastForm').submit();
                </script>
            </div>
        </body>
        </html>
        <?php
        exit;

    } else {
        echo "Registration failed: " . $conn->error;
    }
}

// ===============================
// PAYFAST RETURN HANDLER (After payment)
// ===============================
if (isset($_GET['payfast_return'])) {
    session_start();
    if (!isset($_SESSION['pending_user_id'])) {
        die("Session expired. Please register again.");
    }

    $user_id = $_SESSION['pending_user_id'];
    $email   = $_SESSION['pending_email'];
    $firstName = $_SESSION['pending_firstname'];
    $lastName  = $_SESSION['pending_lastname'];

    // Update status to payment_pending (NOT active yet!)
    $stmt = $conn->prepare("UPDATE users SET status = 'pending' WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    // Send success email
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
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Payment Received – Awaiting Admin Approval';

        $mail->Body = "
            <h3>Thank You, $firstName!</h3>
            <p>Your payment of <strong>R " . number_format(REGISTRATION_FEE, 2) . "</strong> has been received.</p>
            <p>Your account is now <strong>awaiting admin verification</strong>.</p>
            <p>You will receive another email once your account is activated (usually within 24 hours).</p>
            <p>Thank you for joining Girls Coding Academy!</p>
            <hr>
            <small>If you have any questions, reply to this email.</small>
        ";

        $mail->send();
    } catch (Exception $e) {
        error_log("Payment confirmation email failed: " . $e->getMessage());
    }

    // Clear session
    unset($_SESSION['pending_user_id'], $_SESSION['pending_email'], $_SESSION['pending_firstname'], $_SESSION['pending_lastname']);

    // Show success message
    echo "
    <!DOCTYPE html>
    <html><head><title>Payment Successful</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body class='bg-light'>
    <div class='container text-center mt-5'>
        <div class='alert alert-success'>
            <h4>Payment Successful!</h4>
            <p>We've received your R " . number_format(REGISTRATION_FEE, 2) . " registration fee.</p>
            <p>Your account is under review. You will be notified by email when it's activated.</p>
            <a href='login.html' class='btn btn-primary mt-3'>Go to Login</a>
        </div>
    </div>
    </body></html>";
    exit;
}

// Cancelled payment
if (isset($_GET['payfast_cancel'])) {
    echo "
    <!DOCTYPE html>
    <html><head><title>Payment Cancelled</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body class='bg-light'>
    <div class='container text-center mt-5'>
        <div class='alert alert-warning'>
            <h4>Payment Cancelled</h4>
            <p>Your registration is saved but payment was not completed.</p>
            <p>You can log in later and complete payment from your profile.</p>
            <a href='login.html' class='btn btn-secondary'>Back to Login</a>
        </div>
    </div>
    </body></html>";
    exit;
}

$conn->close();
?>