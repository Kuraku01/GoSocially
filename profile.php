<?php
// Profile and Settings Page
// User profile management with privacy settings

require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/profile_functions.php';

// Require login to access profile
requireLogin();

// Get current user info
$currentUser = getCurrentUser();
if (!$currentUser) {
    logout();
    header('Location: index.php');
    exit();
}

// Get user settings
$userSettings = getUserSettings($currentUser['id']);

// Get follower/following counts
$followersCount = getFollowersCount($currentUser['id']);
$followingCount = getFollowingCount($currentUser['id']);

// Handle form submission
$settingsSaved = false;
$settingsError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCSRFToken($_POST['csrf_token'] ?? '');

        // Validate and sanitize inputs
        $messagingPermission = $_POST['messaging_permission'] ?? 'everyone';
        $presenceStatus = $_POST['presence_status'] ?? 'active';
        $allowFollowRequests = isset($_POST['allow_follow_requests']) ? 1 : 0;
        $showOnlineStatus = isset($_POST['show_online_status']) ? 1 : 0;

        // Validate enum values
        $validMessagingPermissions = ['everyone', 'mutuals_only', 'approved_only'];
        $validPresenceStatuses = ['active', 'busy', 'invisible'];

        if (!in_array($messagingPermission, $validMessagingPermissions)) {
            throw new Exception("Invalid messaging permission setting");
        }

        if (!in_array($presenceStatus, $validPresenceStatuses)) {
            throw new Exception("Invalid presence status setting");
        }

        // Update settings
        $newSettings = [
            'messaging_permission' => $messagingPermission,
            'presence_status' => $presenceStatus,
            'allow_follow_requests' => $allowFollowRequests,
            'show_online_status' => $showOnlineStatus
        ];

        if (updateUserSettings($currentUser['id'], $newSettings)) {
            $settingsSaved = true;
            // Refresh settings
            $userSettings = getUserSettings($currentUser['id']);
        } else {
            throw new Exception("Failed to update settings");
        }

    } catch (Exception $e) {
        $settingsError = $e->getMessage();
    }
}

// Handle approved user list actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approved_action'])) {
    try {
        verifyCSRFToken($_POST['csrf_token'] ?? '');

        $approvedUserId = (int)($_POST['approved_user_id'] ?? 0);
        if ($approvedUserId <= 0) {
            throw new Exception("Invalid user ID");
        }

        if ($_POST['approved_action'] === 'add') {
            if (addToApprovedList($currentUser['id'], $approvedUserId)) {
                $settingsSaved = true;
            } else {
                throw new Exception("Failed to add user to approved list");
            }
        } elseif ($_POST['approved_action'] === 'remove') {
            if (removeFromApprovedList($currentUser['id'], $approvedUserId)) {
                $settingsSaved = true;
            } else {
                throw new Exception("Failed to remove user from approved list");
            }
        }

    } catch (Exception $e) {
        $settingsError = $e->getMessage();
    }
}

// Get approved users list
$approvedUsers = getApprovedUsers($currentUser['id']);

// Get all users for approved user selection
$allUsers = getAllActiveUsers();

