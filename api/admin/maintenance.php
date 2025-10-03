<?php
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
if (!verifyCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'clear_cache':
            $result = clearApplicationCache();
            break;
            
        case 'optimize_database':
            $result = optimizeDatabase();
            break;
            
        case 'create_backup':
            $result = createDatabaseBackup();
            break;
            
        case 'cleanup_files':
            $result = cleanupOldFiles();
            break;
            
        case 'view_logs':
            $result = getSystemLogs();
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
    
    // Log the maintenance activity
    logActivity($_SESSION['user_id'], 'maintenance_' . $action, 'system', null, [
        'action' => $action,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Maintenance error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Maintenance operation failed: ' . $e->getMessage()
    ]);
}

/**
 * Clear application cache
 */
function clearApplicationCache(): array {
    $cacheDir = __DIR__ . '/../../cache';
    $clearedFiles = 0;
    
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*');
        foreach ($files as $file) {
            if (is_file($file) && unlink($file)) {
                $clearedFiles++;
            }
        }
    }
    
    // Clear any other cache locations
    $otherCacheDirs = [
        __DIR__ . '/../../tmp',
        __DIR__ . '/../../uploads/cache'
    ];
    
    foreach ($otherCacheDirs as $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && unlink($file)) {
                    $clearedFiles++;
                }
            }
        }
    }
    
    return [
        'success' => true,
        'message' => "Cache cleared successfully. Removed {$clearedFiles} files.",
        'files_cleared' => $clearedFiles
    ];
}

/**
 * Optimize database tables
 */
function optimizeDatabase(): array {
    global $db;
    
    try {
        // Get all tables
        $stmt = $db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $optimizedTables = [];
        
        foreach ($tables as $table) {
            $db->exec("OPTIMIZE TABLE `{$table}`");
            $optimizedTables[] = $table;
        }
        
        // Get database size after optimization
        $sizeStmt = $db->query("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS db_size 
            FROM information_schema.TABLES 
            WHERE table_schema = DATABASE()
        ");
        $dbSize = $sizeStmt->fetchColumn();
        
        return [
            'success' => true,
            'message' => "Database optimization completed. Optimized " . count($optimizedTables) . " tables.",
            'optimized_tables' => count($optimizedTables),
            'database_size_mb' => $dbSize
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Database optimization failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Create database backup
 */
function createDatabaseBackup(): array {
    global $db;
    
    try {
        $backupDir = __DIR__ . '/../../backups';
        
        // Create backups directory if it doesn't exist
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = "backup_{$timestamp}.sql";
        $backupPath = $backupDir . '/' . $backupFile;
        
        // Get database connection details from config
        $host = DB_HOST;
        $database = DB_NAME;
        $username = DB_USER;
        $password = DB_PASS;
        
        // Use mysqldump if available
        $mysqldumpPath = '';
        $possiblePaths = [
            'mysqldump', // If in PATH
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\laragon\\bin\\mysql\\mysql-8.0.30\\bin\\mysqldump.exe'
        ];
        
        foreach ($possiblePaths as $path) {
            if (is_executable($path) || exec("which $path 2>/dev/null")) {
                $mysqldumpPath = $path;
                break;
            }
        }
        
        if ($mysqldumpPath) {
            // Use mysqldump
            $command = "\"{$mysqldumpPath}\" --host={$host} --user={$username} --password={$password} --single-transaction --routines --triggers {$database} > \"{$backupPath}\"";
            exec($command, $output, $returnVar);
            
            if ($returnVar === 0 && file_exists($backupPath)) {
                $fileSize = filesize($backupPath);
                return [
                    'success' => true,
                    'message' => 'Database backup created successfully',
                    'filename' => $backupFile,
                    'size_bytes' => $fileSize,
                    'download_url' => '/api/admin/download-backup.php?file=' . urlencode($backupFile)
                ];
            }
        }
        
        // Fallback to PHP-based backup
        return createPhpBackup($backupPath, $backupFile);
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Backup creation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * PHP-based database backup fallback
 */
function createPhpBackup($backupPath, $backupFile): array {
    global $db;
    
    try {
        $output = "-- Database Backup Created on " . date('Y-m-d H:i:s') . "\n";
        $output .= "-- Generated by Bitversity Admin Panel\n\n";
        
        $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        // Get all tables
        $stmt = $db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            // Get CREATE TABLE statement
            $createStmt = $db->query("SHOW CREATE TABLE `{$table}`");
            $createRow = $createStmt->fetch();
            
            $output .= "-- Table structure for table `{$table}`\n";
            $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $output .= $createRow['Create Table'] . ";\n\n";
            
            // Get table data
            $dataStmt = $db->query("SELECT * FROM `{$table}`");
            $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $output .= "-- Dumping data for table `{$table}`\n";
                
                $columns = array_keys($rows[0]);
                $columnList = '`' . implode('`, `', $columns) . '`';
                
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        $values[] = $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                    }
                    $output .= "INSERT INTO `{$table}` ({$columnList}) VALUES (" . implode(', ', $values) . ");\n";
                }
                $output .= "\n";
            }
        }
        
        $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        file_put_contents($backupPath, $output);
        
        $fileSize = filesize($backupPath);
        
        return [
            'success' => true,
            'message' => 'Database backup created successfully',
            'filename' => $backupFile,
            'size_bytes' => $fileSize,
            'download_url' => '/api/admin/download-backup.php?file=' . urlencode($backupFile)
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'PHP backup creation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Clean up old files
 */
function cleanupOldFiles(): array {
    $cleanedFiles = 0;
    $cleanupDirs = [
        __DIR__ . '/../../logs' => 30, // Keep logs for 30 days
        __DIR__ . '/../../uploads/temp' => 1, // Keep temp files for 1 day
        __DIR__ . '/../../cache' => 7, // Keep cache files for 7 days
        __DIR__ . '/../../backups' => 30 // Keep backups for 30 days
    ];
    
    foreach ($cleanupDirs as $dir => $daysToKeep) {
        if (is_dir($dir)) {
            $files = glob($dir . '/*');
            $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
            
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $cutoffTime) {
                    if (unlink($file)) {
                        $cleanedFiles++;
                    }
                }
            }
        }
    }
    
    return [
        'success' => true,
        'message' => "Cleanup completed. Removed {$cleanedFiles} old files.",
        'files_removed' => $cleanedFiles
    ];
}

/**
 * Get system logs
 */
function getSystemLogs(): array {
    $logDir = __DIR__ . '/../../logs';
    $logs = [];
    
    if (is_dir($logDir)) {
        $files = glob($logDir . '/*.log');
        
        foreach ($files as $file) {
            $logs[] = [
                'filename' => basename($file),
                'size' => filesize($file),
                'modified' => filemtime($file),
                'readable' => is_readable($file)
            ];
        }
        
        // Sort by modification time (newest first)
        usort($logs, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
    }
    
    return [
        'success' => true,
        'logs' => $logs,
        'log_directory' => $logDir
    ];
}
?>