<?php
require_once '../../config/config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Handle GET request to fetch plan details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $plan_id = $conn->real_escape_string($_GET['id']);
    $sql = "SELECT * FROM plans WHERE id = $plan_id AND deleted_at IS NULL";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $plan = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'data' => $plan
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Plan not found'
        ]);
    }
    exit;
}

// Handle POST request to update plan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_id = $conn->real_escape_string($_POST['plan_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $price = floatval($_POST['price']);
    $duration = intval($_POST['duration']);
    $description = $conn->real_escape_string($_POST['description']);
    
    // Validate input
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Plan name is required";
    }
    
    if ($price <= 0) {
        $errors[] = "Price must be greater than 0";
    }
    
    if ($duration <= 0) {
        $errors[] = "Duration must be greater than 0";
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(", ", $errors)]);
        exit;
    }
    
    // Update plan
    $sql = "UPDATE plans 
            SET name = '$name', 
                price = $price, 
                duration = $duration, 
                description = '$description' 
            WHERE id = $plan_id";
    
    if ($conn->query($sql)) {
        echo json_encode([
            'success' => true,
            'message' => 'Plan updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Database error'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
} 