<?php
// User Reporting Interface
// Allows users to report inappropriate behavior

require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/report_functions.php';

// Require login to submit reports
requireLogin();

// Get current user info
$currentUser = getCurrentUser();
if (!$currentUser) {
    logout();
    header('Location: index.php');
    exit();
}

// Handle form submission
$reportSubmitted = false;
$reportError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCSRFToken($_POST['csrf_token'] ?? '');

        $reportedUserId = (int)($_POST['reported_user_id'] ?? 0);
        $reportType = $_POST['report_type'] ?? '';
        $description = trim($_POST['description'] ?? '');

        if ($reportedUserId <= 0) {
            throw new Exception("Please select a user to report");
        }

        if ($reportedUserId == $currentUser['id']) {
            throw new Exception("You cannot report yourself");
        }

        if (empty($reportType)) {
            throw new Exception("Please select a report type");
        }

        if (empty($description)) {
            throw new Exception("Please provide a description of the issue");
        }

        if (strlen($description) > 1000) {
            throw new Exception("Description must be 1000 characters or less");
        }

        // Check rate limiting
        if (!canUserSubmitReport($currentUser['id'], $reportedUserId)) {
            throw new Exception("You have submitted too many reports recently. Please wait before submitting another report.");
        }

        // Create the report
        $reportId = createReport($currentUser['id'], $reportedUserId, $reportType, $description);
        if ($reportId) {
            $reportSubmitted = true;
        } else {
            throw new Exception("Failed to submit report");
        }

    } catch (Exception $e) {
        $reportError = $e->getMessage();
    }
}

// Get all users for the dropdown (excluding current user)
$allUsers = getAllActiveUsers();
$usersForDropdown = [];
foreach ($allUsers as $user) {
    if ($user['id'] != $currentUser['id']) {
        $usersForDropdown[] = $user;
    }
}

// Get user's previous reports
$userReports = getUserReports($currentUser['id'], true, 10);

