<?php
header('Content-Type: application/json');
// Quick DB connection tester using config/db.php
require_once __DIR__ . '/config/db.php';

$result = ['mysqli' => null, 'pdo' => null];

// mysqli test
try {
    $mysqli = @new mysqli(Database::$host ?? 'localhost', Database::$username ?? 'root', Database::$password ?? '', Database::$db_name ?? 'blood');
    if ($mysqli->connect_error) {
        $result['mysqli'] = ['ok' => false, 'error' => $mysqli->connect_error];
    } else {
        $result['mysqli'] = ['ok' => true];
        $mysqli->close();
    }
} catch (Throwable $t) {
    $result['mysqli'] = ['ok' => false, 'error' => $t->getMessage()];
}

// PDO test
try {
    $dsn = "mysql:host=" . (Database::$host ?? 'localhost') . ";dbname=" . (Database::$db_name ?? 'blood') . ";charset=utf8";
    $pdo = @new PDO($dsn, Database::$username ?? 'root', Database::$password ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $result['pdo'] = ['ok' => true];
    $pdo = null;
} catch (Throwable $t) {
    $result['pdo'] = ['ok' => false, 'error' => $t->getMessage()];
}

echo json_encode($result, JSON_PRETTY_PRINT);

?>
