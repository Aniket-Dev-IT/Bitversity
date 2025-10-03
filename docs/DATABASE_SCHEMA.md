# 🗄️ Database Schema Documentation

## Overview

Bitversity uses a MySQL database with a well-structured relational schema designed for scalability, performance, and data integrity.

## Core Tables

### 👥 Users Management

#### `users` Table
Stores user account information and authentication data.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | Unique user identifier |
| `full_name` | VARCHAR(255) | User's full name |
| `email` | VARCHAR(255) | Unique email address |
| `password` | VARCHAR(255) | Hashed password |
| `role` | ENUM | 'user' or 'admin' |
| `is_active` | BOOLEAN | Account status |
| `email_verified` | BOOLEAN | Email verification status |
| `created_at` | TIMESTAMP | Account creation date |
| `updated_at` | TIMESTAMP | Last modification date |

**Indexes:**
- Primary key on `id`
- Unique index on `email`
- Index on `role`, `is_active`

### 📂 Content Management

#### `categories` Table
Organizes content into categories.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | Category identifier |
| `name` | VARCHAR(100) | Category name |
| `slug` | VARCHAR(100) | URL-friendly slug |
| `description` | TEXT | Category description |
| `icon` | VARCHAR(50) | FontAwesome icon class |
| `color` | VARCHAR(7) | Hex color code |
| `is_active` | BOOLEAN | Visibility status |
| `sort_order` | INT | Display order |

#### `books` Table
Stores book information and metadata.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | Book identifier |
| `title` | VARCHAR(255) | Book title |
| `slug` | VARCHAR(255) | URL slug |
| `author` | VARCHAR(255) | Author name |
| `description` | TEXT | Book description |
| `price` | DECIMAL(10,2) | Book price |
| `category_id` | INT (FK) | Reference to categories |
| `cover_image` | VARCHAR(255) | Cover image path |
| `isbn` | VARCHAR(20) | ISBN number |
| `rating` | DECIMAL(3,2) | Average rating (0-5) |
| `total_ratings` | INT | Total number of ratings |
| `is_active` | BOOLEAN | Publication status |
| `is_featured` | BOOLEAN | Featured status |

**Key Features:**
- Full-text search on title, author, description
- Automatic rating calculation via triggers
- JSON column for tags
- File size and download tracking

#### `projects` Table
Stores coding project information.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | Project identifier |
| `title` | VARCHAR(255) | Project title |
| `slug` | VARCHAR(255) | URL slug |
| `description` | TEXT | Project description |
| `price` | DECIMAL(10,2) | Project price |
| `category_id` | INT (FK) | Reference to categories |
| `cover_image` | VARCHAR(255) | Screenshot/image |
| `difficulty_level` | ENUM | beginner/intermediate/advanced |
| `estimated_hours` | INT | Time to complete |
| `technologies` | JSON | Technology stack |
| `demo_url` | VARCHAR(500) | Live demo URL |
| `rating` | DECIMAL(3,2) | Average rating |
| `total_ratings` | INT | Total ratings |

#### `games` Table
Stores educational game information.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | Game identifier |
| `title` | VARCHAR(255) | Game title |
| `slug` | VARCHAR(255) | URL slug |
| `description` | TEXT | Game description |
| `price` | DECIMAL(10,2) | Game price |
| `category_id` | INT (FK) | Reference to categories |
| `thumbnail` | VARCHAR(255) | Game thumbnail |
| `genre` | VARCHAR(100) | Game genre |
| `platform` | ENUM | web/mobile/desktop |
| `min_age` | INT | Minimum age |
| `rating` | DECIMAL(3,2) | Average rating |
| `total_ratings` | INT | Total ratings |

### 💬 Review System

#### `reviews` Table
Stores user reviews and ratings.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | Review identifier |
| `user_id` | INT (FK) | User who wrote review |
| `item_type` | ENUM | book/project/game |
| `item_id` | INT | Referenced item ID |
| `rating` | INT | Rating (1-5) |
| `comment` | TEXT | Review text |
| `is_approved` | BOOLEAN | Moderation status |
| `created_at` | TIMESTAMP | Review date |

**Constraints:**
- One review per user per item
- Rating must be between 1 and 5
- Automatic rating recalculation triggers

### 🛒 E-commerce System

