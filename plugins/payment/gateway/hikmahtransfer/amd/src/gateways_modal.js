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
 * Payment API entry point for the Hikmah transfer gateway. A manual transfer cannot finish in a
 * modal, so choosing this gateway sends the learner to the instructions and receipt page.
 *
 * @module     paygw_hikmahtransfer/gateways_modal
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Process the payment: go to the transfer page. The returned promise never settles because the
 * browser is leaving the page; access is only granted later, when a reviewer approves.
 *
 * @param {string} component Name of the component that the itemId belongs to
 * @param {string} paymentArea The area of the component that the itemId belongs to
 * @param {number} itemId An internal identifier that is used by the component
 * @returns {Promise<string>}
 */
export const process = (component, paymentArea, itemId) => {
    const params = new URLSearchParams({component: component, paymentarea: paymentArea, itemid: itemId});
    window.location.href = window.M.cfg.wwwroot + '/payment/gateway/hikmahtransfer/pay.php?' + params.toString();
    return new Promise(() => null);
};
