<?php
// 100 — Certificates (CERT-01 to CERT-04): one site-level Hikmah template with learner name,
// course, issue date, unique code and QR code; public verification.
//
// Caution from the PRD: never change the course category structure without a backup. A reported
// issue deleted tool_certificate templates and revoked issued certificates.
require(__DIR__ . '/lib.php');

global $DB;

if (!class_exists('\tool_certificate\template')) {
    cli_writeln('  note: tool_certificate is not installed; skipping');
    hk_done(__FILE__);
    return;
}

foreach (['coursecertificate'] as $module) {
    if ($DB->record_exists('modules', ['name' => $module]) && !$DB->get_field('modules', 'visible', ['name' => $module])) {
        \core\plugininfo\mod::enable_plugin($module, 1);
        hk_changed("$module activity enabled");
    }
}

$name = 'Hikmah certificate of completion';
$system = context_system::instance();
if (!$DB->record_exists('tool_certificate_templates', ['name' => $name])) {
    $template = \tool_certificate\template::create((object) ['name' => $name, 'contextid' => $system->id, 'shared' => 1]);
    $page = $template->new_page();
    $page->save((object) ['width' => 297, 'height' => 210, 'leftmargin' => 0, 'rightmargin' => 0]);
    $pageid = $page->get_id();

    $ink = '#1a1a1a';
    $muted = '#5c5b55';
    // Landscape A4 is 297 x 210 mm. refpoint 1 = position is the centre of the element.
    $elements = [
        ['border', 'Frame', ['data' => 2, 'colour' => '#ffe000', 'width' => 2]],
        ['text', 'Institute', ['data' => 'HIKMAH INSTITUTE', 'fontsize' => 13, 'colour' => $muted, 'posx' => 148,
            'posy' => 28, 'width' => 0, 'refpoint' => 1, 'alignment' => 'C']],
        ['text', 'Title', ['data' => 'Certificate of Completion', 'font' => 'freeserif', 'fontsize' => 34,
            'colour' => $ink, 'posx' => 148, 'posy' => 42, 'width' => 0, 'refpoint' => 1, 'alignment' => 'C']],
        ['text', 'Lead', ['data' => 'This is to certify that', 'fontsize' => 13, 'colour' => $muted, 'posx' => 148,
            'posy' => 68, 'width' => 0, 'refpoint' => 1, 'alignment' => 'C']],
        ['userfield', 'Learner name', ['data' => 'fullname', 'font' => 'freeserif', 'fontsize' => 30, 'colour' => $ink,
            'posx' => 148, 'posy' => 80, 'width' => 0, 'refpoint' => 1, 'alignment' => 'C']],
        ['text', 'Completed', ['data' => 'has successfully completed the course', 'fontsize' => 13, 'colour' => $muted,
            'posx' => 148, 'posy' => 102, 'width' => 0, 'refpoint' => 1, 'alignment' => 'C']],
        ['program', 'Course name', ['data' => json_encode(['display' => 'coursefullname']), 'font' => 'freeserif',
            'fontsize' => 22, 'colour' => $ink, 'posx' => 148, 'posy' => 114, 'width' => 230, 'refpoint' => 1,
            'alignment' => 'C']],
        ['text', 'Issued label', ['data' => 'Issued on', 'fontsize' => 10, 'colour' => $muted, 'posx' => 30, 'posy' => 160,
            'width' => 0, 'refpoint' => 0, 'alignment' => 'L']],
        ['date', 'Issue date', ['data' => json_encode(['dateitem' => -1, 'dateformat' => 'strftimedate']),
            'fontsize' => 13, 'colour' => $ink, 'posx' => 30, 'posy' => 166, 'width' => 0, 'refpoint' => 0,
            'alignment' => 'L']],
        ['text', 'Code label', ['data' => 'Certificate code', 'fontsize' => 10, 'colour' => $muted, 'posx' => 30,
            'posy' => 178, 'width' => 0, 'refpoint' => 0, 'alignment' => 'L']],
        ['code', 'Code', ['data' => json_encode(['display' => 1]), 'fontsize' => 13, 'colour' => $ink, 'posx' => 30,
            'posy' => 184, 'width' => 0, 'refpoint' => 0, 'alignment' => 'L']],
        ['code', 'QR code', ['data' => json_encode(['display' => 4]), 'posx' => 237, 'posy' => 156, 'width' => 32,
            'refpoint' => 0]],
        ['text', 'Verify hint', ['data' => 'Scan to verify', 'fontsize' => 9, 'colour' => $muted, 'posx' => 253,
            'posy' => 191, 'width' => 0, 'refpoint' => 1, 'alignment' => 'C']],
    ];
    foreach ($elements as [$type, $elementname, $data]) {
        $element = \tool_certificate\element::instance(0, (object) ['pageid' => $pageid, 'element' => $type]);
        $element->save((object) array_merge([
            'pageid' => $pageid,
            'element' => $type,
            'name' => $elementname,
            'font' => 'freesans',
        ], $data));
    }
    hk_changed('Hikmah certificate template created');
}

// Public verification: the capability goes to guests and signed-in users (plugin default);
// make sure nobody has taken it away.
foreach (['guest', 'user'] as $shortname) {
    $roleid = (int) $DB->get_field('role', 'id', ['shortname' => $shortname]);
    if ($roleid) {
        hk_role_cap($roleid, 'tool/certificate:verify', CAP_ALLOW);
    }
}

// Certificate rules (owner's decision, 2026-11-20): each course sets its minimum grade and attendance
// (course fields certgrade / certattendance, or its "Certificate rules" page); these are the defaults.
// Every course with a certificate is brought in line once here; after that its own edits re-apply them.
if (class_exists('\local_hikmahcompletion\rules')) {
    hk_set('defaultgrade', 50, 'local_hikmahcompletion');
    hk_set('defaultattendance', 75, 'local_hikmahcompletion');
    $certcourses = $DB->get_fieldset_sql("SELECT DISTINCT cm.course FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module AND m.name = 'coursecertificate' WHERE cm.deletioninprogress = 0");
    foreach ($certcourses as $courseid) {
        foreach (\local_hikmahcompletion\rules::sync((int) $courseid) as $change) {
            hk_changed("certificate rules, course $courseid: $change");
        }
    }
}

hk_done(__FILE__);
