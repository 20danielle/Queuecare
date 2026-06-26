<?php
/**
 * config/database.php
 * Connexion PDO - Base : files_attente
 * Compatible Railway et environnement local WampServer.
 */

function envValue(array $keys, string $default): string
{
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
    }

    return $default;
}

function envInt(array $keys, int $default): int
{
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return (int) $value;
        }
    }

    return $default;
}

function parseMysqlUrl(string $url): array
{
    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return [];
    }

    return [
        'host' => $parts['host'],
        'port' => isset($parts['port']) ? (int) $parts['port'] : 3306,
        'user' => isset($parts['user']) ? urldecode($parts['user']) : 'root',
        'pass' => isset($parts['pass']) ? urldecode($parts['pass']) : '',
        'db'   => isset($parts['path']) ? ltrim($parts['path'], '/') : 'files_attente',
    ];
}

$mysqlUrl = envValue(['MYSQL_URL', 'MYSQL_PUBLIC_URL'], '');
$parsedMysqlUrl = $mysqlUrl !== '' ? parseMysqlUrl($mysqlUrl) : [];

class Database
{
    private string $host;
    private string $dbname;
    private string $user;
    private string $pass;
    private string $charset = 'utf8mb4';
    private int    $port;

    private static ?Database $instance = null;
    private ?PDO $pdo = null;

    private function __construct()
    {
        date_default_timezone_set('Africa/Douala');

        global $parsedMysqlUrl;

        $this->host = envValue(['MYSQLHOST', 'MYSQL_HOST', 'DB_HOST'], $parsedMysqlUrl['host'] ?? 'localhost');
        $this->dbname = envValue(['MYSQLDATABASE', 'MYSQL_DATABASE', 'DB_NAME'], $parsedMysqlUrl['db'] ?? 'files_attente');
        $this->user = envValue(['MYSQLUSER', 'MYSQL_USER', 'DB_USER'], $parsedMysqlUrl['user'] ?? 'root');
        $this->pass = envValue(['MYSQLPASSWORD', 'MYSQL_PASSWORD', 'DB_PASS'], $parsedMysqlUrl['pass'] ?? '');
        $this->port = envInt(['MYSQLPORT', 'MYSQL_PORT', 'DB_PORT'], $parsedMysqlUrl['port'] ?? 3306);
    }

    private function __clone() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getConnection(): PDO
    {
        if ($this->pdo === null) {
            $dsn = "mysql:host={$this->host};port={$this->port}"
                 . ";dbname={$this->dbname};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];

            try {
                $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
            } catch (PDOException $e) {
                error_log('[DB] ' . $e->getMessage());
                die('Erreur de connexion a la base de donnees.');
            }
        }

        return $this->pdo;
    }
}
