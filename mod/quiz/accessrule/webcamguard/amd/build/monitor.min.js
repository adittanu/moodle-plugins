// This file is part of Moodle - http://moodle.org/

/**
 * Webcam Guard attempt monitor.
 *
 * Uses a local MediaPipe Face Detection bundle first, then the browser-native FaceDetector API as fallback.
 *
 * @module     quizaccess_webcamguard/monitor
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(ajax) {
    window.quizaccessWebcamguardActiveAttempts = window.quizaccessWebcamguardActiveAttempts || {};

    var state = {
        stream: null,
        video: null,
        canvas: null,
        detector: null,
        detectorType: null,
        detectorUnavailableLogged: false,
        mediaPipeBusy: false,
        mediaPipeResolver: null,
        lastFaceCount: null,
        noFaceStarted: null,
        multiFaceStarted: null,
        blurStarted: null,
        paused: false,
        lastEvents: {},
        queue: [],
        stopped: false,
        activeKey: null,
        faceLoopId: null,
        flushLoopId: null
    };

    var createElements = function() {
        var video = document.createElement('video');
        video.autoplay = true;
        video.muted = true;
        video.playsInline = true;
        video.style.position = 'fixed';
        video.style.right = 'max(12px, env(safe-area-inset-right))';
        video.style.bottom = 'max(12px, env(safe-area-inset-bottom))';
        video.style.width = '160px';
        video.style.maxWidth = '25vw';
        video.style.opacity = '0.75';
        video.style.zIndex = '9999';
        video.setAttribute('aria-label', 'Webcam Guard preview');

        var canvas = document.createElement('canvas');
        canvas.style.display = 'none';

        document.body.appendChild(video);
        document.body.appendChild(canvas);
        state.video = video;
        state.canvas = canvas;
    };

    var snapshot = function() {
        if (!state.video || !state.canvas || !state.video.videoWidth) {
            return '';
        }
        var width = Math.min(state.video.videoWidth, 640);
        var height = Math.round(state.video.videoHeight * (width / state.video.videoWidth));
        state.canvas.width = width;
        state.canvas.height = height;
        var ctx = state.canvas.getContext('2d');
        ctx.drawImage(state.video, 0, 0, width, height);
        return state.canvas.toDataURL('image/jpeg', 0.72);
    };

    var send = function(config, eventtype, durationms, includeSnapshot, metadata) {
        var now = Date.now();
        var throttleKey = eventtype;
        if (state.lastEvents[throttleKey] && now - state.lastEvents[throttleKey] < 5000 && eventtype !== 'interval_snapshot') {
            return;
        }
        state.lastEvents[throttleKey] = now;

        var snap = '';
        var meta = metadata || {};
        if (includeSnapshot) {
            meta.snapshotRequested = true;
            try {
                snap = snapshot();
                if (!snap) {
                    meta.snapshot = 'failed';
                }
            } catch (e) {
                meta.snapshot = 'failed';
                snap = '';
            }
        }

        var request = {
            methodname: 'quizaccess_webcamguard_log_event',
            args: {
                courseid: config.courseid,
                cmid: config.cmid,
                quizid: config.quizid,
                attemptid: config.attemptid,
                eventtype: eventtype,
                durationms: Math.max(0, Math.round(durationms || 0)),
                clienttime: now,
                metadata: JSON.stringify(meta),
                snapshot: snap
            }
        };
        ajax.call([request])[0].fail(function() {
            if (state.queue.length < 50) {
                state.queue.push(request);
            }
        });
    };

    var flushQueue = function() {
        if (!state.queue.length) {
            return;
        }
        var retry = state.queue.splice(0, state.queue.length);
        retry.forEach(function(request) {
            ajax.call([request])[0].fail(function() {
                state.queue.push(request);
            });
        });
    };

    var hasSentMonitoringStarted = function(config) {
        try {
            return window.sessionStorage.getItem('quizaccess-webcamguard-started:' + config.attemptid) === '1';
        } catch (e) {
            return false;
        }
    };

    var getMonitoringStartedAtKey = function(config) {
        return 'quizaccess-webcamguard-started-at:' + config.attemptid;
    };

    var getMonitoringStartedAt = function(config) {
        try {
            return parseInt(window.sessionStorage.getItem(getMonitoringStartedAtKey(config)), 10) || 0;
        } catch (e) {
            return 0;
        }
    };

    var markMonitoringStartedAt = function(config, time) {
        try {
            window.sessionStorage.setItem(getMonitoringStartedAtKey(config), String(time));
        } catch (e) {
            return;
        }
    };

    var markMonitoringStartedSent = function(config) {
        try {
            window.sessionStorage.setItem('quizaccess-webcamguard-started:' + config.attemptid, '1');
            if (!getMonitoringStartedAt(config)) {
                markMonitoringStartedAt(config, Date.now());
            }
        } catch (e) {
            return;
        }
    };

    var hasSentIdentityCheck = function(config) {
        try {
            return window.sessionStorage.getItem('quizaccess-webcamguard-identity:' + config.attemptid) === '1';
        } catch (e) {
            return false;
        }
    };

    var markIdentityCheckSent = function(config) {
        try {
            window.sessionStorage.setItem('quizaccess-webcamguard-identity:' + config.attemptid, '1');
        } catch (e) {
            return;
        }
    };

    var getIntervalSnapshotKey = function(config) {
        return 'quizaccess-webcamguard-last-interval:' + config.attemptid;
    };

    var getLastIntervalSnapshotAt = function(config) {
        try {
            return parseInt(window.sessionStorage.getItem(getIntervalSnapshotKey(config)), 10) || 0;
        } catch (e) {
            return 0;
        }
    };

    var markIntervalSnapshotAt = function(config, time) {
        try {
            window.sessionStorage.setItem(getIntervalSnapshotKey(config), String(time));
        } catch (e) {
            return;
        }
    };

    var loadScript = function(url) {
        return new Promise(function(resolve, reject) {
            var existing = document.querySelector('script[data-webcamguard-mediapipe="1"]');
            if (existing) {
                if (window.FaceDetection) {
                    resolve();
                } else {
                    existing.addEventListener('load', resolve);
                    existing.addEventListener('error', reject);
                }
                return;
            }

            var script = document.createElement('script');
            script.src = url;
            script.async = true;
            script.setAttribute('data-webcamguard-mediapipe', '1');
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    };

    var initMediaPipeDetector = function(config) {
        if (!config.mediapipebase) {
            return Promise.reject(new Error('MediaPipe base URL missing'));
        }

        var base = config.mediapipebase.replace(/\/$/, '') + '/';
        return loadScript(base + 'face_detection.js').then(function() {
            if (!window.FaceDetection) {
                throw new Error('MediaPipe FaceDetection global unavailable');
            }

            var detector = new window.FaceDetection({
                locateFile: function(file) {
                    return base + file;
                }
            });
            detector.setOptions({
                model: 'short',
                minDetectionConfidence: 0.5
            });
            detector.onResults(function(results) {
                var count = results && results.detections ? results.detections.length : 0;
                if (state.mediaPipeResolver) {
                    state.mediaPipeResolver(count);
                    state.mediaPipeResolver = null;
                }
                state.mediaPipeBusy = false;
            });
            state.detector = detector;
            state.detectorType = 'mediapipe';
            return detector.initialize();
        });
    };

    var initNativeDetector = function() {
        if (!('FaceDetector' in window)) {
            return false;
        }
        try {
            state.detector = new window.FaceDetector({fastMode: true, maxDetectedFaces: 5});
            state.detectorType = 'native';
            return true;
        } catch (e) {
            state.detector = null;
            state.detectorType = null;
            return false;
        }
    };

    var initialiseDetector = function(config) {
        return initMediaPipeDetector(config).catch(function(error) {
            if (initNativeDetector()) {
                return;
            }

            state.detectorUnavailableLogged = true;
            send(config, 'monitoring_error', 0, false, {
                reason: 'No face detector available',
                detail: error && error.message ? error.message : 'unknown'
            });
        });
    };

    var detectFaces = function() {
        if (!state.detector || !state.video || state.video.readyState < 2) {
            return Promise.resolve(null);
        }

        if (state.detectorType === 'mediapipe') {
            if (state.mediaPipeBusy) {
                return Promise.resolve(null);
            }
            state.mediaPipeBusy = true;
            return new Promise(function(resolve) {
                var done = false;
                state.mediaPipeResolver = function(count) {
                    if (!done) {
                        done = true;
                        resolve(count);
                    }
                };
                state.detector.send({image: state.video}).catch(function() {
                    if (!done) {
                        done = true;
                        state.mediaPipeBusy = false;
                        state.mediaPipeResolver = null;
                        resolve(null);
                    }
                });
                setTimeout(function() {
                    if (!done) {
                        done = true;
                        state.mediaPipeBusy = false;
                        state.mediaPipeResolver = null;
                        resolve(null);
                    }
                }, 900);
            });
        }

        if (state.detectorType === 'native') {
            return state.detector.detect(state.video).then(function(faces) {
                return faces.length;
            }).catch(function() {
                return null;
            });
        }

        return Promise.resolve(null);
    };

    var getCurrentFaceCount = function() {
        return detectFaces().then(function(count) {
            if (count !== null) {
                return count;
            }
            return state.lastFaceCount;
        });
    };

    var addFaceCount = function(metadata, count) {
        if (count !== null && typeof count !== 'undefined') {
            metadata.faces = count;
        }
        return metadata;
    };

    var processFaceCount = function(config, count) {
        var now = Date.now();
        if (count === null) {
            return;
        }
        state.lastFaceCount = count;

        if (count === 0) {
            if (!state.noFaceStarted) {
                state.noFaceStarted = now;
            }
            if (now - state.noFaceStarted >= config.nofacethreshold * 1000) {
                send(config, 'no_face', now - state.noFaceStarted, config.snapshotonviolation, {faces: count});
                state.noFaceStarted = now;
            }
        } else {
            state.noFaceStarted = null;
        }

        if (count > 1) {
            if (!state.multiFaceStarted) {
                state.multiFaceStarted = now;
            }
            if (now - state.multiFaceStarted >= config.multifacethreshold * 1000) {
                send(config, 'multiple_faces', now - state.multiFaceStarted, config.snapshotonviolation, {faces: count});
                state.multiFaceStarted = now;
            }
        } else {
            state.multiFaceStarted = null;
        }
    };

    var sendIntervalSnapshot = function(config) {
        if (state.stopped) {
            return;
        }

        getCurrentFaceCount().then(function(count) {
            if (state.stopped) {
                return;
            }
            send(config, 'interval_snapshot', 0, true, addFaceCount({
                interval: config.intervalseconds
            }, count));
            markIntervalSnapshotAt(config, Date.now());
        });
    };

    var scheduleIntervalSnapshots = function(config) {
        if (!config.intervalseconds || config.intervalseconds < 60) {
            return;
        }

        var intervalms = config.intervalseconds * 1000;
        var last = getLastIntervalSnapshotAt(config) || getMonitoringStartedAt(config);
        var elapsed = last ? Date.now() - last : 0;
        var delay = intervalms;
        if (elapsed > 0 && elapsed < intervalms) {
            delay = intervalms - elapsed;
        } else if (elapsed >= intervalms) {
            delay = 1000;
        }

        setTimeout(function tick() {
            sendIntervalSnapshot(config);
            setTimeout(tick, intervalms);
        }, delay);
    };

    var sendHeartbeat = function(config) {
        if (state.stopped) {
            return;
        }
        send(config, 'heartbeat', 0, false, {});
    };

    var startLoops = function(config) {
        state.faceLoopId = setInterval(function() {
            if (state.stopped || state.paused) {
                return;
            }
            detectFaces().then(function(count) {
                processFaceCount(config, count);
            });
        }, 1000);

        state.flushLoopId = setInterval(function() {
            flushQueue();
        }, 10000);

        state.heartbeatId = setInterval(function() {
            sendHeartbeat(config);
        }, 30000);


        scheduleIntervalSnapshots(config);
    };

    var sendIdentityCheck = function(config) {
        if (!config.identityResult || hasSentIdentityCheck(config)) {
            return;
        }

        var metadata = {
            status: config.identityResult.status || 'unavailable',
            mode: config.identityResult.mode || '',
            threshold: config.identityResult.threshold || '',
            distance: config.identityResult.distance || '',
            message: config.identityResult.message || ''
        };
        send(config, 'identity_check', 0, true, metadata);
        markIdentityCheckSent(config);
    };

    var setupBlurTracking = function(config) {
        var onBlur = function() {
            if (!state.blurStarted) {
                state.blurStarted = Date.now();
            }
        };
        var onFocus = function() {
            if (!state.blurStarted) {
                return;
            }
            var duration = Date.now() - state.blurStarted;
            if (duration >= config.blurthreshold * 1000) {
                send(config, 'window_blur', duration, config.snapshotonviolation, {});
            }
            state.blurStarted = null;
        };

        window.addEventListener('blur', onBlur);
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                state.paused = true;
                onBlur();
            } else {
                state.paused = false;
                state.noFaceStarted = null;
                state.multiFaceStarted = null;
                onFocus();
            }
        });
    };

    var stop = function() {
        state.stopped = true;
        if (state.faceLoopId) { clearInterval(state.faceLoopId); state.faceLoopId = null; }
        if (state.flushLoopId) { clearInterval(state.flushLoopId); state.flushLoopId = null; }
        if (state.heartbeatId) { clearInterval(state.heartbeatId); state.heartbeatId = null; }
        if (state.activeKey && window.quizaccessWebcamguardActiveAttempts) {
            delete window.quizaccessWebcamguardActiveAttempts[state.activeKey];
            state.activeKey = null;
        }
        if (state.stream) {
            state.stream.getTracks().forEach(function(track) {
                track.stop();
            });
        }
    };

    var init = function(config) {
        var activeKey = [config.cmid, config.attemptid].join(':');
        if (window.quizaccessWebcamguardActiveAttempts[activeKey]) {
            return;
        }
        window.quizaccessWebcamguardActiveAttempts[activeKey] = true;
        state.activeKey = activeKey;
        state.stopped = false;

        createElements();
        setupBlurTracking(config);

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            send(config, 'camera_error', 0, false, {reason: 'getUserMedia unavailable'});
            return;
        }

        navigator.mediaDevices.getUserMedia({
            video: {facingMode: 'user', width: {ideal: 640}, height: {ideal: 480}, frameRate: {ideal: 15}},
            audio: false
        }).then(function(stream) {
            state.stream = stream;
            state.video.srcObject = stream;
            stream.getVideoTracks().forEach(function(track) {
                track.addEventListener('ended', function() {
                    send(config, 'camera_stopped', 0, config.snapshotonviolation, {});
                });
            });
            return state.video.play();
        }).then(function() {
            return initialiseDetector(config);
        }).then(function() {
            return getCurrentFaceCount();
        }).then(function(count) {
            var metadata = addFaceCount({
                detector: state.detectorType || 'none',
                mediaPipe: state.detectorType === 'mediapipe'
            }, count);
            if (hasSentMonitoringStarted(config)) {
                if (!getMonitoringStartedAt(config)) {
                    markMonitoringStartedAt(config, Date.now());
                }
                send(config, 'monitoring_resumed', 0, config.snapshotonviolation, metadata);
            } else {
                send(config, 'monitoring_started', 0, config.snapshotonviolation, metadata);
                markMonitoringStartedSent(config);
            }
            sendIdentityCheck(config);
            startLoops(config);
        }).catch(function(error) {
            send(config, 'camera_error', 0, false, {name: error.name || 'unknown'});
        });

        window.addEventListener('beforeunload', stop);
        document.addEventListener('submit', function() {
            stop();
        }, true);
    };

    return {
        init: init
    };
});
