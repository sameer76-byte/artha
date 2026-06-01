<?php
// ============================================================
//  api/cart/update.php
//  PUT /api/cart/update
//  Body: { cart_item_id, quantity }
//  Set quantity to 0 to remove the item
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/cart_helper.php';

allow_methods(['PUT']);

$data = get_json_body();

$missing = validate_required($data, ['cart_item_id', 'quantity']);
if ($missing) {
    send_error('Missing required fields.', 400, $missing);
}

$cart_item_id = clean_int($data['cart_item_id']);
$quantity     = clean_int($data['quantity']);

if (!$cart_item_id || $quantity < 0) {
    send_error('Invalid cart_item_id or quantity.', 400);
}

$cart_id = get_or_create_cart($pdo);

// ---- Verify this item belongs to the current cart ---------
$stmt = $pdo->prepare('
    SELECT ci.cart_item_id, ci.product_id, ci.variant_id,
           p.stock_qty AS product_stock,
           pv.stock_qty AS variant_stock
    FROM cart_items ci
    JOIN products p ON p.product_id = ci.product_id
    LEFT JOIN product_variants pv ON pv.variant_id = ci.variant_id
    WHERE ci.cart_item_id = ? AND ci.cart_id = ?
    LIMIT 1
');
$stmt->execute([$cart_item_id, $cart_id]);
$item = $stmt->fetch();

if (!$item) {
    send_error('Cart item not found.', 404);
}

// ---- Remove if quantity = 0 -------------------------------
if ($quantity === 0) {
    $pdo->prepare('DELETE FROM cart_items WHERE cart_item_id = ?')
        ->execute([$cart_item_id]);

    $items  = get_cart_items($pdo, $cart_id);
    $totals = calculate_totals($items);
    send_success(['cart_id' => $cart_id, 'items' => $items, 'totals' => $totals, 'count' => count($items)], 200, 'Item removed.');
}

// ---- Check stock ------------------------------------------
$stock = $item['variant_id']
    ? (int)$item['variant_stock']
    : (int)$item['product_stock'];

if ($quantity > $stock) {
    send_error("Only $stock unit(s) available.", 400);
}

// ---- Update quantity --------------------------------------
$pdo->prepare('UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?')
    ->execute([$quantity, $cart_item_id]);

$items  = get_cart_items($pdo, $cart_id);
$totals = calculate_totals($items);

send_success([
    'cart_id' => $cart_id,
    'items'   => $items,
    'totals'  => $totals,
    'count'   => count($items),
], 200, 'Cart updated.');