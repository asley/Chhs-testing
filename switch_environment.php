<?php
/**
 * Environment Switcher
 * Safely switch between local and production URLs
 *
 * USAGE: php switch_environment.php [local|production]
 */

// Database configuration
$host = 'localhost';
$port = '8889'; // MAMP MySQL port
$username = 'root';
$password = 'root';
$database = 'chhs-testing';
$socket = '/Applications/MAMP/tmp/mysql/mysql.sock';

// Environment configurations
$environments = [
    'local' => [
        'absoluteURL' => 'http://localhost:8888/chhs-testing',
        'absolutePath' => '/Applications/MAMP/htdocs/chhs-testing'
    ],
    'production' => [
        'absoluteURL' => 'https://www.tasanz.com/chhs-tc',
        'absolutePath' => '/home/admin/domains/tasanz.com/public_html/chhs-tc'
    ]
];

// Get environment from command line or default to local
$environment = isset($argv[1]) ? strtolower($argv[1]) : 'local';

if (!isset($environments[$environment])) {
    echo "ERROR: Invalid environment. Use 'local' or 'production'\n";
    echo "Usage: php switch_environment.php [local|production]\n";
    exit(1);
}

$config = $environments[$environment];

echo "======================================\n";
echo "Gibbon Environment Switcher\n";
echo "======================================\n\n";
echo "Switching to: " . strtoupper($environment) . " environment\n\n";

try {
    // Connect to database
    $pdo = new PDO("mysql:unix_socket=$socket;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✓ Connected to database\n\n";

    // Update absoluteURL
    $updateURL = $pdo->prepare("UPDATE gibbonSetting SET value = :value WHERE name = 'absoluteURL'");
    $updateURL->execute(['value' => $config['absoluteURL']]);
    echo "✓ Updated absoluteURL to: " . $config['absoluteURL'] . "\n";

    // Update absolutePath
    $updatePath = $pdo->prepare("UPDATE gibbonSetting SET value = :value WHERE name = 'absolutePath'");
    $updatePath->execute(['value' => $config['absolutePath']]);
    echo "✓ Updated absolutePath to: " . $config['absolutePath'] . "\n";

    echo "\n======================================\n";
    echo "✓ Environment switch complete!\n";
    echo "======================================\n\n";

    if ($environment === 'local') {
        echo "Access Gibbon at: http://localhost:8888/chhs-testing\n";
    } else {
        echo "Access Gibbon at: https://www.tasanz.com/chhs-tc\n";
    }

} catch (PDOException $e) {
    echo "✗ Database Error: " . $e->getMessage() . "\n";
    exit(1);
}
