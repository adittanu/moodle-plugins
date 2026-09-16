# Live Monitor regression tests

Install the development dependencies in this directory with `npm install`, then run `npm test`.
The tests execute the actual AMD module in jsdom, with controlled AJAX, LiveKit rooms, and timers.
They replace the earlier text-only mock, which could not detect nested cards or detached video nodes.

Set `WCG_TEST_SOURCE` to an absolute path to `amd/build/live_dashboard.min.js` to test the production build.
Set `WCG_BROWSER_QA` to a Chrome executable path to enable the real-browser layout check.
Optionally set `WCG_SCREENSHOT` to a PNG output path for the desktop rendering.

Tests cover sibling cards, polling with changing participants, retained video and message drafts,
duplicate initialization, native and jQuery modal events, cancellation of pending starts,
stop/start ordering, late disconnect callbacks, failed connection cleanup, and responsive columns.

## Deployment verification

Deploy the source and generated AMD build, purge Moodle caches, then reload the report tab.
The dashboard root should have `data-webcamguard-build="20260916-dom-lifecycle"`.
This identifies the loaded JavaScript without changing the plugin version.

The tests do not connect to a real LiveKit server or validate a student's camera permissions.
