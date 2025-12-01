<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id === 0) {
    header("Location: manage_users.php");
    exit();
}

/* ----------------------------------------------------
   FIXED SQL — NO DUPLICATE COLUMN NAMES
---------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT 
        u.user_id, u.firstName, u.lastName, u.username, u.email, u.phone,
        u.gender, u.dob, u.IDNumber, u.role, u.status, u.created_at,
        
        a.address1, a.streetName, a.postalCode, a.district, a.country,
        
        t.teacher_id, t.subject_speciality, t.photo AS teacher_photo,
        s.student_id, s.photo AS student_photo,
        p.parent_id, p.photo AS parent_photo,

        COALESCE(s.photo, t.photo, p.photo) AS final_photo
    FROM users u
    LEFT JOIN addresses a ON u.address_id = a.address_id
    LEFT JOIN teachers t ON u.user_id = t.user_id AND u.role = 'teacher'
    LEFT JOIN students s ON u.user_id = s.user_id AND u.role = 'student'
    LEFT JOIN parents p ON u.user_id = p.user_id AND u.role = 'parent'
    WHERE u.user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: manage_users.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

/* ---------------------------------------------
   SAFETY CHECK — PREVENT STRING OFFSET ERRORS
---------------------------------------------- */
if (!is_array($user)) {
    die("ERROR: User data corrupted. Dump:<br><pre>" . var_export($user, true) . "</pre>");
}

/* ----------------------------------------------------
   SAFE INITIALS
---------------------------------------------------- */
$firstInitial = (!empty($user['firstName'])) ? strtoupper(substr($user['firstName'], 0, 1)) : '?';
$lastInitial  = (!empty($user['lastName']))  ? strtoupper(substr($user['lastName'], 0, 1)) : '?';
$initials = $firstInitial . $lastInitial;

