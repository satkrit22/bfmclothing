<?php
/** One account: create / edit / disable / delete, plus the customer's order history. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-ui.php';
admin_require('customers.view');

$me       = current_admin();
$canEdit  = admin_can('customers.manage');
$canRoles = admin_can('users.roles');
$id       = input_int('id', 0, 'get');
$user     = $id > 0 ? db_one('SELECT * FROM users WHERE id = ?', [$id]) : null;
if ($id > 0 && !$user) {
    flash('error', 'That account was not found.');
    admin_redirect('customers.php');
}
$isNew = !$user;
if ($isNew && !$canEdit) {
    render_error_page(403, 'Access denied', 'Your role cannot create accounts.');
}
// Staff / admin accounts can only be touched by someone who may assign roles.
if ($user && $user['role'] !== 'customer' && !$canRoles && (int)$user['id'] !== $me['id']) {
    render_error_page(403, 'Access denied', 'Only a super admin can open staff accounts.');
}
$newStaff = $isNew && input_str('new', 8, 'get') === 'staff' && $canRoles;

$f = $user ?: ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'username' => '', 'role' => $newStaff ? 'staff' : 'customer', 'status' => 'active'];
$errors = [];
$isSelf = $user && (int)$user['id'] === $me['id'];

if (is_post()) {
    require_csrf();
    $action = input_str('action', 10);
    if (!$canEdit && !($isSelf && $action === 'save')) {
        render_error_page(403, 'Access denied', 'Your role cannot change accounts.');
    }

    if ($action === 'delete' && $user) {
        if ($isSelf) {
            flash('error', 'You cannot delete your own account.');
        } elseif ($user['role'] === 'super_admin') {
            flash('error', 'A super admin account cannot be deleted here.');
        } else {
            db_query('DELETE FROM users WHERE id = ?', [$id]);
            audit_log('user.delete', 'user', $id, ['email' => $user['email']]);
            flash('success', 'Account deleted. Their past orders were kept.');
            admin_redirect('customers.php');
        }
        admin_redirect('customer.php', ['id' => $id]);
    }

    $f['first_name'] = input_str('first_name', 60);
    $f['last_name']  = input_str('last_name', 60);
    $f['email']      = strtolower(input_str('email', 190));
    $f['phone']      = input_str('phone', 30);
    $f['status']     = input_str('status', 10) === 'disabled' ? 'disabled' : 'active';
    $password        = (string)($_POST['password'] ?? '');
    $role            = $f['role'];
    if ($canRoles) {
        $r = input_str('role', 12);
        $role = in_array($r, ['customer', 'staff', 'admin', 'super_admin'], true) ? $r : $role;
        $f['username'] = strtolower(preg_replace('/[^A-Za-z0-9._-]/', '', input_str('username', 80)));
    }
    if ($isSelf) { // never lock yourself out or demote yourself by accident
        $role = $user['role'];
        $f['status'] = 'active';
    }
    $f['role'] = $role;

    if ($f['first_name'] === '' || $f['last_name'] === '') {
        $errors[] = 'Please enter a first and last name.';
    }
    if (!is_valid_email($f['email'])) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (db_value('SELECT id FROM users WHERE email = ? AND id <> ?', [$f['email'], $id])) {
        $errors[] = 'Another account already uses that email address.';
    }
    if ($f['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $f['phone'])) {
        $errors[] = 'Please enter a valid phone number.';
    }
    if (($f['username'] ?? '') !== '' && db_value('SELECT id FROM users WHERE username = ? AND id <> ?', [$f['username'], $id])) {
        $errors[] = 'That username is taken.';
    }
    if ($role !== 'customer' && ($f['username'] ?? '') === '') {
        $f['username'] = strtolower(preg_replace('/[^a-z0-9]/', '', strstr($f['email'], '@', true) ?: 'staff'));
        if (db_value('SELECT id FROM users WHERE username = ? AND id <> ?', [$f['username'], $id])) {
            $f['username'] .= (string)random_int(10, 99);
        }
    }
    if ($isNew && $password === '') {
        $errors[] = 'Please set a password for the new account.';
    }
    if ($password !== '' && ($pe = password_error($password)) !== null) {
        $errors[] = $pe;
    }
    if ($user && $user['role'] === 'super_admin' && $role !== 'super_admin' && (int)db_value("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND status = 'active'") <= 1) {
        $errors[] = 'There must always be at least one active super admin.';
    }

    if (!$errors) {
        $active = $f['status'] === 'active' ? 1 : 0;
        $username = ($f['username'] ?? '') !== '' ? $f['username'] : null;
        if ($isNew) {
            db_query('INSERT INTO users (first_name, last_name, email, username, password_hash, role, status, phone, is_active) VALUES (?,?,?,?,?,?,?,?,?)',
                [$f['first_name'], $f['last_name'], $f['email'], $username, password_hash($password, PASSWORD_DEFAULT), $role, $f['status'], $f['phone'] ?: null, $active]);
            $id = (int)db()->lastInsertId();
            audit_log('user.create', 'user', $id, ['email' => $f['email'], 'role' => $role]);
        } else {
            $sql = 'UPDATE users SET first_name=?, last_name=?, email=?, phone=?, role=?, status=?, is_active=?' . ($canRoles ? ', username=?' : '') . ($password !== '' ? ', password_hash=?' : '') . ' WHERE id=?';
            $vals = [$f['first_name'], $f['last_name'], $f['email'], $f['phone'] ?: null, $role, $f['status'], $active];
            if ($canRoles) {
                $vals[] = $username;
            }
            if ($password !== '') {
                $vals[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $vals[] = $id;
            db_query($sql, $vals);
            if ($user['role'] !== $role) {
                audit_log('user.role', 'user', $id, ['from' => $user['role'], 'to' => $role]);
            } else {
                audit_log('user.update', 'user', $id, ['email' => $f['email']]);
            }
        }
        flash('success', 'Account saved.');
        admin_redirect('customer.php', ['id' => $id]);
    }
}

$orders = $user ? db_all('SELECT id, order_number, total, status, payment_status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 25', [$id]) : [];
$stats = $user ? db_one("SELECT COUNT(*) AS c, COALESCE(SUM(total), 0) AS s FROM orders WHERE user_id = ? AND status <> 'cancelled'", [$id]) : ['c' => 0, 's' => 0];
$readonly = !$canEdit && !$isSelf;

admin_header($isNew ? ($newStaff ? 'New staff member' : 'New customer') : $f['first_name'] . ' ' . $f['last_name'], 'customers', [
    'back'     => 'customers.php',
    'subtitle' => $user ? ucwords(str_replace('_', ' ', $user['role'])) . ' · joined ' . format_date($user['created_at']) : 'Create an account',
]);
foreach ($errors as $err) {
    echo '<div class="adm-alert adm-alert-error" role="alert">' . ai('alert', 18) . '<span>' . e($err) . '</span></div>';
}
?>
<div class="adm-split">
    <div class="adm-stack">
        <section class="adm-card">
            <div class="adm-card-head"><h2>Account</h2></div>
            <form class="adm-card-body adm-form-grid" method="post" action="<?= e(admin_url('customer.php', $user ? ['id' => $id] : ($newStaff ? ['new' => 'staff'] : []))) ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="save">
                <div class="adm-field"><label for="first_name">First name</label><input class="adm-input" id="first_name" name="first_name" required maxlength="60" value="<?= e($f['first_name']) ?>"<?= $readonly ? ' disabled' : '' ?>></div>
                <div class="adm-field"><label for="last_name">Last name</label><input class="adm-input" id="last_name" name="last_name" required maxlength="60" value="<?= e($f['last_name']) ?>"<?= $readonly ? ' disabled' : '' ?>></div>
                <div class="adm-field"><label for="email">Email</label><input class="adm-input" id="email" name="email" type="email" required maxlength="190" value="<?= e($f['email']) ?>"<?= $readonly ? ' disabled' : '' ?>></div>
                <div class="adm-field"><label for="phone">Phone</label><input class="adm-input" id="phone" name="phone" type="tel" maxlength="30" value="<?= e($f['phone']) ?>"<?= $readonly ? ' disabled' : '' ?>></div>
                <?php if ($canRoles && !$readonly): ?>
                    <div class="adm-field"><label for="role">Role</label>
                        <select class="adm-select" id="role" name="role"<?= $isSelf ? ' disabled' : '' ?>>
                            <?php foreach (['customer' => 'Customer', 'staff' => 'Staff (orders & support)', 'admin' => 'Admin (store management)', 'super_admin' => 'Super admin (everything)'] as $k => $l): ?>
                                <option value="<?= e($k) ?>"<?= adm_sel($f['role'], $k) ?>><?= e($l) ?></option>
                            <?php endforeach; ?>
                        </select><?php if ($isSelf): ?><small>You cannot change your own role.</small><?php endif; ?></div>
                    <div class="adm-field"><label for="username">Username</label><input class="adm-input" id="username" name="username" maxlength="80" value="<?= e($f['username'] ?? '') ?>"><small>Staff can sign in with this or their email.</small></div>
                <?php endif; ?>
                <div class="adm-field"><label for="status">Status</label>
                    <select class="adm-select" id="status" name="status"<?= ($isSelf || $readonly) ? ' disabled' : '' ?>>
                        <option value="active"<?= adm_sel($f['status'], 'active') ?>>Active</option><option value="disabled"<?= adm_sel($f['status'], 'disabled') ?>>Disabled (cannot log in)</option>
                    </select></div>
                <div class="adm-field"><label for="password"><?= $isNew ? 'Password' : 'New password' ?></label><input class="adm-input" id="password" name="password" type="password" autocomplete="new-password"<?= $isNew ? ' required' : '' ?><?= $readonly ? ' disabled' : '' ?>><small><?= $isNew ? 'At least 8 characters with a letter and a number.' : 'Leave empty to keep the current password.' ?></small></div>
                <?php if (!$readonly): ?><div class="adm-full adm-formbar"><button class="adm-btn adm-btn-primary" type="submit"><?= $isNew ? 'Create account' : 'Save changes' ?></button></div><?php endif; ?>
            </form>
        </section>

        <?php if ($user): ?>
        <section class="adm-card">
            <div class="adm-card-head"><h2>Orders</h2><span class="adm-muted"><?= (int)$stats['c'] ?> orders &middot; <?= e(money($stats['s'])) ?> spent</span></div>
            <?php if (!$orders): ?>
                <div class="adm-empty"><p>No orders from this account yet.</p></div>
            <?php else: ?>
                <div class="adm-table-wrap"><table class="adm-table">
                    <thead><tr><th>Order</th><th>Date</th><th>Payment</th><th>Status</th><th class="adm-num">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><a href="<?= e(admin_url('order.php', ['id' => $o['id']])) ?>"><strong><?= e($o['order_number'] ?: '#' . $o['id']) ?></strong></a></td>
                            <td class="adm-muted"><?= e(format_date($o['created_at'])) ?></td>
                            <td><?= adm_payment_badge((string)$o['payment_status']) ?></td>
                            <td><?= adm_status_badge((string)$o['status']) ?></td>
                            <td class="adm-num"><?= e(money($o['total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>

    <?php if ($user && $canEdit && !$isSelf && $user['role'] !== 'super_admin'): ?>
    <aside class="adm-card">
        <div class="adm-card-head"><h2>Danger zone</h2></div>
        <div class="adm-card-body adm-stack">
            <p class="adm-muted">Deleting removes the account, wishlist and saved cart. Their past orders stay in your records.</p>
            <form method="post" action="<?= e(admin_url('customer.php', ['id' => $id])) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="delete">
                <button class="adm-btn adm-btn-danger adm-btn-block" type="submit" data-confirm="Delete the account of <?= e($user['email']) ?>? This cannot be undone." data-confirm-yes="Delete account"><?= ai('trash', 16) ?> Delete account</button></form>
            <p class="adm-muted">Prefer to keep the history? Set the status to Disabled instead.</p>
        </div>
    </aside>
    <?php endif; ?>
</div>
<?php admin_footer(); ?>
