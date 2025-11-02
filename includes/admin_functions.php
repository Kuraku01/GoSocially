<?php
// Admin moderation functions

function timeoutUser($userId, $durationMinutes, $reason, $adminId) {
    global $pdo;

    // Validate inputs
    if ($userId <= 0 || $adminId <= 0) {
        throw new Exception("Invalid user ID");
    }

    if ($durationMinutes < 1 || $durationMinutes > 525600) { // Max 1 year
        throw new Exception("Duration must be between 1 minute and 1 year");
    }

    if (empty(trim($reason))) {
        throw new Exception("Reason is required");
    }

    // Check if admin has permission
    $admin = getUserById($adminId);
    if (!$admin || !in_array($admin['role'], ['admin', 'moderator'])) {
        throw new Exception("Insufficient permissions");
    }

    // Target user cannot be admin or moderator (except by admin)
    $targetUser = getUserById($userId);
    if (!$targetUser) {
        throw new Exception("User not found");
    }

    if (in_array($targetUser['role'], ['admin', 'moderator']) && $admin['role'] !== 'admin') {
        throw new Exception("Cannot restrict admin or moderator users");
    }

    // Calculate expiration time
    $expiresAt = date('Y-m-d H:i:s', strtotime("+$durationMinutes minutes"));

    try {
        $pdo->beginTransaction();

        // Deactivate any existing restrictions
        $stmt = $pdo->prepare("
            UPDATE user_restrictions
            SET is_active = 0
            WHERE user_id = ? AND restriction_type IN ('timeout', 'ban')
            AND is_active = 1
        ");
        $stmt->execute([$userId]);

        // Create new timeout restriction
        $stmt = $pdo->prepare("
            INSERT INTO user_restrictions
            (user_id, restriction_type, reason, created_by, expires_at, is_active)
            VALUES (?, 'timeout', ?, ?, ?, 1)
        ");
        $stmt->execute([$userId, $reason, $adminId, $expiresAt]);

        $restrictionId = $pdo->lastInsertId();
        $pdo->commit();

        return $restrictionId;
    } catch (Exception $e) {
        $pdo->rollback();
        throw new Exception("Failed to apply timeout: " . $e->getMessage());
    }
}

function banUser($userId, $reason, $adminId) {
    global $pdo;

    // Validate inputs
    if ($userId <= 0 || $adminId <= 0) {
        throw new Exception("Invalid user ID");
    }

    if (empty(trim($reason))) {
        throw new Exception("Reason is required");
    }

    // Check if admin has permission
    $admin = getUserById($adminId);
    if (!$admin || $admin['role'] !== 'admin') {
        throw new Exception("Only admins can ban users");
    }

    // Target user cannot be admin
    $targetUser = getUserById($userId);
    if (!$targetUser) {
        throw new Exception("User not found");
    }

    if ($targetUser['role'] === 'admin') {
        throw new Exception("Cannot ban admin users");
    }

    try {
        $pdo->beginTransaction();

        // Deactivate any existing restrictions
        $stmt = $pdo->prepare("
            UPDATE user_restrictions
            SET is_active = 0
            WHERE user_id = ? AND restriction_type IN ('timeout', 'ban')
            AND is_active = 1
        ");
        $stmt->execute([$userId]);

        // Create new ban restriction (permanent - expires_at is NULL)
        $stmt = $pdo->prepare("
            INSERT INTO user_restrictions
            (user_id, restriction_type, reason, created_by, expires_at, is_active)
            VALUES (?, 'ban', ?, ?, NULL, 1)
        ");
        $stmt->execute([$userId, $reason, $adminId]);

        $restrictionId = $pdo->lastInsertId();

        // Mark user as inactive
        $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
        $stmt->execute([$userId]);

        $pdo->commit();

        return $restrictionId;
    } catch (Exception $e) {
        $pdo->rollback();
        throw new Exception("Failed to apply ban: " . $e->getMessage());
    }
}

function removeUserRestriction($userId, $adminId) {
    global $pdo;

    // Check if admin has permission
    $admin = getUserById($adminId);
    if (!$admin || !in_array($admin['role'], ['admin', 'moderator'])) {
        throw new Exception("Insufficient permissions");
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE user_restrictions
            SET is_active = 0
            WHERE user_id = ? AND restriction_type IN ('timeout', 'ban')
            AND is_active = 1
        ");
        $result = $stmt->execute([$userId]);

        // Reactivate user if they were banned
        if ($result) {
            $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
            $stmt->execute([$userId]);
        }

        return $result;
    } catch (Exception $e) {
        throw new Exception("Failed to remove restriction: " . $e->getMessage());
    }
}

function isUserRestricted($userId) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM user_restrictions
        WHERE user_id = ? AND restriction_type IN ('timeout', 'ban')
        AND is_active = 1
        AND (expires_at IS NULL OR expires_at > NOW())
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();

    return ($result['count'] ?? 0) > 0;
}

function getActiveRestrictions($userId) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT ur.*, u.username as admin_username, u.full_name as admin_full_name
        FROM user_restrictions ur
        LEFT JOIN users u ON ur.created_by = u.id
        WHERE ur.user_id = ? AND ur.restriction_type IN ('timeout', 'ban')
        AND ur.is_active = 1
        AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
        ORDER BY ur.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getRestrictionHistory($userId, $limit = 50, $offset = 0) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT ur.*, u.username as admin_username, u.full_name as admin_full_name
        FROM user_restrictions ur
        LEFT JOIN users u ON ur.created_by = u.id
        WHERE ur.user_id = ? AND ur.restriction_type IN ('timeout', 'ban')
        ORDER BY ur.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$userId, $limit, $offset]);
    return $stmt->fetchAll();
}

