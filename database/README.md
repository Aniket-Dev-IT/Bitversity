# 🗄️ Bitversity Database System

This directory contains all database-related files for the Bitversity digital learning platform.

## 📁 Directory Structure

```
database/
├── README.md                 # This file
├── schema.sql               # Complete database schema
├── sample_data.sql          # Sample data for testing
├── setup.php                # Automated setup script
├── migrate.php              # Migration management system
└── migrations/              # Individual migration files
    └── 001_initial_schema.sql
```

## 🚀 Quick Setup

### Option 1: Automated Setup (Recommended)

Run the setup script to automatically create tables and insert sample data:

**Via Browser:**
```
http://localhost/Bitversity/database/setup.php
```

**Via Command Line:**
```bash
php database/setup.php
```

### Option 2: Manual Setup

1. Create your database:
```sql
CREATE DATABASE bitversity CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bitversity;
```

2. Import the schema:
```bash
mysql -u your_username -p bitversity < database/schema.sql
```

3. Import sample data (optional):
```bash
mysql -u your_username -p bitversity < database/sample_data.sql
```

## 📊 Database Schema Overview

The database includes the following main components:

### 👥 User Management
- **users** - User accounts and profiles
- **remember_tokens** - "Remember me" authentication tokens
- **password_resets** - Password reset tokens

### 📚 Content Management
- **categories** - Content categories
- **books** - Digital books and ebooks
- **projects** - Coding projects and tutorials
- **games** - Educational games and simulations
- **posts** - Blog posts and articles

### 🛒 E-commerce
- **cart** - User shopping carts
- **wishlist** - User wishlists
- **orders** - Purchase orders
- **order_items** - Individual items in orders

### ⭐ Review System
- **reviews** - User reviews and ratings

### 🔧 System
- **settings** - Application configuration
- **activity_log** - User activity tracking
- **uploads** - File upload tracking

## 🔄 Migration System

Manage database schema changes using the migration system:

### View Migration Status
```bash
php database/migrate.php status
```

### Run Pending Migrations
```bash
php database/migrate.php migrate
```

### Create New Migration
```bash
php database/migrate.php create add_user_phone "Add phone field to users"
```

### Migration Commands
```bash
php database/migrate.php help          # Show help
php database/migrate.php status        # Show migration status
php database/migrate.php migrate       # Run pending migrations
php database/migrate.php create <name> # Create new migration
```

## 📋 Sample Data

The sample data includes:

### Users (Password: `Demo123456`)
- **demo@bitversity.com** - Regular user account
- **admin@bitversity.com** - Administrator account
- **john@example.com** - Test user with purchase history
- **sarah@example.com** - Test user with orders
- **michael@example.com** - Test user with completed orders

### Content
- **8 Categories** - Web Development, Mobile Development, Data Science, etc.
- **6 Books** - JavaScript, React, Python, Flutter, Security, UI/UX
- **5 Projects** - E-commerce site, Weather app, ML classifier, etc.
- **5 Games** - JavaScript quiz, Python simulator, Data structures, etc.
- **3 Blog Posts** - Getting started guides and tutorials

### E-commerce Data
- **Sample Orders** - Completed and pending orders with realistic data
- **Reviews** - User reviews with ratings and comments
- **Cart Items** - Active shopping cart items
- **Wishlist Items** - Saved items for later

## 🛡️ Security Features

### Authentication
- Secure password hashing with PHP's `password_hash()`
- "Remember me" tokens with expiration
- Failed login attempt tracking and account locking
- Email verification system ready

### Data Protection
- Prepared statements prevent SQL injection
- Input sanitization throughout the application
- Secure session management
- Activity logging for audit trails

## 📈 Performance Optimizations

### Indexes
- Strategic indexes on frequently queried columns
- Composite indexes for complex queries
- Full-text search indexes for content search
- Foreign key constraints for data integrity

### Views
- **popular_content** - Aggregated popular items across all types
- **featured_content** - Featured items with unified structure
- **user_stats** - User statistics for dashboards

## 🔧 Maintenance

### Regular Tasks
1. **Monitor logs** - Check activity_log for suspicious activity
2. **Clean up tokens** - Remove expired password reset and remember tokens
3. **Update statistics** - Refresh view counts, ratings, and statistics
4. **Backup database** - Regular automated backups

### Backup Command
```bash
mysqldump -u username -p bitversity > backup_$(date +%Y%m%d).sql
```

### Restore Command
```bash
mysql -u username -p bitversity < backup_20240101.sql
```

## 🐛 Troubleshooting

### Common Issues

**Setup script fails:**
- Check database connection in `includes/config.php`
- Verify MySQL/MariaDB is running
- Ensure user has CREATE, INSERT, SELECT, UPDATE privileges

**Migration errors:**
- Check migration file syntax
- Verify database connection
- Review error logs for specific issues

**Sample data issues:**
- Clear database and run setup again
- Check for foreign key constraint violations
- Verify character encoding (should be utf8mb4)

### Debug Mode
Enable debug mode in `includes/config.php`:
```php
define('DEBUG_MODE', true);
```

## 📝 Configuration

Database configuration is in `includes/config.php`:

```php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'bitversity');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

## 🚨 Production Notes

### Before Going Live:
1. **Remove setup script** or restrict access
2. **Change default passwords** for demo accounts
3. **Enable SSL** for database connections
4. **Set up monitoring** and alerting
5. **Configure backups** with retention policies
6. **Review and adjust** database user privileges

### Security Checklist:
- [ ] Remove or secure database setup script
- [ ] Change default demo account passwords
- [ ] Enable SSL/TLS for database connections
- [ ] Set up proper database user privileges
- [ ] Configure firewall rules for database access
- [ ] Enable query logging for security monitoring
- [ ] Set up automated security updates

## 📞 Support

For database-related issues:
1. Check this README for common solutions
2. Review application logs in `logs/` directory
3. Check MySQL/MariaDB error logs
4. Verify configuration in `includes/config.php`

---

🎓 **Bitversity Database System** - Powering digital education with robust, scalable data management.