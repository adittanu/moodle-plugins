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
 * The review form submits one compact JSON payload per question (`qjson_N`) so
 * large imports do not exceed PHP's max_input_vars. Individual fields remain as
 * a no-JavaScript fallback.
 *
 * @return array
 */
function local_aiquizgen_get_edited_questions_from_post(): array {
    $questiontotal = optional_param('questiontotal', 0, PARAM_INT);
    $questions = [];

    for ($i = 0; $i < $questiontotal; $i++) {
        if (optional_param('skip_' . $i, 0, PARAM_BOOL)) {
            continue;
        }

        $qdata = null;
        $json = trim((string)optional_param('qjson_' . $i, '', PARAM_RAW));
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $qdata = $decoded;
            }
        }

        if ($qdata === null) {
            $qdata = local_aiquizgen_get_question_from_legacy_fields($i);
        }

        if (!is_array($qdata)) {
            continue;
        }

        $questiontext = trim((string)($qdata['questiontext'] ?? ''));
        if ($questiontext === '') {
            continue;
        }

        $qtype = (string)($qdata['questiontype'] ?? 'multichoice');
        if (!in_array($qtype, ['multichoice', 'truefalse', 'shortanswer', 'essay'], true)) {
            $qtype = 'multichoice';
        }

        // The dedicated mark input wins over the JSON payload.
        $markraw = optional_param('mark_' . $i, null, PARAM_RAW);
        if ($markraw !== null && trim((string)$markraw) !== '') {
            $defaultmark = local_aiquizgen_normalize_mark($markraw);
        } else {
            $defaultmark = local_aiquizgen_normalize_mark($qdata['defaultmark'] ?? 1);
        }

        $graderinfo = trim((string)optional_param('graderinfo_' . $i, '', PARAM_RAW));
        if ($graderinfo === '') {
            $graderinfo = trim((string)($qdata['graderinfo'] ?? ''));
        }

        $answers = [];
        $rawanswers = is_array($qdata['answers'] ?? null) ? $qdata['answers'] : [];
        foreach ($rawanswers as $answer) {
            if (!is_array($answer)) {
                continue;
            }
            $answertext = trim((string)($answer['text'] ?? ''));
            if ($answertext === '') {
                continue;
            }
            $answers[] = [
                'text' => $answertext,
                'fraction' => is_numeric($answer['fraction'] ?? null) ? (float)$answer['fraction'] : 0.0,
                'feedback' => trim((string)($answer['feedback'] ?? '')),
            ];
        }

        if ($qtype === 'essay') {
            // Essay questions may legitimately have no answer options.
            $answers = [];
        } else if (empty($answers)) {
            continue;
        } else if ($qtype === 'shortanswer') {
            // Every accepted answer is correct for Moodle's short answer type.
            foreach ($answers as $index => $answer) {
                $answers[$index]['fraction'] = 1.0;
            }
        } else {
            $correctindex = null;
            foreach ($answers as $index => $answer) {
                if (($answer['fraction'] ?? 0) > 0) {
                    $correctindex = $index;
                    break;
                }
            }
            if ($correctindex === null) {
                $correctindex = 0;
            }
            foreach ($answers as $index => $answer) {
                $answers[$index]['fraction'] = $index === $correctindex ? 1.0 : 0.0;
            }
        }

        $questions[] = [
            'questiontext' => $questiontext,
            'questiontype' => $qtype,
            'defaultmark' => $defaultmark,
            'graderinfo' => $qtype === 'essay' ? $graderinfo : '',
            'answers' => $answers,
        ];
    }

    return $questions;
}

/**
 * Legacy review-field parser used when the compact JSON payload is unavailable.
 *
 * @param int $index Question index.
 * @return array|null
 */
