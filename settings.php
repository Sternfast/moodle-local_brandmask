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
 * Admin settings and the management link for local_brandmask.
 *
 * @package    local_brandmask
 * @copyright  2026 Vbounds LLC (Sternfast)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category(
        'local_brandmask_cat',
        get_string('pluginname', 'local_brandmask')
    ));

    // Manage profiles (its own page).
    $ADMIN->add('local_brandmask_cat', new admin_externalpage(
        'local_brandmask_manage',
        get_string('manageprofiles', 'local_brandmask'),
        new moodle_url('/local/brandmask/manage.php'),
        'local/brandmask:manage'
    ));

    // Plugin settings.
    $settings = new admin_settingpage('local_brandmask_settings', get_string('settings', 'local_brandmask'));
    $ADMIN->add('local_brandmask_cat', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_heading(
            'local_brandmask/info',
            '',
            get_string('howitworks', 'local_brandmask')
        ));

        $settings->add(new admin_setting_configtext(
            'local_brandmask/serverip',
            get_string('serverip', 'local_brandmask'),
            get_string('serverip_desc', 'local_brandmask'),
            '',
            PARAM_TEXT
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_brandmask/trustforwardedhost',
            get_string('trustforwardedhost', 'local_brandmask'),
            get_string('trustforwardedhost_desc', 'local_brandmask'),
            0
        ));

        $settings->add(new admin_setting_configtext(
            'local_brandmask/forwardedhostheader',
            get_string('forwardedhostheader', 'local_brandmask'),
            get_string('forwardedhostheader_desc', 'local_brandmask'),
            'X-Forwarded-Host',
            PARAM_TEXT
        ));

        $settings->add(new admin_setting_configtextarea(
            'local_brandmask/trustedproxies',
            get_string('trustedproxies', 'local_brandmask'),
            get_string('trustedproxies_desc', 'local_brandmask'),
            '',
            PARAM_RAW,
            40,
            4
        ));
    }

    // The category's own page is the manage page; no separate settings node needed at top level.
    $settings = null;
}
