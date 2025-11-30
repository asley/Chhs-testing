<?php
$host = 'localhost';
$db = 'chhs';
$user = 'root';
$pass = 'root';
$charset = 'utf8mb4';
$socket = '/Applications/MAMP/tmp/mysql/mysql.sock';

$dsn = "mysql:unix_socket=$socket;dbname=$db;charset=$charset"; // ✅ This is the one that works

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    echo "Using user: $user and password: $pass\n";
    $pdo = new PDO(
        dsn: 'mysql:unix_socket=/Applications/MAMP/tmp/mysql/mysql.sock;dbname=chhs;charset=utf8mb4',
        username: 'root',
        password: 'root',
        options: $options
      );
    echo "✅ Database connection successful.\n";
} catch (\PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
}


