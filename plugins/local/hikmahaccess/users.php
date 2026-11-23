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
 * "Users": every user for managers, support and administrators; their learners for teachers. View only.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login(null, false);
$system = context_system::instance();
$PAGE->set_url('/local/hikmahaccess/users.php');
$PAGE->set_context($system);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('users'));
$PAGE->set_heading(get_string('users'));

$class = \local_hikmahaccess\userlist::report_class();
if (!$class) {
    throw new required_capability_exception($system, 'local/hikmahaccess:viewlearners', 'nopermissions', '');
}
$isall = $class === \local_hikmahaccess\reportbuilder\local\systemreports\all_users::class;
$choose = $isall && \local_hikmahaccess\userlist::can_choose_columns();

// The "Columns" button (administrators and managers): save the ticked columns, or go back to the default ones.
if ($choose && optional_param('savecolumns', 0, PARAM_BOOL) && confirm_sesskey()) {
    $columns = optional_param('resetcolumns', 0, PARAM_BOOL) ? [] : optional_param_array('columns', [], PARAM_ALPHANUMEXT);
    \local_hikmahaccess\userlist::set_columns($columns);
    redirect($PAGE->url);
}

$report = \core_reportbuilder\system_report_factory::create($class, $system);
\local_hikmahaccess\event\users_list_viewed::create(['context' => $system, 'other' => ['list' => $isall ? 'all' : 'learners']])
    ->trigger();

echo $OUTPUT->header();
if ($choose) {
    $chosen = \local_hikmahaccess\userlist::chosen_columns();
    $options = [];
    foreach (\local_hikmahaccess\userlist::column_options() as $key => $name) {
        $options[] = ['key' => $key, 'name' => $name, 'checked' => in_array($key, $chosen, true)];
    }
    echo $OUTPUT->render_from_template(
        'local_hikmahaccess/columns',
        ['action' => $PAGE->url->out(false), 'sesskey' => sesskey(), 'options' => $options]
    );
}
echo $report->output();
echo $OUTPUT->footer();
