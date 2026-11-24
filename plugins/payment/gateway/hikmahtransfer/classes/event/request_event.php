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

namespace paygw_hikmahtransfer\event;

use paygw_hikmahtransfer\request;

/**
 * Base class for events about a transfer request. Every status change and every receipt
 * download is logged (PRD OPS-05).
 *
 * @package    paygw_hikmahtransfer
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class request_event extends \core\event\base {
    /**
     * Create the event from a request record.
     *
     * @param \stdClass $request
     * @return static
     */
    public static function create_from_request(\stdClass $request): self {
        // Logged in the course's context (Moodle requires context and courseid to match); site level when the
        // request has no course or the course was deleted since.
        $courseid = (int) $request->courseid;
        $context = $courseid ? \context_course::instance($courseid, IGNORE_MISSING) : false;
        $event = static::create([
            'context' => $context ?: \context_system::instance(),
            'objectid' => $request->id,
            'relateduserid' => $request->userid,
            'courseid' => $context ? $courseid : 0,
            'other' => [
                'reference' => $request->reference,
                'status' => $request->status,
                'component' => $request->component,
                'paymentarea' => $request->paymentarea,
                'itemid' => (int) $request->itemid,
            ],
        ]);
        $event->add_record_snapshot(request::TABLE, $request);
        return $event;
    }

    /**
     * Shared initialisation.
     */
    protected function init() {
        $this->data['objecttable'] = request::TABLE;
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Link to the request in the review queue.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/payment/gateway/hikmahtransfer/review.php', ['id' => $this->objectid]);
    }

    /**
     * Object id mapping for backup/restore: requests are not part of course backups.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => request::TABLE, 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Other-field mapping for backup/restore.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
