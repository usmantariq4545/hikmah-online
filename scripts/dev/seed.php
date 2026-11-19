<?php
// Hikmah Online — demo data for the laptop (dev) environment only.
//
// Creates fictional categories, courses, accounts, a payment waiting for review and a simulated
// live class so every screen has something to show. Everything it creates carries the idnumber
// prefix "demo-" (courses) or the username prefix "demo." (accounts). It refuses to run outside
// SITE_ENV=dev unless HIKMAH_DEMO_ON_LIVE=yes is set on purpose (the owner asked for demo content
// on the live site on 2026-11-18), and does nothing on a second run.
//
// Run with: scripts/dev/seed.sh

define('CLI_SCRIPT', true);
require('/var/www/moodle/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/mod/attendance/locallib.php');

if (getenv('SITE_ENV') !== 'dev' && getenv('HIKMAH_DEMO_ON_LIVE') !== 'yes') {
    cli_error('Demo data is only for SITE_ENV=dev. Set HIKMAH_DEMO_ON_LIVE=yes to load it on a live site on purpose.');
}
$password = getenv('HIKMAH_DEMO_PASSWORD');
if (!$password) {
    cli_error('HIKMAH_DEMO_PASSWORD is not set. Run scripts/dev/seed.sh, which generates it.');
}

\core\session\manager::set_user(get_admin());
$generator = new testing_data_generator();
$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
$teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
$now = time();
$zone = new DateTimeZone('Asia/Karachi');

// ---- Accounts ---------------------------------------------------------------------------------------
$people = [
    'demo.teacher' => ['Hamza', 'Malik', 'Male'],
    'demo.teacher2' => ['Maryam', 'Siddiqui', 'Female'],
    'demo.finance' => ['Bilal', 'Ahmed', 'Male'],
    'demo.manager' => ['Sana', 'Qureshi', 'Female'],
    'demo.support' => ['Khadija', 'Anwar', 'Female'],
    'demo.assistant' => ['Usman', 'Tariq', 'Male'],
    'demo.student1' => ['Aisha', 'Khan', 'Female'],
    'demo.student2' => ['Omar', 'Farooq', 'Male'],
    'demo.student3' => ['Fatima', 'Raza', 'Female'],
    'demo.student4' => ['Yusuf', 'Hassan', 'Male'],
    'demo.student5' => ['Zainab', 'Iqbal', 'Female'],
    'demo.student6' => ['Ibrahim', 'Sheikh', 'Male'],
];
$users = [];
$policyversions = $DB->get_fieldset_sql(
    'SELECT p.currentversionid FROM {tool_policy} p WHERE p.currentversionid IS NOT NULL'
);
foreach ($people as $username => [$first, $last, $gender]) {
    $user = $DB->get_record('user', ['username' => $username]);
    if (!$user) {
        $id = user_create_user((object) [
            'username' => $username,
            'password' => $password,
            'firstname' => $first,
            'lastname' => $last,
            'email' => $username . '@example.invalid',
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'city' => 'Lahore',
            'country' => 'PK',
            'timezone' => 'Asia/Karachi',
            'lang' => 'en',
        ]);
        $user = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
    }
    // Required profile fields, so demo accounts are not stopped at the profile form.
    $profile = (object) [
        'id' => $user->id,
        'profile_field_hkgender' => $gender,
        'profile_field_hkdob' => mktime(12, 0, 0, 6, 15, 1998),
        'profile_field_hkphone' => '0300-0000000',
        'profile_field_hkconsentcomms' => 1,
    ];
    profile_save_data($profile);
    // Fictional accounts: record policy acceptance so the demo opens straight on the dashboard.
    if ($policyversions) {
        \tool_policy\api::accept_policies($policyversions, $user->id);
    }
    $users[$username] = $user;
}
if ($financerole = $DB->get_record('role', ['shortname' => 'financereviewer'])) {
    role_assign($financerole->id, $users['demo.finance']->id, context_system::instance()->id);
}
if ($supportrole = $DB->get_record('role', ['shortname' => 'support'])) {
    role_assign($supportrole->id, $users['demo.support']->id, context_system::instance()->id);
}
// A non-editing teacher on the Tajweed course, once that course exists.
$assistantrole = $DB->get_record('role', ['shortname' => 'teacher']);
$tajweedid = $DB->get_field('course', 'id', ['idnumber' => 'demo-tajweed']);
if ($assistantrole && $tajweedid) {
    enrol_try_internal_enrol($tajweedid, $users['demo.assistant']->id, $assistantrole->id);
}
if ($managerrole = $DB->get_record('role', ['shortname' => 'manager'])) {
    role_assign($managerrole->id, $users['demo.manager']->id, context_system::instance()->id);
}
cli_writeln('Accounts: ' . count($users));

