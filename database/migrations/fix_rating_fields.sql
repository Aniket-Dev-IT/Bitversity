-- ========================================
-- Bitversity Rating Field Standardization
-- ========================================
-- This migration fixes the inconsistencies between field names used
-- in PHP code and actual database schema for ratings and review counts.

-- Analysis of current schema (from schema.sql):
-- books table has: rating (DECIMAL), reviews_count (INT)
-- games table has: rating (DECIMAL), reviews_count (INT)  
-- projects table has: rating (DECIMAL), reviews_count (INT)
--
-- But PHP code expects: total_ratings field (which doesn't exist)

USE bitversity;

-- ========================================
-- Step 1: Add missing total_ratings fields
-- ========================================

-- Add total_ratings field to books table (alias for reviews_count)
-- We'll keep both fields for compatibility during migration
ALTER TABLE books 
ADD COLUMN total_ratings INT DEFAULT 0 NOT NULL AFTER rating;

-- Add total_ratings field to games table
ALTER TABLE games 
ADD COLUMN total_ratings INT DEFAULT 0 NOT NULL AFTER rating;

-- Add total_ratings field to projects table  
ALTER TABLE projects 
ADD COLUMN total_ratings INT DEFAULT 0 NOT NULL AFTER rating;

-- ========================================
-- Step 2: Copy existing reviews_count to total_ratings
-- ========================================

-- Copy existing reviews_count data to total_ratings for books
UPDATE books SET total_ratings = reviews_count WHERE reviews_count > 0;

-- Copy existing reviews_count data to total_ratings for games
UPDATE games SET total_ratings = reviews_count WHERE reviews_count > 0;

-- Copy existing reviews_count data to total_ratings for projects
UPDATE projects SET total_ratings = reviews_count WHERE reviews_count > 0;

-- ========================================
-- Step 3: Add indexes for performance
-- ========================================

-- Add indexes for the new total_ratings fields
ALTER TABLE books ADD INDEX idx_total_ratings (total_ratings);
ALTER TABLE games ADD INDEX idx_total_ratings (total_ratings);  
ALTER TABLE projects ADD INDEX idx_total_ratings (total_ratings);

-- ========================================
-- Step 4: Verification queries
-- ========================================

-- Verify the migration worked correctly
SELECT 'books' as table_name, 
       COUNT(*) as total_items,
       SUM(CASE WHEN rating > 0 THEN 1 ELSE 0 END) as items_with_rating,
       SUM(CASE WHEN reviews_count > 0 THEN 1 ELSE 0 END) as items_with_reviews_count,
       SUM(CASE WHEN total_ratings > 0 THEN 1 ELSE 0 END) as items_with_total_ratings,
       SUM(CASE WHEN reviews_count = total_ratings THEN 1 ELSE 0 END) as matching_counts
FROM books

UNION ALL

SELECT 'games' as table_name,
       COUNT(*) as total_items, 
       SUM(CASE WHEN rating > 0 THEN 1 ELSE 0 END) as items_with_rating,
       SUM(CASE WHEN reviews_count > 0 THEN 1 ELSE 0 END) as items_with_reviews_count,
       SUM(CASE WHEN total_ratings > 0 THEN 1 ELSE 0 END) as items_with_total_ratings,
       SUM(CASE WHEN reviews_count = total_ratings THEN 1 ELSE 0 END) as matching_counts
FROM games

UNION ALL

SELECT 'projects' as table_name,
       COUNT(*) as total_items,
       SUM(CASE WHEN rating > 0 THEN 1 ELSE 0 END) as items_with_rating, 
       SUM(CASE WHEN reviews_count > 0 THEN 1 ELSE 0 END) as items_with_reviews_count,
       SUM(CASE WHEN total_ratings > 0 THEN 1 ELSE 0 END) as items_with_total_ratings,
       SUM(CASE WHEN reviews_count = total_ratings THEN 1 ELSE 0 END) as matching_counts
FROM projects;

-- Show sample data to verify structure
SELECT 'BOOKS SAMPLE' as info;
SELECT id, title, rating, reviews_count, total_ratings FROM books LIMIT 5;

SELECT 'GAMES SAMPLE' as info;  
SELECT id, title, rating, reviews_count, total_ratings FROM games LIMIT 5;

SELECT 'PROJECTS SAMPLE' as info;
SELECT id, title, rating, reviews_count, total_ratings FROM projects LIMIT 5;

-- ========================================
-- Migration Complete Message  
-- ========================================
SELECT 'Migration completed successfully! Both reviews_count and total_ratings fields now exist.' as status;