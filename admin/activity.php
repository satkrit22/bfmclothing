<?php
/** Activity log: who changed what, and when. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-ui.php';
admin_require('settings.manage');

$area = input_str('area', 20, 'get');
$q    = input_str('q', 60, 'get');
$areas = ['order', 'product', 'category', 'coupon', 'user', 'review', 'message', 'settings', 'admin'];
$where = [];
$params = [];
if (in_array($area, $areas, true)) {
    $where[] = 'a.action LIKE ?';
    $params[] = $area . '.%';
}
if ($q !== '') {
    $where[] = '(a.action LIKE ? OR a.details LIKE ? OR u.email LIKE ?)';
    $like = admin_like($q);
    array_push($params, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$base = "FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id $whereSql";
$total = (int)db_value("SELECT COUNT(*) $base", $params);
$pg = paginate($total, 30);
$logs = db_all("SELECT a.*, u.first_name, u.last_name, u.email $base ORDER BY a.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);

admin_header('Activity log', 'activity', ['subtitle' => 'A record of changes made in the admin panel.']);
?>
<section class="adm-card">
    <form class="adm-filters" method="get">
        <input class="adm-input adm-search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search action, details or user" aria-label="Search">
        <select class="adm-select" name="area" aria-label="Area"><option value="">All areas</option><?php foreach ($areas as $a): ?><option value="<?= e($a) ?>"<?= adm_sel($area, $a) ?>><?= e(ucfirst($a)) ?></option><?php endforeach; ?></select>
        <button class="adm-btn adm-btn-primary" type="submit">Filter</button>
        <?php if ($q !== '' || $area !== ''): ?><a class="adm-btn" href="<?= e(url('admin/activity.php')) ?>">Reset</a><?php endif; ?>
    </form>
    <?php if (!$logs): ?>
        <div class="adm-empty"><?= ai('activity', 30) ?><p>Nothing recorded yet.</p></div>
    <?php else: ?>
        <div class="adm-table-wrap"><table class="adm-table">
            <thead><tr><th>When (UTC)</th><th>Who</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td class="adm-nowrap adm-muted"><?= e(format_date($l['created_at'], 'd M Y, H:i')) ?></td>
                    <td><?= $l['email'] ? e($l['first_name'] . ' ' . $l['last_name']) : '<span class="adm-muted">system</span>' ?></td>
                    <td><span class="adm-mono"><?= e($l['action']) ?></span><?= $l['entity_id'] ? ' <small class="adm-muted">#' . (int)$l['entity_id'] . '</small>' : '' ?></td>
                    <td class="adm-muted" style="max-width:380px;word-break:break-word"><?= e($l['details']) ?></td>
                    <td class="adm-muted adm-mono"><?= e($l['ip_address']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?= admin_pager($pg) ?>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
