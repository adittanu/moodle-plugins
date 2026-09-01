<?php
// This file is part of Moodle - http://moodle.org/

/**
 * View saved lesson plan.
 *
 * @package    local_ailessonplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_ailessonplan\plan_renderer;
use local_ailessonplan\publisher;

$id = required_param('id', PARAM_INT);
$download = optional_param('download', 0, PARAM_BOOL);
$action = optional_param('action', '', PARAM_ALPHA);

$record = $DB->get_record('local_ailessonplan', ['id' => $id], '*', IGNORE_MISSING);
if (!$record) {
    $courseid = optional_param('courseid', 0, PARAM_INT);
    if ($courseid > 0) {
        redirect(new moodle_url('/local/ailessonplan/index.php', ['courseid' => $courseid]),
            get_string('plannotfound', 'local_ailessonplan'), null, \core\output\notification::NOTIFY_ERROR);
    }
    throw new moodle_exception('plannotfound', 'local_ailessonplan');
}
$course = $DB->get_record('course', ['id' => $record->courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
require_capability('local/ailessonplan:generate', $context);

$plan = json_decode($record->planjson, true);
if (!is_array($plan)) {
    throw new moodle_exception('invalidjson', 'local_ailessonplan');
}

if ($download) {
    $filename = clean_filename($record->title . '.json');
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Activity module options shown in the editable publish preview.
 *
 * @return array<string, string>
 */
function local_ailessonplan_activity_type_options(): array {
    return [
        'label' => get_string('mod_label', 'local_ailessonplan'),
        'page' => get_string('mod_page', 'local_ailessonplan'),
        'forum' => get_string('mod_forum', 'local_ailessonplan'),
        'assign' => get_string('mod_assign', 'local_ailessonplan'),
        'quiz' => get_string('mod_quiz', 'local_ailessonplan'),
        'url' => get_string('mod_url', 'local_ailessonplan'),
        'scorm' => get_string('mod_scorm', 'local_ailessonplan'),
        'book' => get_string('mod_book', 'local_ailessonplan'),
        'choice' => get_string('mod_choice', 'local_ailessonplan'),
        'feedback' => get_string('mod_feedback', 'local_ailessonplan'),
        'glossary' => get_string('mod_glossary', 'local_ailessonplan'),
        'wiki' => get_string('mod_wiki', 'local_ailessonplan'),
    ];
}

/**
 * Build a plan from editable preview POST fields.
 *
 * @param array $sourceplan Original plan.
 * @return array Edited plan.
 */
