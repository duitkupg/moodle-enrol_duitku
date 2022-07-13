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
 * This script is run when user did not complete the whole transaction when using Duitku POP
 *
 * @package   enrol_duitku
 * @copyright 2022 Michael David <mikedh2612@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_duitku\duitku_helper;

require("../../config.php");
require_once("{$CFG->dirroot}/enrol/duitku/lib.php");

require_login();

// Parameters sent from Duitku return response and return url at enrol.html.
$merchantorderid = required_param('merchantOrderId', PARAM_TEXT);
$reference = required_param('reference', PARAM_TEXT);
$resultcode = required_param('resultCode', PARAM_TEXT);
$courseid = required_param('course', PARAM_TEXT);
$instanceid = required_param('instance', PARAM_TEXT);

$merchantcode = get_config('enrol_duitku', 'merchantcode');
$apikey = get_config('enrol_duitku', 'apikey');
$environment = get_config('enrol_duitku', 'environment');
$expiryperiod = get_config('enrol_duitku', 'expiry');

$referenceurl = "{$CFG->wwwroot}/enrol/duitku/reference_check.php?merchantOrderId={$merchantorderid}&courseid={$courseid}&userid={$USER->id}&instanceid={$instanceid}";

if (!$course = $DB->get_record("course", ["id" => $courseid])) {
    redirect($CFG->wwwroot);
}

if (!empty($SESSION->wantsurl)) {
    $destination = $SESSION->wantsurl;
    unset($SESSION->wantsurl);
} else {
    $destination = "$CFG->wwwroot/course/view.php?id=$course->id";
}
$context = context_course::instance($course->id, MUST_EXIST);
$PAGE->set_context($context);
$eventarray = [
    'context' => $context,
    'relateduserid' => $USER->id,
    'other' => [
        'Log Details' => get_string('user_return', 'enrol_duitku'),
        'merchantOrderId' => $merchantorderid,
        'merchantCode' => $merchantcode,
        'resultCode' => $resultcode
    ]
];
$duitkuhelper = new duitku_helper($merchantorderid, $apikey, $merchantorderid, $environment);
$duitkuhelper->log_request($eventarray);

$fullname = format_string($course->fullname, true, ['context' => $context]);

if (is_enrolled($context, null, '', true)) {
    redirect($destination, get_string('paymentthanks', '', $fullname));
}

// Somehow they aren't enrolled yet.
$PAGE->set_url($destination);
$a = new stdClass();
$a->teacher = get_string('defaultcourseteacher');
$a->fullname = $fullname;
$a->reference = $referenceurl;
$response = (object)[
    'return_header' => format_text(get_string('return_header', 'enrol_duitku'), FORMAT_MOODLE),
    'return_sub_header' => format_text(get_string('return_sub_header', 'enrol_duitku', $a), FORMAT_MOODLE),
    'return_body' => format_text(get_string('return_body', 'enrol_duitku', $a), FORMAT_MOODLE),
];
// Output reason why user has not been enrolled yet.
echo $OUTPUT->header();
echo($OUTPUT->render_from_template('enrol_duitku/duitku_return_template', $response));
notice(get_string('paymentsorry', '', $a), $destination);
