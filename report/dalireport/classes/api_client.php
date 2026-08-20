<?php
// This file is part of Moodle - http://moodle.org/.
namespace report_dalireport;

defined('MOODLE_INTERNAL') || die();

/** Requests short-lived report URLs from Dali. */
class api_client {
    /** @return string Signed iframe URL. */
    public function get_embed_url(?int $courseid): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $baseurl = rtrim((string) get_config('report_dalireport', 'baseurl'), '/');
        $apikey = (string) get_config('report_dalireport', 'apikey');
        if ($baseurl === '' || $apikey === '') {
            throw new \moodle_exception('notconfigured', 'report_dalireport');
        }

        $params = $courseid ? ['course_id' => $courseid] : [];
        $curl = new \curl();
        $curl->setHeader(['Authorization: Bearer ' . $apikey, 'Accept: application/json']);
        $response = $curl->get($baseurl . '/api/v1/reports/embed-url', $params);
        $info = $curl->get_info();
        $data = json_decode($response, true);

        if (($info['http_code'] ?? 500) !== 200 || empty($data['url'])) {
            $message = $data['error'] ?? $curl->error ?? ('HTTP ' . ($info['http_code'] ?? 500));
            throw new \moodle_exception('connectionfailed', 'report_dalireport', '', $message);
        }

        return clean_param($data['url'], PARAM_URL);
    }
}
