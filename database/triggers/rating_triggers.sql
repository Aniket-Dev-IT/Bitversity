-- ========================================
-- Bitversity Rating Aggregation Triggers
-- ========================================
-- These triggers automatically update rating and review count fields
-- in books, games, and projects tables when reviews are modified.

USE bitversity;

-- Drop existing triggers if they exist
DROP TRIGGER IF EXISTS update_rating_after_review_insert;
DROP TRIGGER IF EXISTS update_rating_after_review_update;
DROP TRIGGER IF EXISTS update_rating_after_review_delete;

-- ========================================
-- TRIGGER: After Review Insert
-- ========================================
DELIMITER $$

CREATE TRIGGER update_rating_after_review_insert
AFTER INSERT ON reviews
FOR EACH ROW
BEGIN
    DECLARE avg_rating DECIMAL(3,2) DEFAULT 0.00;
    DECLARE review_count INT DEFAULT 0;
    DECLARE table_name VARCHAR(20);
    DECLARE update_query TEXT;
    
    -- Only process approved reviews (handle missing approval column)
    SET @approval_check = 1;
    
    -- Check if is_approved column exists
    SELECT COUNT(*) INTO @has_approval_col
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reviews'
    AND COLUMN_NAME = 'is_approved';
    
    IF @has_approval_col > 0 THEN
        SET @approval_check = NEW.is_approved;
    END IF;
    
    IF @approval_check = 1 THEN
        -- Calculate new aggregated values (with or without approval filter)
        IF @has_approval_col > 0 THEN
            SELECT 
                COALESCE(AVG(rating), 0.00),
                COUNT(*)
            INTO avg_rating, review_count
            FROM reviews 
            WHERE item_type = NEW.item_type 
                AND item_id = NEW.item_id 
                AND is_approved = 1;
        ELSE
            SELECT 
                COALESCE(AVG(rating), 0.00),
                COUNT(*)
            INTO avg_rating, review_count
            FROM reviews 
            WHERE item_type = NEW.item_type 
                AND item_id = NEW.item_id;
        END IF;
        
        -- Update the appropriate table based on item_type
        CASE NEW.item_type
            WHEN 'book' THEN
                UPDATE books 
                SET rating = avg_rating,
                    reviews_count = review_count,
                    total_ratings = review_count
                WHERE id = NEW.item_id;
                
            WHEN 'game' THEN
                UPDATE games 
                SET rating = avg_rating,
                    reviews_count = review_count,
                    total_ratings = review_count
                WHERE id = NEW.item_id;
                
            WHEN 'project' THEN
                UPDATE projects 
                SET rating = avg_rating,
                    reviews_count = review_count,
                    total_ratings = review_count
                WHERE id = NEW.item_id;
        END CASE;
    END IF;
END$$

DELIMITER ;

-- ========================================
-- TRIGGER: After Review Update
-- ========================================
DELIMITER $$

CREATE TRIGGER update_rating_after_review_update
AFTER UPDATE ON reviews
FOR EACH ROW
BEGIN
    DECLARE avg_rating DECIMAL(3,2) DEFAULT 0.00;
    DECLARE review_count INT DEFAULT 0;
    
    -- Check if the review status or rating changed (handle missing approval column)
    SET @should_update = 0;
    
    -- Check if is_approved column exists
    SELECT COUNT(*) INTO @has_approval_col
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reviews'
    AND COLUMN_NAME = 'is_approved';
    
    IF @has_approval_col > 0 THEN
        IF OLD.rating != NEW.rating OR OLD.is_approved != NEW.is_approved THEN
            SET @should_update = 1;
        END IF;
    ELSE
        IF OLD.rating != NEW.rating THEN
            SET @should_update = 1;
        END IF;
    END IF;
    
    IF @should_update = 1 THEN
        -- Calculate new aggregated values (with or without approval filter)
        IF @has_approval_col > 0 THEN
            SELECT 
                COALESCE(AVG(rating), 0.00),
                COUNT(*)
            INTO avg_rating, review_count
            FROM reviews 
            WHERE item_type = NEW.item_type 
                AND item_id = NEW.item_id 
                AND is_approved = 1;
        ELSE
            SELECT 
                COALESCE(AVG(rating), 0.00),
                COUNT(*)
            INTO avg_rating, review_count
            FROM reviews 
            WHERE item_type = NEW.item_type 
                AND item_id = NEW.item_id;
        END IF;
        
        -- Update the appropriate table based on item_type
        CASE NEW.item_type
            WHEN 'book' THEN
                UPDATE books 
                SET rating = avg_rating,
                    reviews_count = review_count,
                    total_ratings = review_count
                WHERE id = NEW.item_id;
                
            WHEN 'game' THEN
                UPDATE games 
                SET rating = avg_rating,
                    reviews_count = review_count,
                    total_ratings = review_count
                WHERE id = NEW.item_id;
                
            WHEN 'project' THEN
                UPDATE projects 
                SET rating = avg_rating,
                    reviews_count = review_count,
                    total_ratings = review_count
                WHERE id = NEW.item_id;
        END CASE;
    END IF;
