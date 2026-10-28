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

        // Operation mode: generate new questions or import existing ones from a PDF.
        $modegroup = [];
        $modegroup[] = $mform->createElement('radio', 'mode', '',
            get_string('mode_generate', 'local_aiquizgen'), 'generate');
        $modegroup[] = $mform->createElement('radio', 'mode', '',
            get_string('mode_extract', 'local_aiquizgen'), 'extract');
        $mform->addGroup($modegroup, 'modegroup', get_string('mode', 'local_aiquizgen'), [' '], false);
        $mform->setDefault('mode', 'generate');
        $mform->setType('mode', PARAM_ALPHA);
        $mform->addHelpButton('modegroup', 'mode', 'local_aiquizgen');

        $extractformats = [
            'asis' => get_string('extractformat_asis', 'local_aiquizgen'),
            'multichoice' => get_string('extractformat_multichoice', 'local_aiquizgen'),
        ];
        $mform->addElement('select', 'extractformat', get_string('extractformat', 'local_aiquizgen'),
            $extractformats);
        $mform->setDefault('extractformat', 'asis');
        $mform->setType('extractformat', PARAM_ALPHA);
        $mform->addHelpButton('extractformat', 'extractformat', 'local_aiquizgen');

        // Optional marks applied to imported questions before the review page.
        $mform->addElement('text', 'mcqmaxmark', get_string('mcqmaxmark', 'local_aiquizgen'), ['size' => 6]);
        $mform->setType('mcqmaxmark', PARAM_FLOAT);
        $mform->addHelpButton('mcqmaxmark', 'mcqmaxmark', 'local_aiquizgen');

        $mform->addElement('text', 'essaymaxmark', get_string('essaymaxmark', 'local_aiquizgen'), ['size' => 6]);
        $mform->setType('essaymaxmark', PARAM_FLOAT);
        $mform->addHelpButton('essaymaxmark', 'essaymaxmark', 'local_aiquizgen');

        $mform->addElement('static', 'extractmarkhint', '',
            '<small class="text-muted">' . get_string('extractmarkhint', 'local_aiquizgen') . '</small>');

        $mform->addElement('textarea', 'topic', get_string('topic', 'local_aiquizgen'), 
            ['rows' => 4, 'cols' => 60]);
        $mform->setType('topic', PARAM_TEXT);
        $mform->addHelpButton('topic', 'topic', 'local_aiquizgen');
        $mform->addElement('static', 'topicexample', '', 
            '<em>' . get_string('topicexample', 'local_aiquizgen') . '</em>');

        // Source selection
        $pdfsources = [
            'topic' => get_string('pdfsource_topic', 'local_aiquizgen'),
            'upload' => get_string('pdfsource_upload', 'local_aiquizgen'),
            'course' => get_string('pdfsource_course', 'local_aiquizgen'),
        ];
        $mform->addElement('select', 'pdfsource', get_string('pdfsource', 'local_aiquizgen'), $pdfsources);
        $mform->setDefault('pdfsource', 'topic');
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
        $mform->addElement('static', 'extractpdfhint', '',
            '<em>' . get_string('extractpdfhint', 'local_aiquizgen') . '</em>');
        
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

        $mform->addElement('text', 'maxmark', get_string('maxmark', 'local_aiquizgen'), ['size' => 6]);
        $mform->setType('maxmark', PARAM_FLOAT);
        $mform->setDefault('maxmark', 1);
        $mform->addHelpButton('maxmark', 'maxmark', 'local_aiquizgen');

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
            $courseid = (int)$customdata['courseid'];
            $categoryoptions = $this->get_question_category_options($courseid);

            if (empty($categoryoptions)) {
                $categoryoptions[''] = get_string('nocategories', 'local_aiquizgen');
            }

            $mform->addElement('select', 'category', get_string('category', 'local_aiquizgen'), $categoryoptions);
            $mform->addHelpButton('category', 'category', 'local_aiquizgen');

            if (!array_key_exists('', $categoryoptions)) {
                $mform->addRule('category', null, 'required', null, 'client');
                $mform->setDefault('category', array_key_first($categoryoptions));
            }

            if (!empty($customdata['categoryid']) && array_key_exists($customdata['categoryid'], $categoryoptions)) {
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
        
        // Add JavaScript for mode and source toggles.
        global $PAGE;
        $js = "
        document.addEventListener('DOMContentLoaded', function() {
            function getMode() {
                var checked = document.querySelector('input[name=\"mode\"]:checked');
                return checked ? checked.value : 'generate';
            }

            function setVisible(fieldName, visible) {
                var el = document.getElementById('id_' + fieldName)
                    || document.getElementById('fitem_id_' + fieldName);
                if (!el) {
                    return;
                }
                var container = el.closest('.fitem') || el.parentElement;
                if (container) {
                    container.style.display = visible ? '' : 'none';
                }
            }

            function getSource() {
                var el = document.getElementById('id_pdfsource');
                return el ? el.value : 'topic';
            }

            function toggleSourceFields(isExtract) {
                if (isExtract) {
                    ['topic', 'topicexample', 'pdfornote'].forEach(function(fieldName) {
                        setVisible(fieldName, false);
                    });
                    setVisible('pdffile', true);
                    setVisible('extractpdfhint', true);
                    ['coursetpdf', 'sourcehint', 'nocoursepdf'].forEach(function(fieldName) {
                        setVisible(fieldName, false);
                    });
                    var pdfsource = document.getElementById('id_pdfsource');
                    if (pdfsource) {
                        pdfsource.value = 'upload';
                    }
                    return;
                }

                var source = getSource();
                setVisible('topic', source === 'topic');
                setVisible('topicexample', source === 'topic');
                setVisible('pdffile', source === 'upload');
                setVisible('extractpdfhint', false);
                setVisible('coursetpdf', source === 'course');
                setVisible('sourcehint', source === 'course');
                setVisible('nocoursepdf', source === 'course');
            }

            function toggleMode() {
                var isExtract = getMode() === 'extract';

                ['topic', 'topicexample', 'pdfsource', 'pdfornote', 'questioncount',
                 'questiontype', 'answeroptioncount', 'difficulty', 'language', 'maxmark'
                ].forEach(function(fieldName) {
                    setVisible(fieldName, !isExtract);
                });

                setVisible('extractformat', isExtract);
                setVisible('extractpdfhint', isExtract);
                ['mcqmaxmark', 'essaymaxmark', 'extractmarkhint'].forEach(function(fieldName) {
                    setVisible(fieldName, isExtract);
                });
                toggleSourceFields(isExtract);
            }

            document.querySelectorAll('input[name=\"mode\"]').forEach(function(el) {
                el.addEventListener('change', toggleMode);
            });

            var pdfsource = document.getElementById('id_pdfsource');
            if (pdfsource) {
                pdfsource.addEventListener('change', function() {
                    toggleSourceFields(getMode() === 'extract');
                });
            }

            toggleMode();
        });
        ";
        $PAGE->requires->js_init_code($js);

        // Show a waiting overlay when a generate request is submitted. Extraction
        // has its own progress bar on the processing page.
        $waitstrings = [
            'title' => get_string('generatewait_title', 'local_aiquizgen'),
            'message' => get_string('generatewait_message', 'local_aiquizgen'),
            'loading' => get_string('loading', 'core'),
        ];
        $waitjs = <<<'JS'
document.addEventListener('DOMContentLoaded', function() {
    var strings = __AIQUIZGEN_STRINGS__;

    function currentMode() {
        var checked = document.querySelector('input[name="mode"]:checked');
        return checked ? checked.value : 'generate';
    }

    var overlay = document.createElement('div');
    overlay.id = 'aiquizgen-waiting-overlay';
    overlay.setAttribute('style', 'position: fixed; top: 0; left: 0; right: 0; bottom: 0;'
        + ' background: rgba(0, 0, 0, 0.55); z-index: 2000; display: none;'
        + ' align-items: center; justify-content: center; padding: 1rem;');
    overlay.innerHTML = '<div style="background: #fff; border-radius: 0.5rem; max-width: 520px;'
        + ' width: 100%; padding: 2rem; text-align: center; box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.3);">'
        + '<div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">'
        + '<span class="sr-only">' + strings.loading + '</span></div>'
        + '<h4 style="margin-top: 1.25rem;">' + strings.title + '</h4>'
        + '<p style="margin-bottom: 0;">' + strings.message + '</p>'
        + '</div>';
    document.body.appendChild(overlay);

    var form = document.querySelector('form.mform');
    if (!form) {
        return;
    }

    form.addEventListener('submit', function(e) {
        var submitter = e.submitter;
        if (submitter && (submitter.name === 'cancel' || submitter.id === 'id_cancel')) {
            return;
        }
        if (currentMode() !== 'generate') {
            return;
        }
        window.setTimeout(function() {
            if (e.defaultPrevented) {
                return;
            }
            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }, 50);
    });
});
JS;
        $waitjs = str_replace('__AIQUIZGEN_STRINGS__', json_encode($waitstrings), $waitjs);
        $PAGE->requires->js_init_code($waitjs);
    }

    /**
     * Return writable question categories for the course across Moodle 4.x and 5.x.
     *
     * @param int $courseid Course ID.
     * @return array<string, string>
     */
    protected function get_question_category_options(int $courseid): array {
        global $DB;

        if (class_exists(\core_question\local\bank\question_bank_helper::class)
                && method_exists(\core_question\local\bank\question_bank_helper::class,
                    'get_activity_instances_with_shareable_questions')) {
            $banks = \core_question\local\bank\question_bank_helper::get_activity_instances_with_shareable_questions(
                [$courseid], [], ['moodle/question:add'], true
            );
            $options = [];
            foreach ($banks as $bank) {
                foreach ($bank->questioncategories as $category) {
                    $options[$category->id . ',' . $category->contextid] =
                        format_string($bank->name) . ' / ' . format_string($category->name);
                }
            }
            return $options;
        }

        $coursecontext = \context_course::instance($courseid);
        $pathids = array_filter(array_map('intval', explode('/', trim((string)$coursecontext->path, '/'))));
        if (empty($pathids)) {
            $pathids = [$coursecontext->id, \context_system::instance()->id];
        }
        [$incontexts, $params] = $DB->get_in_or_equal($pathids, SQL_PARAMS_NAMED, 'ctx');
        $params += [
            'systemlevel' => CONTEXT_SYSTEM,
            'coursecatlevel' => CONTEXT_COURSECAT,
            'courselevel' => CONTEXT_COURSE,
        ];
        $categories = $DB->get_records_sql("SELECT qc.id, qc.name, qc.contextid
                                              FROM {question_categories} qc
                                              JOIN {context} ctx ON ctx.id = qc.contextid
                                             WHERE qc.contextid $incontexts
                                               AND ctx.contextlevel IN (:systemlevel, :coursecatlevel, :courselevel)
                                          ORDER BY ctx.contextlevel DESC, qc.name ASC", $params);
        $options = [];
        foreach ($categories as $category) {
            if (trim((string)$category->name) !== '') {
                $options[$category->id . ',' . $category->contextid] = format_string($category->name);
            }
        }
        return $options;
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

        $mode = $data['mode'] ?? 'generate';

        if ($mode === 'extract') {
            // Import mode always needs an uploaded PDF and a target category.
            if (empty($data['pdffile'])) {
                $errors['pdffile'] = get_string('extractpdf_required', 'local_aiquizgen');
            }

            if (empty($data['category'])) {
                $errors['category'] = get_string('nocategory', 'local_aiquizgen');
            }

            if (!in_array($data['extractformat'] ?? 'asis', ['asis', 'multichoice'], true)) {
                $errors['extractformat'] = get_string('extractformatinvalid', 'local_aiquizgen');
            }

            foreach (['mcqmaxmark', 'essaymaxmark'] as $field) {
                if (isset($data[$field]) && (float)$data[$field] < 0) {
                    $errors[$field] = get_string('maxmark_invalid', 'local_aiquizgen');
                }
            }

            return $errors;
        }

        // The source determines which field is required: topic, uploaded PDF, or
        // synced course materials.
        $hastopic = !empty($data['topic']) && trim($data['topic']) !== '';
        $pdfsource = $data['pdfsource'] ?? 'topic';

        if ($pdfsource === 'course') {
            if (empty($data['coursetpdf'])) {
                $errors['coursetpdf'] = get_string('sourcerequired', 'local_aiquizgen');
            }
        } else if ($pdfsource === 'upload') {
            if (empty($data['pdffile'])) {
                $errors['pdffile'] = get_string('pdfrequired', 'local_aiquizgen');
            }
        } else if (!$hastopic) {
            $errors['topic'] = get_string('topicrequired', 'local_aiquizgen');
        }

        if (empty($data['category'])) {
            $errors['category'] = get_string('nocategory', 'local_aiquizgen');
        }

        if (($data['questiontype'] ?? '') === 'multichoice'
            && (!isset($data['answeroptioncount']) || !in_array((int)$data['answeroptioncount'], range(3, 10), true))) {
            $errors['answeroptioncount'] = get_string('answeroptioncountinvalid', 'local_aiquizgen');
        }

        if ((float)($data['maxmark'] ?? 0) < 0.01) {
            $errors['maxmark'] = get_string('maxmark_invalid', 'local_aiquizgen');
        }

        return $errors;
    }
}
