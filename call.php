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
 * Creates an invoice to Duitku and redirects the user to Duitku POP page.
 * @package   enrol_duitku
 * @copyright 2022 Michael David <mikedh2612@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_duitku\duitku_status_codes;
use enrol_duitku\duitku_mathematical_constants;
use enrol_duitku\duitku_helper;

require("../../config.php");
global $DB;

$timestamp = round(microtime(true) * duitku_mathematical_constants::SECOND_IN_MILLISECONDS); //in milisecond

$environment = $_POST['environment'];
$paymentAmount = (int)$_POST['amount'];
$merchantOrderId = $_POST['orderId'];
$customerVaName = $_POST['customerVa'];
$productDetails = $_POST['item_name'];
$email = $_POST['email'];
$callbackUrl = $_POST['notify_url'];
$returnUrl = $_POST['return'];
$custom = explode('-', $merchantOrderId);
$userid = (int)$custom[1];
$courseid = (int)$custom[2];
$instanceid = (int)$custom[3];

//Initiate all data needed to create transaction first
$merchantCode = get_config('enrol_duitku', 'merchantcode'); 
$apiKey = get_config('enrol_duitku', 'apikey');
$expiryPeriod = get_config('enrol_duitku', 'expiry');

$url = $environment == 'sandbox' ? 'https://api-sandbox.duitku.com/api/merchant/createInvoice' : 'https://api-prod.duitku.com/api/merchant/createInvoice';
$signature = hash('sha256', $merchantCode.$timestamp.$apiKey);
$referenceUrl = "{$CFG->wwwroot}/enrol/duitku/reference_check.php?merchantOrderId={$merchantOrderId}&courseid={$courseid}&userid={$USER->id}&instanceid={$instanceid}";

$phoneNumber = empty($USER->phone1) === true ? "" : $USER->phone1;
$admin = get_admin(); //Only 1 MAIN admin can exist at a time
$address = [ 
	'firstName' => $USER->firstname,
	'lastName' => $USER->lastname,
	'address' => $USER->address,
	'city' => $USER->city,
	'postalCode' => "",
	'phone' => $phoneNumber, //There are phone1 and phone2 for users. Main phone goes to phone1.
	'countryCode' => $USER->country
];

$customerDetail = [
	'firstName' => $USER->firstname,
	'lastName' => $USER->lastname,
	'email' => $USER->email,
	'phoneNumber' => $phoneNumber,
	'billingAddress' => $address,
	'shippingAddress' => $address
];

$itemDetails = [
	[
		'name' => $productDetails,
		'price' => $paymentAmount,
		'quantity' => duitku_mathematical_constants::ONE_PRODUCT
	]
];

$params = [
	'paymentAmount' => $paymentAmount,
	'merchantOrderId' => $merchantOrderId,
	'productDetails' => $productDetails,
	'customerVaName' => $USER->username,
	'merchantUserInfo' => $USER->username,
	'email' => $USER->email,
	'itemDetails' => $itemDetails,
	'customerDetail' => $customerDetail,
	'callbackUrl' => $callbackUrl,
	'returnUrl' => $returnUrl,
	'expiryPeriod' => (int)$expiryPeriod
];

$params_string = json_encode($params);


//Check if the user has not made a transaction before
$params = [
	'userid' => $userid,
	'courseid' => $courseid,
	'instanceid' => $instanceid,
];
$sql_statement = '
SELECT *
FROM {enrol_duitku}
WHERE userid = :userid
AND courseid = :courseid
AND instanceid = :instanceid
ORDER BY {enrol_duitku}.timestamp DESC
';
$context = context_course::instance($courseid, MUST_EXIST);
$existing_data = $DB->get_record_sql($sql_statement, $params, 1);//Will return exactly 1 row. The newest transaction that was saved.
$duitku_helper = new duitku_helper($merchantCode, $apiKey, $merchantOrderId, $environment);

