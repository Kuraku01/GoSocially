<?php
// Report management functions

function createReport($reporterId, $reportedUserId, $type, $description) {
    global $pdo;

    // Validate inputs
    if ($reporterId == $reportedUserId) {
        throw new Exception("You cannot report yourself");
    }

    $validTypes = ['harassment', 'spam', 'inappropriate_content', 'fake_account', 'other'];
    if (!in_array($type, $validTypes)) {
        throw new Exception("Invalid report type");
    }

    if (empty(trim($description))) {
        throw new Exception("Description is required");
    }

    if (strlen($description) > 1000) {
        throw new Exception("Description must be 1000 characters or less");
    }

    // Check if users exist
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id IN (?, ?)");
    $stmt->execute([$reporterId, $reportedUserId]);
    if ($stmt->rowCount() < 2) {
        throw new Exception("Invalid user ID");
    }

    // Check for existing active reports for same issue
    $stmt = $pdo->prepare("
        SELECT id FROM reports
        WHERE reporter_id = ? AND reported_user_id = ?
        AND report_type = ? AND status IN ('pending', 'under_review')
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute([$reporterId, $reportedUserId, $type]);
    if ($stmt->fetch()) {
        throw new Exception("You have already submitted a similar report for this user in the last 24 hours");
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO reports (reporter_id, reported_user_id, report_type, description)
            VALUES (?, ?, ?, ?)
        ");
        $result = $stmt->execute([$reporterId, $reportedUserId, $type, $description]);

        if ($result) {
            return $pdo->lastInsertId();
        }
        throw new Exception("Failed to create report");
    } catch (PDOException $e) {
        throw new Exception("Failed to submit report: " . $e->getMessage());
    }
}

function getPendingReports($limit = 50, $offset = 0) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT r.*,
               reporter.username as reporter_username,
               reporter.full_name as reporter_full_name,
               reported.username as reported_username,
               reported.full_name as reported_full_name
        FROM reports r
        JOIN users reporter ON r.reporter_id = reporter.id
        JOIN users reported ON r.reported_user_id = reported.id
        WHERE r.status = 'pending'
        ORDER BY r.created_at ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll();
}

