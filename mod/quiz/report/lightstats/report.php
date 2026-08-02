<?php
// This file is part of Moodle - http://moodle.org/.

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/default.php');

/** Lightweight statistics quiz report. */
class quiz_lightstats_report extends quiz_default_report {
    public function display($quiz, $cm, $course) {
        global $OUTPUT, $USER;

        require_capability('mod/quiz:viewreports', context_module::instance($cm->id));
        if (optional_param('calculate', 0, PARAM_BOOL)) {
            require_sesskey();
            \quiz_lightstats\job::queue($quiz->id, $USER->id);
            redirect(new moodle_url('/mod/quiz/report.php', ['id' => $cm->id, 'mode' => 'lightstats']));
        }

        $job = \quiz_lightstats\job::get($quiz->id);
        $data = $job ? \quiz_lightstats\job::decode($job) : null;
        if ($data && optional_param('download', '', PARAM_ALPHA) === 'csv') {
            $this->download_csv($quiz, $course, $data);
        }

        $this->print_header_and_tabs($cm, $course, $quiz, 'lightstats');
        echo $OUTPUT->heading(get_string('pluginname', 'quiz_lightstats'));
        echo $OUTPUT->single_button(new moodle_url('/mod/quiz/report.php', [
            'id' => $cm->id, 'mode' => 'lightstats', 'calculate' => 1, 'sesskey' => sesskey()]),
            get_string($data ? 'recalculate' : 'calculate', 'quiz_lightstats'));

        if (!$data) {
            $progress = $job ? (int)$job->progress : 0;
            $message = $job ? s($job->message) : get_string('notcalculated', 'quiz_lightstats');
            echo html_writer::div(html_writer::div('', 'progress-bar', [
                'style' => "width: {$progress}%", 'role' => 'progressbar', 'aria-valuenow' => $progress,
                'aria-valuemin' => 0, 'aria-valuemax' => 100]) . " {$message} ({$progress}%)", 'progress my-3', [
                'id' => 'lightstats-progress', 'data-url' => (new moodle_url('/mod/quiz/report/lightstats/status.php',
                    ['quizid' => $quiz->id]))->out(false)]);
            echo html_writer::script("(function poll(){var e=document.getElementById('lightstats-progress');if(!e)return;fetch(e.dataset.url).then(r=>r.json()).then(j=>{e.lastChild.textContent=' '+j.message+' ('+j.progress+'%)';e.firstChild.style.width=j.progress+'%';if(j.complete)location.reload();else if(j.status==='queued'||j.status==='running')setTimeout(poll,3000);});})();");
            return true;
        }

        if (\quiz_lightstats\job::attempt_count($quiz->id) !== (int)$job->attemptcount) {
            echo $OUTPUT->notification(get_string('stale', 'quiz_lightstats'), 'warning');
        }
        echo $OUTPUT->single_button(new moodle_url('/mod/quiz/report.php', [
            'id' => $cm->id, 'mode' => 'lightstats', 'download' => 'csv']),
            get_string('downloadcsv', 'quiz_lightstats'));

        $s = $data['summary'];
        $table = new html_table();
        $table->head = [get_string('metric', 'quiz_lightstats'), get_string('value', 'quiz_lightstats')];
        foreach ($this->summary_rows($quiz, $course, $s) as $row) {
            $table->data[] = $row;
        }
        echo html_writer::table($table);

        $table = new html_table();
        $table->head = ['Q#', 'Question type', 'Question name', 'Attempts', 'Facility index',
            'Standard deviation', 'Random guess score', 'Intended weight', 'Effective weight',
            'Discrimination index', 'Discriminative efficiency'];
        foreach ($data['questions'] as $question) {
            $table->data[] = $this->question_row($question, false);
        }
        echo $OUTPUT->heading(get_string('questionstats', 'quiz_lightstats'), 3);
        echo html_writer::table($table);
        return true;
    }

    private function summary_rows($quiz, $course, array $s): array {
        return [
            ['Quiz name', format_string($quiz->name)],
            ['Course name', format_string($course->fullname)],
            ['Open the quiz', $quiz->timeopen ? userdate($quiz->timeopen) : '-'],
            ['Number of complete graded first attempts', $s['firstcount']],
            ['Total number of complete graded attempts', $s['allcount']],
            ['Average grade of first attempts', $this->percent($s['firstavg'])],
            ['Average grade of all attempts', $this->percent($s['allavg'])],
            ['Average grade of last attempts', $this->percent($s['lastavg'])],
            ['Average grade of highest graded attempts', $this->percent($s['highestavg'])],
            ['Median grade (for highest graded attempt)', $this->percent($s['median'])],
            ['Standard deviation (for highest graded attempt)', $this->percent($s['sd'])],
            ['Score distribution skewness (for highest graded attempt)', $this->number($s['skewness'])],
            ['Coefficient of internal consistency (for highest graded attempt)', $this->percent($s['alpha'] === null ? null : $s['alpha'] * 100)],
        ];
    }

    private function question_row(array $q, bool $raw): array {
        return [$q['number'], $q['type'], $q['name'], $q['attempts'], $this->percent($q['facility']),
            $this->percent($q['sd']), $this->percent($q['randomguess']), $this->percent($q['intendedweight']),
            $this->percent($q['effectiveweight']), $this->percent($q['discrimination']), $this->percent($q['efficiency'])];
    }

    private function download_csv($quiz, $course, array $data): void {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');
        $filename = clean_filename($course->shortname . '-' . $quiz->name . '-completestats.csv');
        $csv = new csv_export_writer();
        $csv->set_filename(pathinfo($filename, PATHINFO_FILENAME));
        $csv->add_data(array_column($this->summary_rows($quiz, $course, $data['summary']), 0));
        $csv->add_data(array_column($this->summary_rows($quiz, $course, $data['summary']), 1));
        $csv->add_data(['Q#', 'Question type', 'Question name', 'Attempts', 'Facility index',
            'Standard deviation', 'Random guess score', 'Intended weight', 'Effective weight',
            'Discrimination index', 'Discriminative efficiency']);
        foreach ($data['questions'] as $question) {
            $csv->add_data($this->question_row($question, true));
        }
        foreach ($data['questions'] as $key => $question) {
            if (empty($data['responses'][$key])) {
                continue;
            }
            $csv->add_data(['Model response', 'Partial credit', 'Count', 'Frequency']);
            foreach ($data['responses'][$key] as $response) {
                $csv->add_data([$response['response'], $this->percent($response['partialcredit']),
                    $response['count'], $this->percent($response['frequency'])]);
            }
        }
        $csv->download_file();
        exit;
    }

    private function percent(?float $value): string {
        return $value === null ? '' : format_float($value, 2) . '%';
    }

    private function number(?float $value): string {
        return $value === null ? '' : format_float($value, 2);
    }
}
