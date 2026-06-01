<?php
// ============================================================
//  api/products/search.php
//  GET /api/products/search?q=sneak
//  Returns quick suggestions for the live search bar (AJAX)
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';

allow_methods(['GET']);

$q = clean_str($_GET['q'] ?? '');

if (strlen($q) < 2) {
    send_success([]);   // don't search for single chars
}

$like = '%' . $q . '%';

// Products
$stmt = $pdo->prepare("
    SELECT
        'product'         AS type,
        p.product_id      AS id,
        p.name            AS label,
        p.slug,
        img.image_url     AS image,
        COALESCE(p.sale_price, p.price) AS price
    FROM products p
    LEFT JOIN product_images img
           ON img.product_id = p.product_id AND img.is_primary = 1
    WHERE p.is_active = 1
      AND (p.name LIKE ? OR p.sku LIKE ?)
    ORDER BY p.name ASC
    LIMIT 5
");
$stmt->execute([$like, $like]);
$products = $stmt->fetchAll();

// Categories
$stmt = $pdo->prepare("
    SELECT
        'category'        AS type,
        c.category_id     AS id,
        c.name            AS label,
        c.slug,
        NULL              AS image,
        NULL              AS price
    FROM categories c
    WHERE c.is_active = 1 AND c.name LIKE ?
    LIMIT 3
");
$stmt->execute([$like]);
$categories = $stmt->fetchAll();

// Format prices
foreach ($products as &$p) {
    $p['price'] = (float)$p['price'];
}
unset($p);

send_success(array_merge($categories, $products));