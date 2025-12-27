<?php
/**
 * Donor API - Get Dashboard Data
 */

require_once __DIR__ . '/../BaseAPI.php';

class GetDonorDashboardAPI extends BaseAPI {
    
    public function handleRequest() {
        $this->checkMethod(['GET']);
        
        // Validate donor authentication
        $user = $this->validateDonorAuth();
        
        try {
            // Get donor profile
            $stmt = $this->db->prepare("SELECT * FROM donors WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $donor = $stmt->fetch();
            
            if (!$donor) {
                $this->sendError('Donor profile not found', 404);
            }
            
            // Get donation offers
            $stmt = $this->db->prepare("
                SELECT 
                    do.*,
                    h.hospital_name,
                    h.emergency_phone,
                    u.phone as hospital_phone
                FROM donation_offers do
                LEFT JOIN hospitals h ON do.assigned_hospital_id = h.id
                LEFT JOIN users u ON h.user_id = u.id
                WHERE do.donor_id = ?
                ORDER BY do.created_at DESC
            ");
            $stmt->execute([$donor['id']]);
            $offers = $stmt->fetchAll();
            
            // Get donation history
            $stmt = $this->db->prepare("
                SELECT 
                    dh.*,
                    h.hospital_name
                FROM donation_history dh
                LEFT JOIN hospitals h ON dh.hospital_id = h.id
                WHERE dh.donor_id = ?
                ORDER BY dh.donation_date DESC
                LIMIT 10
            ");
            $stmt->execute([$donor['id']]);
            $history = $stmt->fetchAll();
            
            // Get emergency requests near donor (simplified)
            $stmt = $this->db->prepare("
                SELECT 
                    br.*,
                    h.hospital_name,
                    p.patient_id
                FROM blood_requests br
                JOIN hospitals h ON br.assigned_hospital_id = h.id
                JOIN patients p ON br.patient_id = p.id
                WHERE br.blood_type = ? 
                    AND br.priority = 'emergency'
                    AND br.status = 'pending'
                ORDER BY br.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$donor['blood_type']]);
            $emergency_requests = $stmt->fetchAll();
            
            // Calculate statistics
            $stats = [
                'total_donations' => $donor['total_donations'],
                'lives_saved' => $donor['total_donations'] * 3, // Estimate 3 lives per donation
                'pending_offers' => 0,
                'accepted_offers' => 0,
                'completed_donations' => count($history),
                'is_eligible' => (bool)$donor['is_eligible'],
                'next_eligible_date' => $donor['next_eligible_date'],
                'last_donation_date' => $donor['last_donation_date']
            ];
            
            foreach ($offers as $offer) {
                if ($offer['status'] === 'pending') {
                    $stats['pending_offers']++;
                } elseif ($offer['status'] === 'accepted') {
                    $stats['accepted_offers']++;
                }
            }
            
            $this->sendResponse([
                'donor_profile' => $donor,
                'donation_offers' => $offers,
                'donation_history' => $history,
                'emergency_requests' => $emergency_requests,
                'statistics' => $stats
            ], 200, 'Donor dashboard data retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Get Donor Dashboard Error: " . $e->getMessage());
            $this->sendError('Failed to retrieve donor dashboard data', 500);
        }
    }
    
    private function validateDonorAuth() {
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? '';
        
        if (empty($token)) {
            $this->sendError('Authorization token required', 401);
        }
        
        $user_data = $this->validateSessionToken($token);
        
        if (!$user_data || $user_data['user_type'] !== 'donor') {
            $this->sendError('Donor access required', 403);
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

$api = new GetDonorDashboardAPI();
$api->handleRequest();
?>