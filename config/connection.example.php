<?php
// Kopieer dit bestand naar config/connection.php en vul je eigen databasegegevens in.
// config/connection.php staat in .gitignore en komt dus nooit op GitHub.

$dbHost = 'localhost';
$dbName = 'mybuddy';
$dbUser = '';
$dbPass = '';

try {
    $pdo = new PDO("mysql:host=$dbHost;port=3306;dbname=$dbName", $dbUser, $dbPass);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
