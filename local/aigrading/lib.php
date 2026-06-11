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
 * AI Grading plugin library functions.
 *
 * @package    local_aigrading
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Get localized strings for JavaScript.
 *
 * @return array
 */
function local_aigrading_get_strings() {
    return [
        'aisuggestgrade' => get_string('aisuggestgrade', 'local_aigrading'),
        'bulkaigrade' => get_string('bulkaigrade', 'local_aigrading'),
        'autograde' => get_string('autograde', 'local_aigrading'),
        'processing' => get_string('processing', 'local_aigrading'),
        'suggestedgrade' => get_string('suggestedgrade', 'local_aigrading'),
        'feedback' => get_string('feedback', 'local_aigrading'),
        'explanation' => get_string('explanation', 'local_aigrading'),
        'applygrade' => get_string('applygrade', 'local_aigrading'),
        'applyall' => get_string('applyall', 'local_aigrading'),
        'cancel' => get_string('cancel', 'local_aigrading'),
        'error' => get_string('error', 'local_aigrading'),
        'success' => get_string('success', 'local_aigrading'),
        'gradeapplied' => get_string('gradeapplied', 'local_aigrading'),
        'allgradesapplied' => get_string('allgradesapplied', 'local_aigrading'),
        'autogradecomplete' => get_string('autogradecomplete', 'local_aigrading'),
    ];
}

/**
 * Legacy callback for before footer (Moodle 4.1 - 4.4).
 *
 * @return string
 */
function local_aigrading_before_footer() {
    global $PAGE;

    // Only use this callback if hook system is not available (Moodle 4.x).
    if (!class_exists('\core\hook\output\before_footer_html_generation')) {
        $pagepath = $PAGE->url->get_path();

        // Check for quiz grading pages.
        if (strpos($pagepath, '/mod/quiz/report.php') !== false) {
            // Check if we're on the grading report.
            $mode = optional_param('mode', '', PARAM_ALPHA);
            if ($mode !== 'grading') {
                return '';
            }

            // Check if user has capability.
            $cmid = optional_param('id', 0, PARAM_INT);
            if (!$cmid) {
                return '';
            }

            $context = context_module::instance($cmid);
            if (!$context || !has_capability('local/aigrading:useaigrading', $context)) {
                return '';
            }

            // Check if API key is configured.
            $apikey = get_config('local_aigrading', 'apikey');
            if (empty($apikey)) {
                return '';
            }

            // Detect if we're on overview page (no slot) or grading page (has slot).
            $slot = optional_param('slot', 0, PARAM_INT);
            $questionid = optional_param('qid', 0, PARAM_INT);
            $isoverview = ($slot == 0);

            // Load the AMD module.
            $PAGE->requires->js_call_amd('local_aigrading/grading_helper', 'init', [
                [
                    'cmid' => $cmid,
                    'slot' => $slot,
                    'questionid' => $questionid,
                    'isoverview' => $isoverview,
                    'isassignment' => false,
                    'strings' => local_aigrading_get_strings(),
                ]
            ]);

            return '';
        }

        // Check for assignment grading pages.
        if (strpos($pagepath, '/mod/assign/view.php') !== false) {
            // Check if we're on a valid assignment action.
            $action = optional_param('action', '', PARAM_ALPHA);

            // Accept: grader (individual), grade, grading (submissions list), viewsubmission.
            $validactions = ['grader', 'grade', 'grading', 'viewsubmission', ''];
            if (!in_array($action, $validactions)) {
                return '';
            }

            // Determine page type.
            $issubmissionspage = ($action === 'grading' || $action === '' || $action === 'viewsubmission');

            // Check if user has capability.
            $cmid = optional_param('id', 0, PARAM_INT);
            if (!$cmid) {
                return '';
            }

            $context = context_module::instance($cmid);
            if (!$context || !has_capability('local/aigrading:useaigrading', $context)) {
                return '';
            }

            // Check if API key is configured.
            $apikey = get_config('local_aigrading', 'apikey');
            if (empty($apikey)) {
                return '';
            }

            $userid = optional_param('userid', 0, PARAM_INT);

            // Load the AMD module for assignment grading.
            $PAGE->requires->js_call_amd('local_aigrading/grading_helper', 'init', [
                [
                    'cmid' => $cmid,
                    'userid' => $userid,
                    'isassignment' => true,
                    'issubmissionspage' => $issubmissionspage,
                    'strings' => local_aigrading_get_strings(),
                ]
            ]);

            return '';
        }
    }

    return '';
}
