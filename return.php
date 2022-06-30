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
 * This script is run when user did not complete the whole transaction 
 * when using Duitku POP
 * 
 * Checks transaction in case user decided to pay during returning from Duitku POP
 * 
 * @package   enrol_duitku
 * @copyright 2022 Michael David <mikedh2612@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_duitku\duitku_helper;

require("../../config.php");
require_once("{$CFG->dirroot}/enrol/duitku/lib.php");

// Parameters sent from Duitku return response and return url at enrol.html
$merchantOrderId = required_param('merchantOrderId', PARAM_TEXT);
$reference = required_param('reference', PARAM_TEXT);
$resultCode = required_param('resultCode', PARAM_TEXT);
$courseId = required_param('course', PARAM_TEXT);
$instanceId = required_param('instance', PARAM_TEXT);

$merchantCode = get_config('enrol_duitku', 'merchantcode');
$apiKey = get_config('enrol_duitku', 'apikey');
$environment = get_config('enrol_duitku', 'environment');
$expiryPeriod = get_config('enrol_duitku', 'expiry');

$referenceUrl = "{$CFG->wwwroot}/enrol/duitku/reference_check.php?merchantOrderId={$merchantOrderId}&courseid={$courseId}&userid={$USER->id}&instanceid={$instanceId}";

if (!$course = $DB->get_record("course", ["id"=>$courseId])) {
    redirect($CFG->wwwroot);
}

require_login();

if (!empty($SESSION->wantsurl)) {
    $destination = $SESSION->wantsurl;
    unset($SESSION->wantsurl);
} else {
    $destination = "$CFG->wwwroot/course/view.php?id=$course->id";
}
$context = context_course::instance($course->id, MUST_EXIST);
$PAGE->set_context($context);
$event_array = [
    'context' => $context,
    'relateduserid' => $USER->id,
    'other' => [
        'Log Details' => get_string('user_return', 'enrol_duitku'),
        'merchantOrderId' => $merchantOrderId,
        'resultCode' => $resultCode
    ]
];
$duitku_helper = new duitku_helper($merchantOrderId, $apiKey, $merchantOrderId, $environment);
$duitku_helper->log_request($event_array);

$fullname = format_string($course->fullname, true, ['context' => $context]);

if (is_enrolled($context, NULL, '', true)) {
    redirect($destination, get_string('paymentthanks', '', $fullname));
}

//Somehow they aren't enrolled yet. 
$PAGE->set_url($destination);
$a = new stdClass();
$a->teacher = get_string('defaultcourseteacher');
$a->fullname = $fullname;

//Output reason why user has not been enrolled yet
$response = (object)[
    'courseName' => $course->fullname,
    'referenceUrl' => $referenceUrl
];
echo $OUTPUT->header();
echo($OUTPUT->render_from_template('enrol_duitku/duitku_return_template', $response));
notice(get_string('paymentsorry', '', $a), $destination);