<?php
/**
 * PDO connection (singleton). Uses real prepared statements and exceptions.
 * Database errors are logged; customers only ever see a friendly message.
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (PDOException $e) {
        error_log('[DB connect] ' . $e->getMessage());
        render_error_page(
            503,
            'We will be right back',
            'The store is temporarily unavailable. Please try again in a few minutes.'
        );
    }
    return $pdo;
}

/** Run a prepared statement and return the PDOStatement. */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** Fetch all rows. */
function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

/** Fetch a single row or null. */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Fetch a single column value or null. */
function db_value(string $sql, array $params = [])
{
    $val = db_query($sql, $params)->fetchColumn();
    return $val === false ? null : $val;
}