END$$

DELIMITER ;

-- ========================================
-- TRIGGER: After Review Delete
-- ========================================
DELIMITER $$

CREATE TRIGGER update_rating_after_review_delete
AFTER DELETE ON reviews
FOR EACH ROW
BEGIN
    DECLARE avg_rating DECIMAL(3,2) DEFAULT 0.00;
    DECLARE review_count INT DEFAULT 0;
    
    -- Only process if the deleted review was approved (handle missing approval column)
    SET @should_process = 1;
    
    -- Check if is_approved column exists
    SELECT COUNT(*) INTO @has_approval_col
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reviews'
    AND COLUMN_NAME = 'is_approved';
    
    IF @has_approval_col > 0 THEN
        SET @should_process = OLD.is_approved;
    END IF;
    
    IF @should_process = 1 THEN
        -- Calculate new aggregated values (with or without approval filter)
        IF @has_approval_col > 0 THEN
            SELECT 
                COALESCE(AVG(rating), 0.00),
                COUNT(*)
            INTO avg_rating, review_count
            FROM reviews 
            WHERE item_type = OLD.item_type 
                AND item_id = OLD.item_id 
                AND is_approved = 1;
        ELSE
            SELECT 
                COALESCE(AVG(rating), 0.00),
                COUNT(*)
            INTO avg_rating, review_count
            FROM reviews 
            WHERE item_type = OLD.item_type 
                AND item_id = OLD.item_id;
        END IF;
        
        -- Update the appropriate table based on item_type
        CASE OLD.item_type
            WHEN 'book' THEN
                UPDATE books 
                SET rating = avg_rating,
                    reviews_count = review_count,
                    total_ratings = review_count
                WHERE id = OLD.item_id;
                
            WHEN 'game' THEN
                UPDATE games 
                SET rating = avg_rating,
                    reviews_count = review_count,
                    total_ratings = review_count
                WHERE id = OLD.item_id;
                
            WHEN 'project' THEN
                UPDATE projects 
                SET rating = avg_rating,
                    reviews_count = review_count,
                    total_ratings = review_count
                WHERE id = OLD.item_id;
        END CASE;
    END IF;
END$$

DELIMITER ;

-- ========================================
-- Verification: List all triggers
-- ========================================
SHOW TRIGGERS WHERE `Table` = 'reviews';

-- ========================================
-- Test Data Setup (Optional)
-- ========================================
-- You can uncomment these to test the triggers work correctly

/*
-- Test INSERT trigger
INSERT INTO reviews (user_id, item_type, item_id, rating, comment, is_approved) 
VALUES (1, 'book', 1, 5, 'Test review for trigger', 1);

-- Test UPDATE trigger  
UPDATE reviews SET rating = 4 WHERE user_id = 1 AND item_type = 'book' AND item_id = 1;

-- Test DELETE trigger
DELETE FROM reviews WHERE user_id = 1 AND item_type = 'book' AND item_id = 1;
*/

-- ========================================
-- Success Message
-- ========================================
SELECT 'Rating aggregation triggers created successfully!' as status,
       'Reviews will now automatically update item ratings and counts' as info;