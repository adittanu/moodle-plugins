// This file is part of Moodle - http://moodle.org/

/**
 * Teacher-side optional LiveKit viewer for Webcam Guard.
 *
 * @module     quizaccess_webcamguard/live_teacher
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'require'], function(ajax, require) {
    var state = {
        room: null,
        video: null,
        currentRoom: ''
    };

    var call = function(methodname, args) {
        return ajax.call([{
            methodname: methodname,
            args: args
        }])[0];
    };

    var loadLiveKit = function(scriptUrl) {
        if (window.LivekitClient && window.LivekitClient.Room) {
            return Promise.resolve(window.LivekitClient);
        }

        return new Promise(function(resolve, reject) {
            require([scriptUrl], function(livekit) {
                var sdk = livekit || window.LivekitClient;
                if (sdk && sdk.Room) {
                    resolve(sdk);
                    return;
                }
                reject(new Error('LiveKit SDK unavailable.'));
            }, reject);
        });
    };

    var setStatus = function(root, message) {
        var node = root.querySelector('[data-region="webcamguard-live-status"]');
        if (node) {
            node.textContent = message;
        }
    };

    var clearVideo = function(root) {
        var region = root.querySelector('[data-region="webcamguard-live-video"]');
        if (state.video && state.video.parentNode) {
            state.video.parentNode.removeChild(state.video);
        }
        state.video = null;
        if (region) {
            region.innerHTML = '';
        }
    };

    var stopRoom = function(root, status) {
        if (state.room) {
            state.room.disconnect();
            state.room = null;
        }
        state.currentRoom = '';
        clearVideo(root);
        setStatus(root, status);
    };

    var attachTrack = function(root, track) {
        var region = root.querySelector('[data-region="webcamguard-live-video"]');
        if (!region || !track || (track.kind && track.kind !== 'video')) {
            return;
        }
        clearVideo(root);
        state.video = track.attach();
        state.video.autoplay = true;
        state.video.playsInline = true;
        state.video.muted = true;
        state.video.style.width = '100%';
        state.video.style.maxWidth = '520px';
        state.video.style.background = '#111827';
        state.video.style.borderRadius = '6px';
        region.appendChild(state.video);
    };

    var start = function(config, root) {
        setStatus(root, config.strings.starting);
        return call('quizaccess_webcamguard_request_live', {
            courseid: config.courseid,
            cmid: config.cmid,
            quizid: config.quizid,
            attemptid: config.attemptid,
            action: 'start'
        }).then(function(live) {
            if (!live || !live.active) {
                setStatus(root, config.strings.notconfigured);
                return null;
            }

            return loadLiveKit(config.scriptUrl).then(function(LK) {
                stopRoom(root, '');

                var room = new LK.Room({
                    adaptiveStream: true,
                    dynacast: true
                });
                state.room = room;
                state.currentRoom = live.roomname;

                room.on(LK.RoomEvent.TrackSubscribed, function(track) {
                    attachTrack(root, track);
                    setStatus(root, config.strings.connected);
                });
                room.on(LK.RoomEvent.ParticipantConnected, function() {
                    setStatus(root, config.strings.waiting);
                });
                room.on(LK.RoomEvent.Disconnected, function() {
                    clearVideo(root);
                    state.room = null;
                    state.currentRoom = '';
                });

                return room.connect(live.url, live.token, {
                    autoSubscribe: true
                }).then(function() {
                    setStatus(root, config.strings.waiting);
                });
            });
        }).catch(function(error) {
            setStatus(root, config.strings.failed + (error && error.message ? ' ' + error.message : ''));
        });
    };

    var stop = function(config, root) {
        return call('quizaccess_webcamguard_request_live', {
            courseid: config.courseid,
            cmid: config.cmid,
            quizid: config.quizid,
            attemptid: config.attemptid,
            action: 'stop'
        }).then(function() {
            stopRoom(root, config.strings.stopped);
        }).catch(function() {
            stopRoom(root, config.strings.stopped);
        });
    };

    return {
        init: function(config) {
            var root = document.querySelector('[data-region="webcamguard-live-panel"]');
            if (!root) {
                return;
            }

            var startButton = root.querySelector('[data-action="webcamguard-live-start"]');
            var stopButton = root.querySelector('[data-action="webcamguard-live-stop"]');

            if (startButton) {
                startButton.addEventListener('click', function() {
                    start(config, root);
                });
            }
            if (stopButton) {
                stopButton.addEventListener('click', function() {
                    stop(config, root);
                });
            }
        }
    };
});