function local_ailessonplan_plan_from_preview(array $sourceplan, ?array $selectedactivityids = null): array {
    $sourcesections = publisher::plan_sections($sourceplan);
    $sectioncount = max(optional_param('sectioncount', count($sourcesections), PARAM_INT), count($sourcesections));
    $selected = $selectedactivityids === null ? null : array_fill_keys($selectedactivityids, true);
    $sections = [];

    for ($i = 0; $i < $sectioncount; $i++) {
        if (!array_key_exists("section_title_{$i}", $_POST) && isset($sourcesections[$i])) {
            $section = $sourcesections[$i];
            $targetsection = optional_param("section_target_{$i}", 0, PARAM_INT);
            if ($targetsection > 0) {
                $section['_target_sectionnum'] = $targetsection;
            }
            $sections[] = $section;
            continue;
        }

        $week = optional_param("section_week_{$i}", $i + 1, PARAM_INT);
        if ($week < 1 || $week > $sectioncount) {
            $week = $i + 1;
        }
        $targetsection = optional_param("section_target_{$i}", 0, PARAM_INT);
        $title = trim(optional_param("section_title_{$i}", '', PARAM_TEXT));
        $summary = trim(optional_param("section_summary_{$i}", '', PARAM_RAW));
        $objectivesraw = trim(optional_param("section_objectives_{$i}", '', PARAM_RAW));
        $assessment = trim(optional_param("section_assessment_{$i}", '', PARAM_RAW));
        $activitycount = optional_param("activitycount_{$i}", 0, PARAM_INT);
        $activities = [];

        for ($j = 0; $j < $activitycount; $j++) {
            $activityid = optional_param("activity_id_{$i}_{$j}", '', PARAM_ALPHANUMEXT);
            if ($activityid === '' || ($selected !== null && empty($selected[$activityid]))) {
                continue;
            }

            $mod = optional_param("activity_mod_{$i}_{$j}", 'page', PARAM_ALPHANUMEXT);
            $activitytitle = trim(optional_param("activity_title_{$i}_{$j}", '', PARAM_TEXT));
            $purpose = trim(optional_param("activity_purpose_{$i}_{$j}", '', PARAM_TEXT));
            $body = trim(optional_param("activity_body_{$i}_{$j}", '', PARAM_RAW));
            if ($activitytitle === '' && $body === '') {
                continue;
            }

            $activity = [
                '_preview_id' => $activityid,
                'mod' => $mod,
                'title' => $activitytitle !== '' ? $activitytitle : ucfirst($mod),
                'purpose' => $purpose,
            ];

            switch ($mod) {
                case 'assign':
                    $activity['instruction'] = $body;
                    $activity['submission_type'] = 'online text';
                    break;
                case 'forum':
                    $activity['prompt'] = $body;
                    break;
                case 'quiz':
                    $activity['intro'] = $body;
                    $activity['quizgen_hint'] = $activitytitle;
                    break;
                case 'url':
                    $activity['description'] = $body;
                    if (preg_match('/https?:\/\/\S+/i', $body, $matches)) {
                        $activity['external_url'] = $matches[0];
                    }
                    break;
                case 'label':
                    $activity['text'] = $body;
                    break;
                case 'scorm':
                    $activity['learning_goal'] = $body;
                    $activity['placeholder_reason'] = get_string('scormplaceholder', 'local_ailessonplan');
                    break;
                case 'page':
                case 'book':
                default:
                    $activity['student_instruction'] = $body;
                    $activity['content_outline'] = array_values(array_filter(array_map('trim', preg_split('/\R/u', $body))));
                    break;
            }

            $activities[] = $activity;
        }

        $sections[] = [
            'week' => $week,
            '_target_sectionnum' => $targetsection > 0 ? $targetsection : null,
            'title' => $title !== '' ? $title : get_string('week', 'local_ailessonplan') . ' ' . $week,
            'summary' => $summary,
            'objectives' => array_values(array_filter(array_map('trim', preg_split('/\R/u', $objectivesraw)))),
            'activities' => $activities,
            'assessment_summary' => $assessment,
        ];
    }

    $sourceplan['sections'] = $sections;
    $sourceplan['course_title'] = (string)($sourceplan['course_title'] ?? $sourceplan['title'] ?? get_string('pluginname', 'local_ailessonplan'));
    $sourceplan['title'] = $sourceplan['course_title'];
    return $sourceplan;
}

/**
 * Check whether the confirm publish form posted every section editor.
 *
 * @param array $sourceplan Original plan.
 * @return bool
 */
function local_ailessonplan_preview_post_is_complete(array $sourceplan): bool {
    $sectioncount = max(optional_param('sectioncount', 0, PARAM_INT), count(publisher::plan_sections($sourceplan)));
    for ($i = 0; $i < $sectioncount; $i++) {
        if (!array_key_exists("section_title_{$i}", $_POST)) {
            return false;
        }
    }
    return true;
}

/**
 * Compact activity body for editor textarea.
 *
 * @param array $activity
 * @return string
 */
function local_ailessonplan_activity_body(array $activity): string {
    foreach (['student_instruction', 'instruction', 'prompt', 'intro', 'text', 'description', 'learning_goal', 'placeholder_reason'] as $key) {
        if (!empty($activity[$key]) && is_scalar($activity[$key])) {
            return (string)$activity[$key];
        }
    }
    foreach (['content_outline', 'chapters', 'suggested_terms', 'questions', 'options'] as $key) {
        if (!empty($activity[$key]) && is_array($activity[$key])) {
            return implode("\n", array_map(function($item) {
                return is_scalar($item) ? (string)$item : json_encode($item, JSON_UNESCAPED_UNICODE);
            }, $activity[$key]));
        }
    }
    return '';
}

/**
 * Render one editable section in focus preview.
 *
 * @param array $section
 * @param array $change
 * @param int $index
 * @return string
 */
