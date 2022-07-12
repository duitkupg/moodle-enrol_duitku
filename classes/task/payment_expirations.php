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
 * Will delete all expired records from enrol_duitku table
 * @package   enrol_duitku
 * @copyright 2022 Michael David <mikedh2612@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_duitku\task;

use enrol_duitku\duitku_status_codes;
use enrol_duitku\duitku_mathematical_constants;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to turn the transaction status of any pending transaction into expired
 *
 * @author  2022 Michael David <mikedh2612@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_expirations extends \core\task\scheduled_task {

    /**
     * Name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('payment_expirations', 'enrol_duitku');
    }

    /**
     * Run task for processing expirations.
     */
    public function execute() {
        global $DB;
        mtrace('Executing Duitku Enrol Plugin Cleaning');
        $params = [
            'payment_status' => duitku_status_codes::CHECK_STATUS_PENDING
        ];
        $sql = 'SELECT * FROM {enrol_duitku} WHERE payment_status = :payment_status';
        $transactions = $DB->get_records_sql($sql, $params);
        foreach ($transactions as $transaction) {
            $expiryperiod = (int)$transaction->expiryperiod;
            mtrace(round(microtime(true) * duitku_mathematical_constants::SECOND_IN_MILLISECONDS));
            if ($expiryperiod < round(microtime(true) * duitku_mathematical_constants::SECOND_IN_MILLISECONDS)) {
                $object = (object)[ // Somehow only this method of object instantiation works. Others creates errors.
                    'id' => $transaction->id,
                    'payment_status' => duitku_status_codes::CHECK_STATUS_CANCELED,
                    'pending_reason' => 'Transaction expired'
                ];
                $DB->update_record('enrol_duitku', $object);
            }
        }
        mtrace('Finished Cleaning');
    }
}
