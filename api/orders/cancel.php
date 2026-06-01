<?php
// ============================================================
//  api/orders/cancel.php
//  POST /api/orders/cancel
//  Body: { order_id, reason? }
//  Only cancellable if status is pending or confirmed
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/auth.php';

allow_methods(['POST']);
require_login();

$data     = get_json_body();
$order_id = clean_int($data['order_id'] ?? null);
$reason   = clean_str($data['reason']   ?? 'Cancelled by customer.');

if (!$order_id) {
    send_error('order_id is required.', 400);
}

// ---- Fetch order ------------------------------------------
$stmt = $pdo->prepare('SELECT * FROM orders WHERE order_id = ? LIMIT 1');
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    send_error('Order not found.', 404);
}

require_owner_or_admin((int)$order['user_id']);

// ---- Only allow cancellation of early-stage orders --------
$cancellable = ['pending', 'confirmed'];
if (!in_array($order['status'], $cancellable)) {
    send_error(
        "Orders with status '{$order['status']}' cannot be cancelled. Please contact support.",
        400
    );
}

$pdo->beginTransaction();

try {
    // Update order status
    $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE order_id = ?")
        ->execute([$order_id]);

    // Log the status change
    $pdo->prepare('
        INSERT INTO order_status_history (order_id, status, comment, changed_by)
        VALUES (?, "cancelled", ?, ?)
    ')->execute([$order_id, $reason, current_user_id()]);

    // Restore stock
    $stmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();

    foreach ($items as $item) {
        if ($item['variant_id']) {
            $pdo->prepare('UPDATE product_variants SET stock_qty = stock_qty + ? WHERE variant_id = ?')
                ->execute([$item['quantity'], $item['variant_id']]);
        } else {
            $pdo->prepare('UPDATE products SET stock_qty = stock_qty + ? WHERE product_id = ?')
                ->execute([$item['quantity'], $item['product_id']]);
        }
    }

    // Mark payment as refunded if already paid
    $pdo->prepare("
        UPDATE payments SET status = 'refunded'
        WHERE order_id = ? AND status = 'completed'
    ")->execute([$order_id]);

    // Notify user
    $pdo->prepare('
        INSERT INTO notifications (user_id, type, message, link)
        VALUES (?, "order_cancelled", ?, ?)
    ')->execute([
        $order['user_id'],
        "Your order #{$order['order_number']} has been cancelled.",
        "/pages/order_detail.php?id=$order_id",
    ]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Order cancellation failed: ' . $e->getMessage());
    send_error('Could not cancel order. Please try again.', 500);
}

send_success(null, 200, "Order #{$order['order_number']} cancelled successfully.");