function local_ailessonplan_render_section_editor(array $section, array $change, int $index): string {
    $types = local_ailessonplan_activity_type_options();
    $activities = array_values((array)($section['activities'] ?? []));
    $activitycount = count($activities) + 1;
    $html = html_writer::start_div('border rounded p-3 mb-3 local-ailessonplan-section-editor', ['data-section-index' => $index]);
    $html .= html_writer::tag('h5', get_string('week', 'local_ailessonplan') . ' ' . ($index + 1));
    $html .= html_writer::tag('p', get_string('sectionpreviewtarget', 'local_ailessonplan', s($change['currentname'])), ['class' => 'text-muted mb-2']);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => "section_week_{$index}", 'value' => (int)($section['week'] ?? ($index + 1))]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => "section_target_{$index}", 'value' => (int)($change['sectionnum'] ?? 0)]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => "activitycount_{$index}", 'value' => $activitycount]);
    $html .= html_writer::tag('label', get_string('sectiontitle', 'local_ailessonplan'));
    $html .= html_writer::empty_tag('input', ['type' => 'text', 'name' => "section_title_{$index}", 'value' => (string)($section['title'] ?? ''), 'class' => 'form-control mb-2']);
    $html .= html_writer::tag('label', get_string('sectionsummary', 'local_ailessonplan'));
    $html .= html_writer::tag('textarea', s((string)($section['summary'] ?? '')), ['name' => "section_summary_{$index}", 'class' => 'form-control mb-2', 'rows' => 3]);
    $html .= html_writer::tag('label', get_string('objectives', 'local_ailessonplan'));
    $html .= html_writer::tag('textarea', s(implode("\n", array_map('strval', (array)($section['objectives'] ?? [])))), ['name' => "section_objectives_{$index}", 'class' => 'form-control mb-2', 'rows' => 3]);
    $html .= html_writer::tag('label', get_string('assessment', 'local_ailessonplan'));
    $html .= html_writer::tag('textarea', s((string)($section['assessment_summary'] ?? '')), ['name' => "section_assessment_{$index}", 'class' => 'form-control mb-3', 'rows' => 2]);

    $html .= html_writer::tag('h6', get_string('activities', 'local_ailessonplan'));
    foreach ($activities as $activityindex => $activity) {
        if (!is_array($activity)) {
            continue;
        }
        $activityid = $change['activities'][$activityindex]['id'] ?? ('week-' . ($index + 1) . '-' . ($activityindex + 1));
        $html .= local_ailessonplan_render_activity_editor($types, $activity, $activityid, $index, $activityindex, true);
    }

    $newid = 'new-week-' . ($index + 1);
    $html .= local_ailessonplan_render_activity_editor($types, ['mod' => 'page', 'title' => '', 'purpose' => '', 'student_instruction' => ''], $newid, $index, count($activities), false);
    $html .= html_writer::end_div();
    return $html;
}

/**
 * Render an editable activity row.
 *
 * @param array $types
 * @param array $activity
 * @param string $activityid
 * @param int $sectionindex
 * @param int $activityindex
 * @param bool $checked
 * @return string
 */
function local_ailessonplan_render_activity_editor(array $types, array $activity, string $activityid, int $sectionindex, int $activityindex, bool $checked): string {
    $mod = (string)($activity['mod'] ?? 'page');
    $html = html_writer::start_div('border rounded p-2 mb-2 bg-light local-ailessonplan-activity-editor', ['data-activity-index' => $activityindex]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => "activity_id_{$sectionindex}_{$activityindex}", 'value' => $activityid]);
    $html .= html_writer::start_div('row');
    $html .= html_writer::start_div('col-md-1');
    $attrs = ['type' => 'checkbox', 'name' => 'selectedactivities[]', 'value' => $activityid];
    if ($checked) {
        $attrs['checked'] = 'checked';
    }
    $html .= html_writer::tag('label', html_writer::empty_tag('input', $attrs) . ' ' . get_string('publishactivity', 'local_ailessonplan'));
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('col-md-2');
    $html .= html_writer::tag('label', get_string('activitytype', 'local_ailessonplan'));
    $html .= html_writer::select($types, "activity_mod_{$sectionindex}_{$activityindex}", $mod, false, ['class' => 'form-control']);
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('col-md-4');
    $html .= html_writer::tag('label', get_string('activitytitle', 'local_ailessonplan'));
    $html .= html_writer::empty_tag('input', ['type' => 'text', 'name' => "activity_title_{$sectionindex}_{$activityindex}", 'value' => (string)($activity['title'] ?? ''), 'class' => 'form-control']);
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('col-md-5');
    $html .= html_writer::tag('label', get_string('purpose', 'local_ailessonplan'));
    $html .= html_writer::empty_tag('input', ['type' => 'text', 'name' => "activity_purpose_{$sectionindex}_{$activityindex}", 'value' => (string)($activity['purpose'] ?? ''), 'class' => 'form-control']);
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::tag('label', get_string('activitybody', 'local_ailessonplan'), ['class' => 'mt-2']);
    $html .= html_writer::tag('textarea', s(local_ailessonplan_activity_body($activity)), ['name' => "activity_body_{$sectionindex}_{$activityindex}", 'class' => 'form-control', 'rows' => 3]);
    $html .= html_writer::end_div();
    return $html;
}

