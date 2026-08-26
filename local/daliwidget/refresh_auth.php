<?php
// This file is part of Moodle - http://moodle.org/.

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/fetch_auth_helper.php');

header('Content-Type: application/json');

try {
    require_login();
    require_sesskey();
    require_capability('local/daliwidget:view', context_system::instance());

    $auth = \local_daliwidget\fetch_auth_helper::generate_for_user((int) $USER->id);
    if (!$auth) {
        throw new moodle_exception('Signed widget authentication is not configured.');
    }

    echo json_encode(['success' => true, 'data' => $auth]);
} catch (Throwable $error) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $error->getMessage()]);
}
