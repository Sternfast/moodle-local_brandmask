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
 * Privacy provider for the local_brandmask plugin.
 *
 * @package    local_brandmask
 * @copyright  2026 Vbounds LLC (Sternfast)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_brandmask\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context;
use context_system;

/**
 * Privacy provider. The only personal data is the per-user targeting table
 * (which users are assigned a branding profile). Cohort targeting references a cohort,
 * not a person, so it is not covered here.
 * @package local_brandmask
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection The metadata collection to add items to.
     * @return collection The updated metadata collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_brandmask_user', [
            'userid'    => 'privacy:metadata:local_brandmask_user:userid',
            'profileid' => 'privacy:metadata:local_brandmask_user:profileid',
        ], 'privacy:metadata:local_brandmask_user');
        return $collection;
    }

    /**
     * Return the list of contexts that contain personal data for the given user.
     *
     * @param int $userid The user to search.
     * @return contextlist The list of contexts containing data for the user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('local_brandmask_user', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Return the list of users who have data within the given context.
     *
     * @param userlist $userlist The userlist to add users to.
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_brandmask_user}", []);
    }

    /**
     * Export all personal data for the given approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export data for.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        if (empty($contextlist->count())) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $rows = $DB->get_records_sql(
            "SELECT bu.id, p.name, p.brandkey
               FROM {local_brandmask_user} bu
               JOIN {local_brandmask_profile} p ON p.id = bu.profileid
              WHERE bu.userid = ?",
            [$userid]
        );
        if (!$rows) {
            return;
        }
        $assignments = [];
        foreach ($rows as $r) {
            $assignments[] = ['profile' => $r->name, 'brandkey' => $r->brandkey];
        }
        writer::with_context(context_system::instance())->export_data(
            [get_string('pluginname', 'local_brandmask')],
            (object) ['brandassignments' => $assignments]
        );
    }

    /**
     * Delete all personal data for all users in the given context.
     *
     * @param context $context The context to delete data in.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if ($context instanceof context_system) {
            $DB->delete_records('local_brandmask_user');
        }
    }

    /**
     * Delete all personal data for the user in the given approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to delete data in.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                $DB->delete_records('local_brandmask_user', ['userid' => $contextlist->get_user()->id]);
                return;
            }
        }
    }

    /**
     * Delete personal data for the listed users in the given context.
     *
     * @param approved_userlist $userlist The approved userlist to delete data for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }
        $userids = $userlist->get_userids();
        if ($userids) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids);
            $DB->delete_records_select('local_brandmask_user', "userid $insql", $inparams);
        }
    }
}
