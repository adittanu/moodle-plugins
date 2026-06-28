// This file is part of Moodle - http://moodle.org/

/**
 * Teacher multi-attempt LiveKit dashboard for Webcam Guard.
 *
 * @module     quizaccess_webcamguard/live_dashboard
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(["core/ajax", "require"], function (ajax, require) {
	var state = {
		candidates: [],
		selected: [],
		rooms: {},
		livekit: null,
		pollTimer: null,
		pollInflight: false,
		pollVisible: false,
		lastSeenViolationId: {},
		consecutiveFailures: 0,
	};

	var POLL_INTERVAL_MS = 4000;

	var call = function (methodname, args) {
		return ajax.call([
			{
				methodname: methodname,
				args: args,
			},
		])[0];
	};

	var loadLiveKit = function (scriptUrl) {
		if (state.livekit) {
			return Promise.resolve(state.livekit);
		}
		if (window.LivekitClient && window.LivekitClient.Room) {
			state.livekit = window.LivekitClient;
			return Promise.resolve(state.livekit);
		}

		return new Promise(function (resolve, reject) {
			require([scriptUrl], function (livekit) {
				var sdk = livekit || window.LivekitClient;
				if (sdk && sdk.Room) {
					state.livekit = sdk;
					resolve(sdk);
					return;
				}
				reject(new Error("LiveKit SDK unavailable."));
			}, reject);
		});
	};

	var requestLive = function (config, candidate, action) {
		return call("quizaccess_webcamguard_request_live", {
			courseid: config.courseid,
			cmid: config.cmid,
			quizid: config.quizid,
			attemptid: candidate.attemptid,
			action: action,
		});
	};

	var sendWarning = function (config, attemptid, message) {
		return call("quizaccess_webcamguard_send_warning", {
			courseid: config.courseid,
			cmid: config.cmid,
			quizid: config.quizid,
			attemptid: attemptid,
			message: message,
		});
	};

	var escapeHtml = function (value) {
		var div = document.createElement("div");
		div.textContent =
			value === null || value === undefined ? "" : String(value);
		return div.innerHTML;
	};

	var shuffle = function (items) {
		var copy = items.slice();
		for (var i = copy.length - 1; i > 0; i--) {
			var j = Math.floor(Math.random() * (i + 1));
			var tmp = copy[i];
			copy[i] = copy[j];
			copy[j] = tmp;
		}
		return copy;
	};

	var prioritySort = function (a, b) {
		if (b.riskScore !== a.riskScore) {
			return b.riskScore - a.riskScore;
		}
		if (b.violationCount !== a.violationCount) {
			return b.violationCount - a.violationCount;
		}
		if (a.liveChecked !== b.liveChecked) {
			return a.liveChecked ? 1 : -1;
		}
		return b.lastEventTime - a.lastEventTime;
	};

	var applyStatsToCandidate = function (candidate, fresh) {
		candidate.eventCount = Number(fresh.eventCount) || 0;
		candidate.violationCount = Number(fresh.violationCount) || 0;
		candidate.riskScore = Number(fresh.riskScore) || 0;
		candidate.riskLevel = fresh.riskLevel || "none";
		candidate.topViolationType = fresh.topViolationType || "";
		candidate.topViolationName = fresh.topViolationName || "-";
		candidate.topViolationCount = Number(fresh.topViolationCount) || 0;
		candidate.lastEventType = fresh.lastEventType || "";
		candidate.lastEventName = fresh.lastEventName || "-";
		candidate.lastEventTime = Number(fresh.lastEventTime) || 0;
		candidate.lastEventDisplay = fresh.lastEventDisplay || "-";
		candidate.lastEventId = Number(fresh.lastEventId) || 0;
		candidate.lastViolationEventId = Number(fresh.lastViolationEventId) || 0;
		candidate.lastViolationTime = Number(fresh.lastViolationTime) || 0;
	};

	var updateTile = function (root, candidate) {
		var tile = root.querySelector(
			'[data-tile-for="' + candidate.attemptid + '"]',
		);
		if (!tile) {
			return;
		}
		var meta = tile.querySelector(".quizaccess-webcamguard-livemeta");
		if (meta) {
			meta.innerHTML = [
				'<span class="badge ' +
					badgeClass(candidate) +
					'">Risk ' +
					candidate.riskScore +
					"</span>",
				'<span class="badge badge-secondary">' +
					candidate.violationCount +
					" violation</span>",
				'<span class="badge badge-light">' +
					escapeHtml(candidate.topViolationName) +
					"</span>",
			].join("");
		}
		var status = tile.querySelector(
			'[data-status-for="' + candidate.attemptid + '"]',
		);
		if (status && !status.dataset.streamStatus) {
			status.textContent =
				(candidate.lastEventName || "-") +
				" - " +
				(candidate.lastEventDisplay || "-");
		}
	};

	var flashTile = function (root, attemptid) {
		var tile = root.querySelector('[data-tile-for="' + attemptid + '"]');
		if (!tile) {
			return;
		}
		tile.classList.remove("quizaccess-webcamguard-livetile-flash");
		// Force reflow so the animation restarts on repeat violations.
		void tile.offsetWidth;
		tile.classList.add("quizaccess-webcamguard-livetile-flash");
		window.setTimeout(function () {
			if (tile.classList.contains("quizaccess-webcamguard-livetile-flash")) {
				tile.classList.remove("quizaccess-webcamguard-livetile-flash");
			}
		}, 1800);
	};

	var pollStats = function (config, root) {
		if (state.pollInflight || !state.pollVisible || !state.selected.length) {
			return;
		}
		var ids = state.selected.map(function (candidate) {
			return Number(candidate.attemptid);
		});
		state.pollInflight = true;
		call("quizaccess_webcamguard_poll_live_stats", {
			courseid: config.courseid,
			cmid: config.cmid,
			quizid: config.quizid,
			attemptids: ids,
		})
			.then(function (response) {
				if (!response || !response.attempts) {
					return;
				}
				response.attempts.forEach(function (fresh) {
					var candidate = state.candidates.find(function (item) {
						return Number(item.attemptid) === Number(fresh.attemptid);
					});
					if (!candidate) {
						return;
					}
					var prevViolationId = state.lastSeenViolationId[fresh.attemptid] || 0;
					applyStatsToCandidate(candidate, fresh);
					updateTile(root, candidate);
					if (
						candidate.lastViolationEventId &&
						candidate.lastViolationEventId > prevViolationId
					) {
						if (prevViolationId > 0) {
							flashTile(root, candidate.attemptid);
						}
						state.lastSeenViolationId[fresh.attemptid] =
							candidate.lastViolationEventId;
					} else if (!prevViolationId) {
						state.lastSeenViolationId[fresh.attemptid] =
							candidate.lastViolationEventId || 0;
					}
				});

				// Auto-reorder tiles by risk score (highest first).
				var grid = root.querySelector('[data-region="webcamguard-live-grid"]');
				if (grid) {
					state.selected.sort(function (a, b) {
						if (b.riskScore !== a.riskScore) return b.riskScore - a.riskScore;
						if (b.violationCount !== a.violationCount) return b.violationCount - a.violationCount;
						return b.lastEventTime - a.lastEventTime;
					});
					state.selected.forEach(function (candidate) {
						var tile = grid.querySelector('[data-tile-for="' + candidate.attemptid + '"]');
						if (tile) {
							grid.appendChild(tile);
						}
					});
				}
				state.consecutiveFailures = 0;
				var countRegion = root.querySelector('[data-region="webcamguard-live-count"]');
				if (countRegion && countRegion.dataset.pollError) {
					delete countRegion.dataset.pollError;
				countRegion.textContent = state.selected.length + " / " + state.candidates.length + " " + (config.strings.activeAttempts || "active attempts");
				}
			})
			.catch(function () {
				state.consecutiveFailures++;
				if (state.consecutiveFailures >= 3) {
					var countRegion = root.querySelector('[data-region="webcamguard-live-count"]');
					if (countRegion) {
					countRegion.textContent = '⚠ ' + (config.strings.pollFailed || 'Polling failed — check connection');
						countRegion.dataset.pollError = '1';
					}
				}
			})
			.then(function () {
				state.pollInflight = false;
			});
	};

	var startPolling = function (config, root) {
		stopPolling();
		state.pollVisible = true;
		state.pollTimer = window.setInterval(function () {
			pollStats(config, root);
		}, POLL_INTERVAL_MS);
		// Kick off an immediate refresh so the first frame is fresh.
		window.setTimeout(function () {
			pollStats(config, root);
		}, 250);
	};

	var stopPolling = function () {
		state.pollVisible = false;
		if (state.pollTimer) {
			window.clearInterval(state.pollTimer);
			state.pollTimer = null;
		}
	};

	var pickCandidates = function (mode, limit) {
		var picked = state.candidates.slice();

		if (mode === "random") {
			return shuffle(picked).slice(0, limit);
		}

		if (mode === "violations") {
			picked = picked.filter(function (candidate) {
				return candidate.violationCount > 0;
			});
		} else if (
			mode === "no_face" ||
			mode === "multiple_faces" ||
			mode === "window_blur"
		) {
			picked = picked.filter(function (candidate) {
				return (
					candidate.topViolationType === mode ||
					candidate.lastEventType === mode
				);
			});
		} else if (mode === "camera") {
			picked = picked.filter(function (candidate) {
				return (
					candidate.topViolationType === "camera_stopped" ||
					candidate.topViolationType === "camera_error" ||
					candidate.lastEventType === "camera_stopped" ||
					candidate.lastEventType === "camera_error"
				);
			});
		} else if (mode === "unchecked") {
			picked = picked.filter(function (candidate) {
				return !candidate.liveChecked;
			});
		} else if (mode === "high" || mode === "medium" || mode === "low") {
			picked = picked.filter(function (candidate) {
				return candidate.riskLevel === mode;
			});
		}

		// Always sort by risk — ensures top-N shown are the highest risk from ALL candidates.
		picked.sort(prioritySort);

		return picked.slice(0, limit);
	};

	var badgeClass = function (candidate) {
		if (candidate.riskLevel === "high") {
			return "badge-danger";
		}
		if (candidate.riskLevel === "medium") {
			return "badge-warning";
		}
		if (candidate.riskLevel === "low") {
			return "badge-info";
		}
		return "badge-success";
	};

	var setTileStatus = function (root, attemptid, message) {
		var status = root.querySelector('[data-status-for="' + attemptid + '"]');
		if (status) {
			status.textContent = message;
			status.dataset.streamStatus = "1";
		}
	};

	var getVideoRegion = function (root, attemptid) {
		return root.querySelector('[data-video-for="' + attemptid + '"]');
	};

	var render = function (config, root) {
		var grid = root.querySelector('[data-region="webcamguard-live-grid"]');
		var count = root.querySelector('[data-region="webcamguard-live-count"]');
		var mode = root.querySelector(
			'[data-region="webcamguard-live-filter"]',
		).value;
		var limit = Math.max(1, config.limit || 20);
		state.selected = pickCandidates(mode, limit);

		if (count) {
		count.textContent =
			state.selected.length +
			" / " +
			state.candidates.length +
			" " + (config.strings.activeAttempts || "active attempts");
		}

		if (!grid) {
			return;
		}

		if (!state.selected.length) {
			grid.innerHTML = [
				'<div class="quizaccess-webcamguard-liveempty">',
				config.emptyImageUrl
					? '<img src="' +
						escapeHtml(config.emptyImageUrl) +
						'" alt="" role="presentation">'
					: "",
				'<div class="quizaccess-webcamguard-liveempty-title">',
				escapeHtml(config.strings.emptyTitle || config.strings.empty),
				"</div>",
				'<div class="quizaccess-webcamguard-liveempty-body">',
				escapeHtml(config.strings.emptyBody || config.strings.empty),
				"</div>",
				"</div>",
			].join("");
			return;
		}

		grid.innerHTML = state.selected
			.map(function (candidate) {
				return [
					'<div class="quizaccess-webcamguard-livetile" data-tile-for="' +
						candidate.attemptid +
						'">',
					'<div class="quizaccess-webcamguard-livevideo" data-video-for="' +
						candidate.attemptid +
						'">',
					escapeHtml(config.strings.idle),
					"</div>",
					'<div class="quizaccess-webcamguard-livebody">',
					'<div class="quizaccess-webcamguard-livename" title="' +
						escapeHtml(candidate.fullname) +
						'">',
					escapeHtml(candidate.fullname),
					"</div>",
					'<div class="quizaccess-webcamguard-livemeta">',
					'<span class="badge ' +
						badgeClass(candidate) +
						'">Risk ' +
						candidate.riskScore +
						"</span>",
					'<span class="badge badge-secondary">' +
						candidate.violationCount +
						" violation</span>",
					'<span class="badge badge-light">' +
						escapeHtml(candidate.topViolationName) +
						"</span>",
					"</div>",
					'<div class="quizaccess-webcamguard-livestatus" data-status-for="' +
						candidate.attemptid +
						'">',
					escapeHtml(candidate.lastEventName) +
						" - " +
						escapeHtml(candidate.lastEventDisplay),
					"</div>",
					'<div class="quizaccess-webcamguard-livewarning">',
					'<input type="text" class="form-control form-control-sm" ' +
						'placeholder="' + escapeHtml(config.strings.warningPlaceholder || "Type warning...") + '" ' +
						'data-warning-for="' + candidate.attemptid + '" ' +
						'style="font-size:12px;">',
					'<button class="btn btn-sm btn-outline-warning" data-send-warning="' +
						candidate.attemptid + '" style="font-size:11px;padding:2px 8px;">' +
						escapeHtml(config.strings.sendWarning || "Send") + "</button>",
					"</div>",
					"</div>",
				].join("");
			})
			.join("");
	};

	var attachTrack = function (root, candidate, track) {
		var region = getVideoRegion(root, candidate.attemptid);
		if (!region || !track || (track.kind && track.kind !== "video")) {
			return;
		}

		region.innerHTML = "";
		var video = track.attach();
		video.autoplay = true;
		video.playsInline = true;
		video.muted = true;
		region.appendChild(video);
	};

	var startCandidate = function (config, root, candidate) {
		setTileStatus(root, candidate.attemptid, config.strings.starting);

		return requestLive(config, candidate, "start")
			.then(function (live) {
				if (!live || !live.active) {
					setTileStatus(root, candidate.attemptid, config.strings.failed);
					return null;
				}

				return loadLiveKit(config.scriptUrl).then(function (LK) {
					var room = new LK.Room({
						adaptiveStream: true,
						dynacast: true,
					});
					state.rooms[candidate.attemptid] = {
						room: room,
						candidate: candidate,
					};

					room.on(LK.RoomEvent.TrackSubscribed, function (track) {
						attachTrack(root, candidate, track);
						setTileStatus(root, candidate.attemptid, config.strings.connected);
					});
					room.on(LK.RoomEvent.Disconnected, function () {
						delete state.rooms[candidate.attemptid];
						setTileStatus(root, candidate.attemptid, config.strings.stopped);
					});

					return room
						.connect(live.url, live.token, {
							autoSubscribe: true,
						})
						.then(function () {
							candidate.liveChecked = true;
							setTileStatus(root, candidate.attemptid, config.strings.waiting);
						});
				});
			})
			.catch(function (error) {
				var message = config.strings.failed;
				if (error && error.message) {
					message += " " + error.message;
				}
				setTileStatus(root, candidate.attemptid, message);
			});
	};

	var stopCandidate = function (config, root, attemptid) {
		var active = state.rooms[attemptid];
		if (active && active.room) {
			active.room.disconnect();
		}
		delete state.rooms[attemptid];

		var candidate = active
			? active.candidate
			: state.candidates.find(function (item) {
					return Number(item.attemptid) === Number(attemptid);
				});
		if (!candidate) {
			return Promise.resolve();
		}

		var region = getVideoRegion(root, candidate.attemptid);
		if (region) {
			region.innerHTML = escapeHtml(config.strings.stopped);
		}
		var status = root.querySelector(
			'[data-status-for="' + candidate.attemptid + '"]',
		);
		if (status) {
			delete status.dataset.streamStatus;
			status.textContent =
				(candidate.lastEventName || "-") +
				" - " +
				(candidate.lastEventDisplay || "-");
		}
		return requestLive(config, candidate, "stop").catch(function () {
			return null;
		});
	};

	var stopAll = function (config, root) {
		var attemptids = Object.keys(state.rooms);
		return Promise.all(
			attemptids.map(function (attemptid) {
				return stopCandidate(config, root, attemptid);
			}),
		);
	};

	var startSelection = function (config, root) {
		stopAll(config, root).then(function () {
			state.selected.forEach(function (candidate) {
				startCandidate(config, root, candidate);
			});
		});
	};

	return {
		init: function (config) {
			var root = document.getElementById(
				"quizaccess-webcamguard-live-dashboard",
			);
			if (!root) {
				return;
			}

			state.candidates = (config.candidates || []).map(function (candidate) {
				candidate.riskScore = Number(candidate.riskScore) || 0;
				candidate.violationCount = Number(candidate.violationCount) || 0;
				candidate.lastEventTime = Number(candidate.lastEventTime) || 0;
				candidate.lastEventId = Number(candidate.lastEventId) || 0;
				candidate.lastViolationEventId =
					Number(candidate.lastViolationEventId) || 0;
				candidate.liveChecked = Boolean(candidate.liveChecked);
				state.lastSeenViolationId[candidate.attemptid] =
					candidate.lastViolationEventId || 0;
				return candidate;
			});

			render(config, root);

			var filter = root.querySelector(
				'[data-region="webcamguard-live-filter"]',
			);
			var refresh = root.querySelector(
				'[data-action="webcamguard-live-refresh"]',
			);
			var start = root.querySelector(
				'[data-action="webcamguard-live-start-selection"]',
			);
			var stop = root.querySelector(
				'[data-action="webcamguard-live-stop-all"]',
			);

			if (filter) {
				filter.addEventListener("change", function () {
					stopAll(config, root).then(function () {
						render(config, root);
					});
				});
			}
			if (refresh) {
				refresh.addEventListener("click", function () {
					stopAll(config, root).then(function () {
						render(config, root);
					});
				});
			}
			if (start) {
				start.addEventListener("click", function () {
					startSelection(config, root);
				});
			}
			if (stop) {
				stop.addEventListener("click", function () {
					stopAll(config, root);
				});
			}

			// Delegated handler for warning send buttons.
			root.addEventListener("click", function (e) {
				var btn = e.target;
				if (!btn || !btn.dataset || !btn.dataset.sendWarning) {
					return;
				}
				var attemptid = Number(btn.dataset.sendWarning);
				var input = root.querySelector('[data-warning-for="' + attemptid + '"]');
				if (!input || !input.value.trim()) {
					return;
				}
				var message = input.value.trim();
				input.value = "";
				sendWarning(config, attemptid, message).then(function (res) {
					if (res && res.success) {
						btn.textContent = config.strings.warningSent || "Sent!";
						setTimeout(function () {
							btn.textContent = config.strings.sendWarning || "Send";
						}, 2000);
					}
				}).catch(function () {
					// Swallow.
				});
			});

			// Send warning to all selected participants.
			var sendAllBtn = root.querySelector('[data-action="webcamguard-send-warning-all"]');
			var globalInput = root.querySelector('[data-region="webcamguard-global-warning"]');
			if (sendAllBtn && globalInput) {
				sendAllBtn.addEventListener("click", function () {
					var message = globalInput.value.trim();
					if (!message) {
						return;
					}
					globalInput.value = "";
					var targets = state.selected.length ? state.selected : state.candidates;
					var count = 0;
					targets.forEach(function (candidate) {
						sendWarning(config, candidate.attemptid, message).then(function (res) {
							if (res && res.success) {
								count++;
							}
						}).catch(function () {
							// Swallow.
						});
					});
					sendAllBtn.textContent = (config.strings.warningSentAll || "Sent!") + " (" + targets.length + ")";
					setTimeout(function () {
						sendAllBtn.textContent = config.strings.sendWarningAll || "Send to All";
					}, 3000);
				});
			}

			if (window.jQuery) {
				var $root = window.jQuery(root);
				var isModal = $root.hasClass('modal') || $root.closest('.modal').length > 0;
				if (isModal) {
					$root.on("shown.bs.modal", function () {
						startPolling(config, root);
					});
					$root.on("hidden.bs.modal", function () {
						stopPolling();
						stopAll(config, root);
					});
				} else {
					// Not a modal — start polling immediately.
					startPolling(config, root);
				}
			} else {
				// No jQuery — poll while the dashboard root is in the DOM.
				startPolling(config, root);
			}
		},
	};
});
