<?php
/**
 * Database Migration System
 * 
 * Simple migration system for managing database schema changes.
 * This system tracks which migrations have been applied and runs new ones.
 * 
 * Usage: 
 *   php database/migrate.php
 *   php database/migrate.php --rollback
 *   php database/migrate.php --status
 */

require_once __DIR__ . '/../includes/config.php';

// Only allow execution from command line or localhost for security
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', 'localhost'])) {
    die('Access denied. This script can only be run from command line or localhost.');
}

class MigrationManager {
    private $db;
    private $migrationsDir;
    
    public function __construct($database, $migrationsDir = null) {
        $this->db = $database;
        $this->migrationsDir = $migrationsDir ?: __DIR__ . '/migrations';
        
        $this->ensureMigrationTable();
    }
    
    /**
     * Create the migrations table if it doesn't exist
     */
    private function ensureMigrationTable() {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) UNIQUE NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_migration (migration)
        )";
        
        $this->db->exec($sql);
    }
    
    /**
     * Get all available migration files
     */
    private function getAvailableMigrations() {
        if (!is_dir($this->migrationsDir)) {
            return [];
        }
        
        $files = glob($this->migrationsDir . '/*.sql');
        sort($files);
        
        return array_map(function($file) {
            return basename($file, '.sql');
        }, $files);
    }
    
    /**
     * Get migrations that have been executed
     */
    private function getExecutedMigrations() {
        $stmt = $this->db->query("SELECT migration FROM migrations ORDER BY migration");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    }
    
    /**
     * Get pending migrations
     */
    public function getPendingMigrations() {
        $available = $this->getAvailableMigrations();
        $executed = $this->getExecutedMigrations();
        
        return array_diff($available, $executed);
    }
    
    /**
     * Execute a single migration
     */
    private function executeMigration($migration) {
        $file = $this->migrationsDir . '/' . $migration . '.sql';
        
        if (!file_exists($file)) {
            throw new Exception("Migration file not found: {$file}");
        }
        
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new Exception("Could not read migration file: {$file}");
        }
        
        echo "Executing migration: {$migration}\n";
        
        // Begin transaction
        $this->db->beginTransaction();
        
        try {
            // Execute the migration SQL
            $statements = $this->splitSqlStatements($sql);
            
            foreach ($statements as $statement) {
                if (!empty(trim($statement))) {
                    $this->db->exec($statement);
                }
            }
            
            // Record the migration as executed
            $stmt = $this->db->prepare("INSERT INTO migrations (migration) VALUES (?)");
            $stmt->execute([$migration]);
            
            // Commit transaction
            $this->db->commit();
            
            echo "✅ Migration {$migration} executed successfully\n";
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->db->rollBack();
            throw new Exception("Migration {$migration} failed: " . $e->getMessage());
        }
    }
    
    /**
     * Split SQL into individual statements
     */
    private function splitSqlStatements($sql) {
        // Remove comments
        $sql = preg_replace('/--.*$/m', '', $sql);
        
        // Split by semicolon and filter empty statements
        $statements = array_filter(
            array_map('trim', preg_split('/;\s*$/m', $sql)),
            function($statement) {
                return !empty($statement) && !preg_match('/^\s*$/', $statement);
            }
        );
        
        return $statements;
    }
    
    /**
     * Run all pending migrations
     */
    public function migrate() {
        $pending = $this->getPendingMigrations();
        
        if (empty($pending)) {
            echo "✅ No pending migrations\n";
            return true;
        }
        
        echo "Found " . count($pending) . " pending migration(s)\n";
        
        foreach ($pending as $migration) {
            try {
                $this->executeMigration($migration);
            } catch (Exception $e) {
                echo "❌ " . $e->getMessage() . "\n";
                return false;
            }
        }
        
        echo "🎉 All migrations executed successfully\n";
        return true;
    }
    
    /**
     * Show migration status
     */
    public function status() {
        $available = $this->getAvailableMigrations();
        $executed = $this->getExecutedMigrations();
        $pending = $this->getPendingMigrations();
        
        echo "📊 Migration Status\n";
        echo "==================\n";
        echo "Available migrations: " . count($available) . "\n";
        echo "Executed migrations: " . count($executed) . "\n";
        echo "Pending migrations: " . count($pending) . "\n\n";
        
        if (!empty($executed)) {
            echo "✅ Executed Migrations:\n";
            foreach ($executed as $migration) {
                echo "  - {$migration}\n";
            }
            echo "\n";
        }
        
        if (!empty($pending)) {
            echo "⏳ Pending Migrations:\n";
            foreach ($pending as $migration) {
                echo "  - {$migration}\n";
            }
            echo "\n";
        }
        
        if (empty($available)) {
            echo "ℹ️ No migration files found in: {$this->migrationsDir}\n";
        }
    }
    
    /**
     * Create a new migration file
     */
    public function create($name, $description = '') {
        if (!is_dir($this->migrationsDir)) {
            mkdir($this->migrationsDir, 0755, true);
        }
        
        $timestamp = date('Y_m_d_His');
        $filename = $timestamp . '_' . $name . '.sql';
        $filepath = $this->migrationsDir . '/' . $filename;
        
        $template = "-- Migration: {$filename}\n";
        $template .= "-- Description: {$description}\n";
        $template .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
        $template .= "-- Version: 1.0.0\n\n";
        $template .= "-- Add your migration SQL here\n\n";
        $template .= "-- Example:\n";
        $template .= "-- ALTER TABLE users ADD COLUMN phone VARCHAR(20);\n";
        $template .= "-- CREATE INDEX idx_users_phone ON users(phone);\n";
        
        if (file_put_contents($filepath, $template) !== false) {
            echo "✅ Created migration: {$filename}\n";
            echo "📝 Edit the file to add your migration SQL\n";
            return $filepath;
        } else {
            echo "❌ Failed to create migration file\n";
            return false;
        }
    }
}

// Parse command line arguments
$action = $argv[1] ?? 'migrate';

try {
    $migrator = new MigrationManager($db);
    
    switch ($action) {
        case 'migrate':
        case '--migrate':
            echo "🚀 Running database migrations...\n\n";
            $migrator->migrate();
            break;
            
        case 'status':
        case '--status':
            $migrator->status();
            break;
            
        case 'create':
        case '--create':
            $name = $argv[2] ?? null;
            $description = $argv[3] ?? '';
            
            if (!$name) {
                echo "❌ Please provide a migration name\n";
                echo "Usage: php migrate.php create migration_name \"Optional description\"\n";
                exit(1);
            }
            
            $migrator->create($name, $description);
            break;
            
        case 'rollback':
        case '--rollback':
            echo "⚠️ Rollback functionality not implemented yet\n";
            echo "Manual rollback required - check your database and migration files\n";
            break;
            
        case 'help':
        case '--help':
        case '-h':
            echo "🔧 Database Migration System\n";
            echo "============================\n\n";
            echo "Usage:\n";
            echo "  php migrate.php                    - Run pending migrations\n";
            echo "  php migrate.php status             - Show migration status\n";
            echo "  php migrate.php create <name>      - Create new migration file\n";
            echo "  php migrate.php rollback           - Rollback last migration (not implemented)\n";
            echo "  php migrate.php help               - Show this help\n\n";
            echo "Examples:\n";
            echo "  php migrate.php create add_user_phone \"Add phone field to users table\"\n";
            echo "  php migrate.php status\n";
            echo "  php migrate.php migrate\n";
            break;
            
        default:
            echo "❌ Unknown action: {$action}\n";
            echo "Use 'php migrate.php help' for usage information\n";
            exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ Migration command completed\n";
?>