<?php
/**
 * User Registration API Endpoint
 * Handles user registration for patients, donors, and hospitals
 */

require_once __DIR__ . '/../BaseAPI.php';

class RegisterAPI extends BaseAPI {
    
    public function handleRequest() {
        $this->checkMethod(['POST']);
        
        // Validate required fields
        $this->validateRequired($this->request_data, [
            'email', 'password', 'user_type', 'first_name', 'last_name'
        ]);
        
        $data = $this->sanitizeInput($this->request_data);
        
        // Validate email format
        if (!$this->validateEmail($data['email'])) {
            $this->sendError('Invalid email format', 400);
        }
        
        // Validate user type
        $valid_user_types = ['patient', 'donor', 'hospital'];
        if (!in_array($data['user_type'], $valid_user_types)) {
            $this->sendError('Invalid user type. Must be: ' . implode(', ', $valid_user_types), 400);
        }
        
        // Validate password strength
        if (strlen($data['password']) < 8) {
            $this->sendError('Password must be at least 8 characters long', 400);
        }
        
        try {
            // Check if email already exists
            if ($this->getUserByEmail($data['email'])) {
                $this->sendError('Email already registered', 409);
            }
            
            // Start transaction
            $this->db->beginTransaction();
            
            // Create user account
            $user_id = $this->createUser($data);
            
            // Create profile based on user type
            $profile_id = $this->createUserProfile($user_id, $data);
            
            // Send welcome notification
            $this->sendWelcomeNotification($user_id, $data['user_type']);
            
            // Commit transaction
            $this->db->commit();
            
            // Log registration
            $this->logActivity($user_id, 'REGISTER', 'user', $user_id, null, [
                'user_type' => $data['user_type'],
                'email' => $data['email']
            ]);
            
            // Prepare response
            $response_data = [
                'user_id' => $user_id,
                'profile_id' => $profile_id,
                'user_type' => $data['user_type'],
                'email' => $data['email'],
                'message' => 'Registration successful! Please check your email for verification.'
            ];
            
            $this->sendResponse($response_data, 201, 'User registered successfully');
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Registration Error: " . $e->getMessage());
            $this->sendError('Registration failed. Please try again.', 500);
        }
    }
    
