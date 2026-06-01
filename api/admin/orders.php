<?php
// ============================================================
//  api/admin/orders.php
//  GET  /api/admin/orders              → list all orders
//  PUT  /api/admin/orders?id=5         → update order status
//  Requires admin
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/auth.php';

allow_methods(['GET', 'PUT']);
require_admin();

$method = $_SERVER['REQUEST_METHOD'];

// ============================================================
// GET — all orders with filters
// ============================================================
if ($method === 'GET') {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = ORDERS_PER_PAGE;
    $offset = ($page - 1) * $limit;

    $allowed_statuses = ['pending','confirmed','processing','shipped','delivered','cancelled','refunded'];
    $status = $_GET['status'] ?? '';
    $status = in_array($status, $allowed_statuses) ? $status : '';
    $search = clean_str($_GET['search'] ?? '');   // search by order number or customer name

    $where  = [];
    $params = [];

    if ($status !== '') {
        $where[]  = 'o.status = ?';
        $params[] = $status;
    }
    if ($search !== '') {
        $where[]  = '(o.order_number LIKE ? OR CONCAT(u.first_name," ",u.last_name) LIKE ?)';
        $like     = '%' . $search . '%';
        $params   = array_merge($params, [$like, $like]);
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = $pdo->prepare("
        SELECT COUNT(*) FROM orders o
        LEFT JOIN users u ON u.user_id = o.user_id
        $where_sql
    ");
    $total->execute($params);
    $total = (int)$total->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT
            o.order_id, o.order_number, o.status,
            o.total_amt, o.created_at,
            o.shipping_name, o.shipping_city, o.shipping_country,
            CONCAT(u.first_name,' ',u.last_name) AS customer_name,
            u.email AS customer_email,
            p.status AS payment_status,
            p.payment_method,
            COUNT(oi.order_item_id) AS item_count
        FROM orders o
        LEFT JOIN users u      ON u.user_id  = o.user_id
        LEFT JOIN payments p   ON p.order_id = o.order_id
        LEFT JOIN order_items oi ON oi.order_id = o.order_id
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
}

// ============================================================
// PUT — update order status + optional tracking info
// ============================================================
if ($method === 'PUT') {
    $id   = clean_int($_GET['id'] ?? null);
    $data = get_json_body();

    if (!$id) send_error('Order ID is required.', 400);

    $allowed_statuses = ['confirmed','processing','shipped','delivered','cancelled','refunded'];
    $new_status = clean_str($data['status'] ?? '');

    if (!in_array($new_status, $allowed_statuses)) {
        send_error('Invalid status value.', 400);
    }

    // Fetch current order
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_id = ? LIMIT 1');
    $stmt->execute([$id]);
    $order = $stmt->fetch();

    if (!$order) send_error('Order not found.', 404);

    $pdo->beginTransaction();

    try {
        // Update status
        $pdo->prepare('UPDATE orders SET status = ? WHERE order_id = ?')
            ->execute([$new_status, $id]);

        // Log history
        $comment = clean_str($data['comment'] ?? "Status updated to $new_status.");
        $pdo->prepare('
            INSERT INTO order_status_history (order_id, status, comment, changed_by)
            VALUES (?, ?, ?, ?)
        ')->execute([$id, $new_status, $comment, current_user_id()]);

        // If shipped — save tracking info
        if ($new_status === 'shipped') {
            $carrier        = clean_str($data['carrier']         ?? '');
            $tracking_num   = clean_str($data['tracking_number'] ?? '');
            $tracking_url   = clean_str($data['tracking_url']    ?? '');
            $est_delivery   = clean_str($data['estimated_delivery'] ?? '');

            // Upsert shipment
            $stmt = $pdo->prepare('SELECT shipment_id FROM shipments WHERE order_id = ?');
            $stmt->execute([$id]);
            $existing = $stmt->fetch();

            if ($existing) {
                $pdo->prepare('
                    UPDATE shipments SET carrier=?, tracking_number=?, tracking_url=?,
                           estimated_delivery=?, shipped_at=NOW()
                    WHERE order_id=?
                ')->execute([$carrier, $tracking_num, $tracking_url, $est_delivery ?: null, $id]);
            } else {
                $pdo->prepare('
                    INSERT INTO shipments
                        (order_id, carrier, tracking_number, tracking_url, estimated_delivery, shipped_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ')->execute([$id, $carrier, $tracking_num, $tracking_url, $est_delivery ?: null]);
            }
        }

        // If delivered — mark payment completed (for COD)
        if ($new_status === 'delivered') {
            $pdo->prepare("
                UPDATE payments SET status = 'completed', paid_at = NOW()
                WHERE order_id = ? AND payment_method = 'cod' AND status = 'pending'
            ")->execute([$id]);

            $pdo->prepare('UPDATE shipments SET delivered_at = NOW() WHERE order_id = ?')
                ->execute([$id]);
        }

        // Notify customer
        $pdo->prepare('
            INSERT INTO notifications (user_id, type, message, link)
            VALUES (?, ?, ?, ?)
        ')->execute([
            $order['user_id'],
            "order_$new_status",
            "Your order #{$order['order_number']} is now: $new_status.",
            "/pages/order_detail.php?id=$id",
        ]);

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Order update failed: ' . $e->getMessage());
        send_error('Failed to update order.', 500);
    }

    send_success(null, 200, "Order status updated to '$new_status'.");
}