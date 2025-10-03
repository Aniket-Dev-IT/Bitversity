<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $action = $_GET['action'] ?? 'search';
    
    switch ($action) {
        case 'autocomplete':
            handleAutocomplete();
            break;
            
        case 'suggestions':
            handleSuggestions();
            break;
            
        case 'search':
        default:
            handleSearch();
            break;
    }
    
} catch (Exception $e) {
    error_log("Search API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Search service temporarily unavailable'
    ]);
}

/**
 * Handle autocomplete requests - quick title matches
 */
function handleAutocomplete() {
    global $db;
    
    $query = trim($_GET['q'] ?? '');
    $limit = min(10, intval($_GET['limit'] ?? 10));
    
    if (strlen($query) < 2) {
        echo json_encode(['success' => true, 'suggestions' => []]);
        return;
    }
    
    $searchTerm = $query . '%'; // Prefix matching for autocomplete
    
    $suggestions = [];
    
    try {
        // Get title matches from all content types
        $stmt = $db->prepare("
            (SELECT 'book' as type, title, author as subtitle, price 
             FROM books 
             WHERE is_active = 1 AND title LIKE ? 
             ORDER BY title ASC 
             LIMIT ?)
            UNION ALL
            (SELECT 'project' as type, title, 'Project' as subtitle, price
             FROM projects 
             WHERE is_active = 1 AND title LIKE ? 
             ORDER BY title ASC 
             LIMIT ?)
            UNION ALL
            (SELECT 'game' as type, title, genre as subtitle, price
             FROM games 
             WHERE is_active = 1 AND title LIKE ? 
             ORDER BY title ASC 
             LIMIT ?)
            ORDER BY title ASC
            LIMIT ?
        ");
        
        $stmt->execute([$searchTerm, $limit, $searchTerm, $limit, $searchTerm, $limit, $limit]);
        $results = $stmt->fetchAll();
        
        foreach ($results as $result) {
            $suggestions[] = [
                'title' => $result['title'],
                'type' => $result['type'],
                'subtitle' => $result['subtitle'],
                'price' => formatPrice($result['price']),
                'url' => BASE_PATH . "/public/search.php?q=" . urlencode($result['title'])
            ];
        }
        
        // Log autocomplete query for analytics (but don't overwhelm the logs)
        if (isLoggedIn() && strlen($query) >= 3) {
            logSearchQuery($query, 'autocomplete', $_SESSION['user_id']);
        }
        
        echo json_encode([
            'success' => true,
            'query' => $query,
            'suggestions' => $suggestions
        ]);
        
    } catch (PDOException $e) {
        error_log("Autocomplete error: " . $e->getMessage());
        echo json_encode(['success' => true, 'suggestions' => []]); // Fail gracefully
    }
}

/**
 * Handle search suggestions - popular terms and categories
 */
function handleSuggestions() {
    global $db;
    
    try {
        $suggestions = [];
        
        // Get popular search terms (if search_logs table exists)
        try {
            $stmt = $db->prepare("
                SELECT search_query, COUNT(*) as frequency
                FROM search_logs 
                WHERE search_query != '' 
                  AND search_type != 'autocomplete'
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY search_query 
                ORDER BY frequency DESC 
                LIMIT 10
            ");
            $stmt->execute();
            $popularTerms = $stmt->fetchAll();
            
            foreach ($popularTerms as $term) {
                $suggestions[] = [
                    'text' => $term['search_query'],
                    'type' => 'popular',
                    'frequency' => $term['frequency'],
                    'url' => BASE_PATH . "/public/search.php?q=" . urlencode($term['search_query'])
                ];
            }
        } catch (PDOException $e) {
            // search_logs table might not exist yet - ignore
        }
        
        // Get categories as suggestions
        $stmt = $db->prepare("SELECT name, slug FROM categories ORDER BY name LIMIT 10");
        $stmt->execute();
        $categories = $stmt->fetchAll();
        
        foreach ($categories as $category) {
            $suggestions[] = [
                'text' => $category['name'],
                'type' => 'category',
                'url' => BASE_PATH . "/public/search.php?category=" . $category['id']
            ];
        }
        
        // Add some predefined popular searches if no data available
        if (empty($suggestions)) {
            $predefinedSuggestions = [
                'JavaScript', 'Python', 'React', 'Web Development', 
                'Machine Learning', 'Data Science', 'Mobile Development'
            ];
            
            foreach ($predefinedSuggestions as $term) {
                $suggestions[] = [
                    'text' => $term,
                    'type' => 'trending',
                    'url' => BASE_PATH . "/public/search.php?q=" . urlencode($term)
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'suggestions' => $suggestions
        ]);
        
    } catch (PDOException $e) {
        error_log("Suggestions error: " . $e->getMessage());
        echo json_encode(['success' => true, 'suggestions' => []]);
    }
}

/**
 * Handle full search requests - similar to the main search page but JSON response
 */
function handleSearch() {
    global $db;
    
    $query = trim($_GET['q'] ?? '');
    $type = $_GET['type'] ?? 'all';
    $category = $_GET['category'] ?? '';
    $sortBy = $_GET['sort'] ?? 'relevance';
    $minPrice = floatval($_GET['min_price'] ?? 0);
    $maxPrice = floatval($_GET['max_price'] ?? 0);
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = intval($_GET['per_page'] ?? 12);
    $offset = ($page - 1) * $perPage;
    
    if (empty($query) && empty($category) && $minPrice == 0 && $maxPrice == 0) {
        echo json_encode([
            'success' => true,
            'results' => [],
            'total' => 0,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => 0
        ]);
        return;
    }
    
    // Log search query for analytics
    if (isLoggedIn() && !empty($query)) {
        logSearchQuery($query, $type, $_SESSION['user_id']);
    }
    
    // Build WHERE conditions (similar to main search page)
    $conditions = [];
    $params = [];
    
    if (!empty($query)) {
        $searchTerm = '%' . $query . '%';
        $conditions[] = "(title LIKE ? OR description LIKE ? OR author LIKE ? OR tags LIKE ?)";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if (!empty($category)) {
        $conditions[] = "category_id = ?";
        $params[] = $category;
    }
    
    if ($minPrice > 0) {
        $conditions[] = "price >= ?";
        $params[] = $minPrice;
    }
    
    if ($maxPrice > 0) {
        $conditions[] = "price <= ?";
        $params[] = $maxPrice;
    }
    
    $conditions[] = "is_active = 1";
    $whereClause = 'WHERE ' . implode(' AND ', $conditions);
    
    // Build ORDER BY clause
    $orderBy = match($sortBy) {
        'price_low' => 'price ASC',
        'price_high' => 'price DESC',
        'name' => 'title ASC',
        'newest' => 'created_at DESC',
        default => !empty($query) ? 'CASE 
            WHEN title LIKE ? THEN 1 
            WHEN author LIKE ? THEN 2 
            WHEN description LIKE ? THEN 3 
            ELSE 4 END, title ASC' : 'created_at DESC'
    };
    
    // Add relevance parameters if needed
    $relevanceParams = [];
    if ($sortBy === 'relevance' && !empty($query)) {
        $relevanceParams = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    // Build search queries
    $searchQueries = [];
    $searchParams = [];
    
    if ($type === 'all' || $type === 'books') {
        $searchQueries[] = "
            SELECT 'book' as content_type, id, title, author as subtitle, price, 
                   cover_image as image, description, created_at,
                   tags, '' as genre, '' as language, '' as difficulty_level
            FROM books 
            $whereClause
        ";
        $searchParams = array_merge($searchParams, $relevanceParams, $params);
    }
    
    if ($type === 'all' || $type === 'projects') {
        $searchQueries[] = "
            SELECT 'project' as content_type, id, title, 'Project' as subtitle, price,
                   cover_image as image, description, created_at,
                   tags, '' as genre, language, difficulty_level
            FROM projects 
            $whereClause
        ";
        $searchParams = array_merge($searchParams, $relevanceParams, $params);
    }
    
    if ($type === 'all' || $type === 'games') {
        $searchQueries[] = "
            SELECT 'game' as content_type, id, title, genre as subtitle, price,
                   thumbnail as image, description, created_at,
                   tags, genre, '' as language, '' as difficulty_level
            FROM games 
            $whereClause
        ";
        $searchParams = array_merge($searchParams, $relevanceParams, $params);
    }
    
    try {
        if (!empty($searchQueries)) {
            // Get results
            $fullQuery = implode(' UNION ALL ', $searchQueries) . " ORDER BY $orderBy LIMIT $perPage OFFSET $offset";
            $stmt = $db->prepare($fullQuery);
            $stmt->execute($searchParams);
            $results = $stmt->fetchAll();
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM (" . implode(' UNION ALL ', $searchQueries) . ") as combined_results";
            $countStmt = $db->prepare($countQuery);
            $countStmt->execute($searchParams);
            $total = $countStmt->fetch()['total'];
            
            // Format results for JSON
            $formattedResults = [];
            foreach ($results as $result) {
                $imagePath = $result['image'] ? 
                    upload($result['content_type'] . 's/' . $result['image']) : 
                    'https://via.placeholder.com/300x400/0d6efd/ffffff?text=' . ucfirst($result['content_type']);
                
                $formattedResults[] = [
                    'id' => $result['id'],
                    'type' => $result['content_type'],
                    'title' => $result['title'],
                    'subtitle' => $result['subtitle'],
                    'description' => substr($result['description'], 0, 150),
                    'price' => $result['price'],
                    'formatted_price' => formatPrice($result['price']),
                    'image' => $imagePath,
                    'difficulty_level' => $result['difficulty_level'],
                    'language' => $result['language'],
                    'genre' => $result['genre'],
                    'created_at' => $result['created_at']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'query' => $query,
                'results' => $formattedResults,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
                'filters' => [
                    'type' => $type,
                    'category' => $category,
                    'sort' => $sortBy,
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice
                ]
            ]);
            
        } else {
            echo json_encode([
                'success' => true,
                'results' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => 0
            ]);
        }
        
    } catch (PDOException $e) {
        error_log("Search API error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Search failed',
            'results' => [],
            'total' => 0
        ]);
    }
}

/**
 * Log search queries for analytics
 */
function logSearchQuery($query, $type, $userId = null) {
    global $db;
    
    // Only log if query is meaningful
    if (strlen($query) < 2) return;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO search_logs (search_query, search_type, user_id, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$query, $type, $userId]);
    } catch (PDOException $e) {
        // Fail silently - search_logs table might not exist yet
        error_log("Search logging error: " . $e->getMessage());
    }
}
?>