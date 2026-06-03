<?php
// ============================================================
//  api/user/change_password.php
//  POST → verify current password, set new one
//  Requires login
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/auth.php';

allow_methods(['POST']);
require_login();

$data = get_json_body();

$missing = validate_required($data, ['current_password', 'new_password']);
if ($missing) send_error('Missing required fields.', 400, $missing);

$current  = $data['current_password'];
$new_pass = $data['new_password'];

if (!validate_password($new_pass)) {
    send_error('New password must be at least 8 characters and contain a letter and a number.', 400);
}

// Fetch current hash
$stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?');
$stmt->execute([current_user_id()]);
$user = $stmt->fetch();

if (!password_verify($current, $user['password_hash'])) {
    send_error('Current password is incorrect.', 401);
}

if ($current === $new_pass) {
    send_error('New password must be different from your current password.', 400);
}

$new_hash = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
$pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
    ->execute([$new_hash, current_user_id()]);

send_success(null, 200, 'Password changed successfully.');