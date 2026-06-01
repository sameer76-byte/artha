<?php
// ============================================================
//  api/auth/reset_password.php
//  POST /api/auth/reset_password
//  Body: { token, password, password_confirm }
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';

allow_methods(['POST']);

$data = get_json_body();

$missing = validate_required($data, ['token', 'password', 'password_confirm']);
if ($missing) {
    send_error('Missing required fields.', 400, $missing);
}

$token            = clean_str($data['token']);
$password         = $data['password'];
$password_confirm = $data['password_confirm'];

// 1. Validate password strength
if (!validate_password($password)) {
    send_error('Password must be at least 8 characters and contain a letter and a number.', 400);
}

// 2. Confirm passwords match
if ($password !== $password_confirm) {
    send_error('Passwords do not match.', 400);
}

// 3. Look up the token
$stmt = $pdo->prepare('
    SELECT r.reset_id, r.user_id, r.expires_at, r.used
    FROM password_resets r
    WHERE r.token = ?
    LIMIT 1
');
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) {
    send_error('Invalid or expired reset link.', 400);
}

if ((int)$reset['used'] === 1) {
    send_error('This reset link has already been used.', 400);
}

if (strtotime($reset['expires_at']) < time()) {
    send_error('This reset link has expired. Please request a new one.', 400);
}

// 4. Update the password
$new_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

$pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
    ->execute([$new_hash, $reset['user_id']]);

// 5. Mark token as used
$pdo->prepare('UPDATE password_resets SET used = 1 WHERE reset_id = ?')
    ->execute([$reset['reset_id']]);

send_success(null, 200, 'Password reset successfully. You can now log in.');