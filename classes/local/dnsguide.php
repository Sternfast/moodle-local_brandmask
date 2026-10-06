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
 * Generates the DNS records an admin must add to take a tenant subdomain and custom email live.
 *
 * @package    local_brandmask
 * @copyright  2026 Vbounds LLC (Sternfast)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_brandmask\local;

/**
 * Pure helpers that turn a branding profile into copy-paste DNS guidance.
 */
class dnsguide {
    /**
     * DNS record(s) to point a tenant hostname at this Moodle.
     *
     * @param string $hostname The tenant subdomain (e.g. tenant.example.com).
     * @return array List of records, each ['type', 'host', 'value'].
     */
    public static function subdomain_records(string $hostname): array {
        global $CFG;
        $hostname = \core_text::strtolower(trim($hostname));
        if ($hostname === '') {
            return [];
        }
        $ip = trim((string) get_config('local_brandmask', 'serverip'));
        if ($ip !== '') {
            return [[
                'type' => 'A',
                'host' => $hostname,
                'value' => $ip,
                'note' => 'Point this host at your Moodle server. For a client\'s own domain, they add this record; '
                    . 'it works for a sub-host (learn.theirdomain.com) or a root/apex domain.',
            ]];
        }
        // No server IP configured: offer an A-record placeholder and a CNAME alternative.
        $wwwhost = (string) parse_url($CFG->wwwroot, PHP_URL_HOST);
        return [
            [
                'type' => 'A',
                'host' => $hostname,
                'value' => '<your Moodle server public IP>',
                'note' => 'Works for any host, including a root/apex domain.',
            ],
            [
                'type' => 'CNAME',
                'host' => $hostname,
                'value' => $wwwhost,
                'note' => 'Alternative for a sub-host only (e.g. learn.theirdomain.com) — not for a root/apex domain.',
            ],
        ];
    }

    /**
     * Email-authentication DNS records for sending as a custom domain.
     *
     * @param string $fromemail The tenant sender address (e.g. noreply@tenant.example.com).
     * @return array ['domain' => string, 'records' => array of ['type','host','value','note']].
     */
    public static function email_records(string $fromemail): array {
        $domain = '';
        if (strpos((string) $fromemail, '@') !== false) {
            $domain = \core_text::strtolower(trim((string) substr(strrchr($fromemail, '@'), 1)));
        }
        if ($domain === '') {
            return ['domain' => '', 'records' => []];
        }
        $records = [
            [
                'type' => 'TXT',
                'host' => $domain,
                'value' => 'v=spf1 include:YOUR-MAIL-HOST ~all',
                'note' => 'SPF — replace YOUR-MAIL-HOST with the server or relay that sends your Moodle email.',
            ],
            [
                'type' => 'TXT',
                'host' => 'SELECTOR._domainkey.' . $domain,
                'value' => '<DKIM public key from your mail host>',
                'note' => 'DKIM — your mail server or provider generates the selector + key; publish exactly what it gives you.',
            ],
            [
                'type' => 'TXT',
                'host' => '_dmarc.' . $domain,
                'value' => 'v=DMARC1; p=none; rua=mailto:dmarc@' . $domain,
                'note' => 'DMARC — start with p=none to monitor, then tighten to quarantine or reject.',
            ],
        ];
        return ['domain' => $domain, 'records' => $records];
    }

    /**
     * The IP a hostname currently resolves to, or '' if it does not resolve.
     *
     * @param string $hostname The hostname to look up.
     * @return string The resolved IPv4 address, or ''.
     */
    public static function resolves_to(string $hostname): string {
        $hostname = rtrim(\core_text::strtolower(trim($hostname)), '.');
        if ($hostname === '') {
            return '';
        }
        // Cache the result briefly: gethostbyname() can block for seconds when a nameserver is slow
        // or unreachable, and the Go-live page would otherwise re-run it on every reload.
        $cache = \cache::make('local_brandmask', 'dnsresolve');
        $key = sha1($hostname);
        $cached = $cache->get($key);
        if ($cached !== false) {
            return $cached;
        }
        $ip = gethostbyname($hostname);
        // The gethostbyname() call returns the input unchanged when it cannot resolve.
        $ip = ($ip !== $hostname) ? $ip : '';
        $cache->set($key, $ip);
        return $ip;
    }
}
