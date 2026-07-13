<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_quiz_stats_cache_get_quiz_stats' => [
        'classname' => 'local_quiz_stats_cache\external\get_quiz_stats',
        'methodname' => 'execute',
        'description' => 'Get cached quiz statistics (pre-calculated, fast).',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
