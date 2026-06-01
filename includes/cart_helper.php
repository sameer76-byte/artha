<?php
// ============================================================
//  includes/cart_helper.php
//  Shared cart utilities used by all cart endpoints
// ============================================================

require_once __DIR__ . '/../config/db.php';

/**
 * Get the current cart ID — creates one if it doesn't exist.
 * Logged-in users   → cart tied to user_id
 * Guests            → cart tied to session_id
 */
function get_or_create_cart(PDO $pdo): int
{
    $user_id    = current_user_id();   // null if guest
    $session_id = session_id();

    if ($user_id) {
        // Look for existing user cart
        $stmt = $pdo->prepare('SELECT cart_id FROM carts WHERE user_id = ? LIMIT 1');
        $stmt->execute([$user_id]);
    } else {
        // Guest cart — keyed on session_id
        $stmt = $pdo->prepare('SELECT cart_id FROM carts WHERE session_id = ? LIMIT 1');
        $stmt->execute([$session_id]);
    }

    $cart = $stmt->fetch();

    if ($cart) {
        return (int)$cart['cart_id'];
    }

    // Create new cart
    if ($user_id) {
        $pdo->prepare('INSERT INTO carts (user_id) VALUES (?)')->execute([$user_id]);
    } else {
        $pdo->prepare('INSERT INTO carts (session_id) VALUES (?)')->execute([$session_id]);
    }

    return (int)$pdo->lastInsertId();
}

/**
 * Return all items in a cart with product details.
 */
function get_cart_items(PDO $pdo, int $cart_id): array
{
    $stmt = $pdo->prepare("
        SELECT
            ci.cart_item_id,
            ci.product_id,
            ci.variant_id,
            ci.quantity,
            ci.unit_price,
            p.name          AS product_name,
            p.slug          AS product_slug,
            p.stock_qty     AS available_stock,
            img.image_url   AS image,
            pv.sku          AS variant_sku,
            GROUP_CONCAT(
                CONCAT(a.name, ':', av.value)
                ORDER BY a.name SEPARATOR ' | '
            )               AS variant_label
        FROM cart_items ci
        JOIN products p          ON p.product_id  = ci.product_id
        LEFT JOIN product_images img
                               ON img.product_id  = p.product_id AND img.is_primary = 1
        LEFT JOIN product_variants pv
                               ON pv.variant_id   = ci.variant_id
        LEFT JOIN variant_attributes vat
                               ON vat.variant_id  = ci.variant_id
        LEFT JOIN attribute_values av
                               ON av.value_id     = vat.value_id
        LEFT JOIN attributes a ON a.attribute_id  = av.attribute_id
        WHERE ci.cart_id = ?
        GROUP BY ci.cart_item_id
    ");
    $stmt->execute([$cart_id]);
    $items = $stmt->fetchAll();

    foreach ($items as &$item) {
        $item['quantity']        = (int)$item['quantity'];
        $item['unit_price']      = (float)$item['unit_price'];
        $item['line_total']      = round($item['unit_price'] * $item['quantity'], 2);
        $item['available_stock'] = (int)$item['available_stock'];
    }
    unset($item);

    return $items;
}

/**
 * Calculate cart totals (subtotal, shipping, tax, grand total).
 */
function calculate_totals(array $items): array
{
    $subtotal = array_sum(array_column($items, 'line_total'));

    $shipping = $subtotal >= FREE_SHIPPING_ABOVE ? 0.00 : FLAT_SHIPPING_RATE;
    $tax      = round($subtotal * TAX_RATE, 2);
    $total    = round($subtotal + $shipping + $tax, 2);

    return [
        'subtotal'     => round($subtotal, 2),
        'shipping'     => $shipping,
        'tax'          => $tax,
        'total'        => $total,
        'free_shipping'=> $subtotal >= FREE_SHIPPING_ABOVE,
        'currency'     => CURRENCY_SYMBOL,
    ];
}