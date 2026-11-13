<?php
// 090 — Attendance register (ATT-01) and Hikmah reconciliation defaults (ATT-04).
require(__DIR__ . '/lib.php');

global $DB;

if (!$DB->get_field('modules', 'visible', ['name' => 'attendance'])) {
    \core\plugininfo\mod::enable_plugin('attendance', 1);
    hk_changed('Attendance activity enabled');
}

// Site default status set (copied into every new attendance activity):
// Present, Late, Excused, Absent, Pending review.
$wanted = [
    'P' => ['Present', 2.00],
    'L' => ['Late', 1.00],
    'E' => ['Excused', 1.00],
    'A' => ['Absent', 0.00],
    'PR' => ['Pending review', 0.00],
];
foreach ($wanted as $acronym => [$description, $grade]) {
    $status = $DB->get_record('attendance_statuses', ['attendanceid' => 0, 'acronym' => $acronym, 'deleted' => 0]);
    if (!$status) {
        $DB->insert_record('attendance_statuses', (object) [
            'attendanceid' => 0,
            'acronym' => $acronym,
            'description' => $description,
            'grade' => $grade,
            'visible' => 1,
            'deleted' => 0,
            'setnumber' => 0,
        ]);
        hk_changed("default attendance status $acronym created");
    } else if ($status->description !== $description) {
        $DB->set_field('attendance_statuses', 'description', $description, ['id' => $status->id]);
        hk_changed("default attendance status $acronym renamed");
    }
}

// Reconciliation: Present at 75% of the class window; below that is written as Late.
// Automatic writing is not a site setting: it is switched per course and ships off.
hk_set('threshold', 75, 'local_hikmahattendance');
hk_set('acronym_present', 'P', 'local_hikmahattendance');
hk_set('acronym_partial', 'L', 'local_hikmahattendance');
hk_set('acronym_absent', 'A', 'local_hikmahattendance');
hk_set('missinghours', 2, 'local_hikmahattendance');
hk_set('retentiondays', 365, 'local_hikmahattendance');

// Learners never mark themselves.
hk_set('studentscanmark', 0, 'attendance');

hk_done(__FILE__);
