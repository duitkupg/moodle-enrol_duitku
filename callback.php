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
 * 
 * Listens to any callbacks from Duitku
 * If Duitku confirms payment, then setup enrolment for that user
 * 
 * Checks transaction in case user decided to pay during returning from Duitku POP
 * 
 * @package   enrol_duitku
 * @copyright 2022 Michael David <mikedh2612@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_duitku\duitku_mathematical_constants;
use enrol_duitku\duitku_status_codes;
use enrol_duitku\duitku_helper;

// This script does not require login.
require("../../config.php"); // phpcs:ignore
require_once("lib.php");
require_once("{$CFG->libdir}/enrollib.php");
require_once("{$CFG->libdir}/filelib.php");

// Make sure we are enabled in the first place.
if (!enrol_is_enabled('duitku')) {
	http_response_code(503);
	throw new moodle_exception('errdisabled', 'enrol_duitku');
}

/// Keep out casual intruders
if (empty($_POST) or !empty($_GET)) {
	http_response_code(400);
	throw new moodle_exception('invalidrequest', 'core_error');
}

//Gets all response parameter from Duitku callback
$apiKey = get_config('enrol_duitku', 'apikey');
$merchantCode = isset($_POST['merchantCode']) ? $_POST['merchantCode'] : null;
$amount = isset($_POST['amount']) ? $_POST['amount'] : null;
$merchantOrderId = isset($_POST['merchantOrderId']) ? $_POST['merchantOrderId'] : null;
$productDetail = isset($_POST['productDetail']) ? $_POST['productDetail'] : null;
$additionalParam = isset($_POST['additionalParam']) ? $_POST['additionalParam'] : null;
$paymentCode = isset($_POST['paymentCode']) ? $_POST['paymentCode'] : null;
$resultCode = isset($_POST['resultCode']) ? $_POST['resultCode'] : null;
$merchantUserId = isset($_POST['merchantUserId']) ? $_POST['merchantUserId'] : null;
$reference = isset($_POST['reference']) ? $_POST['reference'] : null;
$signature = isset($_POST['signature']) ? $_POST['signature'] : null;

//Making sure that merchant order id is in the correct format
$custom = explode('-', $merchantOrderId);
if (empty($custom) || count($custom) < 4) {
	throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Invalid value of the request param: custom');
}

if (empty($merchantCode) || empty($amount) || empty($merchantOrderId) || empty($signature)) {
	throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Bad Parameter');
}

$params = $merchantCode . $amount . $merchantOrderId . $apiKey;
$calcSignature = md5($params);
if ($signature != $calcSignature) {
	throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Bad Signature');
}

//Make sure it is not a failed payment
if (($resultCode !== duitku_status_codes::CHECK_STATUS_SUCCESS)) {
	throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Payment Failed');
}

$data = new stdClass();
$data->userid = (int)$custom[1];
$data->courseid = (int)$custom[2];
$user = $DB->get_record("user", ["id" => $data->userid], "*", MUST_EXIST);
$course = $DB->get_record("course", ["id" => $data->courseid], "*", MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);
$PAGE->set_context($context);

//Set enrolment duration (default from Moodle)
//Only accessible if all required parameters are available
$data->instanceid = (int)$custom[3];
$plugin_instance = $DB->get_record("enrol", ["id" => $data->instanceid, "enrol" => "duitku", "status" => 0], "*", MUST_EXIST);
$plugin = enrol_get_plugin('duitku');
if ($plugin_instance->enrolperiod) {
	$timestart = time();
	$timeend = $timestart + $plugin_instance->enrolperiod;
}
else {
	$timestart = 0;
	$timeend = 0;
}

//Double check on transaction before continuing
$environment = get_config('enrol_duitku', 'environment');
$duitku_helper = new duitku_helper($merchantCode, $apiKey, $merchantOrderId, $environment);
$request_data = $duitku_helper->check_transaction($context);
$response = json_decode($request_data['request']);
$httpCode = $request_data['httpCode'];
if (($response->statusCode !== duitku_status_codes::CHECK_STATUS_SUCCESS)) {
	throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Payment Failed');
}

// Enrol user and update database
$plugin->enrol_user($plugin_instance, $user->id, $plugin_instance->roleid, $timestart, $timeend);
//Add to log that callback has been received and student enrolled
$event_array = [
	'context' => $context,
	'relateduserid' => (int)$custom[1],
	'other' => [
		'Log Details' => get_string('log_callback', 'enrol_duitku'),
		'merchantOrderId' => $merchantOrderId,
		'reference' => $reference,
		
	]
];
$duitku_helper->log_request($event_array);

