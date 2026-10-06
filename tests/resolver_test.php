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
 * Unit tests for the branding profile resolver.
 *
 * @package    local_brandmask
 * @copyright  2026 Vbounds LLC (Sternfast)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_brandmask;

use local_brandmask\local\resolver;

/**
 * Tests that targeting is cohort/user authoritative and leaves non-members untouched.
 *
 * @covers \local_brandmask\local\resolver
 */
final class resolver_test extends \advanced_testcase {
    /**
     * Insert a branding profile and return its id.
     *
     * @param string $key Brand key (also used to build the name).
     * @param int $enabled Whether the profile is enabled (1) or not (0).
     * @return int The new profile id.
     */
    protected function make_profile(string $key, int $enabled = 1): int {
        global $DB;
        return (int) $DB->insert_record('local_brandmask_profile', (object) [
            'brandkey' => $key,
            'name' => ucfirst($key) . ' tenant',
            'enabled' => $enabled,
        ]);
    }

    /**
     * Add a user to a cohort by inserting the membership row directly.
     *
     * @param int $cohortid The cohort id.
     * @param int $userid The user id.
     * @return void
     */
    protected function add_member(int $cohortid, int $userid): void {
        global $DB;
        $DB->insert_record('cohort_members', (object) [
            'cohortid' => $cohortid,
            'userid' => $userid,
            'timeadded' => time(),
        ]);
    }

    /**
     * A user in a profile's cohort resolves to that profile; a non-member resolves to nothing.
     *
     * @return void
     */
    public function test_cohort_membership_is_authoritative(): void {
        global $DB;
        $this->resetAfterTest();

        $member = $this->getDataGenerator()->create_user();
        $outsider = $this->getDataGenerator()->create_user();
        $cohort = $this->getDataGenerator()->create_cohort();
        $this->add_member($cohort->id, $member->id);

        $profileid = $this->make_profile('tenant');
        $DB->insert_record('local_brandmask_cohort', (object) [
            'profileid' => $profileid,
            'cohortid' => $cohort->id,
        ]);

        $resolved = resolver::for_user($member->id);
        $this->assertNotNull($resolved);
        $this->assertEquals($profileid, $resolved->id);
        $this->assertEquals('tenant', $resolved->brandkey);

        // The outsider is never masked.
        $this->assertNull(resolver::for_user($outsider->id));
    }

    /**
     * An explicit per-user assignment overrides the user's cohort assignment.
     *
     * @return void
     */
    public function test_user_assignment_overrides_cohort(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $cohort = $this->getDataGenerator()->create_cohort();
        $this->add_member($cohort->id, $user->id);

        $cohortprofile = $this->make_profile('viacohort');
        $userprofile = $this->make_profile('viauser');
        $DB->insert_record('local_brandmask_cohort', (object) [
            'profileid' => $cohortprofile,
            'cohortid' => $cohort->id,
        ]);
        $DB->insert_record('local_brandmask_user', (object) [
            'profileid' => $userprofile,
            'userid' => $user->id,
        ]);

        $resolved = resolver::for_user($user->id);
        $this->assertNotNull($resolved);
        $this->assertEquals($userprofile, $resolved->id);
        $this->assertEquals('viauser', $resolved->brandkey);
    }

    /**
     * A disabled profile is never resolved, even for a targeted member.
     *
     * @return void
     */
    public function test_disabled_profile_is_ignored(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $cohort = $this->getDataGenerator()->create_cohort();
        $this->add_member($cohort->id, $user->id);

        $profileid = $this->make_profile('disabled', 0);
        $DB->insert_record('local_brandmask_cohort', (object) [
            'profileid' => $profileid,
            'cohortid' => $cohort->id,
        ]);

        $this->assertNull(resolver::for_user($user->id));
    }

    /**
     * Invalid user ids resolve to nothing rather than erroring.
     *
     * @return void
     */
    public function test_invalid_userid_resolves_to_null(): void {
        $this->resetAfterTest();
        $this->assertNull(resolver::for_user(0));
        $this->assertNull(resolver::for_user(-5));
    }

    /**
     * Pre-login subdomain branding honours X-Forwarded-Host only from a trusted TCP peer, not a
     * client that merely sends the header (spoof protection).
     *
     * @return void
     */
    public function test_front_door_trusts_peer_not_client(): void {
        global $CFG, $DB;
        $this->resetAfterTest();

        $DB->insert_record('local_brandmask_profile', (object) [
            'brandkey' => 'tenant',
            'name' => 'Tenant',
            'enabled' => 1,
            'hostname' => 'tenant.example.com',
        ]);

        $CFG->reverseproxy = true;
        set_config('trustforwardedhost', 1, 'local_brandmask');
        set_config('trustedproxies', "127.0.0.1\n::1", 'local_brandmask');

        $origserver = $_SERVER;
        $_SERVER['HTTP_HOST'] = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_HOST'] = 'tenant.example.com';

        // Trusted peer: the forwarded host is honoured, so the subdomain brands.
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        resolver::reset();
        $trusted = resolver::current();
        $this->assertNotNull($trusted);
        $this->assertEquals('tenant', $trusted->brandkey);

        // Untrusted peer sending the same header: ignored (no spoofed branding).
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        resolver::reset();
        $this->assertNull(resolver::current());

        $_SERVER = $origserver;
        resolver::reset();
    }