/* ----------------------------------------------------
   FINAL PHOTO PATH
---------------------------------------------------- */
$photoPath = $user['final_photo'] ?? null;
$photoExists = ($photoPath && file_exists($photoPath));

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - <?= htmlspecialchars($user['firstName'] . ' ' . $user['lastName']) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        .main-content { margin-left: 220px; transition: margin-left 0.3s ease; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
    </style>
</head>

<body class="bg-gray-50">

<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="main-content" style="padding-top: 80px;">
    <div class="p-8 min-h-screen">
        <div class="max-w-6xl mx-auto">

            <!-- Back Button -->
            <div class="mb-6">
                <a href="manage_users.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-semibold">
                    <i class="fas fa-arrow-left mr-2"></i> Back to User Management
                </a>
            </div>

            <!-- User Profile Card -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden mb-6">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-8">
                    <div class="flex items-center gap-6">

                        <!-- Profile Photo -->
                        <?php if ($photoExists): ?>
                            <img src="<?= htmlspecialchars($photoPath) ?>"
                                 class="w-32 h-32 rounded-full border-4 border-white shadow-xl object-cover">
                        <?php else: ?>
                            <div class="w-32 h-32 bg-white bg-opacity-20 rounded-full flex items-center justify-center text-5xl font-bold">
                                <?= $initials ?>
                            </div>
                        <?php endif; ?>

                        <!-- User Info -->
                        <div>
                            <h1 class="text-4xl font-bold mb-2">
                                <?= htmlspecialchars($user['firstName'] . ' ' . $user['lastName']) ?>
                            </h1>

                            <p class="text-xl text-blue-100">@<?= htmlspecialchars($user['username']) ?></p>

                            <div class="mt-3">
                                <!-- Role Badge -->
                                <span class="inline-block px-4 py-2 rounded-full text-sm font-bold
                                    <?= $user['role']=='admin'   ? 'bg-red-500' :
                                       ($user['role']=='teacher' ? 'bg-blue-500' :
                                       ($user['role']=='parent'  ? 'bg-purple-500' :
                                       ($user['role']=='student' ? 'bg-green-500' : 'bg-gray-500'))) ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>

                                <!-- Status Badge -->
                                <span class="inline-block px-4 py-2 rounded-full text-sm font-bold ml-2
                                    <?= $user['status']=='active'    ? 'bg-green-500' :
                                       ($user['status']=='pending'   ? 'bg-yellow-500' :
                                       ($user['status']=='waitlist'  ? 'bg-orange-500' : 'bg-red-500')) ?>">
                                    <?= ucfirst($user['status']) ?>
                                </span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Information Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- PERSONAL INFORMATION -->
                <div class="bg-white rounded-xl shadow-lg border p-6">
                    <h2 class="text-2xl font-bold mb-6"><i class="fas fa-user text-blue-600 mr-3"></i>Personal Information</h2>

                    <div class="space-y-4">
                        <div class="flex border-b pb-3">
                            <span class="font-semibold w-40">First Name:</span>
                            <span><?= htmlspecialchars($user['firstName']) ?></span>
                        </div>

                        <div class="flex border-b pb-3">
                            <span class="font-semibold w-40">Last Name:</span>
                            <span><?= htmlspecialchars($user['lastName']) ?></span>
                        </div>

                        <div class="flex border-b pb-3">
                            <span class="font-semibold w-40">Gender:</span>
                            <span><?= htmlspecialchars($user['gender'] ?? 'Not set') ?></span>
                        </div>

                        <div class="flex border-b pb-3">
                            <span class="font-semibold w-40">Date of Birth:</span>
                            <span><?= $user['dob'] ? date('M d, Y', strtotime($user['dob'])) : 'Not provided' ?></span>
                        </div>

                        <div class="flex border-b pb-3">
                            <span class="font-semibold w-40">ID Number:</span>
                            <span><?= htmlspecialchars($user['IDNumber'] ?? 'Not provided') ?></span>
                        </div>
                    </div>
                </div>

                <!-- CONTACT INFORMATION -->
                <div class="bg-white rounded-xl shadow-lg border p-6">
                    <h2 class="text-2xl font-bold mb-6"><i class="fas fa-address-book text-blue-600 mr-3"></i>Contact Information</h2>

                    <div class="space-y-4">
                        <div class="flex border-b pb-3">
                            <span class="font-semibold w-40">Email:</span>
                            <span><?= htmlspecialchars($user['email']) ?></span>
                        </div>

                        <div class="flex border-b pb-3">
                            <span class="font-semibold w-40">Phone:</span>
                            <span><?= htmlspecialchars($user['phone'] ?? 'Not provided') ?></span>
                        </div>
                    </div>
                </div>

                <!-- ADDRESS INFORMATION -->
                <div class="bg-white rounded-xl shadow-lg border p-6">
                    <h2 class="text-2xl font-bold mb-6"><i class="fas fa-map-marker-alt text-blue-600 mr-3"></i>Address Information</h2>

                    <div class="space-y-4">
                        <div class="flex border-b pb-3">
                            <span class="font-semibold w-40">Address Line 1:</span>
                            <span><?= htmlspecialchars($user['address1'] ?? 'Not provided') ?></span>
                        </div>

                        <div class="flex border-b pb-3">
                            <span class="font-semibold w-40">Street:</span>
                            <span><?= htmlspecialchars($user['streetName'] ?? 'Not provided') ?></span>
                        </div>

                        <div class="flex border-b pb-3">
                            <span class="font-semibold w-40">District:</span>
                            <span><?= htmlspecialchars($user['district'] ?? 'Not provided') ?></span>
                        </div>

                        <div class="flex border-b pb-3">
                            <span class="font-semibold w-40">Postal Code:</span>
                            <span><?= htmlspecialchars($user['postalCode'] ?? 'Not provided') ?></span>
                        </div>

                        <div class="flex border-b pb-3">
                            <span class="font-semibold w-40">Country:</span>
                            <span><?= htmlspecialchars($user['country'] ?? 'Not provided') ?></span>
                        </div>
                    </div>
                </div>

                <!-- ACCOUNT INFORMATION -->
                <div class="bg-white rounded-xl shadow-lg border p-6">
                    <h2 class="text-2xl font-bold mb-6"><i class="fas fa-cog text-blue-600 mr-3"></i>Account Information</h2>

                    <div class="space-y-4">

                        <div class="flex border-b pb-3">
                            <span class="font-semibold w-40">User ID:</span>
                            <span><?= $user['user_id'] ?></span>
                        </div>

                        <div class="flex border-b pb-3">
                            <span class="font-semibold w-40">Role:</span>
                            <span><?= ucfirst($user['role']) ?></span>
                        </div>

                        <div class="flex border-b pb-3">
                            <span class="font-semibold w-40">Status:</span>
                            <span><?= ucfirst($user['status']) ?></span>
                        </div>

                        <div class="flex border-b pb-3">
                            <span class="font-semibold w-40">Created At:</span>
                            <span><?= date('M d, Y h:i A', strtotime($user['created_at'])) ?></span>
                        </div>

                    </div>
                </div>

                <!-- TEACHER INFO -->
                <?php if ($user['role'] === 'teacher' && !empty($user['subject_speciality'])): ?>
                <div class="bg-white rounded-xl shadow-lg border p-6 lg:col-span-2">
                    <h2 class="text-2xl font-bold mb-6"><i class="fas fa-chalkboard-teacher text-blue-600 mr-3"></i>Teacher Information</h2>

                    <div class="space-y-4">
                        <div class="flex border-b pb-3">
                            <span class="font-semibold w-48">Subject Speciality:</span>
                            <span><?= htmlspecialchars($user['subject_speciality']) ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ACTION BUTTON -->
            <div class="mt-8 flex gap-4 justify-center">
                <a href="manage_users.php" class="bg-gray-600 hover:bg-gray-700 text-white px-8 py-3 rounded-lg font-semibold">
                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                </a>
            </div>

        </div>
    </div>
</div>

</body>
</html>
