<?php
session_start(); // Start the session to access session variables

// Unset all session variables.
// This removes all data stored in $_SESSION for the current user.
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
// This is important to ensure the browser doesn't try to reuse an old session ID.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, // Set cookie expiration to the past
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session itself.
session_destroy();

// Redirect the user to the login page or homepage after logout
header("Location: login.php"); // Or index.php, wherever users should go after logout
exit();
?>