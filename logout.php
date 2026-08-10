<?php
/**
 * Destroys the session and redirects to login.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

// Clear all session data
$_SESSION = [];

// Delete the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();
redirect(SITE_URL . '/login.php?msg=You+have+been+logged+out');