// Generate CSRF token
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE HTML>
<html>
<head>
  <title>Profile - GoSocially</title>
  <meta name="description" content="GoSocially profile and settings" />
  <meta name="keywords" content="profile, settings, privacy, social" />
  <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" type="text/css" href="style.css" />
  <link rel="stylesheet" type="text/css" href="css/dashboard.css" />
  <style>
    .profile-container {
      max-width: 800px;
      margin: 0 auto;
      padding: 20px;
    }

    .profile-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 30px;
      border-radius: 10px;
      margin-bottom: 30px;
      text-align: center;
    }

    .profile-header h1 {
      margin: 0 0 10px 0;
      font-size: 2em;
    }

    .profile-stats {
      display: flex;
      justify-content: center;
      gap: 30px;
      margin-top: 20px;
    }

    .stat-item {
      text-align: center;
    }

    .stat-number {
      font-size: 1.5em;
      font-weight: bold;
    }

    .stat-label {
      font-size: 0.9em;
      opacity: 0.9;
    }

    .settings-section {
      background: white;
      border-radius: 10px;
      padding: 25px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .settings-section h2 {
      margin-top: 0;
      color: #333;
      border-bottom: 2px solid #667eea;
      padding-bottom: 10px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: bold;
      color: #555;
    }

    .form-group select {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 16px;
    }

    .toggle-group {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .toggle-switch {
      position: relative;
      width: 60px;
      height: 30px;
    }

    .toggle-switch input {
      opacity: 0;
      width: 100%;
      height: 100%;
      position: absolute;
      cursor: pointer;
      margin: 0;
      z-index: 1;
    }

    .toggle-slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #ccc;
      transition: .4s;
      border-radius: 34px;
    }

    .toggle-slider:before {
      position: absolute;
      content: "";
      height: 22px;
      width: 22px;
      left: 4px;
      bottom: 4px;
      background-color: white;
      transition: .4s;
      border-radius: 50%;
    }

    input:checked + .toggle-slider {
      background-color: #667eea;
    }

    input:checked + .toggle-slider:before {
      transform: translateX(30px);
    }

    .btn {
      background-color: #667eea;
      color: white;
      padding: 12px 24px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 16px;
      text-decoration: none;
      display: inline-block;
      transition: background-color 0.3s;
    }

    .btn:hover {
      background-color: #5a6fd8;
    }

    .btn-secondary {
      background-color: #6c757d;
    }

    .btn-secondary:hover {
      background-color: #5a6268;
    }

    .btn-danger {
      background-color: #dc3545;
    }

    .btn-danger:hover {
      background-color: #c82333;
    }

    .success-message {
      background-color: #d4edda;
      color: #155724;
      padding: 10px 15px;
      border-radius: 5px;
      margin-bottom: 20px;
      border: 1px solid #c3e6cb;
    }

    .error-message {
      background-color: #f8d7da;
      color: #721c24;
      padding: 10px 15px;
      border-radius: 5px;
      margin-bottom: 20px;
      border: 1px solid #f5c6cb;
    }

    .approved-users-list {
      margin-top: 15px;
    }

    .approved-user-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 5px;
      margin-bottom: 5px;
    }

    .back-link {
      display: inline-block;
      margin-bottom: 20px;
      color: #667eea;
      text-decoration: none;
    }

    .back-link:hover {
      text-decoration: underline;
    }

    .presence-indicator {
      display: inline-block;
      width: 12px;
      height: 12px;
      border-radius: 50%;
      margin-left: 10px;
    }

    .presence-active {
      background-color: #28a745;
    }

    .presence-busy {
      background-color: #ffc107;
    }

    .presence-invisible {
      background-color: #6c757d;
    }
  </style>
