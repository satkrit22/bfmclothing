<?php
/**
 * Admin panel layout + helpers. Included by every admin page AFTER admin-auth.php:
 *
 *     require_once __DIR__ . '/../includes/admin-auth.php';
 *     require_once __DIR__ . '/../includes/admin-ui.php';
 *     admin_require('products.manage');
 *     ... handle POST (redirect) ...
 *     admin_header('Products', 'products', ['actions' => '<a class="adm-btn adm-btn-primary" href="...">Add</a>']);
 *     ... page HTML ...
 *     admin_footer();
 */

/** Errors we want the admin to read (not logged as failures). */
class AdminException extends RuntimeException
{
}

/* ================================================================
 * Icons (Feather-style, inline SVG)
 * ================================================================ */
function ai(string $name, int $size = 18, string $class = ''): string
{
    static $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/>',
        'chart'     => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'bag'       => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>',
        'users'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user'      => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'percent'   => '<line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
        'package'   => '<line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        'tag'       => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
        'star'      => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'mail'      => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
        'settings'  => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
        'activity'  => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'external'  => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
        'menu'      => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'plus'      => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'edit'      => '<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>',
        'trash'     => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
        'eye'       => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'download'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'printer'   => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'search'    => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'check'     => '<polyline points="20 6 9 17 4 12"/>',
        'alert'     => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'x'         => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'back'      => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
        'trend'     => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
        'dollar'    => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'image'     => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
        'box'       => '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><polyline points="3 8 12 13 21 8"/><line x1="12" y1="13" x2="12" y2="21"/>',
    ];
    $inner = $paths[$name] ?? '';
    return '<svg class="ai ' . e($class) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $inner . '</svg>';
}

/* ================================================================
 * URLs / query strings
 * ================================================================ */

/** admin_url('orders.php', ['status' => 'pending']) => /bfmclothing/admin/orders.php?status=pending */
function admin_url(string $file, array $params = []): string
{
    $params = array_filter($params, static fn($v) => $v !== '' && $v !== null);
    return url('admin/' . $file) . ($params ? '?' . http_build_query($params) : '');
}

/** Current query string with some keys changed/removed (for filters + pagination). */
function admin_qs(array $set = [], array $remove = []): string
{
    $q = $_GET;
    foreach ($remove as $k) {
        unset($q[$k]);
    }
    foreach ($set as $k => $v) {
        $q[$k] = $v;
    }
    $q = array_filter($q, static fn($v) => $v !== '' && $v !== null);
    return $q ? '?' . http_build_query($q) : '';
}

/** Redirect to an admin file (relative to /admin/). */
function admin_redirect(string $file, array $params = []): void
{
    redirect('admin/' . $file . ($params ? '?' . http_build_query($params) : ''));
}

/* ================================================================
 * Layout
 * ================================================================ */
function admin_nav(): array
{
    return [
        'Overview' => [
            ['dashboard',  'Dashboard',    'dashboard', 'index.php',      'dashboard.view'],
            ['reports',    'Sales report', 'chart',     'reports.php',    'reports.view'],
        ],
        'Sales' => [
            ['orders',     'Orders',       'bag',       'orders.php',     'orders.view'],
            ['customers',  'Customers',    'users',     'customers.php',  'customers.view'],
            ['coupons',    'Coupons',      'percent',   'coupons.php',    'coupons.manage'],
        ],
        'Catalogue' => [
            ['products',   'Products',     'package',   'products.php',   'products.manage'],
            ['categories', 'Categories',   'tag',       'categories.php', 'categories.manage'],
            ['reviews',    'Reviews',      'star',      'reviews.php',    'reviews.manage'],
        ],
        'Store' => [
            ['messages',   'Messages',     'mail',      'messages.php',   'messages.manage'],
            ['settings',   'Settings',     'settings',  'settings.php',   'settings.manage'],
            ['activity',   'Activity log', 'activity',  'activity.php',   'settings.manage'],
        ],
    ];
}

