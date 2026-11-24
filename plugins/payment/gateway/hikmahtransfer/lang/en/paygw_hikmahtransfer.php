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
 * Strings for the Hikmah transfer gateway.
 *
 * @package    paygw_hikmahtransfer
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allmypayments'] = 'All my payments';
$string['alreadypaid'] = 'Your payment for this course is already approved.';
$string['amountdue'] = 'Amount';
$string['amountmismatch'] = 'Differs from amount due';
$string['approve'] = 'Approve and enroll';
$string['askresubmit'] = 'Ask to resubmit';
$string['backtoqueue'] = 'Back to the review queue';
$string['browsecourses'] = 'Browse courses';
$string['col_item'] = 'Course';
$string['col_learner'] = 'Learner';
$string['col_paidamount'] = 'Amount learner says was paid';
$string['col_scan'] = 'Virus scan';
$string['col_status'] = 'Status';
$string['col_submitted'] = 'Submitted';
$string['col_txnid'] = 'Transaction ID';
$string['config_bankiban'] = 'IBAN / account number';
$string['config_bankname'] = 'Bank name';
$string['config_banktitle'] = 'Account title';
$string['config_easypaisa'] = 'Easypaisa number';
$string['config_instructions'] = 'Extra instructions';
$string['config_instructions_help'] = 'Optional note shown to learners under the account details, for example who to contact about a transfer.';
$string['config_intro'] = 'Enter the accounts learners should send money to. A method is offered to learners only when its details are filled in. Leave a method empty to switch it off.';
$string['config_jazzcash'] = 'JazzCash number';
$string['config_raast'] = 'Raast ID';
$string['copied'] = 'Copied';
$string['copy'] = 'Copy';
$string['decision'] = 'Decision';
$string['decision_approved'] = 'Payment approved. The learner now has access.';
$string['decision_hint'] = 'A reason is required to reject or to ask for resubmission. The learner sees it.';
$string['decision_rejected'] = 'Payment rejected. The learner has been told why.';
$string['decision_resubmit'] = 'The learner has been asked to resubmit.';
$string['decision_stale'] = 'This request was already decided, so nothing was changed.';
$string['details'] = 'Payment details';
$string['download'] = 'Download';
$string['error:alreadyenrolled'] = 'You are already enrolled in this course, so there is nothing to pay.';
$string['error:alreadyenrolled_review'] = 'This learner is already enrolled in the course another way, so approving would enrol them twice. Reject the request instead (and refund it if they paid).';
$string['error:amount'] = 'Enter the amount you transferred.';
$string['error:busy'] = 'This request is being updated by someone else. Try again in a moment.';
$string['error:delivery'] = 'The course access could not be delivered, so the payment was not approved. Check the enrollment method of the course and try again.';
$string['error:futuredate'] = 'The transfer date cannot be in the future.';
$string['error:method'] = 'Choose one of the listed transfer methods.';
$string['error:nomethods'] = 'Fill in at least one transfer method (bank IBAN, JazzCash, Easypaisa or Raast) before enabling this gateway.';
$string['error:reasonrequired'] = 'Give a reason. The learner will see it.';
$string['error:receiptinfected'] = 'The receipt file was refused by the virus scanner and has not been saved. Upload a different file.';
$string['error:receiptread'] = 'The receipt file could not be read. Upload it again.';
$string['error:receiptrequired'] = 'Upload one receipt file (JPG, PNG or PDF).';
$string['error:receiptsize'] = 'The receipt must be smaller than {$a}.';
$string['error:receipttype'] = 'The receipt must be a JPG or PNG image, or a PDF.';
$string['event_approved'] = 'Transfer request approved';
$string['event_receiptviewed'] = 'Transfer receipt viewed';
$string['event_rejected'] = 'Transfer request rejected';
$string['event_resubmit'] = 'Transfer request resubmission requested';
$string['event_submitted'] = 'Transfer request submitted';
$string['field_method'] = 'Paid by';
$string['field_paidamount'] = 'Amount transferred ({$a})';
$string['field_paiddate'] = 'Date of transfer';
$string['field_receipt'] = 'Receipt';
$string['field_receipt_hint'] = 'A screenshot or photo of the transfer confirmation: JPG, PNG or PDF, up to {$a}. Only Hikmah finance reviewers can open it.';
$string['field_txnid'] = 'Transaction ID';
$string['field_txnid_help'] = 'The transaction or reference number shown on your bank or wallet confirmation.';
$string['filter_all'] = 'All';
$string['filter_apply'] = 'Filter';
$string['filter_from'] = 'From';
$string['filter_to'] = 'To';
$string['gatewaydescription'] = 'Pay by bank transfer, JazzCash, Easypaisa or Raast, then upload your receipt. Access opens once Hikmah has verified the payment.';
$string['gatewayname'] = 'Bank or wallet transfer';
$string['gotocourse'] = 'Go to the course';
$string['hikmahtransfer:review'] = 'Review transfer payments';
$string['hikmahtransfer:submit'] = 'Submit a transfer receipt';
$string['hikmahtransfer:viewreceipt'] = 'View transfer receipts';
$string['history'] = 'History';
$string['label_number'] = 'Number';
$string['label_raastid'] = 'Raast ID';
$string['messageprovider:decision'] = 'Decision on your transfer payment';
$string['messageprovider:newsubmission'] = 'Transfer receipts waiting for review';
$string['messageprovider:submitted'] = 'Transfer receipt received';
$string['method_bank'] = 'Bank transfer';
$string['method_easypaisa'] = 'Easypaisa';
$string['method_jazzcash'] = 'JazzCash';
$string['method_raast'] = 'Raast';
$string['msg_approved_body'] = 'Assalamu alaikum {$a->fullname}, your payment of {$a->amount} for "{$a->item}" (reference {$a->reference}) has been verified. You now have access to the course.';
$string['msg_approved_subject'] = 'Payment approved: {$a->item}';
$string['msg_digest_body'] = '{$a->count} transfer receipt(s) are waiting for review.';
$string['msg_digest_subject'] = '{$a->count} transfer receipt(s) waiting for review';
$string['msg_link'] = 'Open in Hikmah Online';
$string['msg_new_body'] = '{$a->fullname} submitted a transfer receipt of {$a->amount} for "{$a->item}" (reference {$a->reference}).';
$string['msg_new_subject'] = 'New transfer receipt: {$a->reference}';
$string['msg_rejected_body'] = 'Assalamu alaikum {$a->fullname}, we could not verify your payment for "{$a->item}" (reference {$a->reference}). Reason: {$a->remarks}';
$string['msg_rejected_subject'] = 'Payment not approved: {$a->item}';
$string['msg_resubmit_body'] = 'Assalamu alaikum {$a->fullname}, we need you to resubmit your receipt for "{$a->item}" (reference {$a->reference}). Reason: {$a->remarks}';
$string['msg_resubmit_subject'] = 'Please resubmit your receipt: {$a->item}';
$string['msg_submitted_body'] = 'Assalamu alaikum {$a->fullname}, we received your transfer receipt of {$a->amount} for "{$a->item}" (reference {$a->reference}). We will email you as soon as it has been verified.';
$string['msg_submitted_subject'] = 'Receipt received: {$a->item}';
$string['nopayments'] = 'No payments yet';
$string['nopayments_text'] = 'When you pay for a course by transfer, its status appears here.';
$string['noremarks'] = 'No remarks given.';
$string['pay_eyebrow'] = 'Pay by transfer';
$string['pay_lead'] = 'Send the fee using the details below, then upload your receipt here. Your place is confirmed once our finance team has checked the transfer.';
$string['pay_step1'] = 'Send the fee';
$string['pay_step2'] = 'Upload your receipt';
$string['pay_step2_text'] = 'Tell us how you paid so we can match your transfer.';
$string['paytitle'] = 'Pay by transfer';
$string['pluginname'] = 'Hikmah transfer';
$string['pluginname_desc'] = 'Manual transfers (bank, JazzCash, Easypaisa, Raast) that a finance reviewer verifies before access is given.';
$string['privacy:metadata:files'] = 'Receipt files uploaded as evidence of a transfer.';
$string['privacy:metadata:hist'] = 'The history of status changes of a transfer request.';
$string['privacy:metadata:hist:remarks'] = 'Remarks recorded with the change.';
$string['privacy:metadata:hist:timecreated'] = 'When the change was made.';
$string['privacy:metadata:hist:tostatus'] = 'The new status.';
$string['privacy:metadata:hist:userid'] = 'The user who made the change.';
$string['privacy:metadata:req'] = 'Transfer payment requests.';
$string['privacy:metadata:req:amount'] = 'The amount due.';
$string['privacy:metadata:req:method'] = 'The transfer method used.';
$string['privacy:metadata:req:paidamount'] = 'The amount the learner reported as transferred.';
$string['privacy:metadata:req:paiddate'] = 'The date of the transfer.';
$string['privacy:metadata:req:reference'] = 'The payment reference.';
$string['privacy:metadata:req:reviewerid'] = 'The reviewer who decided the request.';
$string['privacy:metadata:req:status'] = 'The status of the request.';
$string['privacy:metadata:req:timecreated'] = 'When the request was created.';
$string['privacy:metadata:req:txnid'] = 'The transaction ID given by the learner.';
$string['privacy:metadata:req:userid'] = 'The learner who pays.';
$string['queue_empty'] = 'Nothing to review';
$string['queue_empty_text'] = 'No transfer requests match these filters.';
$string['receipt_deleted'] = 'The receipt was removed at the end of the retention period.';
$string['receipt_none'] = 'No receipt file is stored for this request.';
$string['receipt_nopermission'] = 'You do not have permission to open receipts.';
$string['reference'] = 'Payment reference';
$string['reference_hint'] = 'Put this reference in the transfer description or remarks so we can match your payment.';
$string['reject'] = 'Reject';
$string['remarks'] = 'Remarks for the learner';
$string['remarks_hint'] = 'Optional when approving. Required when rejecting or asking to resubmit.';
$string['resubmit_title'] = 'Please check the details and upload your receipt again.';
$string['resubmitnow'] = 'Resubmit receipt';
$string['review'] = 'Review';
$string['review_eyebrow'] = 'Finance review';
$string['review_waiting'] = 'waiting for verification';
$string['reviewtitle'] = 'Transfer payments';
$string['scan_clean'] = 'Scanned, clean';
$string['scan_notscanned'] = 'Not scanned (no scanner enabled)';
$string['setting_maxbytes'] = 'Maximum receipt size';
$string['setting_maxbytes_desc'] = 'Largest receipt file a learner may upload.';
$string['setting_retentiondays'] = 'Receipt retention (days)';
$string['setting_retentiondays_desc'] = 'Receipt files of approved and rejected requests are deleted this many days after the decision. The request and its history are kept.';
$string['setting_reviewerdigest'] = 'Daily digest for reviewers';
$string['setting_reviewerdigest_desc'] = 'Send reviewers one summary a day instead of a message for every receipt.';
$string['state_approved_text'] = 'Your payment has been verified and your place is confirmed.';
$string['state_approved_title'] = 'Payment approved';
$string['state_draft_title'] = 'Receipt not submitted yet';
$string['state_pending_text'] = 'Our finance team is checking your transfer. You will get an email as soon as it is verified, and the course will open for you.';
$string['state_pending_title'] = 'Receipt received';
$string['state_rejected_title'] = 'Payment not approved';
$string['state_resubmit_title'] = 'Please resubmit your receipt';
$string['status_approved'] = 'Approved';
$string['status_draft'] = 'Draft';
$string['status_pending'] = 'Pending verification';
$string['status_rejected'] = 'Rejected';
$string['status_resubmit'] = 'Resubmission requested';
$string['statustitle'] = 'My payments';
$string['submitagain'] = 'Submit a new payment';
$string['submitreceipt'] = 'Submit receipt';
$string['submitted_ok'] = 'Thank you. Your receipt has been sent for verification.';
$string['task_cleanup'] = 'Delete transfer receipts past retention';
$string['task_digest'] = 'Send transfer review digest';
