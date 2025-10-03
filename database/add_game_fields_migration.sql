-- Migration to add game-specific fields to custom_order_requests table
-- Run this after the initial table creation

-- Add game-specific columns
ALTER TABLE custom_order_requests 
ADD COLUMN game_type VARCHAR(50) NULL COMMENT 'Type of game: quiz, puzzle, simulation, etc.',
ADD COLUMN target_age VARCHAR(20) NULL COMMENT 'Target age group for the game',
ADD COLUMN difficulty_level VARCHAR(20) NULL COMMENT 'Difficulty level: beginner, intermediate, advanced, adaptive',
ADD COLUMN deadline DATE NULL COMMENT 'Hard deadline for project completion';

-- Add indexes for better performance
CREATE INDEX idx_custom_orders_type ON custom_order_requests(type);
CREATE INDEX idx_custom_orders_game_type ON custom_order_requests(game_type);
CREATE INDEX idx_custom_orders_target_age ON custom_order_requests(target_age);
CREATE INDEX idx_custom_orders_status ON custom_order_requests(status);

-- Add some sample data for testing (optional - remove if not needed)
-- INSERT INTO custom_order_requests (
--     user_id, type, title, description, technical_requirements, 
--     budget_range, timeline, status, game_type, target_age, difficulty_level,
--     created_at, updated_at
-- ) VALUES 
-- (1, 'game', 'Math Adventure Quest', 'An educational math game for kids', 'HTML5, responsive design', '600-1200', '2-4 weeks', 'pending', 'adventure', '7-12', 'intermediate', NOW(), NOW()),
-- (1, 'project', 'Learning Management System', 'Custom LMS for school district', 'PHP, MySQL, Bootstrap', '2500-5000', '1-2 months', 'pending', NULL, NULL, NULL, NOW(), NOW());