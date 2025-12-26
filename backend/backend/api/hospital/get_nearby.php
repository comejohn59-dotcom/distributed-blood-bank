<?php
/**
 * Hospital API - Get Nearby Hospitals
 */

require_once __DIR__ . '/../BaseAPI.php';

class GetNearbyHospitalsAPI extends BaseAPI {
    
    public function handleRequest() {
        $this->checkMethod(['GET']);
        
        try {
            // Get all verified and active hospitals
            $stmt = $this->db->prepare("
                SELECT 
                    h.id,
                    h.hospital_id,
                    h.hospital_name,
                    h.hospital_type,
                    h.emergency_services,
                    h.is_24_7,
                    h.latitude,
                    h.longitude,
                    h.website,
                    h.emergency_phone,
                    h.blood_bank_phone,
                    u.phone,
                    u.address,
                    u.city,
                    u.state
                FROM hospitals h
                JOIN users u ON h.user_id = u.id
                WHERE h.is_verified = TRUE 
                    AND h.is_active = TRUE
                ORDER BY h.hospital_name ASC
            ");
            $stmt->execute();
            $hospitals = $stmt->fetchAll();
            
            // Add distance calculation (simplified for demo)
            foreach ($hospitals as &$hospital) {
                $hospital['distance'] = $this->calculateDistance();
                $hospital['full_address'] = $hospital['address'] . ', ' . $hospital['city'] . ', ' . $hospital['state'];
            }
            
            $this->sendResponse([
                'hospitals' => $hospitals,
                'total_count' => count($hospitals)
            ], 200, 'Nearby hospitals retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Get Nearby Hospitals Error: " . $e->getMessage());
            $this->sendError('Failed to retrieve nearby hospitals', 500);
        }
    }
    
    private function calculateDistance() {
        // Simplified distance calculation - return random distance for demo
        return round(rand(1, 50) / 10, 1) . ' km';
    }
}

$api = new GetNearbyHospitalsAPI();
$api->handleRequest();
?>