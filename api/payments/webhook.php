<?php
// ============================================================
//  api/payments/webhook.php
//  Razorpay sends POST requests here for every payment event.
//  Configure this URL in Razorpay Dashboard → Webhooks:
//      https://yourdomain.com/api/payments/webhook.php
//  Set a Webhook Secret in the dashboard and paste it below.
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/razorpay.php';

define('RZP_WEBHOOK_SECRET', 'YOUR_WEBHOOK_SECRET_HERE');   // set in Razorpay dashboard

// ── Read raw body ──────────────────────────────────────────
$raw_body  = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

// ── Verify webhook signature ───────────────────────────────
$expected = hash_hmac('sha256', $raw_body, RZP_WEBHOOK_SECRET);
if (!hash_equals($expected, $signature)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$event = json_decode($raw_body, true);
if (!$event) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$event_type = $event['event'] ?? '';
$payload    = $event['payload']['payment']['entity'] ?? [];

error_log("Razorpay webhook received: $event_type");

// ── Handle events ──────────────────────────────────────────
switch ($event_type) {

    case 'payment.captured':
        // Payment successfully captured
        handlePaymentCaptured($payload, $pdo);
        break;

    case 'payment.failed':
        // Payment failed
        handlePaymentFailed($payload, $pdo);
        break;

    case 'refund.created':
        // Refund initiated
        handleRefundCreated($event['payload']['refund']['entity'] ?? [], $pdo);
        break;

    default:
        // Ignore other events
        break;
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
exit;


// ── Handlers ──────────────────────────────────────────────

function handlePaymentCaptured(array $payment, PDO $pdo): void
{
    $rzp_order_id = $payment['order_id'] ?? '';
    $rzp_pay_id   = $payment['id']       ?? '';

    if (!$rzp_order_id) return;

    // Find our order by Razorpay order ID stored in transaction_id
    $stmt = $pdo->prepare('
        SELECT p.order_id, o.order_number, o.user_id
        FROM payments p
        JOIN orders o ON o.order_id = p.order_id
        WHERE p.transaction_id = ? LIMIT 1
    ');
    $stmt->execute([$rzp_order_id]);
    $row = $stmt->fetch();

    if (!$row) {
        error_log("Webhook: no order found for rzp_order_id $rzp_order_id");
        return;
    }

    // Idempotency: only update if not already completed
    $check = $pdo->prepare('SELECT status FROM payments WHERE order_id = ?');
    $check->execute([$row['order_id']]);
    if ($check->fetchColumn() === 'completed') return;

    $pdo->prepare('
        UPDATE payments
        SET transaction_id = ?, gateway_response = ?, status = "completed", paid_at = NOW()
        WHERE order_id = ?
    ')->execute([$rzp_pay_id, json_encode($payment), $row['order_id']]);

    $pdo->prepare("UPDATE orders SET status = 'confirmed' WHERE order_id = ?")
        ->execute([$row['order_id']]);

    $pdo->prepare('
        INSERT INTO order_status_history (order_id, status, comment)
        VALUES (?, "confirmed", "Payment confirmed via Razorpay webhook.")
    ')->execute([$row['order_id']]);

    error_log("Webhook: payment confirmed for order #{$row['order_number']}");
}

function handlePaymentFailed(array $payment, PDO $pdo): void
{
    $rzp_order_id = $payment['order_id'] ?? '';
    if (!$rzp_order_id) return;

    $stmt = $pdo->prepare('
        SELECT order_id FROM payments WHERE transaction_id = ? LIMIT 1
    ');
    $stmt->execute([$rzp_order_id]);
    $row = $stmt->fetch();
    if (!$row) return;

    $pdo->prepare('
        UPDATE payments SET status = "failed", gateway_response = ? WHERE order_id = ?
    ')->execute([json_encode($payment), $row['order_id']]);

    error_log("Webhook: payment FAILED for order_id {$row['order_id']}");
}

function handleRefundCreated(array $refund, PDO $pdo): void
{
    $rzp_pay_id = $refund['payment_id'] ?? '';
    if (!$rzp_pay_id) return;

    $stmt = $pdo->prepare('SELECT order_id FROM payments WHERE transaction_id = ? LIMIT 1');
    $stmt->execute([$rzp_pay_id]);
    $row = $stmt->fetch();
    if (!$row) return;

    $pdo->prepare("UPDATE payments SET status = 'refunded' WHERE order_id = ?")
        ->execute([$row['order_id']]);

    $pdo->prepare("UPDATE orders SET status = 'refunded' WHERE order_id = ?")
        ->execute([$row['order_id']]);

    $pdo->prepare('
        INSERT INTO order_status_history (order_id, status, comment)
        VALUES (?, "refunded", "Refund processed via Razorpay.")
    ')->execute([$row['order_id']]);

    error_log("Webhook: refund processed for order_id {$row['order_id']}");
}