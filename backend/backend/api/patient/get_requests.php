<?php
/**
 * Patient API - Get Blood Requests
 */

require_once __DIR__ . '/../BaseAPI.php';

class GetPatientRequestsAPI extends BaseAPI {
    
    public function handleRequest() {
        $this->checkMethod(['GET']);
        
        // Validate patient authentication
        $user = $this->validatePatientAuth();
        
        try {
            // Get patient profile
            $stmt = $this->db->prepare("SELECT * FROM patients WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $patient = $stmt->fetch();
            
            if (!$patient) {
                $this->sendError('Patient profile not found', 404);
            }
            
            // Get blood requests for this patient
            $stmt = $this->db->prepare("
                SELECT 
                    br.*,
                    h.hospital_name,
                    h.emergency_phone,
                    h.blood_bank_phone,
                    u.phone as hospital_phone
                FROM blood_requests br
                LEFT JOIN hospitals h ON br.assigned_hospital_id = h.id
                LEFT JOIN users u ON h.user_id = u.id
                WHERE br.patient_id = ?
                ORDER BY br.created_at DESC
            ");
            $stmt->execute([$patient['id']]);
            $requests = $stmt->fetchAll();
            
            // Get statistics
            $stats = [
                'total_requests' => count($requests),
                'pending_requests' => 0,
                'approved_requests' => 0,
                'completed_requests' => 0,
                'rejected_requests' => 0
            ];
            
            foreach ($requests as $request) {
                switch ($request['status']) {
                    case 'pending':
                        $stats['pending_requests']++;
                        break;
                    case 'approved':
                        $stats['approved_requests']++;
                        break;
                    case 'completed':
                        $stats['completed_requests']++;
                        break;
                    case 'rejected':
                        $stats['rejected_requests']++;
                        break;
                }
            }
            
            $this->sendResponse([
                'requests' => $requests,
                'stats' => $stats,
                'patient_info' => [
                    'id' => $patient['id'],
                    'patient_id' => $patient['patient_id'],
                    'blood_type' => $patient['blood_type']
                ]
            ], 200, 'Patient requests retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Get Patient Requests Error: " . $e->getMessage());
            $this->sendError('Failed to retrieve patient requests', 500);
        }
    }
    
    private function validatePatientAuth() {
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? '';
        
        if (empty($token)) {
            $this->sendError('Authorization token required', 401);
        }
        
        $user_data = $this->validateSessionToken($token);
        
        if (!$user_data || $user_data['user_type'] !== 'patient') {
            $this->sendError('Patient access required', 403);
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

$api = new GetPatientRequestsAPI();
$api->handleRequest();
?>