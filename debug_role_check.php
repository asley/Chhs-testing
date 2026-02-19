<?php
/*
Debug role checking for Grade Analytics
*/

include './gibbon.php';

// Check if session exists
if (!$session->exists('gibbonPersonID')) {
    echo "Not logged in!";
    exit;
}

echo "<h2>Role Check Debug</h2>";
echo "<pre>";

// Get role information
$roleCategory = $session->get('gibbonRoleIDCurrentCategory');
$roleName = $session->get('gibbonRoleIDCurrentName');

echo "Current Role Name: '" . $roleName . "' (length: " . strlen($roleName) . ")\n";
echo "Current Role Category: '" . $roleCategory . "'\n";
echo "\n";

// Show byte-by-byte breakdown to detect hidden characters
echo "Role Name Bytes: ";
for ($i = 0; $i < strlen($roleName); $i++) {
    echo ord($roleName[$i]) . " ";
}
echo "\n\n";

// Define unrestricted roles (same as in GradeAnalyticsGateway)
$unrestrictedRoles = [
    'Administrator',
    'School Admin',
    'Principal',
    'Vice-Principal',
    'HOD',
    'Grade Supervisor',
    'Super User',
    'Coordinator',
    'Director'
];

echo "Checking against unrestricted roles:\n";
echo "=====================================\n";

$matched = false;
foreach ($unrestrictedRoles as $role) {
    $position = stripos($roleName, $role);
    $check = ($position !== false) ? "✓ MATCH at position $position" : "✗ no match";
    echo sprintf("%-20s : %s\n", $role, $check);

    if ($position !== false) {
        $matched = true;
    }
}

echo "\n";
echo "Role Category Check:\n";
echo "====================\n";
echo "roleCategory === 'Staff': " . ($roleCategory === 'Staff' ? 'TRUE' : 'FALSE') . "\n";
echo "\n";

echo "Final Decision:\n";
echo "===============\n";

// Replicate the exact logic from shouldRestrictToOwnClasses()
$shouldRestrict = null;

foreach ($unrestrictedRoles as $role) {
    if (stripos($roleName, $role) !== false) {
        $shouldRestrict = false;
        echo "Decision: Role '$roleName' matches unrestricted role '$role'\n";
        echo "Result: UNRESTRICTED (should see all teachers/classes)\n";
        break;
    }
}

if ($shouldRestrict === null && $roleCategory === 'Staff') {
    $shouldRestrict = true;
    echo "Decision: Role category is 'Staff' but not in unrestricted list\n";
    echo "Result: RESTRICTED (should see only own classes)\n";
}

if ($shouldRestrict === null) {
    $shouldRestrict = false;
    echo "Decision: No match and not Staff category\n";
    echo "Result: UNRESTRICTED (default)\n";
}

echo "\n";
echo "shouldRestrictToOwnClasses() would return: " . ($shouldRestrict ? 'TRUE (restricted)' : 'FALSE (unrestricted)') . "\n";

if ($shouldRestrict) {
    echo "\n⚠️ PROBLEM: You should be UNRESTRICTED but the method returns RESTRICTED\n";
} else {
    echo "\n✓ CORRECT: You should be UNRESTRICTED and the method returns UNRESTRICTED\n";
}

echo "</pre>";
