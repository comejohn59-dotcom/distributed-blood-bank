<?php
/**
 * Admin API - Approve Hospital Registration
 */

require_once __DIR__ . '/../BaseAPI.php';

class ApproveHospitalAPI extends BaseAPI {
    
    public function handleRequest() {
        $this->checkMethod(['POST']);
        
        // Validate admin authentication
        $user = $this->validateAdminAuth();
        
        // Validate required fields
        $this->validateRequired($this->request_data, ['hospital_id', 'action']);
        
        $hospital_id = (int)$this->request_data['hospital_id'];
        $action = $this->sanitizeInput($this->request_data['action']); // 'approve' or 'reject'
        $rejection_reason = $this->sanitizeInput($this->request_data['rejection_reason'] ?? '');
        
        if (!in_array($action, ['approve', 'reject'])) {
            $this->sendError('Invalid action. Must be approve or reject.', 400);
        }
        
        try {
            // Get hospital details
            $stmt = $this->db->prepare("
                SELECT h.*, u.email, u.first_name, u.last_name 
                FROM hospitals h 
                JOIN users u ON h.user_id = u.id 
                WHERE h.id = ?
            ");
            $stmt->execute([$hospital_id]);
            $hospital = $stmt->fetch();
            
            if (!$hospital) {
                $this->sendError('Hospital not found', 404);
            }
            
            $this->db->beginTransaction();
            
            if ($action === 'approve') {
                // Approve hospital
                $stmt = $this->db->prepare("
                    UPDATE hospitals 
                    SET is_verified = TRUE, verified_at = NOW(), verified_by = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$user['id'], $hospital_id]);
                
                // Send approval notification
                $this->sendNotification(
                    $hospital['user_id'],
                    'system',
                    'Hospital Registration Approved',
                    'Congratulations! Your hospital registration has been approved. You now have full access to all BloodConnect features.',
                    $hospital_id,
                    'hospital_approval',
                    'high'
                );
                
                $message = 'Hospital approved successfully';
                
            } else {
                // Reject hospital
                $stmt = $this->db->prepare("
                    UPDATE hospitals 
                    SET is_verified = FALSE, verified_at = NULL 
                    WHERE id = ?
                ");
                $stmt->execute([$hospital_id]);
                
                // Send rejection notification
                $rejection_message = 'Your hospital registration has been rejected.';
                if ($rejection_reason) {
                    $rejection_message .= ' Reason: ' . $rejection_reason;
                }
                
                $this->sendNotification(
                    $hospital['user_id'],
                    'system',
                    'Hospital Registration Rejected',
                    $rejection_message,
                    $hospital_id,
                    'hospital_rejection',
                    'high'
                );
                
                $message = 'Hospital rejected successfully';
            }
            
            // Log activity
            $this->logActivity(
                $user['id'], 
                strtoupper($action) . '_HOSPITAL', 
                'hospital', 
                $hospital_id,
                null,
                [
                    'hospital_name' => $hospital['hospital_name'],
                    'action' => $action,
                    'rejection_reason' => $rejection_reason
                ]
            );
            
            $this->db->commit();
            
            $this->sendResponse([
                'hospital_id' => $hospital_id,
                'action' => $action,
                'hospital_name' => $hospital['hospital_name']
            ], 200, $message);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Approve Hospital Error: " . $e->getMessage());
            $this->sendError('Failed to process hospital approval', 500);
        }
    }
    
    private function validateAdminAuth() {
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? '';
        
        if (empty($token)) {
            $this->sendError('Authorization token required', 401);
        }
        
        // Simple token validation (in production, use proper JWT)
        $user_data = $this->validateSessionToken($token);
        
        if (!$user_data || $user_data['user_type'] !== 'admin') {
            $this->sendError('Admin access required', 403);
        }
        
        return $user_data;
    }
    
    private function validateSessionToken($token) {
        // Simple token validation - decode base64 token
        $decoded = base64_decode(str_replace('Bearer ', '', $token));
        $parts = explode(':', $decoded);
        
        if (count($parts) < 2) {
            return false;
        }
        
        $user_id = $parts[0];
        
        // Get user from database
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? AND user_type = 'admin'");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }
}

$api = new ApproveHospitalAPI();
$api->handleRequest();
?>