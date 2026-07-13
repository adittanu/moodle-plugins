<?php
/**
 * JavaScript-based injection for quiz statistics page.
 * More reliable than before_footer callback on report pages.
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Quiz Statistics Cache';
$string['fastcalculator'] = 'Fast Statistics Calculator';
$string['fastcalculator_desc'] = 'Recalculate statistics using SQL-based calculator (instant).';
