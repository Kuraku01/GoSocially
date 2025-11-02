-- GoSocially Seed Data
-- Pre-made test accounts with secure password hashes

USE gosocially;

-- Insert test accounts (password: password123 for all accounts)
-- Password hash for "password123" using PASSWORD_DEFAULT
INSERT INTO users (username, email, password_hash, full_name, role, is_active) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', TRUE),
('moderator', 'moderator@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Community Moderator', 'moderator', TRUE),
('user1', 'user1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test User One', 'user', TRUE),
('user2', 'user2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test User Two', 'user', TRUE),
('user3', 'user3@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test User Three', 'user', TRUE);

-- Generate some initial invite codes (valid for 7 days)
INSERT INTO invite_codes (code, created_by_user_id, expires_at) VALUES
('ABC123DEF456GHI789JKL012MNO345PQ', 1, DATE_ADD(NOW(), INTERVAL 7 DAY)),
('XYZ789ABC456DEF123GHI456JKL789MN', 2, DATE_ADD(NOW(), INTERVAL 7 DAY)),
('MNO345PQR678STU901VWX234YZA567BC', 3, DATE_ADD(NOW(), INTERVAL 7 DAY)),
('STU901VWX234YZA567BCD890EFG123HI', 4, DATE_ADD(NOW(), INTERVAL 7 DAY)),
('JKL012MNO345PQR678STU901VWX234YZ', 5, DATE_ADD(NOW(), INTERVAL 7 DAY));

-- Insert some sample messages for testing
INSERT INTO messages (sender_id, receiver_id, message_text, is_read) VALUES
(1, 2, 'Welcome to GoSocially! Let me know if you need help with anything.', TRUE),
(2, 1, 'Thanks! I''m excited to try out the messaging system.', TRUE),
(3, 4, 'Hey there! Want to test the chat functionality?', FALSE),
(4, 3, 'Sure! Let''s send some messages back and forth.', FALSE),
(1, 3, 'This is a great platform for social interaction.', TRUE),
(3, 1, 'I agree! The invite-only system keeps it exclusive.', FALSE);

-- Update last_login times for some users to simulate recent activity
UPDATE users SET last_login = DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE id IN (1, 2, 3);
UPDATE users SET last_login = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id IN (4, 5);