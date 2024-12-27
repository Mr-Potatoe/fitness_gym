<?php
require_once '../config/config.php';

// Test function to validate subscription creation
function testSubscriptionCreation($conn, $userId, $planId) {
    try {
        // Get plan details
        $stmt = $conn->prepare("SELECT * FROM plans WHERE id = ?");
        $stmt->bind_param("i", $planId);
        $stmt->execute();
        $plan = $stmt->get_result()->fetch_assoc();
        
        if (!$plan) {
            throw new Exception("Plan not found");
        }

        // Calculate subscription dates
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime("+{$plan['duration_months']} months"));

        // Begin transaction
        $conn->begin_transaction();

        // Create subscription
        $stmt = $conn->prepare("
            INSERT INTO subscriptions (
                user_id, plan_id, start_date, end_date,
                status, amount
            ) VALUES (?, ?, ?, ?, 'pending', ?)
        ");
        
        $stmt->bind_param(
            "iissd",
            $userId,
            $planId,
            $startDate,
            $endDate,
            $plan['price']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create subscription: " . $stmt->error);
        }

        $subscriptionId = $conn->insert_id;

        // Create test payment record
        $stmt = $conn->prepare("
            INSERT INTO payments (
                subscription_id, user_id, amount,
                payment_method, reference_number,
                status, payment_date
            ) VALUES (?, ?, ?, 'test', ?, 'pending', NOW())
        ");
        
        $referenceNumber = 'TEST-' . time();
        
        $stmt->bind_param(
            "iids",
            $subscriptionId,
            $userId,
            $plan['price'],
            $referenceNumber
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create payment record: " . $stmt->error);
        }

        // Commit transaction
        $conn->commit();
        
        return [
            'success' => true,
            'subscription_id' => $subscriptionId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'amount' => $plan['price']
        ];

    } catch (Exception $e) {
        $conn->rollback();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Run test
$testUserId = 1; // Replace with a valid user ID from your database
$testPlanId = 1; // Replace with a valid plan ID from your database

$result = testSubscriptionCreation($conn, $testUserId, $testPlanId);

// Output results
echo "\nTest Results:\n";
echo "----------------------------------------\n";
if ($result['success']) {
    echo "✓ Subscription created successfully\n";
    echo "  Subscription ID: {$result['subscription_id']}\n";
    echo "  Start Date: {$result['start_date']}\n";
    echo "  End Date: {$result['end_date']}\n";
    echo "  Amount: ₱" . number_format($result['amount'], 2) . "\n";
} else {
    echo "✗ Test failed: {$result['error']}\n";
}
echo "----------------------------------------\n";
