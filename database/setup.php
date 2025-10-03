<?php
/**
 * Database Setup Script
 * 
 * This script initializes the database with the schema and sample data.
 * Run this script once to set up your Bitversity database.
 * 
 * Usage: php database/setup.php
 * Or access via browser: http://localhost/Bitversity/database/setup.php
 */

// Only allow execution from command line or localhost for security
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', 'localhost'])) {
    die('Access denied. This script can only be run from command line or localhost.');
}

require_once __DIR__ . '/../includes/config.php';

// HTML header for browser access
if (php_sapi_name() !== 'cli') {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Setup - Bitversity</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 40px; background: #f8f9fa; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
            .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
            .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
            .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
            .step { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #007bff; }
            .sql-output { background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; margin: 10px 0; }
            pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🚀 Bitversity Database Setup</h1>
            <p>Setting up your database with schema and sample data...</p>';
}

function output($message, $type = 'info') {
    if (php_sapi_name() === 'cli') {
        $prefix = match($type) {
            'success' => '✅ ',
            'error' => '❌ ',
            'warning' => '⚠️ ',
            default => 'ℹ️ '
        };
        echo $prefix . $message . PHP_EOL;
    } else {
        echo "<div class=\"{$type}\">{$message}</div>";
        flush();
        ob_flush();
    }
}

function executeSQL($db, $filename, $description) {
    output("📄 Executing {$description}...");
    
    $sqlFile = __DIR__ . '/' . $filename;
    
    if (!file_exists($sqlFile)) {
        output("❌ SQL file not found: {$filename}", 'error');
        return false;
    }
    
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        output("❌ Could not read SQL file: {$filename}", 'error');
        return false;
    }
    
    // Split SQL into individual statements
    $statements = array_filter(
        array_map('trim', preg_split('/;\s*$/m', $sql)),
        function($statement) {
            return !empty($statement) && !preg_match('/^--/', $statement);
        }
    );
    
    $successCount = 0;
    $errorCount = 0;
    $results = [];
    
    foreach ($statements as $statement) {
        // Skip comments and empty statements
        if (empty($statement) || preg_match('/^\s*--/', $statement)) {
            continue;
        }
        
        try {
            $stmt = $db->prepare($statement);
            $success = $stmt->execute();
            
            if ($success) {
                $successCount++;
                
                // Collect results from SELECT statements
                if (stripos($statement, 'SELECT') === 0) {
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result) {
                        $results[] = $result;
                    }
                }
            } else {
                $errorCount++;
                $errorInfo = $stmt->errorInfo();
                output("❌ SQL Error: {$errorInfo[2]}", 'error');
            }
            
        } catch (PDOException $e) {
            $errorCount++;
            
            // Skip certain expected errors
            if (strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate entry') !== false) {
                output("⚠️ Skipped (already exists): " . substr($statement, 0, 50) . "...", 'warning');
                continue;
            }
            
            output("❌ SQL Error: {$e->getMessage()}", 'error');
            if (php_sapi_name() !== 'cli') {
                echo "<pre>" . htmlspecialchars(substr($statement, 0, 200)) . "...</pre>";
            }
        }
    }
    
    // Display results from SELECT statements
    foreach ($results as $result) {
        foreach ($result as $key => $value) {
            output("📊 {$key}: {$value}");
        }
    }
    
    if ($errorCount === 0) {
        output("✅ {$description} completed successfully! ({$successCount} statements)", 'success');
        return true;
    } else {
        output("⚠️ {$description} completed with {$errorCount} errors and {$successCount} successes", 'warning');
        return false;
    }
}

// Start the setup process
output("🚀 Starting Bitversity database setup...");
output("📋 This will create all necessary tables and add sample data");

