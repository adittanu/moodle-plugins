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

/**
 * Return the logged-in user's enrolled courses for the Dali widget.
 *
 * @package     local_daliwidget
 * @copyright   2024 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

require_once(__DIR__ . '/classes/fetch_auth_helper.php');

use local_daliwidget\fetch_auth_helper;

header('Content-Type: application/json');

try {
    $userid = 0;
    $signeduserid = optional_param('signed_user_id', 0, PARAM_INT);
    $expires = optional_param('expires', 0, PARAM_INT);
    $signature = optional_param('sig', '', PARAM_ALPHANUMEXT);

    if ($signeduserid > 0 && $expires > 0 && $signature !== '' &&
            fetch_auth_helper::validate($signeduserid, $expires, $signature)) {
        $userid = $signeduserid;
    } else {
        require_login();
        require_sesskey();

        $systemcontext = context_system::instance();
        require_capability('local/daliwidget:view', $systemcontext);
        $userid = (int)$USER->id;
    }

    $userrecord = core_user::get_user($userid, '*', MUST_EXIST);
    $fields = ['id', 'fullname', 'shortname', 'category', 'startdate', 'enddate', 'visible'];
    $courses = enrol_get_all_users_courses($userid, true, implode(',', $fields), 'fullname ASC');
    $payload = [];

    foreach ($courses as $course) {
        if ((int)$course->id === SITEID) {
            continue;
        }

        $roles = [];
        $coursecontext = context_course::instance($course->id);
        $userroles = get_user_roles($coursecontext, $userid, true);
        foreach ($userroles as $role) {
            $roles[] = $role->shortname;
        }

        $payload[] = [
            'id' => (int)$course->id,
            'fullname' => $course->fullname,
            'shortname' => $course->shortname,
            'category' => isset($course->category) ? (int)$course->category : null,
            'visible' => isset($course->visible) ? (bool)$course->visible : true,
            'startdate' => !empty($course->startdate) ? (int)$course->startdate : null,
            'enddate' => !empty($course->enddate) ? (int)$course->enddate : null,
            'roles' => array_values(array_unique($roles)),
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'user' => [
                'id' => $userid,
                'fullname' => fullname($userrecord),
            ],
            'courses' => $payload,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
