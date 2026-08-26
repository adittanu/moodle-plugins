<?php
// This file is part of Moodle - http://moodle.org/.
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tablelib.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$filtercourseid = optional_param('filtercourseid', 0, PARAM_INT);
$from = optional_param('from', date('Y-m-d', strtotime('-29 days')), PARAM_TEXT);
$to = optional_param('to', date('Y-m-d'), PARAM_TEXT);
$role = optional_param('role', '', PARAM_TEXT);
$status = optional_param('status', '', PARAM_ALPHANUMEXT);
$page = optional_param('page', 0, PARAM_INT);
$download = optional_param('download', 0, PARAM_BOOL);

if ($courseid) {
    $course = get_course($courseid);
    require_login($course);
    $context = context_course::instance($courseid);
    require_capability('report/dalireport:view', $context);
    $heading = format_string($course->fullname);
} else {
    require_login();
    $context = context_system::instance();
    require_capability('report/dalireport:viewsite', $context);
    $heading = get_string('reporttitle', 'report_dalireport');
}

$params = [
    'course_id' => $courseid ?: ($filtercourseid ?: ''),
    'from' => $from,
    'to' => $to,
    'role' => $role,
    'status' => $status,
    'per_page' => 20,
    'page' => $page + 1,
];
$report = (new \report_dalireport\api_client())->get_report($params);

if ($download) {
    $table = new flexible_table('report-dalireport-export');
    $table->define_columns(['date', 'user', 'role', 'course', 'activity', 'agent', 'topic', 'status', 'question', 'messages', 'response']);
    $table->define_headers([
        get_string('date'), get_string('user'), get_string('role'), get_string('course'), get_string('activity'),
        get_string('agent', 'report_dalireport'), get_string('topic', 'report_dalireport'),
        get_string('responsestatus', 'report_dalireport'), get_string('initialquestion', 'report_dalireport'),
        get_string('messages', 'message'), get_string('lastresponse', 'report_dalireport'),
    ]);
    $table->is_downloading('csv', 'dali-report-' . $from . '-' . $to);
    $table->setup();
    $dlparams = $params;
    $dlparams['per_page'] = 100;
    $dlpage = 1;
    do {
        $dlparams['page'] = $dlpage;
        $dlreport = (new \report_dalireport\api_client())->get_report($dlparams);
        foreach ($dlreport['sessions']['data'] as $session) {
            $table->add_data([
                $session['updated_at'],
                \report_dalireport\api_client::neutralize_csv_value($session['visitor']),
                $session['role'],
                \report_dalireport\api_client::neutralize_csv_value($session['course']),
                \report_dalireport\api_client::neutralize_csv_value($session['activity']),
                \report_dalireport\api_client::neutralize_csv_value($session['agent']),
                \report_dalireport\api_client::neutralize_csv_value($session['topic']),
                get_string('status_' . $session['response_status'], 'report_dalireport'),
                \report_dalireport\api_client::neutralize_csv_value($session['title']),
                $session['messages_count'],
                \report_dalireport\api_client::neutralize_csv_value($session['last_message']),
            ]);
        }
        $dlpage++;
    } while ($dlpage <= (int) $dlreport['sessions']['last_page']);
    $table->finish_output();
    exit;
}

$sessions = $report['sessions'];

