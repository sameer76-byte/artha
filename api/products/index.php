<?php
// ============================================================
//  api/products/index.php
//  GET /api/products
//  Query params:
//    page        (int)    default 1
//    limit       (int)    default 12
//    category    (int)    category_id
//    brand       (int)    brand_id
//    search      (string) keyword search
//    min_price   (float)
//    max_price   (float)
//    sort        (string) price_asc | price_desc | newest | popular
//    featured    (1)      only featured products
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';

allow_methods(['GET']);

// ---- Read & sanitize query params -------------------------
$page       = max(1, (int)($_GET['page']    ?? 1));
$limit      = min(50, max(1, (int)($_GET['limit'] ?? PRODUCTS_PER_PAGE)));
$offset     = ($page - 1) * $limit;

$category   = clean_int($_GET['category']  ?? null);
$brand      = clean_int($_GET['brand']     ?? null);
$search     = clean_str($_GET['search']    ?? '');
$min_price  = clean_float($_GET['min_price'] ?? null);
$max_price  = clean_float($_GET['max_price'] ?? null);
$featured   = isset($_GET['featured']) ? 1 : null;

$allowed_sorts = ['price_asc', 'price_desc', 'newest', 'popular'];
$sort = in_array($_GET['sort'] ?? '', $allowed_sorts) ? $_GET['sort'] : 'newest';

$sort_sql = match($sort) {
    'price_asc'  => 'COALESCE(p.sale_price, p.price) ASC',
    'price_desc' => 'COALESCE(p.sale_price, p.price) DESC',
    'popular'    => 'avg_rating DESC',
    default      => 'p.created_at DESC',   // newest
};

// ---- Build dynamic WHERE clause ---------------------------
$where  = ['p.is_active = 1'];
$params = [];

if ($category) {
    $where[]  = 'p.category_id = ?';
    $params[] = $category;
}
if ($brand) {
    $where[]  = 'p.brand_id = ?';
    $params[] = $brand;
}
if ($search !== '') {
    $where[]  = '(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like]);
}
if ($min_price !== null) {
    $where[]  = 'COALESCE(p.sale_price, p.price) >= ?';
    $params[] = $min_price;
}
if ($max_price !== null) {
    $where[]  = 'COALESCE(p.sale_price, p.price) <= ?';
    $params[] = $max_price;
}
if ($featured) {
    $where[]  = 'p.is_featured = 1';
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

// ---- Count total (for pagination) -------------------------
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM products p $where_sql
");
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();

// ---- Fetch products ---------------------------------------
$stmt = $pdo->prepare("
    SELECT
        p.product_id,
        p.name,
        p.slug,
        p.short_desc,
        p.sku,
        p.price,
        p.sale_price,
        p.stock_qty,
        p.is_featured,
        c.name        AS category_name,
        b.name        AS brand_name,
        img.image_url AS primary_image,
        ROUND(AVG(r.rating), 1)  AS avg_rating,
        COUNT(DISTINCT r.review_id) AS review_count
    FROM products p
    LEFT JOIN categories c    ON c.category_id = p.category_id
    LEFT JOIN brands b        ON b.brand_id    = p.brand_id
    LEFT JOIN product_images img
           ON img.product_id = p.product_id AND img.is_primary = 1
    LEFT JOIN reviews r
           ON r.product_id   = p.product_id AND r.is_approved = 1
    $where_sql
    GROUP BY p.product_id
    ORDER BY $sort_sql
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$limit, $offset]));
$products = $stmt->fetchAll();

// ---- Format prices as floats ------------------------------
foreach ($products as &$p) {
    $p['price']      = (float)$p['price'];
    $p['sale_price'] = $p['sale_price'] !== null ? (float)$p['sale_price'] : null;
    $p['avg_rating'] = $p['avg_rating']  !== null ? (float)$p['avg_rating'] : null;
    $p['review_count'] = (int)$p['review_count'];
    $p['stock_qty']    = (int)$p['stock_qty'];
}
unset($p);

send_success([
    'products'   => $products,
    'pagination' => [
        'total'        => $total,
        'per_page'     => $limit,
        'current_page' => $page,
        'total_pages'  => (int)ceil($total / $limit),
    ],
]);