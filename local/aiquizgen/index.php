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
 * Index page for AI Quiz Generator plugin.
 *
 * @package    local_aiquizgen
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();

$PAGE->set_url('/local/aiquizgen/index.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_aiquizgen'));
$PAGE->set_heading(get_string('pluginname', 'local_aiquizgen'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_aiquizgen'));

echo html_writer::tag('p', 'AI Quiz Generator memungkinkan Anda generate soal quiz menggunakan OpenAI ChatGPT.');

echo html_writer::tag('h3', 'Cara Penggunaan:');
echo html_writer::start_tag('ol');
echo html_writer::tag('li', 'Masuk ke course yang diinginkan');
echo html_writer::tag('li', 'Klik link "AI Quiz Generator" di course navigation');
echo html_writer::tag('li', 'Isi form dengan topic dan preferensi lainnya');
echo html_writer::tag('li', 'Klik "Generate Questions"');
echo html_writer::tag('li', 'Preview dan questions akan otomatis tersimpan di Question Bank');
echo html_writer::end_tag('ol');

if (is_siteadmin()) {
    $settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_aiquizgen']);
    echo html_writer::div(
        $OUTPUT->single_button($settingsurl, 'Plugin Settings', 'get'),
        'mt-3'
    );
}

echo $OUTPUT->footer();
