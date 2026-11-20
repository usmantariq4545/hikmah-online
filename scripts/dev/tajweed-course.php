<?php
// Builds the demo course "Foundations of Tajweed" (idnumber demo-tajweed) into a realistic eight-class
// course: Saturdays and Sundays from 1 November 2026, five classes held, three to come, with notes,
// slides, a handbook, glossary, announcements, Q&A, quizzes, assignments, a poll, a survey, attendance
// and live-class evidence. Everything is done as the course's instructor (demo.teacher) or assistant
// (demo.assistant), as it would be from their own logins; students' work is done as each student.
//
// Run inside the moodle container as www-data, after scripts/dev/seed.php:
//   HIKMAH_DEMO_ON_LIVE=yes php tajweed-course.php [--dry-run]
// --dry-run builds everything inside a database transaction and rolls it back. It refuses to run
// twice (the course's first class section is the marker).

define('CLI_SCRIPT', true);
require('/var/www/moodle/config.php');
foreach (['/clilib.php', '/testing/generator/lib.php', '/filelib.php', '/completionlib.php', '/gradelib.php',
        '/pdflib.php', '/questionlib.php'] as $lib) {
    require_once($CFG->libdir . $lib);
}
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/attendance/locallib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/choice/lib.php');

[$options] = cli_get_params(['dry-run' => false], []);
if (getenv('SITE_ENV') !== 'dev' && getenv('HIKMAH_DEMO_ON_LIVE') !== 'yes') {
    cli_error('Demo data: set HIKMAH_DEMO_ON_LIVE=yes to build it on a live site on purpose.');
}
$content = require(__DIR__ . '/tajweed-content.php');

$course = $DB->get_record('course', ['idnumber' => 'demo-tajweed'], '*', MUST_EXIST);
$firstclass = 'Class 1 · ' . $content['classes'][1]['title'];
if ($DB->record_exists('course_sections', ['course' => $course->id, 'name' => $firstclass])) {
    cli_error('Foundations of Tajweed is already built.');
}
$user = static fn(string $name) => $DB->get_record('user', ['username' => $name], '*', MUST_EXIST);
$teacher = $user('demo.teacher');
$assistant = $user('demo.assistant');
$students = [];
foreach (['demo.student1', 'demo.student4', 'demo.student5', 'demo.student6'] as $name) {
    $students[$name] = $user($name);
}
[$aisha, $yusuf, $zainab, $ibrahim] = array_values($students);
$as = static function (stdClass $who): void {
    \core\session\manager::set_user($who);
};

$zone = new DateTimeZone('Asia/Karachi');
$at = static fn(string $date, string $time = '18:00'): int => (new DateTime("$date $time", $zone))->getTimestamp();
$classes = $content['classes'];
foreach ($classes as $n => $class) {
    if (!in_array((new DateTime($class["date"], $zone))->format("D"), ["Sat", "Sun"], true)) {
        cli_error("Class $n is not on a Saturday or Sunday: {$class["date"]}");
    }
}
$now = time();
$held = array_filter($classes, static fn(array $c): bool => $at($c['date']) + HOURSECS < $now);