// Accounts are topped up on every run; the rest is created once.
if ($DB->record_exists('course', ['idnumber' => 'demo-tajweed'])) {
    cli_writeln('Demo data already present; nothing to do.');
    exit(0);
}

// ---- Categories -------------------------------------------------------------------------------------
$categories = [];
foreach (['Qur\'an Studies', 'Arabic Language', 'Islamic Sciences'] as $name) {
    $existing = $DB->get_record('course_categories', ['name' => $name]);
    $categories[$name] = $existing ? $existing->id : core_course_category::create(['name' => $name])->id;
}

// ---- Courses ----------------------------------------------------------------------------------------
$start = static function (string $when) use ($zone): int {
    return (new DateTime($when, $zone))->getTimestamp();
};
$courses = [
    'demo-tajweed' => [
        'fullname' => 'Foundations of Tajweed',
        'category' => 'Qur\'an Studies',
        'summary' => '<p>A structured introduction to reciting the Qur\'an correctly. Each week you learn one set of ' .
            'rules, hear them applied, and recite to your teacher in a small live class.</p><p>By the end you will ' .
            'recite Juz Amma with correct articulation and know the reasons behind each rule.</p>',
        'startdate' => $start('-3 weeks monday 00:00'),
        'fields' => [
            'mode' => 'Live', 'language' => 'English and Urdu', 'length' => '12 weeks',
            'schedule' => 'Mondays and Thursdays, 6:00 to 7:00 pm',
            'accesspolicy' => 'Six months from approval, recordings included',
            'eligibility' => 'Open to men and women aged 14 and over.',
            'prerequisites' => 'You can read Arabic script slowly.',
            'outcomes' => "Pronounce every letter from its correct point of articulation\nApply the rules of noon " .
                "sakinah, meem sakinah and madd\nRecite Juz Amma fluently with tajweed\nRecognise and correct " .
                "your own common mistakes",
        ],
        'enrol' => ['fee', 4500],
        'teachers' => ['demo.teacher'],
        'sections' => [
            'Getting started' => [['page', 'Welcome and how this course works'], ['page', 'What you need for class']],
            'Points of articulation' => [['page', 'The throat letters'], ['page', 'The tongue letters'],
                ['assign', 'Recitation recording 1']],
            'Noon and meem rules' => [['page', 'Idhhar, idgham, iqlab, ikhfa'], ['quiz', 'Rules check']],
            'Madd' => [['page', 'Natural and secondary madd'], ['assign', 'Recitation recording 2']],
        ],
        'live' => true,
    ],
    'demo-arabic' => [
        'fullname' => 'Arabic for Beginners',
        'category' => 'Arabic Language',
        'summary' => '<p>Start reading, writing and understanding Arabic from the first lesson. Recorded lessons ' .
            'through the week, then a live class to practise speaking with your teacher and classmates.</p>',
        'startdate' => $start('+10 days 00:00'),
        'fields' => [
            'mode' => 'Hybrid', 'language' => 'English', 'length' => '16 weeks',
            'schedule' => 'Recorded lessons weekly; live practice Saturdays, 11:00 am to 12:00 pm',
            'accesspolicy' => 'For the length of the cohort plus one month',
            'eligibility' => 'Open to all. Places are limited and confirmed by Hikmah staff.',
            'prerequisites' => 'None.',
            'outcomes' => "Read and write the Arabic alphabet with vowels\nUse 300 everyday words\nForm simple " .
                "nominal and verbal sentences\nUnderstand short passages from the Qur'an",
        ],
        'enrol' => ['apply', 0],
        'teachers' => ['demo.teacher2'],
        'sections' => [
            'The alphabet' => [['page', 'Letters and sounds'], ['page', 'Joining letters'], ['quiz', 'Alphabet quiz']],
            'First words' => [['page', 'Greetings and introductions'], ['assign', 'Write about yourself']],
            'Simple sentences' => [['page', 'The nominal sentence'], ['page', 'The verbal sentence']],
        ],
        'live' => true,
    ],
    'demo-seerah' => [
        'fullname' => 'Seerah: The Prophetic Biography',
        'category' => 'Islamic Sciences',
        'summary' => '<p>The life of the Prophet Muhammad, peace be upon him, told in order from Makkah to Madinah, ' .
            'with the lessons each period holds. Study at your own pace with short recorded lessons and a quiz ' .
            'after each part.</p>',
        'startdate' => $start('-2 months 00:00'),
        'fields' => [
            'mode' => 'Self-paced', 'language' => 'English', 'length' => 'About 20 hours',
            'schedule' => 'Start any time',
            'accesspolicy' => 'Lifetime access',
            'eligibility' => 'Open to everyone.',
            'outcomes' => "Narrate the main events of the Makkan and Madinan periods\nPlace each event on a " .
                "timeline\nDraw practical lessons from the Prophet's character",
        ],
        'enrol' => ['self', 0],
        'teachers' => ['demo.teacher'],
        'sections' => [
            'Before prophethood' => [['page', 'Arabia before Islam'], ['page', 'Birth and early life']],
            'The Makkan period' => [['page', 'The first revelation'], ['page', 'The early Muslims'],
                ['quiz', 'Makkan period quiz']],
            'The Madinan period' => [['page', 'The Hijrah'], ['page', 'Building a community'],
                ['quiz', 'Madinan period quiz']],
        ],
        'live' => false,
    ],
    'demo-fiqh' => [
        'fullname' => 'Fiqh of Worship',
        'category' => 'Islamic Sciences',
        'summary' => '<p>Purification, prayer, fasting and zakah explained step by step with their evidences, in ' .
            'live classes where you can ask your questions.</p>',
        'startdate' => $start('+3 weeks 00:00'),
        'fields' => [
            'mode' => 'Live', 'language' => 'Urdu', 'length' => '10 weeks',
            'schedule' => 'Sundays, 8:00 to 9:30 pm',
            'accesspolicy' => 'Three months from approval',
            'eligibility' => 'Open to men and women aged 16 and over.',
            'outcomes' => "Perform wudu, ghusl and tayammum correctly\nKnow the conditions and pillars of " .
                "prayer\nCalculate zakah on common kinds of wealth",
        ],
        'enrol' => ['fee', 6000],
        'teachers' => ['demo.teacher', 'demo.teacher2'],
        'sections' => [
            'Purification' => [['page', 'Water and impurities'], ['page', 'Wudu step by step']],
            'Prayer' => [['page', 'Conditions and pillars'], ['quiz', 'Prayer quiz']],
        ],
        'live' => true,
    ],
    'demo-hadith' => [
        'fullname' => 'Understanding the Forty Hadith',
        'category' => 'Islamic Sciences',
        'summary' => '<p>Imam al-Nawawi\'s collection, one hadith at a time: the text, its meaning and how to act ' .
            'on it.</p>',
        'startdate' => $start('-1 month 00:00'),
        'fields' => [
            'mode' => 'Self-paced', 'language' => 'English and Urdu', 'length' => '42 short lessons',
            'schedule' => 'Start any time',
            'accesspolicy' => 'Lifetime access',
            'eligibility' => 'Open to everyone.',
            'outcomes' => "Explain the meaning of each of the forty hadith\nMemorise ten of them in Arabic",
        ],
        'enrol' => ['self', 0],
        'teachers' => ['demo.teacher2'],
        'sections' => [
            'Hadith 1 to 10' => [['page', 'Actions are by intentions'], ['page', 'Islam, iman and ihsan']],
            'Hadith 11 to 20' => [['page', 'Leave what makes you doubt'], ['quiz', 'Review quiz']],
        ],
        'live' => false,
    ],
    'demo-tafsir' => [
        'fullname' => 'Tafsir of Juz Amma',
        'category' => 'Qur\'an Studies',
        'summary' => '<p>The meanings of the short surahs you recite every day, with their context and lessons.</p>',
        'startdate' => $start('+6 weeks 00:00'),
        'fields' => [
            'mode' => 'Hybrid', 'language' => 'English', 'length' => '14 weeks',
            'schedule' => 'Recorded lessons weekly; live discussion Fridays, 9:00 pm',
            'accesspolicy' => 'Six months from approval',
            'eligibility' => 'Open to everyone.',
            'outcomes' => "Explain the main themes of each surah in Juz Amma\nConnect the surahs to daily worship",
        ],
        'enrol' => ['fee', 3500, $start('+2 weeks 00:00')],
        'teachers' => ['demo.teacher'],
        'sections' => [
            'Surah al-Fatihah' => [['page', 'The opening']],
            'The last ten surahs' => [['page', 'Al-Ikhlas, al-Falaq and an-Nas']],
        ],
        'live' => true,
    ],
];

