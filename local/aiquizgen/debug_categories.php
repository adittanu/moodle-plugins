<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$courseid = required_param('courseid', PARAM_INT);

$coursecontext = context_course::instance($courseid);
$parentcontexts = $coursecontext->get_parent_context_ids(true);

echo "Course ID: $courseid<br>";
echo "Course Context ID: " . $coursecontext->id . "<br>";
echo "Parent Contexts: " . implode(', ', $parentcontexts) . "<br><br>";

// Test query
list($contextsql, $contextparams) = $DB->get_in_or_equal($parentcontexts, SQL_PARAMS_NAMED);

$sql = "SELECT qc.id, qc.name, qc.contextid, qc.parent, qc.sortorder,
               ctx.contextlevel
          FROM {question_categories} qc
          JOIN {context} ctx ON ctx.id = qc.contextid
         WHERE qc.contextid $contextsql
      ORDER BY ctx.contextlevel DESC, qc.parent, qc.sortorder, qc.name";

$categories = $DB->get_records_sql($sql, $contextparams);

echo "<h3>All Categories (including parent=0):</h3>";
foreach ($categories as $cat) {
    echo "ID: {$cat->id}, Name: {$cat->name}, Context: {$cat->contextid}, Parent: {$cat->parent}, Level: {$cat->contextlevel}<br>";
}

// Now with parent > 0
$sql2 = "SELECT qc.id, qc.name, qc.contextid, qc.parent, qc.sortorder,
               ctx.contextlevel
          FROM {question_categories} qc
          JOIN {context} ctx ON ctx.id = qc.contextid
         WHERE qc.contextid $contextsql
           AND qc.parent > 0
      ORDER BY ctx.contextlevel DESC, qc.parent, qc.sortorder, qc.name";

$categories2 = $DB->get_records_sql($sql2, $contextparams);

echo "<h3>Categories with parent > 0:</h3>";
if (empty($categories2)) {
    echo "<strong>EMPTY! This is why dropdown shows 'no categories'</strong><br>";
} else {
    foreach ($categories2 as $cat) {
        echo "ID: {$cat->id}, Name: {$cat->name}, Context: {$cat->contextid}, Parent: {$cat->parent}<br>";
    }
}

// Check what's in question bank
echo "<h3>All categories in database:</h3>";
$allcats = $DB->get_records('question_categories', null, 'contextid, parent, name');
foreach ($allcats as $cat) {
    echo "ID: {$cat->id}, Name: {$cat->name}, Context: {$cat->contextid}, Parent: {$cat->parent}<br>";
}
