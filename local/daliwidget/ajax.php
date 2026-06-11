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
 * AJAX endpoint for Knowledge Base operations
 *
 * @package     local_daliwidget
 * @copyright   2024 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/api_client.php');
require_once(__DIR__ . '/classes/sync_helper.php');

use local_daliwidget\api_client;
use local_daliwidget\sync_helper;

// Get parameters
$action = required_param('action', PARAM_ALPHA);
$courseid = required_param('courseid', PARAM_INT);

// Verify sesskey
require_sesskey();

// Get the course and check access
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);

// Initialize API client
$apiClient = new api_client();

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'sync':
            $cmid = required_param('cmid', PARAM_INT);
            get_coursemodule_from_id(null, $cmid, $courseid, false, MUST_EXIST);
            $result = sync_helper::sync_or_queue($course, $cmid);
            echo json_encode($result);
            break;
            
        case 'addtext':
            $title = required_param('title', PARAM_TEXT);
            $content = required_param('content', PARAM_RAW);
            $courseMetadata = api_client::buildMoodleMetadata($course);
            $result = $apiClient->addTextSource($title, $content, $courseMetadata);
            echo json_encode($result);
            break;
            
        case 'addurl':
            $url = required_param('url', PARAM_URL);
            $name = required_param('name', PARAM_TEXT);
            $courseMetadata = api_client::buildMoodleMetadata($course);
            $result = $apiClient->addUrlSource($url, $name, $courseMetadata);
            echo json_encode($result);
            break;
            
        case 'addyoutube':
            $url = required_param('url', PARAM_URL);
            $courseMetadata = api_client::buildMoodleMetadata($course);
            $result = $apiClient->addYoutubeSource($url, null, $courseMetadata);
            echo json_encode($result);
            break;
            
        case 'delete':
            $sourceid = required_param('sourceid', PARAM_INT);
            $result = $apiClient->deleteSource($sourceid);
            echo json_encode($result);
            break;

        case 'retrysource':
            $sourceid = required_param('sourceid', PARAM_INT);
            $cmid = optional_param('cmid', 0, PARAM_INT);

            if ($cmid > 0) {
                get_coursemodule_from_id(null, $cmid, $courseid, false, MUST_EXIST);
                $deleteResult = $apiClient->deleteSource($sourceid);
                if (empty($deleteResult['success'])) {
                    echo json_encode(['success' => false, 'error' => 'Failed to delete old source before retry: ' . ($deleteResult['error'] ?? 'Unknown error')]);
                    break;
                }
                $result = sync_helper::sync_or_queue($course, $cmid);
                $result['retry_mode'] = 'moodle_activity';
                echo json_encode($result);
                break;
            }

            $result = $apiClient->retrySource($sourceid);
            $result['retry_mode'] = 'app_source';
            echo json_encode($result);
            break;

        case 'retry':
            $sourceid = required_param('sourceid', PARAM_INT);
            $result = $apiClient->retrySource($sourceid);
            echo json_encode($result);
            break;
            
        case 'list':
            $result = $apiClient->getSources($courseid);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
