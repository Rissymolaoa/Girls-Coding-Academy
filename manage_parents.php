<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}
include("db.php");

$imageDir = __DIR__ . '/imageuploads/';
$imageWebPath = 'imageuploads/';
$docDir = __DIR__ . '/uploads/docs/';
$docWebPath = 'uploads/docs/';
if (!is_dir($imageDir)) mkdir($imageDir, 0777, true);
if (!is_dir($docDir)) mkdir($docDir, 0777, true);

function post($key) { return isset($_POST[$key]) ? trim($_POST[$key]) : null; }

// Delete parent
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $res = $conn->prepare("SELECT address_id FROM users WHERE user_id = ?");
    $res->bind_param("i", $del_id); $res->execute();
    $r = $res->get_result()->fetch_assoc(); $address_id = $r['address_id'] ?? null; $res->close();

    $conn->query("DELETE FROM parent_students WHERE parent_id = (SELECT parent_id FROM parents WHERE user_id = $del_id)");
    $conn->query("DELETE FROM parents WHERE user_id = $del_id");
    $conn->query("DELETE FROM users WHERE user_id = $del_id AND role = 'parent'");
    if ($address_id) $conn->query("DELETE FROM addresses WHERE address_id = $address_id");

    header("Location: manage_parents.php");
    exit();
}

// Add/Edit parent
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = post('user_id') ? intval(post('user_id')) : null;
    $username = post('username'); $firstName = post('firstName'); $lastName = post('lastName');
    $dob = post('dob'); $gender = post('gender'); $IDNumber = post('IDNumber');
    $phone = post('phone'); $email = post('email'); $password = post('password');
    $status = post('status') ?: 'active'; $relationship = post('relationship');
    $address1 = post('address1'); $streetName = post('streetName'); $postalCode = post('postalCode');
    $district = post('district'); $country = post('country');

    $photoPath = $docPath = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            $name = time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $imageDir . $name)) {
                $photoPath = $imageWebPath . $name;
            }
        }
    }
    if (isset($_FILES['document']) && $_FILES['document']['error'] === 0 && strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION)) === 'pdf') {
        $name = uniqid('doc_') . '.pdf';
        if (move_uploaded_file($_FILES['document']['tmp_name'], $docDir . $name)) {
            $docPath = $docWebPath . $name;
        }
    }

    if ($user_id) {
        // EDIT
        $stmt = $conn->prepare("SELECT address_id FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id); $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc(); $address_id = $res['address_id'] ?? null; $stmt->close();

        if ($address_id) {
            $stmt = $conn->prepare("UPDATE addresses SET address1=?, streetName=?, postalCode=?, district=?, country=? WHERE address_id=?");
            $stmt->bind_param("sssssi", $address1, $streetName, $postalCode, $district, $country, $address_id);
            $stmt->execute(); $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO addresses (address1,streetName,postalCode,district,country) VALUES (?,?,?,?,?)");
            $stmt->bind_param("sssss", $address1, $streetName, $postalCode, $district, $country);
            $stmt->execute(); $address_id = $conn->insert_id; $stmt->close();
            $conn->query("UPDATE users SET address_id = $address_id WHERE user_id = $user_id");
        }

        $sql = "UPDATE users SET username=?, firstName=?, lastName=?, dob=?, gender=?, IDNumber=?, phone=?, email=?, status=?";
        $params = [$username, $firstName, $lastName, $dob, $gender, $IDNumber, $phone, $email, $status];
        $types = "sssssssss";
        if ($password) { $sql .= ", password=?"; $params[] = password_hash($password, PASSWORD_DEFAULT); $types .= "s"; }
        if ($docPath) { $sql .= ", document=?"; $params[] = $docPath; $types .= "s"; }
        $sql .= " WHERE user_id=? AND role='parent'"; $params[] = $user_id; $types .= "i";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params); $stmt->execute(); $stmt->close();

        $stmt = $conn->prepare("UPDATE parents SET relationship=?, photo=? WHERE user_id=?");
        $stmt->bind_param("ssi", $relationship, $photoPath, $user_id);
        $stmt->execute(); $stmt->close();
    } else {
        // ADD
        $stmt = $conn->prepare("INSERT INTO addresses (address1,streetName,postalCode,district,country) VALUES (?,?,?,?,?)");
        $stmt->bind_param("sssss", $address1, $streetName, $postalCode, $district, $country);
        $stmt->execute(); $address_id = $conn->insert_id; $stmt->close();

        $hash = password_hash($password ?: 'parent123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username,firstName,lastName,dob,gender,IDNumber,phone,email,password,status,role,document,address_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssssssssssssi", $username, $firstName, $lastName, $dob, $gender, $IDNumber, $phone, $email, $hash, $status, $role, $docPath, $address_id);
        $stmt->execute(); $new_user_id = $conn->insert_id; $stmt->close();

        $stmt = $conn->prepare("INSERT INTO parents (user_id, relationship, photo) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $new_user_id, $relationship, $photoPath);
        $stmt->execute(); $stmt->close();
    }
    header("Location: manage_parents.php");
    exit();
}

// Pagination & Search
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

$where = $search ? "AND (u.username LIKE ? OR u.firstName LIKE ? OR u.lastName LIKE ?)" : "";
$like = $search ? "%$search%" : "";

$count_sql = "SELECT COUNT(*) as total FROM users u JOIN parents p ON u.user_id = p.user_id WHERE u.role='parent' $where";
$stmt = $conn->prepare($count_sql);
if ($search) $stmt->bind_param("sss", $like, $like, $like);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $limit);
$stmt->close();