function local_aiquizgen_get_question_from_legacy_fields(int $index): ?array {
    $questiontext = trim((string)optional_param('qtext_' . $index, '', PARAM_RAW));
    if ($questiontext === '') {
        return null;
    }

    $questiontype = optional_param('qtype_' . $index, 'multichoice', PARAM_ALPHA);
    $answercount = optional_param('answercount_' . $index, 0, PARAM_INT);
    $correctindex = optional_param('correct_' . $index, -1, PARAM_INT);
    $defaultmark = local_aiquizgen_normalize_mark(optional_param('mark_' . $index, 1, PARAM_RAW));
    $graderinfo = trim((string)optional_param('graderinfo_' . $index, '', PARAM_RAW));

    $answers = [];
    for ($j = 0; $j < $answercount; $j++) {
        $answertext = trim((string)optional_param('ans_' . $index . '_' . $j, '', PARAM_RAW));
        if ($answertext === '') {
            continue;
        }
        $answers[] = [
            'text' => $answertext,
            'fraction' => ($j === $correctindex) ? 1.0 : 0.0,
            'feedback' => trim((string)optional_param('feed_' . $index . '_' . $j, '', PARAM_RAW)),
        ];
    }

    if ($questiontype !== 'essay' && empty($answers)) {
        return null;
    }

    return [
        'questiontext' => $questiontext,
        'questiontype' => $questiontype,
        'defaultmark' => $defaultmark,
        'graderinfo' => $questiontype === 'essay' ? $graderinfo : '',
        'answers' => $answers,
    ];
}

/**
 * Normalize a maximum mark.
 *
 * @param mixed $value
 * @return float
 */
function local_aiquizgen_normalize_mark($value): float {
    if (is_string($value)) {
        $value = trim($value);
        $hascomma = strpos($value, ',') !== false;
        $hasdot = strpos($value, '.') !== false;
        if ($hascomma && !$hasdot) {
            $value = str_replace(',', '.', $value);
        } else if ($hascomma && $hasdot) {
            $value = str_replace(',', '', $value);
        }

        $converted = unformat_float($value);
        if ($converted !== false && is_numeric($converted)) {
            $value = $converted;
        }
    }

    $mark = is_numeric($value) ? (float)$value : 1.0;
    if ($mark <= 0) {
        $mark = 1.0;
    }

    return round(min(1000.0, $mark), 2);
}

/**
 * Return a human-readable question type label.
 *
 * @param string $type
 * @return string
 */
function local_aiquizgen_question_type_label(string $type): string {
    $known = ['multichoice', 'truefalse', 'shortanswer', 'essay'];
    return in_array($type, $known, true) ? get_string($type, 'local_aiquizgen') : $type;
}

/**
 * Remove duplicate extracted questions.
 *
 * @param array $questions
 * @return array{0: array, 1: int} Unique questions and duplicate count.
 */
