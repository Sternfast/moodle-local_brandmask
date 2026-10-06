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
 * Unit tests for the DNS go-live guidance generator.
 *
 * @package    local_brandmask
 * @copyright  2026 Vbounds LLC (Sternfast)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_brandmask;

use local_brandmask\local\dnsguide;

/**
 * Tests that the DNS record generator produces the expected subdomain and email records.
 *
 * @covers \local_brandmask\local\dnsguide
 */
final class dnsguide_test extends \advanced_testcase {
    /**
     * With a configured server IP, the subdomain guidance is a single A record to that IP.
     *
     * @return void
     */
    public function test_subdomain_records_with_server_ip(): void {
        $this->resetAfterTest();
        set_config('serverip', '203.0.113.10', 'local_brandmask');

        $records = dnsguide::subdomain_records('tenant.example.com');
        $this->assertCount(1, $records);
        $this->assertSame('A', $records[0]['type']);
        $this->assertSame('tenant.example.com', $records[0]['host']);
        $this->assertSame('203.0.113.10', $records[0]['value']);
    }

    /**
     * Without a server IP, both an A placeholder and a CNAME alternative are offered.
     *
     * @return void
     */
    public function test_subdomain_records_without_server_ip(): void {
        $this->resetAfterTest();
        set_config('serverip', '', 'local_brandmask');

        $records = dnsguide::subdomain_records('tenant.example.com');
        $types = array_column($records, 'type');
        $this->assertContains('A', $types);
        $this->assertContains('CNAME', $types);
    }

    /**
     * An empty hostname yields no subdomain records.
     *
     * @return void
     */
    public function test_subdomain_records_empty_hostname(): void {
        $this->resetAfterTest();
        $this->assertSame([], dnsguide::subdomain_records(''));
    }

    /**
     * A custom sender address yields SPF, DKIM and DMARC records keyed to its domain.
     *
     * @return void
     */
    public function test_email_records_for_domain(): void {
        $this->resetAfterTest();
        $guide = dnsguide::email_records('noreply@tenant.example.com');
        $this->assertSame('tenant.example.com', $guide['domain']);
        $this->assertCount(3, $guide['records']);

        $values = implode(' ', array_column($guide['records'], 'value'));
        $this->assertStringContainsString('v=spf1', $values);
        $this->assertStringContainsString('v=DMARC1', $values);

        $hosts = implode(' ', array_column($guide['records'], 'host'));
        $this->assertStringContainsString('_dmarc.tenant.example.com', $hosts);
        $this->assertStringContainsString('_domainkey.tenant.example.com', $hosts);
    }

    /**
     * A value that is not an email address yields no email records.
     *
     * @return void
     */
    public function test_email_records_no_domain(): void {
        $this->resetAfterTest();
        $guide = dnsguide::email_records('not-an-address');
        $this->assertSame('', $guide['domain']);
        $this->assertSame([], $guide['records']);
    }
}
