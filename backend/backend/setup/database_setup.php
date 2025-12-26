<?php
/**
 * Database Setup and Test Script for BloodConnect
 * Run this file to set up and test your database connection
 */

// Include database configuration
require_once __DIR__ . '/../config/database.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type
header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BloodConnect Database Setup</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e74c3c;
        }
        .header h1 {
            color: #e74c3c;
            margin: 0;
        }
        .status-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
        }
        .success {
            border-left: 4px solid #28a745;
            background-color: #d4edda;
        }
        .error {
            border-left: 4px solid #dc3545;
            background-color: #f8d7da;
        }
        .warning {
            border-left: 4px solid #ffc107;
            background-color: #fff3cd;
        }
        .info {
            border-left: 4px solid #17a2b8;
            background-color: #d1ecf1;
        }
        .status-title {
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        .config-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .config-table th,
        .config-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .config-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #e74c3c;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            margin: 5px;
        }
        .btn:hover {
            background-color: #c0392b;
        }
        .btn-success {
            background-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
        }
        .code-block {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
            margin: 10px 0;
        }
        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            background-color: #e74c3c;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            font-weight: bold;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🩸 BloodConnect Database Setup</h1>
            <p>Database initialization and connection testing for WAMP Server</p>
        </div>

        <?php
        // Configuration display
        echo '<div class="status-card info">';
        echo '<div class="status-title">📋 Current Configuration</div>';
        echo '<table class="config-table">';
        echo '<tr><th>Setting</th><th>Value</th></tr>';
        echo '<tr><td>Database Host</td><td>' . DB_HOST . '</td></tr>';
        echo '<tr><td>Database Name</td><td>' . DB_NAME . '</td></tr>';
        echo '<tr><td>Database User</td><td>' . DB_USER . '</td></tr>';
        echo '<tr><td>Database Password</td><td>' . (DB_PASS ? '****** (Set)' : 'Not Set') . '</td></tr>';
        echo '<tr><td>Character Set</td><td>' . DB_CHARSET . '</td></tr>';
        echo '</table>';
        echo '</div>';

        // Step 1: Test basic connection
        echo '<div class="status-card">';
        echo '<div class="status-title"><span class="step-number">1</span>Testing Database Connection</div>';
        
        try {
            $db = Database::getInstance();
            $connectionTest = $db->testConnection();
            
            if ($connectionTest) {
                echo '<div class="success">✅ Database connection successful!</div>';
                
                // Get database info
                $dbInfo = $db->getDatabaseInfo();
                if ($dbInfo) {
                    echo '<p><strong>MySQL Version:</strong> ' . $dbInfo['version'] . '</p>';
                    echo '<p><strong>Connected Database:</strong> ' . $dbInfo['database_name'] . '</p>';
                }
            } else {
                echo '<div class="error">❌ Database connection failed!</div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="error">❌ Connection Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            echo '<div class="warning">';
            echo '<strong>Troubleshooting Steps:</strong><br>';
            echo '1. Make sure WAMP Server is running<br>';
            echo '2. Check if MySQL service is started<br>';
            echo '3. Verify the password is correct (14162121)<br>';
            echo '4. Ensure the database "bloodconnect" exists<br>';
            echo '</div>';
        }
        echo '</div>';

        // Step 2: Check/Create Database Tables
        if (isset($db) && $connectionTest) {
            echo '<div class="status-card">';
            echo '<div class="status-title"><span class="step-number">2</span>Checking Database Tables</div>';
            
            try {
                $tableCheck = $db->checkTables();
                
                if ($tableCheck['all_tables_exist']) {
                    echo '<div class="success">✅ All required tables exist!</div>';
                    echo '<p><strong>Tables found:</strong> ' . $tableCheck['total_tables'] . ' / ' . $tableCheck['required_tables'] . '</p>';
                } else {
                    echo '<div class="warning">⚠️ Some tables are missing</div>';
                    echo '<p><strong>Tables found:</strong> ' . $tableCheck['total_tables'] . ' / ' . $tableCheck['required_tables'] . '</p>';
                    
                    if (!empty($tableCheck['missing_tables'])) {
                        echo '<p><strong>Missing tables:</strong> ' . implode(', ', $tableCheck['missing_tables']) . '</p>';
                    }
                    
                    // Auto-initialize database
                    echo '<div class="info">';
                    echo '<strong>Attempting to create missing tables...</strong><br>';
                    
                    $initResult = initializeDatabase();
                    
                    if ($initResult['success']) {
                        echo '<div class="success">✅ ' . $initResult['message'] . '</div>';
                    } else {
                        echo '<div class="error">❌ ' . $initResult['message'] . '</div>';
                    }
                    echo '</div>';
                }
                
            } catch (Exception $e) {
                echo '<div class="error">❌ Table Check Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            echo '</div>';

            // Step 3: Test Sample Queries
            echo '<div class="status-card">';
            echo '<div class="status-title"><span class="step-number">3</span>Testing Sample Queries</div>';
            
            try {
                $pdo = $db->getConnection();
                
                // Test users table
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
                $userCount = $stmt->fetch()['count'];
                echo '<p>✅ Users table: ' . $userCount . ' records</p>';
                
                // Test system settings
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM system_settings");
                $settingsCount = $stmt->fetch()['count'];
                echo '<p>✅ System settings: ' . $settingsCount . ' records</p>';
                
                // Test blood types enum
                $stmt = $pdo->query("SHOW COLUMNS FROM patients LIKE 'blood_type'");
                $bloodTypeColumn = $stmt->fetch();
                if ($bloodTypeColumn) {
                    echo '<p>✅ Blood type enum configured correctly</p>';
                }
                
                echo '<div class="success">✅ All sample queries executed successfully!</div>';
                
            } catch (Exception $e) {
                echo '<div class="error">❌ Query Test Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            echo '</div>';

            // Step 4: Next Steps
            echo '<div class="status-card success">';
            echo '<div class="status-title"><span class="step-number">4</span>Setup Complete!</div>';
            echo '<p>Your BloodConnect database is ready to use. Here are the next steps:</p>';
            echo '<ul>';
            echo '<li>✅ Database connection established</li>';
            echo '<li>✅ All tables created successfully</li>';
            echo '<li>✅ Default admin user created (admin@bloodconnect.com)</li>';
            echo '<li>✅ System settings initialized</li>';
            echo '</ul>';
            
            echo '<div class="info">';
            echo '<strong>Default Admin Login:</strong><br>';
            echo 'Email: admin@bloodconnect.com<br>';
            echo 'Password: password (Please change this immediately)<br>';
            echo '</div>';
            
            echo '<p><strong>You can now:</strong></p>';
            echo '<ul>';
            echo '<li>Start building your API endpoints</li>';
            echo '<li>Test user registration and login</li>';
            echo '<li>Implement blood request workflows</li>';
            echo '<li>Set up donation management</li>';
            echo '</ul>';
            echo '</div>';
        }
        ?>

        <!-- Manual Setup Instructions -->
        <div class="status-card info">
            <div class="status-title">📖 Manual Setup Instructions</div>
            <p>If automatic setup fails, you can manually create the database:</p>
            
            <p><strong>1. Open phpMyAdmin in WAMP:</strong></p>
            <div class="code-block">http://localhost/phpmyadmin/</div>
            
            <p><strong>2. Create a new database named "bloodconnect":</strong></p>
            <div class="code-block">CREATE DATABASE bloodconnect;</div>
            
            <p><strong>3. Import the SQL file:</strong></p>
            <div class="code-block">backend/database/bloodconnect_database.sql</div>
            
            <p><strong>4. Verify your WAMP configuration:</strong></p>
            <ul>
                <li>MySQL password: 14162121</li>
                <li>Apache and MySQL services running</li>
                <li>PHP version 7.4 or higher</li>
            </ul>
        </div>

        <!-- Action Buttons -->
        <div style="text-align: center; margin-top: 30px;">
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn">🔄 Refresh Test</a>
            <a href="../api/test_connection.php" class="btn btn-success">🧪 Test API Connection</a>
        </div>
    </div>
</body>
</html>