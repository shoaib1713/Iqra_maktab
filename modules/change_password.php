<?php
session_start();
require '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "unauthorized";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $current_password = trim($_POST["current_password"]);
    $new_password = trim($_POST["new_password"]);
    $confirm_password = trim($_POST["confirm_password"]);

    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        echo "All fields are required.";
        exit;
    }

    if ($new_password !== $confirm_password) {
        echo "New passwords do not match.";
        exit;
    }

    if (strlen($new_password) < 8) {
        echo "New password must be at least 8 characters long.";
        exit;
    }

    // Verify current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($hashed_password);
    $stmt->fetch();

    if ($stmt->num_rows == 1) {
        // Verify current password
        if (password_verify($current_password, $hashed_password)) {
            // Update password
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $new_hashed_password, $user_id);
            $update_stmt->execute();

            if ($update_stmt->affected_rows > 0) {
                // Log the password change
                $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, 'password_change', 'Password changed successfully')");
                $log_stmt->bind_param("i", $user_id);
                $log_stmt->execute();
                $log_stmt->close();
                
                echo "success";
            } else {
                echo "Failed to update password. Please try again.";
            }
            $update_stmt->close();
        } else {
            echo "Current password is incorrect.";
        }
    } else {
        echo "User not found.";
    }

    $stmt->close();
    $conn->close();
}
?> 