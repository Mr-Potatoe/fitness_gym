<?php
require_once 'config/config.php';

try {
    // First, add the address column if it doesn't exist
    $sql0 = "ALTER TABLE users 
             ADD COLUMN IF NOT EXISTS address VARCHAR(255) DEFAULT NULL";
    
    if ($conn->query($sql0)) {
        echo "Address column added successfully<br>";
    }

    // Then add the verification columns
    $sql1 = "ALTER TABLE users 
            ADD COLUMN IF NOT EXISTS verified TINYINT(1) DEFAULT 0,
            ADD COLUMN IF NOT EXISTS verified_at DATETIME DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS verified_by INT DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS staff_notes TEXT DEFAULT NULL,
            ADD FOREIGN KEY (verified_by) REFERENCES users(id)";
    
    if ($conn->query($sql1)) {
        echo "Users table updated successfully<br>";
        
        // Auto-verify existing walk-in cash payment members
        $sql2 = "UPDATE users u
                JOIN subscriptions s ON u.id = s.user_id
                JOIN payments p ON s.id = p.subscription_id
                SET u.verified = 1,
                    u.verified_at = u.created_at,
                    u.verified_by = (SELECT id FROM users WHERE role = 'admin' LIMIT 1)
                WHERE u.role = 'member'
                AND p.payment_method = 'cash'
                AND u.username IS NULL";
        
        if ($conn->query($sql2)) {
            echo "Existing walk-in cash payment members auto-verified";
        } else {
            echo "Error updating existing members: " . $conn->error;
        }
    } else {
        echo "Error updating users table: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} 