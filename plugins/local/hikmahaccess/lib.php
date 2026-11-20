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
function local_hikmahaccess_extend_navigation_user_settings(navigation_node $usersetting, $user, $usercontext,
        $course, $coursecontext): void {
    global $USER;
    if (is_siteadmin() || !($account = $usersetting->find('useraccount', null))) {
        return;
    }
    \local_hikmahaccess\preferences::trim($account, (int) $USER->id);
}

/**
 * Sign-up form: only the basic details. The Hikmah profile details (and city and country) are asked
 * after the first sign-in; the password rules sit under the password box; one checkbox records
 * agreement to the Terms of Use and Privacy Policy (owner's decision, 2026-11-20).
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

    $links = ['terms' => get_string('termsofuse', 'local_hikmahaccess'),
        'privacy' => get_string('privacypolicy', 'local_hikmahaccess')];
    if (class_exists('\tool_policy\api')) {
        foreach (\tool_policy\api::list_current_versions() as $version) {
            $key = (int) $version->type === \tool_policy\policy_version::TYPE_PRIVACY ? 'privacy' : 'terms';
            $url = new moodle_url('/admin/tool/policy/view.php', ['policyid' => $version->policyid]);
            $links[$key] = html_writer::link($url, get_string($key === 'privacy' ? 'privacypolicy' : 'termsofuse',
                'local_hikmahaccess'), ['target' => '_blank', 'rel' => 'noopener']);
        }
    }
    $mform->addElement('checkbox', 'hkagree', '', get_string('signupagree', 'local_hikmahaccess', (object) $links));
    $mform->addRule('hkagree', get_string('signupagree_required', 'local_hikmahaccess'), 'required', null, 'client');
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
