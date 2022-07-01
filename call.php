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

require_login();

$currenttimestamp = round(microtime(true) * duitku_mathematical_constants::SECOND_IN_MILLISECONDS);// In milisecond.

$environment = $_POST['environment'];
$paymentamount = (int)$_POST['amount'];
$merchantorderid = $_POST['orderId'];
$customervaname = $_POST['customerVa'];
$productdetails = $_POST['item_name'];
$email = $_POST['email'];
$callbackurl = $_POST['notify_url'];
$returnurl = $_POST['return'];
$custom = explode('-', $merchantorderid);
$userid = (int)$custom[1];
$courseid = (int)$custom[2];
$instanceid = (int)$custom[3];

// Initiate all data needed to create transaction first.
$merchantcode = get_config('enrol_duitku', 'merchantcode');
$apikey = get_config('enrol_duitku', 'apikey');
$expiryperiod = get_config('enrol_duitku', 'expiry');

$url = $environment == 'sandbox' ? 'https://api-sandbox.duitku.com/api/merchant/createInvoice' : 'https://api-prod.duitku.com/api/merchant/createInvoice';
$signature = hash('sha256', $merchantcode.$currenttimestamp.$apikey);
$referenceurl = "{$CFG->wwwroot}/enrol/duitku/reference_check.php?merchantOrderId={$merchantorderid}&courseid={$courseid}&userid={$USER->id}&instanceid={$instanceid}";

$phonenumber = empty($USER->phone1) === true ? "" : $USER->phone1;
$admin = get_admin(); // Only 1 MAIN admin can exist at a time.
$address = [
    'firstName' => $USER->firstname,
    'lastName' => $USER->lastname,
    'address' => $USER->address,
    'city' => $USER->city,
    'postalCode' => "",
    'phone' => $phonenumber, // There are phone1 and phone2 for users. Main phone goes to phone1.
    'countryCode' => $USER->country
];

$customerdetail = [
    'firstName' => $USER->firstname,
    'lastName' => $USER->lastname,
    'email' => $USER->email,
    'phoneNumber' => $phonenumber,
    'billingAddress' => $address,
    'shippingAddress' => $address
];

$itemdetails = [
    [
        'name' => $productdetails,
        'price' => $paymentamount,
        'quantity' => duitku_mathematical_constants::ONE_PRODUCT
    ]
];

$params = [
    'paymentAmount' => (int)$paymentamount,
    'merchantOrderId' => $merchantorderid,
    'productDetails' => $productdetails,
    'customerVaName' => $USER->username,
    'merchantUserInfo' => $USER->username,
    'email' => $USER->email,
    'itemDetails' => $itemdetails,
    'customerDetail' => $customerdetail,
    'callbackUrl' => $callbackurl,
    'returnUrl' => $returnurl,
    'expiryPeriod' => (int)$expiryperiod
];

$paramsstring = json_encode($params);


// Check if the user has not made a transaction before.
$params = [
    'userid' => $userid,
    'courseid' => $courseid,
    'instanceid' => $instanceid,
];
$sql = 'SELECT * FROM {enrol_duitku} WHERE userid = :userid AND courseid = :courseid AND instanceid = :instanceid ORDER BY {enrol_duitku}.timestamp DESC';
$context = context_course::instance($courseid, MUST_EXIST);
$existingdata = $DB->get_record_sql($sql, $params, 1); // Will return exactly 1 row. The newest transaction that was saved.
$duitkuhelper = new duitku_helper($merchantcode, $apikey, $merchantorderid, $environment);

// Initial data that will be used for $enroldata.
$admin = get_admin();
$enroldata = new stdClass();
$enroldata->userid = $USER->id;
$enroldata->courseid = $courseid;
$enroldata->instanceid = $instanceid;
$enroldata->referenceurl = $referenceurl;
$enroldata->timestamp = $currenttimestamp;
$enroldata->signature = $signature;
$enroldata->merchant_order_id = $merchantorderid;
$enroldata->receiver_id = $admin->id;
$enroldata->receiver_email = $admin->email;
$enroldata->payment_status = duitku_status_codes::CHECK_STATUS_PENDING;
$enroldata->pending_reason = get_string('pending_message', 'enrol_duitku');
$enroldata->expiryperiod = $currenttimestamp + ($expiryperiod * duitku_mathematical_constants::MINUTE_IN_SECONDS * duitku_mathematical_constants::SECOND_IN_MILLISECONDS);
if (empty($existingdata)) {
    $requestdata = $duitkuhelper->create_transaction($paramsstring, $currenttimestamp, $context);
    $request = json_decode($requestdata['request']);
    $httpcode = $requestdata['httpCode'];
    if ($httpcode == 200) {
        $enroldata->reference = $request->reference; // Reference only received after successful request transaction.
        $enroldata->timeupdated = round(microtime(true) * duitku_mathematical_constants::SECOND_IN_MILLISECONDS); // In milisecond.
        $DB->insert_record('enrol_duitku', $enroldata);
        header('location: '. $request->paymentUrl);die;
    } else {
        redirect("{$CFG->wwwroot}/enrol/index.php?id={$courseid}", get_string('call_error', 'enrol_duitku'));// Redirects back to payment page with error message.
    }
}