//Initial data that will be used for $enrol_data
$admin = get_admin();
$enrol_data = new stdClass();
$enrol_data->userid = $USER->id;
$enrol_data->courseid = $courseid;
$enrol_data->instanceid = $instanceid;
$enrol_data->referenceurl = $referenceUrl;
$enrol_data->timestamp = $timestamp;
$enrol_data->signature = $signature;
$enrol_data->merchant_order_id = $merchantOrderId;
$enrol_data->receiver_id = $admin->id;
$enrol_data->receiver_email = $admin->email;
$enrol_data->payment_status = duitku_status_codes::CHECK_STATUS_PENDING;
$enrol_data->pending_reason = get_string('pending_message', 'enrol_duitku');
$enrol_data->expiryperiod = $timestamp + ($expiryPeriod * duitku_mathematical_constants::MINUTE_IN_SECONDS * duitku_mathematical_constants::SECOND_IN_MILLISECONDS);
if (empty($existing_data)) {
	$request_data = $duitku_helper->create_transaction($params_string, $timestamp, $context);
	$request = json_decode($request_data['request']);
	$httpCode = $request_data['httpCode'];
	if($httpCode == 200) {
		$enrol_data->reference = $request->reference;//Reference only received after successful request transaction
		$enrol_data->timeupdated = round(microtime(true) * duitku_mathematical_constants::SECOND_IN_MILLISECONDS); //in milisecond
		$DB->insert_record('enrol_duitku', $enrol_data);
		header('location: '. $request->paymentUrl);die;
	} else {
		redirect("{$CFG->wwwroot}/enrol/index.php?id={$courseid}", get_string('call_error', 'enrol_duitku'));//Redirects back to payment page with error message
	}
}

$prev_merchantOrderId = $existing_data->merchant_order_id;
$new_duitku_helper = new duitku_helper($merchantCode, $apiKey, $prev_merchantOrderId, $environment);
$request_data = $new_duitku_helper->check_transaction($context);
$request = json_decode($request_data['request']);
$httpCode = $request_data['httpCode'];

//If duitku has not saved the transaction but transaction exists in database, or transaction is pending
if ($httpCode === 400 || $request->statusCode === duitku_status_codes::CHECK_STATUS_PENDING) {
	//Redirect user to previous checkout link
	$redirectUrl = $environment === 'sandbox' ? 'https://app-sandbox.duitku.com/' : 'https://app-prod.duitku.com/';
	$redirectUrl .= 'redirect_checkout?reference=' . $existing_data->reference;
	header('location: '. $redirectUrl);die;
}

//If transaction was cancelled, create a new transaction but with previous merchant order id and new reference
if ($request->statusCode === duitku_status_codes::CHECK_STATUS_CANCELED) {
	$request_data = $new_duitku_helper->create_transaction($params_string, $timestamp, $context);
	$request = json_decode($request_data['request']);
	$httpCode = $request_data['httpCode'];
	if($httpCode == 200) {
		$new_timestamp = round(microtime(true) * duitku_mathematical_constants::SECOND_IN_MILLISECONDS);
		$enrol_data->id = $existing_data->id;//Update the previous data row in database.
		$enrol_data->reference = $request->reference;//Reference only received after successful request transaction
		$enrol_data->timestamp = $new_timestamp;
		$enrol_data->expiryperiod = $new_timestamp + ($expiryPeriod * duitku_mathematical_constants::MINUTE_IN_SECONDS * duitku_mathematical_constants::SECOND_IN_MILLISECONDS);
		$enrol_data->timeupdated = $new_timestamp;
		$DB->update_record('enrol_duitku', $enrol_data);

		header('location: '. $request->paymentUrl);die;
	} else {
		redirect("{$CFG->wwwroot}/enrol/index.php?id={$courseid}", get_string('call_error', 'enrol_duitku'));//Redirects back to payment page with error message
	}
}

if ($request->statusCode === duitku_status_codes::CHECK_STATUS_SUCCESS) {
	//If previous transaction is successful use a new merchantOrderId
	$request_data = $duitku_helper->create_transaction($params_string, $timestamp, $context);
	$request = json_decode($request_data['request']);
	$httpCode = $request_data['httpCode'];
	if($httpCode == 200) {
		$enrol_data->reference = $request->reference;//Reference only received after successful request transaction
		$enrol_data->timeupdated = round(microtime(true) * duitku_mathematical_constants::SECOND_IN_MILLISECONDS);
		$DB->insert_record('enrol_duitku', $enrol_data);

		header('location: '. $request->paymentUrl);die;
	} else {
		redirect("{$CFG->wwwroot}/enrol/index.php?id={$courseid}", get_string('call_error', 'enrol_duitku'));//Redirects back to payment page with error message
	}
}