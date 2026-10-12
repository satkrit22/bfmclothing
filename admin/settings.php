<?php
/** Store settings (name, contact details, currency, delivery, stock alerts, social links). */
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-ui.php';
admin_require('settings.manage');

$fields = [
    'site_name' => 80, 'tagline' => 160, 'promo_banner' => 160, 'contact_email' => 190, 'contact_phone' => 40, 'contact_address' => 190,
    'currency_symbol' => 8, 'shipping_flat_rate' => 12, 'free_shipping_threshold' => 12, 'low_stock_threshold' => 6,
    'instagram_url' => 255, 'facebook_url' => 255, 'tiktok_url' => 255,
];
$values = [];
foreach ($fields as $k => $max) {
    $values[$k] = setting($k);
}
$errors = [];

if (is_post()) {
    require_csrf();
    foreach ($fields as $k => $max) {
        $values[$k] = input_str($k, $max);
    }
    if ($values['site_name'] === '') {
        $errors[] = 'The store name cannot be empty.';
    }
    if ($values['contact_email'] !== '' && !is_valid_email($values['contact_email'])) {
        $errors[] = 'Enter a valid contact email address.';
    }
    if ($values['currency_symbol'] === '') {
        $errors[] = 'Enter a currency symbol, for example Rs.';
    }
    foreach (['shipping_flat_rate' => 'Delivery fee', 'free_shipping_threshold' => 'Free delivery threshold'] as $k => $label) {
        $clean = str_replace(',', '', $values[$k]);
        if ($clean === '' || !is_numeric($clean) || (float)$clean < 0) {
            $errors[] = $label . ' must be a number (0 or more).';
        } else {
            $values[$k] = (string)(float)$clean;
        }
    }
    if ($values['low_stock_threshold'] === '' || !ctype_digit($values['low_stock_threshold'])) {
        $errors[] = 'The low stock alert level must be a whole number.';
    }
    foreach (['instagram_url', 'facebook_url', 'tiktok_url'] as $k) {
        if ($values[$k] !== '' && !preg_match('#^https?://#i', $values[$k])) {
            $errors[] = 'Social links must start with http:// or https://.';
            break;
        }
    }
    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            foreach ($values as $k => $v) {
                db_query('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$k, $v]);
            }
            $pdo->commit();
            audit_log('settings.update', 'settings');
            flash('success', 'Settings saved.');
            admin_redirect('settings.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[settings] ' . $e->getMessage());
            $errors[] = 'The settings could not be saved. Check logs/error.log.';
        }
    }
}

$field = static function (string $key, string $label, string $hint = '', string $type = 'text') use ($values): string {
    return '<div class="adm-field"><label for="' . e($key) . '">' . e($label) . '</label><input class="adm-input" id="' . e($key) . '" name="' . e($key) . '" type="' . e($type) . '" value="' . e($values[$key]) . '">'
        . ($hint !== '' ? '<small>' . e($hint) . '</small>' : '') . '</div>';
};

admin_header('Settings', 'settings', ['subtitle' => 'Changes apply to the storefront immediately.']);
foreach ($errors as $err) {
    echo '<div class="adm-alert adm-alert-error" role="alert">' . ai('alert', 18) . '<span>' . e($err) . '</span></div>';
}
?>
<form method="post" class="adm-stack" style="max-width:860px">
    <?= csrf_field() ?>
    <section class="adm-card"><div class="adm-card-head"><h2>Store</h2></div>
        <div class="adm-card-body adm-form-grid">
            <?= $field('site_name', 'Store name') ?>
            <?= $field('tagline', 'Tagline') ?>
            <div class="adm-full"><?= $field('promo_banner', 'Announcement bar text', 'Shown at the top of the store. Leave empty to hide.') ?></div>
        </div></section>
    <section class="adm-card"><div class="adm-card-head"><h2>Contact details</h2></div>
        <div class="adm-card-body adm-form-grid">
            <?= $field('contact_email', 'Email', '', 'email') ?>
            <?= $field('contact_phone', 'Phone') ?>
            <div class="adm-full"><?= $field('contact_address', 'Address') ?></div>
        </div></section>
    <section class="adm-card"><div class="adm-card-head"><h2>Money &amp; delivery</h2></div>
        <div class="adm-card-body adm-form-grid adm-form-grid-3">
            <?= $field('currency_symbol', 'Currency symbol', 'For example Rs. or $') ?>
            <?= $field('shipping_flat_rate', 'Delivery fee', 'Charged on smaller orders') ?>
            <?= $field('free_shipping_threshold', 'Free delivery from', '0 = never free') ?>
            <?= $field('low_stock_threshold', 'Low stock alert at', 'Units per size/colour', 'number') ?>
        </div></section>
    <section class="adm-card"><div class="adm-card-head"><h2>Social links</h2></div>
        <div class="adm-card-body adm-form-grid adm-form-grid-3">
            <?= $field('instagram_url', 'Instagram', '', 'url') ?>
            <?= $field('facebook_url', 'Facebook', '', 'url') ?>
            <?= $field('tiktok_url', 'TikTok', '', 'url') ?>
        </div></section>
    <div class="adm-formbar"><button class="adm-btn adm-btn-primary" type="submit">Save settings</button></div>
</form>
<?php admin_footer(); ?>
