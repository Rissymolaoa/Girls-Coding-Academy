<?php
// campaigns.php
session_start(); // Start session at the very top

// Handle form submission FIRST, before any output

// Database connection (adjust credentials as needed)
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=girlscodingacademydb', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Handle form submission for creating new campaign
if (isset($_POST['create_campaign']) && isset($_SESSION['user_id'])) {
    $title = $_POST['campaign_title'];
    $description = $_POST['campaign_description'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $target_audience = $_POST['target_audience'];
    $status = 'active'; // Default status
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("INSERT INTO marketing_campaigns (title, description, start_date, end_date, target_audience, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $description, $start_date, $end_date, $target_audience, $status, $user_id]);
    
    // Redirect to avoid resubmission
    header('Location: campaigns.php');
    exit();
} elseif (isset($_POST['create_campaign']) && !isset($_SESSION['user_id'])) {
    // Redirect if not logged in
    header('Location: login.php');
    exit();
}

// Now include navigation after processing
include 'navigation.php';

// Fetch campaigns
$stmt = $pdo->query("SELECT c.*, u.firstName FROM marketing_campaigns c LEFT JOIN users u ON c.created_by = u.user_id ORDER BY c.created_at DESC");
$campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate leads and conversion (mock/simplified; in production, join with leads table)
foreach ($campaigns as &$campaign) {
    $leadStmt = $pdo->prepare("SELECT COUNT(*) as leads FROM marketing_leads WHERE campaign_id = ?");
    $leadStmt->execute([$campaign['campaign_id']]);
    $leads = $leadStmt->fetch(PDO::FETCH_ASSOC)['leads'];
    
    $conversionStmt = $pdo->prepare("SELECT COUNT(*) as converted FROM marketing_leads WHERE campaign_id = ? AND status = 'converted'");
    $conversionStmt->execute([$campaign['campaign_id']]);
    $converted = $conversionStmt->fetch(PDO::FETCH_ASSOC)['converted'];
    
    $campaign['leads_generated'] = $leads;
    $campaign['conversion_rate'] = $leads > 0 ? round(($converted / $leads) * 100, 0) . '%' : '0%';
}
?>
            <div class="dashboard">
                <h2>Campaign Management</h2>
                <p>Manage your promotional campaigns for courses and events. Track performance and launch new initiatives.</p>
                
                <!-- Create New Campaign Button -->
                <div style="margin-bottom: 2rem;">
                    <a href="campaigns.php?new=1" style="background: var(--secondary-blue); color: var(--white); text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; transition: background 0.3s; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-plus"></i> Create New Campaign
                    </a>
                </div>

                <?php if (isset($_GET['new'])): ?>
                    <!-- New Campaign Form (Modal-like section) -->
                    <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light); margin-bottom: 2rem;">
                        <h3>Create New Campaign</h3>
                        <form action="campaigns.php" method="POST" style="display: grid; gap: 1rem; max-width: 600px;">
                            <div>
                                <label for="campaign_title">Campaign Title</label>
                                <input type="text" id="campaign_title" name="campaign_title" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                            </div>
                            <div>
                                <label for="campaign_description">Description</label>
                                <textarea id="campaign_description" name="campaign_description" rows="3" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;"></textarea>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div>
                                    <label for="start_date">Start Date</label>
                                    <input type="date" id="start_date" name="start_date" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                </div>
                                <div>
                                    <label for="end_date">End Date</label>
                                    <input type="date" id="end_date" name="end_date" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                </div>
                            </div>
                            <div>
                                <label for="target_audience">Target Audience</label>
                                <select id="target_audience" name="target_audience" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                    <option value="">Select...</option>
                                    <option value="students">Prospective Students</option>
                                    <option value="parents">Parents</option>
                                    <option value="alumni">Alumni</option>
                                    <option value="teachers">Teachers</option>
                                    <option value="general">General</option>
                                </select>
                            </div>
                            <button type="submit" name="create_campaign" style="background: var(--success-green); color: var(--white); border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600;">Launch Campaign</button>
                            <a href="campaigns.php" style="color: var(--text-light); text-decoration: none;">Cancel</a>
                        </form>
                    </section>
                <?php endif; ?>

                <!-- Campaigns List -->
                <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light);">
                    <h3>Active Campaigns</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--light-gray);">
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Title</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Description</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Dates</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Status</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Leads</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Conversion</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Created By</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($campaigns)): ?>
                                    <tr>
                                        <td colspan="8" style="padding: 2rem; text-align: center; color: var(--text-light);">No campaigns yet. Create one to get started!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($campaigns as $campaign): ?>
                                        <tr style="border-bottom: 1px solid var(--border-light);">
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($campaign['title']); ?></td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars(substr($campaign['description'], 0, 100)) . '...'; ?></td>
                                            <td style="padding: 1rem;"><?php echo date('M d', strtotime($campaign['start_date'])) . ' - ' . date('M d', strtotime($campaign['end_date'])); ?></td>
                                            <td style="padding: 1rem;">
                                                <span style="background: <?php echo $campaign['status'] === 'active' ? 'var(--success-green)' : ($campaign['status'] === 'completed' ? 'var(--text-light)' : 'var(--error-red)'); ?>; color: var(--white); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem;">
                                                    <?php echo ucfirst($campaign['status']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 1rem;"><?php echo $campaign['leads_generated']; ?></td>
                                            <td style="padding: 1rem;"><?php echo $campaign['conversion_rate']; ?></td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($campaign['firstName'] ?? 'Unknown'); ?></td>
                                            <td style="padding: 1rem;">
                                                <a href="#" style="color: var(--secondary-blue); text-decoration: none; margin-right: 0.5rem;"><i class="fas fa-edit"></i> Edit</a>
                                                <a href="#" style="color: var(--error-red); text-decoration: none;" onclick="return confirmDelete(<?php echo $campaign['campaign_id']; ?>);"><i class="fas fa-trash"></i> Delete</a>
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
            if (confirm('Are you sure you want to delete this campaign?')) {
                // AJAX delete or form submit
                fetch('campaigns.php?delete=' + id, { method: 'POST' })
                    .then(() => location.reload());
            }
            return false;
        }
    </script>
</body>
</html>