<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Webcam Guard quiz access rule.
 *
 * @package    quizaccess_webcamguard
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/locallib.php');

if (class_exists(\mod_quiz\local\access_rule_base::class)) {
    class_alias(\mod_quiz\local\access_rule_base::class, 'quizaccess_webcamguard_base');
} else {
    require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');
    class_alias(\quiz_access_rule_base::class, 'quizaccess_webcamguard_base');
}

/**
 * Quiz access rule that requires webcam monitoring and logs evidence for teacher review.
 */
class quizaccess_webcamguard extends quizaccess_webcamguard_base {
    /** @var string Ready field used by the preflight form. */
    const FIELD_READY = 'webcamguardready';

    /** @var string Consent field used by the preflight form. */
    const FIELD_CONSENT = 'webcamguardconsent';

    /** @var string Identity status field used by the preflight form. */
    const FIELD_IDENTITY_STATUS = 'webcamguardidentitystatus';

    /** @var string Identity distance field used by the preflight form. */
    const FIELD_IDENTITY_DISTANCE = 'webcamguardidentitydistance';

    /** @var string Identity message field used by the preflight form. */
    const FIELD_IDENTITY_MESSAGE = 'webcamguardidentitymessage';

    /** @var array Allowed interval snapshot values. */
    const ALLOWED_INTERVALS = [0, 60, 120, 300];
    /** @var array Allowed live selection refresh intervals in seconds. */
    const ALLOWED_SELECTION_INTERVALS = [30, 60, 180, 300, 600];


    /** @var int Minimum threshold in seconds. */
    const MIN_THRESHOLD = 1;

    /** @var int Maximum threshold in seconds. */
    const MAX_THRESHOLD = 300;

    /** @var array Allowed identity modes. */
    const ALLOWED_IDENTITY_MODES = ['flag', 'block'];

    /** @var array Risk weights for webcam guard event types. */
    const EVENT_WEIGHTS = [
        'no_face' => 2,
        'multiple_faces' => 4,
        'window_blur' => 3,
        'camera_stopped' => 5,
        'camera_error' => 3,
        'identity_check' => 4,
    ];

    /**
     * Get the risk weight for a given event type.
     *
     * @param string $eventtype Event type key.
     * @return int Risk weight (defaults to 1 for unknown types).
     */
    public static function event_weight($eventtype) {
        return isset(self::EVENT_WEIGHTS[$eventtype]) ? self::EVENT_WEIGHTS[$eventtype] : 1;
    }

    /**
     * Return an instance of this rule if Webcam Guard is enabled.
     *
     * @param quiz $quizobj Quiz object.
     * @param int $timenow Current time.
     * @param bool $canignoretimelimits Ignored.
     * @return quizaccess_webcamguard|null
     */
    public static function make($quizobj, $timenow, $canignoretimelimits) {
        $quiz = $quizobj->get_quiz();
        if (empty($quiz->webcamguard_enabled)) {
            return null;
        }

        return new self($quizobj, $timenow);
    }

    /**
     * Explain the restriction on the quiz view page.
     *
     * @return string
     */
    public function description() {
        $message = html_writer::div(get_string('description', 'quizaccess_webcamguard'));
        if (has_capability('quizaccess/webcamguard:viewreport', $this->quizobj->get_context())) {
            $url = new moodle_url('/mod/quiz/accessrule/webcamguard/report.php', [
                'cmid' => $this->quizobj->get_cmid(),
            ]);
            $message .= html_writer::div(html_writer::link($url, get_string('reportlink', 'quizaccess_webcamguard')));
        }
        return $message;
    }

    /**
     * Add Webcam Guard settings to the quiz settings form.
     *
     * @param mod_quiz_mod_form $quizform Quiz form.
     * @param MoodleQuickForm $mform MForm.
     */
    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $mform->addElement('header', 'webcamguardheader', get_string('settingheader', 'quizaccess_webcamguard'));

        $mform->addElement('advcheckbox', 'webcamguard_enabled', get_string('enabled', 'quizaccess_webcamguard'));
        $mform->addHelpButton('webcamguard_enabled', 'enabled', 'quizaccess_webcamguard');
        $mform->setDefault('webcamguard_enabled', 0);

        $devicemodes = [
            'any' => get_string('deviceany', 'quizaccess_webcamguard'),
            'mobile' => get_string('devicemobile', 'quizaccess_webcamguard'),
            'desktop' => get_string('devicedesktop', 'quizaccess_webcamguard'),
        ];
        $mform->addElement('select', 'webcamguard_devicemode',
            get_string('devicemode', 'quizaccess_webcamguard'), $devicemodes);
        $mform->addHelpButton('webcamguard_devicemode', 'devicemode', 'quizaccess_webcamguard');
        $mform->setDefault('webcamguard_devicemode', 'any');
        $mform->hideIf('webcamguard_devicemode', 'webcamguard_enabled', 'notchecked');

        $mform->addElement('advcheckbox', 'webcamguard_snapshotonviolation',
            get_string('snapshotonviolation', 'quizaccess_webcamguard'));
        $mform->addHelpButton('webcamguard_snapshotonviolation', 'snapshotonviolation', 'quizaccess_webcamguard');
        $mform->setDefault('webcamguard_snapshotonviolation', 1);
        $mform->hideIf('webcamguard_snapshotonviolation', 'webcamguard_enabled', 'notchecked');

        $intervals = [
            0 => get_string('intervaloff', 'quizaccess_webcamguard'),
            60 => get_string('interval60', 'quizaccess_webcamguard'),
            120 => get_string('interval120', 'quizaccess_webcamguard'),
            300 => get_string('interval300', 'quizaccess_webcamguard'),
        ];
        $mform->addElement('select', 'webcamguard_intervalseconds',
            get_string('intervalseconds', 'quizaccess_webcamguard'), $intervals);
        $mform->addHelpButton('webcamguard_intervalseconds', 'intervalseconds', 'quizaccess_webcamguard');
        $mform->setDefault('webcamguard_intervalseconds', 0);
        $mform->hideIf('webcamguard_intervalseconds', 'webcamguard_enabled', 'notchecked');

