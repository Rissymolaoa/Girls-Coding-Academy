<?php
// marketing_dashboard.php (Updated version with real DB integration and enhanced visuals)
// Include navigation
session_start();
include 'navigation.php';

// Database connection (adjust credentials as needed)
try {
    $pdo = new PDO('mysql:host=127.0.0.1:3307;dbname=girlscodingacademydb', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Fetch real metrics
// Active Campaigns
$activeCampaignsStmt = $pdo->query("SELECT COUNT(*) as count FROM marketing_campaigns WHERE status = 'active'");
$activeCampaigns = $activeCampaignsStmt->fetch(PDO::FETCH_ASSOC)['count'];

// New Leads (last 7 days)
$newLeadsStmt = $pdo->query("SELECT COUNT(*) as count FROM marketing_leads WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$newLeads = $newLeadsStmt->fetch(PDO::FETCH_ASSOC)['count'];

// Engagement Rate (simplified: avg conversion from recent campaigns)
$engagementStmt = $pdo->query("SELECT AVG((converted / total) * 100) as avg_rate FROM (
    SELECT COUNT(l.lead_id) as total, SUM(CASE WHEN l.status = 'converted' THEN 1 ELSE 0 END) as converted 
    FROM marketing_campaigns c 
    LEFT JOIN marketing_leads l ON c.campaign_id = l.campaign_id 
    WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
    GROUP BY c.campaign_id
) as subquery");
$engagementRate = round($engagementStmt->fetch(PDO::FETCH_ASSOC)['avg_rate'] ?? 0, 1);

// Content Views (mock, as no views table; integrate logs in production)
$contentViewsStmt = $pdo->query("SELECT COUNT(*) as count FROM marketing_content WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$contentViews = $contentViewsStmt->fetch(PDO::FETCH_ASSOC)['count'];

// Recent Activity (last 5 events across tables)
$recentActivityStmt = $pdo->query("SELECT 'Campaign' as type, title as message, created_at as timestamp FROM marketing_campaigns WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                   UNION ALL
                                   SELECT 'Lead' as type, CONCAT(name, ' added') as message, created_at as timestamp FROM marketing_leads WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                   UNION ALL
                                   SELECT 'Post' as type, CONCAT(platform, ': ', content) as message, posted_at as timestamp FROM marketing_social_posts WHERE posted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                   ORDER BY timestamp DESC LIMIT 5");
$recentActivity = $recentActivityStmt->fetchAll(PDO::FETCH_ASSOC);
?>
            <div class="dashboard">
                <h2>Marketing Dashboard</h2>
                <p>Welcome to your Marketing control center. Monitor key metrics and quick actions below. Updated with real-time data from the database.</p>
                
                <!-- Key Metrics Cards -->
                <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin: 2rem 0;">
                    <div class="metric-card" style="background: var(--white); padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-light); text-align: center; transition: transform 0.3s;">
                        <h3 style="color: var(--secondary-blue); margin: 0 0 0.5rem;">Active Campaigns</h3>
                        <p style="font-size: 2rem; font-weight: 700; margin: 0; color: var(--text-dark);"><?php echo $activeCampaigns; ?></p>
                        <a href="campaigns.php" style="color: var(--accent-blue); text-decoration: none; font-size: 0.9rem;">View All →</a>
                    </div>
                    <div class="metric-card" style="background: var(--white); padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-light); text-align: center; transition: transform 0.3s;">
                        <h3 style="color: var(--success-green); margin: 0 0 0.5rem;">New Leads (7 Days)</h3>
                        <p style="font-size: 2rem; font-weight: 700; margin: 0; color: var(--text-dark);"><?php echo $newLeads; ?></p>
                        <a href="leads.php" style="color: var(--accent-blue); text-decoration: none; font-size: 0.9rem;">Manage Leads →</a>
                    </div>
                    <div class="metric-card" style="background: var(--white); padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-light); text-align: center; transition: transform 0.3s;">
                        <h3 style="color: var(--accent-blue); margin: 0 0 0.5rem;">Avg Engagement Rate</h3>
                        <p style="font-size: 2rem; font-weight: 700; margin: 0; color: var(--text-dark);"><?php echo $engagementRate; ?>%</p>
                        <a href="analytics.php" style="color: var(--accent-blue); text-decoration: none; font-size: 0.9rem;">View Analytics →</a>
                    </div>
                    <div class="metric-card" style="background: var(--white); padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-light); text-align: center; transition: transform 0.3s;">
                        <h3 style="color: var(--primary-blue); margin: 0 0 0.5rem;">New Content (7 Days)</h3>
                        <p style="font-size: 2rem; font-weight: 700; margin: 0; color: var(--text-dark);"><?php echo $contentViews; ?></p>
                        <a href="content.php" style="color: var(--accent-blue); text-decoration: none; font-size: 0.9rem;">Upload Content →</a>
                    </div>
                </div>

                <!-- Quick Actions -->
                <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light); margin-bottom: 2rem;">
                    <h3>Quick Actions</h3>
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <button style="background: var(--secondary-blue); color: var(--white); border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; transition: background 0.3s;" onclick="location.href='campaigns.php?new=1';">
                            <i class="fas fa-bullhorn"></i> Create New Campaign
                        </button>
                        <button style="background: var(--success-green); color: var(--white); border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; transition: background 0.3s;" onclick="location.href='leads.php?import=1';">
                            <i class="fas fa-users"></i> Import Leads
                        </button>
                        <button style="background: var(--accent-blue); color: var(--white); border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; transition: background 0.3s;" onclick="location.href='social.php?post=1';">
                            <i class="fas fa-share-alt"></i> Post to Social
                        </button>
                        <button style="background: var(--primary-blue); color: var(--white); border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; transition: background 0.3s;" onclick="location.href='feedback.php?new=1';">
                            <i class="fas fa-comments"></i> Launch Survey
                        </button>
                    </div>
                </section>

                <!-- Recent Activity -->
                <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light);">
                    <h3>Recent Activity</h3>
                    <ul style="list-style: none; padding: 0;">
                        <?php if (empty($recentActivity)): ?>
                            <li style="padding: 1rem; color: var(--text-light); text-align: center;">No recent activity.</li>
                        <?php else: ?>
                            <?php foreach ($recentActivity as $activity): ?>
                                <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center;">
                                    <span style="flex: 1;">
                                        <i class="<?php 
                                            switch($activity['type']) {
                                                case 'Campaign': echo 'fas fa-bullhorn'; break;
                                                case 'Lead': echo 'fas fa-users'; break;
                                                case 'Post': echo 'fas fa-share-alt'; break;
                                                default: echo 'fas fa-info-circle';
                                            }
                                        ?>" style="color: var(--secondary-blue); margin-right: 0.5rem;"></i>
                                        <?php echo htmlspecialchars($activity['message']); ?>
                                    </span>
                                    <small style="color: var(--text-light);"><?php echo date('M d, H:i', strtotime($activity['timestamp'])); ?> ago</small>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </section>
            </div>
        </main>
    </div>
    <script>
        // Hover effects for cards
        document.querySelectorAll('.metric-card').forEach(card => {
            card.addEventListener('mouseenter', () => card.style.transform = 'translateY(-5px)');
            card.addEventListener('mouseleave', () => card.style.transform = 'translateY(0)');
        });
    </script>
</body>
</html>