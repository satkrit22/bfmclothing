<?php
/**
 * Admin authentication + permissions.
 *
 * Every admin page starts with:
 *     require_once __DIR__ . '/../includes/admin-auth.php';
 *     admin_require('products.manage');          // permission name, see the `permissions` table
 *
 * Staff accounts live in the same `users` table as customers (role = staff | admin | super_admin).
 * Permissions come from role_permissions; super_admin can always do everything.
 * admin/login.php defines ADMIN_LOGIN_PAGE before including this file so it is not self-protected.
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';

const ADMIN_IDLE_SECONDS = 7200; // log out after 2 hours of inactivity

/** Used only if the roles/permissions tables are missing or empty. */
const ADMIN_DEFAULT_PERMISSIONS = [
    'staff' => ['dashboard.view', 'orders.view', 'orders.manage', 'customers.view', 'messages.manage'],
    'admin' => ['dashboard.view', 'orders.view', 'orders.manage', 'customers.view', 'customers.manage', 'products.manage',
                'categories.manage', 'coupons.manage', 'reviews.manage', 'messages.manage', 'reports.view'],
];

function is_admin(): bool
{
    return has_role('staff', 'admin', 'super_admin');
}

function current_admin(): ?array
{
    if (!is_admin()) {
        return null;
    }
    $user = current_user();
    if (!$user) {
        return null;
    }
    return [
        'id'    => (int)$user['id'],
        'name'  => trim($user['first_name'] . ' ' . $user['last_name']),
        'first' => (string)$user['first_name'],
        'email' => (string)$user['email'],
        'role'  => current_role(),
    ];
}

function admin_logout(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_last_seen']);
    logout_user();
}

/** Redirect to the admin login unless a staff member is signed in (also expires idle sessions). */
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

/** Permission names granted to the current role (cached for the request). */
function admin_permissions(): array
{
    static $perms = null;
    if ($perms !== null) {
        return $perms;
    }
    $role = current_role();
    if ($role === 'super_admin') {
        return $perms = ['*'];
    }
    $perms = [];
    try {
        $rows = db_all(
            'SELECT p.name FROM role_permissions rp
             JOIN roles r ON r.id = rp.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE r.name = ?',
            [$role]
        );
        $perms = array_column($rows, 'name');
    } catch (Throwable $e) {
        error_log('[admin_permissions] ' . $e->getMessage());
    }
    if (!$perms) {
        $perms = ADMIN_DEFAULT_PERMISSIONS[$role] ?? [];
    }
    return $perms;
}

function admin_can(string $permission): bool
{
    $perms = admin_permissions();
    return in_array('*', $perms, true) || in_array($permission, $perms, true);
}

/** Sign-in required AND the given permission. Shows a 403 page otherwise. */
function admin_require(string $permission): void
{
    require_admin();
    if (!admin_can($permission)) {
        render_error_page(403, 'Access denied', 'Your role does not have permission to open this page.');
    }
}

/** Kept for older code: super admins only. */
function require_super_admin(): void
{
    require_admin();
    if (current_role() !== 'super_admin') {
        render_error_page(403, 'Access denied', 'Only a super admin can open this page.');
    }
}

/** Record who did what (shown in Admin > Activity log). Never breaks the request if it fails. */
function audit_log(string $action, ?string $entityType = null, ?int $entityId = null, array $details = []): void
{
    try {
        $user = current_user();
        db_query(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)',
            [$user ? (int)$user['id'] : null, $action, $entityType, $entityId,
             $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null, client_ip()]
        );
    } catch (Throwable $e) {
        error_log('[audit_log] ' . $e->getMessage());
    }
}

// Every page that includes this file is protected automatically,
// except the login page, which defines ADMIN_LOGIN_PAGE before including it.
if (!defined('ADMIN_LOGIN_PAGE')) {
    require_admin();
}
