<?php
// Content for the demo course "Foundations of Tajweed" (used by tajweed-course.php).
// Original teaching text written for the demo. Qur'anic words and verses are the standard Uthmani
// text of well-known passages; a qualified teacher should still check them before real use.

$ar = static fn(string $text): string => '<span lang="ar" dir="rtl" class="hk-arabic">' . $text . '</span>';
$arblock = static fn(string $text): string =>
    '<p lang="ar" dir="rtl" style="font-size:1.6rem;line-height:2.2;text-align:center">' . $text . '</p>';

return [
    'summary' => '<p>An eight-class introduction to reciting the Qur\'an correctly: where each letter is '
        . 'pronounced (makharij), the rules of noon and meem sakinah, qalqalah, ghunnah and madd. Live classes '
        . 'every Saturday and Sunday, 6:00 to 7:00 PM Pakistan time, with notes, slides, quizzes and recitation '
        . 'feedback from your teacher.</p>',

    'handbook' => '<h3>How this course works</h3>'
        . '<p>Classes are live in the <strong>Live classroom</strong> on Saturdays and Sundays from 6:00 to 7:00 PM '
        . '(Pakistan time). If you miss a class, the recording appears in the Live classroom within a few hours.</p>'
        . '<h4>Schedule</h4><table class="hk-table"><thead><tr><th>Class</th><th>Date</th><th>Topic</th></tr></thead><tbody>'
        . '{{SCHEDULE}}</tbody></table>'
        . '<h4>Each week</h4><ul><li>Join the live class, or watch its recording.</li>'
        . '<li>Read the lesson notes and look through the slides.</li>'
        . '<li>Practise the examples aloud for ten minutes a day.</li>'
        . '<li>Complete the quiz or assignment for that week.</li></ul>'
        . '<h4>Assessment</h4><ul><li>Two short quizzes and a final quiz (40%)</li>'
        . '<li>Two recitation and worksheet assignments (30%)</li><li>Final recitation of Surah Al-Ikhlas (20%)</li>'
        . '<li>Attendance (10%): at least 75% of classes for the certificate</li></ul>'
        . '<h4>Getting help</h4><p>Ask in <strong>Questions and answers</strong>. Your teacher, Ustadh Hamza Malik, '
        . 'or the assistant, Usman Tariq, replies within a day.</p>'
        . '<p class="text-muted small">Demo course: the Qur\'anic text shown here should be checked by a qualified '
        . 'teacher before it is used with real students.</p>',

    'classes' => [
        1 => [
            'date' => '2026-11-01', 'title' => 'Introduction to Tajweed',
            'objectives' => ['Explain what tajweed is and why it matters', 'Name the levels of recitation',
                'Begin recitation correctly with the ta\'awwudh and basmalah'],
            'notes' => '<h3>What is tajweed?</h3>'
                . '<p>Linguistically, <em>tajweed</em> means "to make something excellent". In recitation it means giving '
                . 'every letter its right: its correct point of articulation and its characteristics, and applying the '
                . 'rules that come from how letters meet one another.</p>'
                . '<p>Allah says:</p>' . $arblock('وَرَتِّلِ الْقُرْآنَ تَرْتِيلًا')
                . '<p class="text-center"><em>"…and recite the Qur\'an with measured recitation."</em> (Al-Muzzammil 73:4)</p>'
                . '<h3>Two kinds of mistakes</h3><ul>'
                . '<li><strong>Lahn jali</strong> (clear mistake): changing a letter or a vowel, which can change the meaning. It must be avoided.</li>'
                . '<li><strong>Lahn khafi</strong> (hidden mistake): missing a rule such as ghunnah or madd. It spoils the beauty of the recitation.</li></ul>'
                . '<h3>Levels of recitation</h3><ul>'
                . '<li><strong>Tahqeeq</strong>: very slow and careful, used when learning.</li>'
                . '<li><strong>Tadweer</strong>: a moderate pace.</li><li><strong>Hadr</strong>: a quick pace, keeping every rule.</li></ul>'
                . '<p>Tarteel, measured recitation, describes the quality of all three.</p>'
                . '<h3>Beginning a recitation</h3>' . $arblock('أَعُوذُ بِاللَّهِ مِنَ الشَّيْطَانِ الرَّجِيمِ')
                . $arblock('بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ')
                . '<h3>Practice</h3><p>Recite Surah Al-Fatiha slowly (tahqeeq) three times this week and note any letter you are unsure of.</p>',
            'slides' => [
                ['Foundations of Tajweed', ['Class 1 of 8: Introduction', 'Saturdays and Sundays, 6:00 to 7:00 PM']],
                ['What is tajweed?', ['Giving every letter its right', 'Correct point of articulation (makhraj)',
                    'Correct characteristics (sifat)', 'Rules that come from letters meeting']],
                ['Two kinds of mistakes', ['Lahn jali: changes a letter or vowel, can change meaning',
                    'Lahn khafi: misses a rule, spoils the beauty']],
                ['Levels of recitation', ['Tahqeeq: slow, for learning', 'Tadweer: moderate', 'Hadr: quick, every rule kept']],
                ['This week', ['Recite Al-Fatiha slowly three times', 'Note letters you are unsure of', 'Read the handbook']],
            ],
        ],
        2 => [
            'date' => '2026-11-07', 'title' => 'Makharij (1): the throat letters',
            'objectives' => ['Name the five main areas of articulation', 'Pronounce the six throat letters from the right part of the throat'],
            'notes' => '<h3>Where letters come from</h3><p>Every Arabic letter has a <em>makhraj</em>, a point where it is '
                . 'produced. The scholars group them into five areas: the empty space of the mouth and throat (al-jawf), '
                . 'the throat (al-halq), the tongue (al-lisan), the lips (ash-shafatan) and the nasal passage (al-khayshum).</p>'
                . '<h3>The throat letters</h3><table class="hk-table"><thead><tr><th>Part of the throat</th><th>Letters</th></tr></thead><tbody>'
                . '<tr><td>Deepest part</td><td>' . $ar('ء  هـ') . '</td></tr>'
                . '<tr><td>Middle</td><td>' . $ar('ع  ح') . '</td></tr>'
                . '<tr><td>Closest to the mouth</td><td>' . $ar('غ  خ') . '</td></tr></tbody></table>'
                . '<h3>Common mistakes</h3><ul><li>Saying ' . $ar('ح') . ' like ' . $ar('هـ') . ': the ' . $ar('ح')
                . ' comes from the middle of the throat with a slight friction.</li>'
                . '<li>Saying ' . $ar('ع') . ' like a hamzah: keep the throat tight and the sound continuous.</li></ul>'
                . '<h3>How to test a makhraj</h3><p>Put a sukun on the letter after a hamzah with a fathah, for example '
                . $ar('أَحْ') . ' or ' . $ar('أَعْ') . '. Where the sound stops is the makhraj.</p>',
            'slides' => [
                ['Makharij (1)', ['Class 2 of 8: the throat letters']],
                ['Five areas of articulation', ['Al-jawf: the empty space', 'Al-halq: the throat', 'Al-lisan: the tongue',
                    'Ash-shafatan: the lips', 'Al-khayshum: the nasal passage']],
                ['The throat letters', ['Deepest: hamzah and ha (ء هـ)', 'Middle: ain and haa (ع ح)', 'Closest to the mouth: ghain and khaa (غ خ)']],
                ['Testing a makhraj', ['Hamzah with fathah, then the letter with sukun', 'Example: أَحْ  أَعْ  أَغْ', 'Where the sound stops is the makhraj']],
            ],
        ],
        3 => [
            'date' => '2026-11-08', 'title' => 'Makharij (2): the tongue',
            'objectives' => ['Locate the ten tongue articulation points', 'Distinguish ق from ك and ت from ط'],
            'notes' => '<h3>The tongue</h3><p>The tongue has ten points of articulation and produces eighteen letters. '
                . 'This class covers the most important groups.</p><table class="hk-table"><thead><tr><th>Part of the tongue</th><th>Letters</th></tr></thead><tbody>'
                . '<tr><td>Deepest part, against the soft palate</td><td>' . $ar('ق') . '</td></tr>'
                . '<tr><td>A little forward, against the hard palate</td><td>' . $ar('ك') . '</td></tr>'
                . '<tr><td>Middle of the tongue</td><td>' . $ar('ج  ش  ي') . '</td></tr>'
                . '<tr><td>Side of the tongue</td><td>' . $ar('ض') . '</td></tr>'
                . '<tr><td>Edge and tip</td><td>' . $ar('ل  ن  ر') . '</td></tr>'
                . '<tr><td>Tip with the gum of the upper teeth</td><td>' . $ar('ط  د  ت') . '</td></tr>'
                . '<tr><td>Tip near the lower teeth (whistling letters)</td><td>' . $ar('ص  ز  س') . '</td></tr>'
                . '<tr><td>Tip with the edge of the upper teeth</td><td>' . $ar('ظ  ذ  ث') . '</td></tr></tbody></table>'
                . '<h3>Heavy and light</h3><p>' . $ar('ق') . ' and ' . $ar('ط') . ' are heavy letters (the back of the tongue rises); '
                . $ar('ك') . ' and ' . $ar('ت') . ' are light. Keep them apart: they change the meaning of words.</p>',
            'slides' => [
                ['Makharij (2)', ['Class 3 of 8: the tongue']],
                ['Back of the tongue', ['ق deepest, against the soft palate', 'ك a little forward']],
                ['Middle, side and tip', ['ج ش ي: middle', 'ض: side of the tongue', 'ل ن ر: edge and tip']],
                ['Tip of the tongue', ['ط د ت with the gum', 'ص ز س whistling letters', 'ظ ذ ث with the teeth edge']],
                ['Quiz 1 is open', ['Makharij check, 10 questions', 'Open until Friday 13 November']],
            ],
        ],
        4 => [
            'date' => '2026-11-14', 'title' => 'Makharij (3): lips, nasal passage and the empty space',
            'objectives' => ['Pronounce the lip letters', 'Recognise the madd letters of the empty space', 'Understand where ghunnah comes from'],
            'notes' => '<h3>The lips</h3><ul><li>' . $ar('ف') . ': the inside of the lower lip with the edge of the upper front teeth.</li>'
                . '<li>' . $ar('ب  م  و') . ': both lips; closed for ' . $ar('ب') . ' and ' . $ar('م') . ', rounded and open for ' . $ar('و') . '.</li></ul>'
                . '<h3>The empty space (al-jawf)</h3><p>The three madd letters come from the empty space of the mouth and throat '
                . 'and end with the breath: ' . $ar('ا') . ' after a fathah, ' . $ar('و') . ' with sukun after a dammah and '
                . $ar('ي') . ' with sukun after a kasrah, as in ' . $ar('قَالَ  يَقُولُ  قِيلَ') . '.</p>'
                . '<h3>The nasal passage (al-khayshum)</h3><p>Ghunnah, the nasal sound, comes from here. It is strongest on '
                . $ar('نّ') . ' and ' . $ar('مّ') . ' with shaddah, held for two counts.</p>',
            'slides' => [
                ['Makharij (3)', ['Class 4 of 8: lips, nasal passage, empty space']],
                ['The lips', ['ف: lower lip with upper teeth', 'ب م: both lips closed', 'و: lips rounded']],
                ['The empty space', ['Madd letters: ا  و  ي', 'قَالَ  يَقُولُ  قِيلَ']],
                ['The nasal passage', ['Source of ghunnah', 'نّ and مّ: two counts']],
            ],
        ],
        5 => [
            'date' => '2026-11-15', 'title' => 'Noon sakinah and tanween (1): izhar and idgham',
            'objectives' => ['Identify noon sakinah and tanween', 'Apply izhar before the throat letters', 'Apply idgham with and without ghunnah'],
            'notes' => '<h3>Noon sakinah and tanween</h3><p>Noon sakinah is a noon with sukun (' . $ar('نْ') . '). Tanween is the '
                . 'doubled vowel at the end of a noun, pronounced as a noon sakinah. Four rules depend on the letter that follows.</p>'
                . '<h3>1. Izhar (clear pronunciation)</h3><p>Before the six throat letters ' . $ar('ء هـ ع ح غ خ') . ' the noon is '
                . 'pronounced clearly, without extra ghunnah: ' . $ar('مَنْ آمَنَ') . ', ' . $ar('مِنْ عِلْمٍ') . '.</p>'
                . '<h3>2. Idgham (merging)</h3><p>Before the letters of <em>yarmaloon</em> ' . $ar('ي ر م ل و ن') . ' the noon merges '
                . 'into the next letter.</p><ul><li><strong>With ghunnah</strong> before ' . $ar('ي ن م و') . ': '
                . $ar('فَمَن يَعْمَلْ') . ' (Az-Zalzalah 99:7).</li><li><strong>Without ghunnah</strong> before ' . $ar('ل ر') . ': '
                . $ar('مِن لَّدُنْهُ') . '.</li></ul><p>Exception: within one word, such as ' . $ar('دُنْيَا') . ', there is no idgham.</p>',
            'slides' => [
                ['Noon sakinah and tanween (1)', ['Class 5 of 8: izhar and idgham']],
                ['Four rules', ['Izhar', 'Idgham', 'Iqlab', 'Ikhfa', 'The next letter decides']],
                ['Izhar', ['Before ء هـ ع ح غ خ', 'Clear noon, no extra ghunnah', 'مَنْ آمَنَ']],
                ['Idgham', ['Letters of yarmaloon: ي ر م ل و ن', 'With ghunnah: ي ن م و', 'Without ghunnah: ل ر']],
                ['Assignment 2', ['Noon sakinah worksheet', 'Due Friday 20 November']],
            ],
        ],
        6 => [
            'date' => '2026-11-21', 'title' => 'Noon sakinah (2) and meem sakinah: iqlab, ikhfa and the meem rules',
            'objectives' => ['Apply iqlab before ب', 'Apply ikhfa before the fifteen letters', 'Apply the three rules of meem sakinah'],
            'notes' => '<h3>3. Iqlab (changing)</h3><p>Before ' . $ar('ب') . ' the noon sakinah or tanween changes into a hidden '
                . $ar('م') . ' with ghunnah for two counts: ' . $ar('مِنْ بَعْدِ') . '.</p>'
                . '<h3>4. Ikhfa (hiding)</h3><p>Before the remaining fifteen letters the noon is hidden between izhar and idgham, '
                . 'with ghunnah for two counts: ' . $ar('مِنْ قَبْلِ') . ', ' . $ar('أَنتُمْ') . '.</p>'
                . '<h3>Meem sakinah</h3><ul><li><strong>Ikhfa shafawi</strong> before ' . $ar('ب') . ': '
                . $ar('تَرْمِيهِم بِحِجَارَةٍ') . ' (Al-Fil 105:4).</li>'
                . '<li><strong>Idgham mithlayn</strong> before ' . $ar('م') . ': ' . $ar('لَهُم مَّا') . '.</li>'
                . '<li><strong>Izhar shafawi</strong> before all other letters, taking extra care before ' . $ar('و') . ' and ' . $ar('ف') . '.</li></ul>',
            'slides' => [
                ['Iqlab, ikhfa and meem sakinah', ['Class 6 of 8']],
                ['Iqlab', ['Before ب', 'Noon becomes a hidden meem with ghunnah', 'مِنْ بَعْدِ']],
                ['Ikhfa', ['Before the other 15 letters', 'Hidden noon, two counts of ghunnah', 'مِنْ قَبْلِ']],
                ['Meem sakinah', ['Ikhfa shafawi before ب', 'Idgham mithlayn before م', 'Izhar shafawi before the rest']],
            ],
        ],
        7 => [
            'date' => '2026-11-22', 'title' => 'Qalqalah and ghunnah',
            'objectives' => ['Recognise the five qalqalah letters', 'Apply minor and major qalqalah', 'Hold ghunnah on noon and meem with shaddah'],
            'notes' => '<h3>Qalqalah (echo)</h3><p>Five letters, gathered in the phrase <em>qutbu jad</em> ' . $ar('ق ط ب ج د')
                . ', make a slight echo when they carry a sukun.</p><ul><li><strong>Minor</strong> in the middle of a word: '
                . $ar('يَقْطَعُونَ') . '.</li><li><strong>Major</strong> when stopping at the end of a verse: '
                . $ar('الْفَلَقِ') . ' read as ' . $ar('الْفَلَقْ') . '.</li></ul>'
                . '<h3>Ghunnah</h3><p>Noon and meem with shaddah always carry a full ghunnah of two counts: '
                . $ar('إِنَّ') . ', ' . $ar('ثُمَّ') . '.</p>',
            'slides' => [
                ['Qalqalah and ghunnah', ['Class 7 of 8']],
                ['Qalqalah letters', ['ق ط ب ج د  (qutbu jad)', 'Echo when the letter has sukun']],
                ['Minor and major', ['Minor: middle of a word', 'Major: stopping at a verse end']],
                ['Ghunnah', ['نّ and مّ: two counts', 'إِنَّ   ثُمَّ']],
            ],
        ],
        8 => [
            'date' => '2026-11-28', 'title' => 'Madd (prolongation) and final review',
            'objectives' => ['Hold natural madd for two counts', 'Recognise the main secondary madds', 'Prepare for the final recitation'],
            'notes' => '<h3>Natural madd</h3><p>A madd letter not followed by a hamzah or a sukun is held for two counts: '
                . $ar('قَالَ') . '.</p><h3>Secondary madd</h3><ul>'
                . '<li><strong>Muttasil</strong> (joined): madd letter and hamzah in one word, four or five counts: ' . $ar('جَاءَ') . '.</li>'
                . '<li><strong>Munfasil</strong> (separate): hamzah at the start of the next word, four or five counts: ' . $ar('يَا أَيُّهَا') . '.</li>'
                . '<li><strong>Lazim</strong> (necessary): a permanent sukun or shaddah after the madd letter, six counts: ' . $ar('الضَّالِّينَ') . '.</li>'
                . '<li><strong>\'Arid lis-sukun</strong>: when you stop at a verse end, two, four or six counts: ' . $ar('الرَّحِيمِ') . ' read as ' . $ar('الرَّحِيمْ') . '.</li></ul>'
                . '<h3>Final review</h3><p>For the final recitation, recite Surah Al-Ikhlas applying every rule from this course:</p>'
                . $arblock('قُلْ هُوَ اللَّهُ أَحَدٌ ۝ اللَّهُ الصَّمَدُ ۝ لَمْ يَلِدْ وَلَمْ يُولَدْ ۝ وَلَمْ يَكُن لَّهُ كُفُوًا أَحَدٌ'),
            'slides' => [
                ['Madd and final review', ['Class 8 of 8']],
                ['Natural madd', ['Two counts', 'قَالَ']],
                ['Secondary madd', ['Muttasil: 4 to 5', 'Munfasil: 4 to 5', 'Lazim: 6', '\'Arid lis-sukun: 2, 4 or 6']],
                ['Final assessment', ['Final quiz', 'Recitation of Surah Al-Ikhlas', 'Course survey']],
            ],
        ],
    ],

    'glossary' => [
        'Tajweed' => 'Reciting the Qur\'an by giving every letter its right: its point of articulation, characteristics and rules.',
        'Tarteel' => 'Measured, unhurried recitation with attention to the rules and the meaning.',
        'Makhraj (makharij)' => 'The point in the throat, tongue, lips or nasal passage where a letter is produced.',
        'Sifah (sifat)' => 'A characteristic of a letter, such as heaviness or whispering, that distinguishes it from letters with the same makhraj.',
        'Harakah' => 'A short vowel: fathah, kasrah or dammah.',
        'Sukun' => 'The sign showing a letter has no vowel.',
        'Shaddah' => 'The sign showing a letter is doubled.',
        'Tanween' => 'A doubled vowel at the end of a noun, pronounced as a noon sakinah.',
        'Ghunnah' => 'The nasal sound from the khayshum, held for two counts on noon and meem with shaddah.',
        'Izhar' => 'Pronouncing noon sakinah or tanween clearly, before the six throat letters.',
        'Idgham' => 'Merging noon sakinah or tanween into the next letter (the letters of yarmaloon).',
        'Iqlab' => 'Changing noon sakinah or tanween into a hidden meem before the letter ba.',
        'Ikhfa' => 'Hiding noon sakinah or tanween, with ghunnah, before fifteen letters.',
        'Qalqalah' => 'The echo made by the letters qaf, ta, ba, jim and dal when they carry a sukun.',
        'Madd' => 'Prolonging the sound of a madd letter, for two to six counts.',
        'Lahn jali' => 'A clear mistake that changes a letter or a vowel and can change the meaning.',
        'Lahn khafi' => 'A hidden mistake: missing a rule such as ghunnah or madd.',
        'Waqf' => 'Stopping at the end of a word with a breath before continuing.',
    ],
];
