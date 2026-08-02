<?php
// This file is part of Moodle - http://moodle.org/.

namespace quiz_lightstats;

defined('MOODLE_INTERNAL') || die();

class job {
    public static function get(int $quizid) {
        global $DB;
        return $DB->get_record('quiz_lightstats_jobs', ['quizid' => $quizid]);
    }

    public static function queue(int $quizid, int $userid): void {
        global $DB;
        $record = self::get($quizid) ?: (object)['quizid' => $quizid];
        $record->status = 'queued';
        $record->progress = 5;
        $record->message = get_string('queued', 'quiz_lightstats');
        $record->payload = null;
        $record->error = null;
        $record->requestedby = $userid;
        $record->attemptcount = self::attempt_count($quizid);
        $record->timecreated = time();
        $record->timestarted = null;
        $record->timecompleted = null;
        if (!empty($record->id)) {
            $DB->update_record('quiz_lightstats_jobs', $record);
        } else {
            $DB->insert_record('quiz_lightstats_jobs', $record);
        }
    }

    public static function update(object $record, string $status, int $progress, string $message): void {
        global $DB;
        $record->status = $status;
        $record->progress = $progress;
        $record->message = $message;
        $DB->update_record('quiz_lightstats_jobs', $record);
    }

    public static function attempt_count(int $quizid): int {
        global $DB;
        return $DB->count_records('quiz_attempts', ['quiz' => $quizid, 'preview' => 0, 'state' => 'finished']);
    }

    public static function decode(object $record): ?array {
        if ($record->status !== 'complete' || !$record->payload) {
            return null;
        }
        $data = json_decode($record->payload, true);
        return is_array($data) ? $data : null;
    }
}