        $mform->addElement('text', 'webcamguard_nofacethreshold',
            get_string('nofacethreshold', 'quizaccess_webcamguard'), ['size' => 5]);
        $mform->setType('webcamguard_nofacethreshold', PARAM_INT);
        $mform->setDefault('webcamguard_nofacethreshold', 10);
        $mform->hideIf('webcamguard_nofacethreshold', 'webcamguard_enabled', 'notchecked');

        $mform->addElement('text', 'webcamguard_multifacethreshold',
            get_string('multifacethreshold', 'quizaccess_webcamguard'), ['size' => 5]);
        $mform->setType('webcamguard_multifacethreshold', PARAM_INT);
        $mform->setDefault('webcamguard_multifacethreshold', 3);
        $mform->hideIf('webcamguard_multifacethreshold', 'webcamguard_enabled', 'notchecked');

        $mform->addElement('text', 'webcamguard_blurthreshold',
            get_string('blurthreshold', 'quizaccess_webcamguard'), ['size' => 5]);
        $mform->setType('webcamguard_blurthreshold', PARAM_INT);
        $mform->setDefault('webcamguard_blurthreshold', 5);
        $mform->hideIf('webcamguard_blurthreshold', 'webcamguard_enabled', 'notchecked');

        $mform->addElement('advcheckbox', 'webcamguard_liveenabled',
            get_string('liveenabled', 'quizaccess_webcamguard'));
        $mform->addHelpButton('webcamguard_liveenabled', 'liveenabled', 'quizaccess_webcamguard');
        $mform->setDefault('webcamguard_liveenabled', 0);
        $mform->hideIf('webcamguard_liveenabled', 'webcamguard_enabled', 'notchecked');
        $selectionintervals = [
            30 => get_string('selectioninterval30', 'quizaccess_webcamguard'),
            60 => get_string('selectioninterval60', 'quizaccess_webcamguard'),
            180 => get_string('selectioninterval180', 'quizaccess_webcamguard'),
            300 => get_string('selectioninterval300', 'quizaccess_webcamguard'),
            600 => get_string('selectioninterval600', 'quizaccess_webcamguard'),
        ];
        $mform->addElement('select', 'webcamguard_selectioninterval',
            get_string('selectioninterval', 'quizaccess_webcamguard'), $selectionintervals);
        $mform->addHelpButton('webcamguard_selectioninterval', 'selectioninterval', 'quizaccess_webcamguard');
        $mform->setDefault('webcamguard_selectioninterval', 60);
        $mform->hideIf('webcamguard_selectioninterval', 'webcamguard_liveenabled', 'notchecked');


        $mform->addElement('advcheckbox', 'webcamguard_identityenabled',
            get_string('identityenabled', 'quizaccess_webcamguard'));
        $mform->addHelpButton('webcamguard_identityenabled', 'identityenabled', 'quizaccess_webcamguard');
        $mform->setDefault('webcamguard_identityenabled', 0);
        $mform->hideIf('webcamguard_identityenabled', 'webcamguard_enabled', 'notchecked');

        $mform->addElement('text', 'webcamguard_identitythreshold',
            get_string('identitythreshold', 'quizaccess_webcamguard'), ['size' => 5]);
        $mform->setType('webcamguard_identitythreshold', PARAM_INT);
        $mform->setDefault('webcamguard_identitythreshold', 60);
        $mform->addHelpButton('webcamguard_identitythreshold', 'identitythreshold', 'quizaccess_webcamguard');
        $mform->hideIf('webcamguard_identitythreshold', 'webcamguard_enabled', 'notchecked');
        $mform->hideIf('webcamguard_identitythreshold', 'webcamguard_identityenabled', 'notchecked');

