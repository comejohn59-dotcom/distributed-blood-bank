<?php
/**
 * Blood Request API - Submit Blood Request
 */

require_once __DIR__ . '/../BaseAPI.php';

class SubmitBloodRequestAPI extends BaseAPI {
    
    public function handleRequest() {
        $this->checkMethod(['POST']);
        
        // Validate user authentication
        $user = $this->validateAuth();
        
        if ($user['user_type'] !== 'patient') {
            $this->sendError('Only patients can submit blood requests', 403);
        }
        
        // Validate required fields
        $this->validateRequired($this->request_data, [
            'blood_type', 'units_requested', 'hospital_id', 'priority', 'medical_reason'
        ]);
        
        $data = $this->sanitizeInput($this->request_data);
        
        // Validate blood type
        if (!$this->validateBloodType($data['blood_type'])) {
            $this->sendError('Invalid blood type', 400);
        }
        
        // Validate priority
        if (!in_array($data['priority'], ['routine', 'urgent', 'emergency'])) {
            $this->sendError('Invalid priority level', 400);
        }
        
        // Validate units
        if ($data['units_requested'] < 1 || $data['units_requested'] > 10) {
            $this->sendError('Units requested must be between 1 and 10', 400);
        }
        
        try {
            // Get patient profile
            $stmt = $this->db->prepare("SELECT * FROM patients WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $patient = $stmt->fetch();
            
            if (!$patient) {
                $this->sendError('Patient profile not found', 404);
            }
            
            // Verify hospital exists and is verified
            $stmt = $this->db->prepare("
                SELECT h.*, u.first_name, u.last_name 
                FROM hospitals h 
                JOIN users u ON h.user_id = u.id 
                WHERE h.id = ? AND h.is_verified = TRUE
            ");
            $stmt->execute([$data['hospital_id']]);
            $hospital = $stmt->fetch();
            
            if (!hospital) {
                $this->sendError('Hospital not found or not verified', 404);
            }
            
            // Check if hospital has blood availability
            $stmt = $this->db->prepare("
                SELECT units_available 
                FROM blood_inventory 
                WHERE hospital_id = ? AND blood_type = ?
            ");
            $stmt->execute([$data['hospital_id'], $data['blood_type']]);
            $inventory = $stmt->fetch();
            
            if (!$inventory || $inventory['units_available'] < $data['units_requested']) {
                $this->sendError('Insufficient blood units available at selected hospital', 400);
            }
            
            $this->db->beginTransaction();
            
            // Generate request ID
            $request_id = $this->generateUniqueId('REQ-' . date('Y') . '-', 6);
            
            // Create blood request
            $stmt = $this->db->prepare("
                INSERT INTO blood_requests (
                    request_id, patient_id, assigned_hospital_id, blood_type, 
                    units_requested, priority, medical_reason, emergency_reason,
                    doctor_contact, emergency_contact_name, emergency_contact_phone,
                    requested_by_user_id, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            $stmt->execute([
                $request_id,
                $patient['id'],
                $data['hospital_id'],
                $data['blood_type'],
                $data['units_requested'],
                $data['priority'],
                $data['medical_reason'],
                $data['emergency_reason'] ?? null,
                $data['doctor_contact'] ?? null,
                $data['emergency_contact_name'] ?? null,
                $data['emergency_contact_phone'] ?? null,
                $user['id']
            ]);
            
            $blood_request_id = $this->db->lastInsertId();
            
            // Reserve blood units
            $stmt = $this->db->prepare("
                UPDATE blood_inventory 
                SET units_reserved = units_reserved + ?, units_available = units_available - ?
                WHERE hospital_id = ? AND blood_type = ?
            ");
            $stmt->execute([
                $data['units_requested'], 
                $data['units_requested'], 
                $data['hospital_id'], 
                $data['blood_type']
            ]);
            
            // Send notification to hospital
            $priority_text = ucfirst($data['priority']);
            $notification_title = "New Blood Request - {$priority_text}";
            $notification_message = "New {$data['blood_type']} blood request for {$data['units_requested']} units from patient {$patient['patient_id']}.";
            
            if ($data['priority'] === 'emergency') {
                $notification_message .= " EMERGENCY REQUEST - Immediate attention required.";
            }
            
            $this->sendNotification(
                $hospital['user_id'],
                $user['id'],
                $notification_title,
                $notification_message,
                $blood_request_id,
                'blood_request',
                $data['priority'] === 'emergency' ? 'critical' : ($data['priority'] === 'urgent' ? 'high' : 'normal')
            );
            
            // Log activity
            $this->logActivity(
                $user['id'],
                'SUBMIT_BLOOD_REQUEST',
                'blood_request',
                $blood_request_id,
                null,
                [
                    'request_id' => $request_id,
                    'blood_type' => $data['blood_type'],
                    'units_requested' => $data['units_requested'],
                    'priority' => $data['priority'],
                    'hospital_name' => $hospital['hospital_name']
                ]
            );
            
            $this->db->commit();
            
            $this->sendResponse([
                'request_id' => $request_id,
                'blood_request_id' => $blood_request_id,
                'status' => 'pending',
                'hospital_name' => $hospital['hospital_name'],
                'estimated_response_time' => $data['priority'] === 'emergency' ? '15 minutes' : ($data['priority'] === 'urgent' ? '2 hours' : '24 hours')
            ], 201, 'Blood request submitted successfully');
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Submit Blood Request Error: " . $e->getMessage());
            $this->sendError('Failed to submit blood request', 500);
        }
    }
    
    private function validateAuth() {
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? '';
        
        if (empty($token)) {
            $this->sendError('Authorization token required', 401);
        }
        
        $user_data = $this->validateSessionToken($token);
        
        if (!$user_data) {
            $this->sendError('Invalid or expired token', 401);
        }
        
        return $user_data;
    }
    
    private function validateSessionToken($token) {
        $decoded = base64_decode(str_replace('Bearer ', '', $token));
        $parts = explode(':', $decoded);
        
        if (count($parts) < 2) {
            return false;
        }
        
        $user_id = $parts[0];
        
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }
}

$api = new SubmitBloodRequestAPI();
$api->handleRequest();
?>