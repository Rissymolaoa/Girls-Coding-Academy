<?php
// analytics.php
session_start(); // Start session at the very top

// Handle any processing FIRST, before any output (none for analytics, but placeholder for future)

// Database connection (adjust credentials as needed)
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=girlscodingacademydb', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Now include navigation after processing
include 'navigation.php';

// Fetch analytics data
// Total campaigns
$campaignStmt = $pdo->query("SELECT COUNT(*) as total FROM marketing_campaigns");
$totalCampaigns = $campaignStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Active campaigns
$activeCampaignStmt = $pdo->query("SELECT COUNT(*) as active FROM marketing_campaigns WHERE status = 'active'");
$activeCampaigns = $activeCampaignStmt->fetch(PDO::FETCH_ASSOC)['active'];

// Total leads
$leadsStmt = $pdo->query("SELECT COUNT(*) as total FROM marketing_leads");
$totalLeads = $leadsStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Converted leads
$convertedStmt = $pdo->query("SELECT COUNT(*) as converted FROM marketing_leads WHERE status = 'converted'");
$convertedLeads = $convertedStmt->fetch(PDO::FETCH_ASSOC)['converted'];

// Conversion rate
$conversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;

// Recent content views (mock, as no views table; in production, query logs)
$recentViews = 1250; // Placeholder

// Engagement summary (mock)
$engagementRate = 78.5;

// Fetch recent campaigns for table
$recentCampaignsStmt = $pdo->query("SELECT c.title, c.status, COUNT(l.lead_id) as leads 
                                    FROM marketing_campaigns c 
                                    LEFT JOIN marketing_leads l ON c.campaign_id = l.campaign_id 
                                    GROUP BY c.campaign_id 
                                    ORDER BY c.created_at DESC 
                                    LIMIT 5");
$recentCampaigns = $recentCampaignsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch lead data for chart (last 30 days, grouped by day)
$chartDataStmt = $pdo->query("SELECT DATE(created_at) as date, COUNT(*) as count 
                              FROM marketing_leads 
                              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                              GROUP BY DATE(created_at) 
                              ORDER BY date ASC");
$chartData = $chartDataStmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare JSON for Chart.js
$chartLabels = array_column($chartData, 'date');
$chartValues = array_column($chartData, 'count');
?>
            <div class="dashboard">
                <h2>Analytics & Reporting</h2>
                <p>View key performance indicators, trends, and detailed reports for your marketing efforts.</p>
                
                <!-- Key Metrics Cards -->
                <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin: 2rem 0;">
                    <div class="metric-card" style="background: var(--white); padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-light); text-align: center;">
                        <h3 style="color: var(--secondary-blue); margin: 0 0 0.5rem;">Total Campaigns</h3>
                        <p style="font-size: 2rem; font-weight: 700; margin: 0; color: var(--text-dark);"><?php echo $totalCampaigns; ?></p>
                        <small style="color: var(--text-light);">Active: <?php echo $activeCampaigns; ?></small>
                    </div>
                    <div class="metric-card" style="background: var(--white); padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-light); text-align: center;">
                        <h3 style="color: var(--success-green); margin: 0 0 0.5rem;">Total Leads</h3>
                        <p style="font-size: 2rem; font-weight: 700; margin: 0; color: var(--text-dark);"><?php echo $totalLeads; ?></p>
                        <small style="color: var(--text-light);">Converted: <?php echo $convertedLeads; ?></small>
                    </div>
                    <div class="metric-card" style="background: var(--white); padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-light); text-align: center;">
                        <h3 style="color: var(--accent-blue); margin: 0 0 0.5rem;">Conversion Rate</h3>
                        <p style="font-size: 2rem; font-weight: 700; margin: 0; color: var(--text-dark);"><?php echo $conversionRate; ?>%</p>
                        <small style="color: var(--text-light);">Overall</small>
                    </div>
                    <div class="metric-card" style="background: var(--white); padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-light); text-align: center;">
                        <h3 style="color: var(--primary-blue); margin: 0 0 0.5rem;">Engagement Rate</h3>
                        <p style="font-size: 2rem; font-weight: 700; margin: 0; color: var(--text-dark);"><?php echo $engagementRate; ?>%</p>
                        <small style="color: var(--text-light);">This Month</small>
                    </div>
                </div>

                <!-- Real Chart with Chart.js -->
                <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light); margin-bottom: 2rem;">
                    <h3>Lead Conversion Trend (Last 30 Days)</h3>
                    <canvas id="leadsChart" style="max-height: 400px;"></canvas>
                </section>

                <!-- Recent Campaigns Performance Table -->
                <section style="background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-light);">
                    <h3>Recent Campaign Performance</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--light-gray);">
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Campaign</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">Status</th>
                                    <th style="padding: 1rem; text-align: center; border-bottom: 2px solid var(--border-light);">Leads Generated</th>
                                    <th style="padding: 1rem; text-align: center; border-bottom: 2px solid var(--border-light);">Conversion Rate</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-light);">ROI (Est.)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentCampaigns)): ?>
                                    <tr>
                                        <td colspan="5" style="padding: 2rem; text-align: center; color: var(--text-light);">No recent campaigns. Create some to see analytics!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentCampaigns as $campaign): ?>
                                        <?php 
                                        // Calculate real conversion for this campaign
                                        $campLeads = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as conv FROM marketing_leads WHERE campaign_id = (SELECT campaign_id FROM marketing_campaigns WHERE title = ? LIMIT 1)");
                                        $campLeads->execute([$campaign['title']]);
                                        $campData = $campLeads->fetch(PDO::FETCH_ASSOC);
                                        $campConversion = $campData['total'] > 0 ? round(($campData['conv'] / $campData['total']) * 100, 1) : 0;
                                        ?>
                                        <tr style="border-bottom: 1px solid var(--border-light);">
                                            <td style="padding: 1rem;"><?php echo htmlspecialchars($campaign['title']); ?></td>
                                            <td style="padding: 1rem;">
                                                <span style="background: <?php echo $campaign['status'] === 'active' ? 'var(--success-green)' : 'var(--text-light)'; ?>; color: var(--white); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem;">
                                                    <?php echo ucfirst($campaign['status']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 1rem; text-align: center;"><?php echo $campaign['leads']; ?></td>
                                            <td style="padding: 1rem; text-align: center;"><?php echo $campConversion; ?>%</td>
                                            <td style="padding: 1rem;"><?php echo '$' . rand(500, 2000); // Mock ROI ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 1rem; text-align: center;">
                        <a href="#" style="color: var(--secondary-blue); text-decoration: none; font-weight: 600;">Export Full Report (CSV)</a>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Real Chart.js integration
        const ctx = document.getElementById('leadsChart').getContext('2d');
        const chartLabels = <?php echo json_encode($chartLabels); ?>;
        const chartValues = <?php echo json_encode($chartValues); ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'New Leads',
                    data: chartValues,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>