    /**
     * With a trusted peer, the RIGHTMOST X-Forwarded-Host (the proxy-stamped value) is used, so a
     * client-prepended host cannot force a different tenant (Apache mod_proxy appends).
     *
     * @return void
     */
    public function test_front_door_uses_rightmost_forwarded_host(): void {
        global $CFG, $DB;
        $this->resetAfterTest();

        $DB->insert_record('local_brandmask_profile', (object) [
            'brandkey' => 'tenant', 'name' => 'Tenant', 'enabled' => 1, 'hostname' => 'tenant.example.com',
        ]);

        $CFG->reverseproxy = true;
        set_config('trustforwardedhost', 1, 'local_brandmask');
        set_config('trustedproxies', "127.0.0.1", 'local_brandmask');

        $origserver = $_SERVER;
        $_SERVER['HTTP_HOST'] = '127.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        // Attacker prepends evil.example.com; the trusted proxy appends the real host.
        $_SERVER['HTTP_X_FORWARDED_HOST'] = 'evil.example.com, tenant.example.com';

        resolver::reset();
        $resolved = resolver::current();
        $this->assertNotNull($resolved);
        $this->assertEquals('tenant', $resolved->brandkey);

        $_SERVER = $origserver;
        resolver::reset();
    }

    /**
     * ?brand=off forces the stock, unbranded page even on a branded host.
     *
     * @return void
     */
    public function test_brand_off_suppresses_branding(): void {
        global $CFG, $DB;
        $this->resetAfterTest();

        $DB->insert_record('local_brandmask_profile', (object) [
            'brandkey' => 'tenant', 'name' => 'Tenant', 'enabled' => 1, 'hostname' => 'tenant.example.com',
        ]);

        $CFG->reverseproxy = true;
        set_config('trustforwardedhost', 1, 'local_brandmask');
        set_config('trustedproxies', "127.0.0.1", 'local_brandmask');

        $origserver = $_SERVER;
        $origpost = $_POST;
        $_SERVER['HTTP_HOST'] = '127.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_HOST'] = 'tenant.example.com';

        // Sanity: without the opt-out the host would brand.
        resolver::reset();
        $this->assertNotNull(resolver::current());

        // With ?brand=off it must not — and the sentinel is case-insensitive.
        $_POST['brand'] = 'off';
        resolver::reset();
        $this->assertNull(resolver::current());

        $_POST['brand'] = 'OFF';
        resolver::reset();
        $this->assertNull(resolver::current());

        $_SERVER = $origserver;
        $_POST = $origpost;
        resolver::reset();
    }

    /**
     * Two enabled profiles sharing a hostname resolve deterministically to the lowest id.
     *
     * @return void
     */
    public function test_duplicate_hostname_lowest_id_wins(): void {
        global $CFG, $DB;
        $this->resetAfterTest();

        $first = $DB->insert_record('local_brandmask_profile', (object) [
            'brandkey' => 'tenant1', 'name' => 'Tenant One', 'enabled' => 1, 'hostname' => 'shared.example.com',
        ]);
        $DB->insert_record('local_brandmask_profile', (object) [
            'brandkey' => 'tenant2', 'name' => 'Tenant Two', 'enabled' => 1, 'hostname' => 'shared.example.com',
        ]);

        $CFG->reverseproxy = true;
        set_config('trustforwardedhost', 1, 'local_brandmask');
        set_config('trustedproxies', "127.0.0.1", 'local_brandmask');

        $origserver = $_SERVER;
        $_SERVER['HTTP_HOST'] = '127.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_HOST'] = 'shared.example.com';

        resolver::reset();
        $resolved = resolver::current();
        $this->assertNotNull($resolved);
        $this->assertEquals($first, $resolved->id);

        $_SERVER = $origserver;
        resolver::reset();
    }

    /**
     * normalize_host() lowercases and strips a port and a trailing dot.
     *
     * @return void
     */
    public function test_normalize_host(): void {
        $this->assertSame('tenant.example.com', resolver::normalize_host('Tenant.Example.COM'));
        $this->assertSame('tenant.example.com', resolver::normalize_host('tenant.example.com:443'));
        $this->assertSame('tenant.example.com', resolver::normalize_host('tenant.example.com.'));
        $this->assertSame('tenant.example.com', resolver::normalize_host('  TENANT.example.com.:8080 '));
        $this->assertSame('', resolver::normalize_host('   '));
    }

    /**
     * canonical_host() accepts a valid host (folding trailing dot/port/case) and rejects invalid input.
     *
     * @return void
     */
    public function test_canonical_host(): void {
        $this->assertSame('learn.theircollege.edu', resolver::canonical_host('Learn.TheirCollege.edu.'));
        $this->assertSame('theircollege.edu', resolver::canonical_host('theircollege.edu'));
        $this->assertSame('', resolver::canonical_host(''));
        $this->assertSame('', resolver::canonical_host('http://x/y'));
        $this->assertSame('', resolver::canonical_host('not a host'));
    }

    /**
     * A request host with a trailing dot (FQDN form) still matches a stored dot-free hostname.
     *
     * @return void
     */
    public function test_trailing_dot_host_matches(): void {
        $this->resetAfterTest();
        global $CFG, $DB;

        $id = $DB->insert_record('local_brandmask_profile', (object) [
            'brandkey' => 'tenant', 'name' => 'Tenant', 'enabled' => 1, 'hostname' => 'tenant.example.com',
        ]);

        $CFG->reverseproxy = true;
        set_config('trustforwardedhost', 1, 'local_brandmask');
        set_config('trustedproxies', "127.0.0.1", 'local_brandmask');

        $origserver = $_SERVER;
        $_SERVER['HTTP_HOST'] = '127.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_HOST'] = 'tenant.example.com.';

        resolver::reset();
        $resolved = resolver::current();
        $this->assertNotNull($resolved);
        $this->assertEquals($id, $resolved->id);

        $_SERVER = $origserver;
        resolver::reset();
    }
}
