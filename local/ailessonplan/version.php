<?php
// This file is part of Moodle - http://moodle.org/

/**
 * AI Lesson Plan plugin version information.
 *
 * @package    local_ailessonplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026050400;
$plugin->requires  = 2022112800; // Moodle 4.1+.
$plugin->component = 'local_ailessonplan';
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';
$plugin->supported = [401, 500];
