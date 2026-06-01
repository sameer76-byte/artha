<?php
// ============================================================
//  api/auth/me.php
//  GET /api/auth/me
//  Returns the current logged-in user's profile
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';

allow_methods(['GET']);
require_login();

$stmt = $pdo->prepare('
    SELECT u.user_id, u.first_name, u.last_name, u.email,
           u.phone, u.avatar_url, u.created_at,
           r.role_name
    FROM users u
    JOIN roles r ON r.role_id = u.role_id
    WHERE u.user_id = ?
    LIMIT 1
');
$stmt->execute([current_user_id()]);
$user = $stmt->fetch();

if (!$user) {
    // Session exists but user was deleted — force logout
    logout_user();
    send_error('User not found. Please log in again.', 401);
}

send_success($user);