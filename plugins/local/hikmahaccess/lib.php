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
 * Callbacks for local_hikmahaccess.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Preferences page: hide links nobody but the administrator needs (owner's decision, 2026-11-19).
 * The pages themselves stay reachable; they are only no longer listed.
 *
 * @param navigation_node $usersetting
 * @param stdClass $user
 * @param context_user $usercontext
 * @param stdClass $course
 * @param context_course $coursecontext
 */
function local_hikmahaccess_extend_navigation_user_settings(
    navigation_node $usersetting,
    $user,
    $usercontext,
    $course,
    $coursecontext
): void {
    global $USER;
    if (is_siteadmin() || !($account = $usersetting->find('useraccount', null))) {
        return;
    }
    \local_hikmahaccess\preferences::trim($account, (int) $USER->id);
}

/**
 * Sign-up form: only the basic details. The Hikmah profile details (and city and country) are asked
 * after the first sign-in; the password rules sit under the password box; one checkbox records
 * agreement to the Terms of Use, Privacy Policy and Refund Policy (owner's decisions, 2026-11-20 and 2026-11-27).
 *
 * @param MoodleQuickForm $mform
 */
function local_hikmahaccess_extend_signup_form(MoodleQuickForm $mform): void {
    foreach (['city', 'country'] as $name) {
        if ($mform->elementExists($name)) {
            $mform->removeElement($name);
        }
    }
    if ($mform->elementExists('passwordpolicyinfo') && $mform->elementExists('email')) {
        $mform->insertElementBefore($mform->removeElement('passwordpolicyinfo', false), 'email');
    }

    // Links open the theme's policy pages, which show the current tool_policy versions that the account accepts.
    $links = [];
    foreach (['terms' => 'termsofuse', 'privacy' => 'privacypolicy', 'refund' => 'refundpolicy'] as $kind => $string) {
        $name = get_string($string, 'local_hikmahaccess');
        $links[$kind] = class_exists('\theme_hikmah\local\policypage') ? html_writer::link(
            \theme_hikmah\local\policypage::url($kind),
            $name,
            ['target' => '_blank', 'rel' => 'noopener']
        ) : $name;
    }
    // Checked on submit (validate_extend_signup_form below); no "*" marker, which would sit on a line of its own.

    // Shows "Continue with Google" (and any other sign-in service) above the form, as on the login page; such
    // accounts are created on first sign-in and asked for their profile details then.
    global $OUTPUT;
    $idps = \auth_plugin_base::prepare_identity_providers_for_output(
        \auth_plugin_base::get_identity_providers(get_enabled_auth_plugins()),
        $OUTPUT
    );
    if ($idps && $mform->elementExists('username')) {
        $html = '';
        foreach ($idps as $idp) {
            $icon = empty($idp['iconurl']) ? '' : html_writer::empty_tag('img', ['src' => $idp['iconurl'], 'alt' => '',
                'width' => 20, 'height' => 20]);
            $html .= html_writer::link(
                $idp['url'],
                $icon . s(get_string('signupwith', 'local_hikmahaccess', $idp['name'])),
                ['class' => 'btn btn-outline-secondary login-identityprovider-btn']
            );
        }
        $html .= html_writer::div(get_string('signupor', 'local_hikmahaccess'), 'login-separator');
        $mform->insertElementBefore($mform->createElement('html', html_writer::div($html, 'hk-signup-idp')), 'username');
    }

    $mform->addElement('checkbox', 'hkagree', '', get_string('signupagree', 'local_hikmahaccess', (object) $links));

    // Optional marketing opt-in, unticked. Named after the profile field so auth_email's profile_save_data() stores
    // it on the new account; the field itself stays off the sign-up form, where it would add a section of its own.
    global $DB;
    if ($DB->record_exists('user_info_field', ['shortname' => 'hkconsentcomms'])) {
        $label = get_string('signupmarketing', 'local_hikmahaccess');
        $mform->addElement('checkbox', 'profile_field_hkconsentcomms', '', $label);
    }
}

/**
 * The agreement checkbox must be ticked.
 *
 * @param array $data
 * @return array errors
 */
function local_hikmahaccess_validate_extend_signup_form(array $data): array {
    return empty($data['hkagree']) ? ['hkagree' => get_string('signupagree_required', 'local_hikmahaccess')] : [];
}
