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
 * Resolves which branding profile applies to the current request or a given user.
 *
 * @package    local_brandmask
 * @copyright  2026 Vbounds LLC (Sternfast)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_brandmask\local;

/**
 * Resolves which branding profile (if any) applies.
 *
 * Targeting: a profile is OWNED by its cohort(s)/user(s). Only targeted members are masked;
 * everyone else gets the stock theme (the plugin injects nothing for them).
 *
 *   LOGGED-IN  -> the user's own assignment / cohort is AUTHORITATIVE. Non-members get nothing.
 *                ?brand=/Host are ignored, so no one can self-apply or cross-contaminate.
 *                (Site admins may use ?brand=KEY to preview.)
 *   LOGGED-OUT -> the sign-in "front door": ?brand=KEY (this request only — no sticky session,
 *                which would leak across logout/kiosks) -> trusted forwarded host (reverse proxy)
 *                -> direct Host.
 * @package local_brandmask
 */
class resolver {
    /** @var object|null|false  Per-request cache for current(); false = not resolved yet. */
    protected static $resolved = false;

    /**
     * The brand for the CURRENT request/user (request-scoped cache).
     */
    public static function current(): ?object {
        global $DB, $USER;

        if (self::$resolved !== false) {
            return self::$resolved;
        }
        self::$resolved = null;

        if (!self::usable()) {
            return null;
        }

        $loggedin = isloggedin() && !isguestuser();

        // Explicit ?brand=KEY: a deliberate link, or an admin preview. Not sticky.
        $key = optional_param('brand', '', PARAM_ALPHANUMEXT);
        if (\core_text::strtolower($key) === 'off') {
            // Explicit per-request opt-out (case-insensitive): force the stock, unbranded page.
            return self::$resolved = null;
        }
        if ($key !== '') {
            $p = $DB->get_record('local_brandmask_profile', ['brandkey' => $key, 'enabled' => 1]);
            if ($p && (!$loggedin || is_siteadmin())) {
                return self::$resolved = $p;
            }
        }

        // Logged-in: membership decides, full stop.
        if ($loggedin) {
            return self::$resolved = self::by_membership((int) $USER->id);
        }

        // Logged-out front door: trusted forwarded host, then direct host. Lowest-id wins
        // deterministically if an admin has (mis)configured two enabled profiles on one host.
        $host = self::front_door_host();
        if ($host !== '') {
            $p = $DB->get_record_sql(
                'SELECT * FROM {local_brandmask_profile} WHERE hostname = :h AND enabled = 1 ORDER BY id ASC',
                ['h' => $host],
                IGNORE_MULTIPLE
            );
            if ($p) {
                return self::$resolved = $p;
            }
        }

        return self::$resolved;
    }

    /**
     * The brand for a SPECIFIC user, bypassing the request-scoped cache and any request inputs.
     * This is what the (future) email layer calls per-recipient — current()'s static must never
     * leak one recipient's brand to the next.
     */
    public static function for_user(int $userid): ?object {
        if (!self::usable() || $userid <= 0) {
            return null;
        }
        return self::by_membership($userid);
    }

    /**
     * Profile for a user: an explicit per-user assignment wins, else cohort membership.
     * Ties broken by lowest profile id (deterministic).
     */
    protected static function by_membership(int $userid): ?object {
        global $DB;

        $sqluser = "SELECT p.*
                      FROM {local_brandmask_profile} p
                      JOIN {local_brandmask_user} bu ON bu.profileid = p.id
                     WHERE bu.userid = :userid AND p.enabled = 1
                  ORDER BY p.id ASC";
        if ($rec = $DB->get_record_sql($sqluser, ['userid' => $userid], IGNORE_MULTIPLE)) {
            return $rec;
        }

        $sqlcohort = "SELECT p.*
                        FROM {local_brandmask_profile} p
                        JOIN {local_brandmask_cohort} bc ON bc.profileid = p.id
                        JOIN {cohort_members} cm ON cm.cohortid = bc.cohortid
                       WHERE cm.userid = :userid AND p.enabled = 1
                    ORDER BY p.id ASC";
        $rec = $DB->get_record_sql($sqlcohort, ['userid' => $userid], IGNORE_MULTIPLE);
        return $rec ?: null;
    }

