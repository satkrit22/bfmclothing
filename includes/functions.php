<?php
/**
 * Shared helpers used by every page.
 * Session keys used across the project:
 *   $_SESSION['user_id']     logged-in customer id
 *   $_SESSION['admin_id']    logged-in admin id
 *   $_SESSION['cart']        [variant_id => quantity]
 *   $_SESSION['coupon']      applied coupon code (string)
 *   $_SESSION['csrf_token']  CSRF token
 *   $_SESSION['flash']       list of [type, message]
 */

declare(strict_types=1);

/* ================================================================
 * Output / URLs
 * ================================================================ */

/** Escape for HTML output. Use for EVERY dynamic value printed in a page. */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Site URL path, e.g. url('shop.php?sort=newest') => /bfm-clothing/shop.php?sort=newest */
function url(string $path = ''): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

/** Asset URL with a cache-busting timestamp. */
function asset(string $path): string
{
    $file = ROOT_PATH . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 1;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function redirect(string $path): void
{
    $target = preg_match('#^https?://#i', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

/** Only allow redirecting to a local path (prevents open redirects). */
function safe_redirect_target(?string $next, string $fallback = 'account.php'): string
{
    $next = (string)$next;
    if ($next === '' || $next[0] !== '/' || strpos($next, '//') === 0 || strpos($next, '\\') !== false) {
        return $fallback;
    }
    if (BASE_PATH !== '' && strpos($next, BASE_PATH . '/') !== 0) {
        return $fallback;
    }
    return $next;
}

function current_uri(): string
{
    return $_SERVER['REQUEST_URI'] ?? url();
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/** Output a JSON response and stop. */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Friendly standalone error page. Works even if the database is down. */
function render_error_page(int $code, string $title, string $message): void
{
    if (!headers_sent()) {
        http_response_code($code);
    }
    $css = BASE_PATH . '/assets/css/style.css';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>' . e($title) . ' | ' . e(APP_NAME) . '</title>'
        . '<link rel="stylesheet" href="' . e($css) . '"></head><body class="error-body">'
        . '<main class="error-page"><a class="logo" href="' . e(url()) . '">BFM</a>'
        . '<p class="error-code">' . (int)$code . '</p><h1>' . e($title) . '</h1><p>' . e($message) . '</p>'
        . '<a class="btn btn-primary" href="' . e(url()) . '">Back to the store</a></main></body></html>';
    exit;
}

/* ================================================================
 * Icons (inline SVG sprite lives in includes/icons.php)
 * ================================================================ */
function icon(string $name, string $class = ''): string
{
    return '<svg class="icon ' . e($class) . '" aria-hidden="true" focusable="false"><use href="#i-' . e($name) . '"></use></svg>';
}

/* ================================================================
 * CSRF
 * ================================================================ */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Accepts the token from a form field or the X-CSRF-Token header (AJAX). */
function csrf_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

/** For normal form posts: stop with a friendly error if the token is wrong. */
function require_csrf(): void
{
    if (!csrf_valid()) {
        render_error_page(419, 'Session expired', 'Your session expired or the form was invalid. Please go back, refresh the page and try again.');
    }
}

/** For AJAX endpoints: require POST + valid token, answer in JSON. */
function require_ajax_post(): void
{
    if (!is_post()) {
        json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }
    if (!csrf_valid()) {
        json_response(['ok' => false, 'message' => 'Session expired. Please refresh the page.'], 419);
    }
}

/* ================================================================
 * Flash messages
 * ================================================================ */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function flash_pull(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

/* ================================================================
 * Input helpers
 * ================================================================ */
function input_str(string $key, int $max = 255, string $source = 'post'): string
{
    $arr = $source === 'get' ? $_GET : $_POST;
    $val = $arr[$key] ?? '';
    if (!is_string($val)) {
        return '';
    }
    $val = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $val));
    return mb_substr($val, 0, $max);
}

function input_int(string $key, int $default = 0, string $source = 'post'): int
{
    $arr = $source === 'get' ? $_GET : $_POST;
    $val = $arr[$key] ?? null;
    return (is_scalar($val) && filter_var($val, FILTER_VALIDATE_INT) !== false) ? (int)$val : $default;
}

function is_valid_email(string $email): bool
{
    return strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string)$text, '-');
    return $text !== '' ? $text : 'item';
}

/** Make a slug unique in $table (table/column names are fixed by the caller, never user input). */
function unique_slug(string $table, string $slug, int $ignoreId = 0): string
{
    $allowed = ['products', 'categories'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Invalid table');
    }
    $candidate = $slug;
    $i = 2;
    while (db_value("SELECT id FROM {$table} WHERE slug = ? AND id <> ? LIMIT 1", [$candidate, $ignoreId])) {
        $candidate = $slug . '-' . $i++;
    }
    return $candidate;
}

/* ================================================================
 * Settings
 * ================================================================ */
function settings_all(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db_all('SELECT setting_key, setting_value FROM settings') as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            error_log('[settings] ' . $e->getMessage());
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings_all();
    return isset($all[$key]) && $all[$key] !== null ? (string)$all[$key] : $default;
}

/* ================================================================
 * Money / dates / labels
 * ================================================================ */
function money($amount): string
{
    return setting('currency_symbol', '$') . number_format((float)$amount, 2);
}

function format_date(?string $date, string $format = 'd M Y'): string
{
    return $date ? date($format, strtotime($date)) : '';
}

function order_statuses(): array
{
    return ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
}

function status_badge(string $status): string
{
    $status = in_array($status, order_statuses(), true) ? $status : 'pending';
    return '<span class="status status-' . e($status) . '">' . e(ucfirst($status)) . '</span>';
}

/** BFM-2026-000001 */
function make_order_number(int $orderId): string
{
    return sprintf('%s-%s-%06d', APP_YEAR_PREFIX, date('Y'), $orderId);
}

/* ================================================================
 * Products: pricing + images
 * ================================================================ */

/** Price a customer actually pays (sale price if valid, else regular). */
function effective_price(array $p): float
{
    $price = (float)$p['price'];
    $sale  = $p['sale_price'] ?? null;
    return ($sale !== null && $sale !== '' && (float)$sale > 0 && (float)$sale < $price) ? (float)$sale : $price;
}

function discount_percent(array $p): int
{
    $price = (float)$p['price'];
    $eff   = effective_price($p);
    return ($price > 0 && $eff < $price) ? (int)round((($price - $eff) / $price) * 100) : 0;
}

/** URL of a product image. Falls back to a generated placeholder so the site never shows broken images. */
function product_image_url(?string $path, string $name = 'BFM', int $variant = 0): string
{
    if ($path && is_file(UPLOAD_PATH . $path)) {
        return url(UPLOAD_DIR_REL . $path);
    }
    return url('placeholder.php?t=' . rawurlencode($name) . '&i=' . $variant);
}

function product_url(array $p): string
{
    return url('product.php?slug=' . rawurlencode((string)$p['slug']));
}

function render_stars(float $avg): string
{
    $out = '<span class="stars" aria-hidden="true">';
    $rounded = (int)round($avg);
    for ($i = 1; $i <= 5; $i++) {
        $out .= icon('star', $i <= $rounded ? 'fill' : '');
    }
    return $out . '</span>';
}

function nav_categories(): array
{
    static $cache = null;
    if ($cache === null) {
        try {
            $cache = db_all('SELECT id, name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order, name');
        } catch (Throwable $e) {
            error_log('[nav_categories] ' . $e->getMessage());
            $cache = [];
        }
    }
    return $cache;
}

/* ================================================================
 * Cart (session based; persisted to DB for logged-in users)
 * ================================================================ */
function cart_items(): array
{
    $cart = $_SESSION['cart'] ?? [];
    return is_array($cart) ? $cart : [];
}

function cart_count(): int
{
    return (int)array_sum(cart_items());
}

/** Write the session cart to the cart table for logged-in users. */
function cart_persist(): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }
    $uid = (int)$_SESSION['user_id'];
    try {
        $pdo = db();
        $pdo->beginTransaction();
        db_query('DELETE FROM cart WHERE user_id = ?', [$uid]);
        foreach (cart_items() as $variantId => $qty) {
            db_query('INSERT INTO cart (user_id, variant_id, quantity) VALUES (?, ?, ?)', [$uid, (int)$variantId, (int)$qty]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        error_log('[cart_persist] ' . $e->getMessage());
    }
}

/** On login: merge the guest cart with the saved cart (keeps the larger quantity). */
function cart_merge_on_login(int $userId): void
{
    try {
        $rows = db_all('SELECT variant_id, quantity FROM cart WHERE user_id = ?', [$userId]);
        $cart = cart_items();
        foreach ($rows as $row) {
            $vid = (int)$row['variant_id'];
            $cart[$vid] = max((int)($cart[$vid] ?? 0), (int)$row['quantity']);
        }
        $_SESSION['cart'] = $cart;
        cart_persist();
    } catch (Throwable $e) {
        error_log('[cart_merge] ' . $e->getMessage());
    }
}

/* ================================================================
 * Wishlist
 * ================================================================ */
function wishlist_ids(): array
{
    static $ids = null;
    if ($ids === null) {
        $ids = [];
        if (!empty($_SESSION['user_id'])) {
            try {
                $ids = array_map('intval', array_column(
                    db_all('SELECT product_id FROM wishlist WHERE user_id = ?', [(int)$_SESSION['user_id']]),
                    'product_id'
                ));
            } catch (Throwable $e) {
                error_log('[wishlist_ids] ' . $e->getMessage());
            }
        }
    }
    return $ids;
}

function wishlist_count(): int
{
    return count(wishlist_ids());
}

/* ================================================================
 * Pagination
 * ================================================================ */
function paginate(int $total, int $perPage = ITEMS_PER_PAGE, ?int $page = null): array
{
    $page  = $page ?? max(1, input_int('page', 1, 'get'));
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = min(max(1, $page), $pages);
    return ['total' => $total, 'per_page' => $perPage, 'page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage];
}

function render_pagination(array $pg): string
{
    if ($pg['pages'] <= 1) {
        return '';
    }
    $link = function (int $n, string $label, string $class = '') use ($pg): string {
        $q = $_GET;
        $q['page'] = $n;
        $href = strtok(current_uri(), '?') . '?' . http_build_query($q);
        $cur = $n === $pg['page'] && $class === '' ? ' aria-current="page"' : '';
        return '<a class="page-link ' . $class . ($cur ? ' active' : '') . '" href="' . e($href) . '"' . $cur . '>' . $label . '</a>';
    };
    $html = '<nav class="pagination" aria-label="Pagination">';
    if ($pg['page'] > 1) {
        $html .= $link($pg['page'] - 1, 'Previous', 'page-prev');
    }
    for ($i = 1; $i <= $pg['pages']; $i++) {
        if ($i === 1 || $i === $pg['pages'] || abs($i - $pg['page']) <= 1) {
            $html .= $link($i, (string)$i);
        } elseif (abs($i - $pg['page']) === 2) {
            $html .= '<span class="page-gap">&hellip;</span>';
        }
    }
    if ($pg['page'] < $pg['pages']) {
        $html .= $link($pg['page'] + 1, 'Next', 'page-next');
    }
    return $html . '</nav>';
}

/* ================================================================
 * Secure image upload (used by the admin panel)
 * ================================================================ */

/** Returns [true, extension] or [false, error message]. Never trusts the client filename or MIME. */
function validate_image_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [false, 'The file could not be uploaded.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return [false, 'Invalid upload.'];
    }
    if ($file['size'] <= 0 || $file['size'] > MAX_UPLOAD_BYTES) {
        return [false, 'Images must be smaller than ' . (MAX_UPLOAD_BYTES / 1048576) . ' MB.'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $map   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($map[$mime])) {
        return [false, 'Only JPG, JPEG, PNG and WEBP images are allowed.'];
    }
    if (@getimagesize($file['tmp_name']) === false) {
        return [false, 'The file is not a valid image.'];
    }
    return [true, $map[$mime]];
}

/** Validate and store an upload. Returns [true, 'products/abc.jpg'] or [false, error]. */
function save_uploaded_image(array $file, string $subdir = 'products'): array
{
    [$ok, $extOrErr] = validate_image_upload($file);
    if (!$ok) {
        return [false, $extOrErr];
    }
    $subdir = preg_replace('/[^a-z0-9_-]/i', '', $subdir);
    $dir = UPLOAD_PATH . $subdir . '/';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return [false, 'The upload folder is not writable.'];
    }
    $name = bin2hex(random_bytes(12)) . '.' . $extOrErr;
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
        return [false, 'The image could not be saved.'];
    }
    @chmod($dir . $name, 0644);
    return [true, $subdir . '/' . $name];
}

