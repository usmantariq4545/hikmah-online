<?php
// One live class per class in the demo course "Foundations of Tajweed": a "Live class" room at the top
// of each class section, open from the class start to 30 minutes after it ends, created by the
// instructor. The general room's recordings, attendance links and live-class evidence move to the
// class they belong to, and the general room is hidden (not deleted: deleting a room also talks to
// the BigBlueButton server). Upcoming classes keep their room visible; only their notes and slides
// wait until the day before the class.
// Used by tajweed-course.php; can also be run on its own inside the moodle container (idempotent):
//   php tajweed-liveclasses.php

if (!defined('CLI_SCRIPT')) {
    define('CLI_SCRIPT', true);
    require('/var/www/moodle/config.php');
    $runnow = true;
}
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Give every class section its own live class.
 *
 * @param stdClass $course
 * @return int rooms created
 */
function hk_tajweed_liveclasses(stdClass $course): int {
    global $DB;
    $teacher = $DB->get_record('user', ['username' => 'demo.teacher'], '*', MUST_EXIST);
    \core\session\manager::set_user($teacher);
    $gen = new testing_data_generator();
    $now = time();

    $modinfo = get_fast_modinfo($course);
    $general = null;
    foreach ($modinfo->get_instances_of('bigbluebuttonbn') as $cm) {
        if ((int) $cm->sectionnum === 0) {
            $general = $cm;
        }
    }
    $generalroom = $general ? $DB->get_record('bigbluebuttonbn', ['id' => $general->instance]) : null;
    $attendance = $DB->get_record('attendance', ['course' => $course->id], '*', MUST_EXIST);

    $created = 0;
    $held = [];
    foreach ($DB->get_records_select('course_sections', 'course = ? AND section BETWEEN 1 AND 8', [$course->id], 'section') as $section) {
        $n = (int) $section->section;
        $session = $DB->get_record_select('attendance_sessions', 'attendanceid = ? AND ' . $DB->sql_like('description', '?'),
            [$attendance->id, "Class $n:%"]);
        if (!$session) {
            continue;
        }
        $start = (int) $session->sessdate;
        $end = $start + (int) $session->duration;
        $sectioncms = array_filter($modinfo->get_cms(), fn($cm) => (int) $cm->sectionnum === $n);
        $existing = array_filter($sectioncms, fn($cm) => $cm->modname === 'bigbluebuttonbn');
        if ($existing) {
            $room = $DB->get_record('bigbluebuttonbn', ['id' => reset($existing)->instance], '*', MUST_EXIST);
            if ((int) $room->closingtime !== $end) {
                // The room is the class: it closes when the class ends (shown as "Duration: 1 hour").
                $DB->set_field('bigbluebuttonbn', 'closingtime', $end, ['id' => $room->id]);
            }
        } else {
            $instance = $gen->create_module('bigbluebuttonbn', ['course' => $course->id, 'section' => $n,
                'name' => 'Live class', 'type' => 0, 'wait' => 1, 'record' => 1,
                'intro' => '<p>Class ' . $n . ' is live here on ' . userdate($start, '%A %d %B, %I:%M %p', 'Asia/Karachi', false)
                    . ' (Pakistan time). The recording appears here a few hours after the class.</p>',
                'openingtime' => $start, 'closingtime' => $end]);
            $room = $DB->get_record('bigbluebuttonbn', ['id' => $instance->id], '*', MUST_EXIST);
            if ($generalroom) {
                $DB->set_field('bigbluebuttonbn', 'participants', $generalroom->participants, ['id' => $room->id]);
            }
            // First in the section, above the notes.
            if ($sectioncms) {
                moveto_module(get_coursemodule_from_id('bigbluebuttonbn', $instance->cmid), $section,
                    get_coursemodule_from_id('', reset($sectioncms)->id));
            }
            $created++;
        }

        // Upcoming class: the room stays visible; only the materials wait for the day before.
        if (!empty($section->availability)) {
            foreach ($sectioncms as $cm) {
                $DB->set_field('course_modules', 'availability', $section->availability, ['id' => $cm->id]);
            }
            course_update_section($course, $section, ['availability' => null]);
        }

        $link = $DB->get_record('local_hikmahatt_link', ['sessionid' => $session->id]);
        if ($link && (int) $link->bbbid !== (int) $room->id) {
            $DB->set_field('local_hikmahatt_link', 'bbbid', $room->id, ['id' => $link->id]);
        }
        if ($generalroom) {
            $DB->execute('UPDATE {bigbluebuttonbn_logs} SET bigbluebuttonbnid = ?, meetingid = ?
                           WHERE bigbluebuttonbnid = ? AND timecreated BETWEEN ? AND ?',
                [$room->id, $room->meetingid . '-' . $course->id . '-' . $room->id, $generalroom->id, $start - 1800, $end + 3600]);
        }
        if ($end < $now) {
            $held[] = $room;
        }
        // The standard set-up: register session, link and automatic marking (as for any course).
        \local_hikmahattendance\manager::ensure_for_room((int) $room->id, (int) $teacher->id);
    }

    // The general room's recordings, oldest first, to the held classes in order; any extra to the last.
    if ($generalroom && $held) {
        $recordings = array_values($DB->get_records('bigbluebuttonbn_recordings', ['bigbluebuttonbnid' => $generalroom->id],
            'timecreated ASC', 'id'));
        foreach ($recordings as $i => $recording) {
            $room = $held[min($i, count($held) - 1)];
            $DB->set_field('bigbluebuttonbn_recordings', 'bigbluebuttonbnid', $room->id, ['id' => $recording->id]);
        }
    }
    if ($general && $general->visible) {
        set_coursemodule_visible($general->id, 0);
    }
    rebuild_course_cache($course->id, true);
    return $created;
}

if (!empty($runnow)) {
    $course = $DB->get_record('course', ['idnumber' => 'demo-tajweed'], '*', MUST_EXIST);
    $transaction = $DB->start_delegated_transaction(); // All or nothing.
    $created = hk_tajweed_liveclasses($course);
    $transaction->allow_commit();
    \core\session\manager::set_user(get_admin());
    purge_caches(['courses' => [$course->id], 'muc' => true]);
    cli_writeln("Live classes: $created created.");
}
