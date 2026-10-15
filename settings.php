<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Site settings for local_validat.
 *
 * @package    local_validat
 * @copyright  2026 dawox GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_validat', get_string('pluginname', 'local_validat'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_heading(
            'local_validat/intro',
            '',
            get_string('settings_intro', 'local_validat', (new moodle_url('/local/validat/check.php'))->out())
        ));

        $settings->add(new admin_setting_configtext(
            'local_validat/apiurl',
            get_string('apiurl', 'local_validat'),
            get_string('apiurl_desc', 'local_validat'),
            'https://valid.at',
            PARAM_URL
        ));

        $settings->add(new admin_setting_configpasswordunmask(
            'local_validat/apikey',
            get_string('apikey', 'local_validat'),
            get_string('apikey_desc', 'local_validat'),
            ''
        ));

        $settings->add(new admin_setting_configtext(
            'local_validat/referenceprefix',
            get_string('referenceprefix', 'local_validat'),
            get_string('referenceprefix_desc', 'local_validat'),
            'Moodle',
            PARAM_TEXT,
            20
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_validat/sendemail',
            get_string('sendemail', 'local_validat'),
            get_string('sendemail_desc', 'local_validat'),
            1
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_validat/notify',
            get_string('notify', 'local_validat'),
            get_string('notify_desc', 'local_validat'),
            1
        ));
    }
}
