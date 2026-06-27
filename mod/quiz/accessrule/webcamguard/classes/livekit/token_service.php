<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Small LiveKit token helper for Webcam Guard.
 *
 * @package    quizaccess_webcamguard
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_webcamguard\livekit;

defined('MOODLE_INTERNAL') || die();

/**
 * Creates short-lived LiveKit access tokens.
 */
class token_service {
    /**
     * Whether all required LiveKit settings are present.
     *
     * @return bool
     */
    public static function is_configured() {
        return self::get_url() !== ''
            && trim((string)get_config('quizaccess_webcamguard', 'livekitapikey')) !== ''
            && trim((string)get_config('quizaccess_webcamguard', 'livekitsecret')) !== '';
    }

    /**
     * LiveKit WebSocket URL.
     *
     * @return string
     */
    public static function get_url() {
        return trim((string)get_config('quizaccess_webcamguard', 'livekiturl'));
    }

    /**
     * Short token lifetime in seconds.
     *
     * @return int
     */
    public static function ttl() {
        $ttl = (int)get_config('quizaccess_webcamguard', 'livekitttl');
        if ($ttl < 60) {
            return 300;
        }
        return min($ttl, 3600);
    }

    /**
     * Deterministic room name for an attempt.
     *
     * @param int $quizid Quiz id.
     * @param int $attemptid Attempt id.
     * @return string
     */
    public static function room_name($quizid, $attemptid) {
        return 'wg-q' . (int)$quizid . '-a' . (int)$attemptid . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
    }

    /**
     * Create a LiveKit JWT.
     *
     * @param string $identity Participant identity.
     * @param string $name Participant display name.
     * @param string $roomname Room name.
     * @param bool $canpublish Whether participant can publish media.
     * @param bool $cansubscribe Whether participant can subscribe to media.
     * @param int|null $ttl Token lifetime.
     * @return string
     */
    public static function create_token($identity, $name, $roomname, $canpublish, $cansubscribe, $ttl = null) {
        $apikey = trim((string)get_config('quizaccess_webcamguard', 'livekitapikey'));
        $secret = trim((string)get_config('quizaccess_webcamguard', 'livekitsecret'));
        if ($apikey === '' || $secret === '') {
            throw new \moodle_exception('livenotconfigured', 'quizaccess_webcamguard');
        }

        $now = time();
        $ttl = $ttl === null ? self::ttl() : max(60, min((int)$ttl, 3600));
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];
        $payload = [
            'iss' => $apikey,
            'sub' => $identity,
            'name' => $name,
            'nbf' => $now - 10,
            'exp' => $now + $ttl,
            'video' => [
                'roomJoin' => true,
                'room' => $roomname,
                'canPublish' => (bool)$canpublish,
                'canSubscribe' => (bool)$cansubscribe,
                'canPublishData' => true,
            ],
        ];

        $segments = [
            self::base64url_encode(json_encode($header)),
            self::base64url_encode(json_encode($payload)),
        ];
        $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
        $segments[] = self::base64url_encode($signature);

        return implode('.', $segments);
    }

    /**
     * Base64 URL encoding without padding.
     *
     * @param string $value Input bytes.
     * @return string
     */
    protected static function base64url_encode($value) {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
