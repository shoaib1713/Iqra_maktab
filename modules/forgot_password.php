<?php
session_start();
require '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);

    if (empty($email)) {
        echo "Email is required.";
        exit;
    }

    // Check if email exists
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ? AND is_deleted = 0");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($user_id, $name);
    $stmt->fetch();

    if ($stmt->num_rows == 1) {
        // Generate a unique token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Store token in database (create table if not exists)
        $checkTable = $conn->query("SHOW TABLES LIKE 'password_reset'");
        if ($checkTable->num_rows == 0) {
            // Create the table if it doesn't exist
            $createTable = "CREATE TABLE password_reset (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(64) NOT NULL,
                expires DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )";
            $conn->query($createTable);
        }

        // Delete any existing tokens for this user
        $deleteStmt = $conn->prepare("DELETE FROM password_reset WHERE user_id = ?");
        $deleteStmt->bind_param("i", $user_id);
        $deleteStmt->execute();
        $deleteStmt->close();

        // Insert new token
        $tokenStmt = $conn->prepare("INSERT INTO password_reset (user_id, token, expires) VALUES (?, ?, ?)");
        $tokenStmt->bind_param("iss", $user_id, $token, $expires);
        $tokenStmt->execute();
        $tokenStmt->close();

        // In a real application, you would send an email with the reset link
        // For demonstration, we'll just return the token (in practice, this would be a link in an email)
        $resetUrl = "../reset_password.php?token=" . $token;
        
        // Log action for audit trail
        $logStmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, 'password_reset_request', ?)");
        $details = "Password reset requested for user ID: " . $user_id;
        $logStmt->bind_param("is", $user_id, $details);
        $logStmt->execute();
        $logStmt->close();

        echo "success - Reset instructions sent to your email";
        // In a real application, you would send an email with the reset link
        // Example: mail($email, "Password Reset", "Click here to reset your password: $resetUrl");
    } else {
        echo "Email not found in our records.";
    }

    $stmt->close();
    $conn->close();
}
?> 