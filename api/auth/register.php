<?php
// ============================================================
//  api/auth/register.php
//  POST /api/auth/register
//  Body: { first_name, last_name, email, password }
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/auth.php';

allow_methods(['POST']);

// 1. Parse JSON body
$data = get_json_body();

// 2. Check required fields
$missing = validate_required($data, ['first_name', 'last_name', 'email', 'password']);
if ($missing) {
    send_error('Missing required fields.', 400, $missing);
}

// 3. Sanitize inputs
$first_name = clean_str($data['first_name']);
$last_name  = clean_str($data['last_name']);
$email      = clean_email($data['email']);
$password   = $data['password'];   // validated as-is, NOT cleaned (hashed next)

if (!$email) {
    send_error('Invalid email address.', 400);
}

if (!validate_password($password)) {
    send_error('Password must be at least 8 characters and contain a letter and a number.', 400);
}

if (strlen($first_name) < 2 || strlen($last_name) < 2) {
    send_error('First and last name must each be at least 2 characters.', 400);
}

// 4. Check if email already exists
$stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    send_error('An account with this email already exists.', 409);
}

// 5. Hash password and insert user
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

$stmt = $pdo->prepare('
    INSERT INTO users (first_name, last_name, email, password_hash, role_id)
    VALUES (?, ?, ?, ?, 2)
');
$stmt->execute([$first_name, $last_name, $email, $hash]);
$user_id = (int)$pdo->lastInsertId();

// 6. Auto-login after registration
$new_user = [
    'user_id'    => $user_id,
    'role_id'    => 2,
    'first_name' => $first_name,
    'last_name'  => $last_name,
    'email'      => $email,
];
login_user($new_user);

send_success([
    'user_id'    => $user_id,
    'first_name' => $first_name,
    'last_name'  => $last_name,
    'email'      => $email,
], 201, 'Account created successfully.');