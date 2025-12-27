<?php
/**
 * Admin API - Get Pending Hospital Registrations
 */

require_once __DIR__ . '/../BaseAPI.php';

class GetPendingHospitalsAPI extends BaseAPI {
    
    public function handleRequest() {
        $this->checkMethod(['GET']);
        
        // Validate admin authentication
        $user = $this->validateAdminAuth();
        
        try {
            // Get pending hospitals
            $stmt = $this->db->prepare("
                SELECT 
                    h.id,
                    h.hospital_id,
                    h.hospital_name,
                    h.hospital_type,
                    h.license_number,
                    h.bed_capacity,
                    h.has_blood_bank,
                    h.emergency_services,
                    h.is_verified,
                    h.created_at,
                    u.id as user_id,
                    u.email,
                    u.first_name,
                    u.last_name,
                    u.phone,
                    u.address,
                    u.city,
                    u.state
                FROM hospitals h
                JOIN users u ON h.user_id = u.id
                WHERE h.is_verified = FALSE
                ORDER BY h.created_at DESC
            ");
            $stmt->execute();
            $hospitals = $stmt->fetchAll();
            
            // Get verified hospitals count
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM hospitals WHERE is_verified = TRUE");
            $stmt->execute();
            $verified_count = $stmt->fetch()['count'];
            
            // Get total hospitals count
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM hospitals");
            $stmt->execute();
            $total_count = $stmt->fetch()['count'];
            
            $this->sendResponse([
                'pending_hospitals' => $hospitals,
                'stats' => [
                    'pending_count' => count($hospitals),
                    'verified_count' => $verified_count,
                    'total_count' => $total_count
                ]
            ], 200, 'Pending hospitals retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Get Pending Hospitals Error: " . $e->getMessage());
            $this->sendError('Failed to retrieve pending hospitals', 500);
        }
    }
    
    private function validateAdminAuth() {
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? '';
        
        if (empty($token)) {
            $this->sendError('Authorization token required', 401);
        }
        
        // Simple token validation
        $user_data = $this->validateSessionToken($token);
        
        if (!$user_data || $user_data['user_type'] !== 'admin') {
            $this->sendError('Admin access required', 403);
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
        
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? AND user_type = 'admin'");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }
}

$api = new GetPendingHospitalsAPI();
$api->handleRequest();
?>