$accountid = (int) $DB->get_field('payment_accounts', 'id', ['idnumber' => 'hikmah']);
$handler = \core_course\customfield\course_handler::create();
$fieldsbyname = [];
foreach ($handler->get_fields() as $field) {
    $fieldsbyname[$field->get('shortname')] = $field;
}
$created = [];

foreach ($courses as $idnumber => $spec) {
    $course = create_course((object) [
        'fullname' => $spec['fullname'],
        'shortname' => strtoupper(str_replace('demo-', 'D-', $idnumber)),
        'idnumber' => $idnumber,
        'category' => $categories[$spec['category']],
        'summary' => $spec['summary'],
        'summaryformat' => FORMAT_HTML,
        'format' => 'topics',
        'numsections' => count($spec['sections']),
        'startdate' => $spec['startdate'],
        'visible' => 1,
        'enablecompletion' => 1,
    ]);

    // Custom fields. Select fields store the 1-based index of the chosen option.
    $data = (object) ['id' => $course->id];
    foreach ($spec['fields'] as $shortname => $value) {
        if (!isset($fieldsbyname[$shortname])) {
            continue;
        }
        $field = $fieldsbyname[$shortname];
        if ($field->get('type') === 'select') {
            $index = array_search($value, $field->get_options(), true);
            $data->{'customfield_' . $shortname} = $index === false ? 0 : $index;
        } else if ($field->get('type') === 'textarea') {
            $data->{'customfield_' . $shortname . '_editor'} = ['text' => $value, 'format' => FORMAT_PLAIN];
        } else {
            $data->{'customfield_' . $shortname} = $value;
        }
    }
    $handler->instance_form_save($data);

    // Sections and activities.
    $number = 0;
    foreach ($spec['sections'] as $sectionname => $activities) {
        $number++;
        course_create_sections_if_missing($course, $number);
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $number], '*', MUST_EXIST);
        course_update_section($course, $section, ['name' => $sectionname]);
        foreach ($activities as [$module, $name]) {
            $options = ['course' => $course->id, 'name' => $name, 'section' => $number,
                'completion' => COMPLETION_TRACKING_MANUAL];
            if ($module === 'page') {
                $options['content'] = '<p>Lesson notes for <strong>' . s($name) . '</strong> appear here.</p>';
            }
            if ($module === 'quiz') {
                $options['timeopen'] = 0;
                $options['timeclose'] = 0;
                $options['grade'] = 10;
            }
            $generator->create_module($module, $options);
        }
    }

    // Live classroom, register and certificate.
    if ($spec['live']) {
        $created[$idnumber]['bbb'] = $generator->create_module('bigbluebuttonbn', [
            'course' => $course->id, 'name' => 'Live classroom', 'section' => 0, 'type' => 0,
            'wait' => 1, 'record' => 1, 'intro' => '<p>Join your live class here when it starts.</p>',
        ]);
        // Everyone joins as a viewer; teachers and assistant teachers run the class. Set after
        // creation: the test-data generator ignores a 'participants' value passed to it.
        $DB->set_field('bigbluebuttonbn', 'participants', json_encode([
            ['selectiontype' => 'all', 'selectionid' => 'all', 'role' => 'viewer'],
            ['selectiontype' => 'role', 'selectionid' => (string) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']),
                'role' => 'moderator'],
            ['selectiontype' => 'role', 'selectionid' => (string) $DB->get_field('role', 'id', ['shortname' => 'teacher']),
                'role' => 'moderator'],
        ]), ['id' => $created[$idnumber]['bbb']->id]);
        $created[$idnumber]['attendance'] = $generator->create_module('attendance', [
            'course' => $course->id, 'name' => 'Attendance register', 'section' => 0, 'grade' => 100,
            'intro' => '<p>Your attendance for each live class.</p>',
        ]);
    }
    $templateid = (int) $DB->get_field('tool_certificate_templates', 'id', ['name' => 'Hikmah certificate of completion']);
    if ($templateid && $DB->record_exists('modules', ['name' => 'coursecertificate'])) {
        try {
            $generator->create_module('coursecertificate', [
                'course' => $course->id, 'name' => 'Your certificate', 'section' => $number, 'template' => $templateid,
            ]);
        } catch (\Throwable $e) {
            cli_writeln('  note: certificate activity not added to ' . $idnumber . ': ' . $e->getMessage());
        }
    }

    // Enrolment route.
    [$method, $cost] = $spec['enrol'];
    $opens = $spec['enrol'][2] ?? 0;
    $plugin = enrol_get_plugin($method);
    if ($plugin) {
        $fields = ['status' => ENROL_INSTANCE_ENABLED, 'roleid' => $studentrole->id, 'enrolstartdate' => $opens];
        if ($method === 'fee') {
            $fields += ['cost' => $cost, 'currency' => 'PKR', 'customint1' => $accountid];
        }
        if ($method === 'self') {
            $fields += ['customint6' => 1, 'customint4' => 0, 'name' => ''];
        }
        if ($method === 'apply') {
            // customint6 = accept new applications (the plugin refuses every application without it).
            $fields += ['customint6' => 1, 'customtext1' => '', 'customint7' => 1,
                'customtext2' => 'Anything you would like us to know? (optional)'];
        }
        $created[$idnumber]['enrolid'] = $plugin->add_instance($course, $fields);
    } else {
        cli_writeln("  note: enrolment method $method is not available for $idnumber");
    }

    foreach ($spec['teachers'] as $username) {
        enrol_try_internal_enrol($course->id, $users[$username]->id, $teacherrole->id);
    }
    $created[$idnumber]['course'] = $course;
    cli_writeln('Course: ' . $spec['fullname']);
}