function getRestrictionStats() {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_restrictions,
            SUM(CASE WHEN restriction_type = 'timeout' AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 ELSE 0 END) as active_timeouts,
            SUM(CASE WHEN restriction_type = 'ban' AND is_active = 1 THEN 1 ELSE 0 END) as active_bans,
            SUM(CASE WHEN restriction_type = 'timeout' AND is_active = 0 OR (expires_at IS NOT NULL AND expires_at <= NOW()) THEN 1 ELSE 0 END) as expired_timeouts,
            SUM(CASE WHEN restriction_type = 'ban' AND is_active = 0 THEN 1 ELSE 0 END) as lifted_bans
        FROM user_restrictions
        WHERE restriction_type IN ('timeout', 'ban')
    ");
    $stmt->execute();
    return $stmt->fetch();
}

function getRestrictedUsers($limit = 50, $offset = 0) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            u.id, u.username, u.full_name, u.email, u.role,
            ur.restriction_type, ur.reason, ur.created_at, ur.expires_at,
            admin.username as admin_username, admin.full_name as admin_full_name,
            CASE
                WHEN ur.restriction_type = 'ban' THEN 'Permanent'
                WHEN ur.expires_at IS NULL THEN 'Indefinite'
                ELSE CONCAT('Expires: ', DATE_FORMAT(ur.expires_at, '%M %e, %Y %h:%i %p'))
            END as duration_text
        FROM users u
        JOIN user_restrictions ur ON u.id = ur.user_id
        LEFT JOIN users admin ON ur.created_by = admin.id
        WHERE ur.restriction_type IN ('timeout', 'ban')
        AND ur.is_active = 1
        AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
        ORDER BY ur.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll();
}

function getModerationLog($limit = 100, $offset = 0, $adminId = null) {
    global $pdo;

    $sql = "
        SELECT
            ur.*,
            restricted_user.username as restricted_username,
            restricted_user.full_name as restricted_full_name,
            admin_user.username as admin_username,
            admin_user.full_name as admin_full_name
        FROM user_restrictions ur
        JOIN users restricted_user ON ur.user_id = restricted_user.id
        JOIN users admin_user ON ur.created_by = admin_user.id
        WHERE ur.restriction_type IN ('timeout', 'ban')
    ";

    $params = [];

    if ($adminId) {
        $sql .= " AND ur.created_by = ?";
        $params[] = $adminId;
    }

    $sql .= " ORDER BY ur.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function applyBulkRestrictions($userIds, $restrictionType, $durationMinutes, $reason, $adminId) {
    global $pdo;

    if (!in_array($restrictionType, ['timeout', 'ban'])) {
        throw new Exception("Invalid restriction type");
    }

    $successCount = 0;
    $errors = [];

    foreach ($userIds as $userId) {
        try {
            if ($restrictionType === 'timeout') {
                timeoutUser($userId, $durationMinutes, $reason, $adminId);
            } elseif ($restrictionType === 'ban') {
                banUser($userId, $reason, $adminId);
            }
            $successCount++;
        } catch (Exception $e) {
            $errors[] = "User ID $userId: " . $e->getMessage();
        }
    }

    return [
        'success_count' => $successCount,
        'total_count' => count($userIds),
        'errors' => $errors
    ];
}

function getReportedUsersWithStats($limit = 50, $offset = 0) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            u.id, u.username, u.full_name, u.email, u.role,
            COUNT(DISTINCT r.id) as total_reports,
            COUNT(DISTINCT r.reporter_id) as unique_reporters,
            MAX(r.created_at) as last_report_date,
            GROUP_CONCAT(DISTINCT r.report_type) as report_types
        FROM users u
        LEFT JOIN reports r ON u.id = r.reported_user_id
            AND r.created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
        WHERE r.id IS NOT NULL
        GROUP BY u.id, u.username, u.full_name, u.email, u.role
        HAVING total_reports > 0
        ORDER BY total_reports DESC, last_report_date DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll();
}

function checkUserRestrictions($userId) {
    $restrictions = getActiveRestrictions($userId);

    if (empty($restrictions)) {
        return null;
    }

    $mostRecent = $restrictions[0]; // Already ordered by created_at DESC

    return [
        'is_restricted' => true,
        'restriction_type' => $mostRecent['restriction_type'],
        'reason' => $mostRecent['reason'],
        'expires_at' => $mostRecent['expires_at'],
        'created_at' => $mostRecent['created_at'],
        'duration_text' => $mostRecent['expires_at']
            ? 'Expires: ' . date('M j, Y g:i A', strtotime($mostRecent['expires_at']))
            : 'Permanent'
    ];
}

function canUserLogin($userId) {
    $restriction = checkUserRestrictions($userId);

    if (!$restriction) {
        return ['can_login' => true];
    }

    if ($restriction['restriction_type'] === 'ban') {
        return [
            'can_login' => false,
            'reason' => 'Your account has been permanently banned: ' . $restriction['reason']
        ];
    }

    if ($restriction['restriction_type'] === 'timeout') {
        if ($restriction['expires_at'] && strtotime($restriction['expires_at']) > time()) {
            return [
                'can_login' => false,
                'reason' => 'Your account is temporarily restricted until ' . date('M j, Y g:i A', strtotime($restriction['expires_at'])) . ': ' . $restriction['reason']
            ];
        }
    }

    return ['can_login' => true];
}
?>