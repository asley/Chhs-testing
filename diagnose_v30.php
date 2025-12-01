<?php
/**
 * Gibbon v30 Diagnostic Script
 * Check system status after upgrade
 */

// Include config
require_once 'config.php';

echo "<h1>Gibbon v30 Diagnostic Report</h1>";
echo "<hr>";

// Check database connection
echo "<h2>1. Database Connection</h2>";
try {
    $pdo = new PDO("mysql:host=$databaseServer;dbname=$databaseName;charset=utf8mb4", $databaseUsername, $databasePassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ <strong>Database connection: OK</strong><br>";
} catch (PDOException $e) {
    echo "❌ <strong>Database connection failed:</strong> " . $e->getMessage() . "<br>";
    exit;
}

// Check version
echo "<h2>2. Gibbon Version</h2>";
try {
    $stmt = $pdo->query("SELECT value FROM gibbonSetting WHERE name = 'version'");
    $version = $stmt->fetchColumn();
    echo "Database version: <strong>$version</strong><br>";

    require_once 'version.php';
    echo "File version: <strong>$version</strong><br>";

    if ($version == '30.0.00') {
        echo "✅ <strong>Version upgrade: SUCCESS</strong><br>";
    } else {
        echo "⚠️ <strong>Version mismatch detected</strong><br>";
    }
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Check critical tables
echo "<h2>3. Critical Tables</h2>";
$criticalTables = [
    'gibbonAlert',
    'gibbonAlertLevel',
    'gibbonAlertType',
    'gibbonPerson',
    'gibbonSchoolYear',
    'gibbonSetting',
    'gibbonModule',
    'gibbonMigration'
];

foreach ($criticalTables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "✅ $table: <strong>$count records</strong><br>";
    } catch (PDOException $e) {
        echo "❌ $table: <strong>MISSING or ERROR</strong> - " . $e->getMessage() . "<br>";
    }
}

// Check migrations
echo "<h2>4. Migrations</h2>";
try {
    $stmt = $pdo->query("SELECT name, version, timestamp FROM gibbonMigration ORDER BY timestamp DESC LIMIT 10");
    $migrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Migration</th><th>Version</th><th>Timestamp</th></tr>";
    foreach ($migrations as $migration) {
        echo "<tr>";
        echo "<td>{$migration['name']}</td>";
        echo "<td>{$migration['version']}</td>";
        echo "<td>{$migration['timestamp']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Check system settings
echo "<h2>5. System Settings</h2>";
try {
    $settings = ['absoluteURL', 'absolutePath', 'timezone'];
    foreach ($settings as $setting) {
        $stmt = $pdo->prepare("SELECT value FROM gibbonSetting WHERE name = ?");
        $stmt->execute([$setting]);
        $value = $stmt->fetchColumn();
        echo "<strong>$setting:</strong> $value<br>";
    }
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Check current school year
echo "<h2>6. Current School Year</h2>";
try {
    $stmt = $pdo->query("SELECT name, status FROM gibbonSchoolYear WHERE status = 'Current'");
    $year = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($year) {
        echo "✅ Current year: <strong>{$year['name']}</strong><br>";
    } else {
        echo "⚠️ <strong>No current school year set</strong><br>";
    }
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Check modules
echo "<h2>7. Modules</h2>";
try {
    $stmt = $pdo->query("SELECT name, type, active FROM gibbonModule ORDER BY name");
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Module</th><th>Type</th><th>Active</th></tr>";
    foreach ($modules as $module) {
        $activeIcon = $module['active'] == 'Y' ? '✅' : '❌';
        echo "<tr>";
        echo "<td>{$module['name']}</td>";
        echo "<td>{$module['type']}</td>";
        echo "<td>$activeIcon {$module['active']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Check PHP info
echo "<h2>8. PHP Information</h2>";
echo "PHP Version: <strong>" . phpversion() . "</strong><br>";
echo "PDO MySQL: " . (extension_loaded('pdo_mysql') ? '✅ Loaded' : '❌ Not loaded') . "<br>";
echo "mbstring: " . (extension_loaded('mbstring') ? '✅ Loaded' : '❌ Not loaded') . "<br>";
echo "GD: " . (extension_loaded('gd') ? '✅ Loaded' : '❌ Not loaded') . "<br>";
echo "cURL: " . (extension_loaded('curl') ? '✅ Loaded' : '❌ Not loaded') . "<br>";
echo "ZIP: " . (extension_loaded('zip') ? '✅ Loaded' : '❌ Not loaded') . "<br>";
echo "intl: " . (extension_loaded('intl') ? '✅ Loaded' : '❌ Not loaded') . "<br>";

// Check file permissions
echo "<h2>9. File Permissions</h2>";
$checkPaths = [
    'uploads',
    'uploads/cache',
    'config.php'
];

foreach ($checkPaths as $path) {
    if (file_exists($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        echo "$path: <strong>$perms</strong> " . (is_writable($path) ? '✅' : '⚠️') . "<br>";
    } else {
        echo "$path: <strong>NOT FOUND</strong> ❌<br>";
    }
}

echo "<hr>";
echo "<h2>Diagnostic Complete</h2>";
echo "<p>If everything shows ✅, the upgrade was successful.</p>";
echo "<p>If you see errors, please check the specific items marked with ❌ or ⚠️</p>";
?>
