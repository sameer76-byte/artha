<?php
// ============================================================
//  api/orders/place.php
//  POST /api/orders/place
//  Body: { address_id, payment_method }
//  Requires login
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/cart_helper.php';

allow_methods(['POST']);
require_login();

$data = get_json_body();

$missing = validate_required($data, ['address_id', 'payment_method']);
if ($missing) {
    send_error('Missing required fields.', 400, $missing);
}

$address_id     = clean_int($data['address_id']);
$payment_method = clean_str($data['payment_method']);
$user_id        = current_user_id();

$allowed_methods = ['cod', 'stripe', 'razorpay', 'paypal'];
if (!in_array($payment_method, $allowed_methods)) {
    send_error('Invalid payment method.', 400);
}

// ---- 1. Load & validate cart ------------------------------
$cart_id = get_or_create_cart($pdo);
$items   = get_cart_items($pdo, $cart_id);

if (empty($items)) {
    send_error('Your cart is empty.', 400);
}

// ---- 2. Re-validate stock for every item ------------------
foreach ($items as $item) {
    $stock = $item['available_stock'];
    if ($item['quantity'] > $stock) {
        send_error(
            "'{$item['product_name']}' only has $stock unit(s) left. Please update your cart.",
            400
        );
    }
}

// ---- 3. Load shipping address (must belong to user) -------
$stmt = $pdo->prepare('
    SELECT * FROM user_addresses
    WHERE address_id = ? AND user_id = ?
    LIMIT 1
');
$stmt->execute([$address_id, $user_id]);
$address = $stmt->fetch();

if (!$address) {
    send_error('Shipping address not found.', 404);
}

// ---- 4. Calculate totals ----------------------------------
$totals      = calculate_totals($items);
$subtotal    = $totals['subtotal'];
$shipping    = $totals['shipping'];
$tax         = $totals['tax'];
$discount    = 0.00;
$coupon_id   = null;

// Apply session coupon if present
if (!empty($_SESSION['coupon'])) {
    $discount  = (float)$_SESSION['coupon']['discount_amt'];
    $coupon_id = (int)$_SESSION['coupon']['coupon_id'];
}

$total = max(0, round($subtotal + $shipping + $tax - $discount, 2));

// ---- 5. Begin transaction ---------------------------------
$pdo->beginTransaction();

try {
    // 5a. Create order
    $order_number = generate_order_number();

    $stmt = $pdo->prepare('
        INSERT INTO orders (
            user_id, order_number, status,
            shipping_name, shipping_phone,
            shipping_addr1, shipping_addr2,
            shipping_city, shipping_state,
            shipping_postal, shipping_country,
            subtotal, discount_amt, shipping_amt, tax_amt, total_amt,
            coupon_id
        ) VALUES (
            ?, ?, "pending",
            ?, ?,
            ?, ?,
            ?, ?,
            ?, ?,
            ?, ?, ?, ?, ?,
            ?
        )
    ');
    $stmt->execute([
        $user_id, $order_number,
        $address['full_name'], $address['phone'],
        $address['address_line1'], $address['address_line2'],
        $address['city'], $address['state'],
        $address['postal_code'], $address['country'],
        $subtotal, $discount, $shipping, $tax, $total,
        $coupon_id,
    ]);
    $order_id = (int)$pdo->lastInsertId();

    // 5b. Insert order items + deduct stock
    $item_stmt = $pdo->prepare('
        INSERT INTO order_items
            (order_id, product_id, variant_id, product_name, variant_info, sku, quantity, unit_price, total_price)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stock_stmt = $pdo->prepare('
        UPDATE products SET stock_qty = stock_qty - ? WHERE product_id = ?
    ');
    $variant_stock_stmt = $pdo->prepare('
        UPDATE product_variants SET stock_qty = stock_qty - ? WHERE variant_id = ?
    ');

    foreach ($items as $item) {
        $item_stmt->execute([
            $order_id,
            $item['product_id'],
            $item['variant_id'],
            $item['product_name'],
            $item['variant_label'],
            $item['variant_sku'] ?? '',
            $item['quantity'],
            $item['unit_price'],
            $item['line_total'],
        ]);

        // Deduct stock
        if ($item['variant_id']) {
            $variant_stock_stmt->execute([$item['quantity'], $item['variant_id']]);
        } else {
            $stock_stmt->execute([$item['quantity'], $item['product_id']]);
        }
    }

    // 5c. Record initial order status
    $pdo->prepare('
        INSERT INTO order_status_history (order_id, status, comment, changed_by)
        VALUES (?, "pending", "Order placed.", ?)
    ')->execute([$order_id, $user_id]);

    // 5d. Create payment record
    $pdo->prepare('
        INSERT INTO payments (order_id, payment_method, amount, currency, status)
        VALUES (?, ?, ?, ?, ?)
    ')->execute([
        $order_id,
        $payment_method,
        $total,
        CURRENCY,
        $payment_method === 'cod' ? 'pending' : 'pending',
    ]);

    // 5e. Increment coupon usage
    if ($coupon_id) {
        $pdo->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE coupon_id = ?')
            ->execute([$coupon_id]);
    }

    // 5f. Clear the cart
    $pdo->prepare('DELETE FROM cart_items WHERE cart_id = ?')->execute([$cart_id]);
    unset($_SESSION['coupon']);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Order placement failed: ' . $e->getMessage());
    send_error('Failed to place order. Please try again.', 500);
}

// ---- 6. Send confirmation notification --------------------
$pdo->prepare('
    INSERT INTO notifications (user_id, type, message, link)
    VALUES (?, "order_placed", ?, ?)
')->execute([
    $user_id,
    "Your order #$order_number has been placed successfully!",
    "/pages/order_detail.php?id=$order_id",
]);

send_success([
    'order_id'     => $order_id,
    'order_number' => $order_number,
    'total'        => $total,
    'payment_method' => $payment_method,
], 201, "Order #$order_number placed successfully!");