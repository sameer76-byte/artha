<?php
// ============================================================
//  api/orders/history.php
//  GET /api/orders/history
//  Query params: page, status
//  Requires login
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/auth.php';

allow_methods(['GET']);
require_login();

$user_id = current_user_id();
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = ORDERS_PER_PAGE;
$offset  = ($page - 1) * $limit;

$allowed_statuses = ['pending','confirmed','processing','shipped','delivered','cancelled','refunded'];
$status = $_GET['status'] ?? '';
$status = in_array($status, $allowed_statuses) ? $status : '';

// Build WHERE
$where  = ['o.user_id = ?'];
$params = [$user_id];

if ($status !== '') {
    $where[]  = 'o.status = ?';
    $params[] = $status;
}
$where_sql = 'WHERE ' . implode(' AND ', $where);

// Count total
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM orders o $where_sql");
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();

// Fetch orders
$stmt = $pdo->prepare("
    SELECT
        o.order_id,
        o.order_number,
        o.status,
        o.total_amt,
        o.created_at,
        COUNT(oi.order_item_id)  AS item_count,
        p.status                 AS payment_status,
        s.tracking_number,
        s.carrier
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id  = o.order_id
    LEFT JOIN payments p     ON p.order_id   = o.order_id
    LEFT JOIN shipments s    ON s.order_id   = o.order_id
    $where_sql
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$limit, $offset]));
$orders = $stmt->fetchAll();

foreach ($orders as &$o) {
    $o['total_amt']  = (float)$o['total_amt'];
    $o['item_count'] = (int)$o['item_count'];
}
unset($o);

send_success([
    'orders'     => $orders,
    'pagination' => [
        'total'        => $total,
        'per_page'     => $limit,
        'current_page' => $page,
        'total_pages'  => (int)ceil($total / $limit),
    ],
]);