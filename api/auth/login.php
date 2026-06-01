<?php
// ============================================================
//  api/auth/login.php
//  POST /api/auth/login
//  Body: { email, password }
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/auth.php';

allow_methods(['POST']);

// 1. Parse body
$data = get_json_body();

$missing = validate_required($data, ['email', 'password']);
if ($missing) {
    send_error('Email and password are required.', 400);
}

$email    = clean_email($data['email']);
$password = $data['password'];

if (!$email) {
    send_error('Invalid email address.', 400);
}

// 2. Fetch user by email
$stmt = $pdo->prepare('
    SELECT user_id, role_id, first_name, last_name, email,
           password_hash, is_active
    FROM users
    WHERE email = ?
    LIMIT 1
');
$stmt->execute([$email]);
$user = $stmt->fetch();

// 3. Verify password (use same timing even if user not found — prevents enumeration)
$dummy_hash = '$2y$12$invalidhashfortimingatttackprevention000000000000000000';
$hash_to_check = $user ? $user['password_hash'] : $dummy_hash;

if (!password_verify($password, $hash_to_check) || !$user) {
    send_error('Incorrect email or password.', 401);
}

// 4. Check account is active
if (!(int)$user['is_active']) {
    send_error('Your account has been deactivated. Please contact support.', 403);
}

// 5. Rehash if cost factor has changed
if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST])) {
    $new_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
        ->execute([$new_hash, $user['user_id']]);
}

// 6. Merge guest cart into user cart (if any)
if (!empty($_SESSION['cart_id'])) {
    $guest_cart_id = $_SESSION['cart_id'];
    // Get or create user cart
    $stmt = $pdo->prepare('SELECT cart_id FROM carts WHERE user_id = ? LIMIT 1');
    $stmt->execute([$user['user_id']]);
    $user_cart = $stmt->fetch();

    if ($user_cart) {
        // Move guest items to user cart (ignore duplicates)
        $pdo->prepare('
            UPDATE IGNORE cart_items SET cart_id = ? WHERE cart_id = ?
        ')->execute([$user_cart['cart_id'], $guest_cart_id]);
        // Delete now-empty guest cart
        $pdo->prepare('DELETE FROM carts WHERE cart_id = ?')->execute([$guest_cart_id]);
    } else {
        // Claim the guest cart
        $pdo->prepare('UPDATE carts SET user_id = ?, session_id = NULL WHERE cart_id = ?')
            ->execute([$user['user_id'], $guest_cart_id]);
    }
    unset($_SESSION['cart_id']);
}

// 7. Start session
login_user($user);

send_success([
    'user_id'    => $user['user_id'],
    'first_name' => $user['first_name'],
    'last_name'  => $user['last_name'],
    'email'      => $user['email'],
    'role_id'    => $user['role_id'],
], 200, 'Logged in successfully.');