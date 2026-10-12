<?php
/** Customers (and, for super admins, staff accounts): search, filter, export. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-ui.php';
admin_require('customers.view');

$canStaff = admin_can('users.roles');
$type = input_str('type', 8, 'get');
if ($type !== 'staff' || !$canStaff) {
    $type = 'customers';
}
$q    = input_str('q', 80, 'get');
$sort = input_str('sort', 10, 'get');
$sorts = ['newest' => 'u.created_at DESC, u.id DESC', 'name' => 'u.first_name, u.last_name', 'spent' => 'spent DESC', 'orders' => 'orders DESC'];
if (!isset($sorts[$sort])) {
    $sort = 'newest';
}

$where = [$type === 'staff' ? "u.role <> 'customer'" : "u.role = 'customer'"];
$params = [];
if ($q !== '') {
    $where[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $like = admin_like($q);
    array_push($params, $like, $like, $like);
}
$whereSql = 'WHERE ' . implode(' AND ', $where);
$select = "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.role, u.status, u.created_at,
                  (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status <> 'cancelled') AS orders,
                  (SELECT COALESCE(SUM(o.total), 0) FROM orders o WHERE o.user_id = u.id AND o.status <> 'cancelled') AS spent
           FROM users u $whereSql ORDER BY {$sorts[$sort]}";

if (input_str('export', 5, 'get') === 'csv') {
    $rows = [];
    foreach (db_all($select, $params) as $r) {
        $rows[] = [$r['first_name'] . ' ' . $r['last_name'], $r['email'], $r['phone'], $r['role'], $r['status'], $r['orders'], $r['spent'], $r['created_at']];
    }
    admin_send_csv('customers-' . date('Y-m-d') . '.csv', ['Name', 'Email', 'Phone', 'Role', 'Status', 'Orders', 'Total spent', 'Joined (UTC)'], $rows);
}

$total = (int)db_value("SELECT COUNT(*) FROM users u $whereSql", $params);
$pg    = paginate($total, 20);
$users = db_all($select . " LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
$nCust  = (int)db_value("SELECT COUNT(*) FROM users WHERE role = 'customer'");
$nStaff = (int)db_value("SELECT COUNT(*) FROM users WHERE role <> 'customer'");

admin_header($type === 'staff' ? 'Staff' : 'Customers', 'customers', [
    'subtitle' => number_format($total) . ' account' . ($total === 1 ? '' : 's'),
    'actions'  => '<a class="adm-btn" href="' . e(admin_qs(['export' => 'csv'])) . '">' . ai('download', 16) . ' Export CSV</a>'
                . (admin_can('customers.manage') ? '<a class="adm-btn adm-btn-primary" href="' . e(admin_url('customer.php', $type === 'staff' ? ['new' => 'staff'] : [])) . '">' . ai('plus', 16) . ' Add ' . ($type === 'staff' ? 'staff member' : 'customer') . '</a>' : ''),
]);
?>
<?php if ($canStaff): ?>
<div class="adm-tabs">
    <a class="adm-tab<?= $type === 'customers' ? ' is-active' : '' ?>" href="<?= e(admin_url('customers.php')) ?>">Customers<em><?= $nCust ?></em></a>
    <a class="adm-tab<?= $type === 'staff' ? ' is-active' : '' ?>" href="<?= e(admin_url('customers.php', ['type' => 'staff'])) ?>">Staff &amp; admins<em><?= $nStaff ?></em></a>
</div>
<?php endif; ?>
<section class="adm-card">
    <form class="adm-filters" method="get">
        <?php if ($type === 'staff'): ?><input type="hidden" name="type" value="staff"><?php endif; ?>
        <input class="adm-input adm-search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email or phone" aria-label="Search">
        <select class="adm-select" name="sort" aria-label="Sort">
            <option value="newest"<?= adm_sel($sort, 'newest') ?>>Newest</option><option value="name"<?= adm_sel($sort, 'name') ?>>Name A-Z</option>
            <option value="spent"<?= adm_sel($sort, 'spent') ?>>Top spenders</option><option value="orders"<?= adm_sel($sort, 'orders') ?>>Most orders</option>
        </select>
        <button class="adm-btn adm-btn-primary" type="submit">Search</button>
        <?php if ($q !== ''): ?><a class="adm-btn" href="<?= e(admin_url('customers.php', $type === 'staff' ? ['type' => 'staff'] : [])) ?>">Reset</a><?php endif; ?>
    </form>
    <?php if (!$users): ?>
        <div class="adm-empty"><?= ai('users', 30) ?><p>No accounts found.</p></div>
    <?php else: ?>
        <div class="adm-table-wrap"><table class="adm-table">
            <thead><tr><th>Name</th><th>Contact</th><?php if ($type === 'staff'): ?><th>Role</th><?php endif; ?><th class="adm-num">Orders</th><th class="adm-num">Spent</th><th>Joined</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><a href="<?= e(admin_url('customer.php', ['id' => $u['id']])) ?>"><strong><?= e($u['first_name'] . ' ' . $u['last_name']) ?></strong></a></td>
                    <td><?= e($u['email']) ?><br><small class="adm-muted"><?= e($u['phone']) ?></small></td>
                    <?php if ($type === 'staff'): ?><td><?= adm_badge(ucwords(str_replace('_', ' ', $u['role'])), $u['role'] === 'super_admin' ? 'purple' : 'info') ?></td><?php endif; ?>
                    <td class="adm-num"><?= (int)$u['orders'] ?></td>
                    <td class="adm-num"><?= e(money($u['spent'])) ?></td>
                    <td class="adm-muted adm-nowrap"><?= e(format_date($u['created_at'])) ?></td>
                    <td><?= $u['status'] === 'active' ? adm_badge('Active', 'ok') : adm_badge('Disabled', 'bad') ?></td>
                    <td><div class="adm-row-actions"><a class="adm-btn adm-btn-sm" href="<?= e(admin_url('customer.php', ['id' => $u['id']])) ?>"><?= ai('eye', 14) ?> Open</a></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?= admin_pager($pg) ?>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
