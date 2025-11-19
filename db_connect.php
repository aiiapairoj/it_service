<?php
$servername = "localhost";
$username = "root";        // แก้ไข username ของคุณ
$password = "";            // แก้ไข password ของคุณ
$dbname = "it_service_db";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
