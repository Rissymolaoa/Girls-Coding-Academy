<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}
include("db.php");

// Upload directories
$imageDir = __DIR__ . '/imageuploads/';
$imageWebPath = 'imageuploads/';
if (!is_dir($imageDir)) mkdir($imageDir, 0777, true);

function post($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

// Handle delete
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $res = $conn->prepare("SELECT address_id FROM users WHERE user_id = ?");
    $res->bind_param("i", $del_id);
    $res->execute();
    $r = $res->get_result()->fetch_assoc();
    $address_id = $r['address_id'] ?? null;
    $res->close();

    $tables = ['teachers', 'users'];
    foreach ($tables as $table) {
        $stmt = $conn->prepare("DELETE FROM $table WHERE user_id = ? " . ($table === 'users' ? "AND role='teacher'" : ""));
        $stmt->bind_param("i", $del_id);
        $stmt->execute();
        $stmt->close();
    }
    if ($address_id) {
        $stmt = $conn->prepare("DELETE FROM addresses WHERE address_id = ?");
        $stmt->bind_param("i", $address_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: manage_teachers.php");
    exit();
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = post('user_id') ? intval(post('user_id')) : null;
    $username = post('username');
    $firstName = post('firstName');
    $lastName = post('lastName');
    $gender = post('gender');
    $dob = post('dob');
    $IDNumber = post('IDNumber');
    $phone = post('phone');
    $email = post('email');
    $password = post('password');
    $status = post('status') ?: 'active';
    $address1 = post('address1');
    $streetName = post('streetName');
    $postalCode = post('postalCode');
    $district = post('district');
    $country = post('country');

    $photoPath = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $newName = time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $imageDir . $newName)) {
                $photoPath = $imageWebPath . $newName;
            }
        }
    }

    if ($user_id) {
        // EDIT
        $stmt = $conn->prepare("SELECT address_id FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $address_id = $res['address_id'] ?? null;
        $stmt->close();

        if ($photoPath) {
            $stmt = $conn->prepare("UPDATE teachers SET photo = ? WHERE user_id = ?");
            $stmt->bind_param("si", $photoPath, $user_id);
            $stmt->execute();
            $stmt->close();
        }

        if ($address_id) {
            $stmt = $conn->prepare("UPDATE addresses SET address1=?, streetName=?, postalCode=?, district=?, country=?, updated_at=NOW() WHERE address_id=?");
            $stmt->bind_param("sssssi", $address1, $streetName, $postalCode, $district, $country, $address_id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO addresses (address1, streetName, postalCode, district, country) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $address1, $streetName, $postalCode, $district, $country);
            $stmt->execute();
            $address_id = $conn->insert_id;
            $stmt->close();
            $stmt = $conn->prepare("UPDATE users SET address_id = ? WHERE user_id = ?");
            $stmt->bind_param("ii", $address_id, $user_id);
            $stmt->execute();
            $stmt->close();
        }

        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username=?, firstName=?, lastName=?, gender=?, dob=?, IDNumber=?, phone=?, email=?, password=?, status=? WHERE user_id=? AND role='teacher'");
            $stmt->bind_param("ssssssssssi", $username, $firstName, $lastName, $gender, $dob, $IDNumber, $phone, $email, $hash, $status, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username=?, firstName=?, lastName=?, gender=?, dob=?, IDNumber=?, phone=?, email=?, status=? WHERE user_id=? AND role='teacher'");
            $stmt->bind_param("sssssssssi", $username, $firstName, $lastName, $gender, $dob, $IDNumber, $phone, $email, $status, $user_id);
        }
        $stmt->execute();
        $stmt->close();
    } else {
        // ADD NEW
        $stmt = $conn->prepare("INSERT INTO addresses (address1, streetName, postalCode, district, country) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $address1, $streetName, $postalCode, $district, $country);
        $stmt->execute();
        $address_id = $conn->insert_id;
        $stmt->close();

        $hash = password_hash($password ?: 'teacher123', PASSWORD_DEFAULT);
        $role = 'teacher';
        $stmt = $conn->prepare("INSERT INTO users (username, firstName, lastName, gender, dob, IDNumber, phone, email, password, status, role, address_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssssssi", $username, $firstName, $lastName, $gender, $dob, $IDNumber, $phone, $email, $hash, $status, $role, $address_id);
        $stmt->execute();
        $new_user_id = $conn->insert_id;
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO teachers (user_id, photo) VALUES (?, ?)");
        $stmt->bind_param("is", $new_user_id, $photoPath);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: manage_teachers.php?success=1");
    exit();
}

// Pagination & Search
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

if ($search) {
    $like = "%$search%";
    $stmt = $conn->prepare("SELECT SQL_CALC_FOUND_ROWS u.*, t.photo, a.* FROM users u JOIN teachers t ON u.user_id=t.user_id LEFT JOIN addresses a ON u.address_id=a.address_id WHERE u.role='teacher' AND (u.username LIKE ? OR u.firstName LIKE ? OR u.lastName LIKE ?) ORDER BY u.created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("sssii", $like, $like, $like, $limit, $offset);
} else {
    $stmt = $conn->prepare("SELECT SQL_CALC_FOUND_ROWS u.*, t.photo, a.* FROM users u JOIN teachers t ON u.user_id=t.user_id LEFT JOIN addresses a ON u.address_id=a.address_id WHERE u.role='teacher' ORDER BY u.created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$teachers = $stmt->get_result();
$stmt->close();

$total_result = $conn->query("SELECT FOUND_ROWS() as total");
$total_row = $total_result->fetch_assoc();
$total_pages = ceil($total_row['total'] / $limit);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers - Girls Coding Academy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: #4f46e5; --primary-light: #6366f1; --primary-dark: #4338ca; }
        .bg-primary { background-color: var(--primary); }
        .text-primary { color: var(--primary); }
        .hover\:bg-primary-dark:hover { background-color: var(--primary-dark); }
        .card-hover:hover { transform: translateY(-8px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); }
    </style>
</head>
<body class="h-full bg-gray-50">

<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="ml-64 mt-16 p-8 min-h-screen">
    <div class="max-w-7xl mx-auto">

        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-4xl font-bold text-gray-800">Manage Teachers</h1>
                <p class="text-gray-600 mt-2">Add, edit, and manage teaching staff</p>
            </div>
            <button onclick="openModal()" class="bg-primary hover:bg-primary-dark text-white font-semibold py-4 px-8 rounded-xl shadow-lg transition flex items-center gap-3">
                <i class="fas fa-user-plus"></i> Add New Teacher
            </button>
        </div>

        <!-- Search -->
        <form method="get" class="mb-8">
            <div class="flex gap-4">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or username..." 
                       class="flex-1 px-6 py-4 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200 focus:border-indigo-500 transition text-lg">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-8 py-4 rounded-xl font-semibold transition flex items-center gap-3">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
        </form>

        <!-- Teachers Grid -->
        <?php if ($teachers->num_rows === 0): ?>
            <div class="text-center py-20 bg-white rounded-2xl shadow-sm border border-gray-200">
                <i class="fas fa-chalkboard-teacher text-8xl text-gray-300 mb-6"></i>
                <p class="text-2xl text-gray-600">No teachers found</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php while ($t = $teachers->fetch_assoc()): 
                    $address = trim(($t['address1']??'') . ' ' . ($t['streetName']??'') . ', ' . ($t['district']??'') . ' ' . ($t['country']??''));
                ?>
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden card-hover transition-all duration-300">
                        <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white p-6 text-center">
                            <?php if (!empty($t['photo']) && file_exists($t['photo'])): ?>
                                <img src="<?= htmlspecialchars($t['photo']) ?>" class="w-28 h-28 rounded-full mx-auto border-4 border-white shadow-xl object-cover">
                            <?php else: ?>
                                <div class="w-28 h-28 bg-white bg-opacity-20 rounded-full mx-auto flex items-center justify-center text-5xl font-bold">
                                    <?= strtoupper(substr($t['firstName'],0,1).substr($t['lastName'],0,1)) ?>
                                </div>
                            <?php endif; ?>
                            <h3 class="text-2xl font-bold mt-4"><?= htmlspecialchars($t['firstName'] . ' ' . $t['lastName']) ?></h3>
                            <p class="text-indigo-100">@<?= htmlspecialchars($t['username']) ?></p>
                        </div>

                        <div class="p-6 space-y-4">
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div><span class="text-gray-600">Email:</span><br><strong><?= htmlspecialchars($t['email']) ?></strong></div>
                                <div><span class="text-gray-600">Phone:</span><br><strong><?= htmlspecialchars($t['phone'] ?? '—') ?></strong></div>
                                <div><span class="text-gray-600">Gender:</span><br><strong><?= ucfirst($t['gender'] ?? '—') ?></strong></div>
                                <div><span class="text-gray-600">Status:</span><br>
                                    <span class="px-3 py-1 rounded-full text-xs font-bold <?= $t['status']=='active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' ?>">
                                        <?= ucfirst($t['status']) ?>
                                    </span>
                                </div>
                            </div>
                            <?php if ($address): ?>
                                <div class="text-sm text-gray-600 border-t pt-4">
                                    <i class="fas fa-map-marker-alt text-primary mr-2"></i>
                                    <?= htmlspecialchars($address) ?>
                                </div>
                            <?php endif; ?>

                            <div class="flex gap-3 pt-4 border-t">
                                <button onclick='editTeacher(<?= json_encode($t) ?>)' 
                                        class="flex-1 bg-white border border-indigo-600 text-indigo-600 py-3 rounded-xl font-semibold hover:bg-indigo-50 transition">
                                    <i class="fas fa-edit mr-2"></i> Edit
                                </button>
                                <a href="?delete=<?= $t['user_id'] ?>" onclick="return confirm('Delete this teacher permanently?')"
                                   class="flex-1 bg-red-600 text-white py-3 rounded-xl font-semibold hover:bg-red-700 transition text-center">
                                    <i class="fas fa-trash mr-2"></i> Delete
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center gap-3 mt-12">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?><?= $search ? '&search='.urlencode($search) : '' ?>"
                           class="px-5 py-3 rounded-lg font-medium <?= $i === $page ? 'bg-primary text-white' : 'bg-white border border-gray-300 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div id="teacherModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4" onclick="if(event.target===this) closeModal()">
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-screen overflow-y-auto">
        <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white p-8 rounded-t-2xl">
            <h2 class="text-3xl font-bold" id="modalTitle">Add New Teacher</h2>
        </div>
        <form method="post" enctype="multipart/form-data" class="p-8 space-y-6">
            <input type="hidden" name="user_id" id="user_id">
            <div class="text-center">
                <img id="photoPreview" src="imageuploads/default-avatar.png" class="w-32 h-32 rounded-full mx-auto border-4 border-indigo-200 object-cover">
                <input type="file" name="photo" id="photo" accept="image/*" class="mt-4">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <input type="text" name="username" id="username" placeholder="Username" required class="px-5 py-4 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200">
                <input type="password" name="password" id="password" placeholder="Password (leave blank to keep)" class="px-5 py-4 border border-gray-300 rounded-xl">
                <input type="text" name="firstName" id="firstName" placeholder="First Name" required class="px-5 py-4 border border-gray-300 rounded-xl">
                <input type="text" name="lastName" id="lastName" placeholder="Last Name" required class="px-5 py-4 border border-gray-300 rounded-xl">
                <select name="gender" id="gender" class="px-5 py-4 border border-gray-300 rounded-xl">
                    <option value="">Gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
                <input type="date" name="dob" id="dob" class="px-5 py-4 border border-gray-300 rounded-xl">
                <input type="email" name="email" id="email" placeholder="Email" required class="px-5 py-4 border border-gray-300 rounded-xl">
                <input type="text" name="phone" id="phone" placeholder="Phone" class="px-5 py-4 border border-gray-300 rounded-xl">
                <input type="text" name="IDNumber" id="IDNumber" placeholder="ID Number" class="px-5 py-4 border border-gray-300 rounded-xl">
                <select name="status" id="status" class="px-5 py-4 border border-gray-300 rounded-xl">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="space-y-4">
                <h3 class="text-xl font-bold text-gray-800">Address</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <input type="text" name="address1" id="address1" placeholder="Address Line 1" class="px-5 py-4 border border-gray-300 rounded-xl">
                    <input type="text" name="streetName" id="streetName" placeholder="Street Name" class="px-5 py-4 border border-gray-300 rounded-xl">
                    <input type="text" name="postalCode" id="postalCode" placeholder="Postal Code" class="px-5 py-4 border border-gray-300 rounded-xl">
                    <input type="text" name="district" id="district" placeholder="District" class="px-5 py-4 border border-gray-300 rounded-xl">
                    <input type="text" name="country" id="country" placeholder="Country" value="Lesotho" class="px-5 py-4 border border-gray-300 rounded-xl">
                </div>
            </div>

            <div class="flex justify-end gap-4 pt-6">
                <button type="button" onclick="closeModal()" class="px-8 py-4 border border-gray-300 rounded-xl font-medium hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-8 py-4 bg-primary text-white font-medium rounded-xl hover:bg-primary-dark transition">Save Teacher</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('teacherModal').classList.remove('hidden');
    document.getElementById('modalTitle').textContent = 'Add New Teacher';
    document.querySelector('#teacherModal form').reset();
    document.getElementById('photoPreview').src = 'imageuploads/default-avatar.png';
    document.getElementById('user_id').value = '';
}

function closeModal() {
    document.getElementById('teacherModal').classList.add('hidden');
}

function editTeacher(data) {
    document.getElementById('modalTitle').textContent = 'Edit Teacher';
    document.getElementById('user_id').value = data.user_id;
    document.getElementById('username').value = data.username || '';
    document.getElementById('firstName').value = data.firstName || '';
    document.getElementById('lastName').value = data.lastName || '';
    document.getElementById('gender').value = data.gender || '';
    document.getElementById('dob').value = data.dob || '';
    document.getElementById('email').value = data.email || '';
    document.getElementById('phone').value = data.phone || '';
    document.getElementById('IDNumber').value = data.IDNumber || '';
    document.getElementById('status').value = data.status || 'active';
    document.getElementById('address1').value = data.address1 || '';
    document.getElementById('streetName').value = data.streetName || '';
    document.getElementById('postalCode').value = data.postalCode || '';
    document.getElementById('district').value = data.district || '';
    document.getElementById('country').value = data.country || '';
    document.getElementById('photoPreview').src = data.photo || 'imageuploads/default-avatar.png';
    document.getElementById('teacherModal').classList.remove('hidden');
}

document.getElementById('photo').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('photoPreview').src = e.target.result;
        reader.readAsDataURL(file);
    }
});
</script>
</body>
</html>