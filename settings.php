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
 * The admin global settings for inserting Duitku credentials
 *
 * @package   enrol_duitku
 * @copyright 2022 Michael David <mikedh2612@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Check root/lib/adminlib.php for lists of available classes.
    // --- settings ------------------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_duitku_settings', '', get_string('pluginname_desc', 'enrol_duitku')));

    $settings->add(new admin_setting_configtext('enrol_duitku/apikey', get_string('apikey', 'enrol_duitku'), get_string('apikey_desc', 'enrol_duitku'), '', PARAM_TEXT, 40));

    $settings->add(new admin_setting_configtext('enrol_duitku/merchantcode', get_string('merchantcode', 'enrol_duitku'),
    get_string('merchantcode_desc', 'enrol_duitku'), '', PARAM_TEXT));

    $options = [
        'sandbox' => get_string('environment:sandbox', 'enrol_duitku'),
        'production' => get_string('environment:production', 'enrol_duitku')
    ];
    $settings->add(new admin_setting_configselect('enrol_duitku/environment', get_string('environment', 'enrol_duitku'),
    get_string('environment_desc', 'enrol_duitku'), 'sandbox', $options));

    $settings->add(new admin_setting_configtext('enrol_duitku/expiry', get_string('expiry', 'enrol_duitku'),
    get_string('expiry_desc', 'enrol_duitku'), 10, PARAM_INT));

    $settings->add(new admin_setting_configcheckbox('enrol_duitku/mailstudents', get_string('mailstudents', 'enrol_duitku'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_duitku/mailteachers', get_string('mailteachers', 'enrol_duitku'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_duitku/mailadmins', get_string('mailadmins', 'enrol_duitku'), '', 0));

    $options = [
        ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
    ];
    $settings->add(new admin_setting_configselect('enrol_duitku/expiredaction', get_string('expiredaction', 'enrol_duitku'),
    get_string('expiredaction_help', 'enrol_duitku'), ENROL_EXT_REMOVED_SUSPENDNOROLES, $options));

    // HTML Editor for mail sending. Let default be an empty string. Too difficult to provide the html template with its variables.
    $settings->add(new admin_setting_confightmleditor('enrol_duitku/admin_email', get_string('admin_email', 'enrol_duitku'),
    get_string('admin_email_desc', 'enrol_duitku'), '', PARAM_RAW));
    $settings->add(new admin_setting_confightmleditor('enrol_duitku/teacher_email', get_string('teacher_email', 'enrol_duitku'),
    get_string('teacher_email_desc', 'enrol_duitku'), '', PARAM_RAW));
    $settings->add(new admin_setting_confightmleditor('enrol_duitku/student_email', get_string('student_email', 'enrol_duitku'),
    get_string('student_email_desc', 'enrol_duitku'), '', PARAM_RAW));

    // --- enrol instance defaults ----------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_duitku_defaults',
        get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));

    $options = [ENROL_INSTANCE_ENABLED  => get_string('yes'),
                        ENROL_INSTANCE_DISABLED => get_string('no')];
    $settings->add(new admin_setting_configselect('enrol_duitku/status',
        get_string('status', 'enrol_duitku'), get_string('status_desc', 'enrol_duitku'), ENROL_INSTANCE_DISABLED, $options));

    $currencies = enrol_get_plugin('duitku')->get_currencies();
    $settings->add(new admin_setting_configselect('enrol_duitku/currency',
    get_string('currency', 'enrol_duitku'), '', 'IDR', $currencies));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_duitku/roleid',
            get_string('defaultrole', 'enrol_duitku'),
            get_string('defaultrole_desc', 'enrol_duitku'),
            $student->id ?? null, $options));
    }

    $settings->add(new admin_setting_configduration('enrol_duitku/enrolperiod',
    get_string('enrolperiod', 'enrol_duitku'), get_string('enrolperiod_desc', 'enrol_duitku'), 0));

    $settings->add(new admin_setting_configtext('enrol_duitku/cost', get_string('cost', 'enrol_duitku'), '', 0, PARAM_INT));
}
