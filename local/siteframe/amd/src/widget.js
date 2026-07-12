// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * SiteFrame floating widget AMD module.
 *
 * @module      local_siteframe/widget
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    return {
        init: function(configJson) {
            var config = JSON.parse(configJson);

            var $container = $('.siteframe-widget-container');
            var $button = $container.find('.siteframe-widget-button');
            var $panel = $container.find('.siteframe-widget-panel');
            var $iframe = $container.find('.siteframe-widget-iframe');

            var isOpen = false;

            // Toggle panel on button click.
            $button.on('click', function() {
                if (isOpen) {
                    closePanel();
                } else {
                    openPanel();
                }
            });

            function openPanel() {
                isOpen = true;
                $panel.addClass('siteframe-panel-open');
                $button.addClass('siteframe-button-active');

                // Lazy-load iframe src only when panel opens.
                if (!$iframe.attr('src') || $iframe.attr('src') === 'about:blank') {
                    $iframe.attr('src', config.url);
                }
            }

            function closePanel() {
                isOpen = false;
                $panel.removeClass('siteframe-panel-open');
                $button.removeClass('siteframe-button-active');
            }

            // Escape key to close.
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && isOpen) {
                    closePanel();
                }
            });

            // Close when clicking outside.
            $(document).on('click', function(e) {
                if (isOpen && !$container[0].contains(e.target)) {
                    closePanel();
                }
            });
        }
    };
});
