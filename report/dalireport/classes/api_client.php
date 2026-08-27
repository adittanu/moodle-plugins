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

        if (($info['http_code'] ?? 500) !== 200 || !is_array($data)) {
            $message = $data['message'] ?? $data['error'] ?? $curl->error ?? ('HTTP ' . ($info['http_code'] ?? 500));
            throw new \moodle_exception('connectionfailed', 'report_dalireport', '', $message);
        }

        self::validate_payload($data);

        return $data;
    }

    /**
     * Validate the nested report payload structure.
     *
     * @param array $data Decoded JSON payload.
     * @throws \moodle_exception When required keys are missing.
     */
    private static function validate_payload(array $data): void {
        $required = ['summary', 'sessions', 'filterOptions', 'responseQuality', 'activity', 'topTopics'];
        foreach ($required as $key) {
            if (!isset($data[$key])) {
                throw new \moodle_exception('connectionfailed', 'report_dalireport', '', "Malformed report: missing $key");
            }
        }
        if (!isset($data['sessions']['data'], $data['sessions']['total'], $data['sessions']['per_page'])) {
            throw new \moodle_exception('connectionfailed', 'report_dalireport', '', 'Malformed report: missing sessions pagination');
        }
        if (!isset($data['summary']['tokenUsage'])) {
            throw new \moodle_exception('connectionfailed', 'report_dalireport', '', 'Malformed report: missing token usage');
        }
    }

    /**
     * Prefix apostrophe to values that spreadsheet apps could interpret as formulas.
     *
     * @param string|null $value Cell value.
     * @return string Neutralized value.
     */
    public static function neutralize_csv_value(?string $value): string {
        $value ??= '';
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "\t" . $value;
        }
        return $value;
    }
}