// ---- Enrolments -------------------------------------------------------------------------------------
$enrol = static function (string $idnumber, array $usernames) use ($created, $users, $studentrole): void {
    foreach ($usernames as $username) {
        enrol_try_internal_enrol($created[$idnumber]['course']->id, $users[$username]->id, $studentrole->id);
    }
};
$enrol('demo-tajweed', ['demo.student1', 'demo.student4', 'demo.student5', 'demo.student6']);
$enrol('demo-seerah', ['demo.student1', 'demo.student2', 'demo.student3']);
$enrol('demo-hadith', ['demo.student1', 'demo.student5']);
if ($assistantrole) {
    enrol_try_internal_enrol($created['demo-tajweed']['course']->id, $users['demo.assistant']->id, $assistantrole->id);
}

// ---- Payments waiting for the finance reviewer -----------------------------------------------------
$makereceipt = static function (stdClass $user, string $reference, string $amount): int {
    $image = imagecreatetruecolor(720, 420);
    imagefill($image, 0, 0, imagecolorallocate($image, 250, 250, 246));
    $ink = imagecolorallocate($image, 26, 26, 26);
    imagefilledrectangle($image, 0, 0, 720, 64, $ink);
    imagestring($image, 5, 24, 24, 'DEMO BANK - TRANSFER RECEIPT (fictional)', imagecolorallocate($image, 255, 224, 0));
    $lines = ['To: Hikmah Institute (demo account)', 'Amount: ' . $amount, 'Reference: ' . $reference,
        'From: ' . fullname($user), 'Status: Completed', 'This is sample data for the demo site.'];
    foreach ($lines as $i => $line) {
        imagestring($image, 5, 24, 100 + $i * 44, $line, $ink);
    }
    ob_start();
    imagepng($image);
    $png = ob_get_clean();
    imagedestroy($image);

    \core\session\manager::set_user($user);
    $draftitemid = file_get_unused_draft_itemid();
    get_file_storage()->create_file_from_string([
        'contextid' => context_user::instance($user->id)->id,
        'component' => 'user',
        'filearea' => 'draft',
        'itemid' => $draftitemid,
        'filepath' => '/',
        'filename' => 'transfer-receipt.png',
    ], $png);
    return $draftitemid;
};