$sql = "
    SELECT u.*, p.relationship, p.photo, a.*, 
           GROUP_CONCAT(DISTINCT CONCAT(s.student_id, ':', us.firstName, ' ', us.lastName, ':', s.photo) SEPARATOR '|') as children
    FROM users u
    JOIN parents p ON u.user_id = p.user_id
    LEFT JOIN addresses a ON u.address_id = a.address_id
    LEFT JOIN parent_students ps ON ps.parent_id = p.parent_id
    LEFT JOIN students s ON ps.student_id = s.student_id
    LEFT JOIN users us ON s.user_id = us.user_id
    WHERE u.role='parent' $where
    GROUP BY u.user_id
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
if ($search) $stmt->bind_param("sssii", $like, $like, $like, $limit, $offset);
else $stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$parents = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Parents - Girls Coding Academy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: #4f46e5; --primary-light: #6366f1; }
        .bg-primary { background-color: var(--primary); }
        .text-primary { color: var(--primary); }
        .hover\:bg-primary-dark:hover { background-color: #4338ca; }
        .card-hover:hover { transform: translateY(-8px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="h-full bg-gray-50">

<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="ml-64 mt-16 p-8 min-h-screen">
    <div class="max-w-7xl mx-auto">

        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-4xl font-bold text-gray-800">Manage Parents & Guardians</h1>
                <p class="text-gray-600 mt-2">View and manage parent accounts and their children</p>
            </div>
            <button onclick="openModal()" class="bg-primary hover:bg-primary-dark text-white font-semibold py-4 px-8 rounded-xl shadow-lg transition flex items-center gap-3">
                <i class="fas fa-user-plus"></i> Add Parent
            </button>
        </div>

        <form method="get" class="mb-8">
            <div class="flex gap-4">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search parent..." 
                       class="flex-1 px-6 py-4 border border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200 text-lg">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-8 py-4 rounded-xl font-semibold transition flex items-center gap-3">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
        </form>

        <?php if ($parents->num_rows === 0): ?>
            <div class="text-center py-20 bg-white rounded-2xl shadow-sm border border-gray-200">
                <i class="fas fa-users text-8xl text-gray-300 mb-6"></i>
                <p class="text-2xl text-gray-600">No parents found</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php while ($p = $parents->fetch_assoc()): 
                    $children = [];
                    if ($p['children']) {
                        foreach (explode('|', $p['children']) as $child) {
                            if ($child) {
                                [$id, $name, $photo] = explode(':', $child, 3);
                                $children[] = ['id' => $id, 'name' => $name, 'photo' => $photo];
                            }
                        }
                    }
                ?>
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden card-hover transition-all duration-300">
                        <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white p-6 text-center">
                            <?php if (!empty($p['photo']) && file_exists($p['photo'])): ?>
                                <img src="<?= htmlspecialchars($p['photo']) ?>" class="w-28 h-28 rounded-full mx-auto border-4 border-white shadow-xl object-cover">
                            <?php else: ?>
                                <div class="w-28 h-28 bg-white bg-opacity-20 rounded-full mx-auto flex items-center justify-center text-5xl font-bold">
                                    <?= strtoupper(substr($p['firstName'],0,1).substr($p['lastName'],0,1)) ?>
                                </div>
                            <?php endif; ?>
                            <h3 class="text-2xl font-bold mt-4"><?= htmlspecialchars($p['firstName'] . ' ' . $p['lastName']) ?></h3>
                            <p class="text-indigo-100">@<?= htmlspecialchars($p['username']) ?></p>
                            <span class="inline-block mt-2 px-4 py-1 bg-white bg-opacity-20 rounded-full text-sm">
                                <?= htmlspecialchars($p['relationship']) ?>
                            </span>
                        </div>

                        <div class="p-6 space-y-4">
                            <div class="text-sm grid grid-cols-2 gap-3">
                                <div><span class="text-gray-600">Email:</span><br><strong><?= htmlspecialchars($p['email']) ?></strong></div>
                                <div><span class="text-gray-600">Phone:</span><br><strong><?= htmlspecialchars($p['phone'] ?? '—') ?></strong></div>
                            </div>

                            <?php if (!empty($children)): ?>
                                <div class="border-t pt-4">
                                    <p class="font-semibold text-gray-700 mb-3">Linked Students (<?= count($children) ?>)</p>
                                    <div class="flex flex-wrap gap-3">
                                        <?php foreach ($children as $child): ?>
                                            <a href="academics.php?student_id=<?= $child['id'] ?>" class="group">
                                                <?php if ($child['photo'] && file_exists($child['photo'])): ?>
                                                    <img src="<?= htmlspecialchars($child['photo']) ?>" class="w-16 h-16 rounded-full border-2 border-indigo-200 group-hover:border-indigo-500 transition">
                                                <?php else: ?>
                                                    <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 font-bold text-lg group-hover:bg-indigo-200 transition">
                                                        <?= strtoupper(substr($child['name'],0,2)) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <p class="text-xs text-center mt-1 text-gray-600 group-hover:text-indigo-600"><?= htmlspecialchars($child['name']) ?></p>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="flex gap-3 pt-4 border-t">
                                <button onclick='editParent(<?= json_encode($p) ?>)' 
                                        class="flex-1 bg-white border border-indigo-600 text-indigo-600 py-3 rounded-xl font-semibold hover:bg-indigo-50 transition">
                                    <i class="fas fa-edit mr-2"></i> Edit
                                </button>
                                <a href="?delete=<?= $p['user_id'] ?>" onclick="return confirm('Delete this parent and all links?')"
                                   class="flex-1 bg-red-600 text-white py-3 rounded-xl font-semibold hover:bg-red-700 transition text-center">
                                    <i class="fas fa-trash mr-2"></i> Delete
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

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
<div id="parentModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4" onclick="if(event.target===this) closeModal()">
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-screen overflow-y-auto">
        <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white p-8 rounded-t-2xl">
            <h2 class="text-3xl font-bold" id="modalTitle">Add New Parent</h2>
        </div>
        <form method="post" enctype="multipart/form-data" class="p-8 space-y-6">
            <input type="hidden" name="user_id" id="user_id">
            <div class="text-center">
                <img id="photoPreview" src="imageuploads/default-avatar.png" class="w-32 h-32 rounded-full mx-auto border-4 border-indigo-200 object-cover">
                <input type="file" name="photo" accept="image/*" class="mt-4">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <input type="text" name="username" id="username" placeholder="Username" required class="px-5 py-4 border border-gray-300 rounded-xl">
                <input type="password" name="password" id="password" placeholder="Password (leave blank to keep)" class="px-5 py-4 border border-gray-300 rounded-xl">
                <input type="text" name="firstName" id="firstName" placeholder="First Name" required class="px-5 py-4 border border-gray-300 rounded-xl">
                <input type="text" name="lastName" id="lastName" placeholder="Last Name" required class="px-5 py-4 border border-gray-300 rounded-xl">
                <input type="date" name="dob" id="dob" class="px-5 py-4 border border-gray-300 rounded-xl">
                <select name="relationship" id="relationship" required class="px-5 py-4 border border-gray-300 rounded-xl">
                    <option value="">Relationship</option>
                    <option value="Mother">Mother</option>
                    <option value="Father">Father</option>
                    <option value="Guardian">Guardian</option>
                </select>
                <select name="gender" id="gender" class="px-5 py-4 border border-gray-300 rounded-xl">
                    <option value="">Gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
                <input type="text" name="IDNumber" id="IDNumber" placeholder="ID Number" class="px-5 py-4 border border-gray-300 rounded-xl">
                <input type="email" name="email" id="email" placeholder="Email" required class="px-5 py-4 border border-gray-300 rounded-xl">
                <input type="text" name="phone" id="phone" placeholder="Phone" class="px-5 py-4 border border-gray-300 rounded-xl">
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
                <button type="submit" class="px-8 py-4 bg-primary text-white font-medium rounded-xl hover:bg-primary-dark transition">Save Parent</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('parentModal').classList.remove('hidden');
    document.getElementById('modalTitle').textContent = 'Add New Parent';
    document.querySelector('#parentModal form').reset();
    document.getElementById('photoPreview').src = 'imageuploads/default-avatar.png';
    document.getElementById('user_id').value = '';
}

function closeModal() {
    document.getElementById('parentModal').classList.add('hidden');
}

function editParent(data) {
    document.getElementById('modalTitle').textContent = 'Edit Parent';
    document.getElementById('user_id').value = data.user_id;
    ['username','firstName','lastName','dob','gender','IDNumber','phone','email','status','relationship','address1','streetName','postalCode','district','country'].forEach(f => {
        const el = document.getElementById(f);
        if (el) el.value = data[f] || '';
    });
    document.getElementById('photoPreview').src = data.photo || 'imageuploads/default-avatar.png';
    document.getElementById('parentModal').classList.remove('hidden');
}

document.querySelector('[name="photo"]').addEventListener('change', function(e) {
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