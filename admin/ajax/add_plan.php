<?php
require_once '../../config/config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    // Insert new plan
    $sql = "INSERT INTO plans (name, price, duration, description) 
            VALUES ('$name', $price, $duration, '$description')";
    
    if ($conn->query($sql)) {
        echo json_encode([
            'success' => true,
            'message' => 'Plan added successfully'
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