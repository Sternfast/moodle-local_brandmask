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
 * Event observers for the local_brandmask plugin.
 *
 * @package    local_brandmask
 * @copyright  2026 Vbounds LLC (Sternfast)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_brandmask;

/**
 * Keeps targeting rows clean when the thing they point at is deleted, so a profile can never
 * resolve against an orphaned (or recycled) cohort/user id.
 * @package local_brandmask
 */
class observer {
    /**
     * Remove brand-profile targeting rows pointing at a cohort that has been deleted.
     *
     * @param \core\event\cohort_deleted $event The cohort deletion event.
     */
    public static function cohort_deleted(\core\event\cohort_deleted $event): void {
        global $DB;
        $DB->delete_records('local_brandmask_cohort', ['cohortid' => $event->objectid]);
    }

    /**
     * Remove brand-profile targeting rows pointing at a user that has been deleted.
     *
     * @param \core\event\user_deleted $event The user deletion event.
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        $DB->delete_records('local_brandmask_user', ['userid' => $event->objectid]);
    }
}
