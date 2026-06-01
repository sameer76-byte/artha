<?php
// ============================================================
//  api/products/single.php
//  GET /api/products/single.php?id=5
//  GET /api/products/single.php?slug=red-sneakers
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';

allow_methods(['GET']);

$id   = clean_int($_GET['id']   ?? null);
$slug = clean_str($_GET['slug'] ?? '');

if (!$id && $slug === '') {
    send_error('Provide a product id or slug.', 400);
}

// ---- 1. Fetch core product --------------------------------
$where  = $id ? 'p.product_id = ?' : 'p.slug = ?';
$param  = $id ?? $slug;

$stmt = $pdo->prepare("
    SELECT
        p.*,
        c.name        AS category_name,
        c.slug        AS category_slug,
        b.name        AS brand_name,
        b.logo_url    AS brand_logo,
        ROUND(AVG(r.rating), 1)     AS avg_rating,
        COUNT(DISTINCT r.review_id) AS review_count
    FROM products p
    LEFT JOIN categories c ON c.category_id = p.category_id
    LEFT JOIN brands b     ON b.brand_id    = p.brand_id
    LEFT JOIN reviews r    ON r.product_id  = p.product_id AND r.is_approved = 1
    WHERE $where AND p.is_active = 1
    GROUP BY p.product_id
    LIMIT 1
");
$stmt->execute([$param]);
$product = $stmt->fetch();

if (!$product) {
    send_error('Product not found.', 404);
}

$pid = $product['product_id'];

// ---- 2. Fetch all images ----------------------------------
$stmt = $pdo->prepare('
    SELECT image_id, image_url, alt_text, is_primary, sort_order
    FROM product_images
    WHERE product_id = ?
    ORDER BY is_primary DESC, sort_order ASC
');
$stmt->execute([$pid]);
$product['images'] = $stmt->fetchAll();

// ---- 3. Fetch attributes (e.g. Color: Red, Blue) ----------
$stmt = $pdo->prepare('
    SELECT a.attribute_id, a.name AS attribute_name,
           av.value_id, av.value
    FROM product_attributes pa
    JOIN attribute_values av ON av.value_id    = pa.value_id
    JOIN attributes a        ON a.attribute_id = av.attribute_id
    WHERE pa.product_id = ?
    ORDER BY a.attribute_id, av.value
');
$stmt->execute([$pid]);
$rows = $stmt->fetchAll();

// Group into { Color: [Red, Blue], Size: [S, M, L] }
$attributes = [];
foreach ($rows as $row) {
    $attributes[$row['attribute_name']][] = [
        'value_id' => (int)$row['value_id'],
        'value'    => $row['value'],
    ];
}
$product['attributes'] = $attributes;

// ---- 4. Fetch variants ------------------------------------
$stmt = $pdo->prepare('
    SELECT pv.variant_id, pv.sku, pv.price, pv.sale_price,
           pv.stock_qty, pv.image_url, pv.is_active,
           GROUP_CONCAT(CONCAT(a.name,":",av.value) ORDER BY a.name SEPARATOR " | ") AS variant_label
    FROM product_variants pv
    LEFT JOIN variant_attributes vat ON vat.variant_id  = pv.variant_id
    LEFT JOIN attribute_values av    ON av.value_id     = vat.value_id
    LEFT JOIN attributes a           ON a.attribute_id  = av.attribute_id
    WHERE pv.product_id = ? AND pv.is_active = 1
    GROUP BY pv.variant_id
');
$stmt->execute([$pid]);
$product['variants'] = $stmt->fetchAll();

// ---- 5. Fetch approved reviews (latest 5) -----------------
$stmt = $pdo->prepare('
    SELECT r.review_id, r.rating, r.title, r.body, r.created_at,
           CONCAT(u.first_name," ",LEFT(u.last_name,1),".") AS reviewer_name
    FROM reviews r
    LEFT JOIN users u ON u.user_id = r.user_id
    WHERE r.product_id = ? AND r.is_approved = 1
    ORDER BY r.created_at DESC
    LIMIT 5
');
$stmt->execute([$pid]);
$product['reviews'] = $stmt->fetchAll();

// ---- 6. Related products (same category, exclude self) ----
$stmt = $pdo->prepare('
    SELECT p.product_id, p.name, p.slug, p.price, p.sale_price,
           img.image_url AS primary_image
    FROM products p
    LEFT JOIN product_images img
           ON img.product_id = p.product_id AND img.is_primary = 1
    WHERE p.category_id = ? AND p.product_id != ? AND p.is_active = 1
    ORDER BY RAND()
    LIMIT 4
');
$stmt->execute([$product['category_id'], $pid]);
$product['related_products'] = $stmt->fetchAll();

// ---- 7. Clean up types ------------------------------------
$product['price']        = (float)$product['price'];
$product['sale_price']   = $product['sale_price'] ? (float)$product['sale_price'] : null;
$product['avg_rating']   = $product['avg_rating']  ? (float)$product['avg_rating']  : null;
$product['review_count'] = (int)$product['review_count'];
$product['stock_qty']    = (int)$product['stock_qty'];
$product['is_featured']  = (bool)$product['is_featured'];

send_success($product);