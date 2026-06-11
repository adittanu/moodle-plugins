# Webcam Guard quiz access rule

`quizaccess_webcamguard` adds webcam consent, browser-side monitoring, evidence logging, and teacher review pages to Moodle Quiz.

## Install

Place this directory at:

```txt
mod/quiz/accessrule/webcamguard
```

Then run Moodle upgrade or open **Site administration → Notifications**.

## Usage

1. Edit a quiz.
2. Enable **Webcam Guard** in quiz settings.
3. Configure thresholds and optional interval snapshots.
4. Students must pass the webcam preflight check before starting.
5. Teachers can open the Webcam Guard report from the quiz restriction description link.

## Evidence behavior

- No continuous video recording.
- Snapshots are captured on violations when enabled.
- Interval snapshots are off by default.
- Evidence is deleted after 30 days by the scheduled cleanup task.
- Attempt outcomes are not changed automatically; teachers review evidence manually.

## Browser note

The monitor loads a local MediaPipe Face Detection bundle from `mediapipe/face_detection` as the primary detector. If MediaPipe fails to load, it falls back to the browser-native `FaceDetector` API when available. If no detector is available, the plugin still logs camera, tab/window blur, and interval snapshot evidence, and records a `monitoring_error` event so teachers know face-count detection was limited in that browser.
