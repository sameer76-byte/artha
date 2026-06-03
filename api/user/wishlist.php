<?php
// ============================================================
//  api/user/wishlist.php
//  GET    → list wishlist items
//  POST   → toggle (add if not exists, remove if exists)
//  DELETE → remove specific item
//  Requires login
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/auth.php';

allow_methods(['GET', 'POST', 'DELETE']);
require_login();

$user_id = current_user_id();
$method  = $_SERVER['REQUEST_METHOD'];

// ============================================================
// GET — list all wishlist items
// ============================================================
if ($method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT
            w.wishlist_id,
            p.product_id,
            p.name,
            p.slug,
            p.price,
            p.sale_price,
            p.stock_qty,
            img.image_url AS image,
            w.added_at
        FROM wishlists w
        JOIN products p          ON p.product_id  = w.product_id
        LEFT JOIN product_images img ON img.product_id = p.product_id AND img.is_primary = 1
        WHERE w.user_id = ? AND p.is_active = 1
        ORDER BY w.added_at DESC
    ");
    $stmt->execute([$user_id]);
    $items = $stmt->fetchAll();

    foreach ($items as &$item) {
        $item['price']      = (float)$item['price'];
        $item['sale_price'] = $item['sale_price'] ? (float)$item['sale_price'] : null;
        $item['in_stock']   = (int)$item['stock_qty'] > 0;
    }
    unset($item);

    send_success($items);
}

// ============================================================
// POST — toggle wishlist
// ============================================================
if ($method === 'POST') {
    $data       = get_json_body();
    $product_id = clean_int($data['product_id'] ?? null);
    if (!$product_id) send_error('product_id is required.', 400);

    // Check product exists
    $stmt = $pdo->prepare('SELECT product_id FROM products WHERE product_id = ? AND is_active = 1');
    $stmt->execute([$product_id]);
    if (!$stmt->fetch()) send_error('Product not found.', 404);

    // Check if already in wishlist
    $stmt = $pdo->prepare('SELECT wishlist_id FROM wishlists WHERE user_id = ? AND product_id = ?');
    $stmt->execute([$user_id, $product_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Remove
        $pdo->prepare('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?')
            ->execute([$user_id, $product_id]);
        send_success(['in_wishlist' => false], 200, 'Removed from wishlist.');
    } else {
        // Add
        $pdo->prepare('INSERT INTO wishlists (user_id, product_id) VALUES (?, ?)')
            ->execute([$user_id, $product_id]);
        send_success(['in_wishlist' => true], 200, 'Added to wishlist!');
    }
}

// ============================================================
// DELETE — remove by product_id
// ============================================================
if ($method === 'DELETE') {
    $data       = get_json_body();
    $product_id = clean_int($data['product_id'] ?? null);
    if (!$product_id) send_error('product_id is required.', 400);

    $pdo->prepare('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?')
        ->execute([$user_id, $product_id]);

    send_success(null, 200, 'Removed from wishlist.');
}