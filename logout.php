<?php
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Start a fresh session to hold a logged-out notice toast
session_start();
$_SESSION['flash_info'] = "You have been logged out successfully.";

// Redirect to homepage
header("Location: index.php");
exit;
?>
