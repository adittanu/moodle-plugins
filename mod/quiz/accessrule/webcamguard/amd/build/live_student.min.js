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
        currentRoom: ''
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
        if (logtype) {
            logEvent(config, logtype, {});
        }
    };

    var connect = function(config, live) {
        if (state.room && state.currentRoom === live.roomname) {
            return Promise.resolve();
        }

        disconnect(config, '', null);

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
                return logEvent(config, 'live_started', {
                    room: live.roomname,
                    expiresAt: live.expiresat
                });
            });
        }).catch(function(error) {
            disconnect(config, '', null);
            return logEvent(config, 'live_failed', {
                room: live.roomname,
                message: error && error.message ? error.message : String(error)
            });
        });
    };

    var showWarning = function(config, message) {
        var previous = document.querySelector('[data-region="webcamguard-teacher-message"]');
        if (previous) {
            previous.remove();
        }

        var overlay = document.createElement('div');
        overlay.dataset.region = 'webcamguard-teacher-message';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.58);padding:24px;';

        var dialog = document.createElement('section');
        dialog.setAttribute('role', 'alertdialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', 'webcamguard-teacher-message-title');
        dialog.setAttribute('aria-describedby', 'webcamguard-teacher-message-body');
        dialog.style.cssText = 'width:min(440px,100%);overflow:hidden;background:#fff;border:1px solid #dfe3e8;border-radius:14px;box-shadow:0 20px 48px rgba(15,23,42,.24);';

        var header = document.createElement('div');
        header.style.cssText = 'padding:20px 24px 12px;';
        header.innerHTML = '<div style="margin-bottom:6px;color:#667085;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;">Webcam Guard</div>' +
            '<h2 id="webcamguard-teacher-message-title" style="margin:0;color:#1d2939;font-size:20px;font-weight:650;line-height:1.3;">Pesan dari pengajar</h2>';

        var body = document.createElement('p');
        body.id = 'webcamguard-teacher-message-body';
        body.style.cssText = 'margin:0;padding:4px 24px 22px;color:#344054;font-size:16px;line-height:1.6;white-space:pre-wrap;overflow-wrap:anywhere;';
        body.textContent = message;

        var footer = document.createElement('div');
        footer.style.cssText = 'display:flex;justify-content:flex-end;padding:14px 24px;background:#f8fafc;border-top:1px solid #eaecf0;';
        var dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'btn btn-primary';
        dismiss.textContent = 'Mengerti';
        footer.appendChild(dismiss);

        dialog.appendChild(header);
        dialog.appendChild(body);
        dialog.appendChild(footer);
        overlay.appendChild(dialog);

        var onKeydown = function(event) {
            if (event.key === 'Escape') {
                close();
            }
        };
        var close = function() {
            document.removeEventListener('keydown', onKeydown);
            overlay.remove();
        };
        dismiss.addEventListener('click', close);
        document.addEventListener('keydown', onKeydown);
        document.body.appendChild(overlay);
        dismiss.focus();
        setTimeout(close, 30000);
    };

    var poll = function(config) {
        return call('quizaccess_webcamguard_poll_live', {
            courseid: config.courseid,
            cmid: config.cmid,
            quizid: config.quizid,
            attemptid: config.attemptid
        }).then(function(live) {
            // Show warning if any (even if no active live session).
            if (live && live.warning) {
                showWarning(config, live.warning);
            }
            if (live && live.active) {
                // Only connect to LiveKit if there's a room.
                if (live.roomname) {
                    connect(config, live);
                }
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
