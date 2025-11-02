<?php
// Admin Dashboard
// Administrative interface for managing reports and user restrictions

require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/report_functions.php';
require_once 'includes/admin_functions.php';

// Require admin or moderator access
requireRole(['admin', 'moderator']);

// Get current user info
$currentUser = getCurrentUser();
if (!$currentUser) {
    logout();
    header('Location: index.php');
    exit();
}

// Handle form submissions
$actionResult = '';
$actionError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCSRFToken($_POST['csrf_token'] ?? '');

        $action = $_POST['admin_action'] ?? '';

        switch ($action) {
            case 'update_report_status':
                $reportId = (int)($_POST['report_id'] ?? 0);
                $status = $_POST['status'] ?? '';
                $note = trim($_POST['resolution_note'] ?? '');

                if ($reportId <= 0 || empty($status)) {
                    throw new Exception("Invalid report data");
                }

                if (updateReportStatus($reportId, $status, $currentUser['id'], $note)) {
                    $actionResult = "Report status updated successfully";
                } else {
                    throw new Exception("Failed to update report status");
                }
                break;

            case 'timeout_user':
                $userId = (int)($_POST['user_id'] ?? 0);
                $duration = (int)($_POST['duration_minutes'] ?? 0);
                $reason = trim($_POST['reason'] ?? '');

                if ($userId <= 0 || $duration <= 0 || empty($reason)) {
                    throw new Exception("Invalid timeout data");
                }

                timeoutUser($userId, $duration, $reason, $currentUser['id']);
                $actionResult = "User timeout applied successfully";
                break;

            case 'ban_user':
                $userId = (int)($_POST['user_id'] ?? 0);
                $reason = trim($_POST['reason'] ?? '');

                if ($userId <= 0 || empty($reason)) {
                    throw new Exception("Invalid ban data");
                }

                if ($currentUser['role'] !== 'admin') {
                    throw new Exception("Only admins can ban users");
                }

                banUser($userId, $reason, $currentUser['id']);
                $actionResult = "User banned successfully";
                break;

            case 'remove_restriction':
                $userId = (int)($_POST['user_id'] ?? 0);

                if ($userId <= 0) {
                    throw new Exception("Invalid user ID");
                }

                removeUserRestriction($userId, $currentUser['id']);
                $actionResult = "User restriction removed successfully";
                break;

            default:
                throw new Exception("Invalid action");
        }
    } catch (Exception $e) {
        $actionError = $e->getMessage();
    }
}

// Get dashboard data
$reportStats = getReportStats()['overall'];
$restrictionStats = getRestrictionStats();
$pendingReports = getPendingReports(10);
$restrictedUsers = getRestrictedUsers(10);
$mostReportedUsers = getMostReportedUsers(5);

