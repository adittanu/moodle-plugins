<?php
defined('MOODLE_INTERNAL') || die();

// Only register hook if class exists (Moodle 5+).
if (class_exists('\core\hook\output\before_footer_html_generation')) {
    $callbacks = [
        [
            'hook' => \core\hook\output\before_footer_html_generation::class,
            'callback' => \local_quiz_stats_cache\hook\before_footer_callback::class . '::inject_button',
        ],
    ];
}
