<?php
// GoSocially Registration Page
// Invite-only user registration

require_once 'includes/auth.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Rate limiting check
        checkRateLimit('register', 3, 300); // 3 attempts per 5 minutes

        // Verify CSRF token
        verifyCSRFToken($_POST['csrf_token'] ?? '');

        // Get and validate form data
        $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $fullName = sanitizeInput($_POST['full_name'] ?? '');
        $inviteCode = sanitizeInput($_POST['invite_code'] ?? '');

        // Basic validation
        if (empty($username) || empty($email) || empty($password) || empty($inviteCode)) {
            throw new Exception("All fields are required");
        }

        if (strlen($username) < 3 || strlen($username) > 50) {
            throw new Exception("Username must be between 3 and 50 characters");
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            throw new Exception("Username can only contain letters, numbers, and underscores");
        }

        if (!isValidEmail($email)) {
            throw new Exception("Invalid email format");
        }

        if (!isValidPassword($password)) {
            throw new Exception("Password must be at least 8 characters and contain uppercase, lowercase, and numbers");
        }

        if ($password !== $confirmPassword) {
            throw new Exception("Passwords do not match");
        }

        if (strlen($fullName) > 100) {
            throw new Exception("Full name cannot exceed 100 characters");
        }

        // Validate invite code and get inviter info
        $inviteData = validateInviteCode($inviteCode);
        if (!$inviteData) {
            throw new Exception("Invalid or expired invite code");
        }

        // Register the user
        $newUserId = registerUser($username, $email, $password, $fullName, $inviteCode, $inviteData['created_by_user_id']);

        $success = "Registration successful! You can now log in with your credentials.";

        // Clear form data on success
        $_POST = [];

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Generate CSRF token
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE HTML>
<html>
<head>
  <title>Register - GoSocially</title>
  <meta name="description" content="Register for GoSocially with invite code" />
  <meta name="keywords" content="register, social, messaging, invite code" />
  <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" type="text/css" href="style.css" />
  <style>
    .register-container {
      max-width: 450px;
      margin: 30px auto;
      padding: 30px;
      background: #f9f9f9;
      border-radius: 10px;
      box-shadow: 0 0 20px rgba(0,0,0,0.1);
    }
    .register-form {
      width: 100%;
    }
    .register-form h2 {
      text-align: center;
      margin-bottom: 20px;
      color: #362C20;
    }
    .invite-info {
      background: #e3f2fd;
      border: 1px solid #90caf9;
      border-radius: 5px;
      padding: 15px;
      margin-bottom: 20px;
      text-align: center;
      color: #1565c0;
    }
    .form-group {
      margin-bottom: 15px;
    }
    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: bold;
      color: #555;
    }
    .form-group input {
      width: 100%;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 16px;
      box-sizing: border-box;
    }
    .form-group input:focus {
      border-color: #00C6F0;
      outline: none;
      box-shadow: 0 0 5px rgba(0,198,240,0.3);
    }
    .form-group small {
      color: #666;
      font-size: 12px;
      margin-top: 3px;
      display: block;
    }
    .register-btn {
      width: 100%;
      padding: 12px;
      background: #51cf66;
      color: white;
      border: none;
      border-radius: 5px;
      font-size: 16px;
      font-weight: bold;
      cursor: pointer;
      transition: background 0.3s;
    }
    .register-btn:hover {
      background: #40c057;
    }
    .login-link {
      text-align: center;
      margin-top: 20px;
      padding-top: 20px;
      border-top: 1px solid #ddd;
    }
    .login-link a {
      color: #00C6F0;
      text-decoration: none;
      font-weight: bold;
    }
    .login-link a:hover {
      text-decoration: underline;
    }
    .error-message {
      background: #ff6b6b;
      color: white;
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 15px;
      text-align: center;
    }
    .success-message {
      background: #51cf66;
      color: white;
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 15px;
      text-align: center;
    }
    .site-info {
      text-align: center;
      margin-bottom: 30px;
      color: #666;
    }
    .site-info h1 {
      color: #00C6F0;
      margin-bottom: 10px;
    }
    .password-requirements {
      background: #fff3cd;
      border: 1px solid #ffeaa7;
      border-radius: 5px;
      padding: 10px;
      margin-top: 5px;
      font-size: 12px;
      color: #856404;
    }
  </style>
</head>
<body>
  <div id="main">
    <div id="header">
      <div id="logo">
        <div id="logo_text">
          <h1><a href="index.php">Welcome to<span class="logo_colour"> GoSocially!</span></a></h1>
          <h2>Invite-only Social Platform</h2>
        </div>
      </div>
    </div>

    <div id="content_header"></div>
    <div id="site_content">
      <div class="register-container">
        <div class="site-info">
          <h1>GoSocially</h1>
          <p>Join our exclusive social messaging community</p>
        </div>

        <div class="invite-info">
          <strong>Invite-Only Registration</strong><br>
          You need an invite code from an existing member to register
        </div>

        <?php if ($success): ?>
          <div class="success-message">
            <?php echo htmlspecialchars($success); ?>
          </div>
          <div style="text-align: center; margin-top: 20px;">
            <a href="index.php" class="login-btn" style="display: inline-block; width: auto; padding: 12px 30px;">Proceed to Login</a>
          </div>
        <?php else: ?>
          <form method="post" action="register.php" class="register-form">
            <h2>Create Your Account</h2>

            <?php if ($error): ?>
              <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
              </div>
            <?php endif; ?>

            <div class="form-group">
              <label for="username">Username: *</label>
              <input type="text" id="username" name="username" required
                     value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                     placeholder="Choose a username (3-50 chars)">
              <small>Letters, numbers, and underscores only</small>
            </div>

            <div class="form-group">
              <label for="email">Email Address: *</label>
              <input type="email" id="email" name="email" required
                     value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                     placeholder="your@email.com">
            </div>

            <div class="form-group">
              <label for="full_name">Full Name:</label>
              <input type="text" id="full_name" name="full_name"
                     value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                     placeholder="Your full name (optional)">
            </div>

            <div class="form-group">
              <label for="password">Password: *</label>
              <input type="password" id="password" name="password" required
                     placeholder="Create a strong password">
              <div class="password-requirements">
                Password must contain:<br>
                • At least 8 characters<br>
                • Uppercase letters (A-Z)<br>
                • Lowercase letters (a-z)<br>
                • Numbers (0-9)
              </div>
            </div>

            <div class="form-group">
              <label for="confirm_password">Confirm Password: *</label>
              <input type="password" id="confirm_password" name="confirm_password" required
                     placeholder="Re-enter your password">
            </div>

            <div class="form-group">
              <label for="invite_code">Invite Code: *</label>
              <input type="text" id="invite_code" name="invite_code" required
                     value="<?php echo htmlspecialchars($_POST['invite_code'] ?? ''); ?>"
                     placeholder="Enter your 32-character invite code">
              <small>Get this from an existing GoSocially member</small>
            </div>

            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <button type="submit" class="register-btn">Create Account</button>
          </form>

          <div class="login-link">
            <p>Already have an account? <a href="index.php">Login here</a></p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div id="footer">
      <p>&copy; <?php echo date('Y'); ?> GoSocially. All rights reserved.</p>
      <p>Invite-only social messaging platform</p>
    </div>
  </div>
</body>
</html>