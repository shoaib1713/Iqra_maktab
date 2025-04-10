<?php
session_start();
require '../config/db.php'; // Database connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if (empty($email) || empty($password)) {
        echo "All fields are required.";
        exit;
    }

    // Check if email exists
    $stmt = $conn->prepare("SELECT id, name, password, role,is_deleted FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id, $name, $hashed_password, $role,$is_deleted);
    $stmt->fetch();

    if ($stmt->num_rows == 1) {
        // Verify password
        if (password_verify($password, $hashed_password)) {
            if($is_deleted === 1 ){
                    $stmt->close();
                    $conn->close();
                echo "User is deleted";
                exit;
            }
            $_SESSION['user_id'] = $id;
            $_SESSION['user_name'] = $name;
            $_SESSION['role'] = $role;
            

            if ($role === 'admin') {
                echo "admin";
            } else {
                echo "teacher";
            }
        } else {
            echo "Invalid credentials";
        }
    } else {
        echo "Invalid credentials";
    }

    $stmt->close();
    $conn->close();
}
?>
