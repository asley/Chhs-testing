<?php
// Simple test script to verify the filter logic
$testGrades = [
    '74%' => 74,
    '75%' => 75,
    '76%' => 76,
    '80%' => 80,
    '' => null,
    'A' => 90,
    'B+' => 75,
    'B' => 70,
];

$threshold = 75;
$operator = '>';

echo "<h2>Testing Grade Filter: {$operator} {$threshold}</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Grade Value</th><th>Numeric</th><th>Passes Filter?</th></tr>";

foreach ($testGrades as $gradeStr => $numeric) {
    if ($numeric === null) {
        echo "<tr><td>{$gradeStr}</td><td>NULL</td><td style='background: #fcc'>EXCLUDED (empty)</td></tr>";
        continue;
    }

    $passes = false;
    switch ($operator) {
        case '>':
            $passes = $numeric > $threshold;
            break;
        case '>=':
            $passes = $numeric >= $threshold;
            break;
        case '<':
            $passes = $numeric < $threshold;
            break;
        case '<=':
            $passes = $numeric <= $threshold;
            break;
        case '=':
            $passes = $numeric == $threshold;
            break;
    }

    $color = $passes ? '#cfc' : '#fcc';
    $result = $passes ? 'INCLUDED' : 'EXCLUDED';

    echo "<tr><td>{$gradeStr}</td><td>{$numeric}</td><td style='background: {$color}'>{$result} ({$numeric} {$operator} {$threshold})</td></tr>";
}

echo "</table>";

echo "<h3>Expected Results for > 75:</h3>";
echo "<ul>";
echo "<li>74% → EXCLUDED</li>";
echo "<li>75% → EXCLUDED</li>";
echo "<li>76% → INCLUDED</li>";
echo "<li>80% → INCLUDED</li>";
echo "<li>Empty → EXCLUDED</li>";
echo "<li>A (90) → INCLUDED</li>";
echo "<li>B+ (75) → EXCLUDED</li>";
echo "<li>B (70) → EXCLUDED</li>";
echo "</ul>";
?>
