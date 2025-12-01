<?php
// marketing_events.php
session_start(); // Start session at the very top

// Handle form submission FIRST, before any output (e.g., for promotional links)

// Database connection (adjust credentials as needed)
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=girlscodingacademydb', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Handle form submission for linking promotion to event (placeholder)
if (isset($_POST['link_promotion']) && isset($_SESSION['user_id'])) {
    $event_id = $_POST['event_id'];
    $campaign_id = $_POST['campaign_id'];
    $content_id = $_POST['content_id'] ?? null;

    // Insert into a linking table if needed; for now, update event or log
    // Example: Add promotion note to events table (extend if necessary)
    $stmt = $pdo->prepare("UPDATE events SET photo = CONCAT(COALESCE(photo, ''), ?) WHERE event_id = ?");
    $stmt->execute(['Linked to Campaign: ' . $campaign_id, $event_id]);
    
    header('Location: marketing_events.php');
    exit();
}

// Now include navigation after processing
include 'navigation.php';

// Fetch events (from existing events table)
$eventsStmt = $pdo->query("SELECT * FROM events WHERE is_posted = 1 ORDER BY event_date ASC");
$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active campaigns for linking
$campaignsStmt = $pdo->query("SELECT campaign_id, title FROM marketing_campaigns WHERE status = 'active'");
$campaigns = $campaignsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch content for linking
$contentStmt = $pdo->query("SELECT content_id, title FROM marketing_content ORDER BY uploaded_at DESC LIMIT 10");
$contents = $contentStmt->fetchAll(PDO::FETCH_ASSOC);
?>
            <div class="dashboard">
                <h2>Event Promotions</h2>
                <p>Manage marketing promotions for upcoming events. Link campaigns, content, and social posts to boost attendance.</p>
                
                <!-- Promote Event Button (links to form) -->
                <div style="margin-bottom: 2rem;">
                    <a href="#promote-section" style="background: var(--secondary-blue); color: var(--white); text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; transition: background 0.3s; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-bullhorn"></i> Promote Event
                    </a>
                </div>

                <!-- Promotion Form Section -->
                <section id="promote-section" style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light); margin-bottom: 2rem;">
                    <h3>Link Promotion to Event</h3>
                    <form action="marketing_events.php" method="POST" style="display: grid; gap: 1rem; max-width: 600px;">
                        <div>
                            <label for="event_id">Select Event</label>
                            <select id="event_id" name="event_id" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                <option value="">Select Event...</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event['event_id']; ?>"><?php echo htmlspecialchars($event['title'] . ' - ' . $event['event_date']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="campaign_id">Link to Campaign</label>
                            <select id="campaign_id" name="campaign_id" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                <option value="">None</option>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <option value="<?php echo $campaign['campaign_id']; ?>"><?php echo htmlspecialchars($campaign['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="content_id">Link to Content</label>
                            <select id="content_id" name="content_id" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                <option value="">None</option>
                                <?php foreach ($contents as $content): ?>
                                    <option value="<?php echo $content['content_id']; ?>"><?php echo htmlspecialchars($content['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="link_promotion" style="background: var(--success-green); color: var(--white); border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600;">Link Promotion</button>
                    </form>
                </section>

                <!-- Events List with Promotion Status -->
                <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light);">
                    <h3>Upcoming Events & Promotions</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--light-gray);">
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Event Title</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Date & Time</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Category</th>
                                    <th style="padding: 1rem; text-align: center; border-bottom: 2px solid var(--border-light);">Promotions Linked</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Status</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($events)): ?>
                                    <tr>
                                        <td colspan="6" style="padding: 2rem; text-align: center; color: var(--text-light);">No upcoming events. Check <a href="events.php" style="color: var(--secondary-blue);">Events</a> page.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($events as $event): ?>
                                        <tr style="border-bottom: 1px solid var(--border-light);">
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($event['title']); ?></td>
                                            <td style="padding: 1rem;"><?php echo date('M d, Y', strtotime($event['event_date'])) . ' ' . ($event['event_time_start'] ?? 'All Day'); ?></td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($event['category'] ?? 'General'); ?></td>
                                            <td style="padding: 1rem; text-align: center;">
                                                <span style="color: var(--success-green);"><i class="fas fa-check"></i> <?php echo rand(0, 3); ?> Linked</span> <!-- Mock count -->
                                            </td>
                                            <td style="padding: 1rem;">
                                                <span style="background: <?php echo $event['is_posted'] ? 'var(--success-green)' : 'var(--error-red)'; ?>; color: var(--white); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem;">
                                                    <?php echo $event['is_posted'] ? 'Posted' : 'Draft'; ?>
                                                </span>
                                            </td>
                                            <td style="padding: 1rem;">
                                                <a href="events.php?edit=<?php echo $event['event_id']; ?>" style="color: var(--secondary-blue); text-decoration: none; margin-right: 0.5rem;"><i class="fas fa-edit"></i> Edit</a>
                                                <a href="#" style="color: var(--error-red); text-decoration: none;" onclick="return confirmDelete(<?php echo $event['event_id']; ?>);"><i class="fas fa-trash"></i> Delete</a>
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
            if (confirm('Are you sure you want to delete this event promotion?')) {
                // AJAX delete or form submit
                fetch('marketing_events.php?delete=' + id, { method: 'POST' })
                    .then(() => location.reload());
            }
            return false;
        }
    </script>
</body>
</html>