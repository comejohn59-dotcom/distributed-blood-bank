<?php
/**
 * API Connection Test Endpoint
 * Tests database connection and returns JSON response
 */

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database configuration
require_once __DIR__ . '/../config/database.php';

// Response array
$response = [
    'success' => false,
    'message' => '',
    'data' => [],
    'timestamp' => date('Y-m-d H:i:s'),
    'version' => '1.0.0'
];

try {
    // Test database connection
    $db = Database::getInstance();
    $connectionTest = $db->testConnection();
    
    if (!$connectionTest) {
        throw new Exception('Database connection failed');
    }
    
    // Get database info
    $dbInfo = $db->getDatabaseInfo();
    
    // Check tables
    $tableCheck = $db->checkTables();
    
    // Get some sample data
    $pdo = $db->getConnection();
    
    // Count records in main tables
    $counts = [];
    $tables = ['users', 'patients', 'donors', 'hospitals', 'blood_requests', 'donation_offers'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
            $counts[$table] = $stmt->fetch()['count'];
        } catch (Exception $e) {
            $counts[$table] = 'Error: ' . $e->getMessage();
        }
    }
    
    // Get system settings
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE is_public = 1");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Success response
    $response['success'] = true;
    $response['message'] = 'Database connection successful';
    $response['data'] = [
        'database_info' => $dbInfo,
        'table_status' => $tableCheck,
        'record_counts' => $counts,
        'system_settings' => $settings,
        'connection_details' => [
            'host' => DB_HOST,
            'database' => DB_NAME,
            'user' => DB_USER,
            'charset' => DB_CHARSET
        ]
    ];
    
    http_response_code(200);
    
} catch (Exception $e) {
    // Error response
    $response['success'] = false;
    $response['message'] = 'Connection test failed: ' . $e->getMessage();
    $response['data'] = [
        'error_details' => [
            'type' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ],
        'configuration' => [
            'host' => DB_HOST,
            'database' => DB_NAME,
            'user' => DB_USER,
            'charset' => DB_CHARSET
        ]
    ];
    
    http_response_code(500);
}

// Output JSON response
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>