#### `cart` Table
User shopping cart items.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | Cart item identifier |
| `user_id` | INT (FK) | Cart owner |
| `item_type` | ENUM | book/project/game |
| `item_id` | INT | Referenced item |
| `quantity` | INT | Item quantity |
| `added_at` | TIMESTAMP | When added to cart |

#### `orders` Table
Purchase order information.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | Order identifier |
| `user_id` | INT (FK) | Customer |
| `total_amount` | DECIMAL(10,2) | Order total |
| `status` | ENUM | pending/completed/cancelled |
| `payment_status` | ENUM | pending/completed/failed |
| `billing_name` | VARCHAR(255) | Billing name |
| `billing_email` | VARCHAR(255) | Billing email |
| `created_at` | TIMESTAMP | Order date |

#### `order_items` Table
Individual items in orders.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | Order item identifier |
| `order_id` | INT (FK) | Parent order |
| `item_type` | ENUM | book/project/game |
| `item_id` | INT | Referenced item |
| `title` | VARCHAR(255) | Item title at purchase |
| `price` | DECIMAL(10,2) | Price at purchase |
| `quantity` | INT | Quantity ordered |

## Database Triggers

### Rating Calculation Triggers
Automatically update average ratings when reviews are added, updated, or deleted.

```sql
-- Update ratings after review insert
DELIMITER $$
CREATE TRIGGER update_ratings_after_review_insert
AFTER INSERT ON reviews
FOR EACH ROW
BEGIN
    CALL update_item_rating(NEW.item_type, NEW.item_id);
END$$

-- Update ratings after review update  
CREATE TRIGGER update_ratings_after_review_update
AFTER UPDATE ON reviews
FOR EACH ROW
BEGIN
    CALL update_item_rating(NEW.item_type, NEW.item_id);
END$$

-- Update ratings after review delete
CREATE TRIGGER update_ratings_after_review_delete
AFTER DELETE ON reviews
FOR EACH ROW
BEGIN
    CALL update_item_rating(OLD.item_type, OLD.item_id);
END$$
```

## Indexes and Performance

### Key Indexes
- **Full-text indexes** on searchable content (titles, descriptions)
- **Composite indexes** for common query patterns
- **Foreign key indexes** for join performance
- **Unique indexes** for data integrity

### Query Optimization
- Use of prepared statements throughout application
- Efficient pagination with LIMIT/OFFSET
- Proper JOIN strategies for related data
- Caching layer for frequently accessed data

## Data Integrity

### Foreign Key Constraints
- `CASCADE` delete for dependent records
- `SET NULL` for optional relationships
- Referential integrity maintained across all tables

### Data Validation
- `CHECK` constraints for valid ranges (ratings 1-5)
- `ENUM` types for controlled vocabularies
- `NOT NULL` constraints on required fields
- Unique constraints preventing duplicates

## Backup and Migration

### Backup Strategy
```bash
# Daily full backup
mysqldump -u root -p bitversity > backup_$(date +%Y%m%d).sql

# Incremental backup using binary logs
mysqlbinlog --start-datetime="2024-01-01 00:00:00" /var/log/mysql/mysql-bin.000001
```

### Migration Scripts
Located in `/database/migrations/` directory with versioned SQL files:
- `001_initial_schema.sql` - Base schema
- `002_add_rating_triggers.sql` - Rating system
- `003_add_full_text_search.sql` - Search improvements

## Security Considerations

### Data Protection
- Passwords hashed with `password_hash()`
- Sensitive data encrypted where appropriate
- Input validation and sanitization
- SQL injection prevention with prepared statements

### Access Control
- Role-based permissions (user/admin)
- Session-based authentication
- CSRF protection on all forms
- Rate limiting on API endpoints

## Monitoring and Analytics

### Performance Monitoring
```sql
-- Monitor slow queries
SELECT * FROM mysql.slow_log 
WHERE start_time > DATE_SUB(NOW(), INTERVAL 1 DAY);

-- Check table sizes
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.TABLES 
WHERE table_schema = 'bitversity'
ORDER BY (data_length + index_length) DESC;
```

### Usage Analytics
- User activity tracking in `activity_log` table
- Content popularity metrics
- Search query analytics
- Revenue and order tracking

## Future Enhancements

### Planned Improvements
- **Elasticsearch** integration for advanced search
- **Redis** caching layer for better performance
- **Read replicas** for scaling read operations
- **Partitioning** for large tables
- **Data archiving** strategy for old records