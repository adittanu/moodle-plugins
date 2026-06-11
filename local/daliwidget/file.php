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

define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/classes/file_url_helper.php');

use local_daliwidget\file_url_helper;

$params = [
    'contextid' => required_param('contextid', PARAM_INT),
    'component' => required_param('component', PARAM_COMPONENT),
    'filearea' => required_param('filearea', PARAM_ALPHANUMEXT),
    'itemid' => required_param('itemid', PARAM_INT),
    'filepath' => required_param('filepath', PARAM_PATH),
    'filename' => required_param('filename', PARAM_FILE),
    'expires' => required_param('expires', PARAM_INT),
    'sig' => required_param('sig', PARAM_ALPHANUMEXT),
];

try {
    $file = file_url_helper::validate_request($params);
} catch (\Throwable $e) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'File access denied.';
    exit;
}

send_stored_file($file, 0, 0, true);
