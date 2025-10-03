<?php
require_once __DIR__ . '/../includes/config.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . BASE_PATH . '/auth/login.php');
    exit;
}

$pageTitle = 'Search Analytics';

// Get date range filter
$dateRange = $_GET['range'] ?? '30';
$startDate = date('Y-m-d', strtotime("-{$dateRange} days"));

// Get search analytics data
try {
    // Popular search terms
    $popularTermsStmt = $db->prepare("
        SELECT search_query, 
               COUNT(*) as search_count,
               COUNT(DISTINCT user_id) as unique_users,
               AVG(results_count) as avg_results,
               MAX(created_at) as last_searched
        FROM search_logs 
        WHERE search_type = 'search' 
          AND search_query != ''
          AND created_at >= ?
        GROUP BY search_query 
        ORDER BY search_count DESC 
        LIMIT 20
    ");
    $popularTermsStmt->execute([$startDate]);
    $popularTerms = $popularTermsStmt->fetchAll();
    
    // Search trends by day
    $trendsStmt = $db->prepare("
        SELECT DATE(created_at) as search_date,
               COUNT(*) as total_searches,
               COUNT(DISTINCT search_query) as unique_queries,
               COUNT(DISTINCT user_id) as unique_users
        FROM search_logs 
        WHERE created_at >= ?
        GROUP BY DATE(created_at)
        ORDER BY search_date DESC
        LIMIT 30
    ");
    $trendsStmt->execute([$startDate]);
    $searchTrends = $trendsStmt->fetchAll();
    
    // Zero results searches (need improvement)
    $zeroResultsStmt = $db->prepare("
        SELECT search_query,
               COUNT(*) as search_count,
               MAX(created_at) as last_searched
        FROM search_logs 
        WHERE search_type = 'search'
          AND results_count = 0
          AND created_at >= ?
        GROUP BY search_query
        ORDER BY search_count DESC
        LIMIT 15
    ");
    $zeroResultsStmt->execute([$startDate]);
    $zeroResults = $zeroResultsStmt->fetchAll();
    
    // Search statistics
    $statsStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_searches,
            COUNT(DISTINCT search_query) as unique_queries,
            COUNT(DISTINCT user_id) as unique_users,
            AVG(results_count) as avg_results_per_search,
            COUNT(CASE WHEN results_count = 0 THEN 1 END) as zero_result_searches
        FROM search_logs 
        WHERE search_type = 'search' 
          AND created_at >= ?
    ");
    $statsStmt->execute([$startDate]);
    $stats = $statsStmt->fetch();
    
    // Top categories searched
    $categoryStmt = $db->prepare("
        SELECT c.name as category_name,
               COUNT(*) as search_count
        FROM search_logs sl
        LEFT JOIN books b ON (sl.search_query LIKE CONCAT('%', b.title, '%') OR sl.search_query LIKE CONCAT('%', b.author, '%'))
        LEFT JOIN categories c ON b.category_id = c.id
        WHERE sl.search_type = 'search'
          AND sl.created_at >= ?
          AND c.name IS NOT NULL
        GROUP BY c.name
        ORDER BY search_count DESC
        LIMIT 10
    ");
    $categoryStmt->execute([$startDate]);
    $topCategories = $categoryStmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Search analytics error: " . $e->getMessage());
    $popularTerms = [];
    $searchTrends = [];
    $zeroResults = [];
    $stats = ['total_searches' => 0, 'unique_queries' => 0, 'unique_users' => 0, 'avg_results_per_search' => 0, 'zero_result_searches' => 0];
    $topCategories = [];
}

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-search text-primary me-2"></i>Search Analytics
            </h1>
            <p class="text-muted small mb-0">Analyze search patterns and optimize content discoverability</p>
        </div>
        
        <div class="d-flex gap-2">
            <!-- Date Range Filter -->
            <select class="form-select" onchange="window.location.href='?range='+this.value">
                <option value="7" <?php echo $dateRange === '7' ? 'selected' : ''; ?>>Last 7 days</option>
                <option value="30" <?php echo $dateRange === '30' ? 'selected' : ''; ?>>Last 30 days</option>
                <option value="90" <?php echo $dateRange === '90' ? 'selected' : ''; ?>>Last 90 days</option>
                <option value="365" <?php echo $dateRange === '365' ? 'selected' : ''; ?>>Last year</option>
            </select>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-search fa-2x text-primary"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold"><?php echo number_format($stats['total_searches']); ?></div>
                            <div class="text-muted small">Total Searches</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-users fa-2x text-success"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold"><?php echo number_format($stats['unique_users']); ?></div>
                            <div class="text-muted small">Unique Users</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-list fa-2x text-warning"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold"><?php echo number_format($stats['unique_queries']); ?></div>
                            <div class="text-muted small">Unique Queries</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold"><?php echo number_format($stats['zero_result_searches']); ?></div>
                            <div class="text-muted small">Zero Results</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Popular Search Terms -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Popular Search Terms</h5>
                    <small class="text-muted">Last <?php echo $dateRange; ?> days</small>
                </div>
                <div class="card-body">
                    <?php if (empty($popularTerms)): ?>
                        <p class="text-muted text-center py-3">No search data available</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Query</th>
                                        <th class="text-center">Searches</th>
                                        <th class="text-center">Users</th>
                                        <th class="text-center">Avg Results</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($popularTerms as $term): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo sanitize($term['search_query']); ?></strong>
                                                <br><small class="text-muted">Last: <?php echo date('M j, Y', strtotime($term['last_searched'])); ?></small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary"><?php echo $term['search_count']; ?></span>
                                            </td>
                                            <td class="text-center"><?php echo $term['unique_users']; ?></td>
                                            <td class="text-center">
                                                <?php 
                                                $avgResults = round($term['avg_results'], 1);
                                                $badgeClass = $avgResults == 0 ? 'bg-danger' : ($avgResults < 3 ? 'bg-warning' : 'bg-success');
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?>"><?php echo $avgResults; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Search Trends -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Search Trends</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($searchTrends)): ?>
                        <p class="text-muted text-center py-3">No trend data available</p>
                    <?php else: ?>
                        <canvas id="searchTrendsChart" height="200"></canvas>
                        
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const ctx = document.getElementById('searchTrendsChart').getContext('2d');
                                const trendData = <?php echo json_encode(array_reverse($searchTrends)); ?>;
                                
                                new Chart(ctx, {
                                    type: 'line',
                                    data: {
                                        labels: trendData.map(d => new Date(d.search_date).toLocaleDateString()),
                                        datasets: [{
                                            label: 'Total Searches',
                                            data: trendData.map(d => d.total_searches),
                                            borderColor: '#0d6efd',
                                            backgroundColor: 'rgba(13, 110, 253, 0.1)',
                                            fill: true,
                                            tension: 0.4
                                        }, {
                                            label: 'Unique Users',
                                            data: trendData.map(d => d.unique_users),
                                            borderColor: '#198754',
                                            backgroundColor: 'rgba(25, 135, 84, 0.1)',
                                            fill: false,
                                            tension: 0.4
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        scales: {
                                            y: {
                                                beginAtZero: true
                                            }
                                        }
                                    }
                                });
                            });
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Zero Results Searches -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Zero Results Searches</h5>
                    <span class="badge bg-danger"><?php echo count($zeroResults); ?> queries</span>
                </div>
                <div class="card-body">
                    <?php if (empty($zeroResults)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-check-circle fa-3x text-success mb-2"></i>
                            <p class="text-muted">Great! No zero-result searches found.</p>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small mb-3">These queries returned no results. Consider adding content or improving search algorithm.</p>
                        <div class="list-group list-group-flush">
                            <?php foreach ($zeroResults as $query): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <strong><?php echo sanitize($query['search_query']); ?></strong>
                                        <br><small class="text-muted">Last: <?php echo date('M j, Y', strtotime($query['last_searched'])); ?></small>
                                    </div>
                                    <span class="badge bg-danger"><?php echo $query['search_count']; ?> times</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top Categories -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Popular Categories</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($topCategories)): ?>
                        <p class="text-muted text-center py-3">No category data available</p>
                    <?php else: ?>
                        <?php foreach ($topCategories as $category): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><?php echo sanitize($category['category_name']); ?></span>
                                <div>
                                    <span class="badge bg-primary"><?php echo $category['search_count']; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>