<?php
// GoSocially Dashboard
// Main messaging interface after login

require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Require login to access dashboard
requireLogin();

// Get current user info
$currentUser = getCurrentUser();
if (!$currentUser) {
    logout();
    header('Location: index.php');
    exit();
}

// Get user's conversations
$conversations = getUserConversations($currentUser['id']);

// Get all users for user directory
$allUsers = getAllActiveUsers();

// Get unread message count
$unreadCount = getUnreadMessageCount($currentUser['id']);

// Handle message sending
$messageSent = false;
$messageError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        verifyCSRFToken($_POST['csrf_token'] ?? '');

        if ($_POST['action'] === 'send_message') {
            $receiverId = (int)($_POST['receiver_id'] ?? 0);
            $messageText = trim($_POST['message_text'] ?? '');

            if ($receiverId <= 0 || empty($messageText)) {
                throw new Exception("Invalid message data");
            }

            sendMessage($currentUser['id'], $receiverId, $messageText);
            $messageSent = true;

            // Refresh conversations after sending
            $conversations = getUserConversations($currentUser['id']);
        }
    } catch (Exception $e) {
        $messageError = $e->getMessage();
    }
}

// Get selected conversation
$selectedConversation = null;
$messages = [];

if (isset($_GET['conversation']) && is_numeric($_GET['conversation'])) {
    $otherUserId = (int)$_GET['conversation'];

    // Verify this user is part of the conversation
    $selectedConversation = null;
    foreach ($conversations as $conv) {
        if ($conv['other_user_id'] == $otherUserId) {
            $selectedConversation = $conv;
            break;
        }
    }

    if ($selectedConversation) {
        // Mark messages as read
        markMessagesAsRead($otherUserId, $currentUser['id']);

        // Get conversation messages
        $messages = getMessages($currentUser['id'], $otherUserId, 50);

        // Update unread count
        $unreadCount = getUnreadMessageCount($currentUser['id']);
    }
}

// Generate CSRF token
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE HTML>
<html>
<head>
  <title>Dashboard - GoSocially</title>
  <meta name="description" content="GoSocially messaging dashboard" />
  <meta name="keywords" content="dashboard, messaging, chat, social" />
  <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" type="text/css" href="style.css" />
  <link rel="stylesheet" type="text/css" href="css/dashboard.css" />
