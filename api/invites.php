<?php
// GoSocially Invites API
// AJAX endpoints for invite code management

require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Require login for all API endpoints
requireLogin();

// Get current user
$currentUser = getCurrentUser();
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'generate':
            if ($method !== 'POST') {
                throw new Exception("Method not allowed");
            }

            // Rate limiting check
            checkRateLimit('generate_invite', 10, 3600); // 10 invites per hour

            // Verify CSRF token
            verifyCSRFToken($_POST['csrf_token'] ?? '');

            $code = generateInviteCode($currentUser['id']);

            echo json_encode([
                'success' => true,
                'invite_code' => $code,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days'))
            ]);
            break;

        case 'list':
            if ($method !== 'GET') {
                throw new Exception("Method not allowed");
            }

            $limit = min(50, max(5, (int)($_GET['limit'] ?? 20)));
            $offset = max(0, (int)($_GET['offset'] ?? 0));

            $sql = "SELECT ic.*, u.username as used_by_username
                    FROM invite_codes ic
                    LEFT JOIN users u ON ic.used_by_user_id = u.id
                    WHERE ic.created_by_user_id = ?
                    ORDER BY ic.created_at DESC
                    LIMIT ? OFFSET ?";

            $codes = dbGetAll($sql, [$currentUser['id'], $limit, $offset]);

            echo json_encode([
                'success' => true,
                'invite_codes' => $codes
            ]);
            break;

        case 'validate':
            if ($method !== 'GET') {
                throw new Exception("Method not allowed");
            }

            $code = trim($_GET['code'] ?? '');
            if (empty($code)) {
                throw new Exception("Invite code is required");
            }

            $inviteData = validateInviteCode($code);

            if ($inviteData) {
                echo json_encode([
                    'success' => true,
                    'valid' => true,
                    'created_by' => $inviteData['created_by_username'],
                    'expires_at' => $inviteData['expires_at']
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'valid' => false
                ]);
            }
            break;

        case 'stats':
            if ($method !== 'GET') {
                throw new Exception("Method not allowed");
            }

            $sql = "SELECT
                       COUNT(*) as total_codes,
                       COUNT(CASE WHEN is_used = 0 AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 END) as active_codes,
                       COUNT(CASE WHEN is_used = 1 THEN 1 END) as used_codes,
                       COUNT(CASE WHEN is_used = 0 AND expires_at IS NOT NULL AND expires_at <= NOW() THEN 1 END) as expired_codes
                    FROM invite_codes
                    WHERE created_by_user_id = ?";

            $stats = dbGetRow($sql, [$currentUser['id']]);

            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;

        default:
            throw new Exception("Invalid action");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>