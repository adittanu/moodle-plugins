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
 * Generate questions page for AI Quiz Generator plugin.
 *
 * @package    local_aiquizgen
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/filelib.php');

// Load composer autoload for PDF parser library
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once(__DIR__ . '/../../vendor/autoload.php');
}

use local_aiquizgen\form\generate_form;
use local_aiquizgen\api\mastra_client;
use local_aiquizgen\generator\question_generator;

/**
 * Build edited question payload from review form submission.
 *
 * @return array
 */
function local_aiquizgen_get_edited_questions_from_post(): array {
    $questiontotal = optional_param('questiontotal', 0, PARAM_INT);
    $questions = [];

    for ($i = 0; $i < $questiontotal; $i++) {
        $questiontext = trim((string)optional_param('qtext_' . $i, '', PARAM_RAW));
        $questiontype = optional_param('qtype_' . $i, 'multichoice', PARAM_ALPHA);
        $answercount = optional_param('answercount_' . $i, 0, PARAM_INT);
        $correctindex = optional_param('correct_' . $i, -1, PARAM_INT);

        if ($questiontext === '' || $answercount <= 0) {
            continue;
        }

        $answers = [];
        for ($j = 0; $j < $answercount; $j++) {
            $answertext = trim((string)optional_param('ans_' . $i . '_' . $j, '', PARAM_RAW));
            $feedback = trim((string)optional_param('feed_' . $i . '_' . $j, '', PARAM_RAW));
            if ($answertext === '') {
                continue;
            }

            $answers[] = [
                'text' => $answertext,
                'fraction' => ($j === $correctindex) ? 1 : 0,
                'feedback' => $feedback,
            ];
        }

        if (empty($answers)) {
            continue;
        }

        $hascorrect = false;
        foreach ($answers as $answer) {
            if (($answer['fraction'] ?? 0) > 0) {
                $hascorrect = true;
                break;
            }
        }
        if (!$hascorrect) {
            $answers[0]['fraction'] = 1;
        }

        $questions[] = [
            'questiontext' => $questiontext,
            'questiontype' => $questiontype,
            'answers' => $answers,
        ];
    }

    return $questions;
}

/**
 * Fetch ready Dali knowledge sources synced for the given Moodle course.
 *
 * @param int $courseid
 * @return array{sources: array<int, array>, knowledge_id: string|null, error: string|null}
 */