function admin_nav_counts(): array
{
    static $counts = null;
    if ($counts === null) {
        $counts = ['orders' => 0, 'messages' => 0];
        try {
            $counts['orders']   = (int)db_value("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
            $counts['messages'] = (int)db_value('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0');
        } catch (Throwable $e) {
            error_log('[admin_nav_counts] ' . $e->getMessage());
        }
    }
    return $counts;
}

/**
 * Opens the page: <html>, sidebar, top bar and <main>.
 * $opts: 'actions' (raw trusted HTML shown beside the title), 'subtitle' (plain text), 'back' (admin file for a back link)
 */
function admin_header(string $title, string $active = '', array $opts = []): void
{
    $admin  = current_admin();
    $counts = admin_nav_counts();
    $flashes = flash_pull();
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title) ?> | Admin | <?= e(setting('site_name', APP_NAME)) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="adm">
<div class="adm-shell">
    <aside class="adm-sidebar" id="admSidebar" aria-label="Admin navigation">
        <a class="adm-brand" href="<?= e(admin_url('index.php')) ?>"><span class="adm-logo">BFM</span><span class="adm-brand-text">Admin</span></a>
        <nav class="adm-nav">
            <?php foreach (admin_nav() as $group => $items): ?>
                <?php
                $visible = array_filter($items, static fn(array $i): bool => admin_can($i[4]));
                if (!$visible) {
                    continue;
                }
                ?>
                <p class="adm-nav-title"><?= e($group) ?></p>
                <?php foreach ($visible as [$key, $label, $icon, $file]): ?>
                    <a class="adm-nav-link<?= $active === $key ? ' is-active' : '' ?>" href="<?= e(admin_url($file)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
                        <?= ai($icon, 18) ?><span><?= e($label) ?></span>
                        <?php if (($counts[$key] ?? 0) > 0): ?><em class="adm-count"><?= (int)$counts[$key] ?></em><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
        <div class="adm-sidefoot">
            <a class="adm-nav-link" href="<?= e(url()) ?>" target="_blank" rel="noopener"><?= ai('external', 18) ?><span>View store</span></a>
            <a class="adm-nav-link" href="<?= e(admin_url('logout.php')) ?>"><?= ai('logout', 18) ?><span>Log out</span></a>
        </div>
    </aside>
    <div class="adm-scrim" data-adm-close hidden></div>

    <div class="adm-main">
        <header class="adm-topbar">
            <button class="adm-iconbtn adm-menu-toggle" type="button" data-adm-menu aria-label="Open menu" aria-controls="admSidebar"><?= ai('menu', 20) ?></button>
            <div class="adm-topbar-title">
                <?php if (!empty($opts['back'])): ?><a class="adm-backlink" href="<?= e(admin_url((string)$opts['back'])) ?>"><?= ai('back', 16) ?> Back</a><?php endif; ?>
            </div>
            <a class="adm-user" href="<?= e(admin_url('profile.php')) ?>" title="My profile">
                <span class="adm-avatar"><?= e(strtoupper(mb_substr((string)$admin['first'], 0, 1))) ?></span>
                <span class="adm-user-meta"><strong><?= e($admin['name']) ?></strong><small><?= e(ucwords(str_replace('_', ' ', $admin['role']))) ?></small></span>
            </a>
        </header>

        <main class="adm-content" id="main">
            <div class="adm-pagehead">
                <div>
                    <h1><?= e($title) ?></h1>
                    <?php if (!empty($opts['subtitle'])): ?><p class="adm-muted"><?= e((string)$opts['subtitle']) ?></p><?php endif; ?>
                </div>
                <?php if (!empty($opts['actions'])): ?><div class="adm-actions"><?= $opts['actions'] /* trusted HTML built by the page */ ?></div><?php endif; ?>
            </div>
            <?php foreach ($flashes as [$type, $message]): ?>
                <?php $type = $type === 'danger' ? 'error' : $type; ?>
                <div class="adm-alert adm-alert-<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"><?= ai($type === 'success' ? 'check' : 'alert', 18) ?><span><?= e($message) ?></span></div>
            <?php endforeach; ?>
<?php
}

function admin_footer(): void
{
    ?>
        </main>
    </div>
</div>

<dialog class="adm-dialog" id="admConfirm" aria-labelledby="admConfirmTitle">
    <form method="dialog">
        <h2 id="admConfirmTitle">Are you sure?</h2>
        <p id="admConfirmText"></p>
        <div class="adm-dialog-actions">
            <button class="adm-btn" value="cancel">Cancel</button>
            <button class="adm-btn adm-btn-danger" value="ok" id="admConfirmOk">Confirm</button>
        </div>
    </form>
</dialog>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
<?php
}

/* ================================================================
 * Small UI helpers
 * ================================================================ */
function adm_badge(string $text, string $tone = 'neutral'): string
{
    return '<span class="adm-badge adm-badge-' . e($tone) . '">' . e($text) . '</span>';
}

