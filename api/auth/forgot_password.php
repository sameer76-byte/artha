<?php
// ============================================================
//  api/auth/forgot_password.php
//  POST /api/auth/forgot_password
//  Body: { email }
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';

allow_methods(['POST']);

$data  = get_json_body();
$email = clean_email($data['email'] ?? '');

if (!$email) {
    send_error('A valid email address is required.', 400);
}

// Always respond with success to prevent email enumeration
$generic_msg = 'If that email is registered, a reset link has been sent.';

// Look up the user
$stmt = $pdo->prepare('SELECT user_id, first_name FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    // Don't reveal that the email doesn't exist
    send_success(null, 200, $generic_msg);
}

// Delete any old unused tokens for this user
$pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$user['user_id']]);

// Create new token (expires in 1 hour)
$token      = generate_token(32);
$expires_at = date('Y-m-d H:i:s', time() + 3600);

$pdo->prepare('
    INSERT INTO password_resets (user_id, token, expires_at)
    VALUES (?, ?, ?)
')->execute([$user['user_id'], $token, $expires_at]);

// Build reset URL
$reset_url = SITE_URL . '/pages/reset_password.php?token=' . urlencode($token);

// ---- Send email (PHPMailer) --------------------------------
// Uncomment and configure when PHPMailer is installed via Composer
/*
require_once ROOT_PATH . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host       = 'smtp.gmail.com';
$mail->SMTPAuth   = true;
$mail->Username   = 'your@gmail.com';
$mail->Password   = 'your_app_password';
$mail->SMTPSecure = 'tls';
$mail->Port       = 587;

$mail->setFrom(SITE_EMAIL, SITE_NAME);
$mail->addAddress($email, $user['first_name']);
$mail->Subject = 'Reset your ' . SITE_NAME . ' password';
$mail->isHTML(true);
$mail->Body = "
  <p>Hi {$user['first_name']},</p>
  <p>Click the link below to reset your password. This link expires in 1 hour.</p>
  <p><a href='{$reset_url}'>{$reset_url}</a></p>
  <p>If you didn't request this, ignore this email.</p>
";
$mail->send();
*/

// --- Development: just log the link -------------------------
if (DEBUG) {
    error_log("Password reset link for {$email}: {$reset_url}");
}

send_success(null, 200, $generic_msg);