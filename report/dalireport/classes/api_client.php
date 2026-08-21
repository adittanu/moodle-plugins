<?php
// This file is part of Moodle - http://moodle.org/.
namespace report_dalireport;

defined('MOODLE_INTERNAL') || die();

/** Requests report data from Dali. */
class api_client {
    /**
     * Fetch a tenant-scoped report.
     *
     * @param array $params Report filters.
     * @return array Report payload.
     */
    public function get_report(array $params): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $baseurl = rtrim((string) get_config('report_dalireport', 'baseurl'), '/');
        $apikey = (string) get_config('report_dalireport', 'apikey');
        if ($baseurl === '' || $apikey === '') {
            throw new \moodle_exception('notconfigured', 'report_dalireport');
        }

        $curl = new \curl();
        $curl->setHeader(['Authorization: Bearer ' . $apikey, 'Accept: application/json']);
        $response = $curl->get($baseurl . '/api/v1/reports', array_filter($params, static fn($value) => $value !== ''));
        $info = $curl->get_info();
        $data = json_decode($response, true);

        if (($info['http_code'] ?? 500) !== 200 || !is_array($data) || empty($data['summary'])) {
            $message = $data['message'] ?? $data['error'] ?? $curl->error ?? ('HTTP ' . ($info['http_code'] ?? 500));
            throw new \moodle_exception('connectionfailed', 'report_dalireport', '', $message);
        }

        return $data;
    }
}
