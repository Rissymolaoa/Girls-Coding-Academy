<?php
// content.php
session_start(); // Start session at the very top

// Handle form submission FIRST, before any output

// Database connection (adjust credentials as needed)
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=girlscodingacademydb', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Handle form submission for creating new content
if (isset($_POST['create_content']) && isset($_SESSION['user_id'])) {
    $title = $_POST['content_title'];
    $description = $_POST['content_description'];
    $linked_batch_id = !empty($_POST['linked_batch_id']) ? $_POST['linked_batch_id'] : null;
    $linked_event_id = !empty($_POST['linked_event_id']) ? $_POST['linked_event_id'] : null;
    $user_id = $_SESSION['user_id'];
    $file_path = null;

    // Handle file upload
    if (isset($_FILES['content_file']) && $_FILES['content_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/marketing_content/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_name = basename($_FILES['content_file']['name']);
        $target_path = $upload_dir . time() . '_' . $file_name;
        if (move_uploaded_file($_FILES['content_file']['tmp_name'], $target_path)) {
            $file_path = $target_path;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO marketing_content (title, description, file_path, linked_batch_id, linked_event_id, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $description, $file_path, $linked_batch_id, $linked_event_id, $user_id]);
    
    // Redirect to avoid resubmission
    header('Location: content.php');
    exit();
} elseif (isset($_POST['create_content']) && !isset($_SESSION['user_id'])) {
    // Redirect if not logged in
    header('Location: login.php');
    exit();
}

// Now include navigation after processing
include 'navigation.php';

// Fetch batches for dropdown
$batchStmt = $pdo->query("SELECT batch_id, batch_code FROM batches WHERE status = 'active'");
$batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch events for dropdown
$eventStmt = $pdo->query("SELECT event_id, title FROM events WHERE is_posted = 1");
$events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch content
$contentStmt = $pdo->query("SELECT mc.*, b.batch_code, e.title as event_title, u.firstName FROM marketing_content mc 
                            LEFT JOIN batches b ON mc.linked_batch_id = b.batch_id 
                            LEFT JOIN events e ON mc.linked_event_id = e.event_id 
                            LEFT JOIN users u ON mc.uploaded_by = u.user_id 
                            ORDER BY mc.uploaded_at DESC");
$contents = $contentStmt->fetchAll(PDO::FETCH_ASSOC);
?>
            <div class="dashboard">
                <h2>Content Distribution</h2>
                <p>Upload and manage marketing materials like flyers, videos, and resources linked to courses or events.</p>
                
                <!-- Create New Content Button -->
                <div style="margin-bottom: 2rem;">
                    <a href="content.php?new=1" style="background: var(--secondary-blue); color: var(--white); text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; transition: background 0.3s; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-plus"></i> Upload New Content
                    </a>
                </div>

                <?php if (isset($_GET['new'])): ?>
                    <!-- New Content Form (Modal-like section) -->
                    <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light); margin-bottom: 2rem;">
                        <h3>Upload New Content</h3>
                        <form action="content.php" method="POST" enctype="multipart/form-data" style="display: grid; gap: 1rem; max-width: 600px;">
                            <div>
                                <label for="content_title">Title</label>
                                <input type="text" id="content_title" name="content_title" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                            </div>
                            <div>
                                <label for="content_description">Description</label>
                                <textarea id="content_description" name="content_description" rows="3" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;"></textarea>
                            </div>
                            <div>
                                <label for="content_file">File (PDF, Image, Video)</label>
                                <input type="file" id="content_file" name="content_file" accept=".pdf,.jpg,.jpeg,.png,.mp4" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                            </div>
                            <div>
                                <label for="linked_batch_id">Link to Batch</label>
                                <select id="linked_batch_id" name="linked_batch_id" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                    <option value="">None</option>
                                    <?php foreach ($batches as $batch): ?>
                                        <option value="<?php echo $batch['batch_id']; ?>"><?php echo htmlspecialchars($batch['batch_code']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="linked_event_id">Link to Event</label>
                                <select id="linked_event_id" name="linked_event_id" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                    <option value="">None</option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo $event['event_id']; ?>"><?php echo htmlspecialchars($event['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="create_content" style="background: var(--success-green); color: var(--white); border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600;">Upload Content</button>
                            <a href="content.php" style="color: var(--text-light); text-decoration: none;">Cancel</a>
                        </form>
                    </section>
                <?php endif; ?>

                <!-- Content List -->
                <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light);">
                    <h3>Uploaded Content</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--light-gray);">
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Title</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Description</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">File</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Linked Batch</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Linked Event</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Uploaded By</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Date</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($contents)): ?>
                                    <tr>
                                        <td colspan="8" style="padding: 2rem; text-align: center; color: var(--text-light);">No content uploaded yet. Upload some to get started!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($contents as $content): ?>
                                        <tr style="border-bottom: 1px solid var(--border-light);">
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($content['title']); ?></td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars(substr($content['description'], 0, 100)) . '...'; ?></td>
                                            <td style="padding: 1rem;">
                                                <?php if ($content['file_path']): ?>
                                                    <a href="<?php echo htmlspecialchars($content['file_path']); ?>" target="_blank" style="color: var(--secondary-blue); text-decoration: none;"><i class="fas fa-download"></i> Download</a>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($content['batch_code'] ?? 'N/A'); ?></td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($content['event_title'] ?? 'N/A'); ?></td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($content['firstName'] ?? 'Unknown'); ?></td>
                                            <td style="padding: 1rem;"><?php echo date('M d, Y', strtotime($content['uploaded_at'])); ?></td>
                                            <td style="padding: 1rem;">
                                                <a href="#" style="color: var(--secondary-blue); text-decoration: none; margin-right: 0.5rem;"><i class="fas fa-edit"></i> Edit</a>
                                                <a href="#" style="color: var(--error-red); text-decoration: none;" onclick="return confirmDelete(<?php echo $content['content_id']; ?>);"><i class="fas fa-trash"></i> Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <script>
        function confirmDelete(id) {
            if (confirm('Are you sure you want to delete this content?')) {
                // AJAX delete or form submit
                fetch('content.php?delete=' + id, { method: 'POST' })
                    .then(() => location.reload());
            }
            return false;
        }
    </script>
</body>
</html>