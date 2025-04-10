<?php
include '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    if (empty($name) || empty($email) || empty($password)) {
        echo "All fields are required.";
        exit;
    }

    // Check if email already exists
    $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $checkEmail->store_result();

    if ($checkEmail->num_rows > 0) {
        echo "Email already registered. Please login.";
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $insertUser = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    $insertUser->bind_param("sss", $name, $email, $hashedPassword);

    if ($insertUser->execute()) {
        echo "success";
    } else {
        echo "Error: Could not register user.";
    }
}
?>
