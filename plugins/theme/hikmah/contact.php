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
 * Public Contact us page (/contact). The message form is Moodle's own "Contact site support" form, so it
 * follows the site's support settings: who may write (supportavailability), where it goes (supportemail)
 * and the reCAPTCHA check for visitors who are not signed in.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- The Contact us page is public.
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/theme/hikmah/contact.php'));
$PAGE->set_pagelayout('hikmah');
$PAGE->set_title(get_string('contact_title', 'theme_hikmah'));
$PAGE->add_body_class('hk-contactpage');

$user = isloggedin() && !isguestuser() ? $USER : null;
$availability = (int) ($CFG->supportavailability ?? CONTACT_SUPPORT_DISABLED);
$canwrite = $availability === CONTACT_SUPPORT_ANYONE || ($availability === CONTACT_SUPPORT_AUTHENTICATED && $user);
$sent = optional_param('sent', 0, PARAM_BOOL);
$failed = false;
$formhtml = '';

if ($canwrite && !$sent) {
    $form = new \core_user\form\contactsitesupport_form($PAGE->url, $user);
    if ($form->is_cancelled()) {
        redirect($PAGE->url);
    } else if ($form->is_submitted() && $form->is_validated() && confirm_sesskey()) {
        // Sent the same way as Moodle's own contact page (user/contactsitesupport.php).
        $data = $form->get_data();
        $data->notloggedinuser = !$user;
        $message = $PAGE->get_renderer('user')->render_from_template('user/contact_site_support_email_body', $data);
        $sendmail = email_to_user(
            user: core_user::get_support_user(),
            from: $user ?? core_user::get_noreply_user(),
            subject: get_string('supportemailsubject', 'admin', format_string($SITE->fullname)),
            messagetext: $message,
            usetrueaddress: true,
            replyto: $data->email,
            replytoname: $data->name
        );
        if ($sendmail) {
            redirect(new moodle_url($PAGE->url, ['sent' => 1]));
        }
        $failed = true;
        $form->set_data($data);
    }
    $formhtml = $form->render();
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_hikmah/contact', \theme_hikmah\local\pages::contact($formhtml, $sent, $failed));
echo $OUTPUT->footer();
