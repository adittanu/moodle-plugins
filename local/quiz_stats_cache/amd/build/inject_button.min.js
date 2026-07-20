define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    return {
        init: function() {
            // Only run on quiz statistics report page.
            var url = window.location.href;
            if (url.indexOf('/mod/quiz/report.php') === -1) return;
            if (url.indexOf('mode=statistics') === -1) return;

            // Extract cmid from URL.
            var match = url.match(/[?&]id=(\d+)/);
            if (!match) return;
            var cmid = match[1];

            // Get sesskey from the page.
            // Get sesskey from Moodle global config.
            var sesskey = (typeof M !== 'undefined' && M.cfg && M.cfg.sesskey) ? M.cfg.sesskey : '';
            // Build URLs - use id (cmid) since that's what we have from the page URL.
            var baseUrl = M.cfg.wwwroot + '/local/quiz_stats_cache/recalculate.php';
            var fastUrl = baseUrl + '?id=' + cmid + '&sesskey=' + sesskey;
            var forceUrl = baseUrl + '?id=' + cmid + '&sesskey=' + sesskey + '&force=1';

            // Create button container.
            var html = '<div class="card mb-3" style="border-left: 4px solid #28a745;">' +
                '<div class="card-body py-2">' +
                '<strong class="text-success">&#9889; Fast Statistics Calculator</strong>' +
                '<p class="mb-2 small text-muted">Recalculate statistics using SQL-based calculator (instant).</p>' +
                '<a href="' + fastUrl + '" class="btn btn-sm btn-success mr-2" title="Only recalculates if there are changes">&#9889; Recalculate (fast)</a>' +
                '<a href="' + forceUrl + '" class="btn btn-sm btn-outline-success" title="Always recalculates">&#9889; Force Recalculate</a>' +
                '</div></div>';

            // Insert before the main content.
            var $target = $('.quiz_statistics-summarytable, .questionstatistics, #tablecontainer, .generalbox').first();
            if ($target.length) {
                $target.before(html);
            } else {
                // Fallback: insert after page heading.
                $('h2, .page-header-headings').first().after(html);
            }
        }
    };
});
