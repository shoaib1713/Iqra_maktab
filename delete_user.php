<?php
session_start();
require_once 'config.php';
require 'config/db.php';

if (isset($_POST['id'])) {
    $userId = $_POST['id'];
    $conn->query("UPDATE users SET is_deleted = 1 WHERE id = $userId");
    header("Location: users.php");
    exit();
}
?>
