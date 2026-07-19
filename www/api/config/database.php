<?php
// ????????? Configuration BDD ????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????
// Lit les variables Railway MySQL en production
// Sinon utilise les valeurs locales (WAMP/Docker)

function envValue(array $keys, string $default): string {
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
    }
    return $default;
}

function envInt(array $keys, int $default): int {
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return (int) $value;
        }
    }
    return $default;
}

function parseMysqlUrl(string $url): array {
    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return [];
    }

    $result = [
        'host' => $parts['host'],
        'port' => isset($parts['port']) ? (string) $parts['port'] : '3306',
        'user' => isset($parts['user']) ? urldecode($parts['user']) : 'root',
        'pass' => isset($parts['pass']) ? urldecode($parts['pass']) : '',
        'db'   => isset($parts['path']) ? ltrim($parts['path'], '/') : 'files_attente',
    ];

    return $result;
}

$mysqlUrl = envValue(['MYSQL_URL', 'MYSQL_PUBLIC_URL'], '');
$parsedMysqlUrl = $mysqlUrl !== '' ? parseMysqlUrl($mysqlUrl) : [];

define('DB_HOST', envValue(['MYSQLHOST', 'MYSQL_HOST', 'DB_HOST'], $parsedMysqlUrl['host'] ?? 'localhost'));
define('DB_NAME', envValue(['MYSQLDATABASE', 'MYSQL_DATABASE', 'DB_NAME'], $parsedMysqlUrl['db'] ?? 'files_attente'));
define('DB_USER', envValue(['MYSQLUSER', 'MYSQL_USER', 'DB_USER'], $parsedMysqlUrl['user'] ?? 'root'));
define('DB_PASS', envValue(['MYSQLPASSWORD', 'MYSQL_PASSWORD', 'DB_PASS'], $parsedMysqlUrl['pass'] ?? ''));
define('DB_PORT', (string) envInt(['MYSQLPORT', 'MYSQL_PORT', 'DB_PORT'], isset($parsedMysqlUrl['port']) ? (int) $parsedMysqlUrl['port'] : 3306));
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'queuecare_secret_2024');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST, DB_PORT, DB_NAME
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            // ─────────────────────────────────────────────────────────
            //  FIX FUSEAU HORAIRE MYSQL — Cameroun = GMT+1
            //  NOW(), CURDATE(), CURTIME() retourneront l'heure locale
            //  du Cameroun et non UTC (qui est l'heure par défaut Railway)
            // ─────────────────────────────────────────────────────────
            PDO::MYSQL_ATTR_INIT_COMMAND =>
                "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci; " .
                "SET time_zone = '+01:00';",
        ]);
    }
    return $pdo;
}
