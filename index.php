<?php
// GoSocially Login Page
// Redirect to dashboard if already logged in

require_once 'includes/auth.php';

// Check if user is already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$timeout = isset($_GET['timeout']) ? true : false;
$registered = isset($_GET['registered']) ? true : false;
$loggedOut = isset($_GET['logout']) ? true : false;

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Rate limiting check
        checkRateLimit('login', 5, 300); // 5 attempts per 5 minutes

        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            throw new Exception("Username and password are required");
        }

        // Attempt login
        login($username, $password);

        // Redirect to dashboard on successful login
        header('Location: dashboard.php');
        exit();

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
  <title>Login to GoSocially</title>
  <meta name="description" content="Login to GoSocially - Invite-only social platform" />
  <meta name="keywords" content="login, social, messaging, invite-only" />
  <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" type="text/css" href="style.css" />
  <style>
    .login-container {
      max-width: 400px;
      margin: 50px auto;
      padding: 30px;
      background: #f9f9f9;
      border-radius: 10px;
      box-shadow: 0 0 20px rgba(0,0,0,0.1);
    }
    .login-form {
      width: 100%;
    }
    .login-form h2 {
      text-align: center;
      margin-bottom: 20px;
      color: #362C20;
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
    .login-btn {
      width: 100%;
      padding: 12px;
      background: #00C6F0;
      color: white;
      border: none;
      border-radius: 5px;
      font-size: 16px;
      font-weight: bold;
      cursor: pointer;
      transition: background 0.3s;
    }
    .login-btn:hover {
      background: #00a8d0;
    }
    .register-link {
      text-align: center;
      margin-top: 20px;
      padding-top: 20px;
      border-top: 1px solid #ddd;
    }
    .register-link a {
      color: #00C6F0;
      text-decoration: none;
      font-weight: bold;
    }
    .register-link a:hover {
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
    .timeout-message {
      background: #ff922b;
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
      <div class="login-container">
        <div class="site-info">
          <h1>GoSocially</h1>
          <p>Connect with friends through private messaging</p>
        </div>

        <form method="post" action="index.php" class="login-form">
          <h2>Login to Your Account</h2>

          <?php if ($timeout): ?>
            <div class="timeout-message">
              Your session has expired. Please log in again.
            </div>
          <?php endif; ?>

          <?php if ($loggedOut): ?>
            <div class="success-message">
              You have been logged out successfully.
            </div>
          <?php endif; ?>

          <?php if ($registered): ?>
            <div class="success-message">
              Registration successful! You can now log in.
            </div>
          <?php endif; ?>

          <?php if ($error): ?>
            <div class="error-message">
              <?php echo htmlspecialchars($error); ?>
            </div>
          <?php endif; ?>

          <div class="form-group">
            <label for="username">Username or Email:</label>
            <input type="text" id="username" name="username" required
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                   placeholder="Enter your username or email">
          </div>

          <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required
                   placeholder="Enter your password">
          </div>

          <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

          <button type="submit" class="login-btn">Login</button>
        </form>

        <div class="register-link">
          <p>Don't have an account? <a href="register.php">Register with Invite Code</a></p>
        </div>
      </div>
    </div>

    <div id="footer">
      <p>&copy; <?php echo date('Y'); ?> GoSocially. All rights reserved.</p>
      <p>Invite-only social messaging platform</p>
    </div>
  </div>
</body>
</html>