function adm_status_badge(string $status): string
{
    $tones = ['pending' => 'warn', 'confirmed' => 'info', 'processing' => 'info', 'shipped' => 'purple', 'delivered' => 'ok', 'cancelled' => 'bad'];
    return adm_badge(ucfirst($status), $tones[$status] ?? 'neutral');
}

function adm_payment_badge(string $status): string
{
    $tones = ['unpaid' => 'warn', 'paid' => 'ok', 'refunded' => 'bad'];
    return adm_badge(ucfirst($status), $tones[$status] ?? 'neutral');
}

/** Pagination links using the current query string. */
function admin_pager(array $pg): string
{
    if ($pg['pages'] <= 1) {
        return '';
    }
    $link = static function (int $n, string $label, bool $active = false): string {
        return '<a class="adm-page' . ($active ? ' is-active' : '') . '" href="' . e(admin_qs(['page' => $n])) . '"' . ($active ? ' aria-current="page"' : '') . '>' . $label . '</a>';
    };
    $html = '<nav class="adm-pager" aria-label="Pagination">';
    if ($pg['page'] > 1) {
        $html .= $link($pg['page'] - 1, '&larr; Prev');
    }
    for ($i = 1; $i <= $pg['pages']; $i++) {
        if ($i === 1 || $i === $pg['pages'] || abs($i - $pg['page']) <= 1) {
            $html .= $link($i, (string)$i, $i === $pg['page']);
        } elseif (abs($i - $pg['page']) === 2) {
            $html .= '<span class="adm-page-gap">&hellip;</span>';
        }
    }
    if ($pg['page'] < $pg['pages']) {
        $html .= $link($pg['page'] + 1, 'Next &rarr;');
    }
    $from = $pg['total'] === 0 ? 0 : $pg['offset'] + 1;
    $to   = min($pg['total'], $pg['offset'] + $pg['per_page']);
    return $html . '<span class="adm-pager-info">' . $from . '&ndash;' . $to . ' of ' . $pg['total'] . '</span></nav>';
}

/** Parse a decimal the admin typed ("1,250.50"). Returns $default when empty/invalid. */
function admin_float(string $key, ?float $default = null): ?float
{
    $raw = $_POST[$key] ?? '';
    if (!is_string($raw)) {
        return $default;
    }
    $raw = str_replace([',', ' '], '', trim($raw));
    return ($raw !== '' && is_numeric($raw)) ? (float)$raw : $default;
}

function admin_checked(string $key): int
{
    return !empty($_POST[$key]) ? 1 : 0;
}

