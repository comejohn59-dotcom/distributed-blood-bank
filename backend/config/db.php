<?php
class Database {

    // ✅ Correct host for WAMP
    private static $host = 'localhost';
    private static $db_name = 'blood';

    // ✅ Use app user (NOT root)
    private static $username = 'appuser';
    private static $password = 'change_this_password'; // <-- put REAL password here
    private static $port = 3306;

    private static $conn = null;
    private static $pdo  = null;

    /* ---------- mysqli connection ---------- */
    public static function getConnection() {
        if (self::$conn instanceof mysqli) {
            return self::$conn;
        }

        $mysqli = new mysqli(
            self::$host,
            self::$username,
            self::$password,
            self::$db_name,
            self::$port
        );

        if ($mysqli->connect_error) {
            die("DB connection failed: " . $mysqli->connect_error);
        }

        $mysqli->set_charset('utf8');
        self::$conn = $mysqli;
        return self::$conn;
    }

    /* ---------- PDO connection (if needed) ---------- */
    public static function getPDO() {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        try {
            $dsn = "mysql:host=" . self::$host .
                   ";dbname=" . self::$db_name .
                   ";port=" . self::$port .
                   ";charset=utf8";

            self::$pdo = new PDO($dsn, self::$username, self::$password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die("PDO connection failed: " . $e->getMessage());
        }

        return self::$pdo;
    }

    /* ---------- Helpers ---------- */
    public function fetchAll($sql, $params = []) {
        $conn = self::getConnection();
        $stmt = $conn->prepare($sql);

        if ($params) {
            $types = '';
            foreach ($params as $p) {
                $types .= is_int($p) ? 'i' : 's';
            }
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function fetchOne($sql, $params = []) {
        $rows = $this->fetchAll($sql, $params);
        return $rows[0] ?? null;
    }

    public function execute($sql, $params = []) {
        $conn = self::getConnection();
        $stmt = $conn->prepare($sql);

        if ($params) {
            $types = '';
            foreach ($params as $p) {
                $types .= is_int($p) ? 'i' : 's';
            }
            $stmt->bind_param($types, ...$params);
        }

        return $stmt->execute();
    }
}

/* ---------- Global constants ---------- */
define('DB_HOST', 'localhost');
define('DB_NAME', 'blood');
define('DB_USER', 'appuser');
define('DB_PASS', 'change_this_password'); // <-- SAME password
define('DB_PORT', 3306);
