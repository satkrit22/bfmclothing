<?php
/** Staff sign-in. Only staff / admin / super_admin accounts can use this form. */
declare(strict_types=1);

define('ADMIN_LOGIN_PAGE', true);
require_once __DIR__ . '/../includes/admin-auth.php';

if (is_admin()) {
    redirect('admin/index.php');
}

$errors   = [];
$identity = '';
$next     = safe_redirect_target($_GET['next'] ?? '', 'admin/index.php');

if (is_post()) {
    require_csrf();
    $identity = strtolower(input_str('identity', 190));
    $password = (string)($_POST['password'] ?? '');

    if ($identity !== '' && login_throttled('admin', $identity)) {
        $errors[] = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $user = $identity !== ''
            ? db_one(
                "SELECT * FROM users WHERE (LOWER(email) = ? OR LOWER(username) = ?)
                   AND role IN ('staff','admin','super_admin') AND is_active = 1 AND status = 'active' LIMIT 1",
                [$identity, $identity]
            )
            : null;

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            if ($identity !== '') {
                record_failed_login('admin', $identity);
            }
            $errors[] = 'Incorrect email/username or password.';
        } else {
            clear_failed_logins('admin', $identity);
            if (password_needs_rehash((string)$user['password_hash'], PASSWORD_DEFAULT)) {
                db_query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), (int)$user['id']]);
            }
            login_user($user, false);
            $_SESSION['admin_last_seen'] = time();
            audit_log('admin.login', 'user', (int)$user['id']);
            redirect($next);
        }
    }
}
$flashes = flash_pull();
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Admin sign in | <?= e(setting('site_name', APP_NAME)) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="adm">
<main class="adm-login">
    <div class="adm-login-card">
        <span class="adm-logo">BFM</span>
        <h1>Admin sign in</h1>
        <p class="adm-muted">Manage products, orders, customers and sales.</p>
        <?php foreach ($flashes as [$type, $message]): ?>
            <div class="adm-alert adm-alert-info" style="margin-top:16px"><span><?= e($message) ?></span></div>
        <?php endforeach; ?>
        <?php foreach ($errors as $error): ?>
            <div class="adm-alert adm-alert-error" role="alert" style="margin-top:16px"><span><?= e($error) ?></span></div>
        <?php endforeach; ?>
        <form method="post" autocomplete="on">
            <?= csrf_field() ?>
            <div class="adm-field"><label for="identity">Email or username</label>
                <input class="adm-input" id="identity" name="identity" type="text" required autofocus autocomplete="username" maxlength="190" value="<?= e($identity) ?>"></div>
            <div class="adm-field"><label for="password">Password</label>
                <input class="adm-input" id="password" name="password" type="password" required autocomplete="current-password"></div>
            <button class="adm-btn adm-btn-primary adm-btn-block" type="submit">Sign in</button>
        </form>
        <p class="adm-muted" style="margin-top:18px;text-align:center"><a href="<?= e(url()) ?>">&larr; Back to the store</a></p>
    </div>
</main>
</body>
</html>
