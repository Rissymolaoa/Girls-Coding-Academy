<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}
include("db.php");

$search = trim($_GET['search'] ?? '');
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_announcement'])) {
    $message = trim($_POST['message'] ?? '');

    if (strlen($message) < 5) {
        $errors[] = "Please write a meaningful announcement (at least 5 characters).";
    }

    $recipients = $_POST['recipients'] ?? [];
    if (empty($recipients)) {
        $errors[] = "Please select at least one recipient group.";
    }

    $file_path = $picture_path = null;

    // Image Upload
    if (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/jpg','image/png','image/webp','image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['picture']['tmp_name']);
        finfo_close($finfo);

        if (in_array($mime, $allowed) && $_FILES['picture']['size'] <= 5*1024*1024) {
            $ext = pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION);
            $name = 'img_' . uniqid() . '.' . $ext;
            $dir = 'uploads/announcements/images/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (move_uploaded_file($_FILES['picture']['tmp_name'], $dir . $name)) {
                $picture_path = $dir . $name;
            }
        }
    }

    // Document Upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['file']['tmp_name']);
        finfo_close($finfo);

        if (in_array($mime, $allowed) && $_FILES['file']['size'] <= 10*1024*1024) {
            $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $name = 'doc_' . uniqid() . '.' . $ext;
            $dir = 'uploads/announcements/files/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (move_uploaded_file($_FILES['file']['tmp_name'], $dir . $name)) {
                $file_path = $dir . $name;
            }
        }
    }

    if (empty($errors)) {
        $recipients_str = implode(',', $recipients);
        $stmt = $conn->prepare("INSERT INTO admin_announcements (message, file_path, picture_path, recipients, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $message, $file_path, $picture_path, $recipients_str);
        if ($stmt->execute()) {
            $success = "Announcement published successfully to " . implode(', ', $recipients) . "!";
            $_POST = [];
        } else {
            $errors[] = "Failed to publish. Try again.";
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #f0f7ff 0%, #e8f2ff 100%); }
        
        .file-input::file-selector-button {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-right: 1rem;
            transition: all 0.3s;
        }
        
        .file-input::file-selector-button:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
        }
        
        .recipient-btn {
            @apply p-4 rounded-lg border-2 border-gray-200 bg-white cursor-pointer transition-all duration-300 flex items-center justify-between;
        }
        
        .recipient-btn input:checked ~ .recipient-content {
            @apply border-blue-500 bg-blue-50;
        }
        
        .recipient-checked {
            @apply border-blue-500 bg-blue-50;
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="ml-64 mt-16 min-h-screen p-8">
    <div class="max-w-6xl mx-auto">

        <!-- Header -->
        <div class="mb-10">
            <h1 class="text-5xl font-bold text-gray-900">Announcements</h1>
            <p class="text-gray-600 mt-2">Send updates to students, teachers, and parents</p>
        </div>

        <!-- Success Alert -->
        <?php if ($success): ?>
            <div class="fade-in bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 rounded-lg p-6 mb-8 shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-2xl text-green-600"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-green-900 text-lg">Success!</h3>
                        <p class="text-green-700 mt-1"><?= $success ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Error Alerts -->
        <?php if ($errors): ?>
            <div class="fade-in bg-gradient-to-r from-red-50 to-rose-50 border-l-4 border-red-500 rounded-lg p-6 mb-8 shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-2xl text-red-600"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-red-900 text-lg">Please fix the following:</h3>
                        <ul class="text-red-700 mt-3 space-y-1 list-disc list-inside">
                            <?php foreach($errors as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Create Announcement Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-10 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-8 py-6 border-b border-gray-200">
                <h2 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                    <i class="fas fa-paper-plane text-blue-600"></i>
                    Create New Announcement
                </h2>
            </div>

            <form method="POST" enctype="multipart/form-data" class="p-8">

                <!-- Message -->
                <div class="mb-8">
                    <label class="block text-sm font-bold text-gray-900 mb-3">Message *</label>
                    <textarea name="message" rows="6" required
                              placeholder="Write your announcement here..."
                              class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-blue-500 focus:ring-4 focus:ring-blue-100 outline-none transition-all"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    <p class="text-xs text-gray-500 mt-2">Line breaks and formatting will be preserved</p>
                </div>

                <!-- Recipients and Files Grid -->
                <div class="grid lg:grid-cols-3 gap-8 mb-8">
                    
                    <!-- Recipients -->
                    <div class="lg:col-span-1">
                        <label class="block text-sm font-bold text-gray-900 mb-4">Send To *</label>
                        <div class="space-y-3">
                            <?php 
                            $recipientGroups = [
                                'students' => ['label' => 'Students', 'icon' => 'fa-user-graduate', 'color' => 'blue'],
                                'teachers' => ['label' => 'Teachers', 'icon' => 'fa-chalkboard-teacher', 'color' => 'indigo'],
                                'parents' => ['label' => 'Parents', 'icon' => 'fa-users', 'color' => 'purple']
                            ];
                            ?>
                            <?php foreach($recipientGroups as $value => $group): ?>
                                <label class="recipient-btn <?= in_array($value, $_POST['recipients'] ?? []) ? 'recipient-checked' : '' ?>">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox" name="recipients[]" value="<?= $value ?>" class="hidden" 
                                            <?= in_array($value, $_POST['recipients'] ?? []) ? 'checked' : '' ?>
                                            onchange="this.closest('.recipient-btn').classList.toggle('recipient-checked')">
                                        <i class="fas <?= $group['icon'] ?> text-lg text-<?= $group['color'] ?>-600"></i>
                                        <span class="font-semibold text-gray-800"><?= $group['label'] ?></span>
                                    </div>
                                    <i class="fas fa-check text-lg text-<?= $group['color'] ?>-600 opacity-0 transition-opacity"></i>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Image Upload -->
                    <div class="lg:col-span-1">
                        <label class="block text-sm font-bold text-gray-900 mb-4">Image (Optional)</label>
                        <input type="file" name="picture" accept="image/*" class="file-input w-full text-sm text-gray-600">
                        <p class="text-xs text-gray-500 mt-2">JPG, PNG, GIF • Max 5MB</p>
                        <div id="imagePreview" class="mt-4"></div>
                    </div>

                    <!-- Document Upload -->
                    <div class="lg:col-span-1">
                        <label class="block text-sm font-bold text-gray-900 mb-4">Attachment (Optional)</label>
                        <input type="file" name="file" accept=".pdf,.doc,.docx" class="file-input w-full text-sm text-gray-600">
                        <p class="text-xs text-gray-500 mt-2">PDF or Word • Max 10MB</p>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end gap-4">
                    <button type="reset" class="px-8 py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition">
                        Clear
                    </button>
                    <button type="submit" name="submit_announcement" class="px-8 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg font-semibold hover:shadow-lg hover:from-blue-700 hover:to-indigo-700 transition flex items-center gap-2">
                        <i class="fas fa-paper-plane"></i>
                        Publish Announcement
                    </button>
                </div>
            </form>
        </div>

        <!-- Published Announcements -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-8 py-6 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                    <i class="fas fa-list text-blue-600"></i>
                    Published Announcements
                </h2>
                <div class="relative">
                    <input type="text" placeholder="Search..." value="<?= htmlspecialchars($search) ?>"
                           onchange="location.href='?search='+encodeURIComponent(this.value)"
                           class="pl-10 pr-4 py-2 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-100 text-sm">
                    <i class="fas fa-search absolute left-3 top-2.5 text-gray-400"></i>
                </div>
            </div>

            <div class="p-8">
                <div class="space-y-6">
                    <?php if ($announcements->num_rows > 0): ?>
                        <?php while ($a = $announcements->fetch_assoc()): ?>
                            <div class="fade-in border-l-4 border-blue-500 bg-white rounded-lg p-6 shadow-sm hover:shadow-md transition">
                                
                                <!-- Header with Recipients and Date -->
                                <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                                    <div class="flex flex-wrap gap-2">
                                        <?php 
                                        $recipientColors = [
                                            'students' => 'blue',
                                            'teachers' => 'indigo',
                                            'parents' => 'purple'
                                        ];
                                        foreach(explode(',', $a['recipients']) as $r): 
                                            $color = $recipientColors[trim($r)] ?? 'gray';
                                        ?>
                                            <span class="px-3 py-1 rounded-full text-white text-sm font-semibold bg-<?= $color ?>-600">
                                                <?= ucfirst(trim($r)) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <span class="text-sm text-gray-500">
                                        <i class="fas fa-clock"></i> <?= date("M j, Y \a\t g:i A", strtotime($a['created_at'])) ?>
                                    </span>
                                </div>

                                <!-- Image -->
                                <?php if ($a['picture_path']): ?>
                                    <img src="<?= htmlspecialchars($a['picture_path']) ?>" alt="Announcement Image"
                                         class="w-full max-h-80 object-cover rounded-lg mb-4 shadow-sm">
                                <?php endif; ?>

                                <!-- Message -->
                                <p class="text-gray-700 leading-relaxed mb-4">
                                    <?= nl2br(htmlspecialchars($a['message'])) ?>
                                </p>

                                <!-- Attachment Link -->
                                <?php if ($a['file_path']): ?>
                                    <a href="<?= htmlspecialchars($a['file_path']) ?>" target="_blank" class="inline-flex items-center gap-2 text-blue-600 font-semibold hover:text-blue-700 transition">
                                        <i class="fas fa-download"></i>
                                        Download Attachment
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-3 opacity-30"></i>
                            <p class="text-lg">No announcements yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    // Image preview
    document.querySelector('input[name="picture"]').addEventListener('change', function(e) {
        const preview = document.getElementById('imagePreview');
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(event) {
                preview.innerHTML = `<img src="${event.target.result}" class="w-full rounded-lg max-h-48 object-cover shadow-sm">`;
            };
            reader.readAsDataURL(this.files[0]);
        } else {
            preview.innerHTML = '';
        }
    });
</script>

</body>
</html>