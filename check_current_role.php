<?php
/*
Quick debug script to check current user's role
*/

include './gibbon.php';

// Check if session exists
if (!$session->exists('gibbonPersonID')) {
    echo "Not logged in!";
    exit;
}

echo "<h2>Current User Role Information</h2>";
echo "<table border='1' style='border-collapse: collapse; padding: 10px;'>";
echo "<tr><th style='padding: 10px; text-align: left;'>Property</th><th style='padding: 10px; text-align: left;'>Value</th></tr>";

// Get role information
echo "<tr><td style='padding: 10px;'><strong>Person ID</strong></td><td style='padding: 10px;'>" . $session->get('gibbonPersonID') . "</td></tr>";
echo "<tr><td style='padding: 10px;'><strong>Username</strong></td><td style='padding: 10px;'>" . $session->get('username') . "</td></tr>";
echo "<tr><td style='padding: 10px;'><strong>Preferred Name</strong></td><td style='padding: 10px;'>" . $session->get('preferredName') . "</td></tr>";
echo "<tr><td style='padding: 10px;'><strong>Surname</strong></td><td style='padding: 10px;'>" . $session->get('surname') . "</td></tr>";
echo "<tr><td style='padding: 10px;'><strong>Role ID</strong></td><td style='padding: 10px;'>" . $session->get('gibbonRoleIDCurrent') . "</td></tr>";
echo "<tr><td style='padding: 10px;'><strong>Role Name</strong></td><td style='padding: 10px;'><strong style='color: red;'>" . $session->get('gibbonRoleIDCurrentName') . "</strong></td></tr>";
echo "<tr><td style='padding: 10px;'><strong>Role Category</strong></td><td style='padding: 10px;'><strong style='color: red;'>" . $session->get('gibbonRoleIDCurrentCategory') . "</strong></td></tr>";

echo "</table>";

echo "<h3 style='margin-top: 20px;'>Unrestricted Roles (should match one of these):</h3>";
echo "<ul>";
$unrestrictedRoles = [
    'Administrator',
    'Principal',
    'Principal - Deputy',
    'Vice Principal',
    'Assistant Principal',
    'Coordinator',
    'Head of Department',
    'Director',
    'HOD'
];
foreach ($unrestrictedRoles as $role) {
    echo "<li>$role</li>";
}
echo "</ul>";

// Check if current role matches
$roleName = $session->get('gibbonRoleIDCurrentName');
$matched = false;
foreach ($unrestrictedRoles as $role) {
    if (stripos($roleName, $role) !== false) {
        echo "<p style='color: green; font-weight: bold;'>✓ MATCH FOUND: Your role '$roleName' matches '$role'</p>";
        $matched = true;
        break;
    }
}

if (!$matched) {
    echo "<p style='color: red; font-weight: bold;'>✗ NO MATCH: Your role '$roleName' does not match any unrestricted role</p>";
    echo "<p style='color: blue;'>Your role will be restricted to own classes only.</p>";
}
