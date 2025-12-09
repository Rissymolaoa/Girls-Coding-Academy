<?php
// leads.php
session_start(); // Start session at the very top

// Handle form submission FIRST, before any output

// Database connection (adjust credentials as needed)
try {
    $pdo = new PDO('mysql:host=127.0.0.1:3307;dbname=girlscodingacademydb', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Handle form submission for creating new lead
if (isset($_POST['create_lead']) && isset($_SESSION['user_id'])) {
    $name = $_POST['lead_name'];
    $email = $_POST['lead_email'];
    $phone = $_POST['lead_phone'];
    $source = $_POST['lead_source'];
    $campaign_id = !empty($_POST['campaign_id']) ? $_POST['campaign_id'] : null;
    $status = 'new'; // Default status

    $stmt = $pdo->prepare("INSERT INTO marketing_leads (campaign_id, name, email, phone, source, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$campaign_id, $name, $email, $phone, $source, $status]);
    
    // Redirect to avoid resubmission
    header('Location: leads.php');
    exit();
} elseif (isset($_POST['create_lead']) && !isset($_SESSION['user_id'])) {
    // Redirect if not logged in
    header('Location: login.php');
    exit();
}

// Handle status update (simplified)
if (isset($_POST['update_status']) && isset($_SESSION['user_id'])) {
    $lead_id = $_POST['lead_id'];
    $new_status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE marketing_leads SET status = ? WHERE lead_id = ?");
    $stmt->execute([$new_status, $lead_id]);
    header('Location: leads.php');
    exit();
} elseif (isset($_POST['update_status']) && !isset($_SESSION['user_id'])) {
    // Redirect if not logged in
    header('Location: login.php');
    exit();
}

// Now include navigation after processing
include 'navigation.php';

// Fetch leads
$stmt = $pdo->query("SELECT l.*, c.title as campaign_title FROM marketing_leads l LEFT JOIN marketing_campaigns c ON l.campaign_id = c.campaign_id ORDER BY l.created_at DESC");
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch campaigns for dropdown
$campaignStmt = $pdo->query("SELECT campaign_id, title FROM marketing_campaigns WHERE status = 'active'");
$campaigns = $campaignStmt->fetchAll(PDO::FETCH_ASSOC);
?>
            <div class="dashboard">
                <h2>Lead Management</h2>
                <p>Track and nurture leads from your campaigns. Convert prospects into enrollments.</p>
                
                <!-- Create New Lead Button -->
                <div style="margin-bottom: 2rem;">
                    <a href="leads.php?new=1" style="background: var(--secondary-blue); color: var(--white); text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; transition: background 0.3s; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-plus"></i> Add New Lead
                    </a>
                </div>

                <?php if (isset($_GET['new'])): ?>
                    <!-- New Lead Form (Modal-like section) -->
                    <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light); margin-bottom: 2rem;">
                        <h3>Add New Lead</h3>
                        <form action="leads.php" method="POST" style="display: grid; gap: 1rem; max-width: 600px;">
                            <div>
                                <label for="lead_name">Full Name</label>
                                <input type="text" id="lead_name" name="lead_name" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                            </div>
                            <div>
                                <label for="lead_email">Email</label>
                                <input type="email" id="lead_email" name="lead_email" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                            </div>
                            <div>
                                <label for="lead_phone">Phone</label>
                                <input type="tel" id="lead_phone" name="lead_phone" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                            </div>
                            <div>
                                <label for="lead_source">Source</label>
                                <input type="text" id="lead_source" name="lead_source" placeholder="e.g., Website, Email Campaign" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
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
                            <button type="submit" name="create_lead" style="background: var(--success-green); color: var(--white); border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600;">Add Lead</button>
                            <a href="leads.php" style="color: var(--text-light); text-decoration: none;">Cancel</a>
                        </form>
                    </section>
                <?php endif; ?>

                <!-- Leads List -->
                <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light);">
                    <h3>Leads Overview</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--light-gray);">
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Name</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Email</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Phone</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Source</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Campaign</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Status</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Created</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($leads)): ?>
                                    <tr>
                                        <td colspan="8" style="padding: 2rem; text-align: center; color: var(--text-light);">No leads yet. Add one to get started!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($leads as $lead): ?>
                                        <tr style="border-bottom: 1px solid var(--border-light);">
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($lead['name']); ?></td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($lead['email']); ?></td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($lead['phone'] ?? 'N/A'); ?></td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($lead['source'] ?? 'Direct'); ?></td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($lead['campaign_title'] ?? 'N/A'); ?></td>
                                            <td style="padding: 1rem;">
                                                <span style="background: <?php 
                                                    switch($lead['status']) {
                                                        case 'new': echo 'var(--primary-blue)'; break;
                                                        case 'contacted': echo 'var(--secondary-blue)'; break;
                                                        case 'qualified': echo 'var(--success-green)'; break;
                                                        case 'converted': echo 'var(--accent-blue)'; break;
                                                        case 'lost': echo 'var(--error-red)'; break;
                                                        default: echo 'var(--text-light)';
                                                    }
                                                ?>; color: var(--white); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem;">
                                                    <?php echo ucfirst($lead['status']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 1rem;"><?php echo date('M d, Y', strtotime($lead['created_at'])); ?></td>
                                            <td style="padding: 1rem;">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="lead_id" value="<?php echo $lead['lead_id']; ?>">
                                                    <select name="status" onchange="this.form.submit()" style="padding: 0.25rem; border: 1px solid var(--border-light); border-radius: 4px; font-size: 0.8rem;">
                                                        <option value="new" <?php echo $lead['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                                                        <option value="contacted" <?php echo $lead['status'] === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                                                        <option value="qualified" <?php echo $lead['status'] === 'qualified' ? 'selected' : ''; ?>>Qualified</option>
                                                        <option value="converted" <?php echo $lead['status'] === 'converted' ? 'selected' : ''; ?>>Converted</option>
                                                        <option value="lost" <?php echo $lead['status'] === 'lost' ? 'selected' : ''; ?>>Lost</option>
                                                    </select>
                                                    <input type="hidden" name="update_status" value="1">
                                                </form>
                                                <a href="#" style="color: var(--error-red); text-decoration: none; margin-left: 0.5rem;" onclick="return confirmDelete(<?php echo $lead['lead_id']; ?>);"><i class="fas fa-trash"></i></a>
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
            if (confirm('Are you sure you want to delete this lead?')) {
                // AJAX delete or form submit
                fetch('leads.php?delete=' + id, { method: 'POST' })
                    .then(() => location.reload());
            }
            return false;
        }
    </script>
</body>
</html>