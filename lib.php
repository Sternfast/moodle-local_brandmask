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
 * Library hooks for local_brandmask.
 *
 * @package    local_brandmask
 * @copyright  2026 Vbounds LLC (Sternfast)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serve a tenant's uploaded brand assets (logo / partner logo / login background).
 *
 * These are PUBLIC brand assets — they appear on the pre-login sign-in page — so they are
 * served without a login requirement. They are small, admin-uploaded image files only.
 *
 * @return bool false if not found (Moodle then sends 404)
 * @package local_brandmask
 */
function local_brandmask_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }
    if (!in_array($filearea, ['logo', 'logopartner', 'loginbg'], true)) {
        return false;
    }

    $itemid   = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_brandmask', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    // Cacheable for a day; public asset.
    \core\session\manager::write_close();
    send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
}

/**
 * Brand an outgoing email in place for the recipient's tenant, if any.
 *
 * Core's message manager calls this for EACH processor just before it sends. We touch only the
 * 'email' processor's own copy of the event — where module extras (e.g. the forum
 * reply-by-email footer) have already been merged — leave every other processor untouched, and
 * keep the core 'email' processor enabled, so per-user notification preferences keep working.
 * The event object's properties are mutated in place (its return value is ignored by core).
 *
 * @param string $procname The processor about to run.
 * @param object $proceventdata The per-processor event data, mutated in place.
 * @return void
 */
function local_brandmask_pre_processor_message_send($procname, $proceventdata) {
    if ($procname !== 'email') {
        return;
    }
    if (empty($proceventdata->userto) || empty($proceventdata->userto->id)) {
        return;
    }
    try {
        // The for_user() call returns null during install/upgrade or when the recipient is not a tenant.
        $profile = \local_brandmask\local\resolver::for_user((int) $proceventdata->userto->id);
        if (!$profile || empty($profile->emailenabled)) {
            return;
        }
        $branded = \local_brandmask\local\mailbrander::brand(
            $profile,
            (string) ($proceventdata->subject ?? ''),
            (string) ($proceventdata->fullmessagehtml ?? ''),
            (string) ($proceventdata->fullmessage ?? ''),
            $proceventdata->userfrom ?? null
        );
        $proceventdata->subject         = $branded['subject'];
        $proceventdata->fullmessagehtml = $branded['html'];
        $proceventdata->fullmessage     = $branded['text'];
        $proceventdata->userfrom        = $branded['userfrom'];
        // Only set the tenant reply-to when the message carries none of its own (so a
        // module's reply-by-email handler address, e.g. forum, is never clobbered).
        if ($branded['replytoemail'] !== '' && empty($proceventdata->replyto)) {
            $proceventdata->replyto     = $branded['replytoemail'];
            $proceventdata->replytoname = $branded['replytoname'];
        }
    } catch (\Throwable $e) {
        debugging('local_brandmask: email branding failed (' . $e->getMessage() . ')', DEBUG_DEVELOPER);
    }
}