</head>
<body>
  <div id="dashboard-container">
    <!-- Header -->
    <header id="dashboard-header">
      <div class="header-left">
        <h1><span class="logo_colour">GoSocially</span></h1>
        <div class="user-info">
          <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        </div>
      </div>
      <div class="header-right">
        <a href="logout.php" class="logout-btn">Logout</a>
      </div>
    </header>

    <div id="dashboard-content">
      <main id="dashboard-main" class="profile-container">
        <!-- Profile Header -->
        <div class="profile-header">
          <h1><?php echo htmlspecialchars($currentUser['full_name'] ?: $currentUser['username']); ?></h1>
          <p>@<?php echo htmlspecialchars($currentUser['username']); ?></p>
          <p>Member since <?php echo date('F j, Y', strtotime($currentUser['created_at'])); ?></p>

          <div class="profile-stats">
            <div class="stat-item">
              <div class="stat-number"><?php echo $followersCount; ?></div>
              <div class="stat-label">Followers</div>
            </div>
            <div class="stat-item">
              <div class="stat-number"><?php echo $followingCount; ?></div>
              <div class="stat-label">Following</div>
            </div>
            <div class="stat-item">
              <div class="stat-number"><?php echo count($approvedUsers); ?></div>
              <div class="stat-label">Approved</div>
            </div>
          </div>
        </div>

        <?php if ($settingsSaved): ?>
          <div class="success-message">Settings saved successfully!</div>
        <?php endif; ?>

        <?php if ($settingsError): ?>
          <div class="error-message"><?php echo htmlspecialchars($settingsError); ?></div>
        <?php endif; ?>

        <!-- Privacy Settings -->
        <div class="settings-section">
          <h2>Privacy Settings</h2>
          <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <div class="form-group">
              <label for="messaging_permission">Who can message me:</label>
              <select id="messaging_permission" name="messaging_permission">
                <option value="everyone" <?php echo ($userSettings['messaging_permission'] === 'everyone') ? 'selected' : ''; ?>>
                  Everyone
                </option>
                <option value="mutuals_only" <?php echo ($userSettings['messaging_permission'] === 'mutuals_only') ? 'selected' : ''; ?>>
                  Mutuals only
                </option>
                <option value="approved_only" <?php echo ($userSettings['messaging_permission'] === 'approved_only') ? 'selected' : ''; ?>>
                  Approved users only
                </option>
              </select>
            </div>

            <div class="form-group">
              <div class="toggle-group">
                <label for="show_online_status">Show online status:</label>
                <div class="toggle-switch">
                  <input type="checkbox" id="show_online_status" name="show_online_status"
                         <?php echo ($userSettings['show_online_status']) ? 'checked' : ''; ?>>
                  <span class="toggle-slider"></span>
                </div>
              </div>
            </div>

            <div class="form-group">
              <div class="toggle-group">
                <label for="allow_follow_requests">Allow follow requests:</label>
                <div class="toggle-switch">
                  <input type="checkbox" id="allow_follow_requests" name="allow_follow_requests"
                         <?php echo ($userSettings['allow_follow_requests']) ? 'checked' : ''; ?>>
                  <span class="toggle-slider"></span>
                </div>
              </div>
            </div>

            <button type="submit" class="btn">Save Privacy Settings</button>
          </form>
        </div>

        <!-- Presence Settings -->
        <div class="settings-section">
          <h2>Presence Status</h2>
          <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <div class="form-group">
              <label for="presence_status">Your status:</label>
              <select id="presence_status" name="presence_status">
                <option value="active" <?php echo ($userSettings['presence_status'] === 'active') ? 'selected' : ''; ?>>
                  Active
                </option>
                <option value="busy" <?php echo ($userSettings['presence_status'] === 'busy') ? 'selected' : ''; ?>>
                  Busy
                </option>
                <option value="invisible" <?php echo ($userSettings['presence_status'] === 'invisible') ? 'selected' : ''; ?>>
                  Invisible
                </option>
              </select>
              <span class="presence-indicator presence-<?php echo $userSettings['presence_status']; ?>"></span>
            </div>

            <button type="submit" class="btn">Update Status</button>
          </form>
        </div>

        <!-- Approved Users List (shown when messaging permission is 'approved_only') -->
        <?php if ($userSettings['messaging_permission'] === 'approved_only'): ?>
        <div class="settings-section">
          <h2>Approved Users</h2>
          <p>These users can message you even when your messaging is set to "approved users only".</p>

          <div class="approved-users-list">
            <?php if (empty($approvedUsers)): ?>
              <p>No approved users yet.</p>
            <?php else: ?>
              <?php foreach ($approvedUsers as $user): ?>
                <div class="approved-user-item">
                  <div>
                    <strong><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></strong>
                    <small>(@<?php echo htmlspecialchars($user['username']); ?>)</small>
                  </div>
                  <form method="post" action="" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="approved_action" value="remove">
                    <input type="hidden" name="approved_user_id" value="<?php echo $user['id']; ?>">
                    <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">Remove</button>
                  </form>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <h3>Add Approved User</h3>
          <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="approved_action" value="add">

            <div class="form-group">
              <select name="approved_user_id" required>
                <option value="">Select a user to approve...</option>
                <?php foreach ($allUsers as $user): ?>
                  <?php if ($user['id'] != $currentUser['id']): ?>
                    <?php $isApproved = false; ?>
                    <?php foreach ($approvedUsers as $approved): ?>
                      <?php if ($approved['id'] == $user['id']): $isApproved = true; break; endif; ?>
                    <?php endforeach; ?>
                    <?php if (!$isApproved): ?>
                      <option value="<?php echo $user['id']; ?>">
                        <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?> (@<?php echo htmlspecialchars($user['username']); ?>)
                      </option>
                    <?php endif; ?>
                  <?php endif; ?>
                <?php endforeach; ?>
              </select>
            </div>

            <button type="submit" class="btn btn-secondary">Add to Approved List</button>
          </form>
        </div>
        <?php endif; ?>
      </main>
    </div>
  </div>
</body>
</html>