function local_aiquizgen_dedupe_questions(array $questions): array {
    $unique = [];
    $seen = [];
    $duplicates = 0;

    foreach ($questions as $question) {
        $text = trim((string)($question['questiontext'] ?? ''));
        if ($text === '') {
            continue;
        }

        $keytext = core_text::strtolower(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if (!empty($question['answers'][0]['text'])) {
            $keytext .= '|' . core_text::strtolower(
                preg_replace('/\s+/u', ' ', (string)$question['answers'][0]['text']) ?? ''
            );
        } else if (!empty($question['graderinfo'])) {
            $keytext .= '|' . core_text::strtolower(
                preg_replace('/\s+/u', ' ', (string)$question['graderinfo']) ?? ''
            );
        }

        $key = sha1($keytext);
        if (isset($seen[$key])) {
            $duplicates++;
            continue;
        }

        $seen[$key] = true;
        $unique[] = $question;
    }

    return [$unique, $duplicates];
}

/**
 * Flush output so long-running imports show progress.
 */
function local_aiquizgen_flush_output(): void {
    if (ob_get_level() > 0) {
        @ob_flush();
    }
    flush();
}

/**
 * Render the progress card shown while a PDF is imported or questions are generated.
 *
 * The card is updated by inline scripts after each part so the teacher can see
 * that the request is still running. It also warns before leaving the page,
 * because leaving aborts the request before the review page is rendered.
 *
 * @param string|null $title Card title; defaults to the import title.
 * @param string|null $starting Initial status text; defaults to the import text.
 * @param string|null $warning Warning text; defaults to the import warning.
 * @param bool $simulate Whether to animate the bar slowly while waiting for one long request.
 */
function local_aiquizgen_render_import_progress(?string $title = null, ?string $starting = null,
        ?string $warning = null, bool $simulate = false): void {
    $title = $title ?? get_string('importprogress_title', 'local_aiquizgen');
    $starting = $starting ?? get_string('importprogress_starting', 'local_aiquizgen');
    $warning = $warning ?? get_string('importprogress_warning', 'local_aiquizgen');

    echo html_writer::start_div('card mb-3', ['id' => 'aiquizgen-import-progress']);
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h5', $title, ['class' => 'card-title']);

    echo html_writer::start_div('d-flex align-items-center mb-3');
    echo html_writer::tag('span', '', [
        'class' => 'spinner-border spinner-border-sm text-primary',
        'id' => 'aiquizgen-import-spinner',
        'role' => 'status',
        'aria-hidden' => 'true',
        'style' => 'margin-right: 0.5rem;',
    ]);
    echo html_writer::tag('span', $starting, [
        'id' => 'aiquizgen-import-status',
        'style' => 'margin-left: 0.5rem;',
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('progress', ['style' => 'height: 1.25rem;']);
    echo html_writer::tag('div', '0%', [
        'id' => 'aiquizgen-import-bar',
        'class' => 'progress-bar progress-bar-striped progress-bar-animated bg-primary',
        'role' => 'progressbar',
        'style' => 'width: 0%',
        'aria-valuemin' => '0',
        'aria-valuemax' => '100',
        'aria-valuenow' => '0',
    ]);
    echo html_writer::end_div();

    echo html_writer::tag('p', $warning, [
        'id' => 'aiquizgen-import-warning',
        'class' => 'text-danger small mt-3 mb-0',
    ]);

    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::tag('script', "
window.aiquizgenImportActive = true;
if (!window.aiquizgenImportGuardBound) {
    window.aiquizgenImportGuardBound = true;
    window.addEventListener('beforeunload', function(e) {
        if (!window.aiquizgenImportActive) {
            return;
        }
        e.preventDefault();
        e.returnValue = '';
        return '';
    });
}
window.aiquizgenImportProgress = function(percent, status, done, failed) {
    var bar = document.getElementById('aiquizgen-import-bar');
    var label = document.getElementById('aiquizgen-import-status');
    var spinner = document.getElementById('aiquizgen-import-spinner');
    var warning = document.getElementById('aiquizgen-import-warning');
    percent = Math.max(0, Math.min(100, percent));
    if (bar) {
        var display = Math.round(percent);
        bar.style.width = percent + '%';
        bar.textContent = display + '%';
        bar.setAttribute('aria-valuenow', display);
        if (done || failed) {
            bar.classList.remove('progress-bar-animated', 'bg-primary', 'bg-success', 'bg-danger');
            bar.classList.add(failed ? 'bg-danger' : 'bg-success');
        }
    }
    if (label && status) {
        label.textContent = status;
    }
    if (spinner && (done || failed)) {
        spinner.style.display = 'none';
    }
    if (warning && (done || failed)) {
        warning.style.display = 'none';
    }
    if (done || failed) {
        window.aiquizgenImportActive = false;
    }
};
window.aiquizgenProgressSimulate = function() {
    var percent = 5;
    var step = function() {
        if (!window.aiquizgenImportActive) {
            return;
        }
        percent = Math.min(90, percent + Math.max(0.5, (90 - percent) * 0.03));
        window.aiquizgenImportProgress(percent, null, false, false);
        window.setTimeout(step, 800);
    };
    window.setTimeout(step, 500);
};
");

    if ($simulate) {
        echo html_writer::tag('script', 'window.aiquizgenProgressSimulate();');
    }

    local_aiquizgen_flush_output();
}

/**
 * Update the import progress card from the streaming response.
 *
 * @param int $percent Completion percentage, 0-100.
 * @param string $status Human readable status message.
 * @param bool $done Whether the import has finished.
 * @param bool $failed Whether the import failed.
 */
function local_aiquizgen_update_import_progress(int $percent, string $status, bool $done = false,
        bool $failed = false): void {
    $percent = max(0, min(100, $percent));
    $statusjs = json_encode($status);
    $donejs = $done ? 'true' : 'false';
    $failedjs = $failed ? 'true' : 'false';

    echo html_writer::tag('script', "
(function() {
    if (window.aiquizgenImportProgress) {
        window.aiquizgenImportProgress({$percent}, {$statusjs}, {$donejs}, {$failedjs});
    }
})();
");

    local_aiquizgen_flush_output();
}

/**
 * Apply a maximum mark to every question of a given type.
 *
 * @param array $questions Normalized questions.
 * @param float $mcqmark Mark for non-essay questions (0 keeps the original).
 * @param float $essaymark Mark for essay questions (0 keeps the original).
 * @return array
 */
function local_aiquizgen_apply_max_marks(array $questions, float $mcqmark, float $essaymark): array {
    if ($mcqmark <= 0 && $essaymark <= 0) {
        return $questions;
    }

    foreach ($questions as $index => $question) {
        $isessay = (($question['questiontype'] ?? '') === 'essay');
        $target = $isessay ? $essaymark : $mcqmark;
        if ($target > 0) {
            $questions[$index]['defaultmark'] = local_aiquizgen_normalize_mark($target);
        }
    }

    return $questions;
}

/**
 * Render the editable review form for generated or imported questions.
 *
 * @param array $questions Normalized questions.
 * @param stdClass $data Submitted form data.
 * @param int $courseid Course ID.
 * @param bool $isextract Whether this is import/extract mode.
 */
function local_aiquizgen_render_review_form(array $questions, stdClass $data, int $courseid, bool $isextract): void {
    global $OUTPUT, $PAGE;

    $originaltype = $isextract ? 'extract' : (string)($data->questiontype ?? 'multichoice');
    $originaltopic = $isextract
        ? (string)($data->originaltopic ?? 'Imported from PDF')
        : (string)($data->topic ?? '');
    $title = $isextract
        ? get_string('previewextractedtitle', 'local_aiquizgen')
        : get_string('previewtitle', 'local_aiquizgen');
    $notice = $isextract
        ? get_string('reviewimportednotice', 'local_aiquizgen')
        : get_string('reviewgeneratednotice', 'local_aiquizgen');

    echo $OUTPUT->notification($notice, 'info');
    echo html_writer::tag('h3', $title);

    $hasessay = false;
    foreach ($questions as $question) {
        if (($question['questiontype'] ?? '') === 'essay') {
            $hasessay = true;
            break;
        }
    }

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new moodle_url('/local/aiquizgen/generate.php', ['courseid' => $courseid]))->out(false),
        'id' => 'aiquizgenreviewform',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'saveedited']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'category', 'value' => $data->category]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'originaltopic', 'value' => $originaltopic]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'originaltype', 'value' => $originaltype]);
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'questiontotal',
        'value' => count($questions),
    ]);

    if ($hasessay) {
        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-body');
        echo html_writer::tag('label', get_string('applytoessay', 'local_aiquizgen'), [
            'for' => 'bulkessaymark',
            'class' => 'form-label',
        ]);
        echo html_writer::start_div('input-group', ['style' => 'max-width: 360px;']);
        echo html_writer::empty_tag('input', [
            'type' => 'number',
            'id' => 'bulkessaymark',
            'class' => 'form-control',
            'min' => '0.01',
            'max' => '1000',
            'step' => '0.01',
            'value' => '1',
        ]);
        echo html_writer::tag('button', get_string('apply', 'local_aiquizgen'), [
            'type' => 'button',
            'class' => 'btn btn-secondary',
            'id' => 'applyessaymark',
        ]);
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo html_writer::end_div();
    }

    foreach ($questions as $index => $question) {
        $qtype = (string)($question['questiontype'] ?? 'multichoice');
        $answers = is_array($question['answers'] ?? null) ? $question['answers'] : [];
        $mark = local_aiquizgen_normalize_mark($question['defaultmark'] ?? 1);
        $graderinfo = (string)($question['graderinfo'] ?? '');
        $typelabel = local_aiquizgen_question_type_label($qtype);

        echo html_writer::start_div('card mb-3', [
            'data-qindex' => $index,
            'data-qtype' => $qtype,
        ]);
        echo html_writer::start_div('card-body');

        echo html_writer::start_div('d-flex justify-content-between align-items-center mb-2');
        echo html_writer::tag('h5',
            get_string('question', 'local_aiquizgen') . ' ' . ($index + 1) . ' ' .
            html_writer::tag('span', $typelabel, ['class' => 'badge bg-secondary']),
            ['class' => 'card-title mb-0']
        );
        echo html_writer::start_div('form-check');
        echo html_writer::empty_tag('input', [
            'type' => 'checkbox',
            'class' => 'form-check-input',
            'name' => 'skip_' . $index,
            'id' => 'skip_' . $index,
            'value' => '1',
        ]);
        echo html_writer::tag('label', get_string('skipquestion', 'local_aiquizgen'), [
            'class' => 'form-check-label',
            'for' => 'skip_' . $index,
        ]);
        echo html_writer::end_div();
        echo html_writer::end_div();

        echo html_writer::start_div('form-group');
        echo html_writer::tag('label', get_string('maximummark', 'local_aiquizgen'), [
            'for' => 'mark_' . $index,
        ]);
        echo html_writer::empty_tag('input', [
            'type' => 'number',
            'class' => 'form-control qmark',
            'name' => 'mark_' . $index,
            'id' => 'mark_' . $index,
            'value' => number_format($mark, 2, '.', ''),
            'min' => '0.01',
            'max' => '1000',
            'step' => '0.01',
        ]);
        echo html_writer::tag('small', get_string('maximummark_help', 'local_aiquizgen'), [
            'class' => 'form-text text-muted',
        ]);
        echo html_writer::end_div();

        echo html_writer::start_div('form-group');
        echo html_writer::tag('label', get_string('question', 'local_aiquizgen') . ' text', [
            'for' => 'qtext_' . $index,
        ]);
        echo html_writer::tag('textarea', s((string)($question['questiontext'] ?? '')), [
            'class' => 'form-control qtext',
            'rows' => 3,
            'name' => 'qtext_' . $index,
            'id' => 'qtext_' . $index,
        ]);
        echo html_writer::end_div();

        if ($qtype === 'essay') {
            echo html_writer::start_div('form-group');
            echo html_writer::tag('label', get_string('graderinfo', 'local_aiquizgen'), [
                'for' => 'graderinfo_' . $index,
            ]);
            echo html_writer::tag('textarea', s($graderinfo), [
                'class' => 'form-control qgraderinfo',
                'rows' => 8,
                'name' => 'graderinfo_' . $index,
                'id' => 'graderinfo_' . $index,
            ]);
            echo html_writer::tag('small', get_string('graderinfo_help', 'local_aiquizgen'), [
                'class' => 'form-text text-muted',
            ]);
            echo html_writer::end_div();
        } else {
            echo html_writer::tag('strong', get_string('answers', 'local_aiquizgen') . ':');
            if ($qtype === 'shortanswer') {
                echo html_writer::div(get_string('shortanswernote', 'local_aiquizgen'),
                    'small text-muted mb-2');
            }

            // Always render at least one answer row so a missing key can be fixed.
            if (empty($answers)) {
                $answers = [['text' => '', 'fraction' => 1, 'feedback' => '']];
            }

            echo html_writer::start_div('mt-2');

            $currentcorrect = 0;
            foreach ($answers as $aidx => $answer) {
                if (($answer['fraction'] ?? 0) > 0) {
                    $currentcorrect = $aidx;
                    break;
                }
            }

            foreach ($answers as $aidx => $answer) {
                echo html_writer::start_div('border rounded p-2 mb-2', ['data-answer-index' => $aidx]);
                echo html_writer::start_div('form-row align-items-center');

                if ($qtype !== 'shortanswer') {
                    echo html_writer::start_div('col-auto');
                    echo html_writer::empty_tag('input', [
                        'type' => 'radio',
                        'class' => 'qcorrect',
                        'name' => 'correct_' . $index,
                        'value' => $aidx,
                        'checked' => ($aidx === $currentcorrect) ? 'checked' : null,
                        'title' => get_string('correctanswer', 'local_aiquizgen'),
                    ]);
                    echo html_writer::end_div();
                }

                echo html_writer::start_div('col');
                echo html_writer::empty_tag('input', [
                    'type' => 'text',
                    'class' => 'form-control qanswer-text',
                    'name' => 'ans_' . $index . '_' . $aidx,
                    'value' => (string)($answer['text'] ?? ''),
                ]);
                echo html_writer::end_div();

                echo html_writer::end_div();

                echo html_writer::start_div('form-group mt-2 mb-0');
                echo html_writer::tag('label', get_string('feedback', 'local_aiquizgen'), [
                    'class' => 'small text-muted',
                ]);
                echo html_writer::empty_tag('input', [
                    'type' => 'text',
                    'class' => 'form-control form-control-sm qanswer-feedback',
                    'name' => 'feed_' . $index . '_' . $aidx,
                    'value' => (string)($answer['feedback'] ?? ''),
                ]);
                echo html_writer::end_div();
                echo html_writer::end_div();
            }

            echo html_writer::end_div();
            echo html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'answercount_' . $index,
                'value' => count($answers),
            ]);
        }

        echo html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'qtype_' . $index,
            'value' => $qtype,
        ]);
        echo html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'qjson_' . $index,
            'id' => 'qjson_' . $index,
            'value' => '',
        ]);

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

    $js = "
    document.addEventListener('DOMContentLoaded', function() {
        var form = document.getElementById('aiquizgenreviewform');
        if (!form) {
            return;
        }

        function valueOf(card, selector) {
            var el = card.querySelector(selector);
            return el ? el.value : '';
        }

        form.addEventListener('submit', function() {
            var cards = form.querySelectorAll('[data-qindex]');
            cards.forEach(function(card) {
                var index = card.getAttribute('data-qindex');
                var qtype = card.getAttribute('data-qtype');
                var qtext = valueOf(card, 'textarea.qtext').trim();
                var mark = valueOf(card, 'input.qmark');
                var graderinfo = valueOf(card, 'textarea.qgraderinfo').trim();
                var answers = [];

                if (qtype !== 'essay') {
                    var rows = card.querySelectorAll('[data-answer-index]');
                    var correct = -1;
                    var radio = card.querySelector('input.qcorrect:checked');
                    if (radio) {
                        correct = parseInt(radio.value, 10);
                    }
                    rows.forEach(function(row, rowindex) {
                        var textel = row.querySelector('.qanswer-text');
                        var text = textel ? textel.value.trim() : '';
                        if (!text) {
                            return;
                        }
                        var feedbackel = row.querySelector('.qanswer-feedback');
                        var feedback = feedbackel ? feedbackel.value.trim() : '';
                        var fraction = 0;
                        if (qtype === 'shortanswer' || rowindex === correct) {
                            fraction = 1;
                        }
                        answers.push({text: text, fraction: fraction, feedback: feedback});
                    });
                    if (qtype !== 'shortanswer' && answers.length &&
                            !answers.some(function(answer) { return answer.fraction > 0; })) {
                        answers[0].fraction = 1;
                    }
                }

                var payload = {
                    questiontext: qtext,
                    questiontype: qtype,
                    defaultmark: mark,
                    graderinfo: graderinfo,
                    answers: answers
                };

                var hidden = document.getElementById('qjson_' + index);
                if (hidden) {
                    hidden.value = JSON.stringify(payload);
                }

                // Disable the editable fields so only the compact JSON (plus the
                // skip checkbox) is submitted. This keeps large imports under
                // PHP's max_input_vars limit.
                card.querySelectorAll('input, textarea, select').forEach(function(el) {
                    if (el === hidden) {
                        return;
                    }
                    if (el.name === 'skip_' + index) {
                        return;
                    }
                    el.disabled = true;
                });
            });
        });

        var applybutton = document.getElementById('applyessaymark');
        if (applybutton) {
            applybutton.addEventListener('click', function() {
                var bulk = document.getElementById('bulkessaymark');
                if (!bulk) {
                    return;
                }
                form.querySelectorAll('[data-qtype=\"essay\"] input.qmark').forEach(function(el) {
                    el.value = bulk.value;
                });
            });
        }
    });
    ";
    $PAGE->requires->js_init_code($js);
}

