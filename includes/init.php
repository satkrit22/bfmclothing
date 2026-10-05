<?php
/**
 * Bootstrap. Every public page starts with:
 *
 *     require_once __DIR__ . '/includes/init.php';
 *
 * Loads configuration, session, database, helpers and authentication.
 */

declare(strict_types=1);

if (defined('BFM_INIT')) {
    return;
}
define('BFM_INIT', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Last-resort handler: log the real error, show a friendly page.
set_exception_handler(function (Throwable $e): void {
    error_log('[uncaught] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (APP_DEBUG) {
        throw $e;
    }
    render_error_page(500, 'Something went wrong', 'We hit an unexpected problem. Please try again in a moment.');
});

// Restore a "remember me" login when the session has expired.
try {
    attempt_remember_login();
} catch (Throwable $e) {
    error_log('[remember] ' . $e->getMessage());
}