function local_aiquizgen_get_synced_knowledge_sources(int $courseid): array {
    global $CFG;

    $clientpath = $CFG->dirroot . '/local/daliwidget/classes/api_client.php';
    if (!file_exists($clientpath)) {
        return [
            'sources' => [],
            'knowledge_id' => null,
            'error' => 'local_daliwidget API client is not available.',
        ];
    }

    require_once($clientpath);

    if (!class_exists('\\local_daliwidget\\api_client')) {
        return [
            'sources' => [],
            'knowledge_id' => null,
            'error' => 'local_daliwidget API client class is not available.',
        ];
    }

    try {
        $client = new \local_daliwidget\api_client();
        $response = $client->getSources($courseid);

        if (empty($response['success'])) {
            return [
                'sources' => [],
                'knowledge_id' => null,
                'error' => $response['error'] ?? 'Unable to load synced knowledge sources.',
            ];
        }

        $knowledgeid = $response['knowledge_id'] ?? null;
        $sources = [];

        foreach (($response['data'] ?? []) as $source) {
            if (!is_array($source) || ($source['status'] ?? '') !== 'ready' || empty($source['ulid'])) {
                continue;
            }

            if (empty($source['knowledge_id']) && !empty($knowledgeid)) {
                $source['knowledge_id'] = $knowledgeid;
            }

            $sources[] = $source;
        }

        return [
            'sources' => $sources,
            'knowledge_id' => $knowledgeid,
            'error' => null,
        ];
    } catch (Throwable $e) {
        return [
            'sources' => [],
            'knowledge_id' => null,
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Resolve selected synced knowledge source by ULID.
 *
 * @param int $courseid
 * @param string $sourceid
 * @return array
 */
function local_aiquizgen_find_synced_knowledge_source(int $courseid, string $sourceid): array {
    $knowledge = local_aiquizgen_get_synced_knowledge_sources($courseid);

    if (!empty($knowledge['error'])) {
        throw new moodle_exception('apierror', 'local_aiquizgen', '', $knowledge['error']);
    }

    foreach ($knowledge['sources'] as $source) {
        if ((string)($source['ulid'] ?? '') === $sourceid) {
            if (empty($source['knowledge_id']) && !empty($knowledge['knowledge_id'])) {
                $source['knowledge_id'] = $knowledge['knowledge_id'];
            }
            return $source;
        }
    }

    throw new moodle_exception('apierror', 'local_aiquizgen', '', 'Selected source was not found or is not ready yet.');
}

/**
 * Retrieve source-specific context from Dali RAG.
 *
 * @param array $source
 * @param string $topic
 * @param int $courseid
 * @return string
 */
function local_aiquizgen_retrieve_knowledge_source_context(array $source, string $topic, int $courseid): string {
    $knowledgeid = trim((string)($source['knowledge_id'] ?? ''));
    $sourceid = trim((string)($source['ulid'] ?? ''));
    $title = trim((string)($source['title'] ?? 'course source'));

    if ($knowledgeid === '' || $sourceid === '') {
        throw new moodle_exception('apierror', 'local_aiquizgen', '', 'Selected source is missing knowledge identifiers.');
    }

    $baseurl = get_config('local_daliwidget', 'baseurl') ?: get_config('local_aiquizgen', 'apibaseurl');
    if (empty($baseurl)) {
        throw new moodle_exception('apierror', 'local_aiquizgen', '', 'Dali API base URL is not configured.');
    }

    $apikey = get_config('local_daliwidget', 'apikey') ?: get_config('local_aiquizgen', 'apikey');
    $query = trim($topic);
    if ($query === '') {
        $query = 'Key concepts and important details from ' . $title;
    }

    $payload = [
        'query' => $query,
        'knowledge_id' => $knowledgeid,
        'knowledge_source_id' => $sourceid,
        'k' => 10,
    ];

    $curl = new curl(['ignoresecurity' => true]);
    $headers = ['Content-Type: application/json'];
    if (!empty($apikey)) {
        $headers[] = 'Authorization: Bearer ' . $apikey;
    }
    $curl->setHeader($headers);

    $url = rtrim($baseurl, '/') . '/api/v1/rag/query';
    $response = $curl->post($url, json_encode($payload), [
        'CURLOPT_TIMEOUT' => 45,
        'CURLOPT_CONNECTTIMEOUT' => 10,
        'CURLOPT_SSL_VERIFYPEER' => false,
        'CURLOPT_SSL_VERIFYHOST' => 0,
    ]);
    $info = $curl->get_info();

    if ($curl->get_errno()) {
        throw new moodle_exception('apierror', 'local_aiquizgen', '', 'Retrieval cURL error code: ' . $curl->get_errno());
    }

    if (($info['http_code'] ?? 0) !== 200) {
        $errordata = json_decode($response, true);
        $message = $errordata['error'] ?? $errordata['message'] ?? ('HTTP ' . ($info['http_code'] ?? 'unknown'));
        throw new moodle_exception('apierror', 'local_aiquizgen', '', 'Source retrieval failed: ' . $message);
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded) || empty($decoded['success'])) {
        $message = is_array($decoded) ? ($decoded['error'] ?? 'Invalid retrieval response.') : 'Invalid retrieval response.';
        throw new moodle_exception('apierror', 'local_aiquizgen', '', $message);
    }

    $parts = [];
    foreach (($decoded['results'] ?? []) as $result) {
        if (is_string($result)) {
            $content = $result;
        } else if (is_array($result)) {
            $content = (string)($result['content'] ?? $result['text'] ?? $result['page_content'] ?? '');
        } else {
            $content = '';
        }

        $content = trim($content);
        if ($content !== '') {
            $parts[] = $content;
        }
    }

    $context = trim(implode("\n\n---\n\n", $parts));
    if ($context === '') {
        throw new moodle_exception('apierror', 'local_aiquizgen', '', 'No relevant content was retrieved from the selected source.');
    }

    return core_text::substr($context, 0, 10000);
}

$courseid = required_param('courseid', PARAM_INT);
$categoryid = optional_param('cat', '', PARAM_TEXT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

require_login($courseid);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_capability('local/aiquizgen:generate', $context);

$PAGE->set_url('/local/aiquizgen/generate.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('generatequestions', 'local_aiquizgen'));
$PAGE->set_heading($course->fullname);

$apikey = get_config('local_aiquizgen', 'apikey');
if (empty($apikey)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('noapikey', 'local_aiquizgen'), 'error');
    echo $OUTPUT->footer();
    exit;
}

// Prepare draft area for file upload
$draftitemid = file_get_submitted_draft_itemid('pdffile');
file_prepare_draft_area($draftitemid, $context->id, 'local_aiquizgen', 'pdffile', 0);

$customdata = [
    'courseid' => $courseid,
    'categoryid' => $categoryid,
];

// Set default data for filemanager
$formdata = new stdClass();
$formdata->pdffile = $draftitemid;

$mform = new generate_form(null, $customdata);
$mform->set_data($formdata);

$action = optional_param('action', '', PARAM_ALPHA);

if ($action === 'saveedited' && confirm_sesskey()) {
    echo $OUTPUT->header();

    try {
        $editedquestions = local_aiquizgen_get_edited_questions_from_post();
        if (empty($editedquestions)) {
            throw new moodle_exception('saveerror', 'local_aiquizgen', '', 'No valid edited questions to save.');
        }

        $selectedcategory = required_param('category', PARAM_TEXT);
        $generator = new question_generator();
        $result = $generator->save_questions_to_bank($editedquestions, $selectedcategory, $courseid);

        // Extract category ID for logging.
        $categoryid_for_log = $selectedcategory;
        if (is_string($categoryid_for_log) && strpos($categoryid_for_log, ',') !== false) {
            list($catid, $contextid) = explode(',', $categoryid_for_log);
            $categoryid_for_log = (int)$catid;
        }

        $generator->log_generation(
            $USER->id,
            $courseid,
            $categoryid_for_log,
            optional_param('originaltopic', '', PARAM_RAW),
            count($editedquestions),
            optional_param('originaltype', 'multichoice', PARAM_ALPHA),
            (bool)($result['success'] ?? false)
        );

        if (!empty($result['success'])) {
            echo $OUTPUT->notification(
                get_string('savesuccess', 'local_aiquizgen', $result['saved']),
                'success'
            );

            if (!empty($result['errors'])) {
                echo $OUTPUT->notification(
                    get_string('saveerror', 'local_aiquizgen', implode(', ', $result['errors'])),
                    'warning'
                );
            }

            // Build question bank URL.
            $categoryid_for_db = $selectedcategory;
            if (is_string($categoryid_for_db) && strpos($categoryid_for_db, ',') !== false) {
                list($catid, $contextid) = explode(',', $categoryid_for_db);
                $categoryid_for_db = (int)$catid;
            }

            $categoryid_for_db = (int)$categoryid_for_db;
            if ($categoryid_for_db > 0) {
                $category = $DB->get_record('question_categories', ['id' => $categoryid_for_db]);
                if ($category) {
                    $questionbankurl = new moodle_url('/question/edit.php', [
                        'courseid' => $courseid,
                        'cat' => $selectedcategory . ',' . $category->contextid,
                    ]);

                    echo html_writer::div(
                        $OUTPUT->single_button($questionbankurl, get_string('gotoqbank', 'local_aiquizgen'), 'get') .
                        $OUTPUT->single_button(new moodle_url('/local/aiquizgen/generate.php', ['courseid' => $courseid]),
                            get_string('regenerate', 'local_aiquizgen'), 'get'),
                        'mt-3'
                    );
                }
            }
        } else {
            throw new moodle_exception('saveerror', 'local_aiquizgen', '', implode(', ', $result['errors'] ?? []));
        }
    } catch (Exception $e) {
        echo $OUTPUT->notification(
            get_string('saveerror', 'local_aiquizgen', $e->getMessage()),
            'error'
        );
        echo html_writer::div(
            $OUTPUT->single_button(new moodle_url('/local/aiquizgen/generate.php', ['courseid' => $courseid]),
                get_string('tryagain', 'core'), 'get'),
            'mt-3'
        );
    }

    echo $OUTPUT->footer();
    exit;
}

if ($mform->is_cancelled()) {
    if ($returnurl) {
        redirect(new moodle_url($returnurl));
    } else {
        redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
    }
} else if ($data = $mform->get_data()) {

    // Process uploaded files - save to proper area
    if (!empty($data->pdffile)) {
        file_save_draft_area_files($data->pdffile, $context->id, 'local_aiquizgen', 'pdffile', 0);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('generating', 'local_aiquizgen'));

    echo html_writer::start_div('alert alert-info');
    echo html_writer::tag('p', get_string('generating', 'local_aiquizgen'));
    echo html_writer::end_div();

    try {
        $pdfcontent = '';
        $sourcecontent = '';
        $selectedsourcetitle = '';

        // Debug filemanager
        if (debugging()) {
            echo html_writer::start_div('alert alert-info');
            echo "Debug: pdffile itemid = " . ($data->pdffile ?? 'NULL') . "<br>";
            echo html_writer::end_div();
        }

        // Debug: show all data from form
        if (debugging()) {
            echo html_writer::start_div('alert alert-secondary');
            echo html_writer::tag('p', 'Debug: pdfsource = ' . s($data->pdfsource ?? 'NOT SET') . ', coursetpdf = ' . s($data->coursetpdf ?? 'NOT SET'));
            echo html_writer::end_div();
        }

        $pdffilesource = $data->pdfsource ?? 'upload';
        
        // Handle synced Dali knowledge source from course materials.
        if ($pdffilesource === 'course' && !empty($data->coursetpdf)) {
            echo html_writer::start_div('alert alert-info');
            echo html_writer::tag('p', get_string('retrievingsource', 'local_aiquizgen'));
            echo html_writer::end_div();
            
            // Debug: show selected value
            if (debugging()) {
                echo html_writer::start_div('alert alert-secondary');
                echo html_writer::tag('p', 'Debug: Selected value = ' . s($data->coursetpdf));
                echo html_writer::end_div();
            }

            $selectedsource = local_aiquizgen_find_synced_knowledge_source($courseid, (string)$data->coursetpdf);
            $selectedsourcetitle = trim((string)($selectedsource['title'] ?? 'course source'));
            $sourcecontent = local_aiquizgen_retrieve_knowledge_source_context(
                $selectedsource,
                trim($data->topic ?? ''),
                $courseid
            );

            $retrievedlength = strlen($sourcecontent);
            echo html_writer::start_div('alert alert-success');
            echo html_writer::tag('p', get_string('source_retrieved', 'local_aiquizgen', $retrievedlength));
            echo html_writer::end_div();
        }
        // Handle uploaded PDF file
        else if (!empty($data->pdffile)) {
            echo html_writer::start_div('alert alert-info');
            echo html_writer::tag('p', get_string('extractingpdf', 'local_aiquizgen'));
            echo html_writer::end_div();

            $fs = get_file_storage();

            // Try both draft area and saved area
            $files = $fs->get_area_files($context->id, 'user', 'draft', $data->pdffile, 'id DESC', false);

            // If not in draft, try saved area
            if (empty($files)) {
                $files = $fs->get_area_files($context->id, 'local_aiquizgen', 'pdffile', 0, 'id DESC', false);
            }

            // Debug files found
            if (debugging()) {
                echo html_writer::start_div('alert alert-info');
                echo "Debug: Found " . count($files) . " files<br>";
                foreach ($files as $file) {
                    echo "- " . $file->get_filename() . " (" . $file->get_filesize() . " bytes)<br>";
                }
                echo html_writer::end_div();
            }

            if (!empty($files)) {
                $file = reset($files);
                $tempfile = tempnam(sys_get_temp_dir(), 'pdf_');
                $file->copy_content_to($tempfile);

                try {
                    require_once(__DIR__ . '/classes/util/pdf_extractor.php');
                    // Extract with limit suitable for AI (leave room for prompt + questions)
                    $pdfcontent = \local_aiquizgen\util\pdf_extractor::extract_text($tempfile, 8000);

                    $extractedlength = strlen($pdfcontent);
                    echo html_writer::start_div('alert alert-success');
                    echo html_writer::tag('p', get_string('pdfextracted', 'local_aiquizgen', $extractedlength));

                    // Warn if truncated
                    if ($extractedlength >= 7950) {
                        echo html_writer::tag('p', '<small><em>Note: PDF content was truncated to fit AI token limits. Only the first ~8000 characters were used.</em></small>');
                    }

                    echo html_writer::end_div();
                } catch (Exception $e) {
                    throw new moodle_exception('pdferror', 'local_aiquizgen', '', $e->getMessage());
                } finally {
                    if (file_exists($tempfile)) {
                        unlink($tempfile);
                    }
                }
            }
        }

        // Combine topic and retrieved/uploaded source content.
        $topic = trim($data->topic ?? '');
        if (!empty($sourcecontent)) {
            $sourcetitle = $selectedsourcetitle !== '' ? $selectedsourcetitle : 'selected course source';
            if (!empty($topic)) {
                $topic = "Based on the retrieved course source \"" . $sourcetitle . "\", create questions about: " . $topic .
                         "\n\n=== SOURCE CONTENT START ===\n" . $sourcecontent . "\n=== SOURCE CONTENT END ===\n\n" .
                         "Use only the retrieved source content as the factual basis for the questions.";
            } else {
                $topic = "Read and analyze the retrieved course source \"" . $sourcetitle . "\" carefully, then create questions based on the key concepts and information:\n\n" .
                         "=== SOURCE CONTENT START ===\n" . $sourcecontent . "\n=== SOURCE CONTENT END ===\n\n" .
                         "Create questions that test understanding of the main ideas and important details from this source.";
            }
        } else if (!empty($pdfcontent)) {
            if (!empty($topic)) {
                $topic = "Based on the following PDF content, create questions about: " . $topic .
                         "\n\n=== PDF CONTENT START ===\n" . $pdfcontent . "\n=== PDF CONTENT END ===\n\n" .
                         "Focus the questions on the topic mentioned while using information from the PDF content.";
            } else {
                $topic = "Read and analyze the following PDF content carefully, then create questions based on the key concepts and information:\n\n" .
                         "=== PDF CONTENT START ===\n" . $pdfcontent . "\n=== PDF CONTENT END ===\n\n" .
                         "Create questions that test understanding of the main ideas and important details from this content.";
            }
        }

        // Debug output
        if (debugging()) {
            echo html_writer::start_div('alert alert-warning');
            echo html_writer::tag('p', '<strong>Debug Info:</strong><br>');
            echo 'Topic length: ' . strlen($topic) . ' chars<br>';
            echo 'PDF content length: ' . strlen($pdfcontent) . ' chars<br>';
            echo 'Source content length: ' . strlen($sourcecontent) . ' chars<br>';
            echo 'Additional Instructions: "' . htmlspecialchars($data->additionalinstructions ?? '') . '"<br>';
            echo 'First 200 chars of topic: ' . htmlspecialchars(substr($topic, 0, 200)) . '...<br><br>';

            if (!empty($sourcecontent)) {
                echo '<strong>Retrieved Source Content (first 500 chars):</strong><br>';
                echo '<pre style="background: #f5f5f5; padding: 10px; font-size: 11px; max-height: 200px; overflow-y: auto;">';
                echo htmlspecialchars(substr($sourcecontent, 0, 500));
                echo '</pre>';
            } else if (!empty($pdfcontent)) {
                echo '<strong>Extracted PDF Content (first 500 chars):</strong><br>';
                echo '<pre style="background: #f5f5f5; padding: 10px; font-size: 11px; max-height: 200px; overflow-y: auto;">';
                echo htmlspecialchars(substr($pdfcontent, 0, 500));
                echo '</pre>';
            }

            echo html_writer::end_div();
        }

        $client = new mastra_client();

        $questions = $client->generate_questions(
            $topic,
            $data->questioncount,
            $data->questiontype,
            $data->difficulty,
            $data->language,
            $data->additionalinstructions ?? '',
            $data->questiontype === 'multichoice' ? (int)$data->answeroptioncount : null
        );

        if (empty($questions)) {
            throw new moodle_exception('generateerror', 'local_aiquizgen', '', 'No questions generated');
        }

        echo $OUTPUT->notification('Questions generated. Review and edit them first, then save to question bank.', 'info');
        echo html_writer::tag('h3', get_string('previewtitle', 'local_aiquizgen'));

        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => (new moodle_url('/local/aiquizgen/generate.php', ['courseid' => $courseid]))->out(false),
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'saveedited']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'category', 'value' => $data->category]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'originaltopic', 'value' => $data->topic ?? '']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'originaltype', 'value' => $data->questiontype]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'questiontotal', 'value' => count($questions)]);

        foreach ($questions as $index => $question) {
            $qtype = $question['questiontype'] ?? $data->questiontype;
            $answers = $question['answers'] ?? [];

            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'qtype_' . $index, 'value' => $qtype]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'answercount_' . $index, 'value' => count($answers)]);

            echo html_writer::start_div('card mb-3');
            echo html_writer::start_div('card-body');
            echo html_writer::tag('h5', get_string('question', 'local_aiquizgen') . ' ' . ($index + 1), ['class' => 'card-title']);

            echo html_writer::start_div('form-group');
            echo html_writer::tag('label', get_string('question', 'local_aiquizgen') . ' text', ['for' => 'qtext_' . $index]);
            echo html_writer::tag('textarea', s($question['questiontext'] ?? ''), [
                'class' => 'form-control',
                'rows' => 3,
                'name' => 'qtext_' . $index,
                'id' => 'qtext_' . $index,
                'required' => 'required',
            ]);
            echo html_writer::end_div();

            echo html_writer::tag('strong', get_string('answers', 'local_aiquizgen') . ':');
            echo html_writer::start_div('mt-2');

            $currentcorrect = 0;
            foreach ($answers as $aidx => $answer) {
                if (($answer['fraction'] ?? 0) > 0) {
                    $currentcorrect = $aidx;
                    break;
                }
            }

            foreach ($answers as $aidx => $answer) {
                echo html_writer::start_div('border rounded p-2 mb-2');
                echo html_writer::start_div('form-row align-items-center');

                echo html_writer::start_div('col-auto');
                echo html_writer::empty_tag('input', [
                    'type' => 'radio',
                    'name' => 'correct_' . $index,
                    'value' => $aidx,
                    'checked' => ($aidx === $currentcorrect) ? 'checked' : null,
                    'title' => get_string('correctanswer', 'local_aiquizgen'),
                ]);
                echo html_writer::end_div();

                echo html_writer::start_div('col');
                echo html_writer::empty_tag('input', [
                    'type' => 'text',
                    'class' => 'form-control',
                    'name' => 'ans_' . $index . '_' . $aidx,
                    'value' => $answer['text'] ?? '',
                    'required' => 'required',
                ]);
                echo html_writer::end_div();

                echo html_writer::end_div();

                echo html_writer::start_div('form-group mt-2 mb-0');
                echo html_writer::tag('label', get_string('feedback', 'local_aiquizgen'), ['class' => 'small text-muted']);
                echo html_writer::empty_tag('input', [
                    'type' => 'text',
                    'class' => 'form-control form-control-sm',
                    'name' => 'feed_' . $index . '_' . $aidx,
                    'value' => $answer['feedback'] ?? '',
                ]);
                echo html_writer::end_div();
                echo html_writer::end_div();
            }

            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::end_div();
        }

        echo html_writer::div(
            html_writer::empty_tag('input', [
                'type' => 'submit',
                'class' => 'btn btn-primary mr-2',
                'value' => get_string('savetobank', 'local_aiquizgen'),
            ]) .
            $OUTPUT->single_button(new moodle_url('/local/aiquizgen/generate.php', ['courseid' => $courseid]),
                get_string('regenerate', 'local_aiquizgen'), 'get'),
            'mt-3'
        );

        echo html_writer::end_tag('form');

    } catch (Exception $e) {
        $generator = new question_generator();

        // Extract category ID for logging
        $categoryid_for_log = $data->category ?? 0;
        if (is_string($categoryid_for_log) && strpos($categoryid_for_log, ',') !== false) {
            list($catid, $contextid) = explode(',', $categoryid_for_log);
            $categoryid_for_log = (int)$catid;
        }

        $generator->log_generation(
            $USER->id,
            $courseid,
            $categoryid_for_log,
            $data->topic,
            $data->questioncount,
            $data->questiontype,
            false
        );

        echo $OUTPUT->notification(
            get_string('generateerror', 'local_aiquizgen', $e->getMessage()),
            'error'
        );

        echo html_writer::div(
            $OUTPUT->single_button(new moodle_url('/local/aiquizgen/generate.php', ['courseid' => $courseid]),
                get_string('tryagain', 'core'), 'get'),
            'mt-3'
        );
    }

    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
