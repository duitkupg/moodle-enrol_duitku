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

require_login();

$merchantorderid = required_param('merchantOrderId', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_ALPHANUMEXT);
$userid = required_param('userid', PARAM_ALPHANUMEXT);
$instanceid = required_param('instanceid', PARAM_ALPHANUMEXT);

$merchantcode = get_config('enrol_duitku', 'merchantcode');
$apikey = get_config('enrol_duitku', 'apikey');
$environment = get_config('enrol_duitku', 'environment');
$expiryperiod = get_config('enrol_duitku', 'expiry');

$context = context_course::instance((int)$courseid, MUST_EXIST);

$duitkuhelper = new duitku_helper($merchantcode, $apikey, $merchantorderid, $environment);
$requestdata  = $duitkuhelper->check_transaction($context);
$response = json_decode($requestdata['request']);
$httpcode = $requestdata['httpCode'];

$custom = explode('-', $merchantorderid);

$params = [
    'userid' => (int)$userid,
    'courseid' => (int)$courseid,
    'instanceid' => (int)$instanceid,
    'payment_status' => duitku_status_codes::CHECK_STATUS_PENDING
];
$existingdata = $DB->get_record('enrol_duitku', $params);

// Check for HTTP code first.
// Earlier PHP versions would throw an error to $response->statusCode if not found. Later version would not.
// Transaction does not exist. Create a new transaction.
if (($httpcode !== 200) || (empty($existingdata))) {
    $redirecturl = "$CFG->wwwroot/course/view.php?id=$courseid"; // Cannot redirect user to call.php since it needs to use the POST method.
    redirect($redirecturl, get_string('payment_not_exist', 'enrol_duitku'), null, \core\output\notification::NOTIFY_ERROR); // Redirects the user to course page with message.
}

// Transaction has been paid before. Go to course page.
if ($response->statusCode === duitku_status_codes::CHECK_STATUS_SUCCESS) {
    $redirecturl = "$CFG->wwwroot/course/view.php?id=$courseid";
    redirect($redirecturl, get_string('payment_paid', 'enrol_duitku'), null, \core\output\notification::NOTIFY_SUCCESS); // Redirects the user to course page with message.
}

// Transaction cancelled. Create a new Transaction.
if ($response->statusCode === duitku_status_codes::CHECK_STATUS_CANCELED) {
    $redirecturl = "$CFG->wwwroot/course/view.php?id=$courseid"; // Cannot redirect user to call.php since it needs to use the POST method.
    redirect($redirecturl, get_string('payment_cancelled', 'enrol_duitku'), null, \core\output\notification::NOTIFY_ERROR); // Redirects the user to course page with message.
} else {
    // Transaction exists and still awaiting payment.
    $redirecturl = $environment === 'sandbox' ? 'https://app-sandbox.duitku.com/' : 'https://app-prod.duitku.com/';
    $redirecturl .= 'redirect_checkout?reference=' . $existingdata->reference;
    header('location: '. $redirecturl);die;
}
