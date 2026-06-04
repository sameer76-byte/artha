<?php
// ============================================================
//  config/razorpay.php
//  Razorpay API credentials & helper functions
//  Get your keys from https://dashboard.razorpay.com/app/keys
// ============================================================

define('RZP_KEY_ID',     'rzp_test_DEMO_KEY');
define('RZP_KEY_SECRET', 'DEMO_SECRET');    // replace with your key secret
define('RZP_CURRENCY',   'INR');

/**
 * Create a Razorpay order via their REST API.
 * Returns the order object or throws on failure.
 */
function rzp_create_order(float $amount_inr, string $receipt, array $notes = []): array
{
    // Razorpay expects amount in paise (1 INR = 100 paise)
    $payload = [
        'amount'          => (int)round($amount_inr * 100),
        'currency'        => RZP_CURRENCY,
        'receipt'         => $receipt,
        'notes'           => $notes,
        'payment_capture' => 1,   // auto-capture
    ];

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_USERPWD        => RZP_KEY_ID . ':' . RZP_KEY_SECRET,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($http_code !== 200 || empty($data['id'])) {
        $err = $data['error']['description'] ?? 'Razorpay API error';
        throw new RuntimeException($err);
    }

    return $data;
}

/**
 * Verify the payment signature returned by Razorpay after payment.
 * This prevents tampered responses.
 */
function rzp_verify_signature(string $order_id, string $payment_id, string $signature): bool
{
    $expected = hash_hmac('sha256', $order_id . '|' . $payment_id, RZP_KEY_SECRET);
    return hash_equals($expected, $signature);
}

/**
 * Fetch a payment's details from Razorpay.
 */
function rzp_fetch_payment(string $payment_id): array
{
    $ch = curl_init("https://api.razorpay.com/v1/payments/{$payment_id}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => RZP_KEY_ID . ':' . RZP_KEY_SECRET,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?? [];
}