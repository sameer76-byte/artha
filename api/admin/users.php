<?php
// ============================================================
//  api/admin/users.php
//  GET    /api/admin/users             → list all users
//  GET    /api/admin/users?id=5        → single user detail
//  PUT    /api/admin/users?id=5        → update user (role, active)
//  DELETE /api/admin/users?id=5        → deactivate user
//  Requires admin
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/auth.php';

allow_methods(['GET', 'PUT', 'DELETE']);
require_admin();

$method = $_SERVER['REQUEST_METHOD'];
$id     = clean_int($_GET['id'] ?? null);

// ============================================================
// GET single user
// ============================================================
if ($method === 'GET' && $id) {
    $stmt = $pdo->prepare('
        SELECT u.user_id, u.first_name, u.last_name, u.email,
               u.phone, u.avatar_url, u.is_active, u.created_at,
               r.role_name,
               COUNT(DISTINCT o.order_id)  AS total_orders,
               COALESCE(SUM(o.total_amt),0) AS total_spent
        FROM users u
        JOIN roles r ON r.role_id = u.role_id
        LEFT JOIN orders o ON o.user_id = u.user_id AND o.status != "cancelled"
        WHERE u.user_id = ?
        GROUP BY u.user_id
    ');
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if (!$user) send_error('User not found.', 404);

    // Recent orders
    $stmt = $pdo->prepare('
        SELECT order_id, order_number, status, total_amt, created_at
        FROM orders WHERE user_id = ?
        ORDER BY created_at DESC LIMIT 5
    ');
    $stmt->execute([$id]);
    $user['recent_orders'] = $stmt->fetchAll();

    send_success($user);
}

// ============================================================
// GET all users
// ============================================================
if ($method === 'GET' && !$id) {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20;
    $offset = ($page - 1) * $limit;
    $search = clean_str($_GET['search'] ?? '');
    $role   = clean_int($_GET['role']   ?? null);

    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[]  = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
        $like     = '%' . $search . '%';
        $params   = array_merge($params, [$like, $like, $like]);
    }
    if ($role) {
        $where[]  = 'u.role_id = ?';
        $params[] = $role;
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = $pdo->prepare("SELECT COUNT(*) FROM users u $where_sql");
    $total->execute($params);
    $total = (int)$total->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.email,
               u.phone, u.is_active, u.created_at, r.role_name,
               COUNT(DISTINCT o.order_id) AS total_orders
        FROM users u
        JOIN roles r  ON r.role_id  = u.role_id
        LEFT JOIN orders o ON o.user_id = u.user_id
        $where_sql
        GROUP BY u.user_id
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$limit, $offset]));

    send_success([
        'users'      => $stmt->fetchAll(),
        'pagination' => [
            'total'        => $total,
            'per_page'     => $limit,
            'current_page' => $page,
            'total_pages'  => (int)ceil($total / $limit),
        ],
    ]);
}

// ============================================================
// PUT — update role or active status
// ============================================================
if ($method === 'PUT') {
    if (!$id) send_error('User ID is required.', 400);

    // Prevent admin from editing themselves
    if ($id === current_user_id()) {
        send_error('You cannot edit your own account here.', 403);
    }

    $data    = get_json_body();
    $set     = [];
    $params  = [];

    if (array_key_exists('role_id', $data)) {
        $role_id = clean_int($data['role_id']);
        if (!in_array($role_id, [1, 2, 3])) send_error('Invalid role.', 400);
        $set[]    = 'role_id = ?';
        $params[] = $role_id;
    }

    if (array_key_exists('is_active', $data)) {
        $set[]    = 'is_active = ?';
        $params[] = $data['is_active'] ? 1 : 0;
    }

    if (empty($set)) send_error('Nothing to update.', 400);

    $params[] = $id;
    $pdo->prepare('UPDATE users SET ' . implode(', ', $set) . ' WHERE user_id = ?')
        ->execute($params);

    send_success(null, 200, 'User updated.');
}

// ============================================================
// DELETE — deactivate user
// ============================================================
if ($method === 'DELETE') {
    if (!$id) send_error('User ID is required.', 400);

    if ($id === current_user_id()) {
        send_error('You cannot deactivate your own account.', 403);
    }

    $pdo->prepare('UPDATE users SET is_active = 0 WHERE user_id = ?')->execute([$id]);

    send_success(null, 200, 'User deactivated.');
}