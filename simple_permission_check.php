<?php
// Simple permission check
require_once __DIR__ . '/gibbon.php';

$connection2 = $container->get('db')->getConnection();

echo "<h2>View Grade Analytics Permission Check</h2>";

// Get the action ID
$sql = "SELECT a.gibbonActionID, a.name
        FROM gibbonAction a
        INNER JOIN gibbonModule m ON a.gibbonModuleID = m.gibbonModuleID
        WHERE m.name = 'GradeAnalytics' AND a.name = 'View Grade Analytics'";

$result = $connection2->query($sql);
$action = $result->fetch(PDO::FETCH_ASSOC);

if (!$action) {
    echo "<p style='color: red;'>❌ Action 'View Grade Analytics' NOT FOUND!</p>";
    exit;
}

echo "<p>✅ Action found: {$action['name']} (ID: {$action['gibbonActionID']})</p>";

// Check permissions
$sql = "SELECT p.*, r.name as roleName
        FROM gibbonPermission p
        INNER JOIN gibbonRole r ON p.gibbonRoleID = r.gibbonRoleID
        WHERE p.gibbonActionID = :actionID";

$stmt = $connection2->prepare($sql);
$stmt->execute(['actionID' => $action['gibbonActionID']]);
$permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Permissions in Database:</h3>";
if (empty($permissions)) {
    echo "<p style='color: red; font-size: 18px;'>❌ NO PERMISSIONS FOUND!</p>";
    echo "<p>This is why the checkboxes are greyed out. The CHANGEDB.php script didn't add the permissions.</p>";

    // Manually add permissions
    echo "<h3>Adding Permissions Now...</h3>";

    $rolesToAdd = ['Admin', 'Teacher', 'Principal', 'Vice Principal', 'Head of Department'];

    foreach ($rolesToAdd as $roleName) {
        $sql = "SELECT gibbonRoleID FROM gibbonRole WHERE name = ?";
        $stmt = $connection2->prepare($sql);
        $stmt->execute([$roleName]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($role) {
            try {
                $sql = "INSERT INTO gibbonPermission (gibbonRoleID, gibbonActionID) VALUES (?, ?)";
                $stmt = $connection2->prepare($sql);
                $stmt->execute([$role['gibbonRoleID'], $action['gibbonActionID']]);
                echo "<p style='color: green;'>✅ Added permission for {$roleName}</p>";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    echo "<p style='color: orange;'>⚠️ Permission already exists for {$roleName}</p>";
                } else {
                    echo "<p style='color: red;'>❌ Error for {$roleName}: {$e->getMessage()}</p>";
                }
            }
        } else {
            echo "<p>⚠️ Role '{$roleName}' not found in system</p>";
        }
    }

    echo "<hr><h3 style='color: green;'>✅ Done!</h3>";
    echo "<p>Now go back to <strong>Admin → User Admin → Manage Permissions</strong> and the checkboxes should be enabled.</p>";

} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Role Name</th><th>Role ID</th></tr>";
    foreach ($permissions as $perm) {
        echo "<tr><td>{$perm['roleName']}</td><td>{$perm['gibbonRoleID']}</td></tr>";
    }
    echo "</table>";
    echo "<p style='color: green;'>Permissions exist! The checkboxes should work.</p>";
}
