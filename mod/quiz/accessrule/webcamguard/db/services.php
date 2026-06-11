<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External services for quizaccess_webcamguard.
 *
 * @package    quizaccess_webcamguard
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'quizaccess_webcamguard_log_event' => [
        'classname' => 'quizaccess_webcamguard\\external\\log_event',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Log a Webcam Guard monitoring event for a quiz attempt.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/quiz:attempt',
    ],
    'quizaccess_webcamguard_request_live' => [
        'classname' => 'quizaccess_webcamguard\\external\\request_live',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Request or stop live Webcam Guard monitoring for one quiz attempt.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'quizaccess/webcamguard:viewreport',
    ],
    'quizaccess_webcamguard_poll_live' => [
        'classname' => 'quizaccess_webcamguard\\external\\poll_live',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Poll live Webcam Guard monitoring request state for a quiz attempt.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/quiz:attempt',
    ],
    'quizaccess_webcamguard_poll_live_stats' => [
        'classname' => 'quizaccess_webcamguard\\external\\poll_live_stats',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Poll latest stats for the teacher live monitor dashboard.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'quizaccess/webcamguard:viewreport',
    ],
];
