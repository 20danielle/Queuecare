<?php
/**
 * config/database.php
 * Compatible WAMP local ET Railway (variables d'environnement)
 */
class Database
{
    private string $host    = 'localhost';
    private string $dbname  = 'files_attente';
    private string $user    = 'root';
    private string $pass    = '';
    private string $charset = 'utf8mb4';
    private int    $port    = 3306;

    private static ?Database $instance = null;
    private ?PDO $pdo = null;

    private function __construct() {
        date_default_timezone_set('Africa/Douala');

        $mysqlUrl = $this->envValue(['MYSQL_URL', 'MYSQL_PUBLIC_URL'], '');
        $parsed = $mysqlUrl !== '' ? $this->parseMysqlUrl($mysqlUrl) : [];

        // Railway : surcharger avec les variables MySQL ou les variables locales
        $this->host = $this->envValue(['MYSQLHOST', 'MYSQL_HOST', 'DB_HOST'], $parsed['host'] ?? $this->host);
        $this->dbname = $this->envValue(['MYSQLDATABASE', 'MYSQL_DATABASE', 'DB_NAME'], $parsed['db'] ?? $this->dbname);
        $this->user = $this->envValue(['MYSQLUSER', 'MYSQL_USER', 'DB_USER'], $parsed['user'] ?? $this->user);
        $this->pass = $this->envValue(['MYSQLPASSWORD', 'MYSQL_PASSWORD', 'DB_PASS'], $parsed['pass'] ?? $this->pass);
        $this->port = (int) $this->envValue(['MYSQLPORT', 'MYSQL_PORT', 'DB_PORT'], isset($parsed['port']) ? (string) $parsed['port'] : (string) $this->port);
    }
    private function __clone() {}

    private function envValue(array $keys, string $default): string
    {
        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value !== false && $value !== '') {
                return $value;
            }
        }
        return $default;
    }

    private function parseMysqlUrl(string $url): array
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

    public static function getInstance(): self
    {
        if (self::$instance === null) self::$instance = new self();
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
                die('Erreur de connexion ?? la base de donn??es.');
            }
        }
        return $this->pdo;
    }
}
