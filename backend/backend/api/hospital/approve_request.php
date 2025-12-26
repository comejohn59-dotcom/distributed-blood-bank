<?php
/**
 * Hospital API - Approve/Reject Blood Request
 */

require_once __DIR__ . '/../BaseAPI.php';

class ApproveBloodRequestAPI extends BaseAPI {
    
    public function handleRequest() {
        $this->checkMethod(['POST']);
        
        // Validate hospital authentication
        $user = $this->validateHospitalAuth();
        
        // Validate required fields
        $this->validateRequired($this->request_data, ['request_id', 'action']);
        
        $request_id = (int)$this->request_data['request_id'];
        $action = $this->sanitizeInput($this->request_data['action']); // 'approve' or 'reject'
        $rejection_reason = $this->sanitizeInput($this->request_data['rejection_reason'] ?? '');
        
        if (!in_array($action, ['approve', 'reject'])) {
            $this->sendError('Invalid action. Must be approve or reject.', 400);
        }
        
        try {
            // Get hospital profile
            $stmt = $this->db->prepare("SELECT * FROM hospitals WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $hospital = $stmt->fetch();
            
            if (!$hospital) {
                $this->sendError('Hospital profile not found', 404);
            }
            
            // Get blood request details
            $stmt = $this->db->prepare("
                SELECT br.*, p.patient_id, u.first_name, u.last_name, u.email
                FROM blood_requests br
                JOIN patients p ON br.patient_id = p.id
                JOIN users u ON p.user_id = u.id
                WHERE br.id = ? AND br.assigned_hospital_id = ?
            ");
            $stmt->execute([$request_id, $hospital['id']]);
            $request = $stmt->fetch();
            
            if (!$request) {
                $this->sendError('Blood request not found or not assigned to your hospital', 404);
            }
            
            if ($request['status'] !== 'pending') {
                $this->sendError('Request has already been processed', 400);
            }
            
            $this->db->beginTransaction();
            
            if ($action === 'approve') {
                // Check blood availability
                $stmt = $this->db->prepare("
                    SELECT units_available 
                    FROM blood_inventory 
                    WHERE hospital_id = ? AND blood_type = ?
                ");
                $stmt->execute([$hospital['id'], $request['blood_type']]);
                $inventory = $stmt->fetch();
                
                if (!$inventory || $inventory['units_available'] < $request['units_requested']) {
                    $this->sendError('Insufficient blood units available', 400);
                }
                
                // Approve request
                $stmt = $this->db->prepare("
                    UPDATE blood_requests 
                    SET status = 'approved', approved_by_user_id = ?, approved_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$user['id'], $request_id]);
                
                // Update blood inventory (reserve units)
                $stmt = $this->db->prepare("
                    UPDATE blood_inventory 
                    SET units_reserved = units_reserved + ?, units_available = units_available - ?
                    WHERE hospital_id = ? AND blood_type = ?
                ");
                $stmt->execute([
                    $request['units_requested'], 
                    $request['units_requested'], 
                    $hospital['id'], 
                    $request['blood_type']
                ]);
                
                // Send approval notification to patient
                $this->sendNotification(
                    $request['requested_by_user_id'],
                    $user['id'],
                    'Blood Request Approved',
                    "Your blood request #{$request['request_id']} has been approved by {$hospital['hospital_name']}. Please contact the hospital to arrange collection.",
                    $request_id,
                    'request_approved',
                    'high'
                );
                
                $message = 'Blood request approved successfully';
                
            } else {
                // Reject request
                $stmt = $this->db->prepare("
                    UPDATE blood_requests 
                    SET status = 'rejected', rejected_by_user_id = ?, rejected_at = NOW(), 
                        rejection_reason = ?, rejection_notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$user['id'], $rejection_reason, $rejection_reason, $request_id]);
                
                // Release reserved units
                $stmt = $this->db->prepare("
                    UPDATE blood_inventory 
                    SET units_reserved = units_reserved - ?, units_available = units_available + ?
                    WHERE hospital_id = ? AND blood_type = ?
                ");
                $stmt->execute([
                    $request['units_requested'], 
                    $request['units_requested'], 
                    $hospital['id'], 
                    $request['blood_type']
                ]);
                
                // Send rejection notification to patient
                $rejection_message = "Your blood request #{$request['request_id']} has been rejected by {$hospital['hospital_name']}.";
                if ($rejection_reason) {
                    $rejection_message .= " Reason: " . $rejection_reason;
                }
                
                $this->sendNotification(
                    $request['requested_by_user_id'],
                    $user['id'],
                    'Blood Request Rejected',
                    $rejection_message,
                    $request_id,
                    'request_rejected',
                    'normal'
                );
                
                $message = 'Blood request rejected successfully';
            }
            
            // Log activity
            $this->logActivity(
                $user['id'],
                strtoupper($action) . '_BLOOD_REQUEST',
                'blood_request',
                $request_id,
                null,
                [
                    'request_id' => $request['request_id'],
                    'patient_name' => $request['first_name'] . ' ' . $request['last_name'],
                    'blood_type' => $request['blood_type'],
                    'units_requested' => $request['units_requested'],
                    'action' => $action,
                    'rejection_reason' => $rejection_reason
                ]
            );
            
            $this->db->commit();
            
            $this->sendResponse([
                'request_id' => $request['request_id'],
                'action' => $action,
                'status' => $action === 'approve' ? 'approved' : 'rejected',
                'patient_name' => $request['first_name'] . ' ' . $request['last_name']
            ], 200, $message);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Approve Blood Request Error: " . $e->getMessage());
            $this->sendError('Failed to process blood request', 500);
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

$api = new ApproveBloodRequestAPI();
$api->handleRequest();
?>