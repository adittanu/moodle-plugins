// This file is part of Moodle - http://moodle.org/

/**
 * Webcam Guard preflight check — circular gauge + face glow indicator.
 *
 * @module     quizaccess_webcamguard/preflight
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    var REQUIRED_MATCHES = 2;
    var GAUGE_CIRCUMFERENCE = 2 * Math.PI * 26; // r=26 in SVG

    // ─── Helpers ───────────────────────────────────────────────────────

    var getField = function(id, name) {
        var form = document.getElementById('mod_quiz_preflight_form');
        if (form) {
            var el = name ? form.querySelector('[name="' + name + '"]') : null;
            if (el) { return el; }
        }
        return document.getElementById(id) || document.getElementsByName(name)[0] || null;
    };

    var setFieldValue = function(id, name, value) {
        var f = getField(id, name);
        if (f) { f.value = value; }
    };

    var setStatus = function(el, message, cssClass) {
        if (!el) { return; }
        el.className = cssClass || '';
        el.textContent = message;
    };

    var loadScript = function(url) {
        return new Promise(function(resolve, reject) {
            if (window.faceapi) { resolve(); return; }
            var existing = document.querySelector('script[data-webcamguard-faceapi="1"]');
            if (existing) { existing.addEventListener('load', resolve); existing.addEventListener('error', reject); return; }
            var origDefine = window.define;
            window.define = undefined;
            var s = document.createElement('script');
            s.src = url; s.async = true; s.setAttribute('data-webcamguard-faceapi', '1');
            s.onload = function() { window.define = origDefine; resolve(); };
            s.onerror = function(e) { window.define = origDefine; reject(e); };
            document.head.appendChild(s);
        });
    };

    var loadImage = function(url) {
        return new Promise(function(resolve, reject) {
            if (!url) { reject(new Error('No URL')); return; }
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function() { resolve(img); };
            img.onerror = reject;
            img.src = url;
        });
    };

    // ─── Face-api ──────────────────────────────────────────────────────

    var loadIdentityModels = function(config) {
        if (window.quizaccessWebcamguardFaceApiReady) {
            return window.quizaccessWebcamguardFaceApiReady;
        }
        window.quizaccessWebcamguardFaceApiReady = loadScript(config.identity.scriptUrl).then(function() {
            if (!window.faceapi) { throw new Error('face-api unavailable'); }
            return Promise.all([
                window.faceapi.nets.tinyFaceDetector.loadFromUri(config.identity.modelBase),
                window.faceapi.nets.faceLandmark68Net.loadFromUri(config.identity.modelBase),
                window.faceapi.nets.faceRecognitionNet.loadFromUri(config.identity.modelBase),
            ]);
        });
        return window.quizaccessWebcamguardFaceApiReady;
    };

    var detectorOptions = function() {
        return new window.faceapi.TinyFaceDetectorOptions({inputSize: 160, scoreThreshold: 0.2});
    };

    var getDescriptor = function(input) {
        return window.faceapi.detectSingleFace(input, detectorOptions()).withFaceLandmarks().withFaceDescriptor();
    };

    var frameBrightness = function(video, box) {
        var canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(box.width));
        canvas.height = Math.max(1, Math.round(box.height));
        var context = canvas.getContext('2d', {willReadFrequently: true});
        context.drawImage(video, box.x, box.y, box.width, box.height, 0, 0, canvas.width, canvas.height);
        var pixels = context.getImageData(0, 0, canvas.width, canvas.height).data;
        var total = 0;
        for (var i = 0; i < pixels.length; i += 16) {
            total += (pixels[i] + pixels[i + 1] + pixels[i + 2]) / 3;
        }
        return total / Math.max(1, pixels.length / 16);
    };

    var qualityMessage = function(config, video, result, faceCount) {
        if (faceCount !== 1) {
            return faceCount > 1 ? config.strings.qualitymultiple : config.strings.qualitynoface;
        }
        var box = result.detection.box;
        if (box.width < video.videoWidth * 0.25) {
            return config.strings.qualitytoosmall;
        }
        var landmarks = result.landmarks.positions;
        var eyeMidpoint = (landmarks[36].x + landmarks[45].x) / 2;
        if (Math.abs(landmarks[30].x - eyeMidpoint) > box.width * 0.12) {
            return config.strings.qualitypose;
        }
        if (frameBrightness(video, box) < 45) {
            return config.strings.qualitydark;
        }
        return '';
    };

    var getCameraDescriptor = function(config, video) {
        return window.faceapi.detectAllFaces(video, detectorOptions()).withFaceLandmarks().withFaceDescriptors()
            .then(function(results) {
                var result = results.length === 1 ? results[0] : null;
                var message = qualityMessage(config, video, result, results.length);
                return {result: message ? null : result, message: message};
            });
    };

    var euclidean = function(a, b) {
        var sum = 0;
        for (var i = 0; i < a.length; i++) { var d = a[i] - b[i]; sum += d * d; }
        return Math.sqrt(sum);
    };

    var distToPercent = function(dist) {
        if (dist === null || dist === undefined || isNaN(dist)) { return 0; }
        var p = (1 - dist) * 100;
        return Math.max(0, Math.min(100, p));
    };

    // ─── UI Updates ────────────────────────────────────────────────────

    var setFaceState = function(state) {
        var wrap = document.getElementById('wcg-camera-wrap');
        if (wrap) { wrap.setAttribute('data-face-state', state); }
    };

    var updateGauge = function(percent, state) {
        var fill = document.getElementById('quizaccess-webcamguard-similarity-fill');
        var valueEl = document.getElementById('quizaccess-webcamguard-similarity-value');
        var statusEl = document.getElementById('wcg-similarity-status');

        if (fill) {
            fill.setAttribute('stroke-dasharray', GAUGE_CIRCUMFERENCE);
            var offset = GAUGE_CIRCUMFERENCE - (GAUGE_CIRCUMFERENCE * percent / 100);
            fill.setAttribute('stroke-dashoffset', offset);
            fill.style.strokeDashoffset = offset;
            fill.setAttribute('data-state', state);
            var colors = {matched: '#22c55e', mismatch: '#ef4444', searching: '#6366f1'};
            fill.style.stroke = colors[state] || '#6366f1';
        }
        if (valueEl) {
            valueEl.textContent = Math.round(percent) + '%';
        }
        if (statusEl) {
            statusEl.setAttribute('data-state', state);
        }
    };

    var setSimilarityStatus = function(text, state) {
        var el = document.getElementById('wcg-similarity-status');
        if (el) {
            el.textContent = text;
            el.setAttribute('data-state', state);
        }
    };

    var setIdentityResult = function(config, status, distance, message) {
        setFieldValue(config.identityStatusFieldId, config.identityStatusFieldName, status);
        setFieldValue(config.identityDistanceFieldId, config.identityDistanceFieldName,
            (distance === null || distance === undefined) ? '' : String(distance));
        setFieldValue(config.identityMessageFieldId, config.identityMessageFieldName, message || '');
    };

    var setReadyValue = function(config, value) {
        setFieldValue(config.readyFieldId, config.readyFieldName, value);
    };

    // ─── Live Loop ─────────────────────────────────────────────────────

    var stopLiveLoop = function(state) {
        if (!state) { return; }
        if (state.intervalId) { clearInterval(state.intervalId); state.intervalId = null; }
        state.running = false;
    };

    var startLiveLoop = function(config, video, reference, statusEl) {
        if (config.liveState) { stopLiveLoop(config.liveState); }

        var requiredMatches = (config.identity && config.identity.requiredMatches) || REQUIRED_MATCHES;
        var tickMs = (config.identity && config.identity.liveIntervalMs) || 800;
        var state = { running: true, inFlight: false, intervalId: null, consecutiveMatches: 0, locked: false };
        config.liveState = state;

        setFaceState('searching');
        updateGauge(0, 'searching');
        setSimilarityStatus(config.strings.identitysearching, 'searching');
        setStatus(statusEl, config.strings.identitysearching, 'alert alert-info');

        var tick = function() {
            if (!state.running || state.inFlight || state.locked) { return; }
            state.inFlight = true;

            getCameraDescriptor(config, video).then(function(camera) {
                state.inFlight = false;
                if (!state.running) { return; }

                if (!camera.result) {
                    state.consecutiveMatches = 0;
                    setFaceState('searching');
                    updateGauge(0, 'searching');
                    setSimilarityStatus(camera.message, 'searching');
                    setStatus(statusEl, camera.message, 'alert alert-info');
                    setIdentityResult(config, 'searching', null, '');
                    if (config.identity.mode === 'block') { setReadyValue(config, '0'); }
                    return;
                }

                var dist = euclidean(reference.descriptor, camera.result.descriptor);
                var pct = distToPercent(dist);
                var matched = dist <= config.identity.threshold;

                if (matched) {
                    state.consecutiveMatches++;
                    setFaceState('matched');
                    updateGauge(pct, 'matched');

                    if (state.consecutiveMatches >= requiredMatches) {
                        state.locked = true;
                        clearInterval(state.intervalId);
                        state.intervalId = null;
                        setReadyValue(config, '1');
                        setIdentityResult(config, 'match', dist, config.strings.identitymatched);
                        setSimilarityStatus(config.strings.identitymatched, 'matched');
                        setStatus(statusEl, config.strings.identitymatched + ' (' + Math.round(pct) + '%)', 'alert alert-success');
                    } else {
                        setIdentityResult(config, 'match-pending', dist, '');
                        setSimilarityStatus(Math.round(pct) + '% — verifying...', 'matched');
                        setStatus(statusEl, config.strings.identitymatched + ' (' + Math.round(pct) + '%)', 'alert alert-info');
                    }
                } else {
                    state.consecutiveMatches = 0;
                    setFaceState('mismatch');
                    updateGauge(pct, 'mismatch');
                    setSimilarityStatus(config.strings.identitymismatch, 'mismatch');
                    setIdentityResult(config, 'mismatch', dist, config.strings.identitymismatch);
                    setStatus(statusEl, config.strings.identitymismatch + ' (' + Math.round(pct) + '%)', 'alert alert-warning');
                    if (config.identity.mode === 'block') {
                        setReadyValue(config, '0');
                    } else {
                        setReadyValue(config, '1');
                    }
                }
            }).catch(function() {
                state.inFlight = false;
                state.consecutiveMatches = 0;
                setFaceState('searching');
                updateGauge(0, 'searching');
                setSimilarityStatus(config.strings.identityunavailable, 'searching');
                setStatus(statusEl, config.strings.identityunavailable, 'alert alert-warning');
                setIdentityResult(config, 'unavailable', null, config.strings.identityunavailable);
                if (config.identity.mode === 'flag') { setReadyValue(config, '1'); }
                else { setReadyValue(config, '0'); }
            });
        };

        tick();
        state.intervalId = setInterval(tick, tickMs);
    };

    // ─── No Profile Picture ────────────────────────────────────────────

    var startWithoutProfile = function(config, statusEl) {
        setFaceState('mismatch');
        updateGauge(0, 'mismatch');
        if (config.identity.mode === 'block') {
            setReadyValue(config, '0');
            setSimilarityStatus(config.strings.identityneedprofileblock, 'mismatch');
            setStatus(statusEl, config.strings.identityneedprofileblock, 'alert alert-danger');
            setIdentityResult(config, 'noprofile', null, config.strings.identityneedprofileblock);
        } else {
            setReadyValue(config, '1');
            setSimilarityStatus(config.strings.identityneedprofileflag, 'mismatch');
            setStatus(statusEl, config.strings.identityneedprofileflag, 'alert alert-warning');
            setIdentityResult(config, 'noprofile', null, config.strings.identityneedprofileflag);
        }
    };

    // ─── Run Check ─────────────────────────────────────────────────────

    var detectedDevice = function() {
        // ponytail: dimension-only heuristic; current viewport avoids stale DevTools-emulated screen width.
        return window.innerWidth < 768 ? 'mobile' : 'desktop';
    };

    var deviceMatches = function(config) {
        return !config.deviceMode || config.deviceMode === 'any' || config.deviceMode === detectedDevice();
    };

    var deviceLabel = function(config, mode) {
        return mode === 'mobile' ? config.strings.devicemobile : config.strings.devicedesktop;
    };

    var deviceMessage = function(config, valid) {
        var detected = deviceLabel(config, detectedDevice());
        if (valid) {
            return config.strings.devicepassed + ': ' + detected + '.';
        }
        return config.strings.devicefailed + ': ' + detected + '; ' +
            deviceLabel(config, config.deviceMode) + ' ' + config.strings.devicerequired.toLowerCase() + '. ' +
            config.strings.deviceblocked;
    };

    var setDeviceValidity = function(config) {
        var field = document.getElementById(config.deviceValidFieldId);
        var label = document.getElementById(config.deviceLabelId);
        var valid = deviceMatches(config);
        if (field) { field.value = valid ? '1' : '0'; }
        if (label) {
            label.className = valid ? 'alert alert-info' : 'alert alert-danger';
            label.textContent = deviceMessage(config, valid);
        }
        return valid;
    };

    var runCheck = function(config) {
        var readyField = getField(config.readyFieldId, config.readyFieldName);
        var consentField = getField(config.consentFieldId, config.consentFieldName);
        if (!readyField || !consentField) { return; }

        if (config.liveState) { stopLiveLoop(config.liveState); }
        setIdentityResult(config, '', null, '');

        var video = document.getElementById(config.videoId);
        var statusEl = document.getElementById(config.statusId);
        var placeholder = document.getElementById('wcg-camera-placeholder');
        if (!setDeviceValidity(config)) {
            setReadyValue(config, '0');
            setFaceState('idle');
            setStatus(statusEl, config.strings.deviceblocked, 'alert alert-danger');
            return;
        }


        setReadyValue(config, '0');
        setFaceState('searching');
        setStatus(statusEl, config.strings.checking, 'alert alert-info');

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setStatus(statusEl, config.strings.cameranotfound, 'alert alert-danger');
            setFaceState('idle');
            return;
        }

        navigator.mediaDevices.getUserMedia({
            video: {facingMode: 'user', width: {ideal: 640}, height: {ideal: 480}},
            audio: false
        }).then(function(stream) {
            video.srcObject = stream;
            video.style.display = 'block';
            if (placeholder) { placeholder.style.display = 'none'; }
            return video.play().catch(function() {});
        }).then(function() {
            if (!config.identity || !config.identity.enabled) {
                setReadyValue(config, '1');
                setFaceState('matched');
                updateGauge(100, 'matched');
                setSimilarityStatus(config.strings.ready, 'matched');
                setStatus(statusEl, config.strings.ready, 'alert alert-success');
                return null;
            }
            if (!config.identity.hasProfilePicture || !config.identity.referenceImageUrl) {
                startWithoutProfile(config, statusEl);
                return null;
            }

            setStatus(statusEl, config.strings.identityloading, 'alert alert-info');
            return loadIdentityModels(config).then(function() {
                return loadImage(config.identity.referenceImageUrl);
            }).then(function(refImg) {
                return getDescriptor(refImg);
            }).then(function(reference) {
                if (!reference) {
                    setFaceState('mismatch');
                    updateGauge(0, 'mismatch');
                    if (config.identity.mode === 'block') {
                        setReadyValue(config, '0');
                        setSimilarityStatus(config.strings.identityneedprofileblock, 'mismatch');
                        setStatus(statusEl, config.strings.identityneedprofileblock, 'alert alert-danger');
                    } else {
                        setReadyValue(config, '1');
                        setSimilarityStatus(config.strings.identityunavailable, 'mismatch');
                        setStatus(statusEl, config.strings.identityunavailable, 'alert alert-warning');
                    }
                    return;
                }
                startLiveLoop(config, video, reference, statusEl);
            });
        }).catch(function(error) {
            setFaceState('idle');
            if (error && (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError')) {
                setStatus(statusEl, config.strings.permissiondenied, 'alert alert-danger');
            } else {
                setStatus(statusEl, config.strings.cameranotfound, 'alert alert-danger');
            }
        });
    };

    // ─── Layout ────────────────────────────────────────────────────────

    var ensureLayout = function() {
        var form = document.getElementById('mod_quiz_preflight_form');
        if (!form || form.dataset.wcgLayout === '1') { return; }

        var fieldset = form.querySelector('fieldset[id*="webcamguardpreflightheader"]') || form.querySelector('fieldset');
        if (!fieldset) { return; }
        var container = fieldset.querySelector('.fcontainer') || fieldset;
        container.classList.add('wcg-grid');

        var areaMap = {
            webcamguardmessage: 'warning',
            webcamguardprofilewarning: 'profile',
            webcamguardconsent: 'consent',
            webcamguardcheckpreview: 'camera',
            webcamguardstartcheck: 'startcheck',
        };
        Array.prototype.forEach.call(container.children, function(child) {
            var id = child.id || '';
            var m = id.match(/^fitem_id_(.+)$/);
            var key = m ? m[1] : null;
            if (key && areaMap[key]) {
                child.setAttribute('data-wcg-area', areaMap[key]);
            }
        });
        form.dataset.wcgLayout = '1';
    };

    // ─── Button detection ──────────────────────────────────────────────

    var isCheckButton = function(el) {
        if (!el) { return false; }
        var label = (el.value || el.textContent || '').trim().toLowerCase();
        return el.id === 'quizaccess-webcamguard-startcheck' ||
            el.getAttribute('data-webcamguard-action') === 'startcheck' ||
            label === 'check webcam' || label === 'periksa webcam';
    };

    var findButton = function(config) {
        return document.getElementById(config.buttonId) ||
            document.getElementById('id_webcamguardstartcheck') ||
            document.querySelector('[data-webcamguard-action="startcheck"]');
    };

    // ─── Init ──────────────────────────────────────────────────────────

    var init = function(config) {
        setDeviceValidity(config);
        window.addEventListener('resize', function() { setDeviceValidity(config); });
        var tryBind = function() {
            ensureLayout();
            var button = findButton(config);
            if (button && !button.dataset.wcgBound) {
                button.dataset.wcgBound = '1';
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    runCheck(config);
                });
            }
        };

        tryBind();

        if (typeof MutationObserver !== 'undefined' && document.body) {
            var obs = new MutationObserver(function() { tryBind(); });
            obs.observe(document.body, {childList: true, subtree: true});
        }
        setTimeout(tryBind, 300);
        setTimeout(tryBind, 1000);
        setTimeout(tryBind, 2500);
        setTimeout(tryBind, 5000);

        document.addEventListener('click', function(e) {
            var target = e.target;
            if (!isCheckButton(target) && target && target.closest) {
                target = target.closest('[data-webcamguard-action="startcheck"]');
            }
            if (!isCheckButton(target)) { return; }
            e.preventDefault();
            runCheck(config);
        }, true);

        var bindFormSubmit = function() {
            var form = document.getElementById('mod_quiz_preflight_form');
            if (form && !form.dataset.wcgSubmitBound) {
                form.dataset.wcgSubmitBound = '1';
                form.addEventListener('submit', function(e) {
                    if (!setDeviceValidity(config)) {
                        e.preventDefault();
                        setStatus(document.getElementById(config.statusId),
                            config.strings.deviceblocked, 'alert alert-danger');
                        return;
                    }
                    if (config.liveState) { stopLiveLoop(config.liveState); }
                    var video = document.getElementById(config.videoId);
                    if (video && video.srcObject) {
                        video.srcObject.getTracks().forEach(function(t) { t.stop(); });
                        video.srcObject = null;
                    }
                });
            }
        };
        bindFormSubmit();
        setTimeout(bindFormSubmit, 1000);
    };

    return { init: init };
});
