<?php
// ============================================================
//  api/auth/logout.php
//  POST /api/auth/logout
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';

allow_methods(['POST']);

if (!is_logged_in()) {
    send_error('You are not logged in.', 400);
}

logout_user();

send_success(null, 200, 'Logged out successfully.');