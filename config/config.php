<?php
// ============================================================
//  config/config.php
//  App-wide constants — include this in every PHP file
// ============================================================

// --- Environment -------------------------------------------
define('ENV', 'development');   // 'development' | 'production'
define('DEBUG', ENV === 'development');

// --- Site --------------------------------------------------
define('SITE_NAME',    'MyShop');
define('SITE_URL',     'http://localhost/ecommerce');   // no trailing slash
define('SITE_EMAIL',   'support@myshop.com');

// --- Paths -------------------------------------------------
define('ROOT_PATH',    dirname(__DIR__));               // /path/to/ecommerce
define('UPLOAD_PATH',  ROOT_PATH . '/uploads');
define('UPLOAD_URL',   SITE_URL . '/uploads');

// --- Currency ----------------------------------------------
define('CURRENCY',        'INR');
define('CURRENCY_SYMBOL', '₹');

// --- Pricing -----------------------------------------------
define('TAX_RATE',             0.18);    // 18% GST
define('FREE_SHIPPING_ABOVE',  500);     // free shipping if order > ₹500
define('FLAT_SHIPPING_RATE',   60);      // flat ₹60 otherwise

// --- Pagination --------------------------------------------
define('PRODUCTS_PER_PAGE', 12);
define('ORDERS_PER_PAGE',   20);

// --- Sessions ----------------------------------------------
define('SESSION_NAME',     'ecom_session');
define('SESSION_LIFETIME', 60 * 60 * 24 * 7);   // 7 days in seconds

// --- Security ----------------------------------------------
define('BCRYPT_COST', 12);   // password_hash cost factor

// --- Upload limits -----------------------------------------
define('MAX_IMAGE_SIZE',   2 * 1024 * 1024);   // 2 MB
define('ALLOWED_IMG_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// --- Error handling ----------------------------------------
if (DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Start session once here
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => !DEBUG,       // HTTPS only in production
        'httponly' => true,          // no JS access
        'samesite' => 'Lax',
    ]);
    session_start();
}