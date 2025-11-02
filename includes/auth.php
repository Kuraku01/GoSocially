<?php
// Authentication and session management for GoSocially
// Secure user authentication functions

require_once __DIR__ . '/../config/database.php';

// Start secure session
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Configure secure session settings
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

        session_start();

        // Regenerate session ID to prevent session fixation
        if (!isset($_SESSION['regenerated'])) {
            session_regenerate_id(true);
            $_SESSION['regenerated'] = true;
        }

        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
            session_destroy();
            header('Location: login.php?timeout=1');
            exit();
        }
        $_SESSION['last_activity'] = time();
    }
}

// User login function
function login($username, $password) {
    $sql = "SELECT id, username, email, password_hash, full_name, role, is_active
            FROM users
            WHERE username = ? OR email = ?
            LIMIT 1";

    $user = dbGetRow($sql, [$username, $username]);

    if (!$user) {
        throw new Exception("Invalid username or password");
    }

    if (!$user['is_active']) {
        throw new Exception("Account is disabled");
    }

    if (!password_verify($password, $user['password_hash'])) {
        throw new Exception("Invalid username or password");
    }

    // Update last login time
    dbExecute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);

    // Store user data in session (excluding sensitive info)
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();

    return $user;
}

// User logout function
function logout() {
    startSecureSession();

    // Unset all session variables
    $_SESSION = [];

    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Destroy session
    session_destroy();
}

// Check if user is logged in
function isLoggedIn() {
    startSecureSession();
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// Get current user data
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    $sql = "SELECT id, username, email, full_name, role, is_active, created_at, last_login
            FROM users
            WHERE id = ? AND is_active = 1";

    return dbGetRow($sql, [$_SESSION['user_id']]);
}

// Require user to be logged in (redirect to login if not)
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Require specific role
function requireRole($requiredRole) {
    requireLogin();

    $user = getCurrentUser();
    if (!$user || $user['role'] !== $requiredRole) {
        header('HTTP/1.0 403 Forbidden');
        echo "Access denied. Insufficient privileges.";
        exit();
    }
}

// Check if current user has admin or moderator privileges
function hasAdminAccess() {
    if (!isLoggedIn()) {
        return false;
    }

    $user = getCurrentUser();
    return $user && in_array($user['role'], ['admin', 'moderator']);
}

// Generate CSRF token
function generateCSRFToken() {
    startSecureSession();

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    startSecureSession();

    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        throw new Exception("Invalid CSRF token");
    }

    return true;
}

// Sanitize input data
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Validate email format
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate password strength
function isValidPassword($password) {
    return strlen($password) >= 8 &&
           preg_match('/[A-Z]/', $password) &&
           preg_match('/[a-z]/', $password) &&
           preg_match('/[0-9]/', $password);
}

// Generate secure random string
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Rate limiting helper
function checkRateLimit($action, $limit = 5, $window = 300) {
    startSecureSession();

    $key = $action . '_attempts';
    $time_key = $action . '_window_start';

    $current_time = time();

    if (!isset($_SESSION[$time_key]) || ($current_time - $_SESSION[$time_key]) > $window) {
        $_SESSION[$key] = 0;
        $_SESSION[$time_key] = $current_time;
    }

    if ($_SESSION[$key] >= $limit) {
        throw new Exception("Too many attempts. Please try again later.");
    }

    $_SESSION[$key]++;
}
?>