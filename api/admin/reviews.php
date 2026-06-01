<?php
// ============================================================
//  api/admin/reviews.php
//  GET  /api/admin/reviews             → list pending reviews
//  PUT  /api/admin/reviews?id=5        → approve or reject
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

// ============================================================
// GET — list reviews (default: pending only)
// ============================================================
if ($method === 'GET') {
    $approved = isset($_GET['approved']) ? (int)$_GET['approved'] : 0;

    $stmt = $pdo->prepare("
        SELECT
            r.review_id, r.rating, r.title, r.body,
            r.is_approved, r.created_at,
            p.name AS product_name, p.product_id,
            CONCAT(u.first_name,' ',u.last_name) AS reviewer_name,
            u.email AS reviewer_email
        FROM reviews r
        JOIN products p ON p.product_id = r.product_id
        LEFT JOIN users u ON u.user_id  = r.user_id
        WHERE r.is_approved = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$approved]);

    send_success($stmt->fetchAll());
}

// ============================================================
// PUT — approve or reject
// ============================================================
if ($method === 'PUT') {
    $id   = clean_int($_GET['id'] ?? null);
    $data = get_json_body();

    if (!$id) send_error('Review ID is required.', 400);
    if (!isset($data['is_approved'])) send_error('is_approved is required.', 400);

    $approved = (int)(bool)$data['is_approved'];

    $pdo->prepare('UPDATE reviews SET is_approved = ? WHERE review_id = ?')
        ->execute([$approved, $id]);

    $msg = $approved ? 'Review approved.' : 'Review rejected.';
    send_success(null, 200, $msg);
}

// ============================================================
// DELETE — permanently remove a review
// ============================================================
if ($method === 'DELETE') {
    $id = clean_int($_GET['id'] ?? null);
    if (!$id) send_error('Review ID is required.', 400);

    $pdo->prepare('DELETE FROM reviews WHERE review_id = ?')->execute([$id]);
    send_success(null, 200, 'Review deleted.');
}