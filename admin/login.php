<?php
declare(strict_types=1);
const ADMIN_LOGIN_PAGE = true;
require_once __DIR__ . '/../includes/admin-auth.php';

if (is_admin()) { redirect('admin/index.php'); }
$error = '';
if (is_post()) {
    require_csrf();
    $email = strtolower(input_str('email', 190));
    $password = (string)($_POST['password'] ?? '');
    $admin = $email !== '' ? db_one('SELECT * FROM admins WHERE email = ? AND is_active = 1 LIMIT 1', [$email]) : null;
    if ($admin && password_verify($password, $admin['password_hash'])) {
        admin_login($admin);
        redirect('admin/index.php');
    }
    $error = 'The email or password is incorrect.';
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin login | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>"><style>.admin-auth{max-width:460px;margin:10vh auto;padding:2rem}.admin-auth h1{margin-bottom:1.5rem}.admin-error{color:#a33;margin-bottom:1rem}</style></head><body><main class="admin-auth"><a class="logo" href="<?= e(url()) ?>">BFM</a><h1>Admin login</h1><?php if ($error): ?><p class="admin-error" role="alert"><?= e($error) ?></p><?php endif; ?><form method="post"><?= csrf_field() ?><label>Email<input type="email" name="email" required autocomplete="username"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button class="btn btn-primary" type="submit">Sign in</button></form></main></body></html>?
