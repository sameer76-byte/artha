<?php
// ============================================================
//  api/orders/track.php
//  GET /api/orders/track?order_id=5
//       or ?order_number=ORD-20240601-00001
//  Public — but only shows non-sensitive shipping info
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/auth.php';

allow_methods(['GET']);

$order_id     = clean_int($_GET['order_id']     ?? null);
$order_number = clean_str($_GET['order_number'] ?? '');

if (!$order_id && $order_number === '') {
    send_error('Provide order_id or order_number.', 400);
}

// ---- Fetch order ------------------------------------------
if ($order_id) {
    $stmt = $pdo->prepare('SELECT order_id, user_id, order_number, status, created_at FROM orders WHERE order_id = ?');
    $stmt->execute([$order_id]);
} else {
    $stmt = $pdo->prepare('SELECT order_id, user_id, order_number, status, created_at FROM orders WHERE order_number = ?');
    $stmt->execute([$order_number]);
}
$order = $stmt->fetch();

if (!$order) {
    send_error('Order not found.', 404);
}

// If logged in, enforce ownership. If guest, allow (they need the order number to find it)
if (is_logged_in()) {
    require_owner_or_admin((int)$order['user_id']);
}

// ---- Fetch shipment & status history ----------------------
$stmt = $pdo->prepare('
    SELECT carrier, tracking_number, tracking_url,
           shipped_at, estimated_delivery, delivered_at
    FROM shipments WHERE order_id = ? LIMIT 1
');
$stmt->execute([$order['order_id']]);
$shipment = $stmt->fetch();

$stmt = $pdo->prepare('
    SELECT status, comment, changed_at
    FROM order_status_history
    WHERE order_id = ?
    ORDER BY changed_at ASC
');
$stmt->execute([$order['order_id']]);
$history = $stmt->fetchAll();

send_success([
    'order_number' => $order['order_number'],
    'status'       => $order['status'],
    'placed_at'    => $order['created_at'],
    'shipment'     => $shipment ?: null,
    'timeline'     => $history,
]);