<?php
// db.php
$host = "appdev-finalproj-mysql-appdev-finalproj-mysql.a.aivencloud.com";
$port = 23630;
$db = "defaultdb";
$user = "avnadmin";
$pass = "AVNS_FsvYTS_s7d0c0o2KzXw";
$ca = __DIR__ . "/ca.pem";

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $options = [
        PDO::MYSQL_ATTR_SSL_CA => $ca,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>