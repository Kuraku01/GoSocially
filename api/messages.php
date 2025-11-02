<?php
// GoSocially Messages API
// AJAX endpoints for messaging functionality

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
        case 'send':
            if ($method !== 'POST') {
                throw new Exception("Method not allowed");
            }

            // Verify CSRF token
            verifyCSRFToken($_POST['csrf_token'] ?? '');

            $receiverId = (int)($_POST['receiver_id'] ?? 0);
            $messageText = trim($_POST['message_text'] ?? '');

            if ($receiverId <= 0 || empty($messageText)) {
                throw new Exception("Invalid message data");
            }

            $messageId = sendMessage($currentUser['id'], $receiverId, $messageText);

            echo json_encode([
                'success' => true,
                'message_id' => $messageId,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'get_conversation':
            if ($method !== 'GET') {
                throw new Exception("Method not allowed");
            }

            $otherUserId = (int)($_GET['user_id'] ?? 0);
            if ($otherUserId <= 0) {
                throw new Exception("Invalid user ID");
            }

            $limit = min(50, max(10, (int)($_GET['limit'] ?? 20)));
            $offset = max(0, (int)($_GET['offset'] ?? 0));

            $messages = getMessages($currentUser['id'], $otherUserId, $limit, $offset);

            // Mark messages as read
            markMessagesAsRead($otherUserId, $currentUser['id']);

            echo json_encode([
                'success' => true,
                'messages' => $messages,
                'unread_count' => getUnreadMessageCount($currentUser['id'])
            ]);
            break;

        case 'get_conversations':
            if ($method !== 'GET') {
                throw new Exception("Method not allowed");
            }

            $conversations = getUserConversations($currentUser['id']);

            echo json_encode([
                'success' => true,
                'conversations' => $conversations
            ]);
            break;

        case 'mark_read':
            if ($method !== 'POST') {
                throw new Exception("Method not allowed");
            }

            $senderId = (int)($_POST['sender_id'] ?? 0);
            if ($senderId <= 0) {
                throw new Exception("Invalid sender ID");
            }

            $affected = markMessagesAsRead($senderId, $currentUser['id']);

            echo json_encode([
                'success' => true,
                'messages_marked' => $affected,
                'unread_count' => getUnreadMessageCount($currentUser['id'])
            ]);
            break;

        case 'search_users':
            if ($method !== 'GET') {
                throw new Exception("Method not allowed");
            }

            $query = trim($_GET['q'] ?? '');
            if (strlen($query) < 2) {
                echo json_encode(['success' => true, 'users' => []]);
                break;
            }

            $sql = "SELECT id, username, full_name, role, last_login
                    FROM users
                    WHERE is_active = 1
                    AND id != ?
                    AND (username LIKE ? OR full_name LIKE ?)
                    ORDER BY username ASC
                    LIMIT 20";

            $searchTerm = '%' . $query . '%';
            $users = dbGetAll($sql, [$currentUser['id'], $searchTerm, $searchTerm]);

            echo json_encode([
                'success' => true,
                'users' => $users
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