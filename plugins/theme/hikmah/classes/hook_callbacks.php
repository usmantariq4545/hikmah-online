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

namespace theme_hikmah;

use core\hook\output\before_standard_head_html_generation;
use core\hook\output\before_standard_top_of_body_html_generation;

/**
 * Hook callbacks for the Hikmah theme.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * True when the Hikmah theme is rendering the current page.
     *
     * @return bool
     */
    private static function is_active(): bool {
        global $PAGE;
        // The theme is a magic property of the page, so isset() cannot be used to test it.
        try {
            $theme = $PAGE->theme;
        } catch (\Throwable $e) {
            return false;
        }
        return $theme->name === 'hikmah' || in_array('hikmah', $theme->parents ?? [], true);
    }

    /**
     * Marks the document as script-capable before first paint, so scroll reveals never flash.
     * If the motion module fails to start within three seconds, the mark is removed again and
     * every element shows without animation.
     *
     * @param before_standard_head_html_generation $hook
     */
    public static function before_head(before_standard_head_html_generation $hook): void {
        if (!self::is_active()) {
            return;
        }
        // Scroll reveals start as soon as the page is parsed, without waiting for Moodle's script
        // bundle (which can take seconds on a slow connection). Without IntersectionObserver, or
        // with reduced motion, nothing is ever hidden. The motion module adds the other effects later.
        $hook->add_html(
            "<script>(function(w,d){var h=d.documentElement;" .
            "if(!('IntersectionObserver' in w)||w.matchMedia('(prefers-reduced-motion: reduce)').matches){return;}" .
            "h.classList.add('hk-js');" .
            "d.addEventListener('DOMContentLoaded',function(){" .
            "d.querySelectorAll('[data-hk-stagger]').forEach(function(g){Array.prototype.forEach.call(g.children," .
            "function(c,i){if(!c.hasAttribute('data-hk-reveal')){c.setAttribute('data-hk-reveal','');}" .
            "c.style.setProperty('--hk-d',Math.min(i,8));});});" .
            "var o=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){" .
            "e.target.classList.add('is-in');o.unobserve(e.target);}});},{threshold:0.12,rootMargin:'0px 0px -6% 0px'});" .
            "d.querySelectorAll('[data-hk-reveal],.hk-journey').forEach(function(el){o.observe(el);});" .
            // Pause the rosette animations while they are off screen.
            "var p=new IntersectionObserver(function(es){es.forEach(function(e){" .
            "e.target.classList.toggle('is-paused',!e.isIntersecting);});});" .
            "d.querySelectorAll('.hk-motif,.hk-course').forEach(function(el){p.observe(el);});" .
            "w.hkRevealReady=true;});" .
            "setTimeout(function(){if(!w.hkRevealReady){h.classList.remove('hk-js');}},4000);" .
            "})(window,document);</script>\n" .
            '<meta name="theme-color" content="#1a1a1a">' .
            // The header logo downloads first, ahead of the stylesheet and script bundle.
            '<link rel="preload" as="image" fetchpriority="high" href="'
            . s($hook->renderer->image_url('logo-dark', 'theme_hikmah')->out(false)) . '">'
        );
    }

    /**
     * The course home opens on the learner's current class, one class per page with the class list on the
     * left (owner's decision, 2026-11-20). Edit mode, and any course page link with more than the course
     * id (a section, an action), keep Moodle's full course page.
     *
     * @param \core_course\hook\before_course_viewed $hook
     */
    public static function before_course_viewed(\core_course\hook\before_course_viewed $hook): void {
        global $USER;
        $course = $hook->course;
        if (!self::is_active() || !isloggedin() || isguestuser() || !empty($USER->editing)
                || array_diff(array_keys($_GET + $_POST), ['id']) || !course_format_uses_sections($course->format)) {
            return;
        }
        try {
            $section = \theme_hikmah\local\course_items::current_section($course);
        } catch (\Throwable $e) {
            debugging('Hikmah course landing: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return;
        }
        if ($section) {
            redirect(new \moodle_url('/course/section.php', ['id' => $section->id]));
        }
    }

    /**
     * Course tabs: no Grades tab for staff who are not on the course and cannot see its grades.
     *
     * @param \core\hook\navigation\secondary_extend $hook
     */
    public static function secondary_extend(\core\hook\navigation\secondary_extend $hook): void {
        global $PAGE;
        if (!self::is_active() || empty($PAGE->course->id) || $PAGE->course->id == SITEID) {
            return;
        }
        // Staff who are not on the course (support) only get "Cannot view grades." behind the Grades tab.
        $context = \context_course::instance($PAGE->course->id);
        if (!is_enrolled($context) && !has_capability('moodle/grade:viewall', $context)
                && ($grades = $hook->get_secondaryview()->get('grades'))) {
            $grades->remove();
        }
    }

    /**
     * Role-aware top menu: staff get links to their own tools, and only staff who hold the
     * capability see them (finance reviewers, managers and the administrator see "Payment reviews").
     *
     * @param \core\hook\navigation\primary_extend $hook
     */
    public static function primary_extend(\core\hook\navigation\primary_extend $hook): void {
        if (!self::is_active() || !isloggedin() || isguestuser()) {
            return;
        }
        $system = \context_system::instance();
        $view = $hook->get_primaryview();
        $isreviewer = has_capability('paygw/hikmahtransfer:review', $system);
        // Staff with no courses of their own who cannot create one (finance, support) would only find an empty "My courses".
        if (($isreviewer || has_capability('moodle/course:view', $system)) && !has_capability('moodle/course:create', $system)
                && !enrol_get_my_courses('id', null, 1)
                && ($mycourses = $view->find('mycourses', \navigation_node::TYPE_ROOTNODE))) {
            $mycourses->remove();
        }
        // Support finds learners in the site's participant list.
        if (has_capability('moodle/site:viewparticipants', $system) && !is_siteadmin()) {
            $view->add(get_string('nav_people', 'theme_hikmah'), new \moodle_url('/user/index.php', ['id' => SITEID]),
                \navigation_node::TYPE_CUSTOM, null, 'hkpeople');
        }
        if ($isreviewer) {
            $view->add(
                get_string('nav_paymentreviews', 'theme_hikmah'),
                new \moodle_url('/payment/gateway/hikmahtransfer/review.php'),
                \navigation_node::TYPE_CUSTOM,
                null,
                'hkpaymentreviews'
            );
        }
    }

    /**
     * Course pages: "Join class", "Join now" or "Watch recording" under each scheduled live class.
     * Placed by a few lines of plain script so it does not wait for Moodle's script bundle.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE, $DB;
        if (self::is_active()) {
            self::courseindex($hook);
            // The footer sits over the side lists (_app.scss); shorten them to end where it starts, so their
            // last items (the final classes) stay reachable on short pages.
            $hook->add_html('<script>(function(d,w){var f=d.getElementById("page-footer");if(!f){return;}function a(){'
                . 'var t=f.getBoundingClientRect().top;d.querySelectorAll(".drawer.drawer-left,.drawer.drawer-right")'
                . '.forEach(function(x){x.style.height="";if(t<w.innerHeight&&w.innerWidth>=992){'
                . 'x.style.height=Math.max(0,t-x.getBoundingClientRect().top)+"px";}});}'
                . 'w.addEventListener("scroll",a,{passive:true});w.addEventListener("resize",a);a();})(document,window);</script>');
        }
        if (self::is_active() && str_starts_with($PAGE->pagetype, 'mod-attendance-')) {
            // mod_attendance writes a bare "?" (and "? / 2.0" points) for sessions not marked yet.
            $hook->add_html('<script>(function(d,t){d.querySelectorAll("table td").forEach(function(td){'
                . 'var s=td.textContent.trim();if(s==="?"){td.textContent=t;}else if(/^\? \/ [\d.,]+$/.test(s)){td.textContent="–";}});})'
                . '(document,' . json_encode(get_string('att_notmarked', 'theme_hikmah')) . ');</script>');
        }
        if (self::is_active() && str_starts_with($PAGE->pagetype, 'mod-bigbluebuttonbn-')) {
            // The recordings table dates its rows with the browser's "en" (US) order; reorder to "Wednesday 18 November 2026".
            $hook->add_html('<script>(function(d){var re=/^(\w+), (\w+) (\d{1,2}), (\d{4})$/;function f(){'
                . 'd.querySelectorAll(".mod_bigbluebuttonbn_recordings_table td").forEach(function(td){'
                . 'var m=re.exec(td.textContent.trim());if(m){td.textContent=m[1]+" "+m[3]+" "+m[2]+" "+m[4];}});}'
                . 'new MutationObserver(f).observe(d.body,{childList:true,subtree:true});f();})(document);</script>');
            // The class's own page says "Starts … · Duration …" like the course page, not "Opened / Closed".
            $room = $PAGE->cm ? $DB->get_record('bigbluebuttonbn', ['id' => $PAGE->cm->instance], 'id, openingtime, closingtime')
                : null;
            if ($room && $room->openingtime > 0 && $room->closingtime > $room->openingtime) {
                $hook->add_html('<script>(function(d,h){var r=d.querySelector(\'#page-header [data-region="activity-dates"],'
                    . ' .activity-header [data-region="activity-dates"]\');if(r){r.innerHTML=\'<div class="hk-livedates">\'+h+"</div>";}})'
                    . '(document,' . json_encode(\theme_hikmah\local\live_classes::dates($room),
                        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');</script>');
            }
            return;
        }
        if (!self::is_active() || !str_starts_with($PAGE->pagetype, 'course-view-') || !isloggedin()) {
            return;
        }
        if (!$PAGE->user_is_editing()) {
            // What each item is and how long it takes; on a class's own page, a one-line summary under its title.
            $items = \theme_hikmah\local\course_items::export($PAGE->course);
            $summary = '';
            if (str_starts_with($PAGE->pagetype, 'course-view-section-') && ($sectionid = (int) $PAGE->url->get_param('id'))) {
                $section = get_fast_modinfo($PAGE->course)->get_section_info_by_id($sectionid, IGNORE_MISSING);
                $summary = $section ? ($items['sections'][$section->section] ?? '') : '';
            }
            // Submitted, attempted or completed items lose their "Due / Closes" line.
            $hook->add_html('<script>(function(d,x){x.forEach(function(id){var e=d.querySelector('
                . '\'li.activity[data-id="\'+id+\'"] [data-region="activity-dates"]\');if(e){e.remove();}});})(document,'
                . json_encode(array_values($items['done'])) . ');</script>');
            $hook->add_html('<script>(function(d,m,s){Object.keys(m).forEach(function(id){'
                . 'var a=d.querySelector(\'li.activity[data-id="\'+id+\'"] .activity-name-area\');'
                . 'if(!a||a.querySelector(".hk-cmmeta")){return;}var e=d.createElement("div");e.className="hk-cmmeta";'
                . 'e.textContent=m[id];a.appendChild(e);});var h=s&&d.querySelector("#page-header .page-header-headings");'
                . 'if(h&&!d.querySelector(".hk-classsummary")){var p=d.createElement("p");p.className="hk-classsummary";'
                . 'p.textContent=s;h.appendChild(p);}})(document,'
                . json_encode((object) $items['items'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ','
                . json_encode($summary, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');</script>');
        }
        $live = \theme_hikmah\local\live_classes::for_course($PAGE->course);
        if (!$live) {
            return;
        }
        if ($PAGE->user_is_editing()) {
            // Editors keep the activity's real name so they can edit it in place.
            $live = array_map(static fn(array $l): array => array_diff_key($l, ['title' => 1]), $live);
        }
        // Per class: the status and button, "Starts … · Duration …" over Moodle's dates, and the held-class title.
        $hook->add_html('<script>(function(d,c){Object.keys(c).forEach(function(id){'
            . 'var li=d.querySelector(\'li.activity[data-id="\'+id+\'"]\');if(!li||li.querySelector(".hk-livecta")){return;}'
            . 'var l=c[id],b=d.createElement("div");b.className="hk-livecta hk-livecta--"+l.state;b.innerHTML=l.html;'
            . 'var dt=li.querySelector(\'[data-region="activity-dates"]\');'
            . 'if(!dt){dt=d.createElement("div");dt.className="activity-dates";'
            . '(li.querySelector(".activity-item")||li).appendChild(dt);}'
            . 'dt.innerHTML=\'<div class="hk-livedates">\'+l.dates+"</div>";'
            . 'var n=l.title&&li.querySelector(".instancename");if(n){for(var i=0;i<n.childNodes.length;i++){'
            . 'if(n.childNodes[i].nodeType===3&&n.childNodes[i].nodeValue.trim()){n.childNodes[i].nodeValue=l.title+" ";break;}}}'
            . '(li.querySelector(".activity-item")||li).appendChild(b);});})(document,'
            . json_encode($live, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');</script>');
    }

    /**
     * Course index (left list), on course and activity pages: a lock beside a class with locked items, and a
     * held live class named "Class recording" as on the course page. Core draws the list in the browser, so a
     * small observer re-applies both whenever it redraws.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    private static function courseindex(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE;
        if (!isloggedin() || isguestuser() || empty($PAGE->course->id) || $PAGE->course->id == SITEID
                || !preg_match('/^(course-view-|mod-)/', $PAGE->pagetype)) {
            return;
        }
        $locked = [];
        foreach (get_fast_modinfo($PAGE->course)->get_cms() as $cm) {
            if (!$cm->uservisible && $cm->availableinfo && $cm->is_visible_on_course_page()) {
                $locked[$cm->section] = $cm->section;
            }
        }
        $titles = [];
        if (!$PAGE->user_is_editing()) {
            foreach (\theme_hikmah\local\live_classes::for_course($PAGE->course) as $cmid => $live) {
                if (!empty($live['title'])) {
                    $titles[$cmid] = $live['title'];
                }
            }
        }
        if (!$locked && !$titles) {
            return;
        }
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $hook->add_html('<script>(function(d,l,t){function a(){'
            . 'l.forEach(function(id){var e=d.querySelector(\'.courseindex [data-for="section"][data-id="\'+id+\'"] '
            . '[data-for="section_item"]\');if(e&&!e.classList.contains("hk-locked")){e.classList.add("hk-locked");}});'
            . 'Object.keys(t).forEach(function(id){var e=d.querySelector(\'.courseindex [data-for="cm"][data-id="\'+id+\'"] '
            . 'a[data-for="cm_name"]\');if(e&&e.textContent.trim()!==t[id]){e.textContent=t[id];}});}'
            . 'a();new MutationObserver(a).observe(d.getElementById("courseindex")||d.body,{childList:true,subtree:true});})'
            . '(document,' . json_encode(array_values($locked)) . ',' . json_encode((object) $titles, $flags) . ');</script>');
    }

    /**
     * Staging banner and the motion module.
     *
     * @param before_standard_top_of_body_html_generation $hook
     */
    public static function before_top_of_body(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;
        if (!self::is_active()) {
            return;
        }
        if (get_config('theme_hikmah', 'siteenv') === 'staging') {
            $hook->add_html('<div class="hk-staging-banner" role="note">' .
                get_string('stagingbanner', 'theme_hikmah') . '</div>');
        }
        $PAGE->requires->js_call_amd('theme_hikmah/motion', 'init');

        // On BigBlueButton activity pages, "Join session" and recordings open in the in-portal
        // classroom instead of a separate window.
        if (get_config('theme_hikmah', 'embedclassroom') && str_starts_with($PAGE->pagetype, 'mod-bigbluebuttonbn-')) {
            $PAGE->requires->js_call_amd('theme_hikmah/classroom', 'intercept',
                [(new \moodle_url('/theme/hikmah/classroom.php'))->out(false)]);
        }
    }
}
