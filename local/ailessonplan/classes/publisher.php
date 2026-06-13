<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ailessonplan;

/**
 * Publishes generated course skeletons into native Moodle course content.
 *
 * @package    local_ailessonplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Publisher for turning saved AI course skeleton drafts into Moodle sections and activities.
 */
class publisher {

    /** @var string Start marker for AI-managed section summary block. */
    private const SECTION_MARKER_START = '<!-- local_ailessonplan:start -->';

    /** @var string End marker for AI-managed section summary block. */
    private const SECTION_MARKER_END = '<!-- local_ailessonplan:end -->';

    /** @var string Activity marker prefix. */
    private const ACTIVITY_MARKER_PREFIX = '<!-- local_ailessonplan:activity=';

    /** @var array<string, string> Modules that are created as another safe module in v1. */
    private const FALLBACK_MODULES = [
        'book' => 'page',
        'choice' => 'page',
        'feedback' => 'page',
        'glossary' => 'page',
        'scorm' => 'label',
        'wiki' => 'page',
    ];

    /** @var array<int, string> Modules with real v1 skeleton creation support. */
    private const REAL_MODULES = ['assign', 'forum', 'label', 'page', 'quiz', 'url'];

    /**
     * Publish a saved course skeleton into Moodle sections and activity placeholders.
     *
     * @param \stdClass $record Saved local_ailessonplan record.
     * @param \stdClass $course Course record.
     * @param array $plan Decoded plan JSON.
     * @param bool $updatesections Kept for backwards-compatible call sites; ignored.
     * @param array|null $selectedactivityids Activity IDs selected in preview. Null means all.
     * @param string $placement append, update, or custom.
     * @param int $startsection Custom start section when placement is custom.
     * @return array{cmid:int, pageid:int|null, sectionsupdated:int, activitiescreated:int, activitiesupdated:int}
     */
    public static function publish(
        \stdClass $record,
        \stdClass $course,
        array $plan,
        bool $updatesections = true,
        ?array $selectedactivityids = null,
        string $placement = 'append',
        int $startsection = 1
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->libdir . '/resourcelib.php');

        $sections = self::plan_sections($plan);
        if (empty($sections)) {
            return ['cmid' => 0, 'pageid' => null, 'sectionsupdated' => 0, 'activitiescreated' => 0, 'activitiesupdated' => 0];
        }

        $sectionnums = self::resolve_section_numbers_for_sections($course, $sections, $placement, $startsection);
        course_create_sections_if_missing($course, $sectionnums);
        get_fast_modinfo($course, 0, true);

        $sectionsupdated = 0;
        $activitiescreated = 0;
        $activitiesupdated = 0;
        $firstcmid = 0;
        $selected = $selectedactivityids === null ? null : array_fill_keys(array_map('strval', $selectedactivityids), true);

        foreach ($sections as $index => $sectiondata) {
            $sectionnum = $sectionnums[$index] ?? ($index + 1);
            $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectionnum], '*', MUST_EXIST);
            $title = self::section_name($sectionnum, $sectiondata);
            $summary = self::merge_section_summary((string)($section->summary ?? ''), self::render_section_ai_block($sectionnum, $sectiondata));

            $update = [
                'summary' => $summary,
                'summaryformat' => FORMAT_HTML,
                'name' => $title,
            ];
            if (trim((string)($section->summary ?? '')) !== trim($summary) || trim((string)($section->name ?? '')) !== trim($title)) {
                course_update_section($course, $section, $update);
                $sectionsupdated++;
            }

            foreach (self::section_activities($sectiondata) as $activityindex => $activity) {
                $activityid = self::activity_id($sectionnum, $activityindex, $activity);
                if ($selected !== null && empty($selected[$activityid])) {
                    continue;
                }
                $result = self::create_or_update_activity($course, $sectionnum, $activityid, $activity);
                if (!empty($result['cmid']) && $firstcmid === 0) {
                    $firstcmid = (int)$result['cmid'];
                }
                if (($result['status'] ?? '') === 'created') {
                    $activitiescreated++;
                } else if (($result['status'] ?? '') === 'updated') {
                    $activitiesupdated++;
                }
            }
        }

        rebuild_course_cache((int)$course->id, true);

        return [
            'cmid' => $firstcmid,
            'pageid' => null,
            'sectionsupdated' => $sectionsupdated,
            'activitiescreated' => $activitiescreated,
            'activitiesupdated' => $activitiesupdated,
        ];
    }

    /**
     * Publish one section from the editable preview.
     *
     * @param \stdClass $record Saved local_ailessonplan record.
     * @param \stdClass $course Course record.
     * @param array $sectiondata Edited section data.
     * @param int $sectionnum Target Moodle section number.
     * @param array|null $selectedactivityids Activity IDs selected for publishing. Null means all.
     * @return array{cmid:int, sectionsupdated:int, activitiescreated:int, activitiesupdated:int}
     */
    public static function publish_single_section(
        \stdClass $record,
        \stdClass $course,
        array $sectiondata,
        int $sectionnum,
        ?array $selectedactivityids = null
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->libdir . '/resourcelib.php');

        $week = (int)($sectiondata['week'] ?? 1);
        $sectiondata = self::normalize_section($sectiondata, max(0, $week - 1), max(1, $week));
        $sectiondata['_target_sectionnum'] = $sectionnum;

        course_create_sections_if_missing($course, [$sectionnum]);
        get_fast_modinfo($course, 0, true);

        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectionnum], '*', MUST_EXIST);
        $title = self::section_name($sectionnum, $sectiondata);
        $summary = self::merge_section_summary((string)($section->summary ?? ''), self::render_section_ai_block($sectionnum, $sectiondata));
        $sectionsupdated = 0;
        $activitiescreated = 0;
        $activitiesupdated = 0;
        $firstcmid = 0;
        $selected = $selectedactivityids === null ? null : array_fill_keys(array_map('strval', $selectedactivityids), true);

        $update = [
            'summary' => $summary,
            'summaryformat' => FORMAT_HTML,
            'name' => $title,
        ];
        if (trim((string)($section->summary ?? '')) !== trim($summary) || trim((string)($section->name ?? '')) !== trim($title)) {
            course_update_section($course, $section, $update);
            $sectionsupdated++;
        }

        foreach (self::section_activities($sectiondata) as $activityindex => $activity) {
            $activityid = self::activity_id($sectionnum, $activityindex, $activity);
            if ($selected !== null && empty($selected[$activityid])) {
                continue;
            }
            $result = self::create_or_update_activity($course, $sectionnum, $activityid, $activity);
            if (!empty($result['cmid']) && $firstcmid === 0) {
                $firstcmid = (int)$result['cmid'];
            }
            if (($result['status'] ?? '') === 'created') {
                $activitiescreated++;
            } else if (($result['status'] ?? '') === 'updated') {
                $activitiesupdated++;
            }
        }

        rebuild_course_cache((int)$course->id, true);

        return [
            'cmid' => $firstcmid,
            'sectionsupdated' => $sectionsupdated,
            'activitiescreated' => $activitiescreated,
            'activitiesupdated' => $activitiesupdated,
        ];
    }

    /**
     * Preview section and activity changes without writing to Moodle.
     *
     * @param \stdClass $course Course record.
     * @param array $plan Decoded plan JSON.
     * @return array<int, array<string, mixed>>
     */
    public static function preview_section_changes(\stdClass $course, array $plan, string $placement = 'append', int $startsection = 1): array {
        global $CFG;

        require_once($CFG->dirroot . '/course/lib.php');

        $sections = self::plan_sections($plan);
        if (empty($sections)) {
            return [];
        }

        $modinfo = get_fast_modinfo($course);
        $existingsections = $modinfo->get_section_info_all();
        $sectionnums = self::resolve_section_numbers_for_sections($course, $sections, $placement, $startsection);
        $changes = [];

        foreach ($sections as $index => $sectiondata) {
            $sectionnum = $sectionnums[$index] ?? ($index + 1);
            $section = $existingsections[$sectionnum] ?? null;
            $currentsummary = $section ? (string)($section->summary ?? '') : '';
            $currentname = $section ? get_section_name($course, $section) : get_string('week', 'local_ailessonplan') . ' ' . $sectionnum;
            $aiblock = self::render_section_ai_block($sectionnum, $sectiondata);
            $activitychanges = [];

            foreach (self::section_activities($sectiondata) as $activityindex => $activity) {
                $activityid = self::activity_id($sectionnum, $activityindex, $activity);
                $targetmod = self::target_module($activity);
                $existing = self::find_existing_activity($course, $activityid);
                $activitychanges[] = [
                    'id' => $activityid,
                    'requestedmod' => self::requested_module($activity),
                    'targetmod' => $targetmod,
                    'title' => self::activity_title($activity),
                    'purpose' => (string)($activity['purpose'] ?? ''),
                    'status' => $existing ? 'update' : 'create',
                    'placeholder' => self::is_placeholder_activity($activity),
                    'preview' => self::activity_intro_html($activity, $activityid),
                ];
            }

            $changes[] = [
                'sectionnum' => $sectionnum,
                'weeknum' => $index + 1,
                'exists' => !empty($section),
                'currentname' => $currentname,
                'aititle' => self::section_name($sectionnum, $sectiondata),
                'current_summary' => $currentsummary,
                'proposed_summary' => self::merge_section_summary($currentsummary, $aiblock),
                'ai_block' => $aiblock,
                'action' => $section ? 'update_ai_block' : 'create',
                'changed' => trim($currentsummary) !== trim(self::merge_section_summary($currentsummary, $aiblock)),
                'activities' => $activitychanges,
            ];
        }

        return $changes;
    }

    /**
     * Normalize a new skeleton or legacy meeting plan into course sections.
     *
     * @param array $plan
     * @return array<int, array<string, mixed>>
     */
    public static function plan_sections(array $plan): array {
        if (!empty($plan['sections']) && is_array($plan['sections'])) {
            $rawsections = array_values(array_filter($plan['sections'], 'is_array'));
            $sectioncount = count($rawsections);
            $sections = [];
            foreach ($rawsections as $index => $section) {
                $sections[] = self::normalize_section($section, $index, $sectioncount);
            }
            return $sections;
        }

        $sections = [];
        $meetings = array_values((array)($plan['meetings'] ?? []));
        $meetingcount = count($meetings);
        foreach ($meetings as $index => $meeting) {
            if (!is_array($meeting)) {
                continue;
            }
            $topic = (string)($meeting['topic'] ?? get_string('week', 'local_ailessonplan') . ' ' . ($index + 1));
            $activities = [];
            if (!empty($meeting['materials'])) {
                $activities[] = [
                    'mod' => 'page',
                    'title' => get_string('materialactivityprefix', 'local_ailessonplan') . ': ' . $topic,
                    'purpose' => 'content',
                    'content_outline' => (array)$meeting['materials'],
                    'student_instruction' => get_string('materialactivityinstruction', 'local_ailessonplan'),
                ];
            }
            if (!empty($meeting['activities'])) {
                $activities[] = [
                    'mod' => 'forum',
                    'title' => get_string('discussionactivityprefix', 'local_ailessonplan') . ': ' . $topic,
                    'purpose' => 'discussion',
                    'prompt' => implode("\n", array_map('strval', (array)$meeting['activities'])),
                ];
            }
            if (!empty($meeting['assessment'])) {
                $activities[] = [
                    'mod' => 'assign',
                    'title' => get_string('assignmentactivityprefix', 'local_ailessonplan') . ': ' . $topic,
                    'purpose' => 'assessment',
                    'instruction' => (string)$meeting['assessment'],
                ];
            }
            $sections[] = [
                'week' => (int)($meeting['week'] ?? $meeting['meeting'] ?? ($index + 1)),
                'title' => $topic,
                'summary' => '',
                'objectives' => array_values((array)($meeting['objectives'] ?? [])),
                'activities' => $activities,
                'assessment_summary' => (string)($meeting['assessment'] ?? ''),
            ];
            $sections[count($sections) - 1] = self::normalize_section($sections[count($sections) - 1], $index, $meetingcount);
        }

        return $sections;
    }

    /**
     * Normalize one generated section so publish uses lesson order, not Moodle section numbers.
     *
     * @param array $section
     * @param int $index
     * @param int $sectioncount
     * @return array<string, mixed>
     */
    private static function normalize_section(array $section, int $index, int $sectioncount): array {
        $week = (int)($section['week'] ?? $section['meeting'] ?? 0);
        if ($week < 1 || ($sectioncount > 0 && $week > $sectioncount)) {
            $week = $index + 1;
        }

        $title = trim((string)($section['title'] ?? $section['topic'] ?? $section['name'] ?? ''));
        if ($title === '') {
            $title = get_string('week', 'local_ailessonplan') . ' ' . $week;
        }

        $objectives = array_values(array_filter((array)($section['objectives'] ?? []), 'is_scalar'));
        $activities = array_values(array_filter((array)($section['activities'] ?? []), 'is_array'));

        return [
            'week' => $week,
            '_target_sectionnum' => (int)($section['_target_sectionnum'] ?? 0),
            'title' => $title,
            'summary' => trim((string)($section['summary'] ?? $section['description'] ?? '')),
            'objectives' => $objectives,
            'activities' => $activities,
            'assessment_summary' => trim((string)($section['assessment_summary'] ?? $section['assessment'] ?? '')),
        ];
    }

    /**
     * Resolve target Moodle course section numbers.
     *
     * Existing AI-managed sections are reused first to keep republish stable.
     *
     * @param \stdClass $course
     * @param int $count
     * @param string $placement append, update, or custom.
     * @param int $startsection
     * @return array<int, int>
     */
    private static function resolve_section_numbers(\stdClass $course, int $count, string $placement, int $startsection): array {
        global $DB;

        $sections = $DB->get_records('course_sections', ['course' => $course->id], 'section ASC', 'id, section, summary');
        $aimanged = [];
        $maxsection = 0;

        foreach ($sections as $section) {
            $sectionnum = (int)$section->section;
            if ($sectionnum > $maxsection) {
                $maxsection = $sectionnum;
            }
            if ($sectionnum > 0 && self::has_ai_section_block((string)($section->summary ?? ''))) {
                $aimanged[] = $sectionnum;
            }
        }

        sort($aimanged);
        if ($placement === 'update' && !empty($aimanged)) {
            $start = min($aimanged);
            return range($start, $start + $count - 1);
        }

        if ($placement === 'update') {
            $start = 1;
        } else if ($placement === 'custom') {
            $start = max(1, $startsection);
        } else {
            $start = max(1, $maxsection + 1);
        }

        return range($start, $start + $count - 1);
    }

    /**
     * Resolve target section numbers, preserving preview-confirm section mapping when present.
     *
     * @param \stdClass $course
     * @param array<int, array<string, mixed>> $sections
     * @param string $placement
     * @param int $startsection
     * @return array<int, int>
     */
    private static function resolve_section_numbers_for_sections(\stdClass $course, array $sections, string $placement, int $startsection): array {
        $sectionnums = [];
        foreach ($sections as $section) {
            $target = (int)($section['_target_sectionnum'] ?? 0);
            if ($target <= 0) {
                return self::resolve_section_numbers($course, count($sections), $placement, $startsection);
            }
            $sectionnums[] = $target;
        }

        return $sectionnums;
    }

    /**
     * Get activities for a section.
     *
     * @param array $section
     * @return array<int, array<string, mixed>>
     */
    private static function section_activities(array $section): array {
        return array_values(array_filter((array)($section['activities'] ?? []), 'is_array'));
    }

    /**
     * Create or update a single AI-managed activity.
     *
     * @param \stdClass $course
     * @param int $sectionnum
     * @param string $activityid
     * @param array $activity
     * @return array{status:string, cmid:int}
     */
    private static function create_or_update_activity(\stdClass $course, int $sectionnum, string $activityid, array $activity): array {
        global $DB;

        $existing = self::find_existing_activity($course, $activityid);
        if ($existing) {
            self::update_activity_record($existing, $activity, $activityid);
            self::move_existing_activity_to_section($course, (int)$existing['cmid'], $sectionnum);
            return ['status' => 'updated', 'cmid' => (int)$existing['cmid']];
        }

        try {
            $created = self::create_activity($course, $sectionnum, $activity, $activityid);
            self::move_existing_activity_to_section($course, (int)$created->coursemodule, $sectionnum);
            return ['status' => 'created', 'cmid' => (int)$created->coursemodule];
        } catch (\Throwable $e) {
            // Do NOT call add_moduleinfo() again here — it starts a delegated
            // transaction that leaks when the first one was not cleaned up.
            // Instead, create the fallback label via direct DB inserts.
            try {
                $cmid = self::create_label_fallback($course, $sectionnum, $activity, $activityid);
                return ['status' => 'created', 'cmid' => $cmid];
            } catch (\Throwable $e2) {
                return ['status' => 'error', 'cmid' => 0];
            }
        }
    }
    /**
     * Create a fallback label activity via direct DB inserts.
     *
     * This avoids add_moduleinfo() which opens a delegated transaction that
     * leaks when called inside a catch block after a previous failure.
     *
     * @param \stdClass $course
     * @param int $sectionnum
     * @param array $activity Original activity data
     * @param string $activityid AI marker ID
     * @return int The new course module ID
     */
    private static function create_label_fallback(\stdClass $course, int $sectionnum, array $activity, string $activityid): int {
        global $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $title = self::activity_title($activity);
        $intro = self::activity_intro_html($activity, $activityid);

        // Get the label module ID.
        $labelmodule = $DB->get_record('modules', ['name' => 'label'], 'id', MUST_EXIST);

        // Insert the label instance.
        $label = new \stdClass();
        $label->course = $course->id;
        $label->name = $title;
        $label->intro = $intro;
        $label->introformat = FORMAT_HTML;
        $label->timemodified = time();
        $label->id = $DB->insert_record('label', $label);

        // Ensure section exists.
        course_create_sections_if_missing($course, [$sectionnum]);
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectionnum], '*', MUST_EXIST);

        // Insert the course module.
        $cm = new \stdClass();
        $cm->course = $course->id;
        $cm->module = $labelmodule->id;
        $cm->instance = $label->id;
        $cm->section = $section->id;
        $cm->addedby = $DB->get_field('course', 'userid', ['id' => $course->id]) ?: 2;
        $cm->visible = 1;
        $cm->visibleoncoursepage = 1;
        $cm->showdescription = 0;
        $cm->id = $DB->insert_record('course_modules', $cm);

        // Append to section sequence.
        $seq = $section->sequence ? $section->sequence . ',' . $cm->id : (string)$cm->id;
        $DB->set_field('course_sections', 'sequence', $seq, ['id' => $section->id]);

        // Create context and set role capabilities.
        $context = \context_module::instance($cm->id, MUST_EXIST);

        rebuild_course_cache((int)$course->id, true);

        return (int)$cm->id;
    }


    /**
     * Create a module instance using Moodle APIs.
     *
     * @param \stdClass $course
     * @param int $sectionnum
     * @param array $activity
     * @param string $activityid
     * @return \stdClass
     */
    private static function create_activity(\stdClass $course, int $sectionnum, array $activity, string $activityid): \stdClass {
        [$module, $context, $cw, $cm, $data] = prepare_new_moduleinfo_data($course, self::target_module($activity), $sectionnum);
        $mod = self::target_module($activity);
        $title = self::activity_title($activity);
        $intro = self::activity_intro_html($activity, $activityid);

        $data->name = $title;
        $data->intro = $intro;
        $data->introformat = FORMAT_HTML;
        $data->visible = 1;
        $data->visibleoncoursepage = 1;
        $data->showdescription = 0;
        if (isset($data->introeditor) && is_array($data->introeditor)) {
            $data->introeditor['text'] = $intro;
            $data->introeditor['format'] = FORMAT_HTML;
        }

        switch ($mod) {
            case 'label':
                $data->intro = $intro;
                $data->introformat = FORMAT_HTML;
                break;
            case 'forum':
                $data->type = 'general';
                $data->assessed = 0;
                $data->forcesubscribe = 0;
                $data->trackingtype = 1;
                $data->maxbytes = 0;
                $data->maxattachments = 9;
                break;
            case 'assign':
                $data->alwaysshowdescription = 1;
                $data->submissiondrafts = 0;
                $data->requiresubmissionstatement = 0;
                $data->sendnotifications = 0;
                $data->sendlatenotifications = 0;
                $data->sendstudentnotifications = 1;
                $data->duedate = 0;
                $data->allowsubmissionsfromdate = 0;
                $data->cutoffdate = 0;
                $data->gradingduedate = 0;
                $data->grade = 100;
                $data->teamsubmission = 0;
                $data->requireallteammemberssubmit = 0;
                $data->blindmarking = 0;
                $data->attemptreopenmethod = 'none';
                $data->maxattempts = -1;
                $data->markingworkflow = 0;
                $data->markingallocation = 0;
                $data->assignsubmission_onlinetext_enabled = 1;
                $data->assignsubmission_file_enabled = 0;
                $data->assignfeedback_comments_enabled = 1;
                break;
            case 'quiz':
                $data->timeopen = 0;
                $data->timeclose = 0;
                $data->timelimit = 0;
                $data->overduehandling = 'autosubmit';
                $data->graceperiod = 0;
                $data->preferredbehaviour = 'deferredfeedback';
                $data->attempts = 0;
                $data->attemptonlast = 0;
                $data->grademethod = 1;
                $data->decimalpoints = 2;
                $data->questiondecimalpoints = -1;
                $data->grade = 10;
                $data->sumgrades = 0;
                $data->questionsperpage = 1;
                $data->navmethod = 'free';
                $data->shuffleanswers = 1;
                $data->quizpassword = '';
                $data->subnet = '';
                $data->browsersecurity = '-';
                $data->feedbacktext = [];
                $data->feedbackboundaries = [];
                foreach (['attempt', 'correctness', 'marks', 'specificfeedback', 'generalfeedback', 'rightanswer', 'overallfeedback'] as $field) {
                    foreach (['during', 'immediately', 'open', 'closed'] as $when) {
                        $data->{$field . $when} = 0;
                    }
                }
                break;
            case 'url':
                $data->externalurl = trim((string)($activity['external_url'] ?? ''));
                if ($data->externalurl === '') {
                    $data->externalurl = 'https://example.com';
                }
                $data->display = RESOURCELIB_DISPLAY_AUTO;
                $data->popupwidth = 620;
                $data->popupheight = 450;
                break;
            case 'page':
            default:
                $data->content = self::activity_body_html($activity, $activityid);
                $data->contentformat = FORMAT_HTML;
                $data->display = RESOURCELIB_DISPLAY_AUTO;
                $data->printintro = 1;
                $data->printlastmodified = 0;
                $data->revision = 1;
                break;
        }

        return add_moduleinfo($data, $course, null);
    }

    /**
     * Update the instance table for an existing AI-managed activity.
     *
     * @param array $existing
     * @param array $activity
     * @param string $activityid
     * @return void
     */
    private static function update_activity_record(array $existing, array $activity, string $activityid): void {
        global $DB;

        $mod = $existing['mod'];
        $table = self::module_table($mod);
        if (!$table || !$DB->get_manager()->table_exists($table)) {
            return;
        }

        $record = $DB->get_record($table, ['id' => $existing['instance']], '*', IGNORE_MISSING);
        if (!$record) {
            return;
        }

        if (property_exists($record, 'name')) {
            $record->name = self::activity_title($activity);
        }
        if (property_exists($record, 'intro')) {
            $record->intro = self::activity_intro_html($activity, $activityid);
            $record->introformat = FORMAT_HTML;
        }
        if ($mod === 'page' && property_exists($record, 'content')) {
            $record->content = self::activity_body_html($activity, $activityid);
            $record->contentformat = FORMAT_HTML;
            $record->revision = ((int)($record->revision ?? 0)) + 1;
        }
        if ($mod === 'label' && property_exists($record, 'intro')) {
            $record->intro = self::activity_intro_html($activity, $activityid);
            $record->introformat = FORMAT_HTML;
        }
        if ($mod === 'url' && property_exists($record, 'externalurl') && !empty($activity['external_url'])) {
            $record->externalurl = (string)$activity['external_url'];
        }
        if (property_exists($record, 'timemodified')) {
            $record->timemodified = time();
        }

        $DB->update_record($table, $record);
    }

    /**
     * Move an existing AI-managed activity if the target placement changed.
     *
     * @param \stdClass $course
     * @param int $cmid
     * @param int $sectionnum
     * @return void
     */
    private static function move_existing_activity_to_section(\stdClass $course, int $cmid, int $sectionnum): void {
        global $DB;

        course_create_sections_if_missing($course, [$sectionnum]);
        $cm = $DB->get_record('course_modules', ['id' => $cmid, 'course' => $course->id], '*', IGNORE_MISSING);
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectionnum], '*', IGNORE_MISSING);
        if (!$cm || !$section || (int)$cm->section === (int)$section->id) {
            return;
        }
        moveto_module($cm, $section);
    }

    /**
     * Find an existing AI-managed activity by marker.
     *
     * @param \stdClass $course
     * @param string $activityid
     * @return array<string, mixed>|null
     */
    private static function find_existing_activity(\stdClass $course, string $activityid): ?array {
        global $DB;

        $marker = self::activity_marker($activityid);
        $cms = $DB->get_records('course_modules', ['course' => $course->id], '', 'id, module, instance');
        $modules = $DB->get_records('modules', null, '', 'id, name');

        foreach ($cms as $cm) {
            $mod = $modules[$cm->module]->name ?? '';
            $table = self::module_table($mod);
            if (!$table || !$DB->get_manager()->table_exists($table)) {
                continue;
            }
            $record = $DB->get_record($table, ['id' => $cm->instance], '*', IGNORE_MISSING);
            if (!$record) {
                continue;
            }
            $haystack = ($record->intro ?? '') . "\n" . ($record->content ?? '');
            if (strpos((string)$haystack, $marker) !== false) {
                return ['cmid' => (int)$cm->id, 'mod' => $mod, 'instance' => (int)$cm->instance];
            }
        }

        return null;
    }

    /**
     * Build section title from skeleton data.
     *
     * @param int $sectionnum
     * @param array $section
     * @return string
     */
    private static function section_name(int $sectionnum, array $section): string {
        $title = trim((string)($section['title'] ?? ''));
        if ($title === '') {
            $week = (int)($section['week'] ?? 0);
            $title = get_string('week', 'local_ailessonplan') . ' ' . ($week > 0 ? $week : $sectionnum);
        }
        return \core_text::substr($title, 0, 255);
    }

    /**
     * Render the AI-managed section summary block.
     *
     * @param int $sectionnum
     * @param array $section
     * @return string
     */
    private static function render_section_ai_block(int $sectionnum, array $section): string {
        $weeknum = (int)($section['week'] ?? $sectionnum);
        $titledata = (object)[
            'week' => $weeknum > 0 ? $weeknum : $sectionnum,
            'topic' => self::section_name($sectionnum, $section),
        ];
        $html = \html_writer::tag('h4', get_string('sectionblocktitle', 'local_ailessonplan', $titledata));
        if (!empty($section['summary'])) {
            $html .= \html_writer::tag('p', s((string)$section['summary']));
        }
        if (!empty($section['objectives'])) {
            $html .= \html_writer::tag('h5', get_string('objectives', 'local_ailessonplan'));
            $html .= self::render_unordered_list((array)$section['objectives']);
        }
        if (!empty($section['assessment_summary'])) {
            $html .= \html_writer::tag('h5', get_string('assessment', 'local_ailessonplan'));
            $html .= \html_writer::tag('p', s((string)$section['assessment_summary']));
        }

        return self::SECTION_MARKER_START . "\n" .
            \html_writer::div($html, 'local-ailessonplan-section-block') . "\n" .
            self::SECTION_MARKER_END;
    }

    /**
     * Merge the AI-managed block into an existing section summary.
     *
     * @param string $currentsummary
     * @param string $aiblock
     * @return string
     */
    private static function merge_section_summary(string $currentsummary, string $aiblock): string {
        if (self::has_ai_section_block($currentsummary)) {
            $pattern = '/' . preg_quote(self::SECTION_MARKER_START, '/') . '.*?' . preg_quote(self::SECTION_MARKER_END, '/') . '/s';
            return (string)preg_replace($pattern, $aiblock, $currentsummary, 1);
        }

        $currentsummary = rtrim($currentsummary);
        return $currentsummary === '' ? $aiblock : $currentsummary . "\n\n" . $aiblock;
    }

    /**
     * Check whether a section summary already has an AI-managed block.
     *
     * @param string $summary
     * @return bool
     */
    private static function has_ai_section_block(string $summary): bool {
        return strpos($summary, self::SECTION_MARKER_START) !== false && strpos($summary, self::SECTION_MARKER_END) !== false;
    }

    /**
     * Resolve requested Moodle module.
     *
     * @param array $activity
     * @return string
     */
    private static function requested_module(array $activity): string {
        $mod = strtolower(trim((string)($activity['mod'] ?? 'page')));
        return $mod === 'assignment' ? 'assign' : $mod;
    }

    /**
     * Resolve actual module created by v1 publisher.
     *
     * @param array $activity
     * @return string
     */
    private static function target_module(array $activity): string {
        $mod = self::requested_module($activity);
        if (in_array($mod, self::REAL_MODULES, true)) {
            return $mod;
        }
        return self::FALLBACK_MODULES[$mod] ?? 'page';
    }

    /**
     * Whether activity is published as placeholder/fallback.
     *
     * @param array $activity
     * @return bool
     */
    private static function is_placeholder_activity(array $activity): bool {
        return self::target_module($activity) !== self::requested_module($activity) || self::requested_module($activity) === 'scorm';
    }

    /**
     * Build stable activity ID.
     *
     * @param int $sectionnum
     * @param int $activityindex
     * @param array $activity
     * @return string
     */
    private static function activity_id(int $sectionnum, int $activityindex, array $activity): string {
        if (!empty($activity['_preview_id']) && is_scalar($activity['_preview_id'])) {
            return (string)$activity['_preview_id'];
        }
        return 'week-' . $sectionnum . '-' . ($activityindex + 1) . '-' . self::slug(self::requested_module($activity) . '-' . self::activity_title($activity));
    }

    /**
     * Build marker for activity.
     *
     * @param string $activityid
     * @return string
     */
    private static function activity_marker(string $activityid): string {
        return self::ACTIVITY_MARKER_PREFIX . $activityid . ' -->';
    }

    /**
     * Build activity title.
     *
     * @param array $activity
     * @return string
     */
    private static function activity_title(array $activity): string {
        $title = trim((string)($activity['title'] ?? ''));
        if ($title === '') {
            $title = ucfirst(self::requested_module($activity));
        }
        return \core_text::substr($title, 0, 255);
    }

    /**
     * Build intro HTML with marker.
     *
     * @param array $activity
     * @param string $activityid
     * @return string
     */
    private static function activity_intro_html(array $activity, string $activityid): string {
        $html = self::activity_marker($activityid) . "\n";
        $requested = self::requested_module($activity);
        if (self::is_placeholder_activity($activity)) {
            $placeholder = $requested === 'scorm' ? get_string('scormplaceholder', 'local_ailessonplan') : get_string('unsupportedplaceholder', 'local_ailessonplan', $requested);
            $html .= \html_writer::tag('p', s($placeholder), ['class' => 'alert alert-info']);
        }

        $text = '';
        foreach (['student_instruction', 'instruction', 'prompt', 'intro', 'text', 'description', 'learning_goal', 'placeholder_reason'] as $key) {
            if (!empty($activity[$key]) && is_scalar($activity[$key])) {
                $text .= \html_writer::tag('p', s((string)$activity[$key]));
            }
        }
        if ($requested === 'quiz') {
            $text .= \html_writer::tag('p', s(get_string('quizplaceholder', 'local_ailessonplan')));
        }
        if (!empty($activity['grading_hint'])) {
            $text .= \html_writer::tag('p', \html_writer::tag('strong', get_string('gradinghint', 'local_ailessonplan') . ': ') . s((string)$activity['grading_hint']));
        }

        return $html . ($text !== '' ? $text : \html_writer::tag('p', s(self::activity_title($activity))));
    }

    /**
     * Build richer Page body HTML for material-like activities.
     *
     * @param array $activity
     * @param string $activityid
     * @return string
     */
    private static function activity_body_html(array $activity, string $activityid): string {
        $html = self::activity_intro_html($activity, $activityid);
        foreach (['content', 'content_outline', 'chapters', 'suggested_terms', 'questions', 'options'] as $key) {
            if (empty($activity[$key])) {
                continue;
            }
            $html .= \html_writer::tag('h4', s(str_replace('_', ' ', ucfirst($key))));
            if (is_array($activity[$key])) {
                $html .= self::render_unordered_list($activity[$key]);
            } else if (is_scalar($activity[$key])) {
                $html .= \html_writer::tag('p', s((string)$activity[$key]));
            }
        }
        if (!empty($activity['external_url'])) {
            $html .= \html_writer::tag('p', \html_writer::link((string)$activity['external_url'], s((string)$activity['external_url'])));
        }
        return $html;
    }

    /**
     * Return Moodle DB table for module.
     *
     * @param string $mod
     * @return string|null
     */
    private static function module_table(string $mod): ?string {
        $allowed = ['assign', 'forum', 'label', 'page', 'quiz', 'url'];
        return in_array($mod, $allowed, true) ? $mod : null;
    }

    /**
     * Render an unordered list from scalar or structured items.
     *
     * @param array $items
     * @return string
     */
    private static function render_unordered_list(array $items): string {
        $html = '';
        foreach ($items as $item) {
            if (is_scalar($item)) {
                $text = (string)$item;
            } else if (is_array($item)) {
                $parts = [];
                foreach (['title', 'description', 'content', 'outline'] as $key) {
                    if (!empty($item[$key]) && is_scalar($item[$key])) {
                        $parts[] = (string)$item[$key];
                    }
                }
                $text = !empty($parts) ? implode(' - ', $parts) : json_encode($item, JSON_UNESCAPED_UNICODE);
            } else {
                $text = json_encode($item, JSON_UNESCAPED_UNICODE);
            }
            $html .= \html_writer::tag('li', s((string)$text));
        }
        return \html_writer::tag('ul', $html, ['class' => 'mb-0']);
    }

    /**
     * Slugify a string for stable markers.
     *
     * @param string $text
     * @return string
     */
    private static function slug(string $text): string {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        $text = trim((string)$text, '-');
        return $text === '' ? 'activity' : \core_text::substr($text, 0, 80);
    }
}