try {
    // Test database connection
    output("🔍 Testing database connection...");
    $testQuery = $db->query("SELECT 1");
    if ($testQuery === false) {
        throw new Exception("Database connection test failed");
    }
    output("✅ Database connection successful!", 'success');
    
    // Get database info
    $dbName = $db->query("SELECT DATABASE()")->fetchColumn();
    output("📊 Connected to database: {$dbName}");
    
    // Check if tables already exist
    $tablesExist = $db->query("SHOW TABLES LIKE 'users'")->fetch();
    if ($tablesExist) {
        output("⚠️ Database tables already exist. This will add/update data.", 'warning');
        
        if (php_sapi_name() !== 'cli') {
            echo '<div class="warning">
                <strong>Warning:</strong> Tables already exist. Continue?<br>
                <button onclick="location.reload()" style="margin-top: 10px; padding: 8px 16px;">Continue Setup</button>
            </div>';
        } else {
            echo "Continue? (y/N): ";
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            if (strtolower(trim($line)) !== 'y') {
                output("Setup cancelled by user");
                exit(0);
            }
        }
    }
    
    // Execute schema creation
    output("");
    output("📋 Step 1: Creating database schema...");
    $schemaSuccess = executeSQL($db, 'schema.sql', 'Database Schema');
    
    // Execute sample data insertion
    output("");
    output("📋 Step 2: Inserting sample data...");
    $dataSuccess = executeSQL($db, 'sample_data.sql', 'Sample Data');
    
    // Verify the installation
    output("");
    output("🔍 Step 3: Verifying installation...");
    
    $verificationQueries = [
        'users' => "SELECT COUNT(*) as count FROM users",
        'categories' => "SELECT COUNT(*) as count FROM categories", 
        'books' => "SELECT COUNT(*) as count FROM books",
        'projects' => "SELECT COUNT(*) as count FROM projects",
        'games' => "SELECT COUNT(*) as count FROM games",
        'orders' => "SELECT COUNT(*) as count FROM orders",
        'reviews' => "SELECT COUNT(*) as count FROM reviews"
    ];
    
    $verificationPassed = true;
    foreach ($verificationQueries as $table => $query) {
        try {
            $result = $db->query($query)->fetchColumn();
            output("📊 {$table}: {$result} records");
            
            if ($result == 0 && in_array($table, ['users', 'categories'])) {
                $verificationPassed = false;
                output("❌ Critical table {$table} has no data!", 'error');
            }
        } catch (Exception $e) {
            $verificationPassed = false;
            output("❌ Failed to verify {$table}: {$e->getMessage()}", 'error');
        }
    }
    
    // Final results
    output("");
    if ($schemaSuccess && $dataSuccess && $verificationPassed) {
        output("🎉 Database setup completed successfully!", 'success');
        output("🔑 You can now log in with these demo credentials:");
        output("   📧 Email: demo@bitversity.com");
        output("   🔒 Password: Demo123456");
        output("   👑 Admin: admin@bitversity.com / Demo123456");
        
        if (php_sapi_name() !== 'cli') {
            echo '<div class="success">
                <h3>🎉 Setup Complete!</h3>
                <p><strong>Demo Login Credentials:</strong></p>
                <ul>
                    <li><strong>User:</strong> demo@bitversity.com / Demo123456</li>
                    <li><strong>Admin:</strong> admin@bitversity.com / Demo123456</li>
                </ul>
                <p><a href="../public/" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">🚀 Visit Bitversity</a></p>
            </div>';
        }
        
    } else {
        output("⚠️ Database setup completed with some issues. Please check the errors above.", 'warning');
    }
    
    // Security reminder
    output("");
    output("🔒 Security reminder: Remove or secure this setup script in production!", 'warning');
    
} catch (Exception $e) {
    output("❌ Setup failed: {$e->getMessage()}", 'error');
    
    if (php_sapi_name() !== 'cli') {
        echo '<div class="error"><strong>Setup Failed:</strong><br>' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    
    exit(1);
}

// HTML footer for browser access
if (php_sapi_name() !== 'cli') {
    echo '
            <div class="info">
                <h3>📚 Next Steps</h3>
                <ol>
                    <li>Visit your <a href="../public/">Bitversity homepage</a></li>
                    <li>Log in with the demo credentials above</li>
                    <li>Explore the features: Books, Projects, Games</li>
                    <li>Test the shopping cart and checkout process</li>
                    <li>Check the admin panel (if logged in as admin)</li>
                </ol>
            </div>
        </div>
    </body>
    </html>';
}

output("");
output("✅ Setup script completed!");
?>