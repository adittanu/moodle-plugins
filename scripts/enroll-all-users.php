<?php
/**
 * Enroll all users to all courses (manual enrolment)
 * Run as: sudo -u www-data php enroll-all-users.php
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/enrol/manual/lib.php');

// Get manual enrol plugin instance
$manualplugin = enrol_get_plugin('manual');

// Get all courses (skip the site course id=1)
$courses = $DB->get_records('course', ['visible' => 1], 'id ASC');
unset($courses[SITEID]);

// Get all non-deleted users
$users = $DB->get_records('user', ['deleted' => 0, 'confirmed' => 1], 'id ASC');
unset($users[1]); // Skip guest user

$enrolled = 0;
$skipped = 0;
$errors = 0;

foreach ($courses as $course) {
    // Get or create manual enrol instance for this course
    $instances = enrol_get_instances($course->id, true);
    $manualinstance = null;

    foreach ($instances as $instance) {
        if ($instance->enrol === 'manual') {
            $manualinstance = $instance;
            break;
        }
    }

    if (!$manualinstance) {
        // Create manual enrol instance
        $manualplugin->add_default_enrolment_instance($course);
        $instances = enrol_get_instances($course->id, true);
        foreach ($instances as $instance) {
            if ($instance->enrol === 'manual') {
                $manualinstance = $instance;
                break;
            }
        }
    }

    if (!$manualinstance) {
        echo "ERROR: Cannot find/create manual enrol for course {$course->shortname}\n";
        $errors++;
        continue;
    }

    foreach ($users as $user) {
        // Check if already enrolled
        $alreadyenrolled = $DB->get_record('user_enrolments', [
            'userid' => $user->id,
            'enrolid' => $manualinstance->id,
        ]);

        if ($alreadyenrolled) {
            $skipped++;
            continue;
        }

        // Enrol user as student (roleid 5)
        $manualplugin->enrol_user($manualinstance, $user->id, 5, time(), 0);
        $enrolled++;
    }

    echo "Course: {$course->shortname} ({$course->fullname}) - done\n";
}

echo "\n=== SUMMARY ===\n";
echo "Courses processed: " . count($courses) . "\n";
echo "Users per course: " . count($users) . "\n";
echo "New enrolments: {$enrolled}\n";
echo "Already enrolled (skipped): {$skipped}\n";
echo "Errors: {$errors}\n";
