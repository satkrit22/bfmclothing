<?php
/** My profile: change name, phone and password. Open to every staff member. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-ui.php';
require_admin();

$me   = current_admin();
$row  = db_one('SELECT id, first_name, last_name, email, phone, password_hash FROM users WHERE id = ?', [$me['id']]);
$errors = [];
$pwErrors = [];

if (is_post()) {
    require_csrf();
    $action = input_str('action', 10);

    if ($action === 'profile') {
        $first = input_str('first_name', 60);
        $last  = input_str('last_name', 60);
        $phone = input_str('phone', 30);
        $row['first_name'] = $first;
        $row['last_name']  = $last;
        $row['phone']      = $phone;
        if ($first === '' || $last === '') {
            $errors[] = 'Please enter your first and last name.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
            $errors[] = 'Please enter a valid phone number.';
        }
        if (!$errors) {
            db_query('UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?', [$first, $last, $phone ?: null, $me['id']]);
            audit_log('user.profile', 'user', $me['id']);
            flash('success', 'Profile updated.');
            admin_redirect('profile.php');
        }
    } elseif ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        if (!password_verify($current, (string)$row['password_hash'])) {
            $pwErrors[] = 'Your current password is not correct.';
        }
        if (($pe = password_error($new)) !== null) {
            $pwErrors[] = $pe;
        } elseif ($new !== $confirm) {
            $pwErrors[] = 'The new passwords do not match.';
        } elseif ($new === $current) {
            $pwErrors[] = 'Choose a password that is different from the current one.';
        }
        if (!$pwErrors) {
            db_query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            audit_log('user.password', 'user', $me['id']);
            flash('success', 'Password changed.');
            admin_redirect('profile.php');
        }
    }
}

admin_header('My profile', '', ['subtitle' => $me['email'] . ' · ' . ucwords(str_replace('_', ' ', $me['role']))]);
foreach (array_merge($errors, $pwErrors) as $err) {
    echo '<div class="adm-alert adm-alert-error" role="alert">' . ai('alert', 18) . '<span>' . e($err) . '</span></div>';
}
?>
<div class="adm-split-even" style="max-width:980px">
    <section class="adm-card">
        <div class="adm-card-head"><h2>Details</h2></div>
        <form class="adm-card-body adm-stack" method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="profile">
            <div class="adm-field"><label for="first_name">First name</label><input class="adm-input" id="first_name" name="first_name" required maxlength="60" value="<?= e($row['first_name']) ?>"></div>
            <div class="adm-field"><label for="last_name">Last name</label><input class="adm-input" id="last_name" name="last_name" required maxlength="60" value="<?= e($row['last_name']) ?>"></div>
            <div class="adm-field"><label for="phone">Phone</label><input class="adm-input" id="phone" name="phone" type="tel" maxlength="30" value="<?= e($row['phone']) ?>"></div>
            <div class="adm-field"><label>Email</label><input class="adm-input" value="<?= e($row['email']) ?>" disabled><small>Ask a super admin to change your sign-in email.</small></div>
            <button class="adm-btn adm-btn-primary" type="submit">Save details</button>
        </form>
    </section>
    <section class="adm-card">
        <div class="adm-card-head"><h2>Change password</h2></div>
        <form class="adm-card-body adm-stack" method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="password">
            <div class="adm-field"><label for="current_password">Current password</label><input class="adm-input" id="current_password" name="current_password" type="password" required autocomplete="current-password"></div>
            <div class="adm-field"><label for="new_password">New password</label><input class="adm-input" id="new_password" name="new_password" type="password" required autocomplete="new-password"><small>At least 8 characters with a letter and a number.</small></div>
            <div class="adm-field"><label for="confirm_password">Confirm new password</label><input class="adm-input" id="confirm_password" name="confirm_password" type="password" required autocomplete="new-password"></div>
            <button class="adm-btn adm-btn-primary" type="submit">Change password</button>
        </form>
    </section>
</div>
<?php admin_footer(); ?>
