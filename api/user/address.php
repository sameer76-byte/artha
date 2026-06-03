<?php
// ============================================================
//  api/user/address.php
//  POST   → save new address
//  PUT    → update / set default
//  DELETE → delete address
//  Requires login
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/auth.php';

allow_methods(['POST', 'PUT', 'DELETE']);
require_login();

$user_id = current_user_id();
$method  = $_SERVER['REQUEST_METHOD'];
$data    = get_json_body();

// ============================================================
// POST — add new address
// ============================================================
if ($method === 'POST') {
    $missing = validate_required($data, ['full_name','address_line1','city','state','postal_code']);
    if ($missing) send_error('Missing required fields.', 400, $missing);

    // Max 5 addresses per user
    $count = $pdo->prepare('SELECT COUNT(*) FROM user_addresses WHERE user_id = ?');
    $count->execute([$user_id]);
    if ((int)$count->fetchColumn() >= 5) {
        send_error('You can save up to 5 addresses. Please delete one first.', 400);
    }

    $pdo->prepare('
        INSERT INTO user_addresses
            (user_id, label, full_name, phone, address_line1, address_line2,
             city, state, postal_code, country, is_default)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ')->execute([
        $user_id,
        clean_str($data['label']         ?? 'Home'),
        clean_str($data['full_name']),
        clean_str($data['phone']         ?? ''),
        clean_str($data['address_line1']),
        clean_str($data['address_line2'] ?? ''),
        clean_str($data['city']),
        clean_str($data['state']),
        clean_str($data['postal_code']),
        clean_str($data['country']       ?? 'India'),
        0,
    ]);

    $address_id = (int)$pdo->lastInsertId();
    send_success(['address_id' => $address_id], 201, 'Address saved.');
}

// ============================================================
// PUT — update address or set as default
// ============================================================
if ($method === 'PUT') {
    $address_id = clean_int($data['address_id'] ?? null);
    if (!$address_id) send_error('address_id is required.', 400);

    // Verify ownership
    $stmt = $pdo->prepare('SELECT address_id FROM user_addresses WHERE address_id = ? AND user_id = ?');
    $stmt->execute([$address_id, $user_id]);
    if (!$stmt->fetch()) send_error('Address not found.', 404);

    // Set as default — clear others first
    if (!empty($data['is_default'])) {
        $pdo->prepare('UPDATE user_addresses SET is_default = 0 WHERE user_id = ?')
            ->execute([$user_id]);
        $pdo->prepare('UPDATE user_addresses SET is_default = 1 WHERE address_id = ?')
            ->execute([$address_id]);
        send_success(null, 200, 'Default address updated.');
    }

    // Update fields
    $allowed = ['label','full_name','phone','address_line1','address_line2','city','state','postal_code','country'];
    $set = []; $params = [];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $data)) {
            $set[]    = "$field = ?";
            $params[] = clean_str($data[$field]);
        }
    }
    if (empty($set)) send_error('Nothing to update.', 400);

    $params[] = $address_id;
    $pdo->prepare('UPDATE user_addresses SET ' . implode(', ', $set) . ' WHERE address_id = ?')
        ->execute($params);

    send_success(null, 200, 'Address updated.');
}

// ============================================================
// DELETE — remove address
// ============================================================
if ($method === 'DELETE') {
    $address_id = clean_int($data['address_id'] ?? null);
    if (!$address_id) send_error('address_id is required.', 400);

    $stmt = $pdo->prepare('SELECT address_id FROM user_addresses WHERE address_id = ? AND user_id = ?');
    $stmt->execute([$address_id, $user_id]);
    if (!$stmt->fetch()) send_error('Address not found.', 404);

    $pdo->prepare('DELETE FROM user_addresses WHERE address_id = ?')->execute([$address_id]);
    send_success(null, 200, 'Address deleted.');
}