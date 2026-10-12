<?php
/** Orders list: search, filter by status / payment / date, sort, paginate, export CSV. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-ui.php';
admin_require('orders.view');

$q       = input_str('q', 80, 'get');
$status  = input_str('status', 20, 'get');
$payment = input_str('payment', 20, 'get');
$from    = input_str('from', 10, 'get');
$to      = input_str('to', 10, 'get');
$sort    = input_str('sort', 12, 'get');

if (!in_array($status, order_statuses(), true)) {
    $status = '';
}
if (!in_array($payment, ['unpaid', 'paid', 'refunded'], true)) {
    $payment = '';
}
$from = admin_get_date('from', '');
$to   = admin_get_date('to', '');
$sorts = [
    'newest' => 'o.created_at DESC, o.id DESC',
    'oldest' => 'o.created_at ASC, o.id ASC',
    'high'   => 'o.total DESC, o.id DESC',
    'low'    => 'o.total ASC, o.id DESC',
];
if (!isset($sorts[$sort])) {
    $sort = 'newest';
}

$where  = [];
$params = [];
if ($q !== '') {
    $like = admin_like($q);
    $where[] = '(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.email LIKE ? OR o.phone LIKE ?)';
    array_push($params, $like, $like, $like, $like);
}
if ($status !== '') {
    $where[] = 'o.status = ?';
    $params[] = $status;
}
if ($payment !== '') {
    $where[] = 'o.payment_status = ?';
    $params[] = $payment;
}
if ($from !== '') {
    $where[] = 'o.created_at >= ?';
    $params[] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = 'o.created_at < ?';
    $params[] = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

if (input_str('export', 5, 'get') === 'csv') {
    $rows = db_all(
        "SELECT o.order_number, o.created_at, o.customer_name, o.email, o.phone, o.address_line, o.city, o.state,
                o.subtotal, o.discount, o.shipping, o.total, o.coupon_code, o.payment_method, o.payment_status, o.status
         FROM orders o $whereSql ORDER BY {$sorts[$sort]}",
        $params
    );
    admin_send_csv('orders-' . date('Y-m-d') . '.csv',
        ['Order', 'Date (UTC)', 'Customer', 'Email', 'Phone', 'Address', 'City', 'Province', 'Subtotal', 'Discount', 'Delivery', 'Total', 'Coupon', 'Payment method', 'Payment status', 'Status'],
        array_map('array_values', $rows));
}

$total = (int)db_value("SELECT COUNT(*) FROM orders o $whereSql", $params);
$pg    = paginate($total, 20);
$orders = db_all(
    "SELECT o.id, o.order_number, o.customer_name, o.email, o.city, o.total, o.status, o.payment_status, o.created_at,
            (SELECT COALESCE(SUM(i.quantity), 0) FROM order_items i WHERE i.order_id = o.id) AS items
     FROM orders o $whereSql ORDER BY {$sorts[$sort]} LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);

$counts = array_fill_keys(order_statuses(), 0);
foreach (db_all('SELECT status, COUNT(*) AS c FROM orders GROUP BY status') as $r) {
    $counts[$r['status']] = (int)$r['c'];
}
$allCount = array_sum($counts);

admin_header('Orders', 'orders', [
    'subtitle' => number_format($total) . ' order' . ($total === 1 ? '' : 's') . ' found',
    'actions'  => '<a class="adm-btn" href="' . e(admin_qs(['export' => 'csv'])) . '">' . ai('download', 16) . ' Export CSV</a>',
]);
?>
<div class="adm-tabs">
    <a class="adm-tab<?= $status === '' ? ' is-active' : '' ?>" href="<?= e(admin_qs([], ['status', 'page'])) ?: '?' ?>">All<em><?= (int)$allCount ?></em></a>
    <?php foreach (order_statuses() as $s): ?>
        <a class="adm-tab<?= $status === $s ? ' is-active' : '' ?>" href="<?= e(admin_qs(['status' => $s], ['page'])) ?>"><?= e(ucfirst($s)) ?><em><?= (int)$counts[$s] ?></em></a>
    <?php endforeach; ?>
</div>

<section class="adm-card">
    <form class="adm-filters" method="get">
        <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <input class="adm-input adm-search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search order no., name, email or phone" aria-label="Search orders">
        <select class="adm-select" name="payment" aria-label="Payment status">
            <option value="">Any payment</option>
            <?php foreach (['unpaid', 'paid', 'refunded'] as $p): ?><option value="<?= e($p) ?>"<?= adm_sel($payment, $p) ?>><?= e(ucfirst($p)) ?></option><?php endforeach; ?>
        </select>
        <input class="adm-input" type="date" name="from" value="<?= e($from) ?>" aria-label="From date">
        <input class="adm-input" type="date" name="to" value="<?= e($to) ?>" aria-label="To date">
        <select class="adm-select" name="sort" aria-label="Sort">
            <option value="newest"<?= adm_sel($sort, 'newest') ?>>Newest first</option><option value="oldest"<?= adm_sel($sort, 'oldest') ?>>Oldest first</option>
            <option value="high"<?= adm_sel($sort, 'high') ?>>Highest total</option><option value="low"<?= adm_sel($sort, 'low') ?>>Lowest total</option>
        </select>
        <button class="adm-btn adm-btn-primary" type="submit">Filter</button>
        <?php if ($q !== '' || $payment !== '' || $from !== '' || $to !== '' || $sort !== 'newest'): ?><a class="adm-btn" href="<?= e(admin_qs([], ['q', 'payment', 'from', 'to', 'sort', 'page'])) ?: '?' ?>">Reset</a><?php endif; ?>
    </form>

    <?php if (!$orders): ?>
        <div class="adm-empty"><?= ai('bag', 30) ?><p>No orders match these filters.</p></div>
    <?php else: ?>
        <div class="adm-table-wrap"><table class="adm-table">
            <thead><tr><th>Order</th><th>Date</th><th>Customer</th><th class="adm-num">Items</th><th>Payment</th><th>Status</th><th class="adm-num">Total</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><a href="<?= e(admin_url('order.php', ['id' => $o['id']])) ?>"><strong><?= e($o['order_number'] ?: '#' . $o['id']) ?></strong></a></td>
                    <td class="adm-nowrap adm-muted"><?= e(format_date($o['created_at'], 'd M Y, H:i')) ?></td>
                    <td><?= e($o['customer_name']) ?><br><small class="adm-muted"><?= e($o['city']) ?> &middot; <?= e($o['email']) ?></small></td>
                    <td class="adm-num"><?= (int)$o['items'] ?></td>
                    <td><?= adm_payment_badge((string)$o['payment_status']) ?></td>
                    <td><?= adm_status_badge((string)$o['status']) ?></td>
                    <td class="adm-num"><strong><?= e(money($o['total'])) ?></strong></td>
                    <td><div class="adm-row-actions"><a class="adm-btn adm-btn-sm" href="<?= e(admin_url('order.php', ['id' => $o['id']])) ?>"><?= ai('eye', 14) ?> View</a></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?= admin_pager($pg) ?>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
