<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for Hikmah attendance.
 *
 * @package    local_hikmahattendance
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['applied_result'] = '{$a->written} mark(s) written to the register. Kept as they were: {$a->skippedmanual} marked by hand, {$a->skippedpending} still pending review, {$a->skippedother} other.';
$string['apply'] = 'Apply to register';
$string['apply_hint'] = 'Apply writes the final statuses to the attendance register. Learners already marked by hand keep that mark, and anyone still Pending review is left unmarked. Changing a status needs a reason.';
$string['autowrite'] = 'Automatic writing';
$string['autowrite_desc'] = 'When on, computed statuses for this course are written to the register without an instructor pressing Apply. It comes on by itself when a live class with a start and end time is added. If BigBlueButton sends no class data, learners who joined are marked Present and the rest Absent; a report that arrives later replaces those marks. Pending cases and hand-entered marks are never touched.';
$string['autowrite_off'] = 'Off: marks are written only when an instructor applies them';
$string['autowrite_on'] = 'Automatic writing is on for this course';
$string['autowrite_saved'] = 'Automatic writing setting saved.';
$string['autowrite_turnoff'] = 'Turn off';
$string['autowrite_turnon'] = 'Turn on for this course';
$string['backtosessions'] = 'Back to live sessions';
$string['col_applied'] = 'Applied';
$string['col_attendedminutes'] = 'Attended (min)';
$string['col_classminutes'] = 'Class (min)';
$string['col_computed'] = 'Computed';
$string['col_evidence'] = 'Evidence';
$string['col_exported'] = 'Exported at';
$string['col_final'] = 'Final status';
$string['col_learner'] = 'Learner';
$string['col_liveclass'] = 'Live class';
$string['col_markedby'] = 'Marked by';
$string['col_percent'] = 'Percent';
$string['col_reason'] = 'Reason for change';
$string['col_register'] = 'Register';
$string['col_session'] = 'Session';
$string['col_state'] = 'State';
$string['col_summary'] = 'Summary';
$string['col_threshold'] = 'Threshold %';
$string['decisionstate_applied'] = 'Applied';
$string['decisionstate_approved'] = 'Approved, not written';
$string['decisionstate_overridden'] = 'Changed by instructor';
$string['decisionstate_preview'] = 'Preview';
$string['decisionstate_superseded'] = 'Replaced';
$string['differences'] = '{$a} learner(s) have a register mark that differs from the final status here.';
$string['downloadcsv'] = 'Download CSV';
$string['error:alreadylinked'] = 'That attendance session is already linked to a live class.';
$string['error:othercourse'] = 'The live class and the attendance session must belong to this course.';
$string['error:reasonmissing'] = '{$a} change(s) were not saved because no reason was given. Nothing was applied.';
$string['error:reasonrequired'] = 'Give a reason for changing the status.';
$string['error:session'] = 'That session does not belong to the chosen attendance activity.';
$string['error:status'] = 'Unknown attendance status.';
$string['error:superseded'] = 'This decision was replaced by newer evidence. Reload the page.';
$string['error:threshold'] = 'Enter a whole number from 1 to 100, or leave it empty.';
$string['error:window'] = 'The class must end after it starts.';
$string['error:windowlong'] = 'A class window cannot be longer than 24 hours.';
$string['event_applied'] = 'Attendance decisions applied';
$string['event_autowrite'] = 'Automatic attendance writing changed';
$string['event_overridden'] = 'Attendance decision overridden';
$string['exceptions_text'] = 'Sessions with a missing callback, an unreadable payload or learners pending review need a person to look at them.';
$string['exceptions_title'] = '{$a} session(s) need attention';
$string['form_bbb'] = 'Live class (BigBlueButton)';
$string['form_cancelled'] = 'Cancelled';
$string['form_cancelled_label'] = 'This class did not take place; skip it';
$string['form_newsession'] = 'New session in "{$a}"';
$string['form_save'] = 'Save link';
$string['form_session'] = 'Attendance session';
$string['form_session_help'] = 'Choose an existing session of the register, or create a new one for this class window.';
$string['form_threshold'] = 'Threshold % (site default {$a})';
$string['form_threshold_help'] = 'Share of the class window a learner must attend to be Present. Leave empty to use the site default.';
$string['form_windowend'] = 'Class ends';
$string['form_windowstart'] = 'Class starts';
$string['form_windowstart_help'] = 'Only time inside this window counts. Joining early or staying late adds nothing.';
$string['hikmahattendance:apply'] = 'Apply attendance decisions to the register';
$string['hikmahattendance:configure'] = 'Configure automatic attendance writing';
$string['hikmahattendance:review'] = 'Review attendance reconciliation';
$string['hikmahattendance:viewreports'] = 'View attendance reconciliation reports';
$string['index_eyebrow'] = 'Live class attendance';
$string['index_lead'] = 'Attendance is worked out from BigBlueButton\'s own record of each class. You review it here and decide what goes in the register. Site default threshold: {$a}%.';
$string['link_delete'] = 'Remove this link';
$string['link_deleted'] = 'Link removed. Marks already in the register were left as they are.';
$string['link_lead'] = 'Tell Hikmah which live class belongs to which register session, and when the class ran.';
$string['link_saved'] = 'Link saved.';
$string['linknote_unmapped'] = '{$a} participant(s) are not on the course roster.';
$string['linksession'] = 'Link a live class';
$string['linkstate_applied'] = 'Applied';
$string['linkstate_cancelled'] = 'Cancelled';
$string['linkstate_missing'] = 'Missing callback';
$string['linkstate_ready'] = 'Ready for review';
$string['linkstate_unknown'] = 'Unreadable payload';
$string['linkstate_waiting'] = 'Waiting for class data';
$string['linktitle'] = 'Link live class to register';
$string['logremark'] = 'Hikmah attendance: {$a->percent}% of class ({$a->minutes} min) from BigBlueButton';
$string['logremark_joins'] = 'Hikmah attendance: joined the live class (BigBlueButton sent no class data, so time in class was not measured)';
$string['minutes_threshold'] = '{$a->minutes} min · Present at {$a->threshold}%';
$string['missing_text'] = 'BigBlueButton has not sent its post-class data for this session. Hikmah keeps checking every five minutes. Until it arrives, mark the register by hand if you need to.';
$string['missing_title'] = 'No class data received';
$string['navtitle'] = 'Attendance reconciliation';
$string['needs_attendance'] = 'Add an Attendance activity to the course.';
$string['needs_bbb'] = 'Add a BigBlueButton activity to the course.';
$string['needs_gotocourse'] = 'Go to the course';
$string['needs_title'] = 'This course is not ready for reconciliation yet';
$string['nodecisions'] = 'Nothing to review yet';
$string['nodecisions_text'] = 'Decisions appear here once the class window has ended and BigBlueButton has sent its data.';
$string['nojoin'] = 'no join logged';
$string['nolinks'] = 'No live classes linked yet';
$string['nolinks_text'] = 'Link a BigBlueButton class to a register session to see computed attendance here.';
$string['note_manualmark'] = 'already marked by hand';
$string['note_moreleavesthanjoins'] = 'more leaves than joins';
$string['note_nojoinlog'] = 'BBB reports time but Moodle logged no join';
$string['note_nostatus'] = 'no matching register status';
$string['note_totalonly'] = 'total duration only';
$string['note_unmapped'] = 'not on the course roster';
$string['note_unreadablejoin'] = 'unreadable join time';
$string['note_unreadableleave'] = 'unreadable leave time';
$string['openregister'] = 'Open register';
$string['overrides_saved'] = '{$a} change(s) saved.';
$string['pluginname'] = 'Hikmah attendance';
$string['privacy:metadata:audit'] = 'Audit trail of attendance reconciliation actions.';
$string['privacy:metadata:audit:action'] = 'What was done.';
$string['privacy:metadata:audit:actorid'] = 'Who did it.';
$string['privacy:metadata:audit:reason'] = 'The reason given.';
$string['privacy:metadata:audit:timecreated'] = 'When it was done.';
$string['privacy:metadata:audit:userid'] = 'The learner concerned.';
$string['privacy:metadata:decision'] = 'Attendance computed for a learner in a live class.';
$string['privacy:metadata:decision:computedstatus'] = 'The computed status.';
$string['privacy:metadata:decision:evidence'] = 'Join and leave times used for the computation.';
$string['privacy:metadata:decision:finalstatus'] = 'The status after an instructor override.';
$string['privacy:metadata:decision:percent'] = 'Share of the class attended.';
$string['privacy:metadata:decision:reason'] = 'Reason for an override.';
$string['privacy:metadata:decision:seconds'] = 'Seconds attended inside the class window.';
$string['privacy:metadata:decision:timecreated'] = 'When the decision was computed.';
$string['privacy:metadata:decision:userid'] = 'The learner.';
$string['processall'] = 'Check all sessions now';
$string['processed_n'] = '{$a} session(s) checked.';
$string['reason_hint'] = 'Needed if you change the status';
$string['recompute'] = 'Check for new data';
$string['recomputed'] = 'Evidence re-read.';
$string['reconcile'] = 'Review';
$string['reconciletitle'] = 'Attendance review';
$string['report_lead'] = 'What BigBlueButton recorded beside the final mark in the register, for every reviewed session.';
$string['reporttitle'] = 'Reconciliation report';
$string['savechanges'] = 'Save changes only';
$string['sessiondescription'] = 'Live class: {$a}';
$string['setting_acronym_absent'] = 'Register status for Absent';
$string['setting_acronym_desc'] = 'Acronym of the attendance status to write.';
$string['setting_acronym_partial'] = 'Register status for Below threshold';
$string['setting_acronym_present'] = 'Register status for Present';
$string['setting_missinghours'] = 'Hours before "missing callback"';
$string['setting_missinghours_desc'] = 'How long after a class ends a session without BigBlueButton data is flagged in the exceptions queue.';
$string['setting_retentiondays'] = 'Evidence retention (days)';
$string['setting_retentiondays_desc'] = 'Join and leave times are removed from decisions after this many days. The computed result is kept.';
$string['setting_threshold'] = 'Default threshold %';
$string['setting_threshold_desc'] = 'Share of the class window a learner must attend to be Present. Can be changed per session.';
$string['status_absent'] = 'Absent';
$string['status_partial'] = 'Below threshold';
$string['status_pending'] = 'Pending review';
$string['status_present'] = 'Present';
$string['task_cleanup'] = 'Remove old attendance evidence';
$string['task_process'] = 'Compute live class attendance previews';
$string['unknown_text'] = 'BigBlueButton sent data in a shape Hikmah does not recognise, so nothing was computed. This usually follows a BigBlueButton upgrade; tell the technical owner.';
$string['unknown_title'] = 'Class data could not be read';
$string['waiting_text'] = 'The class has not finished, or BigBlueButton has not sent its data yet.';
$string['waiting_title'] = 'Waiting for class data';
