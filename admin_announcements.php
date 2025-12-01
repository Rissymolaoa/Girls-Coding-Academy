<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html"); exit();
}
include("db.php");

$search = trim($_GET['search'] ?? '');
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_announcement'])) {
    // GET THE FULL HTML FROM QUILL
    $message = $_POST['message'] ?? '';

    // FIX: Accept any real content — even if it's just <p><strong>Hi</strong></p>
    $stripped = trim(strip_tags($message));
    if ($message === '' || $message === '<p><br></p>' || $message === '<div><br></div>' || empty($stripped)) {
        $errors[] = "Please write an announcement message.";
    }

    $recipients = $_POST['recipients'] ?? [];
    if (empty($recipients)) {
        $errors[] = "Please select at least one recipient group.";
    }

    $file_path = $picture_path = null;

    // File upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (in_array($_FILES['file']['type'], $allowed)) {
            $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $name = uniqid('file_') . '.' . $ext;
            $dir = __DIR__ . '/uploads/announcements/files/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (move_uploaded_file($_FILES['file']['tmp_name'], $dir . $name)) {
                $file_path = "uploads/announcements/files/$name";
            }
        } else {
            $errors[] = "Only PDF, DOC, DOCX allowed.";
        }
    }

    // Image upload
    if (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($_FILES['picture']['type'], $allowed)) {
            $ext = pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION);
            $name = uniqid('img_') . '.' . $ext;
            $dir = __DIR__ . '/uploads/announcements/images/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (move_uploaded_file($_FILES['picture']['tmp_name'], $dir . $name)) {
                $picture_path = "uploads/announcements/images/$name";
            }
        } else {
            $errors[] = "Only images allowed.";
        }
    }

    if (empty($errors)) {
        $recipients_str = implode(',', $recipients);
        $stmt = $conn->prepare("INSERT INTO admin_announcements (message, file_path, picture_path, recipients, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $message, $file_path, $picture_path, $recipients_str);
        if ($stmt->execute()) {
            $success = "Announcement published successfully!";
        } else {
            $errors[] = "Database error.";
        }
        $stmt->close();
    }
}

// Fetch announcements
$where = $search ? "WHERE message LIKE ? OR recipients LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%"] : [];
$types = $search ? "ss" : "";