    /**
     * The host to brand the pre-login page by: a trusted forwarded host if a trusted reverse
     * proxy is in front, else the direct Host. Returns '' if none.
     *
     * X-Forwarded-Host is only honoured when the plugin is told to, Moodle is in reverse-proxy
     * mode, AND the immediate peer is in the admin's trusted-proxy allowlist — otherwise the
     * header is attacker-controlled (login-page brand spoofing / phishing).
     */
    protected static function front_door_host(): string {
        global $CFG;

        if (
            get_config('local_brandmask', 'trustforwardedhost')
                && !empty($CFG->reverseproxy)
        ) {
            $trusted = (string) get_config('local_brandmask', 'trustedproxies');
            // Gate on the IMMEDIATE TCP peer (the proxy), not getremoteaddr(): behind a proxy the
            // latter resolves to the end client (via X-Forwarded-For), which can never be allowlisted
            // for a public login page and is itself attacker-controlled.
            $peer = $_SERVER['REMOTE_ADDR'] ?? '';
            if ($trusted !== '' && $peer !== '' && \core\ip_utils::is_ip_in_subnet_list($peer, $trusted, "\n")) {
                $headername = get_config('local_brandmask', 'forwardedhostheader');
                $headername = ($headername !== false && $headername !== '') ? $headername : 'X-Forwarded-Host';
                $serverkey = 'HTTP_' . strtoupper(str_replace('-', '_', $headername));
                if (!empty($_SERVER[$serverkey])) {
                    // Take the RIGHTMOST segment — the one stamped by the immediate trusted proxy.
                    // Apache mod_proxy APPENDS the real host to any client-supplied value, so the
                    // leftmost segment is attacker-controlled; nginx replaces, so there is only one.
                    // Mirrors core getremoteaddr()'s end() handling of appended X-Forwarded-For.
                    $parts = explode(',', $_SERVER[$serverkey]);
                    $h = self::normalize_host((string) end($parts));
                    if ($h !== '') {
                        return $h;
                    }
                }
            }
        }

        if (!empty($_SERVER['HTTP_HOST'])) {
            return self::normalize_host($_SERVER['HTTP_HOST']);
        }
        return '';
    }

    /**
     * Canonicalise a host for matching and storage: lowercase, no port, no trailing dot.
     *
     * @param string $host Raw host from a header, HTTP_HOST or admin input.
     * @return string The normalised host (may be empty).
     */
    public static function normalize_host(string $host): string {
        $host = \core_text::strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host);
        return rtrim($host, '.');
    }

    /**
     * Turn raw admin-entered host input into the canonical host to store and match, or '' when it
     * is not a valid host. An internationalised (Unicode) domain is converted to ASCII punycode,
     * since that is the form a browser puts in the Host header.
     *
     * @param string $raw The raw submitted host.
     * @return string The canonical ASCII host, or '' if invalid.
     */
    public static function canonical_host(string $raw): string {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        // Convert a Unicode domain (café.example.com) to its ASCII punycode (xn--caf-dma...).
        if (function_exists('idn_to_ascii') && preg_match('/[^\x00-\x7F]/', $raw)) {
            $ascii = idn_to_ascii($raw);
            if ($ascii !== false) {
                $raw = $ascii;
            }
        }
        $host = self::normalize_host($raw);
        return (clean_param($host, PARAM_HOST) === $host) ? $host : '';
    }

    /**
     * Safe to run? Not during install/upgrade, and the tables must exist.
     */
    protected static function usable(): bool {
        global $CFG, $DB;
        if (during_initial_install() || !empty($CFG->upgraderunning)) {
            return false;
        }
        try {
            return $DB->get_manager()->table_exists('local_brandmask_profile');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Reset the per-request cache (tests).
     */
    public static function reset(): void {
        self::$resolved = false;
    }
}
