<?php
// ============================================================
//  api/admin/coupons.php
//  GET    /api/admin/coupons           → list coupons
//  POST   /api/admin/coupons           → create coupon
//  PUT    /api/admin/coupons?id=5      → update coupon
//  DELETE /api/admin/coupons?id=5      → delete coupon
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
$id     = clean_int($_GET['id'] ?? null);

// ============================================================
// GET — list all coupons
// ============================================================
if ($method === 'GET') {
    $stmt = $pdo->query('
        SELECT coupon_id, code, description, discount_type,
               discount_value, min_order_amt, max_discount,
               usage_limit, used_count, per_user_limit,
               is_active, starts_at, expires_at, created_at
        FROM coupons
        ORDER BY created_at DESC
    ');
    send_success($stmt->fetchAll());
}

// ============================================================
// POST — create coupon
// ============================================================
if ($method === 'POST') {
    $data = get_json_body();

    $missing = validate_required($data, ['code', 'discount_type', 'discount_value']);
    if ($missing) send_error('Missing required fields.', 400, $missing);

    $code           = strtoupper(clean_str($data['code']));
    $discount_type  = clean_str($data['discount_type']);
    $discount_value = clean_float($data['discount_value']);

    if (!in_array($discount_type, ['percent', 'fixed'])) {
        send_error('discount_type must be "percent" or "fixed".', 400);
    }
    if ($discount_type === 'percent' && $discount_value > 100) {
        send_error('Percent discount cannot exceed 100.', 400);
    }

    // Check unique code
    $chk = $pdo->prepare('SELECT coupon_id FROM coupons WHERE code = ?');
    $chk->execute([$code]);
    if ($chk->fetch()) send_error('Coupon code already exists.', 409);

    $pdo->prepare('
        INSERT INTO coupons
            (code, description, discount_type, discount_value,
             min_order_amt, max_discount, usage_limit, per_user_limit,
             is_active, starts_at, expires_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ')->execute([
        $code,
        clean_str($data['description']    ?? ''),
        $discount_type,
        $discount_value,
        clean_float($data['min_order_amt']  ?? 0),
        isset($data['max_discount'])   ? clean_float($data['max_discount'])  : null,
        isset($data['usage_limit'])    ? clean_int($data['usage_limit'])     : null,
        clean_int($data['per_user_limit'] ?? 1),
        isset($data['is_active'])      ? (int)$data['is_active'] : 1,
        $data['starts_at']  ?? null,
        $data['expires_at'] ?? null,
    ]);

    send_success(['coupon_id' => (int)$pdo->lastInsertId()], 201, 'Coupon created.');
}

// ============================================================
// PUT — update coupon
// ============================================================
if ($method === 'PUT') {
    if (!$id) send_error('Coupon ID is required.', 400);

    $data    = get_json_body();
    $allowed = ['description','discount_type','discount_value','min_order_amt',
                'max_discount','usage_limit','per_user_limit','is_active','starts_at','expires_at'];

    $set = []; $params = [];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $data)) {
            $set[]    = "$field = ?";
            $params[] = $data[$field];
        }
    }

    if (empty($set)) send_error('Nothing to update.', 400);

    $params[] = $id;
    $pdo->prepare('UPDATE coupons SET ' . implode(', ', $set) . ' WHERE coupon_id = ?')
        ->execute($params);

    send_success(null, 200, 'Coupon updated.');
}

// ============================================================
// DELETE — remove coupon
// ============================================================
if ($method === 'DELETE') {
    if (!$id) send_error('Coupon ID is required.', 400);
    $pdo->prepare('DELETE FROM coupons WHERE coupon_id = ?')->execute([$id]);
    send_success(null, 200, 'Coupon deleted.');
}