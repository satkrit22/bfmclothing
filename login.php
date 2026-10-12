<?php
/**
 * Customer + staff login. Staff (staff / admin / super_admin) are sent to the admin panel
 * unless they arrived with a specific ?next= target.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
redirect_if_logged_in();

$errors = [];
$next   = safe_redirect_target($_GET['next'] ?? '', 'account.php');

if (is_post()) {
    require_csrf();
    $identity = strtolower(input_str('identity', 190));
    $password = (string)($_POST['password'] ?? '');

    if ($identity !== '' && login_throttled('user', $identity)) {
        $errors[] = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $user = $identity !== ''
            ? db_one(
                "SELECT * FROM users WHERE (LOWER(email) = ? OR LOWER(username) = ?) AND is_active = 1 AND status = 'active' LIMIT 1",
                [$identity, $identity]
            )
            : null;

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            if ($identity !== '') {
                record_failed_login('user', $identity);
            }
            $errors[] = 'Email/username or password is incorrect.';
        } else {
            clear_failed_logins('user', $identity);
            if (password_needs_rehash((string)$user['password_hash'], PASSWORD_DEFAULT)) {
                db_query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), (int)$user['id']]);
            }
            login_user($user, !empty($_POST['remember']));
            $isStaff = in_array((string)$user['role'], ['staff', 'admin', 'super_admin'], true);
            if ($isStaff) {
                $_SESSION['admin_last_seen'] = time();
                if ($next === 'account.php') {
                    $next = 'admin/index.php';
                }
            }
            redirect($next);
        }
    }
}

$page_title  = 'Log in';
$page_robots = 'noindex,nofollow';
require __DIR__ . '/includes/header.php';
?>
<section class="container auth-page">
    <div class="auth-card">
        <p class="eyebrow">Welcome back</p>
        <h1>Log in to BFM</h1>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endforeach; ?>
        <form method="post" class="form-stack">
            <?= csrf_field() ?>
            <label>Email or username<input name="identity" required autocomplete="username" value="<?= e($_POST['identity'] ?? '') ?>"></label>
            <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
            <label class="check"><input type="checkbox" name="remember"> Remember me</label>
            <button class="btn btn-primary btn-block">Log in</button>
        </form>
        <p class="auth-link">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
