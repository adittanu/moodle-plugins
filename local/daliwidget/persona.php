<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Authenticated private persona configuration endpoint.
 *
 * @package    local_daliwidget
 * @copyright  2026 Dali AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/fetch_auth_helper.php');

use local_daliwidget\fetch_auth_helper;

header('Content-Type: application/json');

$userid = optional_param('signed_user_id', 0, PARAM_INT);
$expires = optional_param('expires', 0, PARAM_INT);
$signature = optional_param('sig', '', PARAM_ALPHANUMEXT);

if (!fetch_auth_helper::validate($userid, $expires, $signature)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired signature.']);
    exit;
}

$style = (string) (get_config('local_daliwidget', 'speaking_style') ?: 'default');
$instruction = trim((string) get_config('local_daliwidget', 'custom_instruction'));
if (!in_array($style, ['default', 'professional', 'friendly', 'casual', 'concise', 'tutor'], true)
        || core_text::strlen($instruction) > 2000) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid persona configuration.']);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => ['speaking_style' => $style, 'custom_instruction' => $instruction],
]);