if (!empty($created['demo-tajweed']['enrolid'])) {
    $payers = [
        ['demo.student2', 'demo-tajweed', 'bank', 4500, 'TXN-884120', null],
        ['demo.student3', 'demo-tajweed', 'jazzcash', 4500, 'JC-55310927', 'resubmit'],
        ['demo.student6', 'demo-fiqh', 'easypaisa', 5500, 'EP-10477213', null],
    ];
    foreach ($payers as [$username, $idnumber, $method, $paid, $txn, $then]) {
        if (empty($created[$idnumber]['enrolid'])) {
            continue;
        }
        $user = $users[$username];
        try {
            $request = \paygw_hikmahtransfer\request::get_or_create_draft('enrol_fee', 'fee',
                (int) $created[$idnumber]['enrolid'], (int) $user->id);
            $draft = $makereceipt($user, $request->reference, 'PKR ' . number_format($paid));
            \paygw_hikmahtransfer\request::submit((int) $request->id, (object) [
                'method' => $method, 'txnid' => $txn, 'paidamount' => $paid, 'paiddate' => $now - DAYSECS,
            ], $draft, (int) $user->id);
            if ($then === 'resubmit') {
                \core\session\manager::set_user($users['demo.finance']);
                \paygw_hikmahtransfer\request::request_resubmission((int) $request->id, (int) $users['demo.finance']->id,
                    'The screenshot is cut off. Please upload the full confirmation showing the transaction ID.');
            }
        } catch (\Throwable $e) {
            cli_writeln('  note: demo payment for ' . $username . ' not created: ' . $e->getMessage());
        }
    }
    cli_writeln('Payments: 2 pending, 1 returned for resubmission');
}
\core\session\manager::set_user(get_admin());

