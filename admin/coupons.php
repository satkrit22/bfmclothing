<?php
/** Coupons: list with live status + add / edit form on the same page. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-ui.php';
admin_require('coupons.manage');

$errors = [];
$editId = input_int('edit', 0, 'get');
$form = ['code' => '', 'type' => 'percent', 'value' => '', 'min_order' => '0', 'max_discount' => '', 'usage_limit' => '', 'starts_at' => '', 'expires_at' => '', 'is_active' => 1];

if (is_post()) {
    require_csrf();
    $action = input_str('action', 10);
    $cid = input_int('id');

    if ($action === 'delete' || $action === 'toggle') {
        $c = $cid > 0 ? db_one('SELECT id, code, is_active FROM coupons WHERE id = ?', [$cid]) : null;
        if (!$c) {
            flash('error', 'Coupon not found.');
        } elseif ($action === 'delete') {
            db_query('DELETE FROM coupons WHERE id = ?', [$cid]);
            audit_log('coupon.delete', 'coupon', $cid, ['code' => $c['code']]);
            flash('success', 'Coupon ' . $c['code'] . ' deleted. Past orders keep the code they used.');
        } else {
            db_query('UPDATE coupons SET is_active = ? WHERE id = ?', [(int)$c['is_active'] ? 0 : 1, $cid]);
            flash('success', 'Coupon ' . $c['code'] . ((int)$c['is_active'] ? ' disabled.' : ' enabled.'));
        }
        admin_redirect('coupons.php');
    }

    $editId = $cid;
    $type = input_str('type', 8) === 'fixed' ? 'fixed' : 'percent';
    $value = admin_float('value');
    $min = admin_float('min_order', 0.0);
    $max = admin_float('max_discount');
    $limitRaw = input_str('usage_limit', 9);
    $limit = ($limitRaw !== '' && ctype_digit($limitRaw) && (int)$limitRaw > 0) ? (int)$limitRaw : null;
    $starts = admin_datetime('starts_at');
    $expires = admin_datetime('expires_at');
    $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', input_str('code', 40)));
    $form = [
        'code' => $code, 'type' => $type, 'value' => $value ?? '', 'min_order' => $min ?? 0, 'max_discount' => $max ?? '',
        'usage_limit' => $limit ?? '', 'starts_at' => $starts ?? '', 'expires_at' => $expires ?? '', 'is_active' => admin_checked('is_active'),
    ];

    if ($code === '' || strlen($code) < 3) {
        $errors[] = 'Enter a coupon code of at least 3 letters or numbers.';
    } elseif (db_value('SELECT id FROM coupons WHERE code = ? AND id <> ?', [$code, $cid])) {
        $errors[] = 'A coupon with the code ' . $code . ' already exists.';
    }
    if ($value === null || $value <= 0) {
        $errors[] = 'Enter a discount value greater than 0.';
    } elseif ($type === 'percent' && $value > 100) {
        $errors[] = 'A percentage discount cannot be more than 100.';
    }
    if ($min !== null && $min < 0) {
        $errors[] = 'The minimum order cannot be negative.';
    }
    if ($starts && $expires && strtotime($expires) <= strtotime($starts)) {
        $errors[] = 'The expiry date must be after the start date.';
    }
    if (!$errors) {
        $vals = [$code, $type, $value, max(0.0, (float)$min), ($type === 'percent' && $max !== null && $max > 0) ? $max : null, $limit, $starts, $expires, $form['is_active']];
        if ($cid > 0) {
            db_query('UPDATE coupons SET code=?, type=?, value=?, min_order=?, max_discount=?, usage_limit=?, starts_at=?, expires_at=?, is_active=? WHERE id=?', array_merge($vals, [$cid]));
            audit_log('coupon.update', 'coupon', $cid, ['code' => $code]);
        } else {
            db_query('INSERT INTO coupons (code, type, value, min_order, max_discount, usage_limit, starts_at, expires_at, is_active) VALUES (?,?,?,?,?,?,?,?,?)', $vals);
            audit_log('coupon.create', 'coupon', (int)db()->lastInsertId(), ['code' => $code]);
        }
        flash('success', 'Coupon ' . $code . ' saved.');
        admin_redirect('coupons.php');
    }
} elseif ($editId > 0) {
    $row = db_one('SELECT * FROM coupons WHERE id = ?', [$editId]);
    if ($row) {
        $form = [
            'code' => $row['code'], 'type' => $row['type'], 'value' => $row['value'], 'min_order' => $row['min_order'], 'max_discount' => $row['max_discount'] ?? '',
            'usage_limit' => $row['usage_limit'] ?? '', 'starts_at' => $row['starts_at'] ?? '', 'expires_at' => $row['expires_at'] ?? '', 'is_active' => (int)$row['is_active'],
        ];
    } else {
        $editId = 0;
    }
}

$coupons = db_all('SELECT * FROM coupons ORDER BY created_at DESC, id DESC');
$now = time();
$statusOf = static function (array $c) use ($now): array {
    if (!(int)$c['is_active']) {
        return ['Disabled', 'neutral'];
    }
    if ($c['starts_at'] && strtotime((string)$c['starts_at']) > $now) {
        return ['Scheduled', 'info'];
    }
    if ($c['expires_at'] && strtotime((string)$c['expires_at']) < $now) {
        return ['Expired', 'bad'];
    }
    if ($c['usage_limit'] !== null && (int)$c['used_count'] >= (int)$c['usage_limit']) {
        return ['Used up', 'warn'];
    }
    return ['Active', 'ok'];
};

admin_header('Coupons', 'coupons', ['subtitle' => 'Discount codes customers can apply in their cart.']);
foreach ($errors as $err) {
    echo '<div class="adm-alert adm-alert-error" role="alert">' . ai('alert', 18) . '<span>' . e($err) . '</span></div>';
}
?>
<div class="adm-split">
    <section class="adm-card">
        <?php if (!$coupons): ?>
            <div class="adm-empty"><?= ai('percent', 30) ?><p>No coupons yet. Create one on the right.</p></div>
        <?php else: ?>
        <div class="adm-table-wrap"><table class="adm-table">
            <thead><tr><th>Code</th><th>Discount</th><th>Min. order</th><th>Used</th><th>Valid</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($coupons as $c): [$sLabel, $sTone] = $statusOf($c); ?>
                <tr>
                    <td><a href="<?= e(admin_url('coupons.php', ['edit' => $c['id']])) ?>"><strong class="adm-mono"><?= e($c['code']) ?></strong></a></td>
                    <td><?= $c['type'] === 'percent' ? e(rtrim(rtrim(number_format((float)$c['value'], 2), '0'), '.')) . '%' : e(money($c['value'])) ?><?php if ($c['type'] === 'percent' && $c['max_discount'] !== null): ?><br><small class="adm-muted">max <?= e(money($c['max_discount'])) ?></small><?php endif; ?></td>
                    <td><?= (float)$c['min_order'] > 0 ? e(money($c['min_order'])) : '-' ?></td>
                    <td><?= (int)$c['used_count'] ?><?= $c['usage_limit'] !== null ? ' / ' . (int)$c['usage_limit'] : '' ?></td>
                    <td class="adm-muted adm-nowrap"><?= $c['starts_at'] ? e(format_date($c['starts_at'])) : 'Now' ?> &rarr; <?= $c['expires_at'] ? e(format_date($c['expires_at'])) : 'No end' ?></td>
                    <td><?= adm_badge($sLabel, $sTone) ?></td>
                    <td><div class="adm-row-actions">
                        <a class="adm-btn adm-btn-sm" href="<?= e(admin_url('coupons.php', ['edit' => $c['id']])) ?>"><?= ai('edit', 14) ?> Edit</a>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="adm-btn adm-btn-sm" type="submit"><?= $c['is_active'] ? 'Disable' : 'Enable' ?></button></form>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="adm-btn adm-btn-sm adm-btn-danger" type="submit" aria-label="Delete <?= e($c['code']) ?>" data-confirm="Delete coupon <?= e($c['code']) ?>?" data-confirm-yes="Delete"><?= ai('trash', 14) ?></button></form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </section>

    <aside class="adm-card">
        <div class="adm-card-head"><h2><?= $editId ? 'Edit coupon' : 'New coupon' ?></h2><?php if ($editId): ?><a href="<?= e(admin_url('coupons.php')) ?>">Cancel</a><?php endif; ?></div>
        <form class="adm-card-body adm-stack" method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$editId ?>">
            <div class="adm-field"><label for="code">Code</label><input class="adm-input adm-mono" id="code" name="code" required maxlength="40" style="text-transform:uppercase" value="<?= e($form['code']) ?>"></div>
            <div class="adm-form-grid">
                <div class="adm-field"><label for="type">Type</label><select class="adm-select" id="type" name="type"><option value="percent"<?= adm_sel($form['type'], 'percent') ?>>Percent (%)</option><option value="fixed"<?= adm_sel($form['type'], 'fixed') ?>>Fixed amount</option></select></div>
                <div class="adm-field"><label for="value">Value</label><input class="adm-input" id="value" name="value" type="number" step="0.01" min="0.01" required value="<?= e($form['value']) ?>"></div>
                <div class="adm-field"><label for="min_order">Minimum order</label><input class="adm-input" id="min_order" name="min_order" type="number" step="0.01" min="0" value="<?= e($form['min_order']) ?>"></div>
                <div class="adm-field"><label for="max_discount">Max discount</label><input class="adm-input" id="max_discount" name="max_discount" type="number" step="0.01" min="0" value="<?= e($form['max_discount']) ?>"><small>Percent coupons only.</small></div>
            </div>
            <div class="adm-field"><label for="usage_limit">Usage limit</label><input class="adm-input" id="usage_limit" name="usage_limit" type="number" min="1" value="<?= e($form['usage_limit']) ?>" placeholder="Unlimited"></div>
            <div class="adm-form-grid">
                <div class="adm-field"><label for="starts_at">Starts</label><input class="adm-input" id="starts_at" name="starts_at" type="datetime-local" value="<?= e(admin_datetime_local($form['starts_at'] ?: null)) ?>"></div>
                <div class="adm-field"><label for="expires_at">Expires</label><input class="adm-input" id="expires_at" name="expires_at" type="datetime-local" value="<?= e(admin_datetime_local($form['expires_at'] ?: null)) ?>"></div>
            </div>
            <label class="adm-check"><input type="checkbox" name="is_active" value="1"<?= adm_chk($form['is_active']) ?>> Active</label>
            <button class="adm-btn adm-btn-primary" type="submit"><?= $editId ? 'Save coupon' : 'Create coupon' ?></button>
        </form>
    </aside>
</div>
<?php admin_footer(); ?>
