<?php
// ============================================================
//  includes/sanitize.php
//  Input sanitization & validation helpers
// ============================================================

/**
 * Sanitize a plain text string (trim + strip tags).
 */
function clean_str(?string $value): string
{
    return htmlspecialchars(strip_tags(trim($value ?? '')), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize and validate an email address.
 * Returns the clean email or false.
 */
function clean_email(?string $value): string|false
{
    $email = filter_var(trim($value ?? ''), FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
}

/**
 * Return a sanitized integer or null if invalid.
 */
function clean_int($value): ?int
{
    $int = filter_var($value, FILTER_VALIDATE_INT);
    return $int !== false ? (int)$int : null;
}

/**
 * Return a sanitized positive float or null.
 */
function clean_float($value): ?float
{
    $f = filter_var($value, FILTER_VALIDATE_FLOAT);
    return ($f !== false && $f >= 0) ? (float)$f : null;
}

/**
 * Generate a URL-friendly slug from a string.
 * e.g. "Red Sneakers!" → "red-sneakers"
 */
function make_slug(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Validate required fields in an array.
 * Returns an array of missing field names.
 *
 * Usage:
 *   $missing = validate_required($data, ['email', 'password']);
 *   if ($missing) send_error('Missing fields', 400, $missing);
 */
function validate_required(array $data, array $fields): array
{
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            $missing[] = $field;
        }
    }
    return $missing;
}

/**
 * Validate password strength.
 * Minimum 8 chars, at least one letter and one number.
 */
function validate_password(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/[0-9]/', $password);
}

/**
 * Generate a secure random token (hex string).
 */
function generate_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

/**
 * Generate a unique order number.
 * e.g. ORD-20240601-00042
 */
function generate_order_number(): string
{
    return 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
}