/** Accepts "2026-03-01T10:30" (datetime-local) or "2026-03-01"; returns "Y-m-d H:i:s" or null. */
function admin_datetime(string $key): ?string
{
    $raw = input_str($key, 25);
    if ($raw === '') {
        return null;
    }
    $ts = strtotime(str_replace('T', ' ', $raw));
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

function admin_datetime_local(?string $value): string
{
    return $value ? date('Y-m-d\TH:i', strtotime($value)) : '';
}

/** 1250 => "1.3k", 2400000 => "2.4M" (chart axis labels). */
function admin_short_number(float $v): string
{
    if ($v >= 1000000) {
        return rtrim(rtrim(number_format($v / 1000000, 1), '0'), '.') . 'M';
    }
    if ($v >= 1000) {
        return rtrim(rtrim(number_format($v / 1000, 1), '0'), '.') . 'k';
    }
    return (string)(int)round($v);
}

function admin_nice_max(float $max): float
{
    if ($max <= 0) {
        return 1.0;
    }
    $mag  = 10 ** floor(log10($max));
    $norm = $max / $mag;
    foreach ([1, 2, 2.5, 5, 10] as $step) {
        if ($norm <= $step) {
            return (float)($step * $mag);
        }
    }
    return (float)(10 * $mag);
}

/**
 * Responsive inline-SVG bar chart (no libraries).
 * $points: [['label' => '12 Oct', 'value' => 1234.5, 'tip' => '12 Oct: Rs. 1,234.50'], ...]
 */
function admin_bar_chart(array $points, string $ariaLabel = 'Chart', int $height = 260): string
{
    $n = count($points);
    if ($n === 0) {
        return '<p class="adm-muted">No data for this period.</p>';
    }
    $w = 760;
    $padL = 50;
    $padR = 8;
    $padT = 12;
    $padB = 30;
    $plotW = $w - $padL - $padR;
    $plotH = $height - $padT - $padB;
    $max = 0.0;
    foreach ($points as $p) {
        $max = max($max, (float)$p['value']);
    }
    $max  = admin_nice_max($max);
    $slot = $plotW / $n;
    $barW = max(2.0, min(34.0, $slot * 0.64));

    $svg = '<svg class="adm-chart" viewBox="0 0 ' . $w . ' ' . $height . '" role="img" aria-label="' . e($ariaLabel) . '">';
    for ($i = 0; $i <= 4; $i++) {
        $y = $padT + $plotH - ($plotH * $i / 4);
        $svg .= '<line class="adm-grid" x1="' . $padL . '" x2="' . ($w - $padR) . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '"/>';
        $svg .= '<text class="adm-axis" x="' . ($padL - 8) . '" y="' . round($y + 4, 1) . '" text-anchor="end">' . e(admin_short_number($max * $i / 4)) . '</text>';
    }
    $step = max(1, (int)ceil($n / 10));
    foreach ($points as $i => $p) {
        $v  = (float)$p['value'];
        $bh = $max > 0 ? $plotH * $v / $max : 0;
        $x  = $padL + $slot * $i + ($slot - $barW) / 2;
        $y  = $padT + $plotH - $bh;
        $svg .= '<g class="adm-bar"><rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($barW, 1) . '" height="' . round(max($bh, $v > 0 ? 1 : 0), 1) . '" rx="2"><title>' . e((string)($p['tip'] ?? $p['label'])) . '</title></rect>';
        // full-height transparent hit area so thin bars are easy to hover
        $svg .= '<rect class="adm-hit" x="' . round($padL + $slot * $i, 1) . '" y="' . $padT . '" width="' . round($slot, 1) . '" height="' . $plotH . '"><title>' . e((string)($p['tip'] ?? $p['label'])) . '</title></rect></g>';
        if ($i % $step === 0) {
            $svg .= '<text class="adm-axis" x="' . round($padL + $slot * $i + $slot / 2, 1) . '" y="' . ($height - 8) . '" text-anchor="middle">' . e((string)$p['label']) . '</text>';
        }
    }
    return $svg . '</svg>';
}

/** Horizontal share bars: rows = [['label' => 'Hoodies', 'value' => 120.0, 'note' => 'Rs. 120.00'], ...] */
function admin_share_bars(array $rows): string
{
    if (!$rows) {
        return '<p class="adm-muted">No data for this period.</p>';
    }
    $max = 0.0;
    foreach ($rows as $r) {
        $max = max($max, (float)$r['value']);
    }
    $html = '<ul class="adm-bars">';
    foreach ($rows as $r) {
        $pct = $max > 0 ? round(((float)$r['value'] / $max) * 100, 1) : 0;
        $html .= '<li><div class="adm-bars-row"><span>' . e((string)$r['label']) . '</span><b>' . e((string)($r['note'] ?? $r['value'])) . '</b></div>'
              . '<div class="adm-bars-track"><i style="width:' . $pct . '%"></i></div></li>';
    }
    return $html . '</ul>';
}

/* ================================================================
 * CSV export (formula-injection safe, Excel friendly)
 * ================================================================ */
function csv_cell($value): string
{
    $s = (string)($value ?? '');
    // Stop spreadsheet formula injection, but leave phone numbers like "+977 9800000001" alone.
    $phoneLike = (bool)preg_match('/^[+\-][0-9][0-9 ()\-]*$/', $s);
    if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($s) && !$phoneLike) {
        $s = "'" . $s;
    }
    return $s;
}

function admin_send_csv(string $filename, array $header, iterable $rows): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads Nepali/accented names correctly
    fputcsv($out, array_map('csv_cell', $header));
    foreach ($rows as $row) {
        fputcsv($out, array_map('csv_cell', $row));
    }
    fclose($out);
    exit;
}

/* ================================================================
 * Business logic shared by several admin pages
 * ================================================================ */

/** Remove an uploaded file, but only if it really lives inside the uploads folder. */
function admin_delete_upload(string $relativePath): void
{
    $base = realpath(UPLOAD_PATH);
    $file = realpath(UPLOAD_PATH . $relativePath);
    if ($base && $file && strpos($file, $base . DIRECTORY_SEPARATOR) === 0 && is_file($file)) {
        @unlink($file);
    }
}

