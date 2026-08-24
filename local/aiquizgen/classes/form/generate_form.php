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
 * Generate questions form for AI Quiz Generator plugin.
 *
 * @package    local_aiquizgen
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiquizgen\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/questionlib.php');

class generate_form extends \moodleform {

    protected function definition() {
        global $USER;
        
        $mform = $this->_form;
        $customdata = $this->_customdata;

        $mform->addElement('header', 'general', get_string('generatequestions', 'local_aiquizgen'));

        $mform->addElement('textarea', 'topic', get_string('topic', 'local_aiquizgen'), 
            ['rows' => 4, 'cols' => 60]);
        $mform->setType('topic', PARAM_TEXT);
        $mform->addHelpButton('topic', 'topic', 'local_aiquizgen');
        $mform->addElement('static', 'topicexample', '', 
            '<em>' . get_string('topicexample', 'local_aiquizgen') . '</em>');

        // Source selection
        $pdfsources = [
            'upload' => get_string('pdfsource_upload', 'local_aiquizgen'),
            'course' => get_string('pdfsource_course', 'local_aiquizgen'),
        ];
        $mform->addElement('select', 'pdfsource', get_string('pdfsource', 'local_aiquizgen'), $pdfsources);
        $mform->setDefault('pdfsource', 'upload');
        $mform->setType('pdfsource', PARAM_ALPHA);
        $mform->addHelpButton('pdfsource', 'pdfsource', 'local_aiquizgen');

        // PDF upload option (shown when pdfsource = upload)
        $mform->addElement('filemanager', 'pdffile', get_string('pdffile', 'local_aiquizgen'), null,
            [
                'subdirs' => 0,
                'maxbytes' => 10485760, // 10MB
                'maxfiles' => 1,
                'accepted_types' => ['.pdf']
            ]);
        $mform->addHelpButton('pdffile', 'pdffile', 'local_aiquizgen');
        
        // Synced knowledge source from course materials (shown when pdfsource = course)
        if (isset($customdata['courseid'])) {
            $courseid = (int)$customdata['courseid'];

            $knowledge = $this->get_synced_knowledge_sources($courseid);
            $coursepdfoptions = ['' => get_string('select_pdf_from_course', 'local_aiquizgen')];

            foreach ($knowledge['sources'] as $source) {
                $sourceid = $source['ulid'] ?? '';
                if ($sourceid === '') {
                    continue;
                }

                $coursepdfoptions[$sourceid] = $this->format_source_label($source);
            }
            
            if (count($coursepdfoptions) > 1) {
                $mform->addElement('select', 'coursetpdf', get_string('coursetpdf', 'local_aiquizgen'), $coursepdfoptions);
                $mform->setType('coursetpdf', PARAM_ALPHANUMEXT);
                $mform->addHelpButton('coursetpdf', 'coursetpdf', 'local_aiquizgen');
                $mform->addElement('static', 'sourcehint', '', 
                    '<small class="text-muted">' . get_string('sources_hint', 'local_aiquizgen') . '</small>');
            } else {
                $message = '<em>' . get_string('nocoursepdf', 'local_aiquizgen') . '</em>';
                if (!empty($knowledge['error']) && debugging()) {
                    $message .= '<div class="alert alert-secondary mt-2 mb-0"><small>' . s($knowledge['error']) . '</small></div>';
                }
                $mform->addElement('static', 'nocoursepdf', '', $message);
            }
        }
        
        $mform->addElement('static', 'pdfornote', '', 
            '<em>' . get_string('pdfornote', 'local_aiquizgen') . '</em>');

        $maxquestions = get_config('local_aiquizgen', 'maxquestions') ?: 20;
        $countoptions = [];
        for ($i = 1; $i <= $maxquestions; $i++) {
            $countoptions[$i] = $i;
        }
        $mform->addElement('select', 'questioncount', get_string('questioncount', 'local_aiquizgen'), $countoptions);
        $mform->setDefault('questioncount', 5);
        $mform->addHelpButton('questioncount', 'questioncount', 'local_aiquizgen');

        $types = [
            'multichoice' => get_string('multichoice', 'local_aiquizgen'),
            'truefalse' => get_string('truefalse', 'local_aiquizgen'),
            'shortanswer' => get_string('shortanswer', 'local_aiquizgen'),
            'essay' => get_string('essay', 'local_aiquizgen'),
        ];
        $mform->addElement('select', 'questiontype', get_string('questiontype', 'local_aiquizgen'), $types);
        $mform->setDefault('questiontype', 'multichoice');
        $mform->addHelpButton('questiontype', 'questiontype', 'local_aiquizgen');
        $answeroptioncounts = array_combine(range(3, 10), range(3, 10));
        $mform->addElement('select', 'answeroptioncount', get_string('answeroptioncount', 'local_aiquizgen'), $answeroptioncounts);
        $mform->setType('answeroptioncount', PARAM_INT);
        $mform->setDefault('answeroptioncount', 5);
        $mform->addHelpButton('answeroptioncount', 'answeroptioncount', 'local_aiquizgen');
        $mform->disabledIf('answeroptioncount', 'questiontype', 'neq', 'multichoice');

        $difficulties = [
            'easy' => get_string('easy', 'local_aiquizgen'),
            'medium' => get_string('medium', 'local_aiquizgen'),
            'hard' => get_string('hard', 'local_aiquizgen'),
        ];
        $mform->addElement('select', 'difficulty', get_string('difficulty', 'local_aiquizgen'), $difficulties);
        $mform->setDefault('difficulty', 'medium');
        $mform->addHelpButton('difficulty', 'difficulty', 'local_aiquizgen');

        $languages = [
            'english' => get_string('english', 'local_aiquizgen'),
            'indonesian' => get_string('indonesian', 'local_aiquizgen'),
            'thai' => get_string('thai', 'local_aiquizgen'),
            'vietnamese' => get_string('vietnamese', 'local_aiquizgen'),
            'malay' => get_string('malay', 'local_aiquizgen'),
            'filipino' => get_string('filipino', 'local_aiquizgen'),
            'burmese' => get_string('burmese', 'local_aiquizgen'),
            'khmer' => get_string('khmer', 'local_aiquizgen'),
            'lao' => get_string('lao', 'local_aiquizgen'),
            'tetum' => get_string('tetum', 'local_aiquizgen'),
        ];
        $mform->addElement('select', 'language', get_string('language', 'local_aiquizgen'), $languages);
        $mform->setDefault('language', 'indonesian');
        $mform->addHelpButton('language', 'language', 'local_aiquizgen');

        if (isset($customdata['courseid'])) {
            global $DB;
            $courseid = (int)$customdata['courseid'];

            $coursecontext = \context_course::instance($courseid);
            $pathids = array_filter(array_map('intval', explode('/', trim((string)$coursecontext->path, '/'))));

            if (empty($pathids)) {
                $pathids = [$coursecontext->id, \context_system::instance()->id];
            }

            list($incontexts, $contextparams) = $DB->get_in_or_equal($pathids, SQL_PARAMS_NAMED, 'ctx');

            $sql = "SELECT qc.id, qc.name, qc.contextid
                      FROM {question_categories} qc
                      JOIN {context} ctx ON ctx.id = qc.contextid
                     WHERE qc.contextid $incontexts
                       AND ctx.contextlevel IN (:systemlevel, :coursecatlevel, :courselevel)
                  ORDER BY ctx.contextlevel DESC, qc.name ASC";

            $params = $contextparams + [
                'systemlevel' => CONTEXT_SYSTEM,
                'coursecatlevel' => CONTEXT_COURSECAT,
                'courselevel' => CONTEXT_COURSE,
            ];

            $allcategories = $DB->get_records_sql($sql, $params);

            $categoryoptions = [];
            foreach ($allcategories as $cat) {
                $name = trim((string)$cat->name);
                if ($name === '') {
                    continue;
                }
                $categorykey = $cat->id . ',' . $cat->contextid;
                $categoryoptions[$categorykey] = format_string($name);
            }

            if (empty($categoryoptions)) {
                $categoryoptions[''] = get_string('nocategories', 'local_aiquizgen');
            }

            $mform->addElement('select', 'category', get_string('category', 'local_aiquizgen'), $categoryoptions);
            $mform->addHelpButton('category', 'category', 'local_aiquizgen');

            if (count($categoryoptions) > 0 && !array_key_exists('', $categoryoptions)) {
                $mform->addRule('category', null, 'required', null, 'client');
                $defaultcategory = array_key_first($categoryoptions);
                if ($defaultcategory !== null) {
                    $mform->setDefault('category', $defaultcategory);
                }
            }

            // Auto-select category if provided in URL.
            if (isset($customdata['categoryid']) && !empty($customdata['categoryid'])
                && array_key_exists($customdata['categoryid'], $categoryoptions)) {
                $mform->setDefault('category', $customdata['categoryid']);
            }
        }

        $mform->addElement('textarea', 'additionalinstructions', 
            get_string('additionalinstructions', 'local_aiquizgen'),
            ['rows' => 3, 'cols' => 60]);
        $mform->setType('additionalinstructions', PARAM_TEXT);
        $mform->addHelpButton('additionalinstructions', 'additionalinstructions', 'local_aiquizgen');


        if (isset($customdata['courseid'])) {
            $mform->addElement('hidden', 'courseid', $customdata['courseid']);
            $mform->setType('courseid', PARAM_INT);
        }

        $this->add_action_buttons(true, get_string('generate', 'local_aiquizgen'));
        
        // Add JavaScript for source toggle
        global $PAGE;
        $js = "
        document.addEventListener('DOMContentLoaded', function() {
            function togglePdfFields() {
                var pdfsourceSelect = document.getElementById('id_pdfsource');
                if (!pdfsourceSelect) return;
                
                var isUpload = pdfsourceSelect.value === 'upload';
                
                // Toggle pdffile field
                var pdffileEl = document.getElementById('id_pdffile');
                if (pdffileEl) {
                    var pdffileContainer = pdffileEl.closest('.fitem') || pdffileEl.parentElement;
                    if (pdffileContainer) pdffileContainer.style.display = isUpload ? '' : 'none';
                }
                
                // Toggle coursetpdf field
                var coursetpdfEl = document.getElementById('id_coursetpdf');
                if (coursetpdfEl) {
                    var coursetpdfContainer = coursetpdfEl.closest('.fitem') || coursetpdfEl.parentElement;
                    if (coursetpdfContainer) coursetpdfContainer.style.display = isUpload ? 'none' : '';
                }

                ['sourcehint', 'nocoursepdf'].forEach(function(fieldName) {
                    var staticEl = document.getElementById('id_' + fieldName);
                    if (staticEl) {
                        var staticContainer = staticEl.closest('.fitem') || staticEl.parentElement;
                        if (staticContainer) staticContainer.style.display = isUpload ? 'none' : '';
                    }
                });
            }
            
            var pdfsourceSelect = document.getElementById('id_pdfsource');
            if (pdfsourceSelect) {
                pdfsourceSelect.addEventListener('change', togglePdfFields);
                togglePdfFields(); // Initial state
            }
        });
        ";
        $PAGE->requires->js_init_code($js);
    }