$sql = "SELECT * FROM admin_announcements $where ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if ($search) $stmt->bind_param($types, ...$params);
$stmt->execute();
$announcements = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements • GCA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <style>
        :root { --primary: #0ea5e9; --primary-dark: #0284c7; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); min-height: 100vh; }
        .glass { background: rgba(255,255,255,0.15); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.3); }
        .card-hover:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(14,165,233,0.2); }
        .announcement-card { transition: all 0.4s; border-left: 6px solid transparent; }
        .announcement-card:hover { border-left-color: var(--primary); }
        .ql-editor { min-height: 220px; font-size: 1.1rem; }
        .ql-container { border-radius: 1rem; }
        .ql-toolbar { border-top-left-radius: 1rem; border-top-right-radius: 1rem; background: #f8fafc; }
        .sidebar { width: 280px; background: linear-gradient(180deg, #1e293b, #0f172a); position: fixed; height: 100vh; top: 0; left: 0; z-index: 1000; }
        .main-content { margin-left: 280px; padding: 2rem; min-height: 100vh; }
        @media (max-width: 992px) { .sidebar { transform: translateX(-100%); } .main-content { margin-left: 0; } }
    </style>
</head>
<body>

<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="main-content">
    <div class="max-w-7xl mx-auto">

        <!-- Header -->
        <div class="glass rounded-3xl p-12 mb-10 text-center border border-white/30 shadow-2xl">
            <h1 class="text-6xl font-extrabold bg-gradient-to-r from-sky-600 to-blue-700 bg-clip-text text-transparent mb-4">
                Admin Announcements
            </h1>
            <p class="text-2xl text-gray-700">Reach your entire academy instantly</p>
        </div>

        <!-- Post Announcement -->
        <div class="glass rounded-3xl p-10 mb-10 border border-white/30 shadow-2xl">
            <h2 class="text-4xl font-bold text-gray-800 mb-10">
                Create New Announcement
            </h2>

            <?php if ($success): ?>
                <div class="bg-emerald-100 border border-emerald-400 text-emerald-700 px-8 py-6 rounded-2xl mb-10 text-xl">
                    <strong>Success!</strong> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-8 py-6 rounded-2xl mb-10">
                    <ul class="space-y-3 text-lg">
                        <?php foreach($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-10">
                <input type="hidden" name="message" id="message_content">

                <div>
                    <label class="text-xl font-bold text-gray-800 mb-6 block">Message</label>
                    <div id="editor" class="bg-white rounded-2xl overflow-hidden shadow-inner"></div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
                    <div>
                        <label class="text-xl font-bold text-gray-800 mb-6 block">Recipients</label>
                        <div class="space-y-5">
                            <label class="flex items-center gap-6 p-6 bg-white/70 rounded-3xl cursor-pointer hover:bg-white transition shadow-md">
                                <input type="checkbox" name="recipients[]" value="students" class="w-7 h-7 text-sky-600 rounded">
                                <div class="flex-1">
                                    <div class="text-2xl font-bold text-gray-800">Students</div>
                                    <div class="text-gray-600">All enrolled learners</div>
                                </div>
                            </label>
                            <label class="flex items-center gap-6 p-6 bg-white/70 rounded-3xl cursor-pointer hover:bg-white transition shadow-md">
                                <input type="checkbox" name="recipients[]" value="teachers" class="w-7 h-7 text-sky-600 rounded">
                                <div class="flex-1">
                                    <div class="text-2xl font-bold text-gray-800">Teachers</div>
                                    <div class="text-gray-600">All teaching staff</div>
                                </div>
                            </label>
                            <label class="flex items-center gap-6 p-6 bg-white/70 rounded-3xl cursor-pointer hover:bg-white transition shadow-md">
                                <input type="checkbox" name="recipients[]" value="parents" class="w-7 h-7 text-sky-600 rounded">
                                <div class="flex-1">
                                    <div class="text-2xl font-bold text-gray-800">Parents</div>
                                    <div class="text-gray-600">All registered guardians</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="text-xl font-bold text-gray-800 mb-6 block">Attachments</label>
                        <div class="space-y-8">
                            <div>
                                <label class="block text-lg font-medium text-gray-700 mb-4">Image (optional)</label>
                                <input type="file" name="picture" accept="image/*" class="block w-full text-lg file:mr-6 file:py-4 file:px-8 file:rounded-full file:border-0 file:text-lg file:font-bold file:bg-gradient-to-r file:from-sky-600 file:to-blue-700 file:text-white">
                                <div id="imagePreview" class="mt-6 rounded-3xl overflow-hidden shadow-2xl hidden">
                                    <img src="" alt="Preview" class="w-full max-h-96 object-cover">
                                </div>
                            </div>
                            <div>
                                <label class="block text-lg font-medium text-gray-700 mb-4">Document (PDF/DOC)</label>
                                <input type="file" name="file" accept=".pdf,.doc,.docx" class="block w-full text-lg file:mr-6 file:py-4 file:px-8 file:rounded-full file:border-0 file:text-lg file:font-bold file:bg-gradient-to-r file:from-indigo-600 file:to-purple-700 file:text-white">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center pt-10">
                    <button type="submit" name="submit_announcement" class="bg-gradient-to-r from-sky-600 to-blue-700 text-white font-bold text-2xl py-6 px-20 rounded-full hover:from-sky-700 hover:to-blue-800 transform hover:scale-105 transition shadow-2xl">
                        Publish Announcement
                    </button>
                </div>
            </form>
        </div>

        <!-- All Announcements -->
        <div class="glass rounded-3xl p-10 border border-white/30">
            <div class="flex justify-between items-center mb-10">
                <h2 class="text-4xl font-bold text-gray-800">All Announcements</h2>
                <div class="relative">
                    <input type="text" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" onchange="location.href='?search='+encodeURIComponent(this.value)" class="pl-14 pr-8 py-5 rounded-full border-2 border-sky-200 focus:border-sky-500 focus:ring-4 focus:ring-sky-100 w-96 text-xl">
                    <i class="bi bi-search absolute left-5 top-1/2 -translate-y-1/2 text-sky-600 text-2xl"></i>
                </div>
            </div>

            <div class="space-y-10">
                <?php if ($announcements && $announcements->num_rows > 0): ?>
                    <?php while ($ann = $announcements->fetch_assoc()): ?>
                        <div class="announcement-card bg-white rounded-3xl p-10 shadow-2xl border border-gray-200 card-hover">
                            <div class="flex justify-between items-start mb-8">
                                <div class="flex flex-wrap gap-4">
                                    <?php foreach (explode(',', $ann['recipients']) as $r): ?>
                                        <span class="px-5 py-2 rounded-full text-sm font-bold <?= $r==='students'?'bg-sky-100 text-sky-800':($r==='teachers'?'bg-indigo-100 text-indigo-800':'bg-purple-100 text-purple-800') ?>">
                                            <?= ucfirst($r) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-gray-500">
                                    <?= date("M j, Y \a\\t g:i A", strtotime($ann['created_at'])) ?>
                                </div>
                            </div>

                            <div class="prose prose-xl max-w-none text-gray-800 mb-8">
                                <?= $ann['message'] ?>
                            </div>

                            <?php if ($ann['picture_path']): ?>
                                <div class="mb-8 -mx-10">
                                    <img src="<?= htmlspecialchars($ann['picture_path']) ?>" alt="Image" class="w-full max-h-96 object-cover rounded-3xl">
                                </div>
                            <?php endif; ?>

                            <?php if ($ann['file_path']): ?>
                                <div class="pt-8 border-t border-gray-200">
                                    <a href="<?= htmlspecialchars($ann['file_path']) ?>" target="_blank" class="inline-flex items-center gap-4 text-sky-600 font-bold text-xl hover:text-sky-700 transition">
                                        Download Attachment
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-24">
                        <p class="text-3xl text-gray-500">No announcements yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Quill Editor
const quill = new Quill('#editor', {
    theme: 'snow',
    modules: { toolbar: [['bold', 'italic', 'underline'], ['link', 'image'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], ['clean']] }
});

// Capture content on submit
document.querySelector('form').onsubmit = function() {
    const content = quill.root.innerHTML.trim();
    document.getElementById('message_content').value = content;
    return true;
};

// Image preview
document.querySelector('input[name="picture"]').addEventListener('change', function(e) {
    if (this.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const preview = document.getElementById('imagePreview');
            preview.querySelector('img').src = e.target.result;
            preview.classList.remove('hidden');
        };
        reader.readAsDataURL(this.files[0]);
    }
});
</script>
</body>
</html>