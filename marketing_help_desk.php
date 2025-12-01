<?php
// help_desk.php
session_start(); // Start session at the very top

// Handle form submission FIRST, before any output

// Database connection (adjust credentials as needed)
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=girlscodingacademydb', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Handle form submission for creating new ticket
if (isset($_POST['create_ticket']) && isset($_SESSION['user_id'])) {
    $subject = $_POST['ticket_subject'];
    $description = $_POST['ticket_description'];
    $priority = $_POST['ticket_priority'];
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("INSERT INTO help_tickets (user_id, subject, description, priority, status) VALUES (?, ?, ?, ?, 'open')");
    $stmt->execute([$user_id, $subject, $description, $priority]);
    
    // Redirect to avoid resubmission
    header('Location: help_desk.php');
    exit();
} elseif (isset($_POST['create_ticket']) && !isset($_SESSION['user_id'])) {
    // Redirect if not logged in
    header('Location: login.php');
    exit();
}

// Now include navigation after processing
include 'navigation.php';

// Fetch user's tickets
$userTicketsStmt = $pdo->prepare("SELECT * FROM help_tickets WHERE user_id = ? ORDER BY created_at DESC");
$userTicketsStmt->execute([$_SESSION['user_id']]);
$tickets = $userTicketsStmt->fetchAll(PDO::FETCH_ASSOC);

// Note: Assumes a 'help_tickets' table exists; create if needed:
// CREATE TABLE `help_tickets` (
//   `ticket_id` int(11) NOT NULL AUTO_INCREMENT,
//   `user_id` int(11) NOT NULL,
//   `subject` varchar(255) NOT NULL,
//   `description` text NOT NULL,
//   `priority` enum('low','medium','high') DEFAULT 'medium',
//   `status` enum('open','in_progress','closed') DEFAULT 'open',
//   `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
//   PRIMARY KEY (`ticket_id`),
//   KEY `user_id` (`user_id`),
//   CONSTRAINT `help_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
?>
            <div class="dashboard">
                <h2>Help Desk</h2>
                <p>Submit support requests or view your open tickets. Our team will respond promptly.</p>
                
                <!-- Create New Ticket Button -->
                <div style="margin-bottom: 2rem;">
                    <a href="help_desk.php?new=1" style="background: var(--secondary-blue); color: var(--white); text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; transition: background 0.3s; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-plus"></i> New Ticket
                    </a>
                </div>

                <?php if (isset($_GET['new'])): ?>
                    <!-- New Ticket Form (Modal-like section) -->
                    <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light); margin-bottom: 2rem;">
                        <h3>Submit New Ticket</h3>
                        <form action="help_desk.php" method="POST" style="display: grid; gap: 1rem; max-width: 600px;">
                            <div>
                                <label for="ticket_subject">Subject</label>
                                <input type="text" id="ticket_subject" name="ticket_subject" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                            </div>
                            <div>
                                <label for="ticket_description">Description</label>
                                <textarea id="ticket_description" name="ticket_description" rows="5" required placeholder="Describe your issue..." style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;"></textarea>
                            </div>
                            <div>
                                <label for="ticket_priority">Priority</label>
                                <select id="ticket_priority" name="ticket_priority" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px;">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            <button type="submit" name="create_ticket" style="background: var(--success-green); color: var(--white); border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600;">Submit Ticket</button>
                            <a href="help_desk.php" style="color: var(--text-light); text-decoration: none;">Cancel</a>
                        </form>
                    </section>
                <?php endif; ?>

                <!-- Tickets List -->
                <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light);">
                    <h3>Your Tickets</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--light-gray);">
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Ticket ID</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Subject</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Priority</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Status</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Created</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tickets)): ?>
                                    <tr>
                                        <td colspan="6" style="padding: 2rem; text-align: center; color: var(--text-light);">No tickets submitted yet. Create one above!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tickets as $ticket): ?>
                                        <tr style="border-bottom: 1px solid var(--border-light);">
                                            <td style="padding: 1rem;">#<?php echo $ticket['ticket_id']; ?></td>
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                            <td style="padding: 1rem;">
                                                <span style="background: <?php 
                                                    switch($ticket['priority']) {
                                                        case 'low': echo 'var(--success-green)'; break;
                                                        case 'medium': echo 'var(--secondary-blue)'; break;
                                                        case 'high': echo 'var(--error-red)'; break;
                                                        default: echo 'var(--text-light)';
                                                    }
                                                ?>; color: var(--white); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem;">
                                                    <?php echo ucfirst($ticket['priority']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 1rem;">
                                                <span style="background: <?php 
                                                    switch($ticket['status']) {
                                                        case 'open': echo 'var(--primary-blue)'; break;
                                                        case 'in_progress': echo 'var(--secondary-blue)'; break;
                                                        case 'closed': echo 'var(--success-green)'; break;
                                                        default: echo 'var(--text-light)';
                                                    }
                                                ?>; color: var(--white); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem;">
                                                    <?php echo ucfirst($ticket['status']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 1rem;"><?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></td>
                                            <td style="padding: 1rem;">
                                                <a href="#" style="color: var(--secondary-blue); text-decoration: none;"><i class="fas fa-eye"></i> View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 1rem; text-align: center; color: var(--text-light);">
                        Need urgent help? Email <a href="mailto:support@girlscoding.com" style="color: var(--secondary-blue);">support@girlscoding.com</a>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <script>
        function confirmDelete(id) {
            if (confirm('Are you sure you want to delete this ticket?')) {
                // AJAX delete or form submit
                fetch('help_desk.php?delete=' + id, { method: 'POST' })
                    .then(() => location.reload());
            }
            return false;
        }
    </script>
</body>
</html>