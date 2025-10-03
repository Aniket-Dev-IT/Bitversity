<?php
require_once 'includes/auth.php';
require_once '../includes/analytics.php';
require_once 'includes/layout.php';

$analytics = new AnalyticsService($db);

// Initialize analytics tracking if needed
$analytics->initializeTracking();

// Get analytics data
$period = isset($_GET['period']) ? $_GET['period'] : '30_days';
$stats = $analytics->getDashboardStats();
$engagement = $analytics->getUserEngagement();
$sales_analytics = $analytics->getSalesAnalytics($period);

// Render admin header
renderAdminHeader('Analytics Dashboard', 'Monitor performance metrics and user engagement');
?>

<div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                        Period: <?= str_replace('_', ' ', ucfirst($period)) ?>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="?period=7_days">Last 7 Days</a></li>
                        <li><a class="dropdown-item" href="?period=30_days">Last 30 Days</a></li>
                        <li><a class="dropdown-item" href="?period=90_days">Last 90 Days</a></li>
                        <li><a class="dropdown-item" href="?period=1_year">Last Year</a></li>
                    </ul>
                </div>
            </div>
            
            <!-- Overview Stats -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3 class="mb-0"><?= number_format($stats['total_users']) ?></h3>
                                    <p class="mb-0">Total Users</p>
                                    <small>+<?= $stats['new_users_period'] ?> this period</small>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3 class="mb-0">$<?= number_format($stats['total_revenue'], 2) ?></h3>
                                    <p class="mb-0">Total Revenue</p>
                                    <small>+$<?= number_format($stats['revenue_period'], 2) ?> this period</small>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-dollar-sign fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3 class="mb-0"><?= number_format($stats['total_orders']) ?></h3>
                                    <p class="mb-0">Total Orders</p>
                                    <small>+<?= $stats['orders_period'] ?> this period</small>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-shopping-cart fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3 class="mb-0"><?= number_format($stats['total_content']) ?></h3>
                                    <p class="mb-0">Active Content</p>
                                    <small>Books, Projects, Games</small>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-archive fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Revenue Trend (Last 7 Days)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="revenueChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Revenue by Type</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="typeChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Engagement Metrics -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="text-primary"><?= $sales_analytics['avg_order_value'] ? '$' . number_format($sales_analytics['avg_order_value'], 2) : 'N/A' ?></h3>
                            <p class="text-muted mb-0">Average Order Value</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="text-success"><?= $sales_analytics['conversion_rate'] ?>%</h3>
                            <p class="text-muted mb-0">Conversion Rate</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="text-info"><?= $engagement['return_rate'] ?></h3>
                            <p class="text-muted mb-0">Return Visitor Rate</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Selling Items -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Top Books</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($stats['top_books'])): ?>
                                <p class="text-muted">No sales data available</p>
                            <?php else: ?>
                                <?php foreach ($stats['top_books'] as $book): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($book['title']) ?></div>
                                            <small class="text-muted"><?= $book['sales'] ?> sales</small>
                                        </div>
                                        <div class="text-success fw-bold">
                                            $<?= number_format($book['revenue'], 2) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Top Projects</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($stats['top_projects'])): ?>
                                <p class="text-muted">No sales data available</p>
                            <?php else: ?>
                                <?php foreach ($stats['top_projects'] as $project): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($project['title']) ?></div>
                                            <small class="text-muted"><?= $project['sales'] ?> sales</small>
                                        </div>
                                        <div class="text-success fw-bold">
                                            $<?= number_format($project['revenue'], 2) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Top Games</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($stats['top_games'])): ?>
                                <p class="text-muted">No sales data available</p>
                            <?php else: ?>
                                <?php foreach ($stats['top_games'] as $game): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($game['title']) ?></div>
                                            <small class="text-muted"><?= $game['sales'] ?> sales</small>
                                        </div>
                                        <div class="text-success fw-bold">
                                            $<?= number_format($game['revenue'], 2) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Popular Pages -->
            <?php if (!empty($stats['popular_pages'])): ?>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Popular Pages (Last 7 Days)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Page</th>
                                            <th>Total Views</th>
                                            <th>Unique Views</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats['popular_pages'] as $page): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($page['page']) ?></td>
                                                <td><?= number_format($page['views']) ?></td>
                                                <td><?= number_format($page['unique_views']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Revenue Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const revenueData = <?= json_encode(array_reverse($stats['daily_revenue'])) ?>;

new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: revenueData.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }),
        datasets: [{
            label: 'Revenue ($)',
            data: revenueData.map(item => parseFloat(item.revenue || 0)),
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Revenue by Type Chart
const typeCtx = document.getElementById('typeChart').getContext('2d');
const typeData = <?= json_encode($sales_analytics['revenue_by_type']) ?>;

if (typeData.length > 0) {
    const colors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#3b82f6'];
    
    new Chart(typeCtx, {
        type: 'doughnut',
        data: {
            labels: typeData.map(item => item.item_type.charAt(0).toUpperCase() + item.item_type.slice(1) + 's'),
            datasets: [{
                data: typeData.map(item => parseFloat(item.revenue)),
                backgroundColor: colors.slice(0, typeData.length),
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
} else {
    document.getElementById('typeChart').parentElement.innerHTML = '<div class="text-center py-4 text-muted">No sales data available</div>';
}
</script>

<style>
.opacity-50 {
    opacity: 0.5;
}
</style>

<?php renderAdminFooter(); ?>
