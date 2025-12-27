<?php
/**
 * Hospital API - Get Blood Requests
 */

require_once __DIR__ . '/../BaseAPI.php';

class GetHospitalRequestsAPI extends BaseAPI {
    
    public function handleRequest() {
        $this->checkMethod(['GET']);
        
        // Validate hospital authentication
        $user = $this->validateHospitalAuth();
        
        try {
            // Get hospital profile
            $stmt = $this->db->prepare("SELECT * FROM hospitals WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $hospital = $stmt->fetch();
            
            if (!$hospital) {
                $this->sendError('Hospital profile not found', 404);
            }
            
            // Get blood requests assigned to this hospital
            $stmt = $this->db->prepare("
                SELECT 
                    br.*,
                    p.patient_id,
                    p.blood_type as patient_blood_type,
                    u.first_name,
                    u.last_name,
                    u.phone,
                    u.email
                FROM blood_requests br
                JOIN patients p ON br.patient_id = p.id
                JOIN users u ON p.user_id = u.id
                WHERE br.assigned_hospital_id = ?
                ORDER BY 
                    CASE br.priority 
                        WHEN 'emergency' THEN 1 
                        WHEN 'urgent' THEN 2 
                        WHEN 'routine' THEN 3 
                    END,
                    br.created_at DESC
            ");
            $stmt->execute([$hospital['id']]);
            $requests = $stmt->fetchAll();
            
            // Get donation offers for this hospital
            $stmt = $this->db->prepare("
                SELECT 
                    do.*,
                    d.donor_id,
                    d.blood_type,
                    d.total_donations,
                    u.first_name,
                    u.last_name,
                    u.phone,
                    u.email
                FROM donation_offers do
                JOIN donors d ON do.donor_id = d.id
                JOIN users u ON d.user_id = u.id
                WHERE do.assigned_hospital_id = ?
                ORDER BY do.created_at DESC
            ");
            $stmt->execute([$hospital['id']]);
            $donations = $stmt->fetchAll();
            
            // Get statistics
            $stats = [
                'pending_requests' => 0,
                'pending_donations' => 0,
                'emergency_requests' => 0,
                'total_requests' => count($requests),
                'total_donations' => count($donations)
            ];
            
            foreach ($requests as $request) {
                if ($request['status'] === 'pending') {
                    $stats['pending_requests']++;
                }
                if ($request['priority'] === 'emergency') {
                    $stats['emergency_requests']++;
                }
            }
            
            foreach ($donations as $donation) {
                if ($donation['status'] === 'pending') {
                    $stats['pending_donations']++;
                }
            }
            
            $this->sendResponse([
                'blood_requests' => $requests,
                'donation_offers' => $donations,
                'statistics' => $stats,
                'hospital_info' => [
                    'id' => $hospital['id'],
                    'name' => $hospital['hospital_name'],
                    'type' => $hospital['hospital_type']
                ]
            ], 200, 'Hospital requests retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Get Hospital Requests Error: " . $e->getMessage());
            $this->sendError('Failed to retrieve hospital requests', 500);
        }
    }
    
    private function validateHospitalAuth() {
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? '';
        
        if (empty($token)) {
            $this->sendError('Authorization token required', 401);
        }
        
        $user_data = $this->validateSessionToken($token);
        
        if (!$user_data || $user_data['user_type'] !== 'hospital') {
            $this->sendError('Hospital access required', 403);
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

$api = new GetHospitalRequestsAPI();
$api->handleRequest();
?>