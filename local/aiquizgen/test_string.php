<?php
require_once(__DIR__ . '/../../config.php');
require_login();

echo "<!DOCTYPE html><html><head><title>Test Language String</title></head><body>";
echo "<h1>Test Language String Loading</h1>";

$strings = [
    'gotoqbank',
    'regenerate',
    'generatequestions',
    'pluginname',
];

echo "<table border='1'>";
echo "<tr><th>String ID</th><th>Value</th><th>Status</th></tr>";

foreach ($strings as $stringid) {
    try {
        $value = get_string($stringid, 'local_aiquizgen');
        $status = '<span style="color:green;">✓ OK</span>';
    } catch (Exception $e) {
        $value = $e->getMessage();
        $status = '<span style="color:red;">✗ ERROR</span>';
    }
    echo "<tr>";
    echo "<td>" . htmlspecialchars($stringid) . "</td>";
    echo "<td>" . htmlspecialchars($value) . "</td>";
    echo "<td>" . $status . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<p><a href='generate.php?courseid=3&cat=6,34'>Test generate.php with cat=6,34</a></p>";
echo "</body></html>";
