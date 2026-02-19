<?php
// Verify View Grade Analytics permissions in database
require_once __DIR__ . '/gibbon.php';

$pdo = $container->get('db');

echo "<h2>View Grade Analytics Permission Verification</h2>";

try {
    // Get the action
    $actionQuery = "SELECT a.*, m.name as moduleName
                    FROM gibbonAction a
                    INNER JOIN gibbonModule m ON a.gibbonModuleID = m.gibbonModuleID
                    WHERE m.name = 'GradeAnalytics' AND a.name = 'View Grade Analytics'";

    $result = $pdo->executeQuery(['query' => $actionQuery]);
    $action = $result->fetch();

    if (!$action) {
        echo "<p style='color: red;'>❌ Action 'View Grade Analytics' NOT FOUND in database!</p>";
        echo "<p>The action may not have been created during module installation.</p>";
        exit;
    }

    echo "<h3>Action Details:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    foreach ($action as $key => $value) {
        echo "<tr><td>{$key}</td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
    }
    echo "</table>";

    // Get permissions for this action
    echo "<h3>Current Permissions in Database:</h3>";
    $permQuery = "SELECT p.*, r.name as roleName, r.category as roleCategory
                  FROM gibbonPermission p
                  INNER JOIN gibbonRole r ON p.gibbonRoleID = r.gibbonRoleID
                  WHERE p.gibbonActionID = :actionID
                  ORDER BY r.name";

    $result = $pdo->executeQuery(['query' => $permQuery, 'params' => ['actionID' => $action['gibbonActionID']]]);
    $permissions = $result->fetchAll();

    if (empty($permissions)) {
        echo "<p style='color: red;'>❌ NO PERMISSIONS found in database!</p>";
        echo "<p>The CHANGEDB.php script may not have run correctly.</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Role Name</th><th>Role Category</th><th>Role ID</th></tr>";
        foreach ($permissions as $perm) {
            echo "<tr>";
            echo "<td>{$perm['roleName']}</td>";
            echo "<td>{$perm['roleCategory']}</td>";
            echo "<td>{$perm['gibbonRoleID']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Check all roles
    echo "<h3>All Roles in System:</h3>";
    $roleQuery = "SELECT * FROM gibbonRole ORDER BY name";
    $result = $pdo->executeQuery(['query' => $roleQuery]);
    $roles = $result->fetchAll();

    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Role Name</th><th>Category</th><th>Role ID</th><th>Has Permission?</th></tr>";
    foreach ($roles as $role) {
        $hasPermission = false;
        foreach ($permissions as $perm) {
            if ($perm['gibbonRoleID'] == $role['gibbonRoleID']) {
                $hasPermission = true;
                break;
            }
        }
        echo "<tr>";
        echo "<td>{$role['name']}</td>";
        echo "<td>{$role['category']}</td>";
        echo "<td>{$role['gibbonRoleID']}</td>";
        echo "<td>" . ($hasPermission ? '✅ Yes' : '❌ No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