        $identitymodes = [
            'flag' => get_string('identitymodeflag', 'quizaccess_webcamguard'),
            'block' => get_string('identitymodeblock', 'quizaccess_webcamguard'),
        ];
        $mform->addElement('select', 'webcamguard_identitymode',
            get_string('identitymode', 'quizaccess_webcamguard'), $identitymodes);
        $mform->setDefault('webcamguard_identitymode', 'flag');
        $mform->hideIf('webcamguard_identitymode', 'webcamguard_enabled', 'notchecked');
        $mform->hideIf('webcamguard_identitymode', 'webcamguard_identityenabled', 'notchecked');
    }

    /**
     * Validate Webcam Guard settings.
     *
     * @param array $errors Errors so far.
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @param mod_quiz_mod_form $quizform Quiz form.
     * @return array
     */
    public static function validate_settings_form_fields(array $errors,
            array $data, $files, mod_quiz_mod_form $quizform) {
        if (empty($data['webcamguard_enabled'])) {
            return $errors;
        }

        $devicemode = $data['webcamguard_devicemode'] ?? 'any';
        if (!in_array($devicemode, ['any', 'mobile', 'desktop'], true)) {
            $errors['webcamguard_devicemode'] = get_string('invaliddevicemode', 'quizaccess_webcamguard');
        }

        if (!in_array((int)$data['webcamguard_intervalseconds'], self::ALLOWED_INTERVALS, true)) {
            $errors['webcamguard_intervalseconds'] = get_string('invalidinterval', 'quizaccess_webcamguard');
        }
        if (!in_array((int)($data['webcamguard_selectioninterval'] ?? 60),
                self::ALLOWED_SELECTION_INTERVALS, true)) {
            $errors['webcamguard_selectioninterval'] = get_string('invalidselectioninterval',
                'quizaccess_webcamguard');
        }


        foreach (['nofacethreshold', 'multifacethreshold', 'blurthreshold'] as $field) {
            $name = 'webcamguard_' . $field;
            $value = isset($data[$name]) ? (int)$data[$name] : 0;
            if ($value < self::MIN_THRESHOLD || $value > self::MAX_THRESHOLD) {
                $a = (object)['min' => self::MIN_THRESHOLD, 'max' => self::MAX_THRESHOLD];
                $errors[$name] = get_string('thresholdinvalid', 'quizaccess_webcamguard', $a);
            }
        }

        if (!empty($data['webcamguard_identityenabled'])) {
            $identitythreshold = isset($data['webcamguard_identitythreshold'])
                ? (int)$data['webcamguard_identitythreshold'] : 0;
            if ($identitythreshold < 30 || $identitythreshold > 90) {
                $errors['webcamguard_identitythreshold'] = get_string('identitythresholdinvalid',
                    'quizaccess_webcamguard');
            }

            $identitymode = isset($data['webcamguard_identitymode']) ? $data['webcamguard_identitymode'] : 'flag';
            if (!in_array($identitymode, self::ALLOWED_IDENTITY_MODES, true)) {
                $errors['webcamguard_identitymode'] = get_string('invalididentitymode', 'quizaccess_webcamguard');
            }
        }

        return $errors;
    }

    /**
     * Save Webcam Guard settings.
     *
     * @param object $quiz Quiz form data.
     */
    public static function save_settings($quiz) {
        global $DB;

        $now = time();
        $enabled = empty($quiz->webcamguard_enabled) ? 0 : 1;
        $record = (object)[
            'quizid' => $quiz->id,
            'cmid' => $quiz->coursemodule,
            'enabled' => $enabled,
            'snapshotonviolation' => empty($quiz->webcamguard_snapshotonviolation) ? 0 : 1,
            'intervalseconds' => isset($quiz->webcamguard_intervalseconds) ? (int)$quiz->webcamguard_intervalseconds : 0,
            'nofacethreshold' => isset($quiz->webcamguard_nofacethreshold) ? (int)$quiz->webcamguard_nofacethreshold : 10,
            'multifacethreshold' => isset($quiz->webcamguard_multifacethreshold) ? (int)$quiz->webcamguard_multifacethreshold : 3,
            'blurthreshold' => isset($quiz->webcamguard_blurthreshold) ? (int)$quiz->webcamguard_blurthreshold : 5,
            'identityenabled' => empty($quiz->webcamguard_identityenabled) ? 0 : 1,
            'identitythreshold' => isset($quiz->webcamguard_identitythreshold) ? (int)$quiz->webcamguard_identitythreshold : 60,
            'identitymode' => isset($quiz->webcamguard_identitymode)
                && in_array($quiz->webcamguard_identitymode, self::ALLOWED_IDENTITY_MODES, true)
                    ? $quiz->webcamguard_identitymode : 'flag',
            'liveenabled' => empty($quiz->webcamguard_liveenabled) ? 0 : 1,
            'selectioninterval' => isset($quiz->webcamguard_selectioninterval)
                && in_array((int)$quiz->webcamguard_selectioninterval, self::ALLOWED_SELECTION_INTERVALS, true)
                    ? (int)$quiz->webcamguard_selectioninterval : 60,
            'devicemode' => isset($quiz->webcamguard_devicemode)
                && in_array($quiz->webcamguard_devicemode, ['any', 'mobile', 'desktop'], true)
                    ? $quiz->webcamguard_devicemode : 'any',
            'timemodified' => $now,
        ];

        $existing = $DB->get_record('quizaccess_wg_config', ['quizid' => $quiz->id]);
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('quizaccess_wg_config', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record('quizaccess_wg_config', $record);
        }
    }

    /**
     * Delete settings for a deleted quiz.
     *
     * @param object $quiz Quiz record.
     */
    public static function delete_settings($quiz) {
        global $DB;
        $DB->delete_records('quizaccess_wg_config', ['quizid' => $quiz->id]);
    }

    /**
     * Load settings with the quiz.
     *
     * @param int $quizid Quiz id.
     * @return array
     */
    public static function get_settings_sql($quizid) {
        return [
            'wg.enabled AS webcamguard_enabled, wg.snapshotonviolation AS webcamguard_snapshotonviolation, ' .
                'wg.intervalseconds AS webcamguard_intervalseconds, ' .
                'wg.nofacethreshold AS webcamguard_nofacethreshold, ' .
                'wg.multifacethreshold AS webcamguard_multifacethreshold, ' .
                'wg.blurthreshold AS webcamguard_blurthreshold, ' .
                'wg.identityenabled AS webcamguard_identityenabled, ' .
                'wg.identitythreshold AS webcamguard_identitythreshold, ' .
                'wg.identitymode AS webcamguard_identitymode, ' .
                'wg.liveenabled AS webcamguard_liveenabled, ' .
                'wg.selectioninterval AS webcamguard_selectioninterval, wg.devicemode AS webcamguard_devicemode',
            'LEFT JOIN {quizaccess_wg_config} wg ON wg.quizid = quiz.id',
            [],
        ];
    }

    /**
     * Whether preflight is required.
     *
     * @param int|null $attemptid Attempt id.
     * @return bool
     */
    public function is_preflight_check_required($attemptid) {
        global $SESSION;

        if ($this->is_real_preview_user()) {
            return false;
        }

        if (empty($SESSION->webcamguardchecked)) {
            return true;
        }

        $key = $this->get_session_key($attemptid);
        $fallbackkey = $this->get_session_key(0);
        $intattemptid = (int)$attemptid;

        if ($intattemptid > 0 && !empty($SESSION->webcamguardchecked[$key])) {
            return false;
        }

        if ($intattemptid > 0 && !empty($SESSION->webcamguardchecked[$fallbackkey])) {
            $SESSION->webcamguardchecked[$key] = true;
            unset($SESSION->webcamguardchecked[$fallbackkey]);
            return false;
        }

        return true;
    }

    /**
     * Add consent and webcam readiness fields to the preflight form.
     *
     * @param mod_quiz_preflight_check_form $quizform Preflight form.
     * @param MoodleQuickForm $mform MForm.
     * @param int|null $attemptid Attempt id.
     */
    public function add_preflight_check_form_fields(mod_quiz_preflight_check_form $quizform,
            MoodleQuickForm $mform, $attemptid) {
        global $CFG, $PAGE, $USER;

        $identityenabled = !empty($this->quiz->webcamguard_identityenabled);
        $identitymode = !empty($this->quiz->webcamguard_identitymode)
            ? $this->quiz->webcamguard_identitymode : 'flag';
        $hasprofilepicture = !empty($USER->picture);
        $profileediturl = (new moodle_url('/user/edit.php', ['id' => $USER->id]))->out(false);

        $mform->addElement('hidden', self::FIELD_CONSENT, 0);
        $mform->setType(self::FIELD_CONSENT, PARAM_BOOL);
        $mform->addElement('hidden', self::FIELD_READY, 0);
        $mform->setType(self::FIELD_READY, PARAM_BOOL);
        $mform->addElement('hidden', 'webcamguarddevicevalid', 0);
        $mform->setType('webcamguarddevicevalid', PARAM_BOOL);
        $mform->addElement('hidden', self::FIELD_IDENTITY_STATUS, '');
        $mform->setType(self::FIELD_IDENTITY_STATUS, PARAM_ALPHANUMEXT);
        $mform->addElement('hidden', self::FIELD_IDENTITY_DISTANCE, '');
        $mform->setType(self::FIELD_IDENTITY_DISTANCE, PARAM_FLOAT);
        $mform->addElement('hidden', self::FIELD_IDENTITY_MESSAGE, '');
        $mform->setType(self::FIELD_IDENTITY_MESSAGE, PARAM_TEXT);

        $html = html_writer::start_div('wcg-workspace', ['id' => 'quizaccess-webcamguard-preflight']);
        $html .= html_writer::start_div('wcg-workspace-head');
        $html .= html_writer::tag('h2', get_string('preflightheader', 'quizaccess_webcamguard'));
        $html .= html_writer::tag('p', get_string('description', 'quizaccess_webcamguard'));
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('wcg-camera-wrap', ['id' => 'wcg-camera-wrap', 'data-face-state' => 'idle']);
        $html .= html_writer::tag('video', '', [
            'id' => 'quizaccess-webcamguard-video', 'autoplay' => 'autoplay', 'playsinline' => 'playsinline',
            'muted' => 'muted', 'style' => 'display:none;',
        ]);
        $html .= html_writer::div(html_writer::tag('span', 'Camera', ['class' => 'wcg-camera-mark']) .
            html_writer::tag('span', get_string('cameraplaceholder', 'quizaccess_webcamguard')), 'wcg-camera-placeholder',
            ['id' => 'wcg-camera-placeholder']);
        $html .= html_writer::end_div();
        $html .= html_writer::tag('canvas', '', ['id' => 'quizaccess-webcamguard-canvas', 'style' => 'display:none;']);
        $html .= self::render_similarity_gauge($this->get_identity_reference_url());
        $html .= html_writer::div('', '', ['id' => 'quizaccess-webcamguard-status', 'role' => 'status']);
        $html .= html_writer::tag('button', get_string('startcheck', 'quizaccess_webcamguard'), [
            'id' => 'quizaccess-webcamguard-startcheck', 'class' => 'wcg-check-btn',
            'data-webcamguard-action' => 'startcheck', 'type' => 'button',
        ]);
        $html .= html_writer::div('', 'wcg-device-status', ['id' => 'quizaccess-webcamguard-device-label']);
        $html .= html_writer::start_div('wcg-consent');
        $html .= html_writer::tag('input', '', ['type' => 'checkbox', 'id' => 'quizaccess-webcamguard-consent']);
        $html .= html_writer::tag('label', get_string('consentlabel', 'quizaccess_webcamguard'),
            ['for' => 'quizaccess-webcamguard-consent']);
        $html .= html_writer::end_div();
        $html .= self::render_preflight_warning();
        $html .= html_writer::end_div();
        $mform->addElement('static', 'webcamguardworkspace', '', $html);

        if ($identityenabled && !$hasprofilepicture) {
            $mform->addElement('static', 'webcamguardprofilewarning', '',
                self::render_profile_picture_required($profileediturl, $identitymode));
        }

        $PAGE->requires->js_call_amd('quizaccess_webcamguard/preflight', 'init', [[
            'readyFieldId' => 'id_' . self::FIELD_READY,
            'readyFieldName' => self::FIELD_READY,
            'consentFieldId' => 'id_' . self::FIELD_CONSENT,
            'consentFieldName' => self::FIELD_CONSENT,
            'deviceValidFieldId' => 'id_webcamguarddevicevalid',
            'deviceLabelId' => 'quizaccess-webcamguard-device-label',
            'deviceMode' => $this->quiz->webcamguard_devicemode ?? 'any',
            'identityStatusFieldId' => 'id_' . self::FIELD_IDENTITY_STATUS,
            'identityStatusFieldName' => self::FIELD_IDENTITY_STATUS,
            'identityDistanceFieldId' => 'id_' . self::FIELD_IDENTITY_DISTANCE,
            'identityDistanceFieldName' => self::FIELD_IDENTITY_DISTANCE,
            'identityMessageFieldId' => 'id_' . self::FIELD_IDENTITY_MESSAGE,
            'identityMessageFieldName' => self::FIELD_IDENTITY_MESSAGE,
            'videoId' => 'quizaccess-webcamguard-video',
            'buttonId' => 'quizaccess-webcamguard-startcheck',
            'statusId' => 'quizaccess-webcamguard-status',
            'similarityBarId' => 'quizaccess-webcamguard-similarity-bar',
            'similarityFillId' => 'quizaccess-webcamguard-similarity-fill',
            'similarityValueId' => 'quizaccess-webcamguard-similarity-value',
            'similarityMarkerId' => 'quizaccess-webcamguard-similarity-marker',
            'identity' => [
                'enabled' => $identityenabled,
                'mode' => $identitymode,
                'threshold' => ((int)$this->quiz->webcamguard_identitythreshold) / 100,
                'scriptUrl' => $CFG->wwwroot . '/mod/quiz/accessrule/webcamguard/faceapi/face-api.min.js',
                'modelBase' => $CFG->wwwroot . '/mod/quiz/accessrule/webcamguard/faceapi/models/',
                'referenceImageUrl' => $this->get_identity_reference_url(),
                'hasProfilePicture' => $hasprofilepicture,
                'profileEditUrl' => $profileediturl,
                'liveIntervalMs' => 800,
                'requiredMatches' => 2,
            ],
            'strings' => [
                'checking' => get_string('checking', 'quizaccess_webcamguard'),
                'ready' => get_string('ready', 'quizaccess_webcamguard'),
                'identitymatched' => get_string('identitymatched', 'quizaccess_webcamguard'),
                'identitymismatch' => get_string('identitymismatch', 'quizaccess_webcamguard'),
                'identityunavailable' => get_string('identityunavailable', 'quizaccess_webcamguard'),
                'identityloading' => get_string('identityloading', 'quizaccess_webcamguard'),
                'identitysearching' => get_string('identitysearching', 'quizaccess_webcamguard'),
                'qualitynoface' => get_string('qualitynoface', 'quizaccess_webcamguard'),
                'qualitymultiple' => get_string('qualitymultiple', 'quizaccess_webcamguard'),
                'qualitytoosmall' => get_string('qualitytoosmall', 'quizaccess_webcamguard'),
                'qualitypose' => get_string('qualitypose', 'quizaccess_webcamguard'),
                'qualitydark' => get_string('qualitydark', 'quizaccess_webcamguard'),
                'identityneedprofileflag' => get_string('identityneedprofileflag', 'quizaccess_webcamguard'),
                'identityneedprofileblock' => get_string('identityneedprofileblock', 'quizaccess_webcamguard'),
                'similaritylabel' => get_string('similaritylabel', 'quizaccess_webcamguard'),
                'similaritythreshold' => get_string('similaritythreshold', 'quizaccess_webcamguard'),
                'permissiondenied' => get_string('permissiondenied', 'quizaccess_webcamguard'),
                'cameranotfound' => get_string('cameranotfound', 'quizaccess_webcamguard'),
                'detectorunavailable' => get_string('detectorunavailable', 'quizaccess_webcamguard'),
                'deviceblocked' => get_string('deviceblocked', 'quizaccess_webcamguard'),
                'deviceany' => get_string('deviceany', 'quizaccess_webcamguard'),
                'devicemobile' => get_string('devicemobile', 'quizaccess_webcamguard'),
                'devicedesktop' => get_string('devicedesktop', 'quizaccess_webcamguard'),
                'devicepassed' => get_string('devicepassed', 'quizaccess_webcamguard'),
                'devicefailed' => get_string('devicefailed', 'quizaccess_webcamguard'),
                'devicerequired' => get_string('devicerequired', 'quizaccess_webcamguard'),
            ],
        ]]);
        if (!$identityenabled) {
            // Same reason as the inline onclick fallback above: delegated fallback
            // cannot run identity matching, so disable it when the richer AMD
            // identity flow is active.
            $PAGE->requires->js_init_code($this->get_delegated_preflight_check_js());
        }
    }

    /**
     * Get the current student's profile image URL for identity verification.
     *
     * Only returns a URL when the user has actually uploaded a profile picture
     * (i.e. $USER->picture > 0). When no picture is set we deliberately return
     * an empty string so the JS layer can render the upload-prompt UI instead
     * of trying to face-match the default Moodle silhouette.
     *
     * @return string
     */
    protected function get_identity_reference_url() {
        global $CFG, $PAGE, $USER;

        if (empty($USER->picture)) {
            return '';
        }

        // Build f1 URL directly — theme's user_picture overrides to f2 (35px) which is too small.
        // f1 = 100x100px, minimum usable for face-api detection.
        $usercontext = \context_user::instance($USER->id);
        return $CFG->wwwroot . '/pluginfile.php/' . $usercontext->id . '/user/icon/f1?rev=' . $USER->picture;
    }


    /**
     * Render a circular gauge similarity indicator.
     *
     * @param string $referenceurl Profile image URL.
     * @return string
     */
    protected static function render_similarity_gauge($referenceurl = '') {
        $output = html_writer::start_div('wcg-similarity-panel', [
            'id' => 'quizaccess-webcamguard-similarity-bar',
            'style' => 'display:flex;align-items:center;gap:0.8rem;width:100%;margin-top:0.65rem;padding:0.7rem 0.8rem;',
        ]);
        if (!empty($referenceurl)) {
            $output .= html_writer::img($referenceurl, get_string('similaritylabel', 'quizaccess_webcamguard'), [
                'class' => 'wcg-reference-img',
                'style' => 'display:block;width:44px;height:44px;flex:0 0 44px;border-radius:50%;object-fit:cover;',
            ]);
        }
        $output .= '<div class="wcg-gauge" style="position:relative;width:56px;height:56px;flex:0 0 56px">'
            . '<svg viewBox="0 0 64 64" width="56" height="56" style="display:block;width:56px;height:56px;transform:rotate(-90deg)">'
            . '<circle class="wcg-gauge-bg" cx="32" cy="32" r="26" fill="none" stroke="#dfe6f0" stroke-width="6"></circle>'
            . '<circle class="wcg-gauge-fill" id="quizaccess-webcamguard-similarity-fill" cx="32" cy="32" r="26"'
            . ' fill="none" stroke="#2563eb" stroke-width="6" stroke-linecap="round"'
            . ' stroke-dasharray="163.36" stroke-dashoffset="163.36" data-state="searching"></circle>'
            . '</svg><div class="wcg-gauge-value" id="quizaccess-webcamguard-similarity-value"'
            . ' style="position:absolute;inset:0;display:grid;place-items:center;font-weight:700">0%</div></div>';
        $output .= html_writer::start_div('wcg-similarity-info', ['style' => 'min-width:0;flex:1 1 auto']);
        $output .= html_writer::div(get_string('similaritylabel', 'quizaccess_webcamguard'), 'wcg-similarity-label');
        $output .= html_writer::div(get_string('identitysearching', 'quizaccess_webcamguard'),
            'wcg-similarity-status', ['id' => 'wcg-similarity-status', 'data-state' => 'searching']);
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        return $output;
    }

    /**
     * Render a banner that prompts the student to upload a profile picture.
     *
     * @param string $profileediturl URL to the profile edit page.
     * @param string $identitymode Identity verification mode ("flag" or "block").
     * @return string
     */
    protected static function render_profile_picture_required($profileediturl, $identitymode) {
        $isblock = ($identitymode === 'block');
        $alertclass = $isblock ? 'alert alert-danger' : 'alert alert-warning';
        $messagekey = $isblock ? 'identityneedprofileblock' : 'identityneedprofileflag';

        $output = html_writer::start_div($alertclass . ' quizaccess-webcamguard-profilewarning', [
            'id' => 'quizaccess-webcamguard-profilewarning',
        ]);
        $output .= html_writer::tag('h6', get_string('identityneedprofiletitle', 'quizaccess_webcamguard'), [
            'class' => 'alert-heading',
        ]);
        $output .= html_writer::tag('p', get_string($messagekey, 'quizaccess_webcamguard'), ['class' => 'mb-2']);
        $output .= html_writer::link($profileediturl,
            get_string('uploadprofilepicture', 'quizaccess_webcamguard'),
            ['class' => 'btn btn-primary btn-sm', 'target' => '_blank', 'rel' => 'noopener']);
        $output .= html_writer::end_div();
        return $output;
    }

    /**
     * Render the student-facing Webcam Guard warning.
     *
     * @return string
     */
    protected static function render_preflight_warning() {
        $items = [
            get_string('preflightruleface', 'quizaccess_webcamguard'),
            get_string('preflightrulemultiplefaces', 'quizaccess_webcamguard'),
            get_string('preflightruletab', 'quizaccess_webcamguard'),
            get_string('preflightrulecamera', 'quizaccess_webcamguard'),
        ];

        $output = html_writer::start_tag('details', ['class' => 'wcg-rules']);
        $output .= html_writer::tag('summary', get_string('preflightwarningtitle', 'quizaccess_webcamguard'));
        $output .= html_writer::tag('p', get_string('preflightmessage', 'quizaccess_webcamguard'));
        $output .= html_writer::start_tag('ul');
        foreach ($items as $item) {
            $output .= html_writer::tag('li', $item);
        }
        $output .= html_writer::end_tag('ul');
        $output .= html_writer::tag('p', get_string('preflightwarningfooter', 'quizaccess_webcamguard'), [
            'class' => 'wcg-rules-note',
        ]);
        $output .= html_writer::end_tag('details');

        return $output;
    }

    /**
     * Validate preflight readiness.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @param array $errors Errors so far.
     * @param int|null $attemptid Attempt id.
     * @return array
     */
    public function validate_preflight_check($data, $files, $errors, $attemptid) {
        global $SESSION, $USER;

        // User asked to keep Start attempt always functional regardless of
        // webcam/identity state. We deliberately skip the consent/ready and
        // identity gating here so submission always succeeds; the live UI
        // (similarity bar, status text) keeps showing the verification info
        // for transparency, but it no longer blocks quiz entry.
        //
        // We still record what the client reported into session so that the
        // teacher review screen can flag suspicious attempts (mode=flag
        // semantics), even though we no longer hard-block.

        if (!empty($this->quiz->webcamguard_identityenabled)) {
            $identitystatus = isset($data[self::FIELD_IDENTITY_STATUS]) ? $data[self::FIELD_IDENTITY_STATUS] : '';
            $identitydistance = isset($data[self::FIELD_IDENTITY_DISTANCE]) ? $data[self::FIELD_IDENTITY_DISTANCE] : '';
            $identitymessage = isset($data[self::FIELD_IDENTITY_MESSAGE]) ? $data[self::FIELD_IDENTITY_MESSAGE] : '';

            $SESSION->webcamguardidentity[$this->get_session_key($attemptid)] = [
                'status' => $identitystatus,
                'distance' => $identitydistance,
                'message' => $identitymessage,
                'threshold' => ((int)$this->quiz->webcamguard_identitythreshold) / 100,
                'mode' => $this->quiz->webcamguard_identitymode,
            ];
            $SESSION->webcamguardidentity[$this->get_session_key(0)] =
                $SESSION->webcamguardidentity[$this->get_session_key($attemptid)];
        }
        return $errors;
    }

    /**
     * Mark preflight as passed in session.
     *
     * @param int|null $attemptid Attempt id.
     */
    public function notify_preflight_check_passed($attemptid) {
        global $SESSION, $DB;
        $SESSION->webcamguardchecked[$this->get_session_key($attemptid)] = true;
        // Also store under the fallback key so subsequent checks that already know the
        // real attempt id (summary.php / attempt.php) can match when this notification
        // arrived from startattempt.php with attemptid still unallocated (=0).
        $SESSION->webcamguardchecked[$this->get_session_key(0)] = true;

        // Store identity check event server-side for teacher review.
        // Only when identity is enabled AND we have actual identity data.
        // monitor.js also sends identity_check via the external API, so this
        // server-side insert only fires once at preflight time.
        if ($attemptid > 0 && !empty($this->quiz->webcamguard_identityenabled)) {
            $attemptrecord = $DB->get_record('quiz_attempts', ['id' => (int)$attemptid], 'id, userid');
            if ($attemptrecord) {
                $identitykey = $this->get_session_key($attemptid);
                $fallbackkey = $this->get_session_key(0);
                $identitydata = !empty($SESSION->webcamguardidentity[$identitykey])
                    ? $SESSION->webcamguardidentity[$identitykey]
                    : (!empty($SESSION->webcamguardidentity[$fallbackkey])
                        ? $SESSION->webcamguardidentity[$fallbackkey] : []);
                if (!empty($identitydata)) {
                    $status = $identitydata['status'] ?? '';
                    $severity = ($status === 'match') ? 'info' : (($status === 'mismatch') ? 'violation' : 'warning');
                    $event = (object)[
                        'attemptid' => (int)$attemptid,
                        'quizid' => (int)$this->quiz->id,
                        'courseid' => (int)$this->quizobj->get_courseid(),
                        'cmid' => (int)$this->quizobj->get_cmid(),
                        'userid' => (int)$attemptrecord->userid,
                        'eventtype' => 'identity_check',
                        'severity' => $severity,
                        'durationms' => 0,
                        'clienttime' => time(),
                        'metadata' => json_encode($identitydata),
                        'timecreated' => time(),
                    ];
                    $DB->insert_record('quizaccess_wg_events', $event);
                }
            }
        }
    }

    /**
     * Clear preflight flag when attempt is finished.
     */
    public function current_attempt_finished() {
        global $SESSION;
        if (!empty($SESSION->webcamguardchecked)) {
            foreach (array_keys($SESSION->webcamguardchecked) as $key) {
                if (strpos($key, $this->quiz->id . ':') === 0) {
                    unset($SESSION->webcamguardchecked[$key]);
                }
            }
        }
        if (!empty($SESSION->webcamguardidentity)) {
            foreach (array_keys($SESSION->webcamguardidentity) as $key) {
                if (strpos($key, $this->quiz->id . ':') === 0) {
                    unset($SESSION->webcamguardidentity[$key]);
                }
            }
        }
    }

    /**
     * Initialise attempt page monitoring.
     *
     * @param moodle_page $page Page object.
     */
    public function setup_attempt_page($page) {
        global $CFG, $PAGE, $SESSION;

        if ($this->is_real_preview_user()) {
            return;
        }

        if ($PAGE->pagetype !== 'mod-quiz-attempt') {
            return;
        }

        $attemptid = optional_param('attempt', 0, PARAM_INT);
        if (!$attemptid) {
            return;
        }

        $page->requires->js_call_amd('quizaccess_webcamguard/monitor', 'init', [[
            'courseid' => $this->quizobj->get_courseid(),
            'cmid' => $this->quizobj->get_cmid(),
            'quizid' => $this->quizobj->get_quizid(),
            'attemptid' => $attemptid,
            'snapshotonviolation' => !empty($this->quiz->webcamguard_snapshotonviolation),
            'intervalseconds' => (int)$this->quiz->webcamguard_intervalseconds,
            'nofacethreshold' => (int)$this->quiz->webcamguard_nofacethreshold,
            'multifacethreshold' => (int)$this->quiz->webcamguard_multifacethreshold,
            'blurthreshold' => (int)$this->quiz->webcamguard_blurthreshold,
            'mediapipebase' => $CFG->wwwroot . '/mod/quiz/accessrule/webcamguard/mediapipe/face_detection/',
            'identityResult' => $this->get_identity_result_for_attempt($attemptid),
        ]]);

        if (!empty($this->quiz->webcamguard_liveenabled) && self::livekit_is_configured()) {
            $page->requires->js_call_amd('quizaccess_webcamguard/live_student', 'init', [[
                'courseid' => $this->quizobj->get_courseid(),
                'cmid' => $this->quizobj->get_cmid(),
                'quizid' => $this->quizobj->get_quizid(),
                'attemptid' => $attemptid,
                'scriptUrl' => $CFG->wwwroot . '/mod/quiz/accessrule/webcamguard/livekit/livekit-client.umd.js',
                'pollSeconds' => 5,
                'strings' => [
                    'starting' => get_string('livestarting', 'quizaccess_webcamguard'),
                    'live' => get_string('liveactive', 'quizaccess_webcamguard'),
                    'stopped' => get_string('livestopped', 'quizaccess_webcamguard'),
                    'failed' => get_string('livefailed', 'quizaccess_webcamguard'),
                    'warningFromTeacher' => get_string('warningfromteacher', 'quizaccess_webcamguard'),
                ],
            ]]);
        }
    }

    /**
     * Whether LiveKit has enough global config to connect.
     *
     * @return bool
     */
    protected static function livekit_is_configured() {
        $url = trim((string)get_config('quizaccess_webcamguard', 'livekiturl'));
        $apikey = trim((string)get_config('quizaccess_webcamguard', 'livekitapikey'));
        $secret = trim((string)get_config('quizaccess_webcamguard', 'livekitsecret'));
        return $url !== '' && $apikey !== '' && $secret !== '';
    }

    /**
     * Get identity result captured during preflight.
     *
     * @param int $attemptid Attempt id.
     * @return array|null
     */
    protected function get_identity_result_for_attempt($attemptid) {
        global $SESSION;

        if (empty($SESSION->webcamguardidentity)) {
            return null;
        }

        $key = $this->get_session_key($attemptid);
        if (!empty($SESSION->webcamguardidentity[$key])) {
            return $SESSION->webcamguardidentity[$key];
        }

        $fallbackkey = $this->get_session_key(0);
        return !empty($SESSION->webcamguardidentity[$fallbackkey]) ? $SESSION->webcamguardidentity[$fallbackkey] : null;
    }


    /**
     * Build a delegated click fallback for pages where AMD binding or inline attributes are not applied.
     *
     * @return string JavaScript code.
     */
    protected function get_delegated_preflight_check_js() {
        $config = [
            'readyFieldId' => 'id_' . self::FIELD_READY,
            'readyFieldName' => self::FIELD_READY,
            'videoId' => 'quizaccess-webcamguard-video',
            'statusId' => 'quizaccess-webcamguard-status',
            'identityEnabled' => !empty($this->quiz->webcamguard_identityenabled),
            'checking' => get_string('checking', 'quizaccess_webcamguard'),
            'ready' => get_string('ready', 'quizaccess_webcamguard'),
            'identityunavailable' => get_string('identityunavailable', 'quizaccess_webcamguard'),
            'permissiondenied' => get_string('permissiondenied', 'quizaccess_webcamguard'),
            'cameranotfound' => get_string('cameranotfound', 'quizaccess_webcamguard'),
        ];
        $json = json_encode($config);

        return "(function(c){" .
            "if(window.quizaccessWebcamguardPreflightFallback){return;}" .
            "window.quizaccessWebcamguardPreflightFallback=true;" .
            "function isBtn(el){var label=(el&&(el.value||el.textContent)||'').replace(/^\\s+|\\s+$/g,'').toLowerCase();" .
                "return !!el&&(el.id==='quizaccess-webcamguard-startcheck'||el.id==='id_webcamguardstartcheck'||" .
                "el.name==='webcamguardstartcheck'||el.getAttribute('data-webcamguard-action')==='startcheck'||" .
                "label==='check webcam'||label==='periksa webcam');}" .
            "function run(b){" .
                "var r=document.getElementById(c.readyFieldId)||document.getElementsByName(c.readyFieldName)[0]," .
                    "v=document.getElementById(c.videoId),s=document.getElementById(c.statusId);" .
                "if(!s){s=document.createElement('div');s.id=c.statusId;s.className='mt-2';b.parentNode.insertBefore(s,b.nextSibling);}" .
                "if(!r){s.className='mt-2 alert alert-danger';s.textContent='Webcam Guard form field is missing. Reload this page and try again.';return;}" .
                "if(!v){v=document.createElement('video');v.id=c.videoId;v.autoplay=true;v.playsInline=true;v.muted=true;" .
                    "v.style.maxWidth='320px';v.style.width='100%';v.style.display='none';v.style.marginBottom='0.5rem';" .
                    "b.parentNode.insertBefore(v,b);}" .
                "r.value='0';s.className='mt-2 alert alert-info';s.textContent=c.checking;" .
                "if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){" .
                    "s.className='mt-2 alert alert-danger';s.textContent=c.cameranotfound;return;}" .
                "navigator.mediaDevices.getUserMedia({video:true,audio:false}).then(function(stream){" .
                    "v.srcObject=stream;v.style.display='block';return v.play().catch(function(){});" .
                "}).then(function(){if(c.identityEnabled){s.className='mt-2 alert alert-danger';s.textContent=c.identityunavailable;return;}" .
                    "r.value='1';s.className='mt-2 alert alert-success';s.textContent=c.ready;})" .
                ".catch(function(e){s.className='mt-2 alert alert-danger';" .
                    "s.textContent=(e&&(e.name==='NotAllowedError'||e.name==='PermissionDeniedError'))?" .
                    "c.permissiondenied:c.cameranotfound;});" .
            "}" .
            "document.addEventListener('click',function(e){var t=e.target;" .
                "if(!isBtn(t)&&t&&t.closest){t=t.closest('#quizaccess-webcamguard-startcheck,#id_webcamguardstartcheck,'+" .
                    "'[name=\"webcamguardstartcheck\"],[data-webcamguard-action=\"startcheck\"],button,input');}" .
                "if(!isBtn(t)){return;}e.preventDefault();run(t);},true);" .
        "}($json));";
    }

    /**
     * Build a session key for a preflight pass.
     *
     * @param int|null $attemptid Attempt id.
     * @return string
     */
    protected function get_session_key($attemptid) {
        return $this->quiz->id . ':' . (int)$attemptid;
    }

    /**
     * Whether the current user should be treated as a real quiz preview user.
     *
     * Moodle's quiz::is_preview_user() returns true for anyone with the
     * mod/quiz:preview capability, which includes site admins even after they
     * "Switch role to Student". For Webcam Guard that's wrong: a teacher (or
     * admin) who deliberately impersonates a student to validate the
     * proctoring flow should go through preflight just like a real student
     * would.
     *
     * We therefore treat the user as a real preview user only when:
     *   - they have mod/quiz:preview, AND
     *   - they are NOT currently role-switched in this course.
     *
     * Real previewers (teachers using "Preview quiz now") still bypass
     * preflight; real students are unaffected because they don't have the
     * preview capability in the first place.
     *
     * @return bool
     */
    protected function is_real_preview_user() {
        if (!$this->quizobj->is_preview_user()) {
            return false;
        }
        $courseid = $this->quizobj->get_courseid();
        if (function_exists('is_role_switched') && is_role_switched($courseid)) {
            return false;
        }
        return true;
    }
}
