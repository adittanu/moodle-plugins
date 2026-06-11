<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ailessonplan;

/**
 * Builds Moodle course context and optional Dali RAG context for lesson-plan generation.
 *
 * @package    local_ailessonplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class context_builder {

    /** @var int Maximum characters for inline course activity context. */
    private const MAX_ACTIVITY_CHARS = 500;

    /** @var int Maximum characters returned from RAG retrieval. */
    private const MAX_RAG_CHARS = 12000;

    /**
     * Build course context for the AI request.
     *
     * @param \stdClass $course
     * @param array $options include_metadata, include_sections, include_activities
     * @return array
     */
    public static function build_course_context(\stdClass $course, array $options = []): array {
        global $DB;

        $includeMetadata = !empty($options['include_metadata']);
        $includeSections = !empty($options['include_sections']);
        $includeActivities = !empty($options['include_activities']);

        $context = [
            'course_id' => (int)$course->id,
        ];

        if ($includeMetadata) {
            $context['course'] = [
                'id' => (int)$course->id,
                'fullname' => format_string($course->fullname),
                'shortname' => format_string($course->shortname),
                'summary' => self::clean_text($course->summary ?? ''),
                'startdate' => !empty($course->startdate) ? (int)$course->startdate : null,
                'enddate' => !empty($course->enddate) ? (int)$course->enddate : null,
            ];
        }

        if (!$includeSections && !$includeActivities) {
            return $context;
        }

        $modinfo = get_fast_modinfo($course);

        if ($includeSections) {
            $context['sections'] = [];
            foreach ($modinfo->get_section_info_all() as $section) {
                if ((int)$section->section === 0) {
                    continue;
                }
                $context['sections'][] = [
                    'section' => (int)$section->section,
                    'name' => get_section_name($course, $section),
                    'summary' => self::clean_text($section->summary ?? ''),
                    'visible' => !empty($section->visible),
                ];
            }
        }

        if ($includeActivities) {
            $context['activities'] = [];
            foreach ($modinfo->cms as $cm) {
                if (empty($cm->uservisible)) {
                    continue;
                }
                $activity = [
                    'cmid' => (int)$cm->id,
                    'name' => format_string($cm->name),
                    'type' => $cm->modname,
                    'section' => (int)$cm->sectionnum,
                    'description' => self::activity_description($cm),
                ];
                $context['activities'][] = $activity;
            }
        }

        return $context;
    }

    /**
     * Get synced Dali knowledge sources for a Moodle course.
     *
     * @param int $courseid
     * @return array{sources: array<int, array>, knowledge_id: string|null, error: string|null}
     */
    public static function get_synced_knowledge_sources(int $courseid): array {
        global $CFG;

        $clientpath = $CFG->dirroot . '/local/daliwidget/classes/api_client.php';
        if (!file_exists($clientpath)) {
            return ['sources' => [], 'knowledge_id' => null, 'error' => 'local_daliwidget API client is not available.'];
        }

        require_once($clientpath);
        if (!class_exists('\\local_daliwidget\\api_client')) {
            return ['sources' => [], 'knowledge_id' => null, 'error' => 'local_daliwidget API client class is not available.'];
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

            return ['sources' => $sources, 'knowledge_id' => $knowledgeid, 'error' => null];
        } catch (\Throwable $e) {
            return ['sources' => [], 'knowledge_id' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Resolve a single source selected by ULID.
     *
     * @param int $courseid
     * @param string $sourceid
     * @return array
     */
    public static function find_synced_knowledge_source(int $courseid, string $sourceid): array {
        $knowledge = self::get_synced_knowledge_sources($courseid);
        if (!empty($knowledge['error'])) {
            throw new \moodle_exception('apierror', 'local_ailessonplan', '', $knowledge['error']);
        }

        foreach ($knowledge['sources'] as $source) {
            if ((string)($source['ulid'] ?? '') === $sourceid) {
                if (empty($source['knowledge_id']) && !empty($knowledge['knowledge_id'])) {
                    $source['knowledge_id'] = $knowledge['knowledge_id'];
                }
                return $source;
            }
        }

        throw new \moodle_exception('apierror', 'local_ailessonplan', '', 'Selected source was not found or is not ready yet.');
    }

    /**
     * Retrieve source context from Dali RAG.
     *
     * @param array $source
     * @param string $query
     * @return string
     */
    public static function retrieve_knowledge_source_context(array $source, string $query): string {
        $knowledgeid = trim((string)($source['knowledge_id'] ?? ''));
        $sourceid = trim((string)($source['ulid'] ?? ''));
        $title = trim((string)($source['title'] ?? 'course source'));

        if ($knowledgeid === '' || $sourceid === '') {
            throw new \moodle_exception('apierror', 'local_ailessonplan', '', 'Selected source is missing knowledge identifiers.');
        }

        $baseurl = get_config('local_daliwidget', 'baseurl') ?: get_config('local_ailessonplan', 'apibaseurl');
        if (empty($baseurl)) {
            throw new \moodle_exception('apierror', 'local_ailessonplan', '', 'Dali API base URL is not configured.');
        }

        $apikey = get_config('local_daliwidget', 'apikey') ?: get_config('local_ailessonplan', 'apikey');
        $query = trim($query) ?: ('Key concepts, competencies, and learning sequence from ' . $title);

        $payload = [
            'query' => $query,
            'knowledge_id' => $knowledgeid,
            'knowledge_source_id' => $sourceid,
            'k' => 12,
        ];

        $curl = new \curl(['ignoresecurity' => true]);
        $headers = ['Content-Type: application/json'];
        if (!empty($apikey)) {
            $headers[] = 'Authorization: Bearer ' . $apikey;
        }
        $curl->setHeader($headers);

        $response = $curl->post(rtrim($baseurl, '/') . '/api/v1/rag/query', json_encode($payload), [
            'CURLOPT_TIMEOUT' => 45,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_SSL_VERIFYPEER' => false,
            'CURLOPT_SSL_VERIFYHOST' => 0,
        ]);
        $info = $curl->get_info();

        if ($curl->get_errno()) {
            throw new \moodle_exception('apierror', 'local_ailessonplan', '', 'Retrieval cURL error code: ' . $curl->get_errno());
        }
        if (($info['http_code'] ?? 0) !== 200) {
            $errordata = json_decode($response, true);
            $message = is_array($errordata) ? ($errordata['error'] ?? $errordata['message'] ?? 'Unknown error') : 'HTTP ' . ($info['http_code'] ?? 'unknown');
            throw new \moodle_exception('apierror', 'local_ailessonplan', '', 'Source retrieval failed: ' . $message);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || empty($decoded['success'])) {
            $message = is_array($decoded) ? ($decoded['error'] ?? 'Invalid retrieval response.') : 'Invalid retrieval response.';
            throw new \moodle_exception('apierror', 'local_ailessonplan', '', $message);
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
            throw new \moodle_exception('apierror', 'local_ailessonplan', '', 'No relevant content was retrieved from the selected source.');
        }

        return \core_text::substr($context, 0, self::MAX_RAG_CHARS);
    }

    /**
     * Extract a small, safe description for supported activity modules.
     *
     * @param \cm_info $cm
     * @return string
     */
    private static function activity_description(\cm_info $cm): string {
        global $DB;

        $content = '';
        switch ($cm->modname) {
            case 'page':
                $record = $DB->get_record('page', ['id' => $cm->instance], 'content', IGNORE_MISSING);
                $content = $record ? $record->content : '';
                break;
            case 'book':
                $chapters = $DB->get_records('book_chapters', ['bookid' => $cm->instance], 'pagenum', 'id, title, content', 0, 3);
                foreach ($chapters as $chapter) {
                    $content .= '\n## ' . $chapter->title . '\n' . $chapter->content;
                }
                break;
            case 'assign':
            case 'quiz':
            case 'forum':
            case 'lesson':
            case 'folder':
            case 'label':
                $table = $cm->modname;
                $record = $DB->get_record($table, ['id' => $cm->instance], 'intro', IGNORE_MISSING);
                $content = $record ? ($record->intro ?? '') : '';
                break;
            case 'resource':
                $record = $DB->get_record('resource', ['id' => $cm->instance], 'intro', IGNORE_MISSING);
                $content = $record ? ($record->intro ?? '') : '';
                break;
        }

        return \core_text::substr(self::clean_text($content), 0, self::MAX_ACTIVITY_CHARS);
    }

    /**
     * Normalize HTML/text for compact prompt context.
     *
     * @param string $text
     * @return string
     */
    private static function clean_text(string $text): string {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string)$text);
    }
}
