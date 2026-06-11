<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ailessonplan\form;

/**
 * Generate lesson plan form.
 *
 * @package    local_ailessonplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class generate_form extends \moodleform {

    /**
     * Define form fields.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $customdata = $this->_customdata;

        $mform->addElement('header', 'general', get_string('generateplan', 'local_ailessonplan'));

        $mform->addElement('textarea', 'topic', get_string('topic', 'local_ailessonplan'), ['rows' => 4, 'cols' => 70]);
        $mform->setType('topic', PARAM_TEXT);
        $mform->addHelpButton('topic', 'topic', 'local_ailessonplan');
        $mform->addElement('static', 'topicexample', '', '<em>' . get_string('topicexample', 'local_ailessonplan') . '</em>');

        $leveloptions = [
            'Beginner / mixed ability' => get_string('level_beginner_mixed', 'local_ailessonplan'),
            'Beginner' => get_string('level_beginner', 'local_ailessonplan'),
            'Intermediate' => get_string('level_intermediate', 'local_ailessonplan'),
            'Advanced' => get_string('level_advanced', 'local_ailessonplan'),
            'Foundation / basic education' => get_string('level_foundation', 'local_ailessonplan'),
            'Secondary / pre-university' => get_string('level_secondary', 'local_ailessonplan'),
            'Vocational / skills training' => get_string('level_vocational', 'local_ailessonplan'),
            'Higher education / university' => get_string('level_higher_education', 'local_ailessonplan'),
            'Professional / corporate training' => get_string('level_professional', 'local_ailessonplan'),
        ];
        $mform->addElement('select', 'level', get_string('level', 'local_ailessonplan'), $leveloptions);
        $mform->setType('level', PARAM_TEXT);
        $mform->setDefault('level', 'Beginner / mixed ability');

        $mform->addElement('text', 'duration', get_string('duration', 'local_ailessonplan'), ['size' => 30]);
        $mform->setType('duration', PARAM_TEXT);
        $mform->setDefault('duration', '2 x 50 menit');

        $meetingoptions = [];
        for ($i = 1; $i <= 5; $i++) {
            $meetingoptions[$i] = $i;
        }
        $mform->addElement('select', 'meetings', get_string('meetings', 'local_ailessonplan'), $meetingoptions);
        $mform->setDefault('meetings', 4);

        $languages = [
            'indonesian' => get_string('indonesian', 'local_ailessonplan'),
            'english' => get_string('english', 'local_ailessonplan'),
        ];
        $mform->addElement('select', 'language', get_string('language', 'local_ailessonplan'), $languages);
        $mform->setDefault('language', 'indonesian');

        $densityoptions = [
            'light' => get_string('density_light', 'local_ailessonplan'),
            'balanced' => get_string('density_balanced', 'local_ailessonplan'),
            'rich' => get_string('density_rich', 'local_ailessonplan'),
        ];
        $mform->addElement('select', 'activitydensity', get_string('activitydensity', 'local_ailessonplan'), $densityoptions);
        $mform->setDefault('activitydensity', 'balanced');

        $mform->addElement('textarea', 'curriculumreference', get_string('curriculumreference', 'local_ailessonplan'), ['rows' => 4, 'cols' => 70]);
        $mform->setType('curriculumreference', PARAM_TEXT);

        $mform->addElement('header', 'contextheader', get_string('includecontext', 'local_ailessonplan'));
        $mform->addElement('advcheckbox', 'includemetadata', '', get_string('includecoursemetadata', 'local_ailessonplan'));
        $mform->setDefault('includemetadata', 1);
        $mform->addElement('advcheckbox', 'includesections', '', get_string('includesections', 'local_ailessonplan'));
        $mform->setDefault('includesections', 1);
        $mform->addElement('advcheckbox', 'includeactivities', '', get_string('includeactivities', 'local_ailessonplan'));
        $mform->setDefault('includeactivities', 1);
        $mform->addElement('advcheckbox', 'includesource', '', get_string('includesource', 'local_ailessonplan'));
        $mform->setDefault('includesource', 0);

        if (!empty($customdata['courseid'])) {
            $courseid = (int)$customdata['courseid'];
            $knowledge = \local_ailessonplan\context_builder::get_synced_knowledge_sources($courseid);
            $sourceoptions = ['' => get_string('selectsource', 'local_ailessonplan')];
            foreach ($knowledge['sources'] as $source) {
                $sourceid = $source['ulid'] ?? '';
                if ($sourceid === '') {
                    continue;
                }
                $sourceoptions[$sourceid] = $this->format_source_label($source);
            }

            $mform->addElement('select', 'knowledgesource', get_string('knowledgesource', 'local_ailessonplan'), $sourceoptions);
            $mform->setType('knowledgesource', PARAM_ALPHANUMEXT);
            $mform->disabledIf('knowledgesource', 'includesource', 'notchecked');
            if (count($sourceoptions) <= 1) {
                $message = '<em>' . get_string('nosources', 'local_ailessonplan') . '</em>';
                if (!empty($knowledge['error']) && debugging()) {
                    $message .= '<div class="alert alert-secondary mt-2 mb-0"><small>' . s($knowledge['error']) . '</small></div>';
                }
                $mform->addElement('static', 'nosources', '', $message);
            } else {
                $mform->addElement('static', 'sourcehint', '', '<small class="text-muted">' . get_string('sources_hint', 'local_ailessonplan') . '</small>');
            }
        }

        $mform->addElement('textarea', 'additionalinstructions', get_string('additionalinstructions', 'local_ailessonplan'), ['rows' => 4, 'cols' => 70]);
        $mform->setType('additionalinstructions', PARAM_TEXT);

        if (!empty($customdata['courseid'])) {
            $mform->addElement('hidden', 'courseid', (int)$customdata['courseid']);
            $mform->setType('courseid', PARAM_INT);
        }

        $this->add_action_buttons(true, get_string('generateplan', 'local_ailessonplan'));
    }

    /**
     * Format a knowledge source option label.
     *
     * @param array $source
     * @return string
     */
    private function format_source_label(array $source): string {
        $title = trim((string)($source['title'] ?? 'Untitled source'));
        $type = trim((string)($source['type'] ?? $source['source_type'] ?? 'source'));
        $label = $title;
        if ($type !== '') {
            $label .= ' (' . $type . ')';
        }
        return \core_text::substr($label, 0, 120);
    }
}
