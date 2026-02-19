<?php
// Add remaining permissions with correct role names
require_once __DIR__ . '/gibbon.php';

$pdo = $container->get('db');

try {
    echo "<h2>Adding Remaining Permissions</h2>";

    // Get the action ID
    $stmt = $pdo->select(
        "SELECT a.gibbonActionID FROM gibbonAction a
         JOIN gibbonModule m ON a.gibbonModuleID = m.gibbonModuleID
         WHERE m.name = 'GradeAnalytics' AND a.name = 'View Grade Analytics'"
    );
    $action = $stmt->fetch();
    $actionID = $action['gibbonActionID'];

    // Add permissions for remaining roles with correct names
    $rolesToAdd = ['Administrator', 'Vice-Principal', 'HOD', 'School Admin', 'Grade Supervisor'];

    foreach ($rolesToAdd as $roleName) {
        $stmt = $pdo->select("SELECT gibbonRoleID FROM gibbonRole WHERE name = :name", ['name' => $roleName]);
        $role = $stmt->fetch();

        if ($role) {
            // Check if already exists
            $check = $pdo->select(
                "SELECT * FROM gibbonPermission WHERE gibbonRoleID = :roleID AND gibbonActionID = :actionID",
                ['roleID' => $role['gibbonRoleID'], 'actionID' => $actionID]
            );

            if ($check->rowCount() == 0) {
                $pdo->insert(
                    "INSERT INTO gibbonPermission (gibbonRoleID, gibbonActionID) VALUES (:roleID, :actionID)",
                    ['roleID' => $role['gibbonRoleID'], 'actionID' => $actionID]
                );
                echo "<p>✅ Added permission for {$roleName}</p>";
            } else {
                echo "<p>✓ Permission already exists for {$roleName}</p>";
            }
        } else {
            echo "<p>⚠️ Role not found: {$roleName}</p>";
        }
    }

    echo "<hr>";
    echo "<h3>✅ Done! Now refresh the Manage Permissions page.</h3>";
    echo "<p><a href='index.php?q=/modules/User Admin/permission_manage.php'>Go to Manage Permissions</a></p>";

} catch (Exception $e) {
    echo "<p>❌ Error: {$e->getMessage()}</p>";
}
