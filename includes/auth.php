<?php
// ============================================================
//  includes/auth.php
//  Authentication helpers & middleware guards
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/response.php';

// ---- Session helpers --------------------------------------

/**
 * Log a user in — store essential data in session.
 */
function login_user(array $user): void
{
    session_regenerate_id(true);   // prevent session fixation
    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['role_id']   = $user['role_id'];
    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['logged_in'] = true;
}

/**
 * Destroy the session and log the user out.
 */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Check if a user is currently logged in.
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
}

/**
 * Check if the logged-in user is an admin (role_id = 1).
 */
function is_admin(): bool
{
    return is_logged_in() && (int)$_SESSION['role_id'] === 1;
}

/**
 * Get the current logged-in user ID or null.
 */
function current_user_id(): ?int
{
    return is_logged_in() ? (int)$_SESSION['user_id'] : null;
}

// ---- API Guards -------------------------------------------

/**
 * Require the user to be logged in.
 * Sends 401 JSON and exits if not.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        send_error('Unauthorised. Please log in.', 401);
    }
}

/**
 * Require admin role.
 * Sends 403 JSON and exits if not admin.
 */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        send_error('Forbidden. Admin access only.', 403);
    }
}

/**
 * Require the logged-in user to own the resource,
 * OR be an admin.
 *
 * Usage: require_owner_or_admin($order['user_id']);
 */
function require_owner_or_admin(int $owner_id): void
{
    require_login();
    if (!is_admin() && current_user_id() !== $owner_id) {
        send_error('Forbidden. You do not have access to this resource.', 403);
    }
}