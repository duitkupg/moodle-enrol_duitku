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
 * Contains all the strings used in the plugin.
 * @package   enrol_duitku
 * @copyright 2022 Michael David <mikedh2612@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Duitku Payment';
$string['pluginname_desc'] = 'The Duitku module allows you to set up paid courses.  If the cost for any course is zero, then students are not asked to pay for entry.  There is a site-wide cost that you set here as a default for the whole site and then a course setting that you can set for each course individually. The course cost overrides the site cost.';

$string['apikey'] = 'API Key';
$string['apikey_desc'] = 'API Key located in the Project website';
$string['assignrole'] = 'Assign role';
$string['call_error'] = 'An error has occured when requesting transaction. Please try again or contact the site admin';
$string['cost'] = 'Enrol cost';
$string['costerror'] = 'The enrolment cost is not numeric';
$string['costorkey'] = 'Please choose one of the following methods of enrolment.';
$string['course_error'] = 'Course not found';
$string['currency'] = 'Currency';
$string['defaultrole'] = 'Default role assignment';
$string['defaultrole_desc'] = 'Select role which should be assigned to users during Duitku enrolments';
$string['duitku:config'] = 'Configure duitku enrol instances';
$string['duitku:manage'] = 'Manage enrolled users';
$string['duitku:unenrol'] = 'Unenrol users from course';
$string['duitku:unenrolself'] = 'Unenrol self from the course';
$string['duitkuaccepted'] = 'Duitku payments accepted';
$string['enrolenddate'] = 'End date';
$string['enrolenddate_help'] = 'If enabled, users can be enrolled until this date only.';
$string['enrolenddaterror'] = 'Enrolment end date cannot be earlier than start date';
$string['enrolperiod'] = 'Enrolment duration';
$string['enrolperiod_desc'] = 'Default length of time that the enrolment is valid. If set to zero, the enrolment duration will be unlimited by default.';
$string['enrolperiod_help'] = 'Length of time that the enrolment is valid, starting with the moment the user is enrolled. If disabled, the enrolment duration will be unlimited.';
$string['enrolstartdate'] = 'Start date';
$string['enrolstartdate_help'] = 'If enabled, users can be enrolled from this date onward only.';
$string['environment'] = 'Environment';
$string['environment_desc'] = 'Configure Duitku endpoint to be sandbox or production';
$string['errdisabled'] = 'The Duitku enrolment plugin is disabled and does not handle payment notifications.';
$string['expiredaction'] = 'Enrolment expiry action';
$string['expiredaction_help'] = 'Select action to carry out when user enrolment expires. Please note that some user data and settings are purged from course during course unenrolment.';
$string['expiry'] = 'Expiry Period';
$string['expiry_desc'] = 'Expiry period for each transaction. Units set in minutes';
$string['mailadmins'] = 'Notify admin';
$string['mailstudents'] = 'Notify students';
$string['mailteachers'] = 'Notify teachers';
$string['mail_logging'] = 'Duitku Logs the email that is sent';
$string['merchantcode'] = 'Merchant Code';
$string['merchantcode_desc'] = 'Merchant code located in the Project website';
$string['nocost'] = 'There is no cost associated with enrolling in this course!';
$string['payment_expirations'] = 'Duitku checks for expired transaction in database';
$string['payment_not_exist'] = 'Transaction does not exist. Please create a new transaction';
$string['payment_cancelled'] = 'Transaction cancelled. Please create a new transaction';
$string['payment_paid'] = 'Transaction paid succesfully. Please wait a moment and refresh the page again.';
$string['pending_message'] = 'User has not completed payment yet';
$string['sendpaymentbutton'] = 'Pay via Duitku';
$string['status'] = 'Allow Duitku enrolments';
$string['status_desc'] = 'Allow users to use Duitku to enrol into a course by default.';
$string['user_return'] = 'User has returned from redirect page';

$string['duitku_request_log'] = 'Duitku Enrol Plugin Log';
$string['log_request_transaction'] = 'Requesting a transaction to Duitku';
$string['log_request_transaction_response'] = 'Duitku response to Request Transaction';
$string['log_check_transaction'] = 'Checking transaction to Duitku';
$string['log_check_transaction_response'] = 'Duitku respose for Checking Transaction';
$string['log_callback'] = 'Received Callback from Duitku. Affected student should be enrolled';

$string['environment:production'] = 'Production';
$string['environment:sandbox'] = 'Sandbox';

$string['return_header'] = 'Pending Transaction';
$string['return_sub_header'] = 'Course name : {$a->fullname}';
$string['return_body'] = 'If you have already paid, wait a few moments then check again if you are already enrolled. <br /> We kept your payment <a href="{$a->reference}">here</a> in case you would like to return.';

$string['admin_email'] = 'Email to Admin on Enrolment';
$string['admin_email_desc'] = 'Fill with HTML format. Leave blank for default template. <br /> Use "$courseShortName" to display the enrolled course short name, <br /> "$studentUsername" to display enrolled student username, <br /> "$courseFullName" to display the enrolled course full name, <br /> "$amount" to get the amount payed during enrolment, "$adminUsername" to get the admin username, "$teacherName" to get the teacher username. (All without quotation marks).';

$string['teacher_email'] = 'Email to Teacher on Enrolment';
$string['teacher_email_desc'] = 'Fill with HTML format. Leave blank for default template. <br /> Use "$courseShortName" to display the enrolled course short name, <br /> "$studentUsername" to display enrolled student username, <br /> "$courseFullName" to display the enrolled course full name, <br /> "$amount" to get the amount payed during enrolment, "$adminUsername" to get the admin username, "$teacherName" to get the teacher username. (All without quotation marks).';

$string['student_email'] = 'Email to Student on Enrolment';
$string['student_email_desc'] = 'Fill with HTML format. Leave blank for default template. <br /> Use "$courseShortName" to display the enrolled course short name, <br /> "$studentUsername" to display enrolled student username, <br /> "$courseFullName" to display the enrolled course full name, <br /> "$amount" to get the amount payed during enrolment, "$adminUsername" to get the admin username, "$teacherName" to get the teacher username. (All without quotation marks).';
