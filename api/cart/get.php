<?php
// ============================================================
//  api/cart/get.php
//  GET /api/cart
//  Returns the current cart items + totals
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/cart_helper.php';

allow_methods(['GET']);

$cart_id = get_or_create_cart($pdo);
$items   = get_cart_items($pdo, $cart_id);
$totals  = calculate_totals($items);

send_success([
    'cart_id' => $cart_id,
    'items'   => $items,
    'totals'  => $totals,
    'count'   => count($items),
]);