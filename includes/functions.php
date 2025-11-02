<?php
// Utility functions for GoSocially
// Common helper functions

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

// Function to get user by ID
function getUserById($userId) {
    $sql = "SELECT id, username, email, full_name, role, is_active, created_at, last_login
            FROM users
            WHERE id = ? AND is_active = 1";

    return dbGetRow($sql, [$userId]);
}

// Function to get all active users
function getAllActiveUsers() {
    $sql = "SELECT id, username, full_name, role, last_login
            FROM users
            WHERE is_active = 1
            ORDER BY username ASC";

    return dbGetAll($sql);
}

// Function to generate invite code
function generateInviteCode($userId) {
    $code = generateRandomString(32);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

    $sql = "INSERT INTO invite_codes (code, created_by_user_id, expires_at)
            VALUES (?, ?, ?)";

    if (dbExecute($sql, [$code, $userId, $expiresAt])) {
        return $code;
    }

    throw new Exception("Failed to generate invite code");
}

// Function to get user's invite codes
function getUserInviteCodes($userId) {
    $sql = "SELECT ic.*, u.username as used_by_username
            FROM invite_codes ic
            LEFT JOIN users u ON ic.used_by_user_id = u.id
            WHERE ic.created_by_user_id = ?
            ORDER BY ic.created_at DESC";

    return dbGetAll($sql, [$userId]);
}

// Function to validate invite code
function validateInviteCode($code) {
    $sql = "SELECT ic.*, u.username as created_by_username
            FROM invite_codes ic
            JOIN users u ON ic.created_by_user_id = u.id
            WHERE ic.code = ? AND ic.is_used = 0
            AND (ic.expires_at IS NULL OR ic.expires_at > NOW())
            LIMIT 1";

    return dbGetRow($sql, [$code]);
}

// Function to mark invite code as used
function useInviteCode($code, $userId) {
    $sql = "UPDATE invite_codes
            SET is_used = 1, used_by_user_id = ?, used_at = NOW()
            WHERE code = ?";

    return dbExecute($sql, [$userId, $code]);
}

// Function to register new user
function registerUser($username, $email, $password, $fullName, $inviteCode, $invitedByUserId) {
    // Validate input
    if (empty($username) || empty($email) || empty($password) || empty($inviteCode)) {
        throw new Exception("All fields are required");
    }

    if (!isValidEmail($email)) {
        throw new Exception("Invalid email format");
    }

    if (!isValidPassword($password)) {
        throw new Exception("Password must be at least 8 characters and contain uppercase, lowercase, and numbers");
    }

    // Validate invite code
    $inviteData = validateInviteCode($inviteCode);
    if (!$inviteData) {
        throw new Exception("Invalid or expired invite code");
    }

    // Check if username or email already exists
    $existing = dbGetRow(
        "SELECT id FROM users WHERE username = ? OR email = ?",
        [$username, $email]
    );

    if ($existing) {
        throw new Exception("Username or email already exists");
    }

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Start transaction
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    try {
        // Insert new user
        $sql = "INSERT INTO users (username, email, password_hash, full_name, invited_by_id)
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $db->prepare($sql);
        $stmt->execute([$username, $email, $passwordHash, $fullName, $invitedByUserId]);
        $newUserId = $db->lastInsertId();

        // Mark invite code as used
        useInviteCode($inviteCode, $newUserId);

        // Commit transaction
        $db->commit();

        return $newUserId;

    } catch (Exception $e) {
        // Rollback transaction
        $db->rollback();
        throw new Exception("Registration failed: " . $e->getMessage());
    }
}

// Function to send message
function sendMessage($senderId, $receiverId, $messageText) {
    if (empty(trim($messageText))) {
        throw new Exception("Message cannot be empty");
    }

    // Verify receiver exists and is active
    $receiver = getUserById($receiverId);
    if (!$receiver) {
        throw new Exception("Invalid recipient");
    }

    $sql = "INSERT INTO messages (sender_id, receiver_id, message_text)
            VALUES (?, ?, ?)";

    if (dbExecute($sql, [$senderId, $receiverId, $messageText])) {
        return dbLastInsertId();
    }

    throw new Exception("Failed to send message");
}

// Function to get messages between two users
function getMessages($userId1, $userId2, $limit = 50, $offset = 0) {
    $sql = "SELECT m.*,
                   u1.username as sender_username,
                   u1.full_name as sender_full_name,
                   u2.username as receiver_username,
                   u2.full_name as receiver_full_name
            FROM messages m
            JOIN users u1 ON m.sender_id = u1.id
            JOIN users u2 ON m.receiver_id = u2.id
            WHERE (m.sender_id = ? AND m.receiver_id = ?)
               OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.sent_at DESC
            LIMIT ? OFFSET ?";

    $messages = dbGetAll($sql, [$userId1, $userId2, $userId2, $userId1, $limit, $offset]);

    // Reverse to show oldest first
    return array_reverse($messages);
}

// Function to get conversations for a user
function getUserConversations($userId) {
    $sql = "SELECT DISTINCT
                   CASE
                       WHEN m.sender_id = ? THEN m.receiver_id
                       ELSE m.sender_id
                   END as other_user_id,
                   u.username,
                   u.full_name,
                   u.last_login,
                   MAX(m.sent_at) as last_message_time,
                   (SELECT COUNT(*) FROM messages
                    WHERE ((sender_id = ? AND receiver_id = other_user_id) OR
                           (sender_id = other_user_id AND receiver_id = ?))
                    AND is_read = 0
                    AND sender_id != ?) as unread_count
            FROM messages m
            JOIN users u ON (CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END) = u.id
            WHERE (m.sender_id = ? OR m.receiver_id = ?)
            AND u.is_active = 1
            GROUP BY other_user_id, u.username, u.full_name, u.last_login
            ORDER BY last_message_time DESC";

    return dbGetAll($sql, [$userId, $userId, $userId, $userId, $userId, $userId, $userId]);
}

// Function to mark messages as read
function markMessagesAsRead($senderId, $receiverId) {
    $sql = "UPDATE messages
            SET is_read = 1
            WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";

    return dbExecute($sql, [$senderId, $receiverId]);
}

// Function to get unread message count
function getUnreadMessageCount($userId) {
    $sql = "SELECT COUNT(*) as count
            FROM messages
            WHERE receiver_id = ? AND is_read = 0";

    $result = dbGetRow($sql, [$userId]);
    return $result ? (int)$result['count'] : 0;
}

// Function to format date/time
function formatDateTime($dateTime) {
    if (empty($dateTime)) {
        return 'Never';
    }

    $timestamp = strtotime($dateTime);
    $now = time();
    $diff = $now - $timestamp;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}

// Function to check if user is online (last activity within 5 minutes)
function isUserOnline($lastLogin) {
    if (empty($lastLogin)) {
        return false;
    }

    $lastLoginTime = strtotime($lastLogin);
    $fiveMinutesAgo = time() - 300; // 5 minutes

    return $lastLoginTime > $fiveMinutesAgo;
}
?>