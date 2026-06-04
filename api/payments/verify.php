<?php
// ============================================================
//  api/payments/verify.php
//  POST /api/payments/verify
//  Body: { order_id, razorpay_order_id, razorpay_payment_id, razorpay_signature }
//  Called by the frontend after the Razorpay popup closes successfully.
//  Verifies the signature and marks the order as paid.
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/razorpay.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/auth.php';

allow_methods(['POST']);
require_login();

$data = get_json_body();

$order_id          = clean_int($data['order_id']            ?? null);
$rzp_order_id      = clean_str($data['razorpay_order_id']   ?? '');
$rzp_payment_id    = clean_str($data['razorpay_payment_id'] ?? '');
$rzp_signature     = clean_str($data['razorpay_signature']  ?? '');

if (!$order_id || !$rzp_order_id || !$rzp_payment_id || !$rzp_signature) {
    send_error('Missing payment verification fields.', 400);
}

// ── Verify signature ───────────────────────────────────────
if (!rzp_verify_signature($rzp_order_id, $rzp_payment_id, $rzp_signature)) {
    error_log("Razorpay signature mismatch for order $order_id");
    send_error('Payment verification failed. Please contact support.', 400);
}

// ── Load our order ─────────────────────────────────────────
$stmt = $pdo->prepare('
    SELECT order_id, order_number, user_id, status, total_amt
    FROM orders WHERE order_id = ? AND user_id = ? LIMIT 1
');
$stmt->execute([$order_id, current_user_id()]);
$order = $stmt->fetch();

if (!$order) {
    send_error('Order not found.', 404);
}

// ── Fetch payment details from Razorpay to double-check amount ──
$rzp_payment = rzp_fetch_payment($rzp_payment_id);

if (empty($rzp_payment['id'])) {
    send_error('Could not fetch payment details from gateway.', 500);
}

// Verify amount matches (Razorpay returns paise)
$expected_paise = (int)round((float)$order['total_amt'] * 100);
if ((int)$rzp_payment['amount'] !== $expected_paise) {
    error_log("Razorpay amount mismatch: expected $expected_paise, got {$rzp_payment['amount']}");
    send_error('Payment amount mismatch. Please contact support.', 400);
}

$pdo->beginTransaction();

try {
    // Update payment record
    $pdo->prepare('
        UPDATE payments
        SET transaction_id    = ?,
            gateway_response  = ?,
            status            = "completed",
            paid_at           = NOW()
        WHERE order_id = ?
    ')->execute([
        $rzp_payment_id,
        json_encode($rzp_payment),
        $order_id,
    ]);

    // Update order status to confirmed
    $pdo->prepare("
        UPDATE orders SET status = 'confirmed' WHERE order_id = ?
    ")->execute([$order_id]);

    // Log status history
    $pdo->prepare('
        INSERT INTO order_status_history (order_id, status, comment, changed_by)
        VALUES (?, "confirmed", "Payment received via Razorpay.", ?)
    ')->execute([$order_id, current_user_id()]);

    // Notify user
    $pdo->prepare('
        INSERT INTO notifications (user_id, type, message, link)
        VALUES (?, "payment_received", ?, ?)
    ')->execute([
        current_user_id(),
        "Payment confirmed for order #{$order['order_number']}.",
        "/pages/order_detail.php?id=$order_id",
    ]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Payment verification DB error: ' . $e->getMessage());
    send_error('Payment recorded but order update failed. Please contact support.', 500);
}

send_success([
    'order_id'     => $order_id,
    'order_number' => $order['order_number'],
], 200, 'Payment successful! Your order is confirmed.');