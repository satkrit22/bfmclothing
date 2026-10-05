<?php
/**
 * BFM Clothing - global configuration.
 * Loaded first by includes/init.php. Edit the values in the "EDIT ME" block.
 */

declare(strict_types=1);

/* ------------------------------------------------------------------
 * EDIT ME
 * ------------------------------------------------------------------ */
const APP_NAME  = 'BFM Clothing';
const APP_DEBUG = false;          // true = show errors on screen (development only)
const APP_YEAR_PREFIX = 'BFM';    // order numbers look like BFM-2026-000001

// Database credentials (XAMPP defaults: user "root", empty password)
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'bfm_clothing';
const DB_USER = 'root';
const DB_PASS = '';

/* ------------------------------------------------------------------
 * Paths and URLs
 * ------------------------------------------------------------------ */
define('ROOT_PATH', dirname(__DIR__));
const UPLOAD_DIR_REL = 'assets/uploads/';
define('UPLOAD_PATH', ROOT_PATH . '/assets/uploads/');
const MAX_UPLOAD_BYTES = 3 * 1024 * 1024; // 3 MB per image
const ITEMS_PER_PAGE   = 12;

/**
 * BASE_PATH is the URL path to the project folder, e.g. "/bfm-clothing".
 * It is detected automatically so the site works in any folder name.
 */
(function (): void {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $root    = realpath(ROOT_PATH);
    $base    = '';
    if ($docRoot && $root && strpos(str_replace('\\', '/', $root), str_replace('\\', '/', $docRoot)) === 0) {
        $base = substr(str_replace('\\', '/', $root), strlen(str_replace('\\', '/', $docRoot)));
    }
    define('BASE_PATH', rtrim($base, '/'));
})();

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443');
define('IS_HTTPS', $isHttps);
define('SITE_ORIGIN', (IS_HTTPS ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
define('SITE_URL', SITE_ORIGIN . BASE_PATH);

/* ------------------------------------------------------------------
 * Errors: log them, never show them to customers
 * ------------------------------------------------------------------ */
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
if (!is_dir(ROOT_PATH . '/logs')) {
    @mkdir(ROOT_PATH . '/logs', 0755, true);
}
ini_set('error_log', ROOT_PATH . '/logs/error.log');
date_default_timezone_set('UTC');

/* ------------------------------------------------------------------
 * Secure session
 * ------------------------------------------------------------------ */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('BFMSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => IS_HTTPS,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ------------------------------------------------------------------
 * Security headers
 * ------------------------------------------------------------------ */
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
