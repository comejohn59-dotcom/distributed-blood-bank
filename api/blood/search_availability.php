<?php
/**
 * Blood Search API - Search Blood Availability
 */

require_once __DIR__ . '/../BaseAPI.php';

class SearchBloodAvailabilityAPI extends BaseAPI {
    
    public function handleRequest() {
        $this->checkMethod(['GET']);
        
        // Get search parameters
        $blood_type = $this->sanitizeInput($_GET['blood_type'] ?? '');
        $location = $this->sanitizeInput($_GET['location'] ?? '');
        $radius = (int)($_GET['radius'] ?? 50);
        
        if (empty($blood_type)) {
            $this->sendError('Blood type is required', 400);
        }
        
        if (!$this->validateBloodType($blood_type)) {
            $this->sendError('Invalid blood type', 400);
        }
        
        try {
            // Search for hospitals with available blood
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
                    u.state,
                    bi.units_available,
                    bi.last_updated,
                    CASE 
                        WHEN bi.units_available >= bi.critical_stock_threshold * 3 THEN 'good'
                        WHEN bi.units_available >= bi.critical_stock_threshold THEN 'low'
                        ELSE 'critical'
                    END as stock_status
                FROM hospitals h
                JOIN users u ON h.user_id = u.id
                JOIN blood_inventory bi ON h.id = bi.hospital_id
                WHERE h.is_verified = TRUE 
                    AND h.is_active = TRUE
                    AND bi.blood_type = ?
                    AND bi.units_available > 0
                ORDER BY bi.units_available DESC, h.hospital_name ASC
            ");
            $stmt->execute([$blood_type]);
            $hospitals = $stmt->fetchAll();
            
            // Calculate distance if location provided (simplified - in production use proper geolocation)
            foreach ($hospitals as &$hospital) {
                $hospital['distance'] = $this->calculateDistance($location, $hospital);
                $hospital['contact_info'] = [
                    'phone' => $hospital['phone'],
                    'emergency_phone' => $hospital['emergency_phone'],
                    'blood_bank_phone' => $hospital['blood_bank_phone']
                ];
                $hospital['services'] = [
                    'emergency_services' => (bool)$hospital['emergency_services'],
                    'is_24_7' => (bool)$hospital['is_24_7']
                ];
            }
            
            // Get blood type compatibility info
            $compatible_types = $this->getCompatibleBloodTypes($blood_type);
            
            $this->sendResponse([
                'search_criteria' => [
                    'blood_type' => $blood_type,
                    'location' => $location,
                    'radius' => $radius
                ],
                'hospitals' => $hospitals,
                'compatible_types' => $compatible_types,
                'total_found' => count($hospitals)
            ], 200, 'Blood availability search completed');
            
        } catch (Exception $e) {
            error_log("Search Blood Availability Error: " . $e->getMessage());
            $this->sendError('Failed to search blood availability', 500);
        }
    }
    
    private function calculateDistance($location, $hospital) {
        // Simplified distance calculation - return random distance for demo
        return round(rand(1, 50) / 10, 1) . ' km';
    }
    
    private function getCompatibleBloodTypes($blood_type) {
        $compatibility = [
            'A+' => ['A+', 'A-', 'O+', 'O-'],
            'A-' => ['A-', 'O-'],
            'B+' => ['B+', 'B-', 'O+', 'O-'],
            'B-' => ['B-', 'O-'],
            'AB+' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
            'AB-' => ['A-', 'B-', 'AB-', 'O-'],
            'O+' => ['O+', 'O-'],
            'O-' => ['O-']
        ];
        
        return $compatibility[$blood_type] ?? [];
    }
}

$api = new SearchBloodAvailabilityAPI();
$api->handleRequest();
?>
              