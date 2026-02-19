<?php
// Script to check and fix GradeAnalytics permissions
require_once __DIR__ . '/gibbon.php';

$pdo = $container->get('db');

echo "<h2>GradeAnalytics Permission Fix</h2>";

try {
    // Check current module version in database
    $result = $pdo->selectOne("SELECT version FROM gibbonModule WHERE name = 'GradeAnalytics'");
    echo "<p><strong>Current DB Version:</strong> " . ($result['version'] ?? 'Not found') . "</p>";

    // Check if the action exists
    $action = $pdo->selectOne("
        SELECT a.gibbonActionID, a.name, m.name as moduleName
        FROM gibbonAction a
        INNER JOIN gibbonModule m ON a.gibbonModuleID = m.gibbonModuleID
        WHERE m.name = 'GradeAnalytics' AND a.name = 'View Grade Analytics'
    ");

    if (!$action) {
        echo "<p style='color: red;'><strong>Error:</strong> 'View Grade Analytics' action not found in database!</p>";
        echo "<p>The module may need to be reinstalled.</p>";
        exit;
    }

    echo "<p><strong>Action Found:</strong> {$action['name']} (ID: {$action['gibbonActionID']})</p>";

    // Check current permissions
    $existingPermissions = $pdo->select("
        SELECT r.name as roleName, r.gibbonRoleID
        FROM gibbonPermission p
        INNER JOIN gibbonRole r ON p.gibbonRoleID = r.gibbonRoleID
        WHERE p.gibbonActionID = :actionID
    ", ['actionID' => $action['gibbonActionID']]);

    echo "<h3>Current Permissions:</h3>";
    if (empty($existingPermissions->toArray())) {
        echo "<p style='color: orange;'><strong>No permissions found!</strong> This is why the option is greyed out.</p>";
    } else {
        echo "<ul>";
        foreach ($existingPermissions as $perm) {
            echo "<li>{$perm['roleName']}</li>";
        }
        echo "</ul>";
    }

    // Apply the permissions fix
    echo "<h3>Applying Permissions Fix...</h3>";

    $sql = "
    INSERT INTO gibbonPermission (gibbonRoleID, gibbonActionID)
    SELECT r.gibbonRoleID, a.gibbonActionID
    FROM gibbonRole r
    CROSS JOIN gibbonAction a
    INNER JOIN gibbonModule m ON a.gibbonModuleID = m.gibbonModuleID
    WHERE m.name = 'GradeAnalytics'
      AND a.name = 'View Grade Analytics'
      AND r.name IN ('Admin', 'Teacher', 'Principal', 'Vice Principal', 'Head of Department')
      AND NOT EXISTS (
        SELECT 1 FROM gibbonPermission p
        WHERE p.gibbonRoleID = r.gibbonRoleID
        AND p.gibbonActionID = a.gibbonActionID
      )";

    $affectedRows = $pdo->affectingStatement($sql);

    echo "<p style='color: green;'><strong>Success!</strong> Added permissions for {$affectedRows} role(s).</p>";

    // Show updated permissions
    $updatedPermissions = $pdo->select("
        SELECT r.name as roleName, r.gibbonRoleID
        FROM gibbonPermission p
        INNER JOIN gibbonRole r ON p.gibbonRoleID = r.gibbonRoleID
        WHERE p.gibbonActionID = :actionID
    ", ['actionID' => $action['gibbonActionID']]);

    echo "<h3>Updated Permissions:</h3>";
    echo "<ul>";
    foreach ($updatedPermissions as $perm) {
        echo "<li>{$perm['roleName']}</li>";
    }
    echo "</ul>";

    echo "<hr>";
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Go to <strong>Admin → User Admin → Manage Permissions</strong></li>";
    echo "<li>Filter by Module: GradeAnalytics</li>";
    echo "<li>Select your role in the 'Role' dropdown</li>";
    echo "<li>You should now see 'View Grade Analytics' checkboxes enabled</li>";
    echo "<li>Check the appropriate box for your role and click Submit</li>";
    echo "</ol>";
    echo "<p><a href='index.php?q=/modules/User Admin/permission_manage.php'>Go to Manage Permissions</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
