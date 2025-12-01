<?php
// events.php - Accounts View
// This page shows school events with financial tracking (e.g., budgeted costs, actual expenses linked to events)
session_start(); // Start session at the very top

// Handle form submission FIRST, before any output (e.g., for linking budget to event)

// Database connection (adjust credentials as needed)
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=girlscodingacademydb', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Handle linking budget or expense to event (placeholder)
if (isset($_POST['link_budget']) && isset($_SESSION['user_id'])) {
    $event_id = $_POST['event_id'];
    $budget_id = $_POST['budget_id'] ?? null;
    // In production, update a linking table or add field to events
    $updateStmt = $pdo->prepare("UPDATE events SET photo = CONCAT(COALESCE(photo, ''), ?, ' (Budget ID: ?)') WHERE event_id = ?");
    $updateStmt->execute(['Linked Budget', $budget_id, $event_id]);
    header('Location: events.php');
    exit();
}

// Now include navigation after processing
include 'accounts_navigation.php';

// Fetch events with financial data (join with budgets/expenses where possible; mock if no direct link)
$eventsStmt = $pdo->prepare("
    SELECT e.*, 
           (SELECT SUM(amount) FROM accounts_budgets b WHERE b.period_start <= e.event_date AND b.period_end >= e.event_date AND b.category_id IN (SELECT category_id FROM accounts_categories WHERE name LIKE '%event%')) as budgeted_cost,
           (SELECT SUM(amount) FROM accounts_expenses ae WHERE ae.date = e.event_date) as actual_cost
    FROM events e 
    WHERE e.is_posted = 1 
    ORDER BY e.event_date ASC
");
$eventsStmt->execute();
$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch budgets for dropdown (optional for linking)
$budgetStmt = $pdo->query("SELECT budget_id, amount, period_start, period_end FROM accounts_budgets ORDER BY period_start DESC LIMIT 10");
$budgets = $budgetStmt->fetchAll(PDO::FETCH_ASSOC);
?>
            <div class="dashboard">
                <h2>Event Financial Tracking</h2>
                <p>View upcoming and past school events with associated budgets and actual costs. Link budgets or expenses to events for better forecasting.</p>
                
                <!-- Link Budget to Event Button -->
                <div style="margin-bottom: 2rem;">
                    <a href="#link-section" style="background: var(--primary-blue); color: var(--white); text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; transition: background: 0.3s; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-link"></i> Link Budget to Event
                    </a>
                </div>

                <!-- Link Form Section -->
                <section id="link-section" style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light); margin-bottom: 2rem;">
                    <h3>Link Budget to Event</h3>
                    <form action="events.php" method="POST" style="display: grid; gap: 1rem; max-width: 600px;">
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
                            <label for="budget_id">Select Budget (Optional)</label>
                            <select id="budget_id" name="budget_id" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                <option value="">No Budget</option>
                                <?php foreach ($budgets as $budget): ?>
                                    <option value="<?php echo $budget['budget_id']; ?>">
                                        <?php echo 'R ' . number_format($budget['amount'], 2) . ' (' . date('M Y', strtotime($budget['period_start'])) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="link_budget" style="background: var(--success-green); color: var(--white); border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600;">Link</button>
                    </form>
                </section>

                <!-- Events List with Financial Data -->
                <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light);">
                    <h3>Events with Financial Summary</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--light-gray);">
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Event</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Date & Time</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Category</th>
                                    <th style="padding: 1rem; text-align: right; border-bottom: 2px solid var(--border-light);">Budgeted Cost (ZAR)</th>
                                    <th style="padding: 1rem; text-align: right; border-bottom: 2px solid var(--border-light);">Actual Cost (ZAR)</th>
                                    <th style="padding: 1rem; text-align: right; border-bottom: 2px solid var(--border-light);">Variance (ZAR)</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Status</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($events)): ?>
                                    <tr>
                                        <td colspan="8" style="padding: 2rem; text-align: center; color: var(--text-light);">No events found. Check <a href="events.php" style="color: var(--secondary-blue);">Events</a> page.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($events as $event): ?>
                                        <?php
                                        $variance = ($event['budgeted_cost'] ?? 0) - ($event['actual_cost'] ?? 0);
                                        $variance_color = $variance >= 0 ? 'success-green' : 'error-red';
                                        ?>
                                        <tr style="border-bottom: 1px solid var(--border-light);">
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($event['title']); ?></td>
                                            <td style="padding: 1rem;"><?php echo date('M d, Y', strtotime($event['event_date'])) . ' ' . ($event['event_time_start'] ?? 'All Day'); ?></td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($event['category'] ?? 'General'); ?></td>
                                            <td style="padding: 1rem; text-align: right;"><?php echo ($event['budgeted_cost'] ?? 0) > 0 ? 'R ' . number_format($event['budgeted_cost'], 2) : 'N/A'; ?></td>
                                            <td style="padding: 1rem; text-align: right;"><?php echo ($event['actual_cost'] ?? 0) > 0 ? 'R ' . number_format($event['actual_cost'], 2) : 'N/A'; ?></td>
                                            <td style="padding: 1rem; text-align: right; font-weight: bold; color: var(--<?php echo $variance_color; ?>);">
                                                <?php echo $variance >= 0 ? '+' : ''; ?> R <?php echo number_format($variance, 2); ?>
                                            </td>
                                            <td style="padding: 1rem;">
                                                <span style="background: <?php echo $event['is_posted'] ? 'var(--success-green)' : 'var(--text-light)'; ?>; color: var(--white); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem;">
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
            if (confirm('Are you sure you want to delete this event?')) {
                // AJAX delete or form submission
                fetch('events.php?delete=' + id, { method: 'POST' })
                    .then(() => location.reload());
            }
            return false;
        }
    </script>
</body>
</html>