/* ================================================================
 * Login throttling (customers + admins)
 * ================================================================ */
function login_throttled(string $scope, string $identifier): bool
{
    try {
        $byId = (int)db_value(
            "SELECT COUNT(*) FROM login_attempts WHERE scope = ? AND identifier = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)",
            [$scope, strtolower($identifier)]
        );
        $byIp = (int)db_value(
            "SELECT COUNT(*) FROM login_attempts WHERE scope = ? AND ip_address = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)",
            [$scope, client_ip()]
        );
        return $byId >= 5 || $byIp >= 20;
    } catch (Throwable $e) {
        error_log('[throttle] ' . $e->getMessage());
        return false;
    }
}

function record_failed_login(string $scope, string $identifier): void
{
    try {
        db_query('INSERT INTO login_attempts (scope, identifier, ip_address) VALUES (?, ?, ?)', [$scope, strtolower($identifier), client_ip()]);
    } catch (Throwable $e) {
        error_log('[record_failed_login] ' . $e->getMessage());
    }
}

function clear_failed_logins(string $scope, string $identifier): void
{
    try {
        db_query('DELETE FROM login_attempts WHERE scope = ? AND identifier = ?', [$scope, strtolower($identifier)]);
    } catch (Throwable $e) {
        error_log('[clear_failed_logins] ' . $e->getMessage());
    }
}
