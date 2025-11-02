<?php
// GoSocially Logout Page
// Secure logout and session cleanup

require_once 'includes/auth.php';

// Logout the user
logout();

// Redirect to login page with success message
header('Location: index.php?logout=1');
exit();
?>