    /**
     * Fetch ready knowledge sources synced by local_daliwidget for this Moodle course.
     *
     * @param int $courseid
     * @return array{sources: array<int, array>, error: string|null}
     */
    protected function get_synced_knowledge_sources(int $courseid): array {
        global $CFG;

        $clientpath = $CFG->dirroot . '/local/daliwidget/classes/api_client.php';
        if (!file_exists($clientpath)) {
            return [
                'sources' => [],
                'error' => 'local_daliwidget API client is not available.',
            ];
        }

        require_once($clientpath);

        if (!class_exists('\\local_daliwidget\\api_client')) {
            return [
                'sources' => [],
                'error' => 'local_daliwidget API client class is not available.',
            ];
        }

        try {
            $client = new \local_daliwidget\api_client();
            $response = $client->getSources($courseid);

            if (empty($response['success'])) {
                return [
                    'sources' => [],
                    'error' => $response['error'] ?? 'Unable to load synced knowledge sources.',
                ];
            }

            $knowledgeid = $response['knowledge_id'] ?? null;
            $sources = [];

            foreach (($response['data'] ?? []) as $source) {
                if (!is_array($source)) {
                    continue;
                }

                if (($source['status'] ?? '') !== 'ready') {
                    continue;
                }

                if (empty($source['ulid'])) {
                    continue;
                }

                if (empty($source['knowledge_id']) && !empty($knowledgeid)) {
                    $source['knowledge_id'] = $knowledgeid;
                }

                $sources[] = $source;
            }

            return [
                'sources' => $sources,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'sources' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build a compact source label for the select element.
     *
     * @param array $source
     * @return string
     */
    protected function format_source_label(array $source): string {
        $title = trim((string)($source['title'] ?? 'Course source'));
        $type = trim((string)($source['type'] ?? 'source'));
        $chunks = isset($source['chunks_count']) ? (int)$source['chunks_count'] : 0;

        $label = $title;
        if ($type !== '') {
            $label .= ' [' . strtoupper($type) . ']';
        }
        if ($chunks > 0) {
            $label .= ' - ' . $chunks . ' chunks';
        }

        return format_string($label);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Either topic or source content must be provided
        $hastopic = !empty($data['topic']) && trim($data['topic']) !== '';
        $pdfsource = $data['pdfsource'] ?? 'upload';
        $haspdf = false;
        
        if ($pdfsource === 'course') {
            $haspdf = !empty($data['coursetpdf']);
            if (!$haspdf) {
                $errors['coursetpdf'] = get_string('topicorpdf_required', 'local_aiquizgen');
            }
        } else {
            $haspdf = !empty($data['pdffile']);
            if (!$haspdf) {
                $errors['pdffile'] = get_string('topicorpdf_required', 'local_aiquizgen');
            }
        }
        
        if (!$hastopic && !$haspdf) {
            $errors['topic'] = get_string('topicorpdf_required', 'local_aiquizgen');
        }

        if (empty($data['category'])) {
            $errors['category'] = get_string('nocategory', 'local_aiquizgen');
        }

        if (($data['questiontype'] ?? '') === 'multichoice'
            && (!isset($data['answeroptioncount']) || !in_array((int)$data['answeroptioncount'], range(3, 10), true))) {
            $errors['answeroptioncount'] = get_string('answeroptioncountinvalid', 'local_aiquizgen');
        }

        return $errors;
    }
}
