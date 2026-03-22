<?php
// config/database.php

$host = 'localhost';
$dbname = 'pharmacy_db';
$username = 'root';
$password = '';
$charset = 'utf8mb4'; // our db support amharic and other languages
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=$charset", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
