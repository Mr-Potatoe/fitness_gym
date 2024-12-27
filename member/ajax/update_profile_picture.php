<?php
require_once '../../config/config.php';

if (!isLoggedIn() || !hasRole('member')) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

try {
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== 0) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $_FILES['profile_picture']['name'];
    $filetype = pathinfo($filename, PATHINFO_EXTENSION);
    
    if (!in_array(strtolower($filetype), $allowed)) {
        throw new Exception('Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.');
    }

    $member_id = $_SESSION['user_id'];
    $upload_dir = '../../uploads/profile_pictures/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $new_filename = 'profile_' . $member_id . '_' . time() . '.' . $filetype;
    $upload_path = $upload_dir . $new_filename;
    
    if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
        throw new Exception('Failed to upload profile picture');
    }

    $profile_path = 'uploads/profile_pictures/' . $new_filename;
    
    // Update database
    $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
    $stmt->bind_param('si', $profile_path, $member_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update profile picture in database');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Profile picture updated successfully',
        'path' => $profile_path
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 