$gen = new testing_data_generator();
$add = static function (string $module, int $section, array $record) use ($gen, $course): stdClass {
    return $gen->create_module($module, ['course' => $course->id, 'section' => $section] + $record);
};
// A file in the acting user's draft area, for resources and folders.
$draft = static function (array $files): int {
    global $USER;
    $itemid = file_get_unused_draft_itemid();
    foreach ($files as $filename => $bytes) {
        get_file_storage()->create_file_from_string(['contextid' => context_user::instance($USER->id)->id,
            'component' => 'user', 'filearea' => 'draft', 'itemid' => $itemid, 'filepath' => '/',
            'filename' => $filename], $bytes);
    }
    return $itemid;
};
// Landscape PDF slides in Hikmah colours.
$slides = static function (string $deck, array $pages): string {
    $pdf = new pdf('L', 'mm', 'A4');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetAutoPageBreak(false);
    foreach ($pages as $i => [$title, $bullets]) {
        $pdf->AddPage();
        $pdf->SetFillColor(26, 26, 26);
        $pdf->Rect(0, 0, 297, 210, 'F');
        $pdf->SetFillColor(255, 214, 0);
        $pdf->Rect(20, 30, 40, 2.5, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('freeserif', '', $i === 0 ? 40 : 30);
        $pdf->SetXY(20, 38);
        $pdf->MultiCell(257, 16, $title, 0, 'L');
        $pdf->SetFont('freeserif', '', 20);
        $pdf->SetTextColor(230, 230, 230);
        $pdf->SetXY(24, $pdf->GetY() + 8);
        foreach ($bullets as $bullet) {
            $pdf->SetX(24);
            $pdf->MultiCell(250, 12, ($i === 0 ? '' : '•  ') . $bullet, 0, 'L');
        }
        $pdf->SetFont('freeserif', '', 11);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->SetXY(20, 192);
        $pdf->Cell(257, 8, 'Hikmah Online · Foundations of Tajweed · ' . $deck, 0, 0, 'L');
    }
    return $pdf->Output('', 'S');
};

// Course caches are rebuilt inside the transaction; clear them whether it commits or rolls back.
core_shutdown_manager::register_function(static fn() => purge_caches(['courses' => [$course->id], 'muc' => true]));
$transaction = $DB->start_delegated_transaction();
try {
    // ---- Course, enrolments and sections (as the instructor) ------------------------------------------
    $as($teacher);
    update_course((object) ['id' => $course->id, 'summary' => $content['summary'], 'summaryformat' => FORMAT_HTML,
        'startdate' => $at('2026-11-01', '00:00'), 'enddate' => $at('2026-11-29', '23:59'), 'enablecompletion' => 1]);
    $course = get_course($course->id);
    // Students enrolled in October, before the first class.
    $DB->execute("UPDATE {user_enrolments} SET timestart = ? WHERE enrolid IN (SELECT id FROM {enrol} WHERE courseid = ?)",
        [$at('2026-10-25', '10:00'), $course->id]);

    $modinfo = get_fast_modinfo($course);
    $keep = [];
    foreach ($modinfo->get_cms() as $cm) {
        if ((int) $cm->sectionnum === 0 || $cm->modname === 'coursecertificate') {
            $keep[$cm->modname] = $cm;
            continue;
        }
        course_delete_module($cm->id);
    }
    course_create_sections_if_missing($course, range(0, 9));
    $schedule = '';
    foreach ($classes as $n => $class) {
        $start = $at($class['date']);
        $schedule .= "<tr><td>$n</td><td>" . userdate($start, '%a %d %b', 'Asia/Karachi', false) . "</td><td>{$class['title']}</td></tr>";
        // The class time is on the class's live class ("Starts: … · Duration: …"), not repeated here.
        $summary = '<p>In this class you will:</p><ul><li>'
            . implode('</li><li>', $class['objectives']) . '</li></ul>';
        $sectiondata = ['name' => "Class $n · {$class['title']}", 'summary' => $summary, 'summaryformat' => FORMAT_HTML];
        if ($start > $now) {
            // Notes for upcoming classes open the day before the class.
            $sectiondata['availability'] = json_encode(['op' => '&', 'showc' => [true],
                'c' => [['type' => 'date', 'd' => '>=', 't' => $at($class['date'], '00:00') - DAYSECS]]]);
        }
        course_update_section($course, $DB->get_record('course_sections', ['course' => $course->id, 'section' => $n]),
            $sectiondata);
    }
    course_update_section($course, $DB->get_record('course_sections', ['course' => $course->id, 'section' => 9]),
        ['name' => 'Final assessment and certificate', 'summaryformat' => FORMAT_HTML,
            'summary' => '<p>The final quiz, your recitation of Surah Al-Ikhlas and a short course survey. The certificate '
                . 'unlocks when you have completed the course and attended at least 75% of the classes.</p>']);
    if (isset($keep['coursecertificate'])) {
        moveto_module($keep['coursecertificate'], $DB->get_record('course_sections', ['course' => $course->id, 'section' => 9]));
    }

    // ---- General section: handbook, live classroom, Q&A, glossary ---------------------------------------
    $manual = ['completion' => COMPLETION_TRACKING_MANUAL];
    $handbook = $add('page', 0, ['name' => 'Course handbook and schedule', 'intro' => 'How the course works, the class schedule and assessment.',
        'content' => str_replace('{{SCHEDULE}}', $schedule, $content['handbook']), 'contentformat' => FORMAT_HTML] + $manual);
    if (isset($keep['bigbluebuttonbn'])) {
        $DB->set_field('bigbluebuttonbn', 'intro', '<p>All classes are held here on Saturdays and Sundays, 6:00 to 7:00 PM '
            . '(Pakistan time). Recordings appear below a few hours after each class.</p>', ['id' => $keep['bigbluebuttonbn']->instance]);
    }
    $qa = $add('forum', 0, ['name' => 'Questions and answers', 'type' => 'general',
        'intro' => 'Ask about anything from the classes. The teacher or assistant replies within a day.']);
    $glossary = $add('glossary', 0, ['name' => 'Tajweed terms', 'intro' => 'The terms used in this course, in plain words.',
        'displayformat' => 'dictionary', 'showalphabet' => 1] + $manual);
    $glossarygen = $gen->get_plugin_generator('mod_glossary');
    foreach ($content['glossary'] as $concept => $definition) {
        $glossarygen->create_content($glossary, ['concept' => $concept, 'definition' => "<p>$definition</p>",
            'userid' => $teacher->id]);
    }

    // ---- Question bank -----------------------------------------------------------------------------------
    // Saved through each question type's own save_question(), as the question editing form does (the
    // test question generator needs PHPUnit, which a production image does not have).
    $bank = \core_question\local\bank\question_bank_helper::get_default_open_instance_system_type($course, true);
    $category = question_get_default_category(context_module::instance($bank->id)->id, true);
    $html = static fn(string $t): array => ['text' => $t, 'format' => FORMAT_HTML];
    $combined = ['correctfeedback' => $html('Well done.'), 'partiallycorrectfeedback' => $html('Partly right.'),
        'incorrectfeedback' => $html('Not quite: look at the class notes.'), 'shownumcorrect' => 1];
    $saveq = static function (string $qtype, string $name, string $text, array $extra) use ($category, $html): stdClass {
        $form = (object) ($extra + ['category' => $category->id . ',' . $category->contextid, 'name' => $name,
            'questiontext' => $html($text), 'generalfeedback' => $html(''), 'defaultmark' => 1, 'penalty' => 0.3333333,
            'idnumber' => null, 'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY]);
        return question_bank::get_qtype($qtype)->save_question((object) ['qtype' => $qtype, 'category' => $category->id], $form);
    };
    $truefalse = static fn(string $name, string $text, bool $true) => $saveq('truefalse', $name, $text, ['correctanswer' => (int) $true,
        'feedbacktrue' => $html($true ? 'Correct.' : 'It is false.'), 'feedbackfalse' => $html($true ? 'It is true.' : 'Correct.'),
        'penalty' => 1, 'showstandardinstruction' => 0]);
    // The first option is the right one; Moodle shuffles them for each student.
    $choice = static fn(string $name, string $text, array $options) => $saveq('multichoice', $name, $text, $combined + [
        'single' => 1, 'shuffleanswers' => 1, 'answernumbering' => 'abc', 'showstandardinstruction' => 0,
        'answer' => array_map($html, $options),
        'fraction' => array_map(static fn(int $i): string => $i === 0 ? '1.0' : '0.0', array_keys($options)),
        'feedback' => array_map(static fn(int $i): array => $html($i === 0 ? 'Correct.' : 'Not quite: look at the class notes.'),
            array_keys($options))]);
    $short = static fn(string $name, string $text, string $answer) => $saveq('shortanswer', $name, $text, ['usecase' => 0,
        'answer' => [$answer, '*'], 'fraction' => ['1.0', '0.0'], 'feedback' => [$html('Correct.'), $html("The answer is $answer.")]]);
    $match = static fn(string $name, string $text, array $pairs) => $saveq('match', $name, $text, $combined + [
        'shuffleanswers' => 1, 'subquestions' => array_map($html, array_keys($pairs)), 'subanswers' => array_values($pairs)]);

    $quiz = static function (int $section, string $name, string $intro, int $open, int $close, array $questions) use ($add) {
        $quiz = $add('quiz', $section, ['name' => $name, 'intro' => $intro, 'timeopen' => $open, 'timeclose' => $close,
            'grade' => 10, 'attempts' => 2, 'grademethod' => QUIZ_GRADEHIGHEST, 'questionsperpage' => 2,
            'preferredbehaviour' => 'deferredfeedback', 'gradepass' => 6,
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionusegrade' => 1]);
        foreach ($questions as $question) {
            quiz_add_quiz_question($question->id, $quiz);
        }
        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        return $quiz;
    };
    $assignment = static function (int $section, string $name, string $intro, int $open, int $due) use ($add) {
        // Opens after its class; late work is accepted for three days, then the assignment locks.
        return $add('assign', $section, ['name' => $name, 'intro' => $intro, 'introformat' => FORMAT_HTML,
            'allowsubmissionsfromdate' => $open, 'duedate' => $due, 'cutoffdate' => $due + 3 * DAYSECS, 'gradingduedate' => $due + 3 * DAYSECS,
            'grade' => 100, 'submissiondrafts' => 0, 'requiresubmissionstatement' => 0, 'sendstudentnotifications' => 1,
            'assignsubmission_onlinetext_enabled' => 1, 'assignsubmission_file_enabled' => 1,
            'assignsubmission_file_maxfiles' => 2, 'assignsubmission_file_maxsizebytes' => 20 * 1024 * 1024,
            'assignfeedback_comments_enabled' => 1, 'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionsubmit' => 1]);
    };

    // ---- The eight classes -------------------------------------------------------------------------------
    $made = [];
    foreach ($classes as $n => $class) {
        $start = $at($class['date']);
        $made[$n]['notes'] = $add('page', $n, ['name' => "Lesson notes: {$class['title']}",
            'intro' => 'Read after the class and practise the examples aloud.', 'content' => $class['notes'],
            'contentformat' => FORMAT_HTML] + $manual);
        $made[$n]['slides'] = $add('resource', $n, ['name' => "Slides: Class $n", 'intro' => 'The slides used in the live class (PDF).',
            'files' => $draft(["tajweed-class-$n-slides.pdf" => $slides("Class $n", $class['slides'])])] + $manual);
    }
    // Class 1: the handbook book, listening and a poll.
    $book = $add('book', 1, ['name' => 'Reading: the etiquette of recitation', 'intro' => 'A short reading in three chapters.'] + $manual);
    $bookgen = $gen->get_plugin_generator('mod_book');
    foreach ([
        'Before you recite' => '<p>Make wudu, sit facing the qiblah if you can, and begin with the ta\'awwudh. Choose a quiet time '
            . 'and a pace you can keep with care.</p>',
        'While you recite' => '<p>Recite slowly enough to give each letter its makhraj. Reflect on what you recite and pause at the '
            . 'ends of verses. Beautify your voice without exaggerating.</p>',
        'Practising every day' => '<p>Ten minutes every day is better than an hour once a week. Record yourself, listen back and '
            . 'compare with a qualified reciter.</p>',
    ] as $title => $text) {
        $bookgen->create_chapter(['bookid' => $book->id, 'title' => $title, 'content' => $text]);
    }
    $add('url', 1, ['name' => 'Listen: Surah Al-Fatiha', 'externalurl' => 'https://quran.com/1',
        'intro' => 'Listen to a recitation of Al-Fatiha and follow the text.', 'display' => 0] + $manual);
    $poll = $add('choice', 1, ['name' => 'Poll: a time for the revision session', 'intro' => 'Which time suits you for an optional revision session?',
        'option' => ['Saturday 11:00 AM', 'Sunday 4:00 PM', 'Wednesday 8:00 PM'], 'showresults' => 3]);
    // Class 2: recitation assignment and a handout folder.
    $a1 = $assignment(2, 'Assignment 1: recite the throat letters',
        '<p>Record yourself saying each throat letter with sukun after a hamzah (' . "أَءْ أَهْ أَعْ أَحْ أَغْ أَخْ" . ') and upload '
        . 'the recording, or describe in the text box any letter you found difficult.</p>', $at('2026-11-07', '19:00'), $at('2026-11-10', '23:59'));
    $add('folder', 2, ['name' => 'Handouts: articulation charts', 'intro' => 'Printable charts for the five areas of articulation.',
        'files' => $draft(['makharij-overview.pdf' => $slides('Handout', [['The five areas of articulation',
            ['Al-jawf: ا و ي (madd letters)', 'Al-halq: ء هـ ع ح غ خ', 'Al-lisan: 18 letters', 'Ash-shafatan: ف ب م و',
                'Al-khayshum: ghunnah']]]),
            'throat-letters.pdf' => $slides('Handout', [['The throat letters', ['Deepest: ء هـ', 'Middle: ع ح', 'Closest to the mouth: غ خ']]])])]
        + $manual);
    // Class 3: quiz 1.
    $quiz1 = $quiz(3, 'Quiz 1: makharij check', 'Ten minutes, two attempts, your highest mark counts.',
        $at('2026-11-08', '19:00'), $at('2026-11-13', '23:59'), [
            $choice('Q1 throat', 'Which letters come from the deepest part of the throat?', ['ء and هـ', 'ع and ح', 'غ and خ', 'ق and ك']),
            $choice('Q1 middle', 'Where is ع pronounced?', ['The middle of the throat', 'The deepest part of the throat',
                'The back of the tongue', 'The lips']),
            $truefalse('Q1 qaf', 'ق is pronounced further back in the mouth than ك.', true),
            $truefalse('Q1 lips', 'ف is pronounced with both lips closed.', false),
            $short('Q1 area', 'What is the Arabic name of the nasal passage, the source of ghunnah? (one word)', 'khayshum'),
            $match('Q1 match', 'Match each part of the throat with its letters.', ['Deepest part' => 'ء هـ',
                'Middle' => 'ع ح', 'Closest to the mouth' => 'غ خ', 'Not a throat letter' => 'ق ك']),
        ]);
    // Class 4: practice sheets.
    $add('folder', 4, ['name' => 'Practice sheets: lips and madd letters', 'intro' => 'Two sheets to practise at home.',
        'files' => $draft(['lip-letters.pdf' => $slides('Practice', [['Lip letters', ['ف: lower lip and upper teeth',
            'ب م: lips closed', 'و: lips rounded']]]),
            'madd-letters.pdf' => $slides('Practice', [['Madd letters', ['قَالَ', 'يَقُولُ', 'قِيلَ']]])])] + $manual);
    // Class 5: worksheet assignment.
    $a2 = $assignment(5, 'Assignment 2: noon sakinah worksheet',
        '<p>For each phrase, name the rule of noon sakinah or tanween and explain why in one line:</p><ol>'
        . '<li><span lang="ar" dir="rtl">مَنْ آمَنَ</span></li><li><span lang="ar" dir="rtl">فَمَن يَعْمَلْ</span></li>'
        . '<li><span lang="ar" dir="rtl">مِن لَّدُنْهُ</span></li><li><span lang="ar" dir="rtl">مِنْ عِلْمٍ</span></li></ol>',
        $at('2026-11-15', '19:00'), $at('2026-11-20', '23:59'));
    // Class 6: quiz 2.
    $quiz2 = $quiz(6, 'Quiz 2: noon and meem sakinah', 'Opens after class 6. Two attempts, your highest mark counts.',
        $at('2026-11-21', '19:00'), $at('2026-11-26', '23:59'), [
            $choice('Q2 izhar', 'Which rule applies to noon sakinah before a throat letter?', ['Izhar', 'Idgham', 'Iqlab', 'Ikhfa']),
            $choice('Q2 iqlab', 'Before which letter does iqlab happen?', ['ب', 'م', 'ل', 'ق']),
            $truefalse('Q2 ghunnah', 'Idgham before ل and ر is without ghunnah.', true),
            $short('Q2 meem', 'Meem sakinah before ب: what is the rule called? (two words)', 'ikhfa shafawi'),
        ]);
    // Class 7: listening.
    $add('url', 7, ['name' => 'Listen: Surah Al-Falaq', 'externalurl' => 'https://quran.com/113',
        'intro' => 'Listen for the qalqalah when the reciter stops at the end of each verse.', 'display' => 0] + $manual);
    // Final section.
    $final = $quiz(9, 'Final quiz', 'Covers the whole course. One attempt.', $at('2026-11-28', '19:00'), $at('2026-11-30', '23:59'), [
        $choice('F madd', 'How many counts is natural madd?', ['Two', 'Four', 'Six', 'One']),
        $choice('F qalqalah', 'Which group are all qalqalah letters?', ['ق ط ب ج د', 'ء هـ ع ح', 'ي ر م ل', 'ف ب م و']),
        $truefalse('F lazim', 'Madd lazim is held for six counts.', true),
        $truefalse('F hadr', 'Hadr means reciting quickly while dropping some rules.', false),
    ]);
    $DB->set_field('quiz', 'attempts', 1, ['id' => $final->id]);
    $a3 = $assignment(9, 'Final recitation: Surah Al-Ikhlas',
        '<p>Record your recitation of Surah Al-Ikhlas, applying every rule from the course, and upload it here.</p>',
        $at('2026-11-28', '19:00'), $at('2026-11-30', '23:59'));
    $survey = $add('feedback', 9, ['name' => 'Course survey', 'intro' => 'Two minutes: tell us how the course went.',
        'anonymous' => 1, 'timeopen' => $at('2026-11-28', '19:00')]);
    $fbgen = $gen->get_plugin_generator('mod_feedback');
    $fbgen->create_item_multichoice($survey, ['name' => 'Overall', 'label' => 'overall', 'required' => 1,
        'presentation' => 'r>>>>>Excellent|Good|Fair|Needs improvement']);
    $fbgen->create_item_multichoice($survey, ['name' => 'Pace of the live classes', 'label' => 'pace',
        'presentation' => 'r>>>>>Too slow|About right|Too fast']);
    $fbgen->create_item_textarea($survey, ['name' => 'What would you improve?', 'label' => 'improve']);

    // ---- Announcements (instructor) -------------------------------------------------------------------
    $forumgen = $gen->get_plugin_generator('mod_forum');
    $news = $DB->get_record('forum', ['course' => $course->id, 'type' => 'news'], '*', MUST_EXIST);
    $post = static function (stdClass $forum, stdClass $who, string $subject, string $message, int $time) use ($forumgen, $course) {
        return $forumgen->create_discussion(['course' => $course->id, 'forum' => $forum->id, 'userid' => $who->id,
            'name' => $subject, 'message' => "<p>$message</p>", 'timemodified' => $time, 'mailed' => 1]);
    };
    $reply = static function (stdClass $discussion, stdClass $who, string $message, int $time) use ($forumgen) {
        $forumgen->create_post(['discussion' => $discussion->id, 'parent' => $discussion->firstpost, 'userid' => $who->id,
            'subject' => 'Re: ' . $discussion->name, 'message' => "<p>$message</p>", 'created' => $time, 'modified' => $time,
            'mailed' => 1]);
    };
    $post($news, $teacher, 'Welcome to Foundations of Tajweed',
        'Assalamu alaikum and welcome! Our first live class is this Sunday, 1 November, at 6:00 PM. Please read the course '
        . 'handbook beforehand and join from the Live classroom a few minutes early to test your microphone.', $at('2026-10-30', '10:00'));
    $post($news, $teacher, 'Assignment 1 is open',
        'Assignment 1 asks you to record the throat letters. It is due on Tuesday 10 November. A phone recording is fine.',
        $at('2026-11-07', '19:30'));
    $post($news, $teacher, 'Quiz 1 results and a tip',
        'Well done on Quiz 1: the class average was 8.6 out of 10. Most mistakes were between ع and ح, so we will revise '
        . 'them at the start of Saturday\'s class.', $at('2026-11-14', '09:00'));
    $post($news, $teacher, 'Class 6 this Saturday and Quiz 2',
        'This Saturday we cover iqlab, ikhfa and meem sakinah. Quiz 2 opens straight after the class. The notes for class 6 '
        . 'open on Friday.', $at('2026-11-19', '18:30'));

    // ---- Questions and answers (students ask, assistant and instructor reply) ----------------------------
    $as($aisha);
    $d = $post($qa, $aisha, 'ح or هـ?', 'I keep mixing up ح and هـ when I recite quickly. Is there a trick to tell them apart?',
        $at('2026-11-09', '21:10'));
    $as($assistant);
    $reply($d, $assistant, 'Good question, Aisha. هـ comes from the deepest part of the throat and is light and breathy. ح comes '
        . 'from the middle with a slight friction, like breathing on glass to clean it. Practise أَهْ then أَحْ slowly.', $at('2026-11-10', '09:30'));
    $as($zainab);
    $d = $post($qa, $zainab, 'Missing Sunday\'s class', 'I can\'t join on Sunday 15 November. Will the class be recorded?',
        $at('2026-11-12', '16:00'));
    $as($assistant);
    $reply($d, $assistant, 'Yes, every class is recorded. The recording appears in the Live classroom a few hours after the class.',
        $at('2026-11-12', '18:05'));
    $as($yusuf);
    $d = $post($qa, $yusuf, 'Ghunnah in idgham', 'Is the ghunnah in idgham with ghunnah also two counts?', $at('2026-11-16', '20:40'));
    $as($teacher);
    $reply($d, $teacher, 'Yes, two counts, the same as noon and meem with shaddah. We will practise it again in class 7.',
        $at('2026-11-17', '08:15'));

    // ---- Attendance: eight sessions, five taken by the assistant ----------------------------------------
    $attendance = $DB->get_record('attendance', ['course' => $course->id], '*', MUST_EXIST);
    $attcm = get_coursemodule_from_instance('attendance', $attendance->id, $course->id, false, MUST_EXIST);
    $structure = new mod_attendance_structure($attendance, $attcm, $course, context_module::instance($attcm->id));
    foreach ($DB->get_records('local_hikmahatt_link', ['courseid' => $course->id]) as $link) {
        \local_hikmahattendance\manager::delete_link((int) $link->id, (int) $teacher->id);
    }
    $oldsessions = $DB->get_records('attendance_sessions', ['attendanceid' => $attendance->id]);
    $bbb = $keep['bigbluebuttonbn'] ?? null;
    if ($bbb) {
        foreach ($oldsessions as $old) {
            // Simulated evidence from the first demo build, around the old sessions.
            $DB->delete_records_select('bigbluebuttonbn_logs', "bigbluebuttonbnid = ? AND log IN ('Join', 'Summary', 'Callback')
                AND timecreated BETWEEN ? AND ?", [$bbb->instance, $old->sessdate - 900, $old->sessdate + $old->duration + 900]);
        }
    }
    if ($oldsessions) {
        $structure->delete_sessions(array_keys($oldsessions));
    }
    $statuses = $DB->get_records_menu('attendance_statuses', ['attendanceid' => $attendance->id, 'deleted' => 0], '', 'acronym, id');
    // P present, L late, A absent: per student, per held class.
    $register = ['demo.student1' => 'PPPLP', 'demo.student4' => 'PAPPL', 'demo.student5' => 'PPPPP', 'demo.student6' => 'PAAPA'];
    $as($assistant);
    $sessionids = [];
    foreach ($classes as $n => $class) {
        $start = $at($class['date']);
        $sessionids[$n] = $structure->add_session((object) ['sessdate' => $start, 'duration' => HOURSECS, 'groupid' => 0,
            'description' => "Class $n: {$class['title']}", 'descriptionformat' => FORMAT_HTML, 'descriptionitemid' => 0,
            'timemodified' => $start - 3 * DAYSECS, 'statusset' => 0, 'calendarevent' => 1, 'absenteereport' => 1]);
        if (!isset($held[$n])) {
            continue;
        }
        foreach ($register as $username => $marks) {
            $DB->insert_record('attendance_log', (object) ['sessionid' => $sessionids[$n], 'studentid' => $students[$username]->id,
                'statusid' => $statuses[$marks[$n - 1]], 'statusset' => implode(',', $statuses), 'timetaken' => $start + 2 * HOURSECS,
                'takenby' => $assistant->id, 'remarks' => $marks[$n - 1] === 'L' ? 'Joined 20 minutes late' : '']);
        }
        $DB->update_record('attendance_sessions', (object) ['id' => $sessionids[$n], 'lasttaken' => $start + 2 * HOURSECS,
            'lasttakenby' => $assistant->id]);

        // What BigBlueButton reported after the class, for the instructor's attendance review.
        if ($bbb) {
            $end = $start + HOURSECS;
            $link = \local_hikmahattendance\manager::save_link((object) ['courseid' => $course->id, 'bbbid' => $bbb->instance,
                'attendanceid' => $attendance->id, 'sessionid' => $sessionids[$n], 'windowstart' => $start, 'windowend' => $end,
                'threshold' => null, 'cancelled' => 0], (int) $teacher->id);
            $bbbrecord = $DB->get_record('bigbluebuttonbn', ['id' => $bbb->instance], '*', MUST_EXIST);
            $log = static function (string $type, int $userid, array $meta, int $time) use ($DB, $course, $bbbrecord): void {
                $DB->insert_record('bigbluebuttonbn_logs', (object) ['courseid' => $course->id, 'bigbluebuttonbnid' => $bbbrecord->id,
                    'userid' => $userid, 'timecreated' => $time, 'log' => $type, 'meta' => json_encode($meta),
                    'meetingid' => $bbbrecord->meetingid . '-' . $course->id . '-' . $bbbrecord->id]);
            };
            $internal = sha1("tajweed-class-$n") . '-' . $start . '000';
            $log('Callback', 0, ['internalmeetingid' => $internal, 'callback' => 'meeting_events',
                'data' => ['duration' => 3700, 'demo' => true]], $end + 180);
            $iso = static fn(int $t): string => gmdate('Y-m-d\TH:i:s\Z', $t);
            $present = ['demo.teacher' => [$start - 300, $end]];
            foreach ($register as $username => $marks) {
                if ($marks[$n - 1] !== 'A') {
                    $present[$username] = [$marks[$n - 1] === 'L' ? $start + 1200 : $start - 60, $end];
                }
            }
            foreach ($present as $username => [$in, $out]) {
                $who = $username === 'demo.teacher' ? $teacher : $students[$username];
                $log('Join', (int) $who->id, [], $in);
                $log('Summary', (int) $who->id, ['recordid' => $internal, 'data' => ['ext_user_id' => (string) $who->id,
                    'name' => fullname($who), 'moderator' => $username === 'demo.teacher', 'joins' => [$iso($in)],
                    'leaves' => [$iso($out)], 'duration' => $out - $in]], $end + 180);
            }
            \local_hikmahattendance\manager::process_link((int) $link->id);
        }
    }
    $structure->update_users_grade(array_map(static fn($s) => $s->id, array_values($students)));

    // ---- Assignment work: students submit, instructor and assistant grade -------------------------------
    $submit = static function (stdClass $assigninstance, stdClass $student, string $text, int $time) use ($as, $DB): void {
        $as($student);
        $cm = get_coursemodule_from_instance('assign', $assigninstance->id, 0, false, MUST_EXIST);
        $assign = new assign(context_module::instance($cm->id), $cm, null);
        $notices = [];
        $assign->save_submission((object) ['userid' => $student->id, 'onlinetext_editor' => ['text' => "<p>$text</p>",
            'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()]], $notices);
        $DB->execute('UPDATE {assign_submission} SET timecreated = ?, timemodified = ?, timestarted = ? WHERE assignment = ? AND userid = ?',
            [$time, $time, $time, $assigninstance->id, $student->id]);
    };
    $grade = static function (stdClass $assigninstance, stdClass $grader, stdClass $student, int $mark, string $feedback, int $time)
            use ($as, $DB): void {
        $as($grader);
        $cm = get_coursemodule_from_instance('assign', $assigninstance->id, 0, false, MUST_EXIST);
        $assign = new assign(context_module::instance($cm->id), $cm, null);
        $assign->save_grade($student->id, (object) ['grade' => $mark, 'attemptnumber' => -1, 'addattempt' => 0,
            'workflowstate' => '', 'applytoall' => 0, 'sendstudentnotifications' => false,
            'assignfeedbackcomments_editor' => ['text' => "<p>$feedback</p>", 'format' => FORMAT_HTML]]);
        $DB->execute('UPDATE {assign_grades} SET timecreated = ?, timemodified = ? WHERE assignment = ? AND userid = ?',
            [$time, $time, $assigninstance->id, $student->id]);
    };
    $submit($a1, $aisha, 'Recording uploaded by voice note. I found غ and خ hardest to keep apart.', $at('2026-11-09', '20:15'));
    $submit($a1, $zainab, 'All six letters recorded. ع was difficult at first.', $at('2026-11-08', '21:40'));
    $submit($a1, $yusuf, 'Recorded on my phone. Not sure my ح is right.', $at('2026-11-10', '23:20'));
    $grade($a1, $teacher, $aisha, 88, 'Very good, Aisha. Your ع and ح are clear. Keep the خ a little higher in the throat: it '
        . 'should sound rougher than the غ.', $at('2026-11-12', '10:00'));
    $grade($a1, $teacher, $zainab, 92, 'Excellent work, Zainab, all six letters from the right place. Well done.', $at('2026-11-12', '10:20'));
    $grade($a1, $teacher, $yusuf, 74, 'A good start, Yusuf. Your ح is coming from the deepest part of the throat and sounds like هـ: '
        . 'bring it up to the middle of the throat. Listen to the class 2 recording again.', $at('2026-11-12', '10:45'));
    $submit($a2, $aisha, '1. Izhar: noon before the throat letter alif (hamzah). 2. Idgham with ghunnah: noon before ya. '
        . '3. Idgham without ghunnah: noon before lam. 4. Izhar: noon before ain.', $at('2026-11-18', '19:30'));
    $submit($a2, $zainab, '1. Izhar. 2. Idgham with ghunnah. 3. Idgham without ghunnah. 4. Izhar.', $at('2026-11-19', '13:10'));
    $grade($a2, $assistant, $aisha, 95, 'All four correct with clear reasons. Masha\'Allah, Aisha.', $at('2026-11-19', '17:45'));

    // ---- Quiz attempts -------------------------------------------------------------------------------
    $quizgen = $gen->get_plugin_generator('mod_quiz');
    $q1answers = [
        'demo.student1' => [1 => 'ء and هـ', 2 => 'The middle of the throat', 3 => 'True', 4 => 'False', 5 => 'khayshum',
            6 => ['Deepest part' => 'ء هـ', 'Middle' => 'ع ح', 'Closest to the mouth' => 'ق ك', 'Not a throat letter' => 'غ خ']],
        'demo.student5' => [1 => 'ء and هـ', 2 => 'The middle of the throat', 3 => 'True', 4 => 'False', 5 => 'khayshum',
            6 => ['Deepest part' => 'ء هـ', 'Middle' => 'ع ح', 'Closest to the mouth' => 'غ خ', 'Not a throat letter' => 'ق ك']],
        'demo.student4' => [1 => 'ع and ح', 2 => 'The middle of the throat', 3 => 'True', 4 => 'True', 5 => 'khayshum',
            6 => ['Deepest part' => 'ء هـ', 'Middle' => 'ع ح', 'Closest to the mouth' => 'غ خ', 'Not a throat letter' => 'ق ك']],
    ];
    $whens = ['demo.student1' => '2026-11-10', 'demo.student5' => '2026-11-09', 'demo.student4' => '2026-11-12'];
    foreach ($q1answers as $username => $responses) {
        $as($students[$username]);
        $attempt = $quizgen->create_attempt($quiz1->id, $students[$username]->id);
        $finished = $at($whens[$username], '20:30');
        // As the quiz page posts them: each question's response fields, then submit and grade.
        $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
        $postdata = ['slots' => implode(',', array_keys($responses))];
        foreach ($responses as $slot => $response) {
            $qa = $attemptobj->get_question_attempt($slot);
            $response = match ($qa->get_question()->get_type_name()) {
                'truefalse' => ['answer' => $response === 'True' ? 1 : 0],
                'multichoice', 'shortanswer' => ['answer' => $response],
                default => $response, // Matching: stem => choice.
            };
            foreach ($qa->get_question()->prepare_simulated_post_data($response) as $field => $value) {
                $postdata[$qa->get_qt_field_name($field)] = $value;
            }
            $postdata[$qa->get_control_field_name('sequencecheck')] = (string) $qa->get_sequence_check_count();
        }
        $attemptobj->process_submitted_actions($finished - 60, false, $postdata);
        $attemptobj->process_submit($finished, false);
        $attemptobj->process_grade_submission($finished);
        $DB->execute('UPDATE {quiz_attempts} SET timestart = ?, timemodified = ? WHERE id = ?', [$finished - 540, $finished, $attempt->id]);
    }

    // ---- Poll answers and progress ---------------------------------------------------------------------
    $pollrecord = choice_get_choice($poll->id);
    $pollcm = get_coursemodule_from_instance('choice', $poll->id, $course->id, false, MUST_EXIST);
    $optionids = array_keys($pollrecord->option);
    foreach (['demo.student1' => 1, 'demo.student4' => 0, 'demo.student5' => 1, 'demo.student6' => 2] as $username => $pick) {
        $as($students[$username]);
        choice_user_submit_response($optionids[$pick], $pollrecord, $students[$username]->id, $course, $pollcm);
    }
    rebuild_course_cache($course->id, true);
    $completion = new completion_info(get_course($course->id));
    $modinfo = get_fast_modinfo($course->id);
    // How far each student has worked through the materials of the held classes.
    $reach = ['demo.student1' => 5, 'demo.student4' => 3, 'demo.student5' => 5, 'demo.student6' => 1];
    foreach ($modinfo->get_cms() as $cm) {
        if ($cm->completion != COMPLETION_TRACKING_MANUAL) {
            continue;
        }
        foreach ($reach as $username => $upto) {
            if ($cm->sectionnum <= $upto && !($username === 'demo.student1' && $cm->modname === 'folder' && (int) $cm->sectionnum === 4)) {
                $as($students[$username]);
                $completion->update_state($cm, COMPLETION_COMPLETE, $students[$username]->id);
            }
        }
    }
    \core\session\manager::set_user(get_admin());
    require_once(__DIR__ . '/tajweed-gradebook.php');
    hk_tajweed_gradebook((int) $course->id);
    require_once(__DIR__ . '/tajweed-liveclasses.php');
    hk_tajweed_liveclasses(get_course($course->id));
    \core\session\manager::set_user(get_admin());

    foreach ($students as $username => $student) {
        $q1 = $DB->get_field('quiz_grades', 'grade', ['quiz' => $quiz1->id, 'userid' => $student->id]);
        $g1 = $DB->get_field('assign_grades', 'grade', ['assignment' => $a1->id, 'userid' => $student->id]);
        $taken = $DB->count_records_sql('SELECT COUNT(1) FROM {attendance_log} l JOIN {attendance_sessions} s ON s.id = l.sessionid
            WHERE s.attendanceid = ? AND l.studentid = ?', [$attendance->id, $student->id]);
        $done = $DB->count_records('course_modules_completion', ['userid' => $student->id, 'completionstate' => COMPLETION_COMPLETE]);
        cli_writeln(sprintf('  %-14s quiz1 %-5s assign1 %-6s attendance marks %d  completed items %d', $username,
            $q1 === false ? '-' : round($q1, 1), $g1 === false ? '-' : round($g1), $taken, $done));
    }
    if ($options['dry-run']) {
        throw new moodle_exception('dryrun', 'error', '', null, 'Dry run: everything built, now rolled back.');
    }
    $transaction->allow_commit();
    cli_writeln('Foundations of Tajweed built: 8 classes (' . count($held) . ' held), 3 quizzes, 3 assignments, '
        . count($content['glossary']) . ' glossary terms, 4 announcements, 3 Q&A threads.');
} catch (Throwable $e) {
    try {
        $transaction->rollback($e);
    } catch (Throwable $rolledback) {
        cli_writeln(($options['dry-run'] && $rolledback instanceof moodle_exception && $rolledback->errorcode === 'dryrun')
            ? 'Dry run OK: everything was built and then rolled back.'
            : 'FAILED and rolled back: ' . $rolledback->getMessage() . "\n" . $rolledback->getTraceAsString());
        exit($options['dry-run'] && $rolledback instanceof moodle_exception && $rolledback->errorcode === 'dryrun' ? 0 : 1);
    }
}
