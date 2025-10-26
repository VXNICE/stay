<?php
$host = config('config.database.mysql.host');
$port = config('config.database.mysql.port');
$dbname = config('config.database.mysql.dbname');
$user = config('config.database.mysql.username');
$pass = config('config.database.mysql.password');
$pdo = null;

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Database connection failed']);
    exit;
}
