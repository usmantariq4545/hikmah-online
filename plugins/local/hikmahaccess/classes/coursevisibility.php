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

namespace local_hikmahaccess;

use core_course\hook\after_form_definition;
use core_course\hook\after_form_definition_after_data;
use core_course\hook\after_form_submission;
use core_course\hook\after_form_validation;

/**
 * Course visibility on the course form: Hidden, Public, or Scheduled to go public at a set time
 * (owner, 2026-11-23). A scheduled course stays hidden until then; a task makes it public.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursevisibility {
    /** Choice values of the Visibility field. */
    public const HIDDEN = 0;
    /** Public. */
    public const PUBLIC = 1;
    /** Scheduled. */
    public const SCHEDULED = 2;

    /** Table of scheduled courses. */
    public const TABLE = 'local_hikmahaccess_publish';

    /**
     * Replace Moodle's Hide/Show select with the three choices and a date picker.
     *
     * @param after_form_definition $hook
     */
    public static function form_definition(after_form_definition $hook): void {
        $mform = $hook->mform;
        if (!$mform->elementExists('visible') || $mform->getElement('visible')->isFrozen()) {
            return; // People who may not change visibility keep Moodle's frozen field.
        }
        $default = (int) ($mform->getElement('visible')->getValue()[0] ?? 0);
        $choices = [
            self::HIDDEN => get_string('visibility_hidden', 'local_hikmahaccess'),
            self::PUBLIC => get_string('visibility_public', 'local_hikmahaccess'),
            self::SCHEDULED => get_string('visibility_scheduled', 'local_hikmahaccess'),
        ];
        $mform->insertElementBefore($mform->createElement('select', 'hkvisibility',
            get_string('visibility', 'local_hikmahaccess'), $choices), 'visible');
        $mform->insertElementBefore($mform->createElement('date_time_selector', 'hkpublishat',
            get_string('publishat', 'local_hikmahaccess'), ['step' => 5]), 'visible');
        $mform->removeElement('visible');
        $mform->addHelpButton('hkvisibility', 'visibility', 'local_hikmahaccess');
        $mform->setDefault('hkvisibility', $default ? self::PUBLIC : self::HIDDEN);
        $mform->setDefault('hkpublishat', time() + DAYSECS);
        $mform->hideIf('hkpublishat', 'hkvisibility', 'neq', self::SCHEDULED);
        // Saved hidden first; the choice is applied in form_submission, so nothing goes public by mistake.
        $mform->addElement('hidden', 'visible');
        $mform->setType('visible', PARAM_INT);
        $mform->setConstant('visible', 0);
    }

    /**
     * Show an existing course's current state.
     *
     * @param after_form_definition_after_data $hook
     */
    public static function form_definition_after_data(after_form_definition_after_data $hook): void {
        global $DB;
        $mform = $hook->mform;
        $courseid = (int) ($mform->elementExists('id') ? $mform->getElementValue('id') : 0);
        if (!$mform->elementExists('hkvisibility') || !$courseid || $hook->formwrapper->is_submitted()) {
            return;
        }
        $choice = $DB->get_field('course', 'visible', ['id' => $courseid]) ? self::PUBLIC : self::HIDDEN;
        if ($choice === self::HIDDEN && ($at = $DB->get_field(self::TABLE, 'timepublish', ['courseid' => $courseid]))) {
            $choice = self::SCHEDULED;
            $mform->setDefault('hkpublishat', (int) $at);
        }
        $mform->setDefault('hkvisibility', $choice);
    }

    /**
     * A scheduled time must be in the future.
     *
     * @param after_form_validation $hook
     */
    public static function form_validation(after_form_validation $hook): void {
        $data = $hook->get_data();
        if ((int) ($data['hkvisibility'] ?? -1) === self::SCHEDULED && (int) ($data['hkpublishat'] ?? 0) <= time()) {
            $hook->add_errors(['hkpublishat' => get_string('publishatpast', 'local_hikmahaccess')]);
        }
    }

    /**
     * Apply the choice. Runs after a new course is inserted, and before an edited course is saved.
     *
     * @param after_form_submission $hook
     */
    public static function form_submission(after_form_submission $hook): void {
        global $CFG, $DB;
        $data = $hook->get_data();
        if (!isset($data->hkvisibility) || empty($data->id)) {
            return; // Not from the course form (e.g. the show/hide icon), or visibility was frozen.
        }
        $choice = (int) $data->hkvisibility;
        self::schedule((int) $data->id, $choice === self::SCHEDULED ? (int) $data->hkpublishat : null);
        $visible = $choice === self::PUBLIC ? 1 : 0;
        if ($hook->isnewcourse) {
            if ($visible) {
                require_once($CFG->dirroot . '/course/lib.php');
                course_change_visibility((int) $data->id, true);
            }
            return;
        }
        $old = (int) $DB->get_field('course', 'visible', ['id' => $data->id]);
        $data->visible = $visible;
        if ($old !== $visible) {
            $data->visibleold = $visible;
            \cache_helper::purge_by_event('changesincoursecat');
        }
    }

    /**
     * Set or clear a course's publish time.
     *
     * @param int $courseid
     * @param int|null $timepublish null clears it
     */
    public static function schedule(int $courseid, ?int $timepublish): void {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['courseid' => $courseid]);
        if ($timepublish === null) {
            if ($row) {
                $DB->delete_records(self::TABLE, ['id' => $row->id]);
            }
        } else if ($row) {
            $DB->update_record(self::TABLE, ['id' => $row->id, 'timepublish' => $timepublish, 'timemodified' => time()]);
        } else {
            $DB->insert_record(self::TABLE, ['courseid' => $courseid, 'timepublish' => $timepublish,
                'timemodified' => time()]);
        }
    }

    /**
     * Make every course whose time has come public. Called by the scheduled task.
     *
     * @return int courses published
     */
    public static function publish_due(): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $published = 0;
        foreach ($DB->get_records_select(self::TABLE, 'timepublish <= ?', [time()]) as $row) {
            if ($DB->record_exists('course', ['id' => $row->courseid])) {
                course_change_visibility((int) $row->courseid, true);
                $published++;
            }
            $DB->delete_records(self::TABLE, ['id' => $row->id]);
        }
        return $published;
    }

    /**
     * Forget the schedule of a deleted course.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['courseid' => $event->objectid]);
    }

    /**
     * On the course management page, mark scheduled courses with their time.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer(\core\hook\output\before_footer_html_generation $hook): void {
        global $DB, $PAGE;
        if ($PAGE->pagetype !== 'course-management' || !isloggedin()) {
            return;
        }
        $labels = [];
        foreach ($DB->get_records(self::TABLE) as $row) {
            $labels[$row->courseid] = get_string('scheduledfor', 'local_hikmahaccess',
                userdate($row->timepublish, get_string('strftimedatetimeshort', 'langconfig')));
        }
        if (!$labels) {
            return;
        }
        $hook->add_html('<script>(function(labels){var add=function(){document.querySelectorAll('
            . '".listitem-course[data-id]").forEach(function(li){var t=labels[li.dataset.id];'
            . 'if(!t||li.querySelector(".hk-scheduled"))return;var b=document.createElement("span");'
            . 'b.className="hk-scheduled badge bg-warning text-dark ms-2";b.textContent=t;'
            . 'var n=li.querySelector(".coursename")||li;n.after?n.after(b):li.appendChild(b);});};add();'
            . 'new MutationObserver(add).observe(document.body,{childList:true,subtree:true});})('
            . json_encode($labels, JSON_HEX_TAG | JSON_HEX_AMP) . ');</script>');
    }
}
