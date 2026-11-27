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
 * Serves /course/<slug> (the course itself) and /courses/<slug> (its catalog page). Nginx sends those
 * paths here as ?hkslug=<slug>&hkpage=view|info plus the visitor's own query string. The page's own script
 * runs as if it had been called with ?id=<course id>; an old name answers with a permanent redirect to
 * the current one, and an unknown name goes on to the Moodle Router.
 *
 * @package    local_hikmahurls
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing -- the included page checks access itself.
require_once(__DIR__ . '/../../config.php');

$hkslug = optional_param('hkslug', '', PARAM_ALPHANUMEXT);
$hkpage = optional_param('hkpage', 'view', PARAM_ALPHA) === 'info' ? 'info' : 'view';
unset($_GET['hkslug'], $_GET['hkpage'], $_REQUEST['hkslug'], $_REQUEST['hkpage']);

$hkfound = $hkslug !== '' ? \local_hikmahurls\slugs::find($hkslug) : null;
if (!$hkfound) {
    // Not a course name: whatever else the Router knows at this path, or its "not found" page.
    chdir($CFG->dirroot);
    require($CFG->dirroot . '/r.php');
    die();
}

$hkscript = $hkpage === 'info' ? '/theme/hikmah/course.php' : '/course/view.php';
if (!$hkfound->iscurrent) {
    // An old name (the course was renamed): send the visitor to the current link.
    $hkurl = new moodle_url($hkscript, ['id' => $hkfound->courseid] + $_GET);
    header('Location: ' . $hkurl->out(false), true, 301);
    die();
}

$_GET['id'] = $_REQUEST['id'] = $hkfound->courseid;
// The course page loads its helpers with relative paths (require_once('lib.php')), which PHP looks up from
// the current folder first.
chdir(dirname($CFG->dirroot . $hkscript));
unset($hkslug, $hkpage, $hkfound);
require($CFG->dirroot . $hkscript);
