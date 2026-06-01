<?php
// ============================================================
//  api/cart/add.php
//  POST /api/cart/add
//  Body: { product_id, quantity, variant_id? }
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/cart_helper.php';

allow_methods(['POST']);

$data = get_json_body();

$missing = validate_required($data, ['product_id', 'quantity']);
if ($missing) {
    send_error('Missing required fields.', 400, $missing);
}

$product_id = clean_int($data['product_id']);
$quantity   = clean_int($data['quantity']);
$variant_id = isset($data['variant_id']) ? clean_int($data['variant_id']) : null;

if (!$product_id || $quantity < 1) {
    send_error('Invalid product_id or quantity.', 400);
}

// ---- 1. Validate product exists & is active ---------------
$stmt = $pdo->prepare('
    SELECT product_id, name, price, sale_price, stock_qty
    FROM products
    WHERE product_id = ? AND is_active = 1
');
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    send_error('Product not found or unavailable.', 404);
}

// ---- 2. Validate variant (if provided) --------------------
$unit_price = (float)($product['sale_price'] ?? $product['price']);
$stock      = (int)$product['stock_qty'];

if ($variant_id) {
    $stmt = $pdo->prepare('
        SELECT variant_id, price, sale_price, stock_qty
        FROM product_variants
        WHERE variant_id = ? AND product_id = ? AND is_active = 1
    ');
    $stmt->execute([$variant_id, $product_id]);
    $variant = $stmt->fetch();

    if (!$variant) {
        send_error('Selected variant not found.', 404);
    }

    // Variant price overrides product price if set
    if ($variant['sale_price'] !== null) {
        $unit_price = (float)$variant['sale_price'];
    } elseif ($variant['price'] !== null) {
        $unit_price = (float)$variant['price'];
    }

    $stock = (int)$variant['stock_qty'];
}

// ---- 3. Stock check ---------------------------------------
if ($stock < 1) {
    send_error('Sorry, this product is out of stock.', 400);
}

// ---- 4. Get or create cart --------------------------------
$cart_id = get_or_create_cart($pdo);

// ---- 5. Check if item already in cart ---------------------
$stmt = $pdo->prepare('
    SELECT cart_item_id, quantity
    FROM cart_items
    WHERE cart_id = ? AND product_id = ?
      AND (variant_id = ? OR (variant_id IS NULL AND ? IS NULL))
    LIMIT 1
');
$stmt->execute([$cart_id, $product_id, $variant_id, $variant_id]);
$existing = $stmt->fetch();

if ($existing) {
    // Update quantity
    $new_qty = $existing['quantity'] + $quantity;

    if ($new_qty > $stock) {
        send_error("Only $stock unit(s) available. You already have {$existing['quantity']} in your cart.", 400);
    }

    $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?')
        ->execute([$new_qty, $existing['cart_item_id']]);
} else {
    // Insert new item
    if ($quantity > $stock) {
        send_error("Only $stock unit(s) available.", 400);
    }

    $pdo->prepare('
        INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, unit_price)
        VALUES (?, ?, ?, ?, ?)
    ')->execute([$cart_id, $product_id, $variant_id, $quantity, $unit_price]);
}

// ---- 6. Return updated cart -------------------------------
$items  = get_cart_items($pdo, $cart_id);
$totals = calculate_totals($items);

send_success([
    'cart_id' => $cart_id,
    'items'   => $items,
    'totals'  => $totals,
    'count'   => count($items),
], 200, 'Item added to cart.');