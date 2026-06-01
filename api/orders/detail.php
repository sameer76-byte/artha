<?php
// ============================================================
//  api/orders/detail.php
//  GET /api/orders/detail?id=5
//  Requires login — user can only see their own orders
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/auth.php';

allow_methods(['GET']);
require_login();

$order_id = clean_int($_GET['id'] ?? null);
if (!$order_id) {
    send_error('Order ID is required.', 400);
}

// ---- 1. Fetch order ---------------------------------------
$stmt = $pdo->prepare('
    SELECT o.*, c.code AS coupon_code
    FROM orders o
    LEFT JOIN coupons c ON c.coupon_id = o.coupon_id
    WHERE o.order_id = ?
    LIMIT 1
');
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    send_error('Order not found.', 404);
}

// Only owner or admin can view
require_owner_or_admin((int)$order['user_id']);

// ---- 2. Order items ---------------------------------------
$stmt = $pdo->prepare('
    SELECT oi.*,
           img.image_url AS product_image,
           p.slug        AS product_slug
    FROM order_items oi
    LEFT JOIN products p ON p.product_id = oi.product_id
    LEFT JOIN product_images img
           ON img.product_id = oi.product_id AND img.is_primary = 1
    WHERE oi.order_id = ?
');
$stmt->execute([$order_id]);
$order['items'] = $stmt->fetchAll();

// ---- 3. Payment info --------------------------------------
$stmt = $pdo->prepare('
    SELECT payment_method, amount, currency, status, transaction_id, paid_at
    FROM payments
    WHERE order_id = ?
    ORDER BY created_at DESC
    LIMIT 1
');
$stmt->execute([$order_id]);
$order['payment'] = $stmt->fetch();

// ---- 4. Shipment / tracking --------------------------------
$stmt = $pdo->prepare('
    SELECT carrier, tracking_number, tracking_url,
           shipped_at, estimated_delivery, delivered_at
    FROM shipments
    WHERE order_id = ?
    LIMIT 1
');
$stmt->execute([$order_id]);
$order['shipment'] = $stmt->fetch();

// ---- 5. Status history ------------------------------------
$stmt = $pdo->prepare('
    SELECT status, comment, changed_at
    FROM order_status_history
    WHERE order_id = ?
    ORDER BY changed_at ASC
');
$stmt->execute([$order_id]);
$order['status_history'] = $stmt->fetchAll();

// ---- 6. Type casting --------------------------------------
$order['subtotal']     = (float)$order['subtotal'];
$order['discount_amt'] = (float)$order['discount_amt'];
$order['shipping_amt'] = (float)$order['shipping_amt'];
$order['tax_amt']      = (float)$order['tax_amt'];
$order['total_amt']    = (float)$order['total_amt'];

foreach ($order['items'] as &$item) {
    $item['unit_price']  = (float)$item['unit_price'];
    $item['total_price'] = (float)$item['total_price'];
    $item['quantity']    = (int)$item['quantity'];
}
unset($item);

send_success($order);