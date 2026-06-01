<?php
// ============================================================
//  api/admin/dashboard.php
//  GET /api/admin/dashboard
//  Returns sales stats, recent orders, low stock alerts
//  Requires admin
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';

allow_methods(['GET']);
require_admin();

// ---- 1. Sales overview ------------------------------------
$sales = $pdo->query("
    SELECT
        COUNT(*)                                          AS total_orders,
        COALESCE(SUM(total_amt), 0)                       AS total_revenue,
        COALESCE(SUM(CASE WHEN status = 'pending'   THEN 1 END), 0) AS pending,
        COALESCE(SUM(CASE WHEN status = 'confirmed' THEN 1 END), 0) AS confirmed,
        COALESCE(SUM(CASE WHEN status = 'shipped'   THEN 1 END), 0) AS shipped,
        COALESCE(SUM(CASE WHEN status = 'delivered' THEN 1 END), 0) AS delivered,
        COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 END), 0) AS cancelled
    FROM orders
")->fetch();

// ---- 2. Today's stats -------------------------------------
$today = $pdo->query("
    SELECT
        COUNT(*)                   AS orders_today,
        COALESCE(SUM(total_amt),0) AS revenue_today
    FROM orders
    WHERE DATE(created_at) = CURDATE()
")->fetch();

// ---- 3. This month's revenue (daily breakdown for chart) --
$monthly = $pdo->query("
    SELECT
        DATE(created_at)           AS date,
        COUNT(*)                   AS orders,
        COALESCE(SUM(total_amt),0) AS revenue
    FROM orders
    WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
      AND status != 'cancelled'
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll();

// ---- 4. Total users & new today ---------------------------
$users = $pdo->query("
    SELECT
        COUNT(*)                                               AS total_users,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) AS new_today
    FROM users
    WHERE role_id = 2
")->fetch();

// ---- 5. Total products & low stock ------------------------
$products = $pdo->query("
    SELECT
        COUNT(*)                                                     AS total_products,
        SUM(CASE WHEN stock_qty = 0 THEN 1 END)                     AS out_of_stock,
        SUM(CASE WHEN stock_qty > 0 AND stock_qty <= low_stock_threshold THEN 1 END) AS low_stock
    FROM products
    WHERE is_active = 1
")->fetch();

// ---- 6. Low stock product list ----------------------------
$low_stock_items = $pdo->query("
    SELECT product_id, name, sku, stock_qty, low_stock_threshold
    FROM products
    WHERE is_active = 1 AND stock_qty <= low_stock_threshold
    ORDER BY stock_qty ASC
    LIMIT 10
")->fetchAll();

// ---- 7. Recent 10 orders ----------------------------------
$recent_orders = $pdo->query("
    SELECT
        o.order_id, o.order_number, o.status,
        o.total_amt, o.created_at,
        CONCAT(u.first_name,' ',u.last_name) AS customer_name
    FROM orders o
    LEFT JOIN users u ON u.user_id = o.user_id
    ORDER BY o.created_at DESC
    LIMIT 10
")->fetchAll();

// ---- 8. Top 5 selling products ----------------------------
$top_products = $pdo->query("
    SELECT
        p.product_id, p.name, p.sku,
        SUM(oi.quantity)      AS units_sold,
        SUM(oi.total_price)   AS revenue
    FROM order_items oi
    JOIN products p ON p.product_id = oi.product_id
    JOIN orders o   ON o.order_id   = oi.order_id
    WHERE o.status != 'cancelled'
    GROUP BY p.product_id
    ORDER BY units_sold DESC
    LIMIT 5
")->fetchAll();

// ---- 9. Pending reviews awaiting approval -----------------
$pending_reviews = (int)$pdo->query("
    SELECT COUNT(*) FROM reviews WHERE is_approved = 0
")->fetchColumn();

send_success([
    'sales'           => $sales,
    'today'           => $today,
    'monthly_chart'   => $monthly,
    'users'           => $users,
    'products'        => $products,
    'low_stock_items' => $low_stock_items,
    'recent_orders'   => $recent_orders,
    'top_products'    => $top_products,
    'pending_reviews' => $pending_reviews,
]);