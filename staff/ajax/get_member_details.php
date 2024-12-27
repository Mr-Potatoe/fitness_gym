<?php
require_once '../../config/config.php';

// Check if user is logged in and is staff
if (!isLoggedIn() || !hasRole('staff')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate CSRF token
if (!validateCSRFToken($_GET['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

// Get and validate member ID
$member_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$member_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid member ID']);
    exit;
}

try {
    // Get member details using prepared statement
    $member_stmt = $conn->prepare("
        SELECT u.*, 
               s.id as subscription_id,
               s.status as subscription_status,
               s.start_date,
               s.end_date,
               p.name as plan_name,
               p.price as plan_price,
               p.duration as plan_duration,
               py.status as payment_status,
               py.payment_date,
               py.payment_method,
               py.payment_proof
        FROM users u
        LEFT JOIN subscriptions s ON u.id = s.user_id AND s.status != 'cancelled'
        LEFT JOIN plans p ON s.plan_id = p.id
        LEFT JOIN payments py ON s.id = py.subscription_id
        WHERE u.id = ? AND u.role = 'member'
        ORDER BY s.created_at DESC
        LIMIT 1
    ");
    
    $member_stmt->bind_param("i", $member_id);
    $member_stmt->execute();
    $result = $member_stmt->get_result();
    $member = $result->fetch_assoc();
    $member_stmt->close();

    if (!$member) {
        echo json_encode(['success' => false, 'message' => 'Member not found']);
        exit;
    }

    // Get subscription history
    $history_stmt = $conn->prepare("
        SELECT s.*, 
               p.name as plan_name,
               p.price as plan_price,
               py.status as payment_status,
               py.payment_date,
               py.payment_method
        FROM subscriptions s
        LEFT JOIN plans p ON s.plan_id = p.id
        LEFT JOIN payments py ON s.id = py.subscription_id
        WHERE s.user_id = ?
        ORDER BY s.created_at DESC
        LIMIT 10
    ");
    
    $history_stmt->bind_param("i", $member_id);
    $history_stmt->execute();
    $history_result = $history_stmt->get_result();
    $subscription_history = [];
    while ($row = $history_result->fetch_assoc()) {
        $subscription_history[] = $row;
    }
    $history_stmt->close();

    // Build HTML for member details
    $html = '
    <div class="row">
        <div class="col s12">
            <h5>Personal Information</h5>
            <div class="detail-section">
                <p><strong>Name:</strong> ' . htmlspecialchars($member['full_name']) . '</p>
                <p><strong>Email:</strong> ' . htmlspecialchars($member['email']) . '</p>
                <p><strong>Member Since:</strong> ' . date('F j, Y', strtotime($member['created_at'])) . '</p>
                <p><strong>Status:</strong> ' . 
                    '<span class="status-badge status-' . strtolower($member['status']) . '">' . 
                    ucfirst($member['status']) . '</span></p>
            </div>
        </div>
    </div>';

    // Current Subscription Details
    if ($member['subscription_id']) {
        $html .= '
        <div class="row">
            <div class="col s12">
                <h5>Current Subscription</h5>
                <div class="detail-section">
                    <p><strong>Plan:</strong> ' . htmlspecialchars($member['plan_name']) . '</p>
                    <p><strong>Status:</strong> 
                        <span class="status-badge status-' . strtolower($member['subscription_status']) . '">' . 
                        ucfirst($member['subscription_status']) . '</span></p>
                    <p><strong>Start Date:</strong> ' . date('F j, Y', strtotime($member['start_date'])) . '</p>
                    <p><strong>End Date:</strong> ' . date('F j, Y', strtotime($member['end_date'])) . '</p>
                    <p><strong>Payment Status:</strong> 
                        <span class="status-badge status-' . strtolower($member['payment_status']) . '">' . 
                        ucfirst($member['payment_status']) . '</span></p>';
        
        if ($member['payment_proof']) {
            $html .= '<p><strong>Payment Proof:</strong> 
                        <a href="#" onclick="viewPaymentProof(\'' . htmlspecialchars($member['payment_proof']) . '\')">
                            View Proof
                        </a></p>';
        }
        
        $html .= '</div></div></div>';
    }

    // Subscription History
    if (!empty($subscription_history)) {
        $html .= '
        <div class="row">
            <div class="col s12">
                <h5>Subscription History</h5>
                <table class="striped">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($subscription_history as $sub) {
            $html .= '<tr>
                        <td>' . htmlspecialchars($sub['plan_name']) . '</td>
                        <td>' . date('M j, Y', strtotime($sub['start_date'])) . '</td>
                        <td>' . date('M j, Y', strtotime($sub['end_date'])) . '</td>
                        <td><span class="status-badge status-' . strtolower($sub['status']) . '">' . 
                            ucfirst($sub['status']) . '</span></td>
                        <td><span class="status-badge status-' . strtolower($sub['payment_status']) . '">' . 
                            ucfirst($sub['payment_status']) . '</span></td>
                    </tr>';
        }
        
        $html .= '</tbody></table></div></div>';
    }

    // Log the view action
    logAdminAction('view_member_details', "Viewed member details for user ID: $member_id");

    echo json_encode([
        'success' => true,
        'html' => $html
    ]);

} catch (Exception $e) {
    // Log the error
    error_log("Error in get_member_details.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while retrieving member details'
    ]);
}