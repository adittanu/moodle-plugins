// This file is part of Moodle - http://moodle.org/

/**
 * Student-side optional LiveKit publisher for Webcam Guard.
 *
 * @module     quizaccess_webcamguard/live_student
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'require'], function(ajax, require) {
    var state = {
        room: null,
        stream: null,
        pollTimer: null,
        stopping: false,
        currentRoom: '',
        statusNode: null
    };

    var call = function(methodname, args) {
        return ajax.call([{
            methodname: methodname,
            args: args
        }])[0];
    };

    var logEvent = function(config, eventtype, metadata) {
        return call('quizaccess_webcamguard_log_event', {
            courseid: config.courseid,
            cmid: config.cmid,
            quizid: config.quizid,
            attemptid: config.attemptid,
            eventtype: eventtype,
            durationms: 0,
            clienttime: Date.now(),
            metadata: JSON.stringify(metadata || {}),
            snapshot: ''
        }).catch(function() {
            return null;
        });
    };

    var setStatus = function(config, text) {
        if (!state.statusNode) {
            state.statusNode = document.createElement('div');
            state.statusNode.style.position = 'fixed';
            state.statusNode.style.left = '12px';
            state.statusNode.style.bottom = '12px';
            state.statusNode.style.zIndex = '9999';
            state.statusNode.style.padding = '8px 10px';
            state.statusNode.style.borderRadius = '6px';
            state.statusNode.style.background = 'rgba(25, 31, 38, 0.92)';
            state.statusNode.style.color = '#fff';
            state.statusNode.style.fontSize = '13px';
            state.statusNode.style.boxShadow = '0 6px 18px rgba(0, 0, 0, 0.18)';
            state.statusNode.hidden = true;
            document.body.appendChild(state.statusNode);
        }

        state.statusNode.textContent = text;
        state.statusNode.hidden = !text;
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

    var stopLocalTracks = function() {
        if (state.stream) {
            state.stream.getTracks().forEach(function(track) {
                track.stop();
            });
            state.stream = null;
        }
    };

    var disconnect = function(config, text, logtype) {
        var room = state.room;
        state.stopping = true;
        state.room = null;
        if (room) {
            room.disconnect();
        }
        stopLocalTracks();
        state.currentRoom = '';
        window.setTimeout(function() {
            state.stopping = false;
        }, 1000);
        setStatus(config, text || '');
        if (logtype) {
            logEvent(config, logtype, {});
        }
    };

    var connect = function(config, live) {
        if (state.room && state.currentRoom === live.roomname) {
            return Promise.resolve();
        }

        disconnect(config, '', null);
        setStatus(config, config.strings.starting);

        return loadLiveKit(config.scriptUrl).then(function(LK) {
            var room = new LK.Room({
                adaptiveStream: true,
                dynacast: true
            });

            room.on(LK.RoomEvent.Disconnected, function() {
                if (!state.stopping) {
                    logEvent(config, 'live_disconnected', {
                        room: state.currentRoom
                    });
                    setStatus(config, config.strings.stopped);
                }
                state.room = null;
                stopLocalTracks();
                state.currentRoom = '';
            });

            state.room = room;
            state.currentRoom = live.roomname;

            return room.connect(live.url, live.token, {
                autoSubscribe: false
            }).then(function() {
                return navigator.mediaDevices.getUserMedia({
                    audio: false,
                    video: {
                        width: {ideal: 320},
                        height: {ideal: 240},
                        frameRate: {ideal: 10, max: 10},
                        facingMode: 'user'
                    }
                });
            }).then(function(stream) {
                state.stream = stream;
                return room.localParticipant.publishTrack(stream.getVideoTracks()[0], {
                    name: 'webcamguard-live'
                });
            }).then(function() {
                setStatus(config, config.strings.live);
                return logEvent(config, 'live_started', {
                    room: live.roomname,
                    expiresAt: live.expiresat
                });
            });
        }).catch(function(error) {
            disconnect(config, config.strings.failed, null);
            return logEvent(config, 'live_failed', {
                room: live.roomname,
                message: error && error.message ? error.message : String(error)
            });
        });
    };

    var poll = function(config) {
        return call('quizaccess_webcamguard_poll_live', {
            courseid: config.courseid,
            cmid: config.cmid,
            quizid: config.quizid,
            attemptid: config.attemptid
        }).then(function(live) {
            if (live && live.active) {
                connect(config, live);
                return;
            }
            if (state.room) {
                disconnect(config, config.strings.stopped, 'live_stopped');
            }
        }).catch(function() {
            if (state.room) {
                disconnect(config, config.strings.failed, 'live_failed');
            }
        });
    };

    return {
        init: function(config) {
            if (!config || !config.scriptUrl || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                return;
            }

            state.pollTimer = window.setInterval(function() {
                poll(config);
            }, Math.max(3, config.pollSeconds || 5) * 1000);
            // ponytail: first poll failure should not kill polling; the interval keeps retrying.
            poll(config).catch(function() {
                // Swallow — interval will retry.
            });

            window.addEventListener('beforeunload', function() {
                if (state.pollTimer) {
                    window.clearInterval(state.pollTimer);
                    state.pollTimer = null;
                }
                disconnect(config, '', null);
            });
        }
    };
});
