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
 * Upgrade steps for local_brandmask.
 *
 * @package    local_brandmask
 * @copyright  2026 Vbounds LLC (Sternfast)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Perform the local_brandmask database upgrade to the given target version.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool True on success.
 */
function xmldb_local_brandmask_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026100201) {
        // Add the cohort-targeting table.
        $table = new xmldb_table('local_brandmask_cohort');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('profileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cohortid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('profileid', XMLDB_KEY_FOREIGN, ['profileid'], 'local_brandmask_profile', ['id']);
        $table->add_index('cohortid', XMLDB_INDEX_NOTUNIQUE, ['cohortid']);
        $table->add_index('profilecohort', XMLDB_INDEX_UNIQUE, ['profileid', 'cohortid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026100201, 'local', 'brandmask');
    }

    if ($oldversion < 2026100202) {
        // Add the explicit per-user targeting table.
        $table = new xmldb_table('local_brandmask_user');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('profileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('profileid', XMLDB_KEY_FOREIGN, ['profileid'], 'local_brandmask_profile', ['id']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('profileuser', XMLDB_INDEX_UNIQUE, ['profileid', 'userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026100202, 'local', 'brandmask');
    }

    if ($oldversion < 2026100205) {
        // Email-layer fields on the profile.
        $table = new xmldb_table('local_brandmask_profile');
        $fields = [
            // The fromname/fromemail columns also live in install.xml; (re)create them here,
            // guarded by the field_exists check below, so a site upgrading from a version that
            // predated them still gets the columns that the email fields after them anchor to.
            new xmldb_field('fromname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'extracss'),
            new xmldb_field('fromemail', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'fromname'),
            new xmldb_field('emailenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'fromemail'),
            new xmldb_field('emailsendas', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'emailenabled'),
            new xmldb_field('replytoname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'emailsendas'),
            new xmldb_field('replytoemail', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'replytoname'),
            new xmldb_field('emailsubjectprefix', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'replytoemail'),
            new xmldb_field('emailfooter', XMLDB_TYPE_TEXT, null, null, null, null, null, 'emailsubjectprefix'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026100205, 'local', 'brandmask');
    }

    return true;
}
