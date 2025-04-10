<?php
$servername = "localhost";
$username = "root"; // Change as per your database credentials
$password = "";
$database = "maktab_a_ekra_new"; // Change to your DB name

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
