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
 * Checks the referenceUrl for expiry (just in case admin does not run cron)
 * 
 * @package   enrol_duitku
 * @copyright 2022 Michael David <mikedh2612@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_duitku\duitku_helper;
use enrol_duitku\duitku_status_codes;

require('../../config.php');

$merchantOrderId = required_param('merchantOrderId', PARAM_ALPHANUMEXT);;
$courseId = required_param('courseid', PARAM_ALPHANUMEXT);
$userId = required_param('userid', PARAM_ALPHANUMEXT);
$instanceId = required_param('instanceid', PARAM_ALPHANUMEXT);

$merchantCode = get_config('enrol_duitku', 'merchantcode');
$apiKey = get_config('enrol_duitku', 'apikey');
$environment = get_config('enrol_duitku', 'environment');
$expiryPeriod = get_config('enrol_duitku', 'expiry');

$context = context_course::instance((int)$courseId, MUST_EXIST);

$duitku_helper = new duitku_helper($merchantCode, $apiKey, $merchantOrderId, $environment);
$request_data  = $duitku_helper->check_transaction($context);
$response = json_decode($request_data['request']);
$httpCode	 = $request_data['httpCode'];

$custom = explode('-', $merchantOrderId);

$params = [
	'userid' => (int)$userId,
	'courseid' => (int)$courseId,
	'instanceid' => (int)$instanceId,
	'payment_status' => duitku_status_codes::CHECK_STATUS_PENDING
];
$existing_data = $DB->get_record('enrol_duitku', $params);

//Check for HTTP code first.
//Earlier PHP versions would throw an error to $response->statusCode if not found. Later version would not. 
//Transaction has been created before but has not been chosen a payment method
if (($httpCode === 400) && (!empty($existing_data))) {
	$redirectUrl = $environment === 'sandbox' ? 'https://app-sandbox.duitku.com/' : 'https://app-prod.duitku.com/';
	$redirectUrl .= 'redirect_checkout?reference=' . $existing_data->reference;
	header('location: '. $redirectUrl);die;
}

//Transaction does not exist. Create a new transaction
if (($httpCode === 400) && (empty($existing_data))) {
	$redirectUrl = "$CFG->wwwroot/course/view.php?id=$courseId"; //Cannot redirect user to call.php since it needs to use the POST method
	redirect($redirectUrl, get_string('payment_not_exist', 'enrol_duitku'), null, \core\output\notification::NOTIFY_ERROR);	//Redirects the user to course page with message
}

//Transaction cancelled. Create a new Transaction
if ($response->statusCode === duitku_status_codes::CHECK_STATUS_CANCELED) {
	$redirectUrl = "$CFG->wwwroot/course/view.php?id=$courseId"; //Cannot redirect user to call.php since it needs to use the POST method
	redirect($redirectUrl, get_string('payment_cancelled', 'enrol_duitku'), null, \core\output\notification::NOTIFY_ERROR); //Redirects the user to course page with message
} else {
	//Transaction exists and still awaiting payment.
	$redirectUrl = $environment === 'sandbox' ? 'https://app-sandbox.duitku.com/' : 'https://app-prod.duitku.com/';
	$redirectUrl .= 'redirect_checkout?reference=' . $existing_data->reference;
	header('location: '. $redirectUrl);die;
}