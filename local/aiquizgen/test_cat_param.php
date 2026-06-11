<?php
// Quick test to see what parameter is received
require_once(__DIR__ . '/../../config.php');

require_login();

echo "<!DOCTYPE html><html><head><title>Test Category Parameter</title></head><body>";
echo "<h1>Category Parameter Test</h1>";

$cat = optional_param('cat', '', PARAM_TEXT);

echo "<p><strong>Raw \$_GET['cat']:</strong> ";
echo isset($_GET['cat']) ? htmlspecialchars($_GET['cat']) : 'NOT SET';
echo "</p>";

echo "<p><strong>After optional_param():</strong> ";
echo !empty($cat) ? htmlspecialchars($cat) : 'EMPTY';
echo "</p>";

echo "<p><strong>Is empty?:</strong> " . (empty($cat) ? 'YES' : 'NO') . "</p>";

echo "<p><strong>Test URL:</strong></p>";
echo "<ul>";
echo "<li><a href='?cat=6,34'>Test with cat=6,34</a></li>";
echo "<li><a href='?cat=6%2C34'>Test with cat=6%2C34 (URL encoded)</a></li>";
echo "</ul>";

echo "<p><a href='generate.php?courseid=3&cat=6,34'>Go to generate.php with cat=6,34</a></p>";

echo "</body></html>";
