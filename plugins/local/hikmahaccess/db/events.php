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
 * Event observers.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_deleted',
        'callback' => '\local_hikmahaccess\coursevisibility::course_deleted',
    ],
    [
        'eventname' => '\core\event\role_assigned',
        'callback' => '\local_hikmahaccess\observer::role_assigned',
    ],
    [
        'eventname' => '\core\event\user_enrolment_created',
        'callback' => '\local_hikmahaccess\observer::user_enrolment_created',
    ],
    // PostHog key events (local_hikmahaccess\analytics): queued for the person, sent from their browser after consent.
    ['eventname' => '\core\event\user_created', 'callback' => '\local_hikmahaccess\analytics::observe'],
    ['eventname' => '\core\event\user_updated', 'callback' => '\local_hikmahaccess\analytics::observe'],
    ['eventname' => '\core\event\course_viewed', 'callback' => '\local_hikmahaccess\analytics::observe'],
    ['eventname' => '\core\event\user_enrolment_created', 'callback' => '\local_hikmahaccess\analytics::observe'],
    ['eventname' => '\paygw_hikmahtransfer\event\request_submitted', 'callback' => '\local_hikmahaccess\analytics::observe'],
    ['eventname' => '\paygw_hikmahtransfer\event\request_approved', 'callback' => '\local_hikmahaccess\analytics::observe'],
    ['eventname' => '\mod_bigbluebuttonbn\event\meeting_joined', 'callback' => '\local_hikmahaccess\analytics::observe'],
    ['eventname' => '\tool_certificate\event\certificate_issued', 'callback' => '\local_hikmahaccess\analytics::observe'],
];