// Generate CSRF token
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE HTML>
<html>
<head>
  <title>Admin Dashboard - GoSocially</title>
  <meta name="description" content="Administrative dashboard for moderation" />
  <meta name="keywords" content="admin, moderation, dashboard, management" />
  <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" type="text/css" href="style.css" />
  <link rel="stylesheet" type="text/css" href="css/dashboard.css" />
  <style>
    .admin-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 20px;
    }

    .admin-header {
      background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
      color: white;
      padding: 30px;
      border-radius: 10px;
      margin-bottom: 30px;
      text-align: center;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: white;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      text-align: center;
    }

    .stat-number {
      font-size: 2em;
      font-weight: bold;
      color: #667eea;
      margin-bottom: 5px;
    }

    .stat-label {
      color: #666;
      font-size: 0.9em;
    }

    .admin-section {
      background: white;
      border-radius: 10px;
      padding: 25px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .admin-section h2 {
      margin-top: 0;
      color: #333;
      border-bottom: 2px solid #667eea;
      padding-bottom: 10px;
    }

    .table-container {
      overflow-x: auto;
    }

    .admin-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }

    .admin-table th,
    .admin-table td {
      padding: 12px;
      text-align: left;
      border-bottom: 1px solid #ddd;
    }

    .admin-table th {
      background-color: #f8f9fa;
      font-weight: bold;
      color: #333;
    }

    .admin-table tr:hover {
      background-color: #f8f9fa;
    }

    .status-badge {
      padding: 3px 8px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: bold;
    }

    .status-pending { background-color: #fff3cd; color: #856404; }
    .status-under_review { background-color: #cce5ff; color: #004085; }
    .status-resolved { background-color: #d4edda; color: #155724; }
    .status-dismissed { background-color: #f8d7da; color: #721c24; }

    .btn {
      background-color: #667eea;
      color: white;
      padding: 8px 16px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
      text-decoration: none;
      display: inline-block;
      transition: background-color 0.3s;
      margin: 2px;
    }

    .btn:hover {
      background-color: #5a6fd8;
    }

    .btn-danger {
      background-color: #dc3545;
    }

    .btn-danger:hover {
      background-color: #c82333;
    }

    .btn-warning {
      background-color: #ffc107;
      color: #000;
    }

    .btn-warning:hover {
      background-color: #e0a800;
    }

    .btn-success {
      background-color: #28a745;
    }

    .btn-success:hover {
      background-color: #218838;
    }

    .btn-secondary {
      background-color: #6c757d;
    }

    .btn-secondary:hover {
      background-color: #5a6268;
    }

    .btn-sm {
      padding: 4px 8px;
      font-size: 12px;
    }

    .action-buttons {
      display: flex;
      gap: 5px;
      flex-wrap: wrap;
    }

    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
    }

    .modal-content {
      background-color: white;
      margin: 5% auto;
      padding: 20px;
      border-radius: 10px;
      width: 90%;
      max-width: 500px;
    }

    .close {
      color: #aaa;
      float: right;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
    }

    .close:hover {
      color: #000;
    }

    .form-group {
      margin-bottom: 15px;
    }

    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: bold;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 8px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 14px;
    }

    .form-group textarea {
      min-height: 80px;
      resize: vertical;
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

    .back-link {
      display: inline-block;
      margin-bottom: 20px;
      color: #667eea;
      text-decoration: none;
    }

    .back-link:hover {
      text-decoration: underline;
    }

    .restriction-type {
      font-weight: bold;
    }

    .restriction-ban {
      color: #dc3545;
    }

    .restriction-timeout {
      color: #ffc107;
    }

    .report-description {
      max-width: 300px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .admin-tabs {
      display: flex;
      border-bottom: 1px solid #ddd;
      margin-bottom: 20px;
    }

    .admin-tab {
      padding: 10px 20px;
      background: none;
      border: none;
      cursor: pointer;
      border-bottom: 2px solid transparent;
    }

    .admin-tab.active {
      border-bottom-color: #667eea;
      color: #667eea;
    }

    .tab-content {
      display: none;
    }

    .tab-content.active {
      display: block;
    }
  </style>
</head>
<body>
  <div id="dashboard-container">
    <!-- Header -->
    <header id="dashboard-header">
      <div class="header-left">
        <h1><span class="logo_colour">GoSocially Admin</span></h1>
        <div class="user-info">
          <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        </div>
      </div>
      <div class="header-right">
        <a href="logout.php" class="logout-btn">Logout</a>
      </div>
    </header>

    <div id="dashboard-content">
      <main id="dashboard-main" class="admin-container">
        <!-- Admin Header -->
        <div class="admin-header">
          <h1>Admin Dashboard</h1>
          <p>Welcome, <?php echo htmlspecialchars($currentUser['full_name'] ?: $currentUser['username']); ?> (<?php echo htmlspecialchars($currentUser['role']); ?>)</p>
        </div>

        <?php if ($actionResult): ?>
          <div class="success-message"><?php echo htmlspecialchars($actionResult); ?></div>
        <?php endif; ?>

        <?php if ($actionError): ?>
          <div class="error-message"><?php echo htmlspecialchars($actionError); ?></div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <div class="admin-section">
          <h2>Overview Statistics</h2>
          <div class="stats-grid">
            <div class="stat-card">
              <div class="stat-number"><?php echo $reportStats['pending_reports']; ?></div>
              <div class="stat-label">Pending Reports</div>
            </div>
            <div class="stat-card">
              <div class="stat-number"><?php echo $reportStats['under_review_reports']; ?></div>
              <div class="stat-label">Under Review</div>
            </div>
            <div class="stat-card">
              <div class="stat-number"><?php echo $restrictionStats['active_timeouts']; ?></div>
              <div class="stat-label">Active Timeouts</div>
            </div>
            <div class="stat-card">
              <div class="stat-number"><?php echo $restrictionStats['active_bans']; ?></div>
              <div class="stat-label">Active Bans</div>
            </div>
            <div class="stat-card">
              <div class="stat-number"><?php echo $reportStats['reports_last_24h']; ?></div>
              <div class="stat-label">Reports (24h)</div>
            </div>
            <div class="stat-card">
              <div class="stat-number"><?php echo $reportStats['reports_last_7d']; ?></div>
              <div class="stat-label">Reports (7d)</div>
            </div>
          </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="admin-tabs">
          <button class="admin-tab active" onclick="showTab('pending-reports')">Pending Reports</button>
          <button class="admin-tab" onclick="showTab('restricted-users')">Restricted Users</button>
          <button class="admin-tab" onclick="showTab('reported-users')">Most Reported</button>
        </div>

        <!-- Pending Reports Tab -->
        <div id="pending-reports" class="tab-content active">
          <div class="admin-section">
            <h2>Pending Reports</h2>
            <?php if (empty($pendingReports)): ?>
              <p>No pending reports.</p>
            <?php else: ?>
              <div class="table-container">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Reporter</th>
                      <th>Reported User</th>
                      <th>Type</th>
                      <th>Description</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($pendingReports as $report): ?>
                      <tr>
                        <td><?php echo date('M j, g:i A', strtotime($report['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($report['reporter_username']); ?></td>
                        <td><?php echo htmlspecialchars($report['reported_username']); ?></td>
                        <td><?php echo htmlspecialchars($report['report_type']); ?></td>
                        <td class="report-description" title="<?php echo htmlspecialchars($report['description']); ?>">
                          <?php echo htmlspecialchars($report['description']); ?>
                        </td>
                        <td>
                          <div class="action-buttons">
                            <button class="btn btn-sm btn-warning" onclick="reviewReport(<?php echo $report['id']; ?>)">Review</button>
                            <button class="btn btn-sm btn-success" onclick="resolveReport(<?php echo $report['id']; ?>)">Resolve</button>
                            <button class="btn btn-sm btn-secondary" onclick="dismissReport(<?php echo $report['id']; ?>)">Dismiss</button>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Restricted Users Tab -->
        <div id="restricted-users" class="tab-content">
          <div class="admin-section">
            <h2>Currently Restricted Users</h2>
            <?php if (empty($restrictedUsers)): ?>
              <p>No restricted users.</p>
            <?php else: ?>
              <div class="table-container">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>User</th>
                      <th>Restriction Type</th>
                      <th>Reason</th>
                      <th>Duration</th>
                      <th>Applied By</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($restrictedUsers as $user): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td class="restriction-type restriction-<?php echo $user['restriction_type']; ?>">
                          <?php echo ucfirst($user['restriction_type']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($user['reason']); ?></td>
                        <td><?php echo htmlspecialchars($user['duration_text']); ?></td>
                        <td><?php echo htmlspecialchars($user['admin_username']); ?></td>
                        <td>
                          <div class="action-buttons">
                            <button class="btn btn-sm btn-success" onclick="removeRestriction(<?php echo $user['id']; ?>)">Remove</button>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Most Reported Users Tab -->
        <div id="reported-users" class="tab-content">
          <div class="admin-section">
            <h2>Most Reported Users (30 days)</h2>
            <?php if (empty($mostReportedUsers)): ?>
              <p>No reported users in the last 30 days.</p>
            <?php else: ?>
              <div class="table-container">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>User</th>
                      <th>Total Reports</th>
                      <th>Unique Reporters</th>
                      <th>Last Report</th>
                      <th>Report Types</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($mostReportedUsers as $user): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo $user['report_count']; ?></td>
                        <td><?php echo $user['unique_reporters']; ?></td>
                        <td><?php echo date('M j, g:i A', strtotime($user['last_report_date'])); ?></td>
                        <td><?php echo htmlspecialchars($user['report_types']); ?></td>
                        <td>
                          <div class="action-buttons">
                            <button class="btn btn-sm btn-warning" onclick="showRestrictionModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">Restrict</button>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Report Action Modal -->
  <div id="reportModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeModal('reportModal')">&times;</span>
      <h3 id="modalTitle">Report Action</h3>
      <form id="reportActionForm" method="post">
        <input type="hidden" name="admin_action" id="modalAction">
        <input type="hidden" name="report_id" id="modalReportId">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

        <div class="form-group">
          <label for="modalStatus">Status:</label>
          <select name="status" id="modalStatus">
            <option value="under_review">Under Review</option>
            <option value="resolved">Resolved</option>
            <option value="dismissed">Dismissed</option>
          </select>
        </div>

        <div class="form-group">
          <label for="modalNote">Resolution Note:</label>
          <textarea name="resolution_note" id="modalNote" placeholder="Optional note about the resolution..."></textarea>
        </div>

        <button type="submit" class="btn">Submit</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal('reportModal')">Cancel</button>
      </form>
    </div>
  </div>

  <!-- User Restriction Modal -->
  <div id="restrictionModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeModal('restrictionModal')">&times;</span>
      <h3>Restrict User: <span id="restrictionUsername"></span></h3>
      <form id="restrictionForm" method="post">
        <input type="hidden" name="user_id" id="restrictionUserId">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

        <div class="form-group">
          <label for="restrictionType">Restriction Type:</label>
          <select name="restriction_type" id="restrictionType" onchange="toggleDuration()">
            <option value="">Select restriction type...</option>
            <option value="timeout">Timeout</option>
            <option value="ban" <?php echo ($currentUser['role'] !== 'admin') ? 'disabled' : ''; ?>>Ban (Admin Only)</option>
          </select>
        </div>

        <div class="form-group" id="durationGroup" style="display: none;">
          <label for="duration_minutes">Duration (minutes):</label>
          <select name="duration_minutes" id="duration_minutes">
            <option value="60">1 hour</option>
            <option value="1440">1 day</option>
            <option value="10080">1 week</option>
            <option value="43200">1 month</option>
            <option value="525600">1 year</option>
          </select>
        </div>

        <div class="form-group">
          <label for="restrictionReason">Reason:</label>
          <textarea name="reason" id="restrictionReason" placeholder="Detailed reason for this restriction..." required></textarea>
        </div>

        <div id="restrictionActions">
          <button type="submit" name="admin_action" value="timeout_user" class="btn btn-warning">Apply Timeout</button>
          <button type="submit" name="admin_action" value="ban_user" class="btn btn-danger" <?php echo ($currentUser['role'] !== 'admin') ? 'disabled' : ''; ?>>Ban User</button>
        </div>

        <button type="button" class="btn btn-secondary" onclick="closeModal('restrictionModal')">Cancel</button>
      </form>
    </div>
  </div>

  <script>
    // Tab functionality
    function showTab(tabId) {
      // Hide all tabs
      const tabs = document.querySelectorAll('.tab-content');
      tabs.forEach(tab => tab.classList.remove('active'));

      // Remove active class from all tab buttons
      const tabButtons = document.querySelectorAll('.admin-tab');
      tabButtons.forEach(button => button.classList.remove('active'));

      // Show selected tab
      document.getElementById(tabId).classList.add('active');
      event.target.classList.add('active');
    }

    // Modal functionality
    function reviewReport(reportId) {
      document.getElementById('modalTitle').textContent = 'Review Report';
      document.getElementById('modalAction').value = 'update_report_status';
      document.getElementById('modalReportId').value = reportId;
      document.getElementById('modalStatus').value = 'under_review';
      document.getElementById('modalNote').value = '';
      document.getElementById('reportModal').style.display = 'block';
    }

    function resolveReport(reportId) {
      document.getElementById('modalTitle').textContent = 'Resolve Report';
      document.getElementById('modalAction').value = 'update_report_status';
      document.getElementById('modalReportId').value = reportId;
      document.getElementById('modalStatus').value = 'resolved';
      document.getElementById('modalNote').value = '';
      document.getElementById('reportModal').style.display = 'block';
    }

    function dismissReport(reportId) {
      document.getElementById('modalTitle').textContent = 'Dismiss Report';
      document.getElementById('modalAction').value = 'update_report_status';
      document.getElementById('modalReportId').value = reportId;
      document.getElementById('modalStatus').value = 'dismissed';
      document.getElementById('modalNote').value = '';
      document.getElementById('reportModal').style.display = 'block';
    }

    function showRestrictionModal(userId, username) {
      document.getElementById('restrictionUsername').textContent = username;
      document.getElementById('restrictionUserId').value = userId;
      document.getElementById('restrictionType').value = '';
      document.getElementById('durationGroup').style.display = 'none';
      document.getElementById('restrictionReason').value = '';
      document.getElementById('restrictionModal').style.display = 'block';
    }

    function removeRestriction(userId) {
      if (confirm('Are you sure you want to remove all restrictions from this user?')) {
        const form = document.createElement('form');
        form.method = 'post';
        form.innerHTML = `
          <input type="hidden" name="admin_action" value="remove_restriction">
          <input type="hidden" name="user_id" value="${userId}">
          <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
        `;
        document.body.appendChild(form);
        form.submit();
      }
    }

    function toggleDuration() {
      const type = document.getElementById('restrictionType').value;
      const durationGroup = document.getElementById('durationGroup');
      const timeoutBtn = document.querySelector('button[value="timeout_user"]');
      const banBtn = document.querySelector('button[value="ban_user"]');

      if (type === 'timeout') {
        durationGroup.style.display = 'block';
        timeoutBtn.style.display = 'inline-block';
        banBtn.style.display = 'none';
      } else if (type === 'ban') {
        durationGroup.style.display = 'none';
        timeoutBtn.style.display = 'none';
        banBtn.style.display = 'inline-block';
      } else {
        durationGroup.style.display = 'none';
        timeoutBtn.style.display = 'none';
        banBtn.style.display = 'none';
      }
    }

    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
      if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
      }
    }
  </script>
</body>
</html>