<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// DB connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "girlscodingacademydb";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$errors = [];
$success = "";

// Handle file upload & form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_title = trim($_POST['event_title'] ?? '');
    $event_description = trim($_POST['event_description'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    $event_time_start = $_POST['event_time_start'] ?? '';
    $event_time_end = $_POST['event_time_end'] ?? '';
    $event_category = $_POST['event_category'] ?? '';
    $event_location = trim($_POST['event_location'] ?? '');
    $post_immediately = isset($_POST['post_immediately']) ? 1 : 0;

    // Validate required fields
    if ($event_title === '') {
        $errors[] = "Event title is required.";
    }
    if ($event_date === '') {
        $errors[] = "Event date is required.";
    }

    // Prepare photo upload
    $photo_path = null;
    if (!empty($_FILES['event_photo']['name'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES['event_photo']['type'], $allowed_types)) {
            $errors[] = "Only JPG, PNG, GIF images are allowed for the event photo.";
        } elseif ($_FILES['event_photo']['size'] > 2 * 1024 * 1024) {
            $errors[] = "Event photo must be less than 2MB.";
        } else {
            $ext = pathinfo($_FILES['event_photo']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid('event_', true) . "." . $ext;
            $upload_dir = __DIR__ . "/uploads/events/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $target_file = $upload_dir . $new_filename;
            if (move_uploaded_file($_FILES['event_photo']['tmp_name'], $target_file)) {
                $photo_path = "uploads/events/" . $new_filename;
            } else {
                $errors[] = "Failed to upload event photo.";
            }
        }
    }

    // Duplicate event check
    if (empty($errors)) {
        $dup_check_stmt = $conn->prepare("SELECT event_id FROM events WHERE title=? AND event_date=? AND event_time_start=?");
        $dup_check_stmt->bind_param("sss", $event_title, $event_date, $event_time_start);
        $dup_check_stmt->execute();
        $dup_check_stmt->store_result();

        if ($dup_check_stmt->num_rows > 0) {
            $errors[] = "An event with the same title, date, and start time already exists.";
        }
        $dup_check_stmt->close();
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, event_time_start, event_time_end, category, location, photo, is_posted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssi", $event_title, $event_description, $event_date, $event_time_start, $event_time_end, $event_category, $event_location, $photo_path, $post_immediately);
        if ($stmt->execute()) {
            $success = "Event created successfully!";
            $_POST = [];
        } else {
            $errors[] = "Failed to create event. Please try again.";
        }
    }
}

// Fetch summary counts
$total_posted_events = $conn->query("SELECT COUNT(*) AS total FROM events WHERE is_posted=1")->fetch_assoc()['total'];
$total_available_events = $conn->query("SELECT COUNT(*) AS total FROM events")->fetch_assoc()['total'];

// Event categories for dropdown
$categories = ['Competition', 'Festival', 'Graduation', 'Other'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events • Girls Coding Academy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #f0f7ff 0%, #e8f2ff 100%); }
        
        .form-input {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }
        .form-input:focus {
            border-color: #e879f9;
            box-shadow: 0 0 0 3px rgba(232, 121, 249, 0.1);
            outline: none;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #ec4899 0%, #f472b6 100%);
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(236, 72, 153, 0.3);
        }
        
        .preview-image {
            width: 200px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e879f9;
            display: none;
        }
        
        .alert-success {
            background: #ecfdf5;
            border: 1px solid #86efac;
            color: #166534;
            border-radius: 8px;
            padding: 1rem;
        }
        
        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 8px;
            padding: 1rem;
        }
        
        .summary-card {
            background: white;
            border-left: 4px solid #e879f9;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }
        .summary-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(236, 72, 153, 0.15);
        }
        .summary-card p {
            margin: 0;
        }
        .summary-card .label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .summary-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-top: 0.5rem;
        }
        .summary-card i {
            font-size: 2rem;
            color: #e879f9;
            margin-bottom: 0.5rem;
            display: block;
        }
    </style>
</head>
<body>

<?php include 'top_navigation.php'; ?>
<?php include 'admin_navigation.php'; ?>

<div class="ml-64 mt-16 min-h-screen p-8">
    <div class="max-w-4xl mx-auto">
        
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900">Manage Events</h1>
            <p class="text-gray-600 mt-2">Create and manage upcoming events</p>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
            <a href="posted_events.php" class="summary-card">
                <i class="fas fa-check-circle"></i>
                <p class="label">Posted Events</p>
                <p class="number"><?= intval($total_posted_events) ?></p>
            </a>
            <a href="all_events.php" class="summary-card">
                <i class="fas fa-calendar-alt"></i>
                <p class="label">Total Events</p>
                <p class="number"><?= intval($total_available_events) ?></p>
            </a>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert-success mb-6 flex items-center gap-3">
                <i class="fas fa-check-circle text-lg"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert-danger mb-6">
                <div class="flex items-start gap-3 mb-3">
                    <i class="fas fa-exclamation-circle text-lg flex-shrink-0 mt-1"></i>
                    <div>
                        <p class="font-semibold mb-2">Please fix the following errors:</p>
                        <ul class="list-disc list-inside space-y-1">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Main Form -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-8 py-6 border-b border-gray-200 bg-gradient-to-r from-rose-50 to-pink-50">
                <h2 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                    <i class="fas fa-calendar-plus text-rose-500"></i>
                    Create New Event
                </h2>
            </div>

            <form method="POST" action="" enctype="multipart/form-data" class="p-8">
                
                <!-- Image Preview -->
                <div class="mb-8">
                    <img src="#" id="photoPreview" class="preview-image" alt="Event photo preview" />
                </div>

                <!-- Photo Upload -->
                <div class="mb-8">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Event Photo</label>
                    <div class="relative">
                        <input 
                            type="file" 
                            id="event_photo" 
                            name="event_photo" 
                            accept="image/*" 
                            class="form-input w-full file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-rose-100 file:text-rose-700 hover:file:bg-rose-200"
                        />
                        <p class="text-xs text-gray-500 mt-2">JPG, PNG, or GIF • Max 2MB</p>
                    </div>
                </div>

                <!-- Event Title -->
                <div class="mb-8">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Event Title <span class="text-red-500">*</span></label>
                    <input 
                        type="text" 
                        id="event_title" 
                        name="event_title" 
                        class="form-input w-full" 
                        placeholder="Enter event title" 
                        required 
                        value="<?= htmlspecialchars($_POST['event_title'] ?? '') ?>"
                    />
                </div>

                <!-- Description -->
                <div class="mb-8">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Description</label>
                    <textarea 
                        id="event_description" 
                        name="event_description" 
                        class="form-input w-full" 
                        rows="5" 
                        placeholder="Enter event description..."
                    ><?= htmlspecialchars($_POST['event_description'] ?? '') ?></textarea>
                </div>

                <!-- Date & Time Row -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Event Date <span class="text-red-500">*</span></label>
                        <input 
                            type="date" 
                            id="event_date" 
                            name="event_date" 
                            class="form-input w-full" 
                            required 
                            value="<?= htmlspecialchars($_POST['event_date'] ?? '') ?>"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Start Time</label>
                        <input 
                            type="time" 
                            id="event_time_start" 
                            name="event_time_start" 
                            class="form-input w-full" 
                            value="<?= htmlspecialchars($_POST['event_time_start'] ?? '') ?>"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">End Time</label>
                        <input 
                            type="time" 
                            id="event_time_end" 
                            name="event_time_end" 
                            class="form-input w-full" 
                            value="<?= htmlspecialchars($_POST['event_time_end'] ?? '') ?>"
                        />
                    </div>
                </div>

                <!-- Category & Location Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Category</label>
                        <select id="event_category" name="event_category" class="form-input w-full">
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>" <?= (($_POST['event_category'] ?? '') === $category) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Location</label>
                        <input 
                            type="text" 
                            id="event_location" 
                            name="event_location" 
                            class="form-input w-full" 
                            placeholder="Event location" 
                            value="<?= htmlspecialchars($_POST['event_location'] ?? '') ?>"
                        />
                    </div>
                </div>

                <!-- Checkbox -->
                <div class="mb-8">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input 
                            type="checkbox" 
                            id="post_immediately" 
                            name="post_immediately" 
                            class="w-5 h-5 text-rose-500 rounded border-gray-300 focus:ring-rose-500 cursor-pointer"
                            <?= isset($_POST['post_immediately']) ? 'checked' : '' ?>
                        />
                        <span class="text-gray-700 font-medium">Post Immediately</span>
                        <span class="text-gray-500 text-sm">(Make event visible to users)</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <div class="flex gap-4">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-plus mr-2"></i>
                        Create Event
                    </button>
                    <a href="all_events.php" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition flex items-center gap-2">
                        <i class="fas fa-eye"></i>
                        View All Events
                    </a>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
    // Image preview
    document.getElementById('event_photo').addEventListener('change', function(e) {
        const preview = document.getElementById('photoPreview');
        if (this.files && this.files[0]) {
            preview.src = URL.createObjectURL(this.files[0]);
            preview.style.display = 'block';
        } else {
            preview.src = '#';
            preview.style.display = 'none';
        }
    });
</script>

</body>
</html>