// Generate CSRF token
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE HTML>
<html>
<head>
  <title>Report User - GoSocially</title>
  <meta name="description" content="Report inappropriate behavior to administrators" />
  <meta name="keywords" content="report, moderation, safety, community" />
  <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" type="text/css" href="style.css" />
  <link rel="stylesheet" type="text/css" href="css/dashboard.css" />
  <style>
    .report-container {
      max-width: 800px;
      margin: 0 auto;
      padding: 20px;
    }

    .report-header {
      background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
      color: white;
      padding: 30px;
      border-radius: 10px;
      margin-bottom: 30px;
      text-align: center;
    }

    .report-section {
      background: white;
      border-radius: 10px;
      padding: 25px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .report-section h2 {
      margin-top: 0;
      color: #333;
      border-bottom: 2px solid #dc3545;
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

    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 16px;
      font-family: inherit;
    }

    .form-group textarea {
      min-height: 120px;
      resize: vertical;
    }

    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: #dc3545;
      box-shadow: 0 0 5px rgba(220, 53, 69, 0.3);
    }

    .report-type-options {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 10px;
      margin-bottom: 20px;
    }

    .report-type-option {
      position: relative;
    }

    .report-type-option input[type="radio"] {
      position: absolute;
      opacity: 0;
    }

    .report-type-option label {
      display: block;
      padding: 15px;
      border: 2px solid #ddd;
      border-radius: 8px;
      cursor: pointer;
      text-align: center;
      transition: all 0.3s ease;
      margin: 0;
    }

    .report-type-option input[type="radio"]:checked + label {
      border-color: #dc3545;
      background-color: #fff5f5;
      color: #dc3545;
      font-weight: bold;
    }

    .report-type-option label:hover {
      border-color: #dc3545;
      background-color: #f8f9fa;
    }

    .btn {
      background-color: #dc3545;
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
      background-color: #c82333;
    }

    .btn-secondary {
      background-color: #6c757d;
    }

    .btn-secondary:hover {
      background-color: #5a6268;
    }

    .success-message {
      background-color: #d4edda;
      color: #155724;
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
      border: 1px solid #c3e6cb;
    }

    .error-message {
      background-color: #f8d7da;
      color: #721c24;
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
      border: 1px solid #f5c6cb;
    }

    .warning-message {
      background-color: #fff3cd;
      color: #856404;
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
      border: 1px solid #ffeaa7;
    }

    .back-link {
      display: inline-block;
      margin-bottom: 20px;
      color: #dc3545;
      text-decoration: none;
    }

    .back-link:hover {
      text-decoration: underline;
    }

    .previous-reports {
      margin-top: 20px;
    }

    .report-item {
      padding: 15px;
      border: 1px solid #ddd;
      border-radius: 5px;
      margin-bottom: 10px;
    }

    .report-item-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
    }

    .report-status {
      padding: 3px 8px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: bold;
    }

    .status-pending {
      background-color: #fff3cd;
      color: #856404;
    }

    .status-under_review {
      background-color: #cce5ff;
      color: #004085;
    }

    .status-resolved {
      background-color: #d4edda;
      color: #155724;
    }

    .status-dismissed {
      background-color: #f8d7da;
      color: #721c24;
    }

    .character-counter {
      text-align: right;
      font-size: 12px;
      color: #666;
      margin-top: 5px;
    }

    .character-counter.warning {
      color: #dc3545;
    }

    .required-field {
      color: #dc3545;
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
      <main id="dashboard-main" class="report-container">
        <!-- Report Header -->
        <div class="report-header">
          <h1>Report a User</h1>
          <p>Help keep our community safe by reporting inappropriate behavior</p>
        </div>

        <?php if ($reportSubmitted): ?>
          <div class="success-message">
            <strong>Report submitted successfully!</strong><br>
            Your report has been received and will be reviewed by our moderation team.
            We will take appropriate action based on our community guidelines.
          </div>
        <?php endif; ?>

        <?php if ($reportError): ?>
          <div class="error-message">
            <?php echo htmlspecialchars($reportError); ?>
          </div>
        <?php endif; ?>

        <?php if (!$reportSubmitted): ?>
        <!-- Report Form -->
        <div class="report-section">
          <h2>Submit a Report</h2>
          <div class="warning-message">
            <strong>Important:</strong> False reports may result in action against your account.
            Please only report genuine violations of our community guidelines.
          </div>

          <form method="post" action="" id="report-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <div class="form-group">
              <label for="reported_user_id">User to Report <span class="required-field">*</span></label>
              <select id="reported_user_id" name="reported_user_id" required>
                <option value="">Select a user...</option>
                <?php foreach ($usersForDropdown as $user): ?>
                  <option value="<?php echo $user['id']; ?>">
                    <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?> (@<?php echo htmlspecialchars($user['username']); ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label>Report Type <span class="required-field">*</span></label>
              <div class="report-type-options">
                <div class="report-type-option">
                  <input type="radio" id="harassment" name="report_type" value="harassment" required>
                  <label for="harassment">Harassment</label>
                </div>
                <div class="report-type-option">
                  <input type="radio" id="spam" name="report_type" value="spam">
                  <label for="spam">Spam</label>
                </div>
                <div class="report-type-option">
                  <input type="radio" id="inappropriate_content" name="report_type" value="inappropriate_content">
                  <label for="inappropriate_content">Inappropriate Content</label>
                </div>
                <div class="report-type-option">
                  <input type="radio" id="fake_account" name="report_type" value="fake_account">
                  <label for="fake_account">Fake Account</label>
                </div>
                <div class="report-type-option">
                  <input type="radio" id="other" name="report_type" value="other">
                  <label for="other">Other</label>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label for="description">Description <span class="required-field">*</span></label>
              <textarea id="description" name="description"
                        placeholder="Please provide detailed information about the issue. Include specific messages, timestamps, or other relevant details that can help our moderators investigate..."
                        maxlength="1000" required></textarea>
              <div class="character-counter" id="char-counter">0 / 1000 characters</div>
            </div>

            <div style="text-align: center;">
              <button type="submit" class="btn" onclick="return confirm('Are you sure you want to submit this report? False reports may result in action against your account.')">
                Submit Report
              </button>
            </div>
          </form>
        </div>
        <?php endif; ?>

        <!-- Previous Reports -->
        <?php if (!empty($userReports)): ?>
        <div class="report-section">
          <h2>Your Previous Reports</h2>
          <div class="previous-reports">
            <?php foreach ($userReports as $report): ?>
              <div class="report-item">
                <div class="report-item-header">
                  <div>
                    <strong>Reported:</strong>
                    <?php
                    $reportedUser = getUserById($report['reported_user_id']);
                    echo htmlspecialchars($reportedUser['full_name'] ?: $reportedUser['username']);
                    ?>
                    <br>
                    <strong>Type:</strong> <?php echo htmlspecialchars($report['report_type']); ?>
                    <br>
                    <strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?>
                  </div>
                  <span class="report-status status-<?php echo str_replace('_', '-', $report['status']); ?>">
                    <?php echo ucwords(str_replace('_', ' ', $report['status'])); ?>
                  </span>
                </div>
                <div>
                  <strong>Description:</strong> <?php echo htmlspecialchars(substr($report['description'], 0, 200)); ?><?php echo strlen($report['description']) > 200 ? '...' : ''; ?>
                </div>
                <?php if ($report['resolution_note']): ?>
                  <div style="margin-top: 10px; padding: 10px; background-color: #f8f9fa; border-radius: 5px;">
                    <strong>Resolution Note:</strong> <?php echo htmlspecialchars($report['resolution_note']); ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Guidelines -->
        <div class="report-section">
          <h2>Community Guidelines</h2>
          <p>Before submitting a report, please review our community guidelines:</p>
          <ul>
            <li><strong>Harassment:</strong> Repeated unwanted contact, threats, or bullying</li>
            <li><strong>Spam:</strong> Unsolicited promotional content or repetitive messages</li>
            <li><strong>Inappropriate Content:</strong> Content that is offensive, explicit, or violates community standards</li>
            <li><strong>Fake Accounts:</strong> Accounts that appear to be impersonating others or using false information</li>
          </ul>
          <p><strong>Note:</strong> All reports are reviewed by human moderators. We take each report seriously and will take appropriate action based on our findings.</p>
        </div>
      </main>
    </div>
  </div>

  <script>
    // Character counter for description field
    const descriptionField = document.getElementById('description');
    const charCounter = document.getElementById('char-counter');

    if (descriptionField && charCounter) {
        descriptionField.addEventListener('input', function() {
            const length = this.value.length;
            const maxLength = 1000;

            charCounter.textContent = `${length} / ${maxLength} characters`;

            if (length > maxLength * 0.9) {
                charCounter.classList.add('warning');
            } else {
                charCounter.classList.remove('warning');
            }
        });
    }
  </script>
</body>
</html>