<?php
/** One order: items, customer, status workflow (keeps stock in sync), payment, notes, print, delete. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-ui.php';
admin_require('orders.view');

$id = input_int('id', 0, 'get');
$order = $id > 0 ? db_one('SELECT * FROM orders WHERE id = ?', [$id]) : null;
if (!$order) {
    flash('error', 'That order was not found.');
    admin_redirect('orders.php');
}

if (is_post()) {
    require_csrf();
    $action = input_str('action', 20);

    if (in_array($action, ['status', 'payment', 'notes', 'delete'], true) && $action !== 'delete' && !admin_can('orders.manage')) {
        render_error_page(403, 'Access denied', 'Your role cannot change orders.');
    }

    if ($action === 'status') {
        [$ok, $msg] = order_set_status($id, input_str('status', 20));
        flash($ok ? 'success' : 'error', $msg);
    } elseif ($action === 'payment') {
        $p = input_str('payment_status', 10);
        if (in_array($p, ['unpaid', 'paid', 'refunded'], true)) {
            db_query('UPDATE orders SET payment_status = ? WHERE id = ?', [$p, $id]);
            audit_log('order.payment', 'order', $id, ['to' => $p, 'number' => $order['order_number']]);
            flash('success', 'Payment status updated.');
        }
    } elseif ($action === 'notes') {
        $notes = input_str('admin_notes', 500);
        db_query('UPDATE orders SET admin_notes = ? WHERE id = ?', [$notes !== '' ? $notes : null, $id]);
        flash('success', 'Internal note saved.');
    } elseif ($action === 'delete') {
        if (!admin_can('orders.delete')) {
            render_error_page(403, 'Access denied', 'Only a super admin can delete orders.');
        }
        if ($order['status'] !== 'cancelled') {
            [$ok, $msg] = order_set_status($id, 'cancelled'); // puts the stock back first
            if (!$ok) {
                flash('error', 'The order was not deleted: ' . $msg);
                admin_redirect('order.php', ['id' => $id]);
            }
        }
        db_query('DELETE FROM orders WHERE id = ?', [$id]);
        audit_log('order.delete', 'order', $id, ['number' => $order['order_number']]);
        flash('success', 'Order ' . ($order['order_number'] ?: '#' . $id) . ' deleted and its stock returned.');
        admin_redirect('orders.php');
    }
    admin_redirect('order.php', ['id' => $id]);
}

$items = db_all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$id]);
$customer = $order['user_id'] ? db_one('SELECT id, first_name, last_name FROM users WHERE id = ?', [(int)$order['user_id']]) : null;
$label = $order['order_number'] ?: '#' . $id;
$canManage = admin_can('orders.manage');
$flow = ['pending' => 'Confirm order', 'confirmed' => 'Start processing', 'processing' => 'Mark as shipped', 'shipped' => 'Mark as delivered'];
$nextStatus = ['pending' => 'confirmed', 'confirmed' => 'processing', 'processing' => 'shipped', 'shipped' => 'delivered'][$order['status']] ?? null;

admin_header('Order ' . $label, 'orders', [
    'back'     => 'orders.php',
    'subtitle' => 'Placed ' . format_date($order['created_at'], 'd M Y, H:i') . ' (UTC)',
    'actions'  => '<a class="adm-btn" href="#" data-print>' . ai('printer', 16) . ' Print</a>',
]);
?>
<div class="adm-split">
    <div class="adm-stack">
        <section class="adm-card">
            <div class="adm-card-head"><h2>Items</h2><span><?= adm_status_badge((string)$order['status']) ?> <?= adm_payment_badge((string)$order['payment_status']) ?></span></div>
            <div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Product</th><th>Variant</th><th class="adm-num">Price</th><th class="adm-num">Qty</th><th class="adm-num">Total</th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?php if ($it['product_id']): ?><a href="<?= e(admin_url('product-edit.php', ['id' => $it['product_id']])) ?>"><?= e($it['product_name']) ?></a><?php else: ?><?= e($it['product_name']) ?> <small class="adm-muted">(removed)</small><?php endif; ?><br><small class="adm-muted adm-mono"><?= e($it['sku']) ?></small></td>
                        <td><?= e($it['color']) ?> &middot; <?= e($it['size']) ?></td>
                        <td class="adm-num"><?= e(money($it['unit_price'])) ?></td>
                        <td class="adm-num"><?= (int)$it['quantity'] ?></td>
                        <td class="adm-num"><?= e(money($it['line_total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <div class="adm-totals">
                <div><span class="adm-muted">Subtotal</span><span><?= e(money($order['subtotal'])) ?></span></div>
                <?php if ((float)$order['discount'] > 0): ?><div><span class="adm-muted">Discount<?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?></span><span>- <?= e(money($order['discount'])) ?></span></div><?php endif; ?>
                <div><span class="adm-muted">Delivery</span><span><?= (float)$order['shipping'] > 0 ? e(money($order['shipping'])) : 'Free' ?></span></div>
                <div class="adm-grand"><span>Total</span><span><?= e(money($order['total'])) ?></span></div>
            </div>
        </section>

        <section class="adm-card">
            <div class="adm-card-head"><h2>Customer &amp; delivery</h2></div>
            <div class="adm-card-body">
                <dl class="adm-dl">
                    <dt>Name</dt><dd><?= e($order['customer_name']) ?><?php if ($customer && admin_can('customers.view')): ?> &middot; <a class="adm-noprint" href="<?= e(admin_url('customer.php', ['id' => $customer['id']])) ?>">view account</a><?php endif; ?></dd>
                    <dt>Email</dt><dd><a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a></dd>
                    <dt>Phone</dt><dd><a href="tel:<?= e($order['phone']) ?>"><?= e($order['phone']) ?></a></dd>
                    <dt>Address</dt><dd><?= e($order['address_line']) ?><br><?= e($order['city']) ?>, <?= e($order['state']) ?> <?= e($order['postal_code']) ?><br><?= e($order['country']) ?></dd>
                    <dt>Payment</dt><dd><?= $order['payment_method'] === 'cod' ? 'Cash on delivery' : 'Online' ?></dd>
                    <?php if ($order['notes']): ?><dt>Customer note</dt><dd><?= nl2br(e($order['notes'])) ?></dd><?php endif; ?>
                </dl>
            </div>
        </section>
    </div>

    <aside class="adm-stack adm-noprint">
        <?php if ($canManage): ?>
        <section class="adm-card">
            <div class="adm-card-head"><h2>Update status</h2></div>
            <div class="adm-card-body adm-stack">
                <?php if ($nextStatus): ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="<?= e($nextStatus) ?>">
                        <button class="adm-btn adm-btn-primary adm-btn-block" type="submit"><?= e($flow[$order['status']]) ?></button></form>
                <?php endif; ?>
                <form method="post" class="adm-field"><?= csrf_field() ?><input type="hidden" name="action" value="status">
                    <label for="st">Set status</label>
                    <select class="adm-select" id="st" name="status">
                        <?php foreach (order_statuses() as $s): ?><option value="<?= e($s) ?>"<?= adm_sel($order['status'], $s) ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
                    </select>
                    <button class="adm-btn" type="submit" data-confirm="Change the status of this order? Cancelling returns the items to stock." data-confirm-yes="Change status">Save status</button>
                    <small>Cancelling returns items to stock. Delivered cash-on-delivery orders are marked paid.</small>
                </form>
            </div>
        </section>
        <section class="adm-card">
            <div class="adm-card-head"><h2>Payment</h2></div>
            <div class="adm-card-body">
                <form method="post" class="adm-field"><?= csrf_field() ?><input type="hidden" name="action" value="payment">
                    <select class="adm-select" name="payment_status" aria-label="Payment status">
                        <?php foreach (['unpaid', 'paid', 'refunded'] as $p): ?><option value="<?= e($p) ?>"<?= adm_sel($order['payment_status'], $p) ?>><?= e(ucfirst($p)) ?></option><?php endforeach; ?>
                    </select>
                    <button class="adm-btn" type="submit">Save payment</button>
                </form>
            </div>
        </section>
        <section class="adm-card">
            <div class="adm-card-head"><h2>Internal note</h2></div>
            <div class="adm-card-body">
                <form method="post" class="adm-field"><?= csrf_field() ?><input type="hidden" name="action" value="notes">
                    <textarea class="adm-textarea" name="admin_notes" maxlength="500" placeholder="Only staff can see this"><?= e($order['admin_notes']) ?></textarea>
                    <button class="adm-btn" type="submit">Save note</button>
                </form>
            </div>
        </section>
        <?php endif; ?>
        <?php if (admin_can('orders.delete')): ?>
        <section class="adm-card">
            <div class="adm-card-body">
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete">
                    <button class="adm-btn adm-btn-danger adm-btn-block" type="submit" data-confirm="Delete order <?= e($label) ?> permanently? Items will be returned to stock. This cannot be undone." data-confirm-yes="Delete order"><?= ai('trash', 16) ?> Delete order</button>
                </form>
            </div>
        </section>
        <?php endif; ?>
    </aside>
</div>
<?php admin_footer(); ?>
