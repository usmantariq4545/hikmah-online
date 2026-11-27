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
 * Sign-up form, in the order most sites use (owner, 2026-11-27): first and last name, email, password with a
 * live checklist of the password rules, confirm password, "I'm not a robot", the agreement to the Terms of Use,
 * Privacy Policy and Refund Policy, the optional news opt-in, Create account, then "or" and Continue with Google.
 * No username and no "Email (again)": the hook in classes/hook_callbacks.php fills both from the email address
 * when the form is sent. City, country and the Hikmah profile questions are asked after the first sign-in.
 *
 * @param MoodleQuickForm $mform
 */
function local_hikmahaccess_extend_signup_form(MoodleQuickForm $mform): void {
    global $CFG, $DB, $OUTPUT, $PAGE;

    foreach (['city', 'country', 'passwordpolicyinfo'] as $name) {
        if ($mform->elementExists($name)) {
            $mform->removeElement($name);
        }
    }
    foreach (['username', 'email2'] as $name) {
        if ($mform->elementExists($name)) {
            $mform->removeElement($name);
            $mform->addElement('hidden', $name);
            $mform->setType($name, PARAM_RAW);
        }
    }
    if ($mform->elementExists('recaptcha_element')) {
        // The box speaks for itself: no "Security question" heading or help icon.
        $captcha = $mform->getElement('recaptcha_element');
        $captcha->setLabel('');
        $captcha->_helpbutton = '';
    }

    // Password rules as a checklist that ticks itself while typing (signup.js below); the server still checks them.
    $rules = [];
    if (!empty($CFG->passwordpolicy)) {
        $rules['length'] = get_string('signuprule_length', 'local_hikmahaccess', max(1, (int) $CFG->minpasswordlength));
        foreach (['upper' => 'minpasswordupper', 'lower' => 'minpasswordlower', 'digit' => 'minpassworddigits'] as $rule => $setting) {
            if (!empty($CFG->$setting)) {
                $rules[$rule] = get_string('signuprule_' . $rule, 'local_hikmahaccess');
            }
        }
    }
    $items = '';
    foreach ($rules as $rule => $text) {
        $items .= html_writer::tag('li', s($text), ['data-rule' => $rule]);
    }
    $meter = html_writer::div(html_writer::div('', 'hk-pwmeter__bar'), 'hk-pwmeter', ['aria-hidden' => 'true']) .
        html_writer::div('', 'hk-pwmeter__label', ['aria-live' => 'polite']);
    $mform->addElement('static', 'hkpasswordrules', '', html_writer::div(
        $meter . ($items ? html_writer::tag('ul', $items, ['class' => 'hk-pwrules']) : ''),
        'hk-pwcheck',
        [
            'data-minlength' => (int) ($CFG->minpasswordlength ?? 0),
            'data-words' => implode('|', array_map(
                fn($w) => get_string('signupstrength_' . $w, 'local_hikmahaccess'),
                ['weak', 'fair', 'strong']
            )),
            'data-match' => get_string('signuppasswordmatch', 'local_hikmahaccess'),
            'data-nomatch' => get_string('signuppasswordmismatch', 'local_hikmahaccess'),
        ]
    ));

    $mform->addElement('password', 'hkpassword2', get_string('signupconfirmpassword', 'local_hikmahaccess'), [
        'maxlength' => MAX_PASSWORD_CHARACTERS,
        'autocomplete' => 'new-password',
    ]);
    $mform->setType('hkpassword2', PARAM_RAW);
    $mform->addRule('hkpassword2', get_string('missingpassword'), 'required', null, 'client');

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
    $mform->addElement('checkbox', 'hkagree', '', get_string('signupagree', 'local_hikmahaccess', (object) $links));

    // Optional marketing opt-in, unticked. Named after the profile field so auth_email's profile_save_data() stores
    // it on the new account; the field itself stays off the sign-up form, where it would add a section of its own.
    if ($DB->record_exists('user_info_field', ['shortname' => 'hkconsentcomms'])) {
        $label = get_string('signupmarketing', 'local_hikmahaccess');
        $mform->addElement('checkbox', 'profile_field_hkconsentcomms', '', $label);
    }

    // The fields in the owner's order; anything not listed (hidden fields) keeps its place.
    $order = ['firstname', 'lastname', 'email', 'password', 'hkpasswordrules', 'hkpassword2', 'recaptcha_element', 'hkagree',
        'profile_field_hkconsentcomms'];
    foreach ($order as $name) {
        if ($mform->elementExists($name)) {
            $mform->addElement($mform->removeElement($name, false));
        }
    }

    // "or" and Continue with Google (and any other sign-in service). Printed here, above the buttons that Moodle adds
    // after this hook; signup.js moves it under Create account. Such accounts answer the profile questions later.
    $idps = \auth_plugin_base::prepare_identity_providers_for_output(
        \auth_plugin_base::get_identity_providers(get_enabled_auth_plugins()),
        $OUTPUT
    );
    if ($idps) {
        $html = html_writer::div(get_string('signupor', 'local_hikmahaccess'), 'login-separator');
        foreach ($idps as $idp) {
            $icon = empty($idp['iconurl']) ? '' : html_writer::empty_tag('img', ['src' => $idp['iconurl'], 'alt' => '',
                'width' => 20, 'height' => 20]);
            $html .= html_writer::link(
                $idp['url'],
                $icon . s(get_string('signupwith', 'local_hikmahaccess', $idp['name'])),
                ['class' => 'btn btn-outline-secondary login-identityprovider-btn']
            );
        }
        $mform->addElement('html', html_writer::div($html, 'hk-signup-idp'));
    }

    $PAGE->requires->js_init_code(file_get_contents(__DIR__ . '/signup.js'), true);
}

/**
 * The agreement checkbox must be ticked and both passwords must match.
 *
 * @param array $data
 * @return array errors
 */
function local_hikmahaccess_validate_extend_signup_form(array $data): array {
    $errors = [];
    if (empty($data['hkagree'])) {
        $errors['hkagree'] = get_string('signupagree_required', 'local_hikmahaccess');
    }
    if (($data['hkpassword2'] ?? '') !== ($data['password'] ?? '')) {
        $errors['hkpassword2'] = get_string('signuppasswordmismatch', 'local_hikmahaccess');
    }
    return $errors;
}
