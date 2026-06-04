<?php
// ============================================================
//  api/payments/create_order.php
//  POST /api/payments/create_order
//  Body: { order_id }
//  Creates a Razorpay order and returns the order details
//  needed to initialise the frontend checkout popup.
//  Requires login.
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/razorpay.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/auth.php';

allow_methods(['POST']);
require_login();

$data     = get_json_body();
$order_id = clean_int($data['order_id'] ?? null);

if (!$order_id) {
    send_error('order_id is required.', 400);
}

// ── Load our order ─────────────────────────────────────────
$stmt = $pdo->prepare('
    SELECT o.order_id, o.order_number, o.total_amt, o.user_id,
           u.first_name, u.last_name, u.email, u.phone
    FROM orders o
    JOIN users u ON u.user_id = o.user_id
    WHERE o.order_id = ? AND o.user_id = ?
    LIMIT 1
');
$stmt->execute([$order_id, current_user_id()]);
$order = $stmt->fetch();

if (!$order) {
    send_error('Order not found.', 404);
}

// ── Check payment not already completed ────────────────────
$pay_stmt = $pdo->prepare('
    SELECT status FROM payments WHERE order_id = ? LIMIT 1
');
$pay_stmt->execute([$order_id]);
$payment = $pay_stmt->fetch();

if ($payment && $payment['status'] === 'completed') {
    send_error('This order has already been paid.', 400);
}

// ── Create Razorpay order ──────────────────────────────────
try {
    $rzp_order = rzp_create_order(
        (float)$order['total_amt'],
        $order['order_number'],
        [
            'order_id'     => $order_id,
            'order_number' => $order['order_number'],
        ]
    );
} catch (RuntimeException $e) {
    error_log('Razorpay create_order failed: ' . $e->getMessage());
    send_error('Payment gateway error. Please try again.', 500);
}

// ── Store Razorpay order ID against our payment record ─────
$pdo->prepare('
    UPDATE payments
    SET transaction_id = ?
    WHERE order_id = ?
')->execute([$rzp_order['id'], $order_id]);

// ── Return everything the frontend needs ───────────────────
send_success([
    'rzp_order_id'  => $rzp_order['id'],
    'amount'        => $rzp_order['amount'],        // in paise
    'currency'      => $rzp_order['currency'],
    'key_id'        => RZP_KEY_ID,
    'order_number'  => $order['order_number'],
    'prefill' => [
        'name'    => $order['first_name'] . ' ' . $order['last_name'],
        'email'   => $order['email'],
        'contact' => $order['phone'] ?? '',
    ],
]);