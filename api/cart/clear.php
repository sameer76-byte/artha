<?php
// ============================================================
//  api/cart/clear.php
//  DELETE /api/cart/clear
//  Removes all items from the current cart
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/cart_helper.php';

allow_methods(['DELETE']);

$cart_id = get_or_create_cart($pdo);

$pdo->prepare('DELETE FROM cart_items WHERE cart_id = ?')->execute([$cart_id]);
unset($_SESSION['coupon']);

send_success([
    'cart_id' => $cart_id,
    'items'   => [],
    'totals'  => calculate_totals([]),
    'count'   => 0,
], 200, 'Cart cleared.');