/**
 * Extract existing questions (with answer keys) from an uploaded PDF and render
 * the same editable review form used by generate mode.
 *
 * @param stdClass $data Submitted form data.
 * @param context $context Course context.
 * @param int $courseid Course ID.
 * @return void
 */
function local_aiquizgen_process_extract(stdClass $data, context $context, int $courseid): void {
    global $OUTPUT, $USER;

    $categoryid = (string)($data->category ?? '');
    $generator = new question_generator();

    try {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'user', 'draft', $data->pdffile, 'id DESC', false);
        if (empty($files)) {
            $files = $fs->get_area_files($context->id, 'local_aiquizgen', 'pdffile', 0, 'id DESC', false);
        }
        if (empty($files)) {
            throw new moodle_exception('extractpdf_required', 'local_aiquizgen');
        }

        $file = reset($files);
        $filename = $file->get_filename();
        $tempfile = tempnam(sys_get_temp_dir(), 'pdf_');
        $file->copy_content_to($tempfile);

        local_aiquizgen_render_import_progress();

        require_once(__DIR__ . '/classes/util/pdf_extractor.php');
        require_once(__DIR__ . '/classes/util/text_chunker.php');

        try {
            $pdfcontent = \local_aiquizgen\util\pdf_extractor::extract_text($tempfile, 50000);
        } finally {
            if (file_exists($tempfile)) {
                unlink($tempfile);
            }
        }

        if (trim($pdfcontent) === '') {
            throw new moodle_exception('pdferror', 'local_aiquizgen', '',
                'No text could be extracted from the uploaded PDF.');
        }

        local_aiquizgen_update_import_progress(5,
            get_string('importprogress_pdf', 'local_aiquizgen', strlen($pdfcontent)));
        if (strpos($pdfcontent, '[... truncated]') !== false) {
            echo $OUTPUT->notification(
                get_string('pdftruncated', 'local_aiquizgen', 50000),
                'warning'
            );
        }

        $chunks = \local_aiquizgen\util\text_chunker::chunk_questions($pdfcontent, 4000);
        if (empty($chunks)) {
            $chunks = [$pdfcontent];
        }

        $totalparts = count($chunks);
        $client = new mastra_client();
        $questions = [];
        $failedparts = [];

        foreach ($chunks as $index => $chunk) {
            $part = $index + 1;
            $startpercent = 5 + (int)floor((($part - 1) / max(1, $totalparts)) * 90);
            $endpercent = 5 + (int)floor(($part / max(1, $totalparts)) * 90);

            local_aiquizgen_update_import_progress($startpercent,
                get_string('importprogress_part', 'local_aiquizgen',
                    (object)['part' => $part, 'total' => $totalparts]));

            try {
                $partquestions = $client->extract_questions(
                    $chunk,
                    $part,
                    $totalparts,
                    (string)($data->extractformat ?? 'asis'),
                    (string)($data->additionalinstructions ?? '')
                );
                $questions = array_merge($questions, $partquestions);
                local_aiquizgen_update_import_progress($endpercent,
                    get_string('importprogress_partdone', 'local_aiquizgen',
                        (object)[
                            'part' => $part,
                            'total' => $totalparts,
                            'count' => count($partquestions),
                        ]));
            } catch (Exception $e) {
                $failedparts[] = $part . ' (' . $e->getMessage() . ')';
                local_aiquizgen_update_import_progress($endpercent,
                    get_string('importprogress_partfailed', 'local_aiquizgen',
                        (object)['part' => $part, 'total' => $totalparts]));
                echo $OUTPUT->notification(
                    get_string('extractchunkfailed', 'local_aiquizgen', $part),
                    'warning'
                );
            }
        }

        if (empty($questions)) {
            throw new moodle_exception('noquestionsfound', 'local_aiquizgen');
        }

        list($questions, $duplicates) = local_aiquizgen_dedupe_questions($questions);
        if ($duplicates > 0) {
            echo $OUTPUT->notification(
                get_string('extractduplicates', 'local_aiquizgen', $duplicates),
                'info'
            );
        }

        $questions = local_aiquizgen_apply_max_marks(
            $questions,
            (float)($data->mcqmaxmark ?? 0),
            (float)($data->essaymaxmark ?? 0)
        );

        local_aiquizgen_update_import_progress(100,
            get_string('importprogress_done', 'local_aiquizgen', count($questions)), true);

        if (!empty($failedparts)) {
            echo $OUTPUT->notification(
                get_string('extractpartfailedlist', 'local_aiquizgen', implode('; ', $failedparts)),
                'warning'
            );
        }

        $generator->log_generation(
            $USER->id,
            $courseid,
            $categoryid,
            'PDF import: ' . $filename,
            count($questions),
            'extract',
            true
        );

        local_aiquizgen_render_review_form($questions, $data, $courseid, true);
    } catch (Exception $e) {
        $generator->log_generation(
            $USER->id,
            $courseid,
            $categoryid,
            'PDF import',
            0,
            'extract',
            false
        );

        local_aiquizgen_update_import_progress(100,
            get_string('importprogress_failed', 'local_aiquizgen'), true, true);

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
$formdata->mode = optional_param('mode', 'generate', PARAM_ALPHA);
$formdata->extractformat = optional_param('extractformat', 'asis', PARAM_ALPHA);

$mform = new generate_form(null, $customdata);
$mform->set_data($formdata);

$action = optional_param('action', '', PARAM_ALPHA);

if ($action === 'saveedited' && confirm_sesskey()) {
    echo $OUTPUT->header();

    // Saving a large import can take longer than PHP's default 30 second limit.
    core_php_time_limit::raise(0);

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
                        'cat' => $selectedcategory,
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

    // Process uploaded files - save to the plugin file area only when the
    // selected source is an uploaded PDF (or the import mode).
    $usesuploadedpdf = (($data->mode ?? 'generate') === 'extract')
        || (($data->pdfsource ?? 'topic') === 'upload');
    if ($usesuploadedpdf && !empty($data->pdffile)) {
        file_save_draft_area_files($data->pdffile, $context->id, 'local_aiquizgen', 'pdffile', 0);
    }

    echo $OUTPUT->header();

    // AI extraction/generation calls can take several minutes for long PDFs.
    core_php_time_limit::raise(0);

    // Import mode: extract existing questions and answer keys from a PDF.
    if (($data->mode ?? 'generate') === 'extract') {
        local_aiquizgen_process_extract($data, $context, $courseid);
        echo $OUTPUT->footer();
        exit;
    }

    // Generate mode: show an animated progress card while the single AI request runs.
    local_aiquizgen_render_import_progress(
        get_string('generateprogress_title', 'local_aiquizgen'),
        get_string('generateprogress_starting', 'local_aiquizgen'),
        get_string('generateprogress_warning', 'local_aiquizgen'),
        true
    );

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

        $pdffilesource = $data->pdfsource ?? 'topic';
        
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
        else if ($pdffilesource === 'upload' && !empty($data->pdffile)) {

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

        // Combine topic and retrieved/uploaded source content. A topic is only
        // used when the selected source is Topic/Subject.
        $topic = $pdffilesource === 'topic' ? trim($data->topic ?? '') : '';
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

        $generatemark = (float)($data->maxmark ?? 0);
        $questions = local_aiquizgen_apply_max_marks($questions, $generatemark, $generatemark);

        local_aiquizgen_update_import_progress(100,
            get_string('generateprogress_done', 'local_aiquizgen', count($questions)), true);

        local_aiquizgen_render_review_form($questions, $data, $courseid, false);

    } catch (Exception $e) {
        $generator = new question_generator();

        local_aiquizgen_update_import_progress(100,
            get_string('generateprogress_failed', 'local_aiquizgen'), true, true);

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
            $data->topic ?? '',
            $data->questioncount ?? 0,
            $data->questiontype ?? 'multichoice',
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
