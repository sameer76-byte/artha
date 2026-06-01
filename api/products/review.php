<?php
// ============================================================
//  api/products/review.php
//  POST /api/products/review
//  Body: { product_id, rating, title, body }
//  Requires login + must have purchased the product
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/auth.php';

allow_methods(['POST']);
require_login();

$data = get_json_body();

$missing = validate_required($data, ['product_id', 'rating']);
if ($missing) {
    send_error('Missing required fields.', 400, $missing);
}

$product_id = clean_int($data['product_id']);
$rating     = clean_int($data['rating']);
$title      = clean_str($data['title'] ?? '');
$body       = clean_str($data['body']  ?? '');
$user_id    = current_user_id();

if (!$product_id) {
    send_error('Invalid product ID.', 400);
}
if ($rating < 1 || $rating > 5) {
    send_error('Rating must be between 1 and 5.', 400);
}

// 1. Check product exists
$stmt = $pdo->prepare('SELECT product_id FROM products WHERE product_id = ? AND is_active = 1');
$stmt->execute([$product_id]);
if (!$stmt->fetch()) {
    send_error('Product not found.', 404);
}

// 2. Check user has purchased this product (verified review)
$stmt = $pdo->prepare('
    SELECT oi.order_item_id
    FROM order_items oi
    JOIN orders o ON o.order_id = oi.order_id
    WHERE oi.product_id = ? AND o.user_id = ? AND o.status = "delivered"
    LIMIT 1
');
$stmt->execute([$product_id, $user_id]);
$order_item = $stmt->fetch();

if (!$order_item) {
    send_error('You can only review products you have purchased and received.', 403);
}

// 3. Check not already reviewed
$stmt = $pdo->prepare('
    SELECT review_id FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1
');
$stmt->execute([$product_id, $user_id]);
if ($stmt->fetch()) {
    send_error('You have already reviewed this product.', 409);
}

// 4. Insert review (pending approval)
$stmt = $pdo->prepare('
    INSERT INTO reviews (product_id, user_id, rating, title, body, is_approved)
    VALUES (?, ?, ?, ?, ?, 0)
');
$stmt->execute([$product_id, $user_id, $rating, $title ?: null, $body ?: null]);

send_success(null, 201, 'Review submitted and is pending approval. Thank you!');