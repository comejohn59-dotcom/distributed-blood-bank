<?php
/**
 * Donation API - Submit Donation Offer
 */

require_once __DIR__ . '/../BaseAPI.php';

class SubmitDonationOfferAPI extends BaseAPI {
    
    public function handleRequest() {
        $this->checkMethod(['POST']);
        
        // Validate user authentication
        $user = $this->validateAuth();
        
        if ($user['user_type'] !== 'donor') {
            $this->sendError('Only donors can submit donation offers', 403);
        }
        
        // Validate required fields
        $this->validateRequired($this->request_data, [
            'hospital_id', 'preferred_date', 'preferred_time'
        ]);
        
        $data = $this->sanitizeInput($this->request_data);
        
        try {
            // Get donor profile
            $stmt = $this->db->prepare("SELECT * FROM donors WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $donor = $stmt->fetch();
            
            if (!$donor) {
                $this->sendError('Donor profile not found', 404);
            }
            
            // Check donor eligibility
            if (!$donor['is_eligible']) {
                $this->sendError('Donor is not currently eligible for donation', 400);
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
            
            if (!$hospital) {
                $this->sendError('Hospital not found or not verified', 404);
            }
            
            // Check if donor has pending offers
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM donation_offers 
                WHERE donor_id = ? AND status = 'pending'
            ");
            $stmt->execute([$donor['id']]);
            $pending_count = $stmt->fetch()['count'];
            
            if ($pending_count > 0) {
                $this->sendError('You already have a pending donation offer. Please wait for hospital response.', 400);
            }
            
            $this->db->beginTransaction();
            
            // Generate offer ID
            $offer_id = $this->generateUniqueId('DON-' . date('Y') . '-', 6);
            
            // Create donation offer
            $stmt = $this->db->prepare("
                INSERT INTO donation_offers (
                    offer_id, donor_id, assigned_hospital_id, blood_type, 
                    volume_ml, preferred_date, preferred_time, 
                    offered_by_user_id, status, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            
            $stmt->execute([
                $offer_id,
                $donor['id'],
                $data['hospital_id'],
                $donor['blood_type'],
                $data['volume_ml'] ?? 450,
                $data['preferred_date'],
                $data['preferred_time'],
                $user['id'],
                $data['notes'] ?? null
            ]);
            
            $donation_offer_id = $this->db->lastInsertId();
            
            // Send notification to hospital
            $notification_title = "New Donation Offer";
            $notification_message = "New {$donor['blood_type']} blood donation offer from donor {$donor['donor_id']} for {$data['preferred_date']} at {$data['preferred_time']}.";
            
            $this->sendNotification(
                $hospital['user_id'],
                $user['id'],
                $notification_title,
                $notification_message,
                $donation_offer_id,
                'donation_offer',
                'normal'
            );
            
            // Log activity
            $this->logActivity(
                $user['id'],
                'SUBMIT_DONATION_OFFER',
                'donation_offer',
                $donation_offer_id,
                null,
                [
                    'offer_id' => $offer_id,
                    'blood_type' => $donor['blood_type'],
                    'hospital_name' => $hospital['hospital_name'],
                    'preferred_date' => $data['preferred_date'],
                    'preferred_time' => $data['preferred_time']
                ]
            );
            
            $this->db->commit();
            
            $this->sendResponse([
                'offer_id' => $offer_id,
                'donation_offer_id' => $donation_offer_id,
                'status' => 'pending',
                'hospital_name' => $hospital['hospital_name'],
                'preferred_date' => $data['preferred_date'],
                'preferred_time' => $data['preferred_time'],
                'estimated_response_time' => '24 hours'
            ], 201, 'Donation offer submitted successfully');
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Submit Donation Offer Error: " . $e->getMessage());
            $this->sendError('Failed to submit donation offer', 500);
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

$api = new SubmitDonationOfferAPI();
$api->handleRequest();
?>