$urlparams = array_filter(['courseid' => $courseid ?: null, 'filtercourseid' => $filtercourseid ?: null, 'from' => $from, 'to' => $to, 'role' => $role, 'status' => $status]);
$PAGE->set_url(new moodle_url('/report/dalireport/index.php', $urlparams));
$PAGE->set_context($context);
$PAGE->set_title(get_string('reporttitle', 'report_dalireport'));
$PAGE->set_heading($heading);
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reporttitle', 'report_dalireport'));
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'row g-3 align-items-end mb-4']);
if ($courseid) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
} else {
    $options = [0 => get_string('allcourses', 'report_dalireport')];
    foreach ($report['filterOptions']['courses'] as $item) {
        $options[(int) $item['course_id']] = $item['course_name'] ?: $item['course_id'];
    }
    echo html_writer::start_div('col-auto');
    echo html_writer::label(get_string('course'), 'id_filtercourseid', false, ['class' => 'form-label']);
    echo html_writer::select($options, 'filtercourseid', $filtercourseid, false, ['id' => 'id_filtercourseid', 'class' => 'form-select']);
    echo html_writer::end_div();
}
foreach ([['from', get_string('from', 'report_dalireport'), $from], ['to', get_string('to', 'report_dalireport'), $to]] as [$name, $label, $value]) {
    echo html_writer::start_div('col-auto');
    echo html_writer::label($label, 'id_' . $name, false, ['class' => 'form-label']);
    echo html_writer::empty_tag('input', ['id' => 'id_' . $name, 'class' => 'form-control', 'type' => 'date', 'name' => $name, 'value' => $value]);
    echo html_writer::end_div();
}
$options = ['' => get_string('allroles', 'report_dalireport')];
foreach ($report['filterOptions']['roles'] as $item) {
    $options[$item] = $item;
}
echo html_writer::start_div('col-auto');
echo html_writer::label(get_string('role'), 'id_role', false, ['class' => 'form-label']);
echo html_writer::select($options, 'role', $role, false, ['id' => 'id_role', 'class' => 'form-select']);
echo html_writer::end_div();
$options = ['' => get_string('allstatuses', 'report_dalireport')];
foreach ($report['filterOptions']['statuses'] as $item) {
    $options[$item] = get_string('status_' . $item, 'report_dalireport');
}
echo html_writer::start_div('col-auto');
echo html_writer::label(get_string('responsestatus', 'report_dalireport'), 'id_status', false, ['class' => 'form-label']);
echo html_writer::select($options, 'status', $status, false, ['id' => 'id_status', 'class' => 'form-select']);
echo html_writer::end_div();
echo html_writer::div(html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary', 'value' => get_string('applyfilters', 'report_dalireport')]), 'col-auto');
echo html_writer::div(html_writer::link(new moodle_url('/report/dalireport/index.php', $urlparams + ['download' => 1]), get_string('exportcsv', 'report_dalireport'), ['class' => 'btn btn-secondary']), 'col-auto');
echo html_writer::end_tag('form');

$summary = $report['summary'];
echo html_writer::start_div('row row-cols-1 row-cols-md-4 g-3 mb-4');
foreach (['sessions' => get_string('sessions', 'report_dalireport'), 'uniqueVisitors' => get_string('uniquevisitors', 'report_dalireport'), 'questions' => get_string('questions', 'report_dalireport'), 'responses' => get_string('responses', 'report_dalireport')] as $key => $label) {
    echo html_writer::div(html_writer::div(html_writer::tag('h3', format_float($summary[$key], 0)) . html_writer::div($label, 'text-muted'), 'card-body'), 'col card');
}
echo html_writer::end_div();
$tokens = $summary['tokenUsage'];
echo $OUTPUT->heading(get_string('modelusage', 'report_dalireport'), 3);
echo html_writer::div(get_string('tokensummary', 'report_dalireport', (object) ['total' => format_float($tokens['totalTokens'], 0), 'input' => format_float($tokens['inputTokens'], 0), 'output' => format_float($tokens['outputTokens'], 0), 'positive' => format_float($summary['positiveFeedback'], 0), 'negative' => format_float($summary['negativeFeedback'], 0)]), 'alert alert-info');

$normalizetopic = static function(array $item): string {
    $topic = trim((string) ($item['topic'] ?? ''));
    $decoded = json_decode($topic, true);
    if (is_array($decoded)) {
        $topic = trim((string) ($decoded['topik'] ?? $decoded['topic'] ?? $decoded['name'] ?? ''));
    }

    return $topic !== '' ? $topic : get_string('unknown', 'core');
};

$renderbars = static function(string $title, array $items, string $valuekey, callable $label): void {
    global $OUTPUT;
    echo $OUTPUT->heading($title, 3);
    $max = max(array_column($items, $valuekey) ?: [1]);
    foreach ($items as $item) {
        $value = (int) $item[$valuekey];
        $displaylabel = $label($item);
        echo html_writer::start_div('mb-3');
        echo html_writer::start_div('d-flex justify-content-between align-items-baseline mb-1', ['style' => 'gap:1rem;']);
        echo html_writer::span(s($displaylabel), 'text-break');
        echo html_writer::tag('strong', format_float($value, 0), [
            'class' => 'flex-shrink-0', 'style' => 'font-variant-numeric:tabular-nums;',
        ]);
        echo html_writer::end_div();
        $percent = ($value / max($max, 1)) * 100;
        echo html_writer::div(html_writer::div('', 'progress-bar', [
            'style' => 'width:' . $percent . '%', 'role' => 'progressbar',
            'aria-valuenow' => $value, 'aria-valuemin' => 0, 'aria-valuemax' => $max,
            'aria-label' => $displaylabel . ': ' . $value,
        ]), 'progress');
        echo html_writer::end_div();
    }
};
$renderbars(get_string('dailyactivity', 'report_dalireport'), $report['activity'], 'messages', static fn(array $item): string => $item['date']);
$renderbars(get_string('answerquality', 'report_dalireport'), $report['responseQuality'], 'total', static fn(array $item): string => get_string('status_' . $item['status'], 'report_dalireport'));
$renderbars(get_string('toptopics', 'report_dalireport'), $report['topTopics'], 'sessions', $normalizetopic);

$table = new html_table();
$table->head = [get_string('user'), get_string('course'), get_string('topic', 'report_dalireport'), get_string('responsestatus', 'report_dalireport'), get_string('conversation', 'report_dalireport'), get_string('messages', 'message'), get_string('lastaccess')];
foreach ($sessions['data'] as $session) {
    $table->data[] = [s($session['visitor']), s(($session['course'] ?: '-') . ' / ' . ($session['role'] ?: '-')),
        s($normalizetopic(['topic' => $session['topic']])), get_string('status_' . $session['response_status'], 'report_dalireport'),
        s($session['title']) . html_writer::div(s($session['last_message']), 'small text-muted'),
        (int) $session['messages_count'], s($session['updated_at'])];
}
echo html_writer::table($table);
echo $OUTPUT->paging_bar((int) $sessions['total'], $page, (int) $sessions['per_page'], new moodle_url('/report/dalireport/index.php', $urlparams));
echo $OUTPUT->footer();
