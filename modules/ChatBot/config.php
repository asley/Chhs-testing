<?php
// Database Settings - Load from environment variables
// SECURITY: Never hardcode credentials. Use .env file (added to .gitignore)
$databaseServer = $_ENV['DB_SERVER'] ?? getenv('DB_SERVER') ?? 'localhost';
$databasePort = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? '3306';
$databaseName = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? 'chhs';
$databaseUsername = $_ENV['DB_USER'] ?? getenv('DB_USER');
$databasePassword = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');
$databaseSocket = $_ENV['DB_SOCKET'] ?? getenv('DB_SOCKET') ?? '/Applications/MAMP/tmp/mysql/mysql.sock';

// Validate required credentials
if (!$databasePassword || !$databaseUsername) {
    throw new Exception('Database credentials not configured. Set DB_USER and DB_PASSWORD environment variables.');
}

// Set database connection string
$databaseConnectionString = "mysql:unix_socket=$databaseSocket;dbname=$databaseName";

// Error Reporting - DISABLED IN PRODUCTION
// SECURITY: Never display errors in production. Log to file instead.
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php-chatbot-errors.log');
error_reporting(E_ALL & ~E_NOTICE);

// Set timezone
date_default_timezone_set('America/Los_Angeles');

// Debug mode - DISABLED IN PRODUCTION
$debugMode = false;

// REMOVED: Database connection logging exposed credentials in logs

// Set include path
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__); 