<?php
class Database {
    private static $host = 'localhost';
    private static $db_name = 'blood';

    // MariaDB / WAMP defaults for your environment
    private static $username = 'root';
    private static $password = '';
    // default TCP port for MySQL/MariaDB on WAMP
    private static $port = 3306;
                
    private static $pdo = null;
    private static $conn = null;

    /**
     * Return a mysqli connection. Many existing files call Database::getConnection()
     * (sometimes via an instance). Expose a static method that returns a
     * shared mysqli connection to keep compatibility with the codebase.
     */
    public static function getConnection() {
        if (self::$conn instanceof mysqli) {
            return self::$conn;
        }

        // Allow overriding credentials with DB_* constants or environment variables
        $user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: self::$username);
        $pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: self::$password);
        $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: self::$host);
        $dbname = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: self::$db_name);

        // Support custom port (use DB_PORT constant or env if defined)
        $port = defined('DB_PORT') ? DB_PORT : (getenv('DB_PORT') ?: self::$port);
        $mysqli = new mysqli($host, $user, $pass, $dbname, $port);
        if ($mysqli->connect_error) {
            error_log('Connection error: ' . $mysqli->connect_error);
            return null;
        }
        $mysqli->set_charset('utf8');
        self::$conn = $mysqli;
        return self::$conn;
    }

    /**
     * Return a PDO connection for parts of the codebase that use PDO.
     */
    public static function getPDO() {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        try {
            $user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: self::$username);
            $pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: self::$password);
            $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: self::$host);
            $dbname = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: self::$db_name);
            $port = defined('DB_PORT') ? DB_PORT : (getenv('DB_PORT') ?: self::$port);

            $dsn = "mysql:host=" . $host . ";dbname=" . $dbname . ";charset=utf8";
            if ($port) {
                $dsn .= ";port=" . $port;
            }

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            self::$pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log('PDO Connection error: ' . $e->getMessage());
            return null;
        }

        return self::$pdo;
    }

    /* Instance helper methods using mysqli for convenience */
    public function fetchAll($sql, $params = []) {
        $conn = self::getConnection();
        if (!$conn) return [];

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            // Try direct query for simple SELECTs
            $res = $conn->query($sql);
            if ($res) {
                return $res->fetch_all(MYSQLI_ASSOC);
            }
            return [];
        }

        if ($params) {
            $types = '';
            $vals = [];
            foreach ($params as $p) {
                $types .= is_int($p) ? 'i' : 's';
                $vals[] = $p;
            }
            $stmt->bind_param($types, ...$vals);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function fetchOne($sql, $params = []) {
        $all = $this->fetchAll($sql, $params);
        return $all[0] ?? null;
    }

    public function execute($sql, $params = []) {
        $conn = self::getConnection();
        if (!$conn) return false;

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            return $conn->query($sql);
        }

        if ($params) {
            $types = '';
            $vals = [];
            foreach ($params as $p) {
                $types .= is_int($p) ? 'i' : 's';
                $vals[] = $p;
            }
            $stmt->bind_param($types, ...$vals);
        }

        return $stmt->execute();
    }
}

// Database configuration constants (for any parts of the app that use defines)
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'blood');
}
// sensible defaults for WAMP: user=root and empty password
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', 3306);
}
