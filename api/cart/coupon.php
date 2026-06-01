<?php
// ============================================================
//  api/cart/coupon.php
//  POST /api/cart/coupon        → apply coupon
//  DELETE /api/cart/coupon      → remove coupon
//  Body (POST): { code }
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/cart_helper.php';

allow_methods(['POST', 'DELETE']);

$cart_id = get_or_create_cart($pdo);
$items   = get_cart_items($pdo, $cart_id);

if (empty($items)) {
    send_error('Your cart is empty.', 400);
}

// ---- REMOVE coupon ----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    unset($_SESSION['coupon']);
    send_success(null, 200, 'Coupon removed.');
}

// ---- APPLY coupon -----------------------------------------
$data = get_json_body();
$code = strtoupper(clean_str($data['code'] ?? ''));

if ($code === '') {
    send_error('Please enter a coupon code.', 400);
}

// Fetch coupon from DB
$stmt = $pdo->prepare('
    SELECT * FROM coupons
    WHERE code = ?
      AND is_active = 1
      AND (starts_at IS NULL OR starts_at <= NOW())
      AND (expires_at IS NULL OR expires_at >= NOW())
    LIMIT 1
');
$stmt->execute([$code]);
$coupon = $stmt->fetch();

if (!$coupon) {
    send_error('Invalid or expired coupon code.', 400);
}

// Check global usage limit
if ($coupon['usage_limit'] !== null && $coupon['used_count'] >= $coupon['usage_limit']) {
    send_error('This coupon has reached its usage limit.', 400);
}

// Check per-user limit (requires login)
if (is_logged_in() && $coupon['per_user_limit']) {
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM orders
        WHERE user_id = ? AND coupon_id = ?
    ');
    $stmt->execute([current_user_id(), $coupon['coupon_id']]);
    $used_by_user = (int)$stmt->fetchColumn();

    if ($used_by_user >= (int)$coupon['per_user_limit']) {
        send_error('You have already used this coupon.', 400);
    }
}

// Check minimum order amount
$subtotal = array_sum(array_column($items, 'line_total'));
if ($subtotal < (float)$coupon['min_order_amt']) {
    $min = CURRENCY_SYMBOL . number_format($coupon['min_order_amt'], 2);
    send_error("This coupon requires a minimum order of $min.", 400);
}

// Calculate discount
if ($coupon['discount_type'] === 'percent') {
    $discount = round($subtotal * ($coupon['discount_value'] / 100), 2);
    if ($coupon['max_discount'] !== null) {
        $discount = min($discount, (float)$coupon['max_discount']);
    }
} else {
    $discount = min((float)$coupon['discount_value'], $subtotal);
}

// Store coupon in session for checkout
$_SESSION['coupon'] = [
    'coupon_id'      => $coupon['coupon_id'],
    'code'           => $coupon['code'],
    'discount_type'  => $coupon['discount_type'],
    'discount_value' => $coupon['discount_value'],
    'discount_amt'   => $discount,
];

$totals = calculate_totals($items);
$totals['discount']    = $discount;
$totals['coupon_code'] = $coupon['code'];
$totals['total']       = max(0, round($totals['total'] - $discount, 2));

send_success([
    'coupon'  => $_SESSION['coupon'],
    'totals'  => $totals,
], 200, "Coupon applied! You saved " . CURRENCY_SYMBOL . number_format($discount, 2) . ".");