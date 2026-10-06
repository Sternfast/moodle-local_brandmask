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
 * Unit tests for the in-place email-branding hook.
 *
 * @package    local_brandmask
 * @copyright  2026 Vbounds LLC (Sternfast)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_brandmask;

/**
 * Tests that the pre_processor hook brands only the email processor's copy, only for tenants.
 *
 * @covers ::local_brandmask_pre_processor_message_send
 */
final class mail_hook_test extends \advanced_testcase {
    /**
     * Create a tenant (cohort + member + branding profile) and return the member user.
     *
     * @param array $profileoverrides Extra/overriding fields for the branding profile.
     * @return object The member user record.
     */
    protected function make_tenant_user(array $profileoverrides = []): object {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $cohort = $this->getDataGenerator()->create_cohort();
        $DB->insert_record('cohort_members', (object) [
            'cohortid' => $cohort->id, 'userid' => $user->id, 'timeadded' => time(),
        ]);
        $pid = $DB->insert_record('local_brandmask_profile', (object) array_merge([
            'brandkey' => 'tenant', 'name' => 'Tenant Corp', 'enabled' => 1, 'primarycolor' => '#c62828',
            'hostname' => 'tenant.example.com', 'emailenabled' => 1, 'fromname' => 'Tenant Corp',
            'emailsubjectprefix' => '[Tenant]',
        ], $profileoverrides));
        $DB->insert_record('local_brandmask_cohort', (object) ['profileid' => $pid, 'cohortid' => $cohort->id]);
        return $user;
    }

    /**
     * Build a per-processor event object like core hands the hook.
     *
     * @param object $userto The recipient user.
     * @return object The event data object.
     */
    protected function event(object $userto): object {
        return (object) [
            'userto' => $userto,
            'userfrom' => \core_user::get_noreply_user(),
            'subject' => 'A notification',
            'fullmessage' => 'Plain body',
            'fullmessagehtml' => '<p>HTML body</p>',
            'replyto' => '',
            'replytoname' => '',
        ];
    }

    /**
     * The email processor copy is branded for a tenant recipient.
     *
     * @return void
     */
    public function test_email_processor_branded_for_tenant(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/brandmask/lib.php');

        $user = $this->make_tenant_user([
            'replytoemail' => 'support@tenant.example.com', 'replytoname' => 'Tenant Support',
        ]);
        $e = $this->event($user);
        local_brandmask_pre_processor_message_send('email', $e);

        $this->assertStringStartsWith('[Tenant] ', $e->subject);
        $this->assertStringStartsWith('<!DOCTYPE html>', $e->fullmessagehtml);
        $this->assertStringContainsString('HTML body', $e->fullmessagehtml);
        $this->assertSame('Tenant Corp', $e->userfrom->firstname);
        $this->assertSame('support@tenant.example.com', $e->replyto);
        $this->assertSame('Tenant Support', $e->replytoname);
    }

    /**
     * A non-email processor copy is left untouched.
     *
     * @return void
     */
    public function test_non_email_processor_untouched(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/brandmask/lib.php');

        $user = $this->make_tenant_user();
        $e = $this->event($user);
        local_brandmask_pre_processor_message_send('popup', $e);

        $this->assertSame('A notification', $e->subject);
        $this->assertSame('<p>HTML body</p>', $e->fullmessagehtml);
    }

    /**
     * A recipient who is not a tenant member is left untouched.
     *
     * @return void
     */
    public function test_non_tenant_recipient_untouched(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/brandmask/lib.php');

        $outsider = $this->getDataGenerator()->create_user();
        $e = $this->event($outsider);
        local_brandmask_pre_processor_message_send('email', $e);

        $this->assertSame('A notification', $e->subject);
        $this->assertSame('<p>HTML body</p>', $e->fullmessagehtml);
    }

    /**
     * A message-specific reply-to (e.g. forum reply-by-email handler) is never clobbered.
     *
     * @return void
     */
    public function test_existing_replyto_preserved(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/brandmask/lib.php');

        $user = $this->make_tenant_user(['replytoemail' => 'support@tenant.example.com']);
        $e = $this->event($user);
        $e->replyto = 'reply+token@inbound.example.com';
        local_brandmask_pre_processor_message_send('email', $e);

        $this->assertSame('reply+token@inbound.example.com', $e->replyto);
    }
}
