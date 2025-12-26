<?php
/**
 * User Login API Endpoint
 * Handles user authentication for all user types
 */

require_once __DIR__ . '/../BaseAPI.php';

class LoginAPI extends BaseAPI {
    
    public function handleRequest() {
        $this->checkMethod(['POST']);
        
        // Validate required fields
        $this->validateRequired($this->request_data, ['email', 'password']);
        
        $email = $this->sanitizeInput($this->request_data['email']);
        $password = $this->request_data['password'];
        
        // Validate email format
        if (!$this->validateEmail($email)) {
            $this->sendError('Invalid email format', 400);
        }
        
        try {
            // Get user by email
            $user = $this->getUserByEmail($email);
            
            if (!$user) {
                $this->sendError('Invalid email or password', 401);
            }
            
            // Verify password
            if (!$this->verifyPassword($password, $user['password_hash'])) {
                $this->sendError('Invalid email or password', 401);
            }
            
            // Check if user is active
            if (!$user['is_active']) {
                $this->sendError('Account is deactivated. Please contact support.', 403);
            }
            
            // Update last login
            $this->updateLastLogin($user['id']);
            
            // Get user profile based on user type
            $profile = $this->getUserProfile($user);
            
            // Log successful login
            $this->logActivity($user['id'], 'LOGIN', 'user', $user['id']);
            
            // Prepare response data
            $response_data = [
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'user_type' => $user['user_type'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'phone' => $user['phone'],
                    'is_verified' => (bool)$user['is_verified'],
                    'last_login' => $user['last_login']
                ],
                'profile' => $profile,
                'session' => [
                    'token' => $this->generateSessionToken($user['id']),
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours'))
                ]
            ];
            
            $this->sendResponse($response_data, 200, 'Login successful');
            
        } catch (Exception $e) {
            error_log("Login Error: " . $e->getMessage());
            $this->sendError('Login failed. Please try again.', 500);
        }
    }
    
    /**
     * Update user's last login timestamp
     */
    private function updateLastLogin($user_id) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user_id]);
        } catch (Exception $e) {
            error_log("Update Last Login Error: " . $e->getMessage());
        }
    }
    
    /**
     * Get user profile based on user type
     */
    private function getUserProfile($user) {
        try {
            switch ($user['user_type']) {
                case 'patient':
                    return $this->getPatientProfile($user['id']);
                case 'donor':
                    return $this->getDonorProfile($user['id']);
                case 'hospital':
                    return $this->getHospitalProfile($user['id']);
                case 'admin':
                    return $this->getAdminProfile($user['id']);
                default:
                    return null;
            }
        } catch (Exception $e) {
            error_log("Get User Profile Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get patient profile
     */
    private function getPatientProfile($user_id) {
        $stmt = $this->db->prepare("
            SELECT patient_id, blood_type, date_of_birth, gender, weight, height,
                   medical_id, insurance_provider, emergency_contact_name, 
                   emergency_contact_phone, known_allergies, medical_conditions
            FROM patients WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }
    
    /**
     * Get donor profile
     */
    private function getDonorProfile($user_id) {
        $stmt = $this->db->prepare("
            SELECT donor_id, blood_type, date_of_birth, gender, weight, height,
                   is_eligible, last_donation_date, next_eligible_date, total_donations,
                   health_status, is_available, preferred_donation_time
            FROM donors WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }
    
    /**
     * Get hospital profile
     */
    private function getHospitalProfile($user_id) {
        $stmt = $this->db->prepare("
            SELECT hospital_id, hospital_name, hospital_type, license_number,
                   accreditation_level, bed_capacity, has_blood_bank, emergency_services,
                   trauma_center_level, is_24_7, latitude, longitude, website,
                   emergency_phone, blood_bank_phone, is_verified
            FROM hospitals WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }
    
    /**
     * Get admin profile
     */
    private function getAdminProfile($user_id) {
        // For admin, just return basic info
        return [
            'admin_level' => 'super_admin',
            'permissions' => ['all']
        ];
    }
    
    /**
     * Generate session token (simple implementation)
     */
    private function generateSessionToken($user_id) {
        return base64_encode($user_id . ':' . time() . ':' . bin2hex(random_bytes(16)));
    }
}

// Handle the request
$api = new LoginAPI();
$api->handleRequest();
?>