$previewpublish = false;
$sectionchanges = [];
$defaultplacement = (!empty($record->publishedcmid) || !empty($record->publishedat)) ? 'update' : 'append';
$placement = optional_param('publishplacement', $defaultplacement, PARAM_ALPHA);
$startsection = optional_param('startsection', 1, PARAM_INT);

if ($action === 'previewpublish' && confirm_sesskey()) {
    require_capability('moodle/course:manageactivities', $context);
    $previewpublish = true;
    $sectionchanges = publisher::preview_section_changes($course, $plan, $placement, $startsection);
} else if ($action === 'publish' && confirm_sesskey()) {
    require_capability('moodle/course:manageactivities', $context);

    $selectedactivities = local_ailessonplan_preview_post_is_complete($plan)
        ? optional_param_array('selectedactivities', [], PARAM_ALPHANUMEXT)
        : null;
    $editedplan = local_ailessonplan_plan_from_preview($plan, $selectedactivities);
    $result = publisher::publish($record, $course, $editedplan, true, $selectedactivities, $placement, $startsection);

    $record->publishedcmid = $result['cmid'];
    if (local_ailessonplan_preview_post_is_complete($plan)) {
        $record->planjson = json_encode(local_ailessonplan_plan_from_preview($plan), JSON_UNESCAPED_UNICODE);
    }
    $record->publishedat = time();
    $record->timemodified = time();
    $DB->update_record('local_ailessonplan', $record);

    $message = get_string('publishsuccess', 'local_ailessonplan');
    if (!empty($result['sectionsupdated'])) {
        $message .= ' ' . get_string('sectionsupdated', 'local_ailessonplan', $result['sectionsupdated']);
    }
    if (!empty($result['activitiescreated']) || !empty($result['activitiesupdated'])) {
        $activitycounts = (object)[
            'created' => (int)($result['activitiescreated'] ?? 0),
            'updated' => (int)($result['activitiesupdated'] ?? 0),
        ];
        $message .= ' ' . get_string('activitiespublished', 'local_ailessonplan', $activitycounts);
    }
    redirect(new moodle_url('/local/ailessonplan/view.php', ['id' => $id]), $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->set_url('/local/ailessonplan/view.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(format_string($record->title));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
$buttons = $OUTPUT->single_button(new moodle_url('/local/ailessonplan/index.php', ['courseid' => $course->id]), get_string('backtolist', 'local_ailessonplan'), 'get') .
    $OUTPUT->single_button(new moodle_url('/local/ailessonplan/generate.php', ['courseid' => $course->id]), get_string('generateplan', 'local_ailessonplan'), 'get') .
    $OUTPUT->single_button(new moodle_url('/local/ailessonplan/view.php', ['id' => $id, 'download' => 1]), get_string('downloadjson', 'local_ailessonplan'), 'get');

echo html_writer::div($buttons, 'mb-3');

if (!empty($record->publishedcmid)) {
    $status = get_string('publishedstatus', 'local_ailessonplan', userdate((int)($record->publishedat ?? $record->timemodified)));
    echo $OUTPUT->notification($status, \core\output\notification::NOTIFY_INFO);
}

if ($previewpublish) {
    $sections = publisher::plan_sections($plan);
    echo html_writer::start_div('card card-body mb-3 border-primary');
    echo html_writer::tag('h4', get_string('reviewcourseskeleton', 'local_ailessonplan'));
    echo html_writer::tag('p', get_string('editablepreview_desc', 'local_ailessonplan'), ['class' => 'text-muted']);
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new moodle_url('/local/ailessonplan/view.php', ['id' => $id]))->out(false),
        'class' => 'mt-2 local-ailessonplan-publish-form',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'publish']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sectioncount', 'value' => count($sections)]);

    echo html_writer::start_div('border rounded p-3 mb-3');
    echo html_writer::tag('h5', get_string('publishplacement', 'local_ailessonplan'));
    $placementoptions = [
        'append' => get_string('placement_append', 'local_ailessonplan'),
        'update' => get_string('placement_update', 'local_ailessonplan'),
        'custom' => get_string('placement_custom', 'local_ailessonplan'),
    ];
    foreach ($placementoptions as $value => $label) {
        $attrs = ['type' => 'radio', 'name' => 'publishplacement', 'value' => $value];
        if ($placement === $value) {
            $attrs['checked'] = 'checked';
        }
        echo html_writer::tag('label', html_writer::empty_tag('input', $attrs) . ' ' . $label, ['class' => 'd-block']);
    }
    echo html_writer::tag('label', get_string('startsection', 'local_ailessonplan'), ['class' => 'mt-2']);
    echo html_writer::empty_tag('input', ['type' => 'number', 'name' => 'startsection', 'value' => max(1, $startsection), 'min' => 1, 'class' => 'form-control', 'style' => 'max-width:180px']);
    echo html_writer::tag('div', get_string('startsection_help', 'local_ailessonplan'), ['class' => 'text-muted small mt-1']);
    echo html_writer::end_div();

    if (empty($sectionchanges)) {
        echo $OUTPUT->notification(get_string('nosectionchanges', 'local_ailessonplan'), \core\output\notification::NOTIFY_INFO);
    } else {
        foreach ($sectionchanges as $index => $change) {
            echo local_ailessonplan_render_section_editor($sections[$index] ?? [], $change, $index);
        }
    }

    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'id' => 'local-ailessonplan-confirm-publish',
        'class' => 'btn btn-primary',
        'value' => get_string('confirmpublish', 'local_ailessonplan'),
    ]);
    echo ' ' . html_writer::link(new moodle_url('/local/ailessonplan/view.php', ['id' => $id]), get_string('cancel', 'core'), ['class' => 'btn btn-secondary']);
    echo html_writer::div('', 'mt-3 local-ailessonplan-publish-progress', ['id' => 'local-ailessonplan-publish-progress']);
    echo html_writer::end_tag('form');
    $publishurl = json_encode((new moodle_url('/local/ailessonplan/publish_section.php', ['id' => $id]))->out(false));
    $reloadurl = json_encode((new moodle_url('/local/ailessonplan/view.php', ['id' => $id]))->out(false));
    $js = <<<JS
