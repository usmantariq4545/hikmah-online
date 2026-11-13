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

namespace paygw_hikmahtransfer;

use stored_file;

/**
 * Receipt files (PRD ENR-05): type and size validation, antivirus scan, private storage in the
 * system context, and removal at the end of the retention period. Files are only ever served
 * through pluginfile.php after a capability check; there is no public URL.
 *
 * @package    paygw_hikmahtransfer
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class receipt {
    /** @var string[] Accepted file extensions. */
    public const EXTENSIONS = ['.jpg', '.jpeg', '.png', '.pdf'];

    /** @var string[] Accepted content types, detected from the file content. */
    public const MIMETYPES = ['image/jpeg', 'image/png', 'application/pdf'];

    /** @var int Default maximum receipt size: 5 MB. */
    public const DEFAULT_MAXBYTES = 5242880;

    /**
     * Maximum receipt size in bytes.
     *
     * @return int
     */
    public static function maxbytes(): int {
        $configured = (int) get_config('paygw_hikmahtransfer', 'maxbytes');
        return $configured > 0 ? $configured : self::DEFAULT_MAXBYTES;
    }

    /**
     * File manager options for the submission form.
     *
     * @return array
     */
    public static function filemanager_options(): array {
        return [
            'subdirs' => 0,
            'maxbytes' => self::maxbytes(),
            'areamaxbytes' => self::maxbytes(),
            'maxfiles' => 1,
            'accepted_types' => self::EXTENSIONS,
            'return_types' => 2, // FILE_INTERNAL: uploaded files only, no links.
        ];
    }

    /**
     * Files in a user's draft area (directories excluded).
     *
     * @param int $draftitemid
     * @param int $userid
     * @return stored_file[]
     */
    public static function draft_files(int $draftitemid, int $userid): array {
        $context = \context_user::instance($userid);
        return array_values(get_file_storage()->get_area_files(
            $context->id,
            'user',
            'draft',
            $draftitemid,
            'id',
            false
        ));
    }

    /**
     * Validate the receipt waiting in a draft area: exactly one file, allowed type (checked
     * against the real content, not just the name), within the size limit, and clean according
     * to the antivirus manager.
     *
     * @param int $draftitemid
     * @param int $userid
     * @return string scan status: "clean" when a scanner checked the file, else "notscanned"
     * @throws \moodle_exception when the file is missing, invalid or infected
     */
    public static function validate_draft(int $draftitemid, int $userid): string {
        global $CFG;

        $files = self::draft_files($draftitemid, $userid);
        if (count($files) !== 1) {
            throw new \moodle_exception('error:receiptrequired', 'paygw_hikmahtransfer');
        }
        $file = $files[0];

        if ($file->get_filesize() <= 0 || $file->get_filesize() > self::maxbytes()) {
            throw new \moodle_exception('error:receiptsize', 'paygw_hikmahtransfer', '', display_size(self::maxbytes()));
        }

        $extension = '.' . strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        if (!in_array($extension, self::EXTENSIONS, true)) {
            throw new \moodle_exception('error:receipttype', 'paygw_hikmahtransfer');
        }

        $temp = $file->copy_content_to_temp('paygw_hikmahtransfer');
        if (!$temp) {
            throw new \moodle_exception('error:receiptread', 'paygw_hikmahtransfer');
        }
        try {
            $detected = function_exists('mime_content_type') ? (string) mime_content_type($temp) : '';
            if (!in_array($detected, self::MIMETYPES, true)) {
                throw new \moodle_exception('error:receipttype', 'paygw_hikmahtransfer');
            }
            try {
                // Throws when a scanner finds something, or cannot run and is set to refuse uploads.
                \core\antivirus\manager::scan_file($temp, $file->get_filename(), false);
            } catch (\core\antivirus\scanner_exception $e) {
                throw new \moodle_exception('error:receiptinfected', 'paygw_hikmahtransfer');
            }
        } finally {
            if (file_exists($temp)) {
                @unlink($temp);
            }
        }

        return !empty($CFG->antiviruses) ? 'clean' : 'notscanned';
    }

    /**
     * Move the receipt from the draft area into the private receipt area of a request,
     * replacing any earlier receipt.
     *
     * @param int $draftitemid
     * @param int $requestid
     */
    public static function store_from_draft(int $draftitemid, int $requestid): void {
        $context = \context_system::instance();
        file_save_draft_area_files(
            $draftitemid,
            $context->id,
            'paygw_hikmahtransfer',
            request::FILEAREA,
            $requestid,
            ['subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => self::maxbytes()]
        );
    }

    /**
     * The stored receipt of a request, or null if none (never uploaded, or removed by retention).
     *
     * @param int $requestid
     * @return stored_file|null
     */
    public static function get_file(int $requestid): ?stored_file {
        $files = get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'paygw_hikmahtransfer',
            request::FILEAREA,
            $requestid,
            'id',
            false
        );
        return $files ? reset($files) : null;
    }

    /**
     * Protected URL of a receipt. Works only for the owner and for users with viewreceipt.
     *
     * @param stored_file $file
     * @param bool $forcedownload
     * @return \moodle_url
     */
    public static function url(stored_file $file, bool $forcedownload = false): \moodle_url {
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            'paygw_hikmahtransfer',
            request::FILEAREA,
            $file->get_itemid(),
            '/',
            $file->get_filename(),
            $forcedownload
        );
    }

    /**
     * Delete the receipt file(s) of a request.
     *
     * @param int $requestid
     */
    public static function delete(int $requestid): void {
        get_file_storage()->delete_area_files(
            \context_system::instance()->id,
            'paygw_hikmahtransfer',
            request::FILEAREA,
            $requestid
        );
    }
}
