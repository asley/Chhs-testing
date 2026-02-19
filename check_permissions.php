<?php
// Check if permissions exist for View Grade Analytics
require_once __DIR__ . '/gibbon.php';

$pdo = $container->get('db');

try {
    echo "<h2>Checking View Grade Analytics Permissions</h2>";

    // Get the action ID
    $stmt = $pdo->select(
        "SELECT a.gibbonActionID, a.name, m.name as moduleName
         FROM gibbonAction a
         JOIN gibbonModule m ON a.gibbonModuleID = m.gibbonModuleID
         WHERE m.name = 'GradeAnalytics' AND a.name = 'View Grade Analytics'"
    );
    $action = $stmt->fetch();

    if (!$action) {
        echo "<p>❌ Action 'View Grade Analytics' not found!</p>";
        exit;
    }

    echo "<p>✅ Action found: {$action['name']} (ID: {$action['gibbonActionID']})</p>";

    // Check existing permissions
    $stmt = $pdo->select(
        "SELECT p.*, r.name as roleName
         FROM gibbonPermission p
         JOIN gibbonRole r ON p.gibbonRoleID = r.gibbonRoleID
         WHERE p.gibbonActionID = :actionID",
        ['actionID' => $action['gibbonActionID']]
    );
    $permissions = $stmt->fetchAll();

    echo "<h3>Current Permissions:</h3>";
    if (count($permissions) > 0) {
        echo "<ul>";
        foreach ($permissions as $perm) {
            echo "<li>✅ {$perm['roleName']} (Role ID: {$perm['gibbonRoleID']})</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>❌ NO PERMISSIONS FOUND - This is why the checkbox is greyed out!</p>";
        echo "<p><strong>Let's add them now...</strong></p>";

        // List all available roles
        $stmt = $pdo->select("SELECT gibbonRoleID, name FROM gibbonRole ORDER BY name");
        $roles = $stmt->fetchAll();

        echo "<h3>Available Roles:</h3>";
        echo "<ul>";
        foreach ($roles as $role) {
            echo "<li>{$role['name']} (ID: {$role['gibbonRoleID']})</li>";
        }
        echo "</ul>";

        // Now add permissions
        echo "<h3>Adding Permissions...</h3>";
        $rolesToAdd = ['Admin', 'Teacher', 'Principal', 'Vice Principal', 'Head of Department'];

        foreach ($rolesToAdd as $roleName) {
            $stmt = $pdo->select("SELECT gibbonRoleID FROM gibbonRole WHERE name = :name", ['name' => $roleName]);
            $role = $stmt->fetch();

            if ($role) {
                try {
                    $pdo->insert(
                        "INSERT INTO gibbonPermission (gibbonRoleID, gibbonActionID) VALUES (:roleID, :actionID)",
                        ['roleID' => $role['gibbonRoleID'], 'actionID' => $action['gibbonActionID']]
                    );
                    echo "<p>✅ Added permission for {$roleName}</p>";
                } catch (Exception $e) {
                    echo "<p>⚠️ {$roleName}: {$e->getMessage()}</p>";
                }
            } else {
                echo "<p>❌ Role not found: {$roleName}</p>";
            }
        }

        echo "<hr>";
        echo "<h3>✅ Done! Refresh the Manage Permissions page.</h3>";
    }

} catch (Exception $e) {
    echo "<p>Error: {$e->getMessage()}</p>";
}