function recalc_product_rating(int $productId): void
{
    db_query(
        'UPDATE products SET
            rating_avg   = COALESCE((SELECT ROUND(AVG(r.rating), 2) FROM reviews r WHERE r.product_id = ? AND r.is_approved = 1), 0),
            rating_count = (SELECT COUNT(*) FROM reviews r WHERE r.product_id = ? AND r.is_approved = 1)
         WHERE id = ?',
        [$productId, $productId, $productId]
    );
}

/**
 * Change an order's status and keep stock consistent:
 *   - moving TO cancelled returns the items to stock (and lowers sold counts)
 *   - moving OUT of cancelled takes the stock again (fails if it has sold out meanwhile)
 *   - delivered + cash on delivery + unpaid => marked paid
 * Returns [bool ok, string message].
 */
function order_set_status(int $orderId, string $new): array
{
    if (!in_array($new, order_statuses(), true)) {
        return [false, 'Unknown status.'];
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $o = db_one('SELECT id, order_number, status, payment_method, payment_status FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
        if (!$o) {
            throw new AdminException('Order not found.');
        }
        $old = (string)$o['status'];
        if ($old === $new) {
            $pdo->rollBack();
            return [true, 'The order already has that status.'];
        }
        $items = db_all('SELECT variant_id, product_id, quantity FROM order_items WHERE order_id = ?', [$orderId]);

        if ($new === 'cancelled') {
            foreach ($items as $it) {
                if ($it['variant_id']) {
                    db_query('UPDATE product_variants SET stock = stock + ? WHERE id = ?', [(int)$it['quantity'], (int)$it['variant_id']]);
                }
                if ($it['product_id']) {
                    db_query('UPDATE products SET sold_count = GREATEST(CAST(sold_count AS SIGNED) - ?, 0) WHERE id = ?', [(int)$it['quantity'], (int)$it['product_id']]);
                }
            }
        } elseif ($old === 'cancelled') {
            foreach ($items as $it) {
                if ($it['variant_id']) {
                    $stock = db_value('SELECT stock FROM product_variants WHERE id = ? FOR UPDATE', [(int)$it['variant_id']]);
                    if ($stock === null || (int)$stock < (int)$it['quantity']) {
                        throw new AdminException('Not enough stock to re-open this order. Restock the items first.');
                    }
                    db_query('UPDATE product_variants SET stock = stock - ? WHERE id = ?', [(int)$it['quantity'], (int)$it['variant_id']]);
                }
                if ($it['product_id']) {
                    db_query('UPDATE products SET sold_count = sold_count + ? WHERE id = ?', [(int)$it['quantity'], (int)$it['product_id']]);
                }
            }
        }

        $payment = (string)$o['payment_status'];
        if ($new === 'delivered' && $o['payment_method'] === 'cod' && $payment === 'unpaid') {
            $payment = 'paid';
        }
        db_query('UPDATE orders SET status = ?, payment_status = ? WHERE id = ?', [$new, $payment, $orderId]);
        $pdo->commit();
        audit_log('order.status', 'order', $orderId, ['from' => $old, 'to' => $new, 'number' => $o['order_number']]);
        return [true, 'Order status changed to ' . $new . '.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof AdminException) {
            return [false, $e->getMessage()];
        }
        error_log('[order_set_status] ' . $e->getMessage());
        return [false, 'The status could not be changed. Check logs/error.log.'];
    }
}

/* ================================================================
 * Tiny form helpers
 * ================================================================ */
function adm_sel($current, $value): string
{
    return (string)$current === (string)$value ? ' selected' : '';
}

function adm_chk($on): string
{
    return $on ? ' checked' : '';
}

/** Valid Y-m-d from a GET parameter, else $default. */
function admin_get_date(string $key, string $default): string
{
    $raw = input_str($key, 10, 'get');
    return (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) && strtotime($raw) !== false) ? $raw : $default;
}

/** Escape a value for use inside a SQL LIKE pattern. */
function admin_like(string $s): string
{
    return '%' . addcslashes($s, '%_\\') . '%';
}

/** +12.5 / -3 percent change between two numbers, as ['text' => '+12%', 'class' => 'adm-delta-up'] (or null). */
function admin_delta(float $now, float $before): ?array
{
    if ($before <= 0) {
        return $now > 0 ? ['text' => 'new', 'class' => 'adm-delta-up'] : null;
    }
    $pct = (($now - $before) / $before) * 100;
    return ['text' => ($pct >= 0 ? '+' : '') . round($pct) . '%', 'class' => $pct >= 0 ? 'adm-delta-up' : 'adm-delta-down'];
}
