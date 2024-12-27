<?php
require_once '../../config/config.php';

// Prevent any output before headers
ob_start();

header('Content-Type: application/json');

// Clear any previous output
ob_clean();

try {
    if (!isLoggedIn() || !hasRole('admin')) {
        throw new Exception('Unauthorized access');
    }

    if (!isset($_GET['id'])) {
        throw new Exception('Member ID is required');
    }

    $member_id = (int)$_GET['id'];
    
    $query = "SELECT u.*, 
              s.status as subscription_status,
              p.name as plan_name,
              s.start_date,
              s.end_date,
              s.created_at as subscription_date,
              COALESCE(py.status, 'pending') as payment_status
              FROM users u 
              LEFT JOIN subscriptions s ON u.id = s.user_id AND s.status IN ('active', 'pending')
              LEFT JOIN plans p ON s.plan_id = p.id
              LEFT JOIN payments py ON s.id = py.subscription_id
              WHERE u.id = ? AND u.role = 'member'";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result || $result->num_rows === 0) {
        throw new Exception('Member not found');
    }

    $member = $result->fetch_assoc();
    
    $html = "<div class='detail-section'>";
    $html .= "<h5>Personal Information</h5>";
    $html .= "<div class='detail-row'><span class='detail-label'>Name:</span><span class='detail-value'>" . htmlspecialchars($member['full_name']) . "</span></div>";
    $html .= "<div class='detail-row'><span class='detail-label'>Email:</span><span class='detail-value'>" . htmlspecialchars($member['email']) . "</span></div>";
    $html .= "<div class='detail-row'><span class='detail-label'>Member Since:</span><span class='detail-value'>" . date('F j, Y', strtotime($member['created_at'])) . "</span></div>";
    $html .= "</div>";

    if ($member['verified']) {
        $verifier_query = "SELECT full_name FROM users WHERE id = {$member['verified_by']}";
        $verifier = $conn->query($verifier_query)->fetch_assoc();
        
        $html .= "<div class='detail-section'>";
        $html .= "<h5>Verification Details</h5>";
        $html .= "<div class='detail-row'><span class='detail-label'>Status:</span><span class='detail-value status-active'><i class='material-icons tiny'>verified</i> Verified</span></div>";
        $html .= "<div class='detail-row'><span class='detail-label'>Verified By:</span><span class='detail-value'>" . htmlspecialchars($verifier['full_name']) . "</span></div>";
        $html .= "<div class='detail-row'><span class='detail-label'>Verified At:</span><span class='detail-value'>" . date('F j, Y g:i A', strtotime($member['verified_at'])) . "</span></div>";
        $html .= "</div>";
    }

    // Send the response
    ob_clean(); // Clear output buffer
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);

} catch (Exception $e) {
    ob_clean(); // Clear output buffer
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit;