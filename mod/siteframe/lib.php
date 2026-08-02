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
 * Library functions for mod_siteframe.
 *
 * @package     mod_siteframe
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Return the features supported by the siteframe module.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports the feature, null otherwise
 */
function siteframe_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        default:
            return null;
    }
}

/**
 * Adds siteframe instance.
 *
 * @param object $siteframe The siteframe data from the form.
 * @param mod_siteframe_mod_form $mform The form instance.
 * @return int The id of the newly created siteframe record.
 */
function siteframe_add_instance($siteframe, $mform = null) {
    global $DB;

    $now = time();
    // Explicit field whitelist — moodleform_mod submits many extra fields
    // (coursemodule, section, module, add, update, submitbutton, ...) that
    // must never land in the siteframe table.
    $record = (object) [
        'course'      => $siteframe->course,
        'name'        => $siteframe->name,
        'intro'       => $siteframe->intro ?? '',
        'introformat' => $siteframe->introformat ?? 0,
        'url'         => $siteframe->url,
        'displaymode' => $siteframe->displaymode ?? 'inline',
        'height'      => $siteframe->height ?? 0,
        'width'       => $siteframe->width ?? '100%',
        'scrolling'   => $siteframe->scrolling ?? 'auto',
        'timemodified' => $now,
        'timecreated' => $now,
    ];

    return $DB->insert_record('siteframe', $record);
}

/**
 * Updates a siteframe instance.
 *
 * @param object $siteframe The siteframe data from the form.
 * @param mod_siteframe_mod_form $mform The form instance.
 * @return bool True on success.
 */
function siteframe_update_instance($siteframe, $mform = null) {
    global $DB;

    // Explicit field whitelist — see add_instance note.
    $record = (object) [
        'id'          => $siteframe->instance,
        'course'      => $siteframe->course,
        'name'        => $siteframe->name,
        'intro'       => $siteframe->intro ?? '',
        'introformat' => $siteframe->introformat ?? 0,
        'url'         => $siteframe->url,
        'displaymode' => $siteframe->displaymode ?? 'inline',
        'height'      => $siteframe->height ?? 0,
        'width'       => $siteframe->width ?? '100%',
        'scrolling'   => $siteframe->scrolling ?? 'auto',
        'timemodified' => time(),
    ];

    return $DB->update_record('siteframe', $record);
}


/**
 * Deletes a siteframe instance.
 *
 * @param int $id The id of the siteframe to delete.
 * @return bool True on success.
 */
function siteframe_delete_instance($id) {
    global $DB;

    if (!$siteframe = $DB->get_record('siteframe', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('siteframe', ['id' => $id]);

    return true;
}

/**
 * Return information for the course-module info (cached in modinfo).
 *
 * @param cm_info $cm The course module info object.
 * @return cached_cm_info|null Cached info or null.
 */
function siteframe_get_coursemodule_info($cm) {
    global $DB;

    if (!$siteframe = $DB->get_record('siteframe', ['id' => $cm->instance], 'id, name, intro, introformat')) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $siteframe->name;

    if ($cm->showdescription) {
        $info->content = format_module_intro('siteframe', $siteframe, $cm->id, false);
    }

    return $info;
}

/**
 * Print overview of siteframe activities.
 *
 * @param array $courses The courses to print overview for.
 * @param array $htmlarray The HTML array to add to.
 */
function siteframe_print_overview($courses, &$htmlarray) {
    global $DB, $CFG;

    if (empty($courses) || !is_array($courses)) {
        return;
    }

    if (!$courseids = array_keys($courses)) {
        return;
    }

    list($inorequal, $params) = $DB->get_in_or_equal($courseids);

    $sql = "SELECT sf.id, sf.name, sf.course, cm.id AS cmid
              FROM {siteframe} sf
              JOIN {course_modules} cm ON cm.instance = sf.id
              JOIN {modules} m ON m.id = cm.module AND m.name = 'siteframe'
             WHERE sf.course $inorequal";
    $siteframes = $DB->get_records_sql($sql, $params);

    foreach ($siteframes as $sf) {
        $str = '<div class="siteframe-overview">';
        $url = new moodle_url('/mod/siteframe/view.php', ['id' => $sf->cmid]);
        $str .= html_writer::link($url, format_string($sf->name));
        $str .= '</div>';
        $htmlarray[$sf->course][] = $str;
    }
}
