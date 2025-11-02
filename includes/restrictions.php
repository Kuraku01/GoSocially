<?php
// User messaging restrictions functions

function restrictUserFromMessaging($userId, $restrictedUserId) {
    global $pdo;

    // Prevent self-restriction
    if ($userId == $restrictedUserId) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO user_restrictions
            (user_id, restricted_user_id, restriction_type, reason, created_by)
            VALUES (?, ?, 'messaging_restriction', 'User blocked from messaging', ?)
        ");
        return $stmt->execute([$userId, $restrictedUserId, $userId]);
    } catch (PDOException $e) {
        // Handle duplicate entry or other errors
        return false;
    }
}

function unrestrictUserFromMessaging($userId, $restrictedUserId) {
    global $pdo;

    $stmt = $pdo->prepare("
        UPDATE user_restrictions
        SET is_active = 0
        WHERE user_id = ? AND restricted_user_id = ? AND restriction_type = 'messaging_restriction'
    ");
    return $stmt->execute([$userId, $restrictedUserId]);
}

function isUserRestrictedFromMessaging($userId, $restrictedUserId) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM user_restrictions
        WHERE user_id = ? AND restricted_user_id = ?
        AND restriction_type = 'messaging_restriction'
        AND is_active = 1
    ");
    $stmt->execute([$userId, $restrictedUserId]);
    $result = $stmt->fetch();

    return ($result['count'] ?? 0) > 0;
}

function getBlockedUsers($userId, $limit = 50, $offset = 0) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.full_name, ur.created_at as blocked_at, ur.reason
        FROM user_restrictions ur
        JOIN users u ON ur.restricted_user_id = u.id
        WHERE ur.user_id = ? AND ur.restriction_type = 'messaging_restriction'
        AND ur.is_active = 1
        ORDER BY ur.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$userId, $limit, $offset]);
    return $stmt->fetchAll();
}

function getUsersWhoBlockedMe($userId, $limit = 50, $offset = 0) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.full_name, ur.created_at as blocked_at
        FROM user_restrictions ur
        JOIN users u ON ur.user_id = u.id
        WHERE ur.restricted_user_id = ? AND ur.restriction_type = 'messaging_restriction'
        AND ur.is_active = 1
        ORDER BY ur.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$userId, $limit, $offset]);
    return $stmt->fetchAll();
}

function canSendMessageToUser($senderId, $recipientId) {
    // First check if recipient has blocked sender
    if (isUserRestrictedFromMessaging($recipientId, $senderId)) {
        return false;
    }

    // Then check general messaging permissions
    return canUserMessage($senderId, $recipientId);
}

function addUserToMessagingBlockList($userId, $blockedUserId) {
    return restrictUserFromMessaging($userId, $blockedUserId);
}

function removeUserFromMessagingBlockList($userId, $blockedUserId) {
    return unrestrictUserFromMessaging($userId, $blockedUserId);
}

function isUserInBlockList($userId, $blockedUserId) {
    return isUserRestrictedFromMessaging($userId, $blockedUserId);
}

function getBlockListStats($userId) {
    global $pdo;

    // Get count of users blocked by this user
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM user_restrictions
        WHERE user_id = ? AND restriction_type = 'messaging_restriction'
        AND is_active = 1
    ");
    $stmt->execute([$userId]);
    $blockedCount = $stmt->fetch()['count'];

    // Get count of users who blocked this user
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM user_restrictions
        WHERE restricted_user_id = ? AND restriction_type = 'messaging_restriction'
        AND is_active = 1
    ");
    $stmt->execute([$userId]);
    $blockedByCount = $stmt->fetch()['count'];

    return [
        'blocked_by_me' => $blockedCount,
        'blocked_me' => $blockedByCount
    ];
}

function getBlockedUsersWithDetails($userId, $limit = 50) {
    global $pdo;

    $blockedUsers = getBlockedUsers($userId, $limit);
    $detailedList = [];

    foreach ($blockedUsers as $user) {
        $detailedList[] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'blocked_at' => $user['blocked_at'],
            'reason' => $user['reason'],
            'is_online' => isUserOnline($user['id']),
            'presence_status' => getUserPresenceStatus($user['id'])
        ];
    }

    return $detailedList;
}

function checkMessagingRestrictions($senderId, $recipientId) {
    $restrictions = [];

    // Check if recipient blocked sender
    if (isUserRestrictedFromMessaging($recipientId, $senderId)) {
        $restrictions[] = 'recipient_blocked_sender';
    }

    // Check if sender is on recipient's approved list (when applicable)
    $recipientSettings = getUserSettings($recipientId);
    if ($recipientSettings['messaging_permission'] === 'approved_only') {
        if (!isApprovedUser($recipientId, $senderId)) {
            $restrictions[] = 'not_approved_by_recipient';
        }
    }

    // Check mutual requirement
    if ($recipientSettings['messaging_permission'] === 'mutuals_only') {
        if (!areMutuals($senderId, $recipientId)) {
            $restrictions[] = 'not_mutuals_with_recipient';
        }
    }

    return $restrictions;
}

function getMessagingRestrictionReason($senderId, $recipientId) {
    $restrictions = checkMessagingRestrictions($senderId, $recipientId);

    if (empty($restrictions)) {
        return null; // No restrictions
    }

    $reasons = [
        'recipient_blocked_sender' => 'You have been blocked by this user',
        'not_approved_by_recipient' => 'You are not on this user\'s approved messaging list',
        'not_mutuals_with_recipient' => 'You must be mutual followers to message this user'
    ];

    return $reasons[$restrictions[0]] ?? 'Messaging not allowed';
}
?>