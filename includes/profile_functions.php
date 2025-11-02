<?php
// Profile and settings management functions

function getUserSettings($user_id) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch();

    // If no settings exist, return defaults
    if (!$settings) {
        return [
            'messaging_permission' => 'everyone',
            'presence_status' => 'active',
            'allow_follow_requests' => 1,
            'show_online_status' => 1
        ];
    }

    return $settings;
}

function updateUserSettings($user_id, $settings) {
    global $pdo;

    // Check if user settings already exist
    $stmt = $pdo->prepare("SELECT id FROM user_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $exists = $stmt->fetch();

    if ($exists) {
        // Update existing settings
        $stmt = $pdo->prepare("UPDATE user_settings SET
            messaging_permission = ?,
            presence_status = ?,
            allow_follow_requests = ?,
            show_online_status = ?
            WHERE user_id = ?");

        return $stmt->execute([
            $settings['messaging_permission'],
            $settings['presence_status'],
            $settings['allow_follow_requests'],
            $settings['show_online_status'],
            $user_id
        ]);
    } else {
        // Insert new settings
        $stmt = $pdo->prepare("INSERT INTO user_settings
            (user_id, messaging_permission, presence_status, allow_follow_requests, show_online_status)
            VALUES (?, ?, ?, ?, ?)");

        return $stmt->execute([
            $user_id,
            $settings['messaging_permission'],
            $settings['presence_status'],
            $settings['allow_follow_requests'],
            $settings['show_online_status']
        ]);
    }
}

function getFollowersCount($user_id) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_relationships WHERE following_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();

    return $result['count'] ?? 0;
}

function getFollowingCount($user_id) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_relationships WHERE follower_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();

    return $result['count'] ?? 0;
}

function canMessageUser($sender_id, $recipient_id) {
    global $pdo;

    // Users can always message themselves
    if ($sender_id == $recipient_id) {
        return true;
    }

    // Get recipient's settings
    $stmt = $pdo->prepare("SELECT messaging_permission FROM user_settings WHERE user_id = ?");
    $stmt->execute([$recipient_id]);
    $settings = $stmt->fetch();

    $permission = $settings['messaging_permission'] ?? 'everyone';

    // Admin and moderator override
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$sender_id]);
    $sender = $stmt->fetch();

    if (in_array($sender['role'], ['admin', 'moderator'])) {
        return true;
    }

    switch ($permission) {
        case 'everyone':
            return true;

        case 'mutuals_only':
            // Check if they follow each other
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM user_relationships r1, user_relationships r2
                WHERE r1.follower_id = ? AND r1.following_id = ?
                AND r2.follower_id = ? AND r2.following_id = ?"
            );
            $stmt->execute([$sender_id, $recipient_id, $recipient_id, $sender_id]);
            $result = $stmt->fetch();
            return ($result['count'] ?? 0) > 0;

        case 'approved_only':
            // Check if sender is on recipient's approved list
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM approved_messaging
                WHERE user_id = ? AND approved_user_id = ?"
            );
            $stmt->execute([$recipient_id, $sender_id]);
            $result = $stmt->fetch();
            return ($result['count'] ?? 0) > 0;

        default:
            return true;
    }
}

function addToApprovedList($user_id, $approved_user_id) {
    global $pdo;

    // Prevent adding self
    if ($user_id == $approved_user_id) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO approved_messaging (user_id, approved_user_id)
            VALUES (?, ?)"
        );
        return $stmt->execute([$user_id, $approved_user_id]);
    } catch (PDOException $e) {
        // Handle duplicate entry or other errors
        return false;
    }
}

function removeFromApprovedList($user_id, $approved_user_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        DELETE FROM approved_messaging
        WHERE user_id = ? AND approved_user_id = ?"
    );
    return $stmt->execute([$user_id, $approved_user_id]);
}

function getApprovedUsers($user_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.full_name, am.created_at
        FROM approved_messaging am
        JOIN users u ON am.approved_user_id = u.id
        WHERE am.user_id = ?
        ORDER BY u.username"
    );
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function initializeUserSettings($user_id) {
    global $pdo;

    // Check if settings already exist
    $stmt = $pdo->prepare("SELECT id FROM user_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);

    if (!$stmt->fetch()) {
        // Create default settings for new user
        $stmt = $pdo->prepare("
            INSERT INTO user_settings (user_id, messaging_permission, presence_status, allow_follow_requests, show_online_status)
            VALUES (?, 'everyone', 'active', 1, 1)"
        );
        return $stmt->execute([$user_id]);
    }

    return true;
}
?>