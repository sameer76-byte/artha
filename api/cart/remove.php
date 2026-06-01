<?php
// ============================================================
//  api/cart/remove.php
//  DELETE /api/cart/remove
//  Body: { cart_item_id }
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/cart_helper.php';

allow_methods(['DELETE']);

$data         = get_json_body();
$cart_item_id = clean_int($data['cart_item_id'] ?? null);

if (!$cart_item_id) {
    send_error('cart_item_id is required.', 400);
}

$cart_id = get_or_create_cart($pdo);

// Verify ownership before deleting
$stmt = $pdo->prepare('SELECT cart_item_id FROM cart_items WHERE cart_item_id = ? AND cart_id = ?');
$stmt->execute([$cart_item_id, $cart_id]);

if (!$stmt->fetch()) {
    send_error('Item not found in your cart.', 404);
}

$pdo->prepare('DELETE FROM cart_items WHERE cart_item_id = ?')->execute([$cart_item_id]);

$items  = get_cart_items($pdo, $cart_id);
$totals = calculate_totals($items);

send_success([
    'cart_id' => $cart_id,
    'items'   => $items,
    'totals'  => $totals,
    'count'   => count($items),
], 200, 'Item removed from cart.');