<?php
/**
 * Base API Class for BloodConnect
 * Provides common functionality for all API endpoints
 */

require_once __DIR__ . '/../config/database.php';

class BaseAPI {
    protected $db;
    protected $request_method;
    protected $request_data;
    protected $headers;
    
    public function __construct() {
        // Set CORS headers
        $this->setCORSHeaders();
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
        
        // Initialize database connection
        try {
            $this->db = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            $this->sendError('Database connection failed', 500);
        }
        
        // Get request method and data
        $this->request_method = $_SERVER['REQUEST_METHOD'];
        $this->request_data = $this->getRequestData();
        $this->headers = $this->getRequestHeaders();
    }
    
    /**
     * Set CORS headers
     */
    private function setCORSHeaders() {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
    }
    
    /**
     * Get request data based on method
     */
    private function getRequestData() {
        $data = [];
        
        switch ($this->request_method) {
            case 'GET':
                $data = $_GET;
                break;
            case 'POST':
            case 'PUT':
            case 'DELETE':
                $input = file_get_contents('php://input');
                $json_data = json_decode($input, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data = $json_data;
                } else {
                    $data = $_POST;
                }
                break;
        }
        
        return $data;
    }
    
    /**
     * Get request headers
     */
    private function getRequestHeaders() {
        return getallheaders() ?: [];
    }
    
    /**
     * Send JSON response
     */
    protected function sendResponse($data, $status_code = 200, $message = 'Success') {
        http_response_code($status_code);
        
        $response = [
            'success' => $status_code < 400,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s'),
            'status_code' => $status_code
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    /**
     * Send error response
     */
    protected function sendError($message, $status_code = 400, $details = null) {
        http_response_code($status_code);
        
        $response = [
            'success' => false,
            'message' => $message,
            'data' => null,
            'timestamp' => date('Y-m-d H:i:s'),
            'status_code' => $status_code
        ];
        
        if ($details) {
            $response['details'] = $details;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    /**
     * Validate required fields
     */
    protected function validateRequired($data, $required_fields) {
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            $this->sendError(
                'Missing required fields: ' . implode(', ', $missing_fields),
                400,
                ['missing_fields' => $missing_fields]
            );
        }
        
        return true;
    }
    
    /**
     * Sanitize input data
     */
    protected function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email format
     */
    protected function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate phone number format
     */
    protected function validatePhone($phone) {
        // Basic phone validation - adjust regex as needed
        return preg_match('/^[\+]?[1-9][\d]{0,15}$/', preg_replace('/[^\d+]/', '', $phone));
    }
    
    /**
     * Validate blood type
     */
    protected function validateBloodType($blood_type) {
        $valid_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        return in_array($blood_type, $valid_types);
    }
    
    /**
     * Generate unique ID
     */
    protected function generateUniqueId($prefix = '', $length = 8) {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $id = $prefix;
        
        for ($i = 0; $i < $length; $i++) {
            $id .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $id;
    }
    
    /**
     * Hash password
     */
    protected function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify password
     */
    protected function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Log activity
     */
    protected function logActivity($user_id, $action, $entity_type, $entity_id, $old_values = null, $new_values = null) {
        try {
            $log_id = $this->generateUniqueId('LOG-', 10);
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs 
                (log_id, user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $log_id,
                $user_id,
                $action,
                $entity_type,
                $entity_id,
                $old_values ? json_encode($old_values) : null,
                $new_values ? json_encode($new_values) : null,
                $ip_address,
                $user_agent
            ]);
            
        } catch (Exception $e) {
            error_log("Activity Log Error: " . $e->getMessage());
        }
    }
    
    /**
     * Send notification
     */
    protected function sendNotification($recipient_user_id, $type, $title, $message, $related_id = null, $related_type = null, $priority = 'normal') {
        try {
            $notification_id = $this->generateUniqueId('NOTIF-', 10);
            
            $stmt = $this->db->prepare("
                INSERT INTO notifications 
                (notification_id, recipient_user_id, type, title, message, related_id, related_type, priority) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $notification_id,
                $recipient_user_id,
                $type,
                $title,
                $message,
                $related_id,
                $related_type,
                $priority
            ]);
            
            return $notification_id;
            
        } catch (Exception $e) {
            error_log("Notification Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user by ID
     */
    protected function getUserById($user_id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
            $stmt->execute([$user_id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get user by email
     */
    protected function getUserByEmail($email) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if method is allowed
     */
    protected function checkMethod($allowed_methods) {
        if (!in_array($this->request_method, $allowed_methods)) {
            $this->sendError(
                'Method not allowed. Allowed methods: ' . implode(', ', $allowed_methods),
                405
            );
        }
    }
    
    /**
     * Paginate results
     */
    protected function paginate($query, $params = [], $page = 1, $limit = 20) {
        try {
            $offset = ($page - 1) * $limit;
            
            // Get total count
            $count_query = "SELECT COUNT(*) as total FROM (" . $query . ") as count_table";
            $count_stmt = $this->db->prepare($count_query);
            $count_stmt->execute($params);
            $total = $count_stmt->fetch()['total'];
            
            // Get paginated results
            $paginated_query = $query . " LIMIT {$limit} OFFSET {$offset}";
            $stmt = $this->db->prepare($paginated_query);
            $stmt->execute($params);
            $results = $stmt->fetchAll();
            
            return [
                'data' => $results,
                'pagination' => [
                    'current_page' => (int)$page,
                    'per_page' => (int)$limit,
                    'total' => (int)$total,
                    'total_pages' => ceil($total / $limit),
                    'has_next' => $page < ceil($total / $limit),
                    'has_prev' => $page > 1
                ]
            ];
            
        } catch (Exception $e) {
            throw new Exception("Pagination error: " . $e->getMessage());
        }
    }
}