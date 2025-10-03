<?php
/**
 * Database Setup Script
 * Creates database and populates with sample data
 */

// Database connection without database name first
try {
    $pdo = new PDO("mysql:host=localhost;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "Connected to MySQL server successfully.\n";
    
    // Read and execute schema
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    $statements = explode(';', $schema);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Skip errors for statements like "USE database" on first run
                if (strpos($e->getMessage(), 'Unknown database') === false) {
                    echo "Error executing statement: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    echo "Database schema created successfully.\n";
    
    // Read and execute sample data
    $sampleData = file_get_contents(__DIR__ . '/sample_data.sql');
    $statements = explode(';', $sampleData);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                echo "Error inserting data: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "Sample data inserted successfully.\n";
    echo "Database setup completed!\n";
    
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>