(function() {
    var form = document.querySelector('.local-ailessonplan-publish-form');
    if (!form || !window.fetch) {
        return;
    }

    var publishUrl = {$publishurl};
    var reloadUrl = {$reloadurl};
    var button = document.getElementById('local-ailessonplan-confirm-publish');
    var progress = document.getElementById('local-ailessonplan-publish-progress');

    function field(name) {
        var input = form.querySelector('[name="' + name + '"]');
        return input ? input.value : '';
    }

    function lines(text) {
        return String(text || '').split(/\\r?\\n/).map(function(line) {
            return line.trim();
        }).filter(Boolean);
    }

    function activityFromFields(sectionIndex, activityIndex) {
        var id = field('activity_id_' + sectionIndex + '_' + activityIndex);
        var mod = field('activity_mod_' + sectionIndex + '_' + activityIndex) || 'page';
        var title = field('activity_title_' + sectionIndex + '_' + activityIndex).trim();
        var purpose = field('activity_purpose_' + sectionIndex + '_' + activityIndex).trim();
        var body = field('activity_body_' + sectionIndex + '_' + activityIndex).trim();
        if (!id || (!title && !body)) {
            return null;
        }

        var activity = {
            _preview_id: id,
            mod: mod,
            title: title || (mod.charAt(0).toUpperCase() + mod.slice(1)),
            purpose: purpose
        };

        if (mod === 'assign') {
            activity.instruction = body;
            activity.submission_type = 'online text';
        } else if (mod === 'forum') {
            activity.prompt = body;
        } else if (mod === 'quiz') {
            activity.intro = body;
            activity.quizgen_hint = title;
        } else if (mod === 'url') {
            activity.description = body;
            var match = body.match(/https?:\\/\\/\\S+/i);
            if (match) {
                activity.external_url = match[0];
            }
        } else if (mod === 'label') {
            activity.text = body;
        } else if (mod === 'scorm') {
            activity.learning_goal = body;
            activity.placeholder_reason = body;
        } else {
            activity.student_instruction = body;
            activity.content_outline = lines(body);
        }

        return activity;
    }

    function sectionFromEditor(sectionEl) {
        var sectionIndex = parseInt(sectionEl.getAttribute('data-section-index'), 10);
        var count = parseInt(field('activitycount_' + sectionIndex), 10) || 0;
        var activities = [];
        for (var i = 0; i < count; i++) {
            var activity = activityFromFields(sectionIndex, i);
            if (activity) {
                activities.push(activity);
            }
        }

        return {
            week: parseInt(field('section_week_' + sectionIndex), 10) || (sectionIndex + 1),
            _target_sectionnum: parseInt(field('section_target_' + sectionIndex), 10) || 0,
            title: field('section_title_' + sectionIndex).trim(),
            summary: field('section_summary_' + sectionIndex).trim(),
            objectives: lines(field('section_objectives_' + sectionIndex)),
            activities: activities,
            assessment_summary: field('section_assessment_' + sectionIndex).trim()
        };
    }

    function checkedActivityIds(sectionEl) {
        return Array.prototype.slice.call(sectionEl.querySelectorAll('input[name="selectedactivities[]"]:checked')).map(function(input) {
            return input.value;
        });
    }

    function setStatus(index, text, cssClass) {
        var row = progress.querySelector('[data-section-status="' + index + '"]');
        if (!row) {
            return;
        }
        row.className = cssClass || '';
        row.textContent = text;
    }

    async function publishSection(sectionEl, index) {
        var section = sectionFromEditor(sectionEl);
        if (!section._target_sectionnum) {
            throw new Error('Missing target section for Week ' + (index + 1));
        }

        var formData = new FormData();
        formData.append('sesskey', field('sesskey'));
        formData.append('sectionindex', String(index));
        formData.append('targetsection', String(section._target_sectionnum));
        formData.append('sectionjson', JSON.stringify(section));
        formData.append('selectionsent', '1');
        checkedActivityIds(sectionEl).forEach(function(id) {
            formData.append('selectedactivities[]', id);
        });

        var response = await fetch(publishUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });
        var text = await response.text();
        var payload;
        try {
            payload = JSON.parse(text);
        } catch (e) {
            throw new Error(text || 'Invalid server response');
        }
        if (!response.ok || !payload.success) {
            throw new Error(payload.error || 'Publish failed');
        }
        return payload;
    }

    form.addEventListener('submit', async function(event) {
        event.preventDefault();
        var sections = Array.prototype.slice.call(form.querySelectorAll('.local-ailessonplan-section-editor'));
        if (!sections.length) {
            form.submit();
            return;
        }

        button.disabled = true;
        button.value = 'Publishing...';
        progress.innerHTML = sections.map(function(sectionEl, index) {
            return '<div data-section-status="' + index + '" class="text-muted">Week ' + (index + 1) + ': waiting</div>';
        }).join('');

        try {
            for (var i = 0; i < sections.length; i++) {
                setStatus(i, 'Week ' + (i + 1) + ': publishing...', 'text-info');
                var result = await publishSection(sections[i], i);
                var counts = result.result || {};
                setStatus(
                    i,
                    'Week ' + (i + 1) + ': saved to section ' + result.targetsection +
                        ' (' + (counts.activitiescreated || 0) + ' created, ' + (counts.activitiesupdated || 0) + ' updated)',
                    'text-success'
                );
            }
            button.value = 'Published';
            setTimeout(function() {
                window.location.href = reloadUrl;
            }, 1000);
        } catch (error) {
            button.disabled = false;
            button.value = 'Retry publish';
            setStatus(
                Math.max(0, progress.querySelectorAll('.text-success').length),
                'Publish stopped: ' + error.message,
                'text-danger'
            );
        }
    });
})();
JS;
    echo html_writer::script($js);
    echo html_writer::end_div();
}

if (!$previewpublish && has_capability('moodle/course:manageactivities', $context)) {
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new moodle_url('/local/ailessonplan/view.php', ['id' => $id]))->out(false),
        'class' => 'card card-body mb-3',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'previewpublish']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'publishplacement', 'value' => $defaultplacement]);
    echo html_writer::tag('h4', get_string('publishtomoodle', 'local_ailessonplan'));
    echo html_writer::tag('p', get_string('publishtomoodle_desc', 'local_ailessonplan'), ['class' => 'text-muted']);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary', 'value' => get_string('previewpublishbutton', 'local_ailessonplan')]);
    echo html_writer::end_tag('form');
}

if (!$previewpublish) {
    echo plan_renderer::render_plan($plan);
}
echo $OUTPUT->footer();
