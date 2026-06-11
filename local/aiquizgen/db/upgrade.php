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
 * Upgrade steps for AI Quiz Generator plugin.
 *
 * @package    local_aiquizgen
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Run local_aiquizgen upgrade steps.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_aiquizgen_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026041602) {
        // Moodle essay questions expect renderer-backed response formats such as
        // "editor"; "html" makes Moodle look for qtype_essay_format_html_renderer.
        $DB->set_field('qtype_essay_options', 'responseformat', 'editor', [
            'responseformat' => 'html',
        ]);

        upgrade_plugin_savepoint(true, 2026041602, 'local', 'aiquizgen');
    }

    return true;
}
