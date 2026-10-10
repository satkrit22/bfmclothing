<?php
/**
 * Admin authentication. Include this at the top of every admin page
 * EXCEPT admin/login.php:
 *
 *     require_once __DIR__ . '/../includes/admin-auth.php';
 *
 * It loads the app, then blocks anyone who is not a logged-in admin.
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';

const ADMIN_IDLE_SECONDS = 7200; // log out after 2 hours of inactivity

function is_admin(): bool
{
    return has_role('staff', 'admin', 'super_admin');
}

function current_admin(): ?array
{
    if (!is_admin()) return null;
    $user = current_user();
    if (!$user) return null;
    return ['id'=>$user['id'], 'name'=>trim($user['first_name'].' '.$user['last_name']), 'email'=>$user['email'], 'role'=>current_role()];
}

function admin_login(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id']        = (int)$admin['id'];
    $_SESSION['admin_last_seen'] = time();
    unset($_SESSION['csrf_token']);
    db_query('UPDATE admins SET last_login_at = NOW() WHERE id = ?', [(int)$admin['id']]);
}

function admin_logout(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_last_seen']);
    session_regenerate_id(true);
}

/** Block access for non-admins (and expire idle sessions). */
function require_admin(): void
{
    if (is_admin()) {
        $last = (int)($_SESSION['admin_last_seen'] ?? 0);
        if ($last > 0 && (time() - $last) > ADMIN_IDLE_SECONDS) {
            admin_logout();
            flash('info', 'You were logged out because of inactivity.');
        } else {
            $_SESSION['admin_last_seen'] = time();
        }
    }
    if (!is_admin() || current_admin() === null) {
        redirect('admin/login.php');
    }
}

/** Only super admins may do this. */
function require_super_admin(): void
{
    require_admin();
    $admin = current_admin();
    if (!$admin || $admin['role'] !== 'super_admin') {
        render_error_page(403, 'Access denied', 'You do not have permission to open this page.');
    }
}

// Every page that includes this file is protected automatically,
// except the login page, which defines ADMIN_LOGIN_PAGE before including it.
if (!defined('ADMIN_LOGIN_PAGE')) {
    require_admin();
}