$params = [
	'userid' => (int)$custom[1],	
	'courseid' => (int)$custom[2],
	'instanceid' => (int)$custom[3],
	'reference' => $reference
];
$admin = get_admin(); //Only 1 MAIN admin can exist at a time
$existing_data = $DB->get_record('enrol_duitku', $params);
$data->id = $existing_data->id;
$data->payment_status = $resultCode;
$data->pending_reason = get_string('log_callback', 'enrol_duitku');
$data->timeupdated = round(microtime(true) * duitku_mathematical_constants::SECOND_IN_MILLISECONDS);

$DB->update_record('enrol_duitku', $data);

//Standard mail sending by Moodle to notify users if there are enrolments
// Pass $view=true to filter hidden caps if the user cannot see them
if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC', '', '', '', '', false, true)) {
	$users = sort_by_roleassignment_authority($users, $context);
	$teacher = array_shift($users);
} else {
	$teacher = false;
}

$mailstudents = $plugin->get_config('mailstudents');
$mailteachers = $plugin->get_config('mailteachers');
$mailadmins   = $plugin->get_config('mailadmins');
$shortname = format_string($course->shortname, true, ['context' => $context]);

//Setup the array that will be replace the variables in the custom email html.
$maildata = [
	'$courseFullName' => format_string($course->fullname, true, array('context' => $context)),
	'$amount' => $amount,
	'$courseShortName' => $shortname,
	'$studentUsername' => fullname($user),
	'$courseFullName' => format_string($course->fullname, true, array('context' => $context)),
	'$teacherName' => empty($teacher) ? core_user::get_support_user() : $teacher->username,
	'$adminUsername' => $admin->username

];

//Setup the array that will be replace the variables in the email template.
$templatedata = new stdClass();
$templatedata->courseFullName = format_string($course->fullname, true, array('context' => $context));
$templatedata->amount = $amount;
$templatedata->courseShortName = $shortname;
$templatedata->studentUsername = fullname($user);
$templatedata->courseFullName = format_string($course->fullname, true, array('context' => $context));
$templatedata->teacherName = empty($teacher) ? core_user::get_support_user() : $teacher->username;
$templatedata->adminUsername = $admin->username;

if (!empty($mailstudents)) {
	$userfrom = empty($teacher) ? core_user::get_support_user() : $teacher;
	$subject = get_string("enrolmentnew", 'enrol', $shortname);
	$student_email = $plugin->get_config('student_email');
	$student_email = html_entity_decode($student_email);
	$fullmessage = empty($student_email) === true ? $OUTPUT->render_from_template('enrol_duitku/duitku_mail_for_students', $templatedata) : strtr($student_email, $maildata);

	// Send test email.
	ob_start();
	$success = email_to_user($user, $userfrom, $subject, $fullmessage);
	$smtplog = ob_get_contents();
	ob_end_clean();
}

if (!empty($mailteachers) && !empty($teacher)) {
	$subject = get_string("enrolmentnew", 'enrol', $shortname);
	$teacher_email = $plugin->get_config('teacher_email');
	$fullmessage = empty($teacher_email) === true ? $OUTPUT->render_from_template('enrol_duitku/duitku_mail_for_teachers', $templatedata) : strtr($teacher_email, $maildata);

	// Send test email.
	ob_start();
	$success = email_to_user($teacher, $user, $subject, $fullmessage, $fullmessagehtml);
	$smtplog = ob_get_contents();
	ob_end_clean();
}

if (!empty($mailadmins)) {
	$admin_email = $plugin->get_config('admin_email');
	$admins = get_admins();
	foreach ($admins as $admin) {
		$subject = get_string("enrolmentnew", 'enrol', $shortname);
		$maildata['$adminUsername'] = $admin->username;
		$templatedata->adminUsername = $admin->username;
		$fullmessage = empty($admin_email) === true ? $OUTPUT->render_from_template('enrol_duitku/duitku_mail_for_admins', $templatedata) : strtr($admin_email, $maildata);
		// Send test email.
		ob_start();
		echo($fullmessagehtml . '<br />');
		$success = email_to_user($admin, $user, $subject, $fullmessage, $fullmessagehtml);
		$smtplog = ob_get_contents();
		ob_end_clean();
	}
}