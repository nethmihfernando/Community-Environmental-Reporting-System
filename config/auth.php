<?php
// Start a session if one isn't already running.
// Sessions let PHP remember who is logged in across page requests.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if any user (citizen OR admin) is logged in.
 * Redirects to login page if not.
 */
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . SITE_URL . '/login.php?msg=Please+log+in+first');
        exit;
    }
}

/**
 * Check if the logged-in user is an admin.
 * Redirects to home page if they are a regular citizen.
 */
function require_admin(): void {
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: ' . SITE_URL . '/index.php?msg=Access+denied');
        exit;
    }
}

/**
 * Returns true when somebody is logged in.
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Returns true when the logged-in user is an admin.
 */
function is_admin(): bool {
    return is_logged_in() && $_SESSION['role'] === 'admin';
}

/**
 * Safely escape a value before printing it in HTML.
 * Always use this function when displaying user-submitted data.
 *
 * Example:  echo h($report['title']);
 */
function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a URL and stop the script.
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Return a Bootstrap badge HTML snippet for a report status.
 */
function status_badge(string $status): string {
    $map = [
        'pending'     => 'warning',
        'in_progress' => 'info',
        'resolved'    => 'success',
        'rejected'    => 'danger',
    ];
    $color = $map[$status] ?? 'secondary';
    $label = ucwords(str_replace('_', ' ', $status));
    return "<span class=\"badge bg-{$color}\">{$label}</span>";
}

/**
 * Return a Bootstrap badge for priority level.
 */
function priority_badge(string $priority): string {
    $map = ['low' => 'success', 'medium' => 'warning', 'high' => 'danger'];
    $color = $map[$priority] ?? 'secondary';
    return "<span class=\"badge bg-{$color}\">" . ucfirst($priority) . "</span>";
}
?>