$prevmerchantorderid = $existingdata->merchant_order_id;
$newduitkuhelper = new duitku_helper($merchantcode, $apikey, $prevmerchantorderid, $environment);
$requestdata = $newduitkuhelper->check_transaction($context);
$request = json_decode($requestdata['request']);
$httpcode = $requestdata['httpCode'];

// Check for expired transaction just in case.
if ($existingdata->expiryperiod < $currenttimestamp) {
    $params = [
        'paymentAmount' => (int)$paymentamount,
        'merchantOrderId' => $prevmerchantorderid,
        'productDetails' => $productdetails,
        'customerVaName' => $customervaname,
        'merchantUserInfo' => $USER->username,
        'email' => $USER->email,
        'itemDetails' => $itemdetails,
        'customerDetail' => $customerdetail,
        'callbackUrl' => $callbackurl,
        'returnUrl' => $returnurl,
        'expiryPeriod' => (int)$expiryperiod
    ];
    $paramsstring = json_encode($params);
    $requestdata = $newduitkuhelper->create_transaction($paramsstring, $currenttimestamp, $context);
    $request = json_decode($requestdata['request']);
    $httpcode = $requestdata['httpCode'];
    if ($httpcode == 200) {
        // Insert to database to be reused later.
        $enroldata->id = $existingdata->id;
        $enroldata->reference = $request->reference;
        $enroldata->timestamp = $currenttimestamp;
        $enroldata->expiryperiod = $currenttimestamp + ($expiryperiod * duitku_mathematical_constants::MINUTE_IN_SECONDS * duitku_mathematical_constants::SECOND_IN_MILLISECONDS);
        $DB->update_record('enrol_duitku', $enroldata);
        header('location: '. $request->paymentUrl);die;
    } else {
        redirect("{$CFG->wwwroot}/enrol/index.php?id={$courseid}", get_string('call_error', 'enrol_duitku'));
    }
}

// If duitku has not saved the transaction but transaction exists in database, or transaction is pending.
if ($httpcode === 400 || $request->statusCode === duitku_status_codes::CHECK_STATUS_PENDING) {
    // Redirect user to previous checkout link.
    $redirecturl = $environment === 'sandbox' ? 'https://app-sandbox.duitku.com/' : 'https://app-prod.duitku.com/';
    $redirecturl .= 'redirect_checkout?reference=' . $existingdata->reference;
    header('location: '. $redirecturl);die;
}

// If transaction was cancelled, create a new transaction but with previous merchant order id and new reference.
if ($request->statusCode === duitku_status_codes::CHECK_STATUS_CANCELED) {
    $params = [
        'paymentAmount' => (int)$paymentamount,
        'merchantOrderId' => $prevmerchantorderid,
        'productDetails' => $productdetails,
        'customerVaName' => $USER->username,
        'merchantUserInfo' => $USER->username,
        'email' => $USER->email,
        'itemDetails' => $itemdetails,
        'customerDetail' => $customerdetail,
        'callbackUrl' => $callbackurl,
        'returnUrl' => $returnurl,
        'expiryPeriod' => (int)$expiryperiod
    ];
    $paramsstring = json_encode($params);
    $requestdata = $newduitkuhelper->create_transaction($paramsstring, $currenttimestamp, $context);
    $request = json_decode($requestdata['request']);
    $httpcode = $requestdata['httpCode'];
    if ($httpcode == 200) {
        $newtimestamp = round(microtime(true) * duitku_mathematical_constants::SECOND_IN_MILLISECONDS);
        $enroldata->id = $existingdata->id;// Update the previous data row in database.
        $enroldata->reference = $request->reference;// Reference only received after successful request transaction.
        $enroldata->timestamp = $newtimestamp;
        $enroldata->expiryperiod = $newtimestamp + ($expiryperiod * duitku_mathematical_constants::MINUTE_IN_SECONDS * duitku_mathematical_constants::SECOND_IN_MILLISECONDS);
        $enroldata->timeupdated = $newtimestamp;
        $DB->update_record('enrol_duitku', $enroldata);

        header('location: '. $request->paymentUrl);die;
    } else {
        redirect("{$CFG->wwwroot}/enrol/index.php?id={$courseid}", get_string('call_error', 'enrol_duitku'));// Redirects back to payment page with error message.
    }
}

if ($request->statusCode === duitku_status_codes::CHECK_STATUS_SUCCESS) {
    // If previous transaction is successful use a new merchantOrderId.
    $requestdata = $duitkuhelper->create_transaction($paramsstring, $currenttimestamp, $context);
    $request = json_decode($requestdata['request']);
    $httpcode = $requestdata['httpCode'];
    if ($httpcode == 200) {
        $enroldata->reference = $request->reference;// Reference only received after successful request transaction.
        $enroldata->timeupdated = round(microtime(true) * duitku_mathematical_constants::SECOND_IN_MILLISECONDS);
        $DB->insert_record('enrol_duitku', $enroldata);

        header('location: '. $request->paymentUrl);die;
    } else {
        redirect("{$CFG->wwwroot}/enrol/index.php?id={$courseid}", get_string('call_error', 'enrol_duitku'));// Redirects back to payment page with error message.
    }
}