function getAllReports($status = null, $limit = 50, $offset = 0, $reportType = null) {
    global $pdo;

    $sql = "
        SELECT r.*,
               reporter.username as reporter_username,
               reporter.full_name as reporter_full_name,
               reported.username as reported_username,
               reported.full_name as reported_full_name,
               resolver.username as resolver_username,
               resolver.full_name as resolver_full_name
        FROM reports r
        JOIN users reporter ON r.reporter_id = reporter.id
        JOIN users reported ON r.reported_user_id = reported.id
        LEFT JOIN users resolver ON r.resolved_by = resolver.id
        WHERE 1=1
    ";

    $params = [];

    if ($status) {
        $sql .= " AND r.status = ?";
        $params[] = $status;
    }

    if ($reportType) {
        $sql .= " AND r.report_type = ?";
        $params[] = $reportType;
    }

    $sql .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function updateReportStatus($reportId, $status, $adminId, $note = null) {
    global $pdo;

    $validStatuses = ['pending', 'under_review', 'resolved', 'dismissed'];
    if (!in_array($status, $validStatuses)) {
        throw new Exception("Invalid status");
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("
            UPDATE reports
            SET status = ?, resolved_by = ?, resolved_at = NOW(), resolution_note = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $adminId, $note, $reportId]);

        if ($stmt->rowCount() === 0) {
            throw new Exception("Report not found");
        }

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

function getUserReports($userId, $asReporter = null, $limit = 50, $offset = 0) {
    global $pdo;

    if ($asReporter === true) {
        // Reports made by this user
        $sql = "
            SELECT r.*,
                   reported.username as reported_username,
                   reported.full_name as reported_full_name
            FROM reports r
            JOIN users reported ON r.reported_user_id = reported.id
            WHERE r.reporter_id = ?
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $params = [$userId, $limit, $offset];
    } elseif ($asReporter === false) {
        // Reports made against this user
        $sql = "
            SELECT r.*,
                   reporter.username as reporter_username,
                   reporter.full_name as reporter_full_name
            FROM reports r
            JOIN users reporter ON r.reporter_id = reporter.id
            WHERE r.reported_user_id = ?
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $params = [$userId, $limit, $offset];
    } else {
        // All reports involving this user (both as reporter and reported)
        $sql = "
            SELECT r.*,
               reporter.username as reporter_username,
               reporter.full_name as reporter_full_name,
               reported.username as reported_username,
               reported.full_name as reported_full_name
        FROM reports r
        JOIN users reporter ON r.reporter_id = reporter.id
        JOIN users reported ON r.reported_user_id = reported.id
        WHERE r.reporter_id = ? OR r.reported_user_id = ?
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
        ";
        $params = [$userId, $userId, $limit, $offset];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getReportById($reportId) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT r.*,
               reporter.username as reporter_username,
               reporter.full_name as reporter_full_name,
               reported.username as reported_username,
               reported.full_name as reported_full_name,
               resolver.username as resolver_username,
               resolver.full_name as resolver_full_name
        FROM reports r
        JOIN users reporter ON r.reporter_id = reporter.id
        JOIN users reported ON r.reported_user_id = reported.id
        LEFT JOIN users resolver ON r.resolved_by = resolver.id
        WHERE r.id = ?
    ");
    $stmt->execute([$reportId]);
    return $stmt->fetch();
}

function getReportStats() {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_reports,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_reports,
            SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review_reports,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_reports,
            SUM(CASE WHEN status = 'dismissed' THEN 1 ELSE 0 END) as dismissed_reports,
            SUM(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as reports_last_24h,
            SUM(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as reports_last_7d
        FROM reports
    ");
    $stmt->execute();
    $overall = $stmt->fetch();

    // Get reports by type
    $stmt = $pdo->prepare("
        SELECT report_type, COUNT(*) as count
        FROM reports
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY report_type
        ORDER BY count DESC
    ");
    $stmt->execute();
    $byType = $stmt->fetchAll();

    return [
        'overall' => $overall,
        'by_type' => $byType
    ];
}

function getMostReportedUsers($limit = 10, $days = 30) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            u.id, u.username, u.full_name,
            COUNT(r.id) as report_count,
            COUNT(DISTINCT r.reporter_id) as unique_reporters,
            MAX(r.created_at) as last_report_date
        FROM users u
        JOIN reports r ON u.id = r.reported_user_id
        WHERE r.created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY u.id, u.username, u.full_name
        HAVING report_count > 0
        ORDER BY report_count DESC, unique_reporters DESC
        LIMIT ?
    ");
    $stmt->execute([$days, $limit]);
    return $stmt->fetchAll();
}

function canUserSubmitReport($reporterId, $reportedUserId) {
    global $pdo;

    // Check rate limiting (max 5 reports per hour)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM reports
        WHERE reporter_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$reporterId]);
    $hourlyCount = $stmt->fetch()['count'];

    if ($hourlyCount >= 5) {
        return false;
    }

    // Check for duplicate reports in last 24 hours
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM reports
        WHERE reporter_id = ? AND reported_user_id = ?
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute([$reporterId, $reportedUserId]);
    $dailyDuplicateCount = $stmt->fetch()['count'];

    if ($dailyDuplicateCount >= 3) {
        return false;
    }

    return true;
}

function getReportsByAdmin($adminId, $limit = 50, $offset = 0) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT r.*,
               reporter.username as reporter_username,
               reporter.full_name as reporter_full_name,
               reported.username as reported_username,
               reported.full_name as reported_full_name
        FROM reports r
        JOIN users reporter ON r.reporter_id = reporter.id
        JOIN users reported ON r.reported_user_id = reported.id
        WHERE r.resolved_by = ?
        ORDER BY r.resolved_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$adminId, $limit, $offset]);
    return $stmt->fetchAll();
}
?>