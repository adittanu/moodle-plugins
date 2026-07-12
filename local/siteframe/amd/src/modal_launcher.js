// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * SiteFrame modal launcher AMD module.
 *
 * @module      local_siteframe/modal_launcher
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/modal_factory', 'core/modal_events'], function($, ModalFactory, ModalEvents) {
    return {
        init: function() {
            $(document).on('click', '.siteframe-modal-trigger', function(e) {
                e.preventDefault();

                var url = $(this).data('url');
                var sandbox = $(this).data('sandbox') || 'allow-scripts allow-same-origin allow-popups';
                var title = $(this).data('title') || 'SiteFrame';

                var iframeHtml = '<iframe src="' + url + '" ' +
                    'width="100%" height="600" frameborder="0" ' +
                    'allowfullscreen="true" sandbox="' + sandbox + '" ' +
                    'class="siteframe-iframe siteframe-display-modal"></iframe>';

                ModalFactory.create({
                    title: title,
                    body: iframeHtml,
                    type: ModalFactory.types.DEFAULT,
                    large: true
                }).then(function(modal) {
                    modal.show();

                    modal.getRoot().on(ModalEvents.hidden, function() {
                        // Remove iframe src to stop loading when modal closes.
                        modal.getRoot().find('iframe').attr('src', 'about:blank');
                        modal.destroy();
                    });

                    return;
                }).catch(function(err) {
                    window.console.error('SiteFrame modal error:', err);
                });
            });
        }
    };
});