</head>
<body data-user-id="<?php echo $currentUser['id']; ?>">
  <div id="dashboard-container">
    <!-- Header -->
    <header id="dashboard-header">
      <div class="header-left">
        <h1><span class="logo_colour">GoSocially</span></h1>
        <div class="user-info">
          Welcome back, <strong><?php echo htmlspecialchars($currentUser['full_name'] ?: $currentUser['username']); ?></strong>
          <?php if ($unreadCount > 0): ?>
            <span class="unread-badge"><?php echo $unreadCount; ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="header-right">
        <a href="logout.php" class="logout-btn">Logout</a>
      </div>
    </header>

    <div id="dashboard-content">
      <!-- Sidebar -->
      <aside id="dashboard-sidebar">
        <!-- Invite Code Section -->
        <div class="sidebar-section">
          <h3>Invite Codes</h3>
          <div class="invite-section">
            <button id="generate-invite-btn" class="btn btn-primary">Generate New Invite</button>
            <div id="invite-codes-list"></div>
          </div>
        </div>

        <!-- Conversations Section -->
        <div class="sidebar-section">
          <h3>Recent Chats</h3>
          <div class="conversations-list">
            <?php if (empty($conversations)): ?>
              <p class="no-conversations">No conversations yet. Start chatting!</p>
            <?php else: ?>
              <?php foreach ($conversations as $conversation): ?>
                <div class="conversation-item <?php echo ($selectedConversation && $selectedConversation['other_user_id'] == $conversation['other_user_id']) ? 'active' : ''; ?>"
                     data-user-id="<?php echo $conversation['other_user_id']; ?>">
                  <div class="conversation-info">
                    <div class="conversation-name">
                      <?php echo htmlspecialchars($conversation['full_name'] ?: $conversation['username']); ?>
                      <?php if (isUserOnline($conversation['last_login'])): ?>
                        <span class="online-indicator"></span>
                      <?php endif; ?>
                    </div>
                    <div class="conversation-time">
                      <?php echo formatDateTime($conversation['last_message_time']); ?>
                    </div>
                  </div>
                  <?php if ($conversation['unread_count'] > 0): ?>
                    <div class="unread-count"><?php echo $conversation['unread_count']; ?></div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- User Directory Section -->
        <div class="sidebar-section">
          <h3>All Users</h3>
          <div class="user-directory">
            <?php foreach ($allUsers as $user): ?>
              <?php if ($user['id'] != $currentUser['id']): ?>
                <div class="user-item" data-user-id="<?php echo $user['id']; ?>">
                  <div class="user-name">
                    <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>
                    <?php if (isUserOnline($user['last_login'])): ?>
                      <span class="online-indicator"></span>
                    <?php endif; ?>
                  </div>
                  <div class="user-role"><?php echo htmlspecialchars($user['role']); ?></div>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
      </aside>

      <!-- Main Content Area -->
      <main id="dashboard-main">
        <?php if ($selectedConversation): ?>
          <!-- Chat Interface -->
          <div class="chat-container">
            <div class="chat-header">
              <div class="chat-recipient">
                <h3><?php echo htmlspecialchars($selectedConversation['full_name'] ?: $selectedConversation['username']); ?></h3>
                <?php if (isUserOnline($selectedConversation['last_login'])): ?>
                  <span class="status-text">Online</span>
                <?php else: ?>
                  <span class="status-text">Last seen: <?php echo formatDateTime($selectedConversation['last_login']); ?></span>
                <?php endif; ?>
              </div>
            </div>

            <div class="messages-container" id="messages-container">
              <?php if (empty($messages)): ?>
                <div class="no-messages">No messages yet. Start the conversation!</div>
              <?php else: ?>
                <?php foreach ($messages as $message): ?>
                  <div class="message <?php echo ($message['sender_id'] == $currentUser['id']) ? 'sent' : 'received'; ?>">
                    <div class="message-content">
                      <div class="message-text"><?php echo htmlspecialchars($message['message_text']); ?></div>
                      <div class="message-time"><?php echo date('g:i A', strtotime($message['sent_at'])); ?></div>
                      <?php if ($message['sender_id'] == $currentUser['id'] && $message['is_read']): ?>
                        <div class="message-read">✓ Read</div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <div class="message-input-container">
              <?php if ($messageError): ?>
                <div class="error-message"><?php echo htmlspecialchars($messageError); ?></div>
              <?php endif; ?>
              <?php if ($messageSent): ?>
                <div class="success-message">Message sent successfully!</div>
              <?php endif; ?>

              <form id="message-form" method="post" action="dashboard.php?conversation=<?php echo $selectedConversation['other_user_id']; ?>">
                <div class="input-group">
                  <input type="text" id="message-input" name="message_text"
                         placeholder="Type your message..."
                         maxlength="1000" required>
                  <input type="hidden" name="receiver_id" value="<?php echo $selectedConversation['other_user_id']; ?>">
                  <input type="hidden" name="action" value="send_message">
                  <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                  <button type="submit" class="send-btn">Send</button>
                </div>
              </form>
            </div>
          </div>
        <?php else: ?>
          <!-- Welcome Screen -->
          <div class="welcome-container">
            <div class="welcome-content">
              <h2>Welcome to GoSocially!</h2>
              <p>Select a conversation from the sidebar or choose a user from the directory to start messaging.</p>

              <div class="welcome-stats">
                <div class="stat-item">
                  <div class="stat-number"><?php echo count($conversations); ?></div>
                  <div class="stat-label">Conversations</div>
                </div>
                <div class="stat-item">
                  <div class="stat-number"><?php echo $unreadCount; ?></div>
                  <div class="stat-label">Unread Messages</div>
                </div>
                <div class="stat-item">
                  <div class="stat-number"><?php echo count($allUsers) - 1; ?></div>
                  <div class="stat-label">Other Users</div>
                </div>
              </div>

              <div class="welcome-actions">
                <h3>Quick Actions:</h3>
                <button id="generate-invite-welcome-btn" class="btn btn-primary">Generate Invite Code</button>
                <p>Invite friends to join GoSocially!</p>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </main>
    </div>
  </div>

  <script src="js/dashboard.js"></script>
</body>
</html>