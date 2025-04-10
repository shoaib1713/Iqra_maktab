<?php
session_start();
require_once 'config.php';
require 'config/db.php';

if (isset($_GET['id'])) {
    $userId = $_GET['id'];
    $conn->query("UPDATE users SET is_deleted = 0 WHERE id = $userId");
    header("Location: users.php");
    exit();
}
?>