// ---- A past live class with simulated BigBlueButton evidence ----------------------------------------
if (!empty($created['demo-tajweed']['bbb'])) {
    $course = $created['demo-tajweed']['course'];
    $bbb = $created['demo-tajweed']['bbb'];
    $attendance = $DB->get_record('attendance', ['id' => $created['demo-tajweed']['attendance']->id], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('attendance', $attendance->id, $course->id, false, MUST_EXIST);
    $structure = new mod_attendance_structure($attendance, $cm, $course, context_module::instance($cm->id));

    $classes = [
        [$start('yesterday 18:00'), 'done'],
        [$start('-4 days 18:00'), 'done'],
        [$start('+2 days 18:00'), 'future'],   // Shown under "Coming up" on the dashboard.
    ];
    foreach ($classes as $index => [$windowstart, $kind]) {
        $windowend = $windowstart + HOURSECS;
        $sessionid = $structure->add_session((object) [
            'sessdate' => $windowstart,
            'duration' => HOURSECS,
            'groupid' => 0,
            'description' => 'Live class: Live classroom',
            'descriptionformat' => FORMAT_HTML,
            'descriptionitemid' => 0,
            'timemodified' => $now,
            'statusset' => 0,
            'calendarevent' => $kind === 'future' ? 1 : 0,
            'absenteereport' => 1,
        ]);
        $link = \local_hikmahattendance\manager::save_link((object) [
            'courseid' => $course->id,
            'bbbid' => $bbb->id,
            'attendanceid' => $attendance->id,
            'sessionid' => $sessionid,
            'windowstart' => $windowstart,
            'windowend' => $windowend,
            'threshold' => null,
            'cancelled' => 0,
        ], (int) $users['demo.teacher']->id);

        if ($kind !== 'done') {
            continue;
        }
        // What BigBlueButton would have posted after the class, stored as mod_bigbluebuttonbn stores it.
        $iso = static fn(int $t): string => gmdate('Y-m-d\TH:i:s\Z', $t);
        $attendees = [
            'demo.student1' => [[$windowstart - 120, $windowend]],                                    // Whole class.
            'demo.student4' => [[$windowstart + 1500, $windowend]],                                   // Joined 25 min late.
            'demo.student5' => [[$windowstart, $windowstart + 1200], [$windowstart + 1500, $windowend]], // Dropped, rejoined.
            // The sixth learner (demo.student6) did not attend.
            'demo.teacher' => [[$windowstart - 300, $windowend]],
        ];
        if ($index === 1) {
            unset($attendees['demo.student4']);
        }
        $log = static function (string $type, int $userid, array $meta, int $time) use ($DB, $course, $bbb): void {
            $DB->insert_record('bigbluebuttonbn_logs', (object) [
                'courseid' => $course->id,
                'bigbluebuttonbnid' => $bbb->id,
                'userid' => $userid,
                'timecreated' => $time,
                'meetingid' => $bbb->meetingid . '-' . $course->id . '-' . $bbb->id,
                'log' => $type,
                'meta' => json_encode($meta),
            ]);
        };
        $internal = sha1('demo-' . $windowstart) . '-' . $windowstart . '000';
        $log('Callback', 0, ['internalmeetingid' => $internal, 'callback' => 'meeting_events',
            'data' => ['duration' => 3900, 'demo' => true]], $windowend + 180);
        foreach ($attendees as $username => $intervals) {
            $userid = (int) $users[$username]->id;
            $log('Join', $userid, [], $intervals[0][0]);
            $log('Summary', $userid, ['recordid' => $internal, 'data' => [
                'ext_user_id' => (string) $userid,
                'name' => fullname($users[$username]),
                'moderator' => $username === 'demo.teacher',
                'joins' => array_map(static fn(array $i): string => $iso($i[0]), $intervals),
                'leaves' => array_map(static fn(array $i): string => $iso($i[1]), $intervals),
                'duration' => array_sum(array_map(static fn(array $i): int => $i[1] - $i[0], $intervals)),
                'engagement' => ['chats' => 1, 'talks' => 1, 'raisehand' => 0, 'emojis' => 0, 'poll_votes' => 0,
                    'talk_time' => 30],
            ]], $windowend + 180);
        }
        \local_hikmahattendance\manager::process_link((int) $link->id);
    }
    cli_writeln('Live classes: 2 past sessions with simulated BigBlueButton data, 1 upcoming');
}

// ---- A certificate that can be verified ---------------------------------------------------------------
if (class_exists('\tool_certificate\template')) {
    $templateid = (int) $DB->get_field('tool_certificate_templates', 'id', ['name' => 'Hikmah certificate of completion']);
    if ($templateid) {
        try {
            $course = $created['demo-hadith']['course'];
            $template = \tool_certificate\template::instance($templateid);
            $issueid = $template->issue_certificate(
                (int) $users['demo.student1']->id,
                null,
                [
                    'courseid' => $course->id,
                    'courseshortname' => $course->shortname,
                    'coursefullname' => $course->fullname,
                    'courseurl' => course_get_url($course)->out(false),
                    'coursecompletiondate' => userdate($now - 5 * DAYSECS, get_string('strftimedate', 'langconfig')),
                    'coursegrade' => '',
                ],
                'mod_coursecertificate',
                $course->id
            );
            $code = $DB->get_field('tool_certificate_issues', 'code', ['id' => $issueid]);
            cli_writeln('Certificate issued to demo.student1; verification code: ' . $code);
        } catch (\Throwable $e) {
            cli_writeln('  note: demo certificate not issued: ' . $e->getMessage());
        }
    }
}

cache_helper::purge_by_definition('theme_hikmah', 'stats');
rebuild_course_cache(0, true);
cli_writeln('Demo data ready.');
