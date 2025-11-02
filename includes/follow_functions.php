<?php
// Follow system functions

function followUser($followerId, $followingId) {
    global $pdo;

    // Prevent self-following
    if ($followerId == $followingId) {
        return false;
    }

    // Check if user allows follow requests
    $stmt = $pdo->prepare("SELECT allow_follow_requests FROM user_settings WHERE user_id = ?");
    $stmt->execute([$followingId]);
    $settings = $stmt->fetch();

    if (!$settings || !$settings['allow_follow_requests']) {
        throw new Exception("User does not allow follow requests");
    }

    // Check if already following
    $stmt = $pdo->prepare("SELECT id FROM user_relationships WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$followerId, $followingId]);
    $existing = $stmt->fetch();

    if ($existing) {
        throw new Exception("Already following this user");
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO user_relationships (follower_id, following_id) VALUES (?, ?)");
        return $stmt->execute([$followerId, $followingId]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            throw new Exception("Already following this user");
        }
        throw new Exception("Failed to follow user");
    }
}

function unfollowUser($followerId, $followingId) {
    global $pdo;

    $stmt = $pdo->prepare("DELETE FROM user_relationships WHERE follower_id = ? AND following_id = ?");
    return $stmt->execute([$followerId, $followingId]);
}

function areMutuals($user1Id, $user2Id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM user_relationships
        WHERE (follower_id = ? AND following_id = ?) OR (follower_id = ? AND following_id = ?)
    ");
    $stmt->execute([$user1Id, $user2Id, $user2Id, $user1Id]);
    $result = $stmt->fetch();

    return ($result['count'] ?? 0) >= 2; // Both follow each other
}

function getFollowers($userId, $limit = 50, $offset = 0) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.full_name, ur.created_at as followed_at
        FROM user_relationships ur
        JOIN users u ON ur.follower_id = u.id
        WHERE ur.following_id = ?
        ORDER BY ur.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$userId, $limit, $offset]);
    return $stmt->fetchAll();
}

function getFollowing($userId, $limit = 50, $offset = 0) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.full_name, ur.created_at as followed_at
        FROM user_relationships ur
        JOIN users u ON ur.following_id = u.id
        WHERE ur.follower_id = ?
        ORDER BY ur.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$userId, $limit, $offset]);
    return $stmt->fetchAll();
}

function isFollowing($followerId, $followingId) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT id FROM user_relationships WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$followerId, $followingId]);
    return $stmt->fetch() !== false;
}

function canUserMessage($senderId, $recipientId) {
    global $pdo;

    // Users can always message themselves
    if ($senderId == $recipientId) {
        return true;
    }

    // Get recipient's settings
    $stmt = $pdo->prepare("SELECT messaging_permission FROM user_settings WHERE user_id = ?");
    $stmt->execute([$recipientId]);
    $settings = $stmt->fetch();

    $permission = $settings['messaging_permission'] ?? 'everyone';

    // Admin and moderator override
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$senderId]);
    $sender = $stmt->fetch();

    if (in_array($sender['role'], ['admin', 'moderator'])) {
        return true;
    }

    // Check if sender is restricted by recipient
    if (isUserRestrictedFromMessaging($recipientId, $senderId)) {
        return false;
    }

    switch ($permission) {
        case 'everyone':
            return true;

        case 'mutuals_only':
            // Check if they follow each other
            return areMutuals($senderId, $recipientId);

        case 'approved_only':
            // Check if sender is on recipient's approved list
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM approved_messaging
                WHERE user_id = ? AND approved_user_id = ?"
            );
            $stmt->execute([$recipientId, $senderId]);
            $result = $stmt->fetch();
            return ($result['count'] ?? 0) > 0;

        default:
            return true;
    }
}

function isApprovedUser($recipientId, $senderId) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM approved_messaging
        WHERE user_id = ? AND approved_user_id = ?"
    );
    $stmt->execute([$recipientId, $senderId]);
    $result = $stmt->fetch();

    return ($result['count'] ?? 0) > 0;
}

function getFollowRequests($userId, $limit = 50, $offset = 0) {
    global $pdo;

    // Get users who follow this user but are not followed back
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.full_name, ur.created_at as request_date
        FROM user_relationships ur
        JOIN users u ON ur.follower_id = u.id
        WHERE ur.following_id = ?
        AND ur.follower_id NOT IN (
            SELECT following_id FROM user_relationships WHERE follower_id = ?
        )
        ORDER BY ur.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$userId, $userId, $limit, $offset]);
    return $stmt->fetchAll();
}

function acceptFollowRequest($userId, $followerId) {
    global $pdo;

    // Check if follower is actually following this user
    if (!isFollowing($followerId, $userId)) {
        throw new Exception("No follow request found");
    }

    // Follow back (making them mutuals)
    try {
        $stmt = $pdo->prepare("INSERT INTO user_relationships (follower_id, following_id) VALUES (?, ?)");
        return $stmt->execute([$userId, $followerId]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            // Already following, which is fine
            return true;
        }
        throw new Exception("Failed to accept follow request");
    }
}

function declineFollowRequest($userId, $followerId) {
    global $pdo;

    // Remove the follower relationship
    return unfollowUser($followerId, $userId);
}

function getFollowStats($userId) {
    global $pdo;

    // Get followers count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_relationships WHERE following_id = ?");
    $stmt->execute([$userId]);
    $followersCount = $stmt->fetch()['count'];

    // Get following count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_relationships WHERE follower_id = ?");
    $stmt->execute([$userId]);
    $followingCount = $stmt->fetch()['count'];

    // Get mutuals count
    $mutualsCount = 0;
    $followers = getFollowers($userId, 1000); // Get all followers
    foreach ($followers as $follower) {
        if (isFollowing($userId, $follower['id'])) {
            $mutualsCount++;
        }
    }

    return [
        'followers' => $followersCount,
        'following' => $followingCount,
        'mutuals' => $mutualsCount
    ];
}

function getRecentFollowActivity($userId, $limit = 10) {
    global $pdo;

    $stmt = $pdo->prepare("
        (SELECT
            u.id, u.username, u.full_name,
            ur.created_at as activity_date,
            'follower' as activity_type,
            'started following you' as activity_description
        FROM user_relationships ur
        JOIN users u ON ur.follower_id = u.id
        WHERE ur.following_id = ?
        )
        UNION ALL
        (SELECT
            u.id, u.username, u.full_name,
            ur.created_at as activity_date,
            'following' as activity_type,
            'you started following' as activity_description
        FROM user_relationships ur
        JOIN users u ON ur.following_id = u.id
        WHERE ur.follower_id = ?
        )
        ORDER BY activity_date DESC
        LIMIT ?
    ");
    $stmt->execute([$userId, $userId, $limit]);
    return $stmt->fetchAll();
}
?>