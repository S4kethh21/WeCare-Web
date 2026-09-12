<?php
require_once __DIR__ . '/auth.php';

// 1. Unset all session variables
$_SESSION = [];

// 2. Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 3. Destroy PHP session completely
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// 4. Send anti-cache headers to prevent browser Back button from displaying cached session
sendNoCacheHeaders();

// 5. Redirect cleanly to login.php with logged_out flag
header('Location: login.php?logged_out=1');
exit;
