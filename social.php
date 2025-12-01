<?php
// social.php
session_start(); // Start session at the very top

// Handle form submission FIRST, before any output

// Database connection (adjust credentials as needed)
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=girlscodingacademydb', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Handle form submission for creating new social post
if (isset($_POST['create_post']) && isset($_SESSION['user_id'])) {
    $platform = $_POST['platform'];
    $content = $_POST['post_content'];
    $campaign_id = !empty($_POST['campaign_id']) ? $_POST['campaign_id'] : null;
    $post_url = $_POST['post_url'] ?? null; // If posting to external API, set URL
    $engagement_metrics = json_encode(['likes' => 0, 'shares' => 0, 'comments' => 0]); // Default
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("INSERT INTO marketing_social_posts (platform, content, post_url, engagement_metrics, campaign_id, posted_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$platform, $content, $post_url, $engagement_metrics, $campaign_id, $user_id]);
    
    // Redirect to avoid resubmission
    header('Location: social.php');
    exit();
} elseif (isset($_POST['create_post']) && !isset($_SESSION['user_id'])) {
    // Redirect if not logged in
    header('Location: login.php');
    exit();
}

// Now include navigation after processing
include 'navigation.php';

// Fetch campaigns for dropdown
$campaignStmt = $pdo->query("SELECT campaign_id, title FROM marketing_campaigns WHERE status = 'active'");
$campaigns = $campaignStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch social posts
$postStmt = $pdo->query("SELECT sp.*, c.title as campaign_title, u.firstName FROM marketing_social_posts sp 
                         LEFT JOIN marketing_campaigns c ON sp.campaign_id = c.campaign_id 
                         LEFT JOIN users u ON sp.posted_by = u.user_id 
                         ORDER BY sp.posted_at DESC");
$posts = $postStmt->fetchAll(PDO::FETCH_ASSOC);
?>
            <div class="dashboard">
                <h2>Social Media Management</h2>
                <p>Create and track social media posts linked to campaigns. Monitor engagement metrics.</p>
                
                <!-- Create New Post Button -->
                <div style="margin-bottom: 2rem;">
                    <a href="social.php?new=1" style="background: var(--secondary-blue); color: var(--white); text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; transition: background 0.3s; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-plus"></i> Create New Post
                    </a>
                </div>

                <?php if (isset($_GET['new'])): ?>
                    <!-- New Post Form (Modal-like section) -->
                    <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light); margin-bottom: 2rem;">
                        <h3>Create Social Post</h3>
                        <form action="social.php" method="POST" style="display: grid; gap: 1rem; max-width: 600px;">
                            <div>
                                <label for="platform">Platform</label>
                                <select id="platform" name="platform" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                    <option value="">Select...</option>
                                    <option value="twitter">Twitter (X)</option>
                                    <option value="facebook">Facebook</option>
                                    <option value="instagram">Instagram</option>
                                    <option value="linkedin">LinkedIn</option>
                                </select>
                            </div>
                            <div>
                                <label for="post_content">Post Content</label>
                                <textarea id="post_content" name="post_content" rows="4" required placeholder="Write your post here..." style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;"></textarea>
                            </div>
                            <div>
                                <label for="post_url">Post URL (if already posted externally)</label>
                                <input type="url" id="post_url" name="post_url" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                            </div>
                            <div>
                                <label for="campaign_id">Linked Campaign</label>
                                <select id="campaign_id" name="campaign_id" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                    <option value="">None</option>
                                    <?php foreach ($campaigns as $campaign): ?>
                                        <option value="<?php echo $campaign['campaign_id']; ?>"><?php echo htmlspecialchars($campaign['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="create_post" style="background: var(--success-green); color: var(--white); border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600;">Post Now</button>
                            <a href="social.php" style="color: var(--text-light); text-decoration: none;">Cancel</a>
                        </form>
                    </section>
                <?php endif; ?>

                <!-- Social Posts List -->
                <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light);">
                    <h3>Recent Social Posts</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--light-gray);">
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Platform</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Content</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">URL</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Campaign</th>
                                    <th style="padding: 1rem; text-align: center; border-bottom: 2px solid var(--border-light);">Engagement</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Posted By</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Date</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($posts)): ?>
                                    <tr>
                                        <td colspan="8" style="padding: 2rem; text-align: center; color: var(--text-light);">No posts yet. Create one to get started!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($posts as $post): ?>
                                        <?php 
                                        $metrics = json_decode($post['engagement_metrics'], true);
                                        $engagement = ($metrics['likes'] ?? 0) + ($metrics['shares'] ?? 0) + ($metrics['comments'] ?? 0);
                                        ?>
                                        <tr style="border-bottom: 1px solid var(--border-light);">
                                            <td style="padding: 1rem;">
                                                <span style="background: var(--secondary-blue); color: var(--white); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; text-transform: uppercase;">
                                                    <?php echo htmlspecialchars($post['platform']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 1rem; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                <?php echo htmlspecialchars(substr($post['content'], 0, 50)) . '...'; ?>
                                            </td>
                                            <td style="padding: 1rem;">
                                                <?php if ($post['post_url']): ?>
                                                    <a href="<?php echo htmlspecialchars($post['post_url']); ?>" target="_blank" style="color: var(--secondary-blue); text-decoration: none;"><i class="fas fa-external-link-alt"></i> View</a>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($post['campaign_title'] ?? 'N/A'); ?></td>
                                            <td style="padding: 1rem; text-align: center;"><?php echo $engagement; ?></td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($post['firstName'] ?? 'Unknown'); ?></td>
                                            <td style="padding: 1rem;"><?php echo date('M d, Y', strtotime($post['posted_at'])); ?></td>
                                            <td style="padding: 1rem;">
                                                <a href="#" style="color: var(--secondary-blue); text-decoration: none; margin-right: 0.5rem;"><i class="fas fa-edit"></i> Edit Metrics</a>
                                                <a href="#" style="color: var(--error-red); text-decoration: none;" onclick="return confirmDelete(<?php echo $post['post_id']; ?>);"><i class="fas fa-trash"></i> Delete</a>
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
            if (confirm('Are you sure you want to delete this post?')) {
                // AJAX delete or form submit
                fetch('social.php?delete=' + id, { method: 'POST' })
                    .then(() => location.reload());
            }
            return false;
        }
    </script>
</body>
</html>