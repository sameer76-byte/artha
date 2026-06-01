<?php
// ============================================================
//  api/admin/products.php
//  GET    /api/admin/products          → list all products
//  POST   /api/admin/products          → create product
//  PUT    /api/admin/products?id=5     → update product
//  DELETE /api/admin/products?id=5     → soft delete product
//  Requires admin
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/auth.php';

allow_methods(['GET', 'POST', 'PUT', 'DELETE']);
require_admin();

$method = $_SERVER['REQUEST_METHOD'];

// ============================================================
// GET — list all products (admin view, includes inactive)
// ============================================================
if ($method === 'GET') {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20;
    $offset = ($page - 1) * $limit;
    $search = clean_str($_GET['search'] ?? '');

    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[]  = '(p.name LIKE ? OR p.sku LIKE ?)';
        $like     = '%' . $search . '%';
        $params   = [$like, $like];
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = $pdo->prepare("SELECT COUNT(*) FROM products p $where_sql");
    $total->execute($params);
    $total = (int)$total->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT
            p.product_id, p.name, p.sku, p.price, p.sale_price,
            p.stock_qty, p.is_active, p.is_featured, p.created_at,
            c.name AS category_name,
            b.name AS brand_name,
            img.image_url AS primary_image
        FROM products p
        LEFT JOIN categories c ON c.category_id = p.category_id
        LEFT JOIN brands b     ON b.brand_id     = p.brand_id
        LEFT JOIN product_images img
               ON img.product_id = p.product_id AND img.is_primary = 1
        $where_sql
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $products = $stmt->fetchAll();

    send_success([
        'products'   => $products,
        'pagination' => [
            'total'        => $total,
            'per_page'     => $limit,
            'current_page' => $page,
            'total_pages'  => (int)ceil($total / $limit),
        ],
    ]);
}

// ============================================================
// POST — create new product
// ============================================================
if ($method === 'POST') {
    $data = get_json_body();

    $missing = validate_required($data, ['name', 'category_id', 'price', 'sku']);
    if ($missing) send_error('Missing required fields.', 400, $missing);

    $name        = clean_str($data['name']);
    $slug        = make_slug($name);
    $category_id = clean_int($data['category_id']);
    $brand_id    = isset($data['brand_id'])   ? clean_int($data['brand_id'])    : null;
    $price       = clean_float($data['price']);
    $sale_price  = isset($data['sale_price']) ? clean_float($data['sale_price']) : null;
    $cost_price  = isset($data['cost_price']) ? clean_float($data['cost_price']) : null;
    $sku         = clean_str($data['sku']);
    $stock_qty   = clean_int($data['stock_qty']   ?? 0);
    $description = clean_str($data['description'] ?? '');
    $short_desc  = clean_str($data['short_desc']  ?? '');
    $is_featured = (int)!empty($data['is_featured']);
    $is_active   = isset($data['is_active']) ? (int)$data['is_active'] : 1;
    $meta_title  = clean_str($data['meta_title']  ?? $name);
    $meta_desc   = clean_str($data['meta_desc']   ?? $short_desc);

    if (!$price) send_error('Invalid price.', 400);

    // Ensure unique slug
    $base_slug = $slug;
    $i = 1;
    while (true) {
        $chk = $pdo->prepare('SELECT product_id FROM products WHERE slug = ?');
        $chk->execute([$slug]);
        if (!$chk->fetch()) break;
        $slug = $base_slug . '-' . $i++;
    }

    // Ensure unique SKU
    $chk = $pdo->prepare('SELECT product_id FROM products WHERE sku = ?');
    $chk->execute([$sku]);
    if ($chk->fetch()) send_error('SKU already exists.', 409);

    $stmt = $pdo->prepare('
        INSERT INTO products
            (category_id, brand_id, name, slug, description, short_desc,
             sku, price, sale_price, cost_price, stock_qty,
             is_active, is_featured, meta_title, meta_desc)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ');
    $stmt->execute([
        $category_id, $brand_id, $name, $slug, $description, $short_desc,
        $sku, $price, $sale_price, $cost_price, $stock_qty,
        $is_active, $is_featured, $meta_title, $meta_desc,
    ]);
    $product_id = (int)$pdo->lastInsertId();

    send_success(['product_id' => $product_id, 'slug' => $slug], 201, 'Product created.');
}

// ============================================================
// PUT — update existing product
// ============================================================
if ($method === 'PUT') {
    $id = clean_int($_GET['id'] ?? null);
    if (!$id) send_error('Product ID is required.', 400);

    $stmt = $pdo->prepare('SELECT product_id FROM products WHERE product_id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) send_error('Product not found.', 404);

    $data = get_json_body();

    // Build dynamic SET clause from provided fields only
    $allowed = [
        'name','category_id','brand_id','description','short_desc',
        'price','sale_price','cost_price','sku','stock_qty',
        'is_active','is_featured','meta_title','meta_desc',
    ];
    $set    = [];
    $params = [];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $data)) {
            $set[]    = "$field = ?";
            $params[] = in_array($field, ['price','sale_price','cost_price'])
                ? clean_float($data[$field])
                : (in_array($field, ['category_id','brand_id','stock_qty','is_active','is_featured'])
                    ? clean_int($data[$field])
                    : clean_str($data[$field]));
        }
    }

    if (empty($set)) send_error('No fields to update.', 400);

    // Update slug if name changed
    if (array_key_exists('name', $data)) {
        $set[]    = 'slug = ?';
        $params[] = make_slug($data['name']);
    }

    $params[] = $id;
    $pdo->prepare('UPDATE products SET ' . implode(', ', $set) . ' WHERE product_id = ?')
        ->execute($params);

    send_success(null, 200, 'Product updated.');
}

// ============================================================
// DELETE — soft delete (deactivate)
// ============================================================
if ($method === 'DELETE') {
    $id = clean_int($_GET['id'] ?? null);
    if (!$id) send_error('Product ID is required.', 400);

    $pdo->prepare('UPDATE products SET is_active = 0 WHERE product_id = ?')->execute([$id]);

    send_success(null, 200, 'Product deactivated.');
}