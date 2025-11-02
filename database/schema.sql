-- GoSocially Database Schema
-- Invite-Only Login System with Messaging

CREATE DATABASE IF NOT EXISTS gosocially;
USE gosocially;

-- Users table for authentication and user management
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NULL,
    role ENUM('admin', 'moderator', 'user') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL,
    invited_by_id INT NULL,
    FOREIGN KEY (invited_by_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Invite codes table for invite-only registration
CREATE TABLE invite_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(32) UNIQUE NOT NULL,
    created_by_user_id INT NOT NULL,
    used_by_user_id INT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME NULL,
    expires_at DATETIME NULL,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (used_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Messages table for private messaging
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message_text TEXT NOT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create indexes for better performance
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_active ON users(is_active);
CREATE INDEX idx_invite_codes_code ON invite_codes(code);
CREATE INDEX idx_invite_codes_unused ON invite_codes(is_used, expires_at);
CREATE INDEX idx_messages_sender_receiver ON messages(sender_id, receiver_id);
CREATE INDEX idx_messages_receiver_unread ON messages(receiver_id, is_read);

-- User settings table for profile and privacy settings
CREATE TABLE user_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    messaging_permission ENUM('everyone', 'mutuals_only', 'approved_only') DEFAULT 'everyone',
    presence_status ENUM('active', 'busy', 'invisible') DEFAULT 'active',
    allow_follow_requests BOOLEAN DEFAULT TRUE,
    show_online_status BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Approved messaging table for whitelist system
CREATE TABLE approved_messaging (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    approved_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_approval (user_id, approved_user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CHECK (user_id != approved_user_id)
);

-- User relationships table for follow system
CREATE TABLE user_relationships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    follower_id INT NOT NULL,
    following_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_follow (follower_id, following_id),
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE,
    CHECK (follower_id != following_id)
);

-- Reports table for user reporting system
CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT NOT NULL,
    reported_user_id INT NOT NULL,
    report_type ENUM('harassment', 'spam', 'inappropriate_content', 'fake_account', 'other') NOT NULL,
    description TEXT NOT NULL,
    status ENUM('pending', 'under_review', 'resolved', 'dismissed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    resolved_by INT NULL,
    resolution_note TEXT NULL,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- User restrictions table for bans/timeouts and messaging restrictions
CREATE TABLE user_restrictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    restricted_user_id INT NULL, -- For messaging restrictions
    restriction_type ENUM('timeout', 'ban', 'messaging_restriction') NOT NULL,
    reason TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL, -- NULL for permanent bans
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (restricted_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Create indexes for new tables
CREATE INDEX idx_user_settings_user_id ON user_settings(user_id);
CREATE INDEX idx_approved_messaging_user_id ON approved_messaging(user_id);
CREATE INDEX idx_approved_messaging_approved_user_id ON approved_messaging(approved_user_id);
CREATE INDEX idx_user_relationships_follower ON user_relationships(follower_id);
CREATE INDEX idx_user_relationships_following ON user_relationships(following_id);
CREATE INDEX idx_reports_reporter ON reports(reporter_id);
CREATE INDEX idx_reports_reported_user ON reports(reported_user_id);
CREATE INDEX idx_reports_status ON reports(status);
CREATE INDEX idx_user_restrictions_user_id ON user_restrictions(user_id);
CREATE INDEX idx_user_restrictions_active ON user_restrictions(user_id, is_active);