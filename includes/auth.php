<?php
/**
 * Customer authentication. Passwords use password_hash() / password_verify().
 */

declare(strict_types=1);

const REMEMBER_COOKIE = 'bfm_remember';
const REMEMBER_DAYS   = 30;

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/** Currently logged-in customer row (without sensitive columns) or null. */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (is_logged_in()) {
            $user = db_one(
                'SELECT id, first_name, last_name, email, phone, created_at FROM users WHERE id = ? AND is_active = 1',
                [(int)$_SESSION['user_id']]
            );
            if ($user === null) {
                unset($_SESSION['user_id']);
            }
        }
    }
    return $user;
}

/** Log a customer in. Regenerates the session id to prevent session fixation. */
function login_user(array $user, bool $remember = false): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    unset($_SESSION['csrf_token']);
    cart_merge_on_login((int)$user['id']);
    if ($remember) {
        set_remember_cookie((int)$user['id']);
    }
}

function logout_user(): void
{
    if (!empty($_SESSION['user_id'])) {
        clear_remember_cookie((int)$_SESSION['user_id']);
    }
    unset($_SESSION['user_id'], $_SESSION['cart'], $_SESSION['coupon']);
    session_regenerate_id(true);
}

/** Redirect guests to the login page and return here afterwards. */
function require_login(): void
{
    if (!is_logged_in()) {
        flash('info', 'Please log in to continue.');
        redirect('login.php?next=' . rawurlencode(current_uri()));
    }
}

/** Redirect logged-in customers away from login/register. */
function redirect_if_logged_in(string $to = 'account.php'): void
{
    if (is_logged_in()) {
        redirect($to);
    }
}

/* ---------------- Password rules ---------------- */

/** Returns an error message, or null when the password is acceptable. */
function password_error(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (strlen($password) > 72) {
        return 'Password must be 72 characters or fewer.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must contain at least one letter and one number.';
    }
    return null;
}

/* ---------------- Remember me (selector + hashed validator) ---------------- */

function set_remember_cookie(int $userId): void
{
    $selector  = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expires   = time() + REMEMBER_DAYS * 86400;
    db_query(
        'UPDATE users SET remember_selector = ?, remember_token_hash = ?, remember_expires = ? WHERE id = ?',
        [$selector, hash('sha256', $validator), date('Y-m-d H:i:s', $expires), $userId]
    );
    setcookie(REMEMBER_COOKIE, $selector . ':' . $validator, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => IS_HTTPS,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clear_remember_cookie(int $userId = 0): void
{
    if ($userId > 0) {
        db_query('UPDATE users SET remember_selector = NULL, remember_token_hash = NULL, remember_expires = NULL WHERE id = ?', [$userId]);
    }
    setcookie(REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'secure' => IS_HTTPS, 'httponly' => true, 'samesite' => 'Lax']);
}

/** Called on every request: log the customer back in from a valid remember cookie. */
function attempt_remember_login(): void
{
    if (is_logged_in() || empty($_COOKIE[REMEMBER_COOKIE]) || !is_string($_COOKIE[REMEMBER_COOKIE])) {
        return;
    }
    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE]);
    if (count($parts) !== 2 || !ctype_xdigit($parts[0]) || !ctype_xdigit($parts[1])) {
        clear_remember_cookie();
        return;
    }
    $user = db_one(
        'SELECT id, remember_token_hash FROM users WHERE remember_selector = ? AND remember_expires > NOW() AND is_active = 1',
        [$parts[0]]
    );
    if ($user && hash_equals((string)$user['remember_token_hash'], hash('sha256', $parts[1]))) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        cart_merge_on_login((int)$user['id']);
        set_remember_cookie((int)$user['id']); // rotate the token
    } else {
        clear_remember_cookie();
    }
}
