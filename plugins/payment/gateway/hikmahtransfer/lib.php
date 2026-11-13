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
 * Callbacks.
 *
 * @package    paygw_hikmahtransfer
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serve a receipt. Receipts are private: only the learner who uploaded the file and users with
 * paygw/hikmahtransfer:viewreceipt may open it, and every access is logged (PRD ENR-05).
 *
 * @param stdClass $course
 * @param stdClass|null $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool false when the file cannot be served
 */
function paygw_hikmahtransfer_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $USER;

    if ($context->contextlevel != CONTEXT_SYSTEM || $filearea !== \paygw_hikmahtransfer\request::FILEAREA) {
        return false;
    }
    require_login(null, false);
    if (isguestuser()) {
        return false;
    }

    $requestid = (int) array_shift($args);
    $filename = array_pop($args);
    $request = \paygw_hikmahtransfer\request::get($requestid, IGNORE_MISSING);
    if (!$request) {
        return false;
    }

    $isowner = (int) $request->userid === (int) $USER->id;
    if (!$isowner && !has_capability('paygw/hikmahtransfer:viewreceipt', $context)) {
        return false;
    }

    $file = get_file_storage()->get_file($context->id, 'paygw_hikmahtransfer', $filearea, $requestid, '/', $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    \paygw_hikmahtransfer\event\receipt_viewed::create_from_request($request)->trigger();

    // Never cached by browsers or proxies.
    send_stored_file($file, 0, 0, $forcedownload, $options + ['cacheability' => 'private', 'immutable' => false]);
}