    /**
     * Create user account
     */
    private function createUser($data) {
        $stmt = $this->db->prepare("
            INSERT INTO users (email, password_hash, user_type, first_name, last_name, phone, address, city, state, zip_code, country)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['email'],
            $this->hashPassword($data['password']),
            $data['user_type'],
            $data['first_name'],
            $data['last_name'],
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $data['city'] ?? null,
            $data['state'] ?? null,
            $data['zip_code'] ?? null,
            $data['country'] ?? 'USA'
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Create user profile based on type
     */
    private function createUserProfile($user_id, $data) {
        switch ($data['user_type']) {
            case 'patient':
                return $this->createPatientProfile($user_id, $data);
            case 'donor':
                return $this->createDonorProfile($user_id, $data);
            case 'hospital':
                return $this->createHospitalProfile($user_id, $data);
            default:
                throw new Exception('Invalid user type for profile creation');
        }
    }
    
    /**
     * Create patient profile
     */
    private function createPatientProfile($user_id, $data) {
        // Validate required patient fields
        $this->validateRequired($data, ['blood_type', 'date_of_birth', 'gender']);
        
        if (!$this->validateBloodType($data['blood_type'])) {
            throw new Exception('Invalid blood type');
        }
        
        $patient_id = $this->generateUniqueId('PT-' . date('Y') . '-', 6);
        
        $stmt = $this->db->prepare("
            INSERT INTO patients (user_id, patient_id, blood_type, date_of_birth, gender, weight, height,
                                medical_id, insurance_provider, emergency_contact_name, emergency_contact_phone,
                                known_allergies, medical_conditions)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $patient_id,
            $data['blood_type'],
            $data['date_of_birth'],
            $data['gender'],
            $data['weight'] ?? null,
            $data['height'] ?? null,
            $data['medical_id'] ?? null,
            $data['insurance_provider'] ?? null,
            $data['emergency_contact_name'] ?? null,
            $data['emergency_contact_phone'] ?? null,
            $data['known_allergies'] ?? null,
            $data['medical_conditions'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Create donor profile
     */
    private function createDonorProfile($user_id, $data) {
        // Validate required donor fields
        $this->validateRequired($data, ['blood_type', 'date_of_birth', 'gender', 'weight']);
        
        if (!$this->validateBloodType($data['blood_type'])) {
            throw new Exception('Invalid blood type');
        }
        
        // Check minimum weight for donation (usually 50kg/110lbs)
        if ($data['weight'] < 50) {
            throw new Exception('Minimum weight requirement not met for blood donation');
        }
        
        $donor_id = $this->generateUniqueId('DN-' . date('Y') . '-', 6);
        
        $stmt = $this->db->prepare("
            INSERT INTO donors (user_id, donor_id, blood_type, date_of_birth, gender, weight, height,
                              health_status, preferred_donation_time, emergency_contact_name, emergency_contact_phone,
                              known_allergies, medical_conditions)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $donor_id,
            $data['blood_type'],
            $data['date_of_birth'],
            $data['gender'],
            $data['weight'],
            $data['height'] ?? null,
            $data['health_status'] ?? 'good',
            $data['preferred_donation_time'] ?? 'any',
            $data['emergency_contact_name'] ?? null,
            $data['emergency_contact_phone'] ?? null,
            $data['known_allergies'] ?? null,
            $data['medical_conditions'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Create hospital profile
     */
    private function createHospitalProfile($user_id, $data) {
        // Validate required hospital fields
        $this->validateRequired($data, ['hospital_name', 'hospital_type', 'license_number']);
        
        $hospital_id = $this->generateUniqueId('HP-' . date('Y') . '-', 6);
        
        $stmt = $this->db->prepare("
            INSERT INTO hospitals (user_id, hospital_id, hospital_name, hospital_type, license_number,
                                 accreditation_level, bed_capacity, has_blood_bank, blood_bank_license,
                                 emergency_services, trauma_center_level, operating_hours_start, operating_hours_end,
                                 is_24_7, latitude, longitude, website, emergency_phone, blood_bank_phone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $hospital_id,
            $data['hospital_name'],
            $data['hospital_type'],
            $data['license_number'],
            $data['accreditation_level'] ?? 'level_1',
            $data['bed_capacity'] ?? null,
            isset($data['has_blood_bank']) ? (bool)$data['has_blood_bank'] : true,
            $data['blood_bank_license'] ?? null,
            isset($data['emergency_services']) ? (bool)$data['emergency_services'] : true,
            $data['trauma_center_level'] ?? 'none',
            $data['operating_hours_start'] ?? '00:00:00',
            $data['operating_hours_end'] ?? '23:59:59',
            isset($data['is_24_7']) ? (bool)$data['is_24_7'] : true,
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
            $data['website'] ?? null,
            $data['emergency_phone'] ?? null,
            $data['blood_bank_phone'] ?? null
        ]);
        
        $hospital_profile_id = $this->db->lastInsertId();
        
        // Initialize blood inventory for hospital
        $this->initializeBloodInventory($hospital_profile_id);
        
        return $hospital_profile_id;
    }
    
    /**
     * Initialize blood inventory for new hospital
     */
    private function initializeBloodInventory($hospital_id) {
        $blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        
        $stmt = $this->db->prepare("
            INSERT INTO blood_inventory (hospital_id, blood_type, units_available, low_stock_threshold, critical_stock_threshold)
            VALUES (?, ?, 0, 10, 5)
        ");
        
        foreach ($blood_types as $blood_type) {
            $stmt->execute([$hospital_id, $blood_type]);
        }
    }
    
    /**
     * Send welcome notification
     */
    private function sendWelcomeNotification($user_id, $user_type) {
        $titles = [
            'patient' => 'Welcome to BloodConnect!',
            'donor' => 'Thank you for becoming a blood donor!',
            'hospital' => 'Welcome to the BloodConnect network!'
        ];
        
        $messages = [
            'patient' => 'Your account has been created successfully. You can now search for blood availability and submit requests.',
            'donor' => 'Your donor account is ready! You can now offer blood donations to help save lives.',
            'hospital' => 'Your hospital account has been created. Please wait for admin verification to access all features.'
        ];
        
        $this->sendNotification(
            $user_id,
            'system',
            $titles[$user_type],
            $messages[$user_type],
            null,
            null,
            'normal'
        );
    }
}

// Handle the request
$api = new RegisterAPI();
$api->handleRequest();
?>