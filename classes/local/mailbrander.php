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

namespace local_brandmask\local;

/**
 * Applies a tenant's branding to an outgoing email: a branded, email-safe HTML shell
 * (logo(s) + colours + footer), subdomain link rewriting, subject prefix, and a sender
 * identity. Pure/testable — the pre_processor_message_send hook (lib.php) calls this per recipient.
 *
 * @package    local_brandmask
 * @copyright  2026 Vbounds LLC (Sternfast)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mailbrander {
    /**
     * Brand one email for one recipient.
     *
     * @param object $profile The tenant branding profile.
     * @param string $subject The original message subject.
     * @param string $html The original HTML body.
     * @param string $text The original plain-text body.
     * @param object|null $userfrom The original sender, if any.
     * @return array {subject, html, text, userfrom, replytoemail, replytoname}
     */
    public static function brand(
        object $profile,
        string $subject,
        string $html,
        string $text,
        ?object $userfrom = null
    ): array {
        global $CFG;

        // Rewrite canonical links to the tenant subdomain (only meaningful if it serves the site).
        // Boundary-anchored so a host that merely has wwwroot as a string prefix (e.g.
        // "<wwwroot>.evil.com" or "<wwwroot>xyz") is never rewritten — only real link boundaries.
        if (!empty($profile->hostname)) {
            $sub = 'https://' . $profile->hostname;
            $re = '~' . preg_quote($CFG->wwwroot, '~') . '(?=[/"\'\s<>)\]]|$)~';
            $subject = preg_replace($re, $sub, $subject);
            $html    = preg_replace($re, $sub, $html);
            $text    = preg_replace($re, $sub, $text);
        }

        // Only prefix when there is a non-whitespace prefix (avoids a stray leading space).
        $prefix = trim((string) ($profile->emailsubjectprefix ?? ''));
        if ($prefix !== '') {
            $subject = $prefix . ' ' . $subject;
        }

        // If the message had no HTML part, build one from the text so the branded shell is not an
        // empty frame; if there is nothing at all, leave HTML empty so core's own text->HTML
        // fallback in email_to_user() still runs.
        if (trim($html) === '') {
            $html = ($text !== '') ? text_to_html($text) : '';
        }

        // Wrap AFTER the rewrite, so the shell's own logo URL stays on the canonical host
        // (reliable mail-client loading). A rare provider-supplied FULL HTML document is left
        // intact rather than nested inside the shell.
        if ($html !== '' && !self::is_full_document($html)) {
            $html = self::shell($profile, $html);
        }

        return [
            'subject'      => $subject,
            'html'         => $html,
            'text'         => $text,
            'userfrom'     => self::sender($profile, $userfrom),
            'replytoemail' => self::safe_email($profile->replytoemail ?? ''),
            'replytoname'  => trim((string) ($profile->replytoname ?? '')),
        ];
    }

    /**
     * The From user object. SAFE DEFAULT: keep the incoming sender ADDRESS (so core's own
     * SPF/DKIM alignment and privacy gating still apply), but show the tenant's DISPLAY NAME.
     * Only override the address — and only then expose it via maildisplay — when the admin opted
     * into "send as" AND gave a valid address (core still gatekeeps it via allowedemaildomains).
     *
     * @param object $profile Branding profile record.
     * @param object|null $userfrom The original message sender, if any.
     * @return object The From user object to send as.
     */
    protected static function sender(object $profile, ?object $userfrom): object {
        $from = $userfrom ? clone $userfrom : \core_user::get_noreply_user();

        if (!empty($profile->fromname)) {
            // Display-name only: fullname() is built from firstname/lastname, NOT maildisplay, so
            // we never touch the original sender's maildisplay here (honouring a Hidden address).
            $from->firstname = $profile->fromname;
            $from->lastname  = '';
        }
        if (!empty($profile->emailsendas) && !empty($profile->fromemail)) {
            $addr = self::safe_email($profile->fromemail);
            if ($addr !== '') {
                // Admin deliberately sends as the tenant's own address — exposing THAT address is
                // intended; it is not the original sender's private address.
                $from->email = $addr;
                $from->maildisplay = defined('\\core_user::MAILDISPLAY_EVERYONE')
                    ? \core_user::MAILDISPLAY_EVERYONE : 1;
            }
        }
        return $from;
    }

    /**
     * The branded, email-safe HTML shell: inline styles, tables, 600px, https logo + text fallback.
     *
     * @param object $profile Branding profile record.
     * @param string $bodyhtml The original email body HTML to wrap.
     * @return string The full branded HTML document.
     */
    protected static function shell(object $profile, string $bodyhtml): string {
        global $SITE, $CFG;

        // Accept the SAME colour formats as the on-page branding (hex/rgb/hsl/named), not hex-only,
        // so the email header matches the site instead of falling back to navy.
        $primary = brander::safecolor($profile->primarycolor ?? '') ?: '#1f3a5f';
        $name    = s($profile->fromname ?: $profile->name ?: ($SITE->fullname ?? ''));
        $logos   = brander::logo_urls($profile);
        // Email has no document base URL, so make any root-relative logo URL absolute.
        foreach (['primary', 'partner'] as $logokey) {
            if ($logos[$logokey] !== '' && strpos($logos[$logokey], '/') === 0) {
                $logos[$logokey] = $CFG->wwwroot . $logos[$logokey];
            }
        }
        $footer  = $profile->emailfooter ?? '';

        // Header: logo(s) on the brand colour, or the tenant name if no logo.
        $header = '';
        if ($logos['primary'] !== '') {
            $header .= '<img src="' . s($logos['primary']) . '" alt="' . $name
                . '" height="34" style="height:34px;vertical-align:middle;border:0;">';
            if ($logos['partner'] !== '') {
                $header .= '<span style="display:inline-block;width:1px;height:28px;'
                    . 'background:rgba(255,255,255,.5);margin:0 14px;vertical-align:middle;"></span>'
                    . '<img src="' . s($logos['partner'])
                    . '" alt="" height="34" style="height:34px;vertical-align:middle;border:0;">';
            }
        } else {
            $header = '<span style="color:#ffffff;font:700 18px Arial,Helvetica,sans-serif;">' . $name . '</span>';
        }

        $footerhtml = '';
        if (trim((string) $footer) !== '') {
            $footerhtml = '<tr><td style="padding:16px 28px;color:#8a8a8a;'
                . 'font:12px/1.5 Arial,Helvetica,sans-serif;border-top:1px solid #ededed;">'
                . nl2br(s($footer)) . '</td></tr>';
        }

        return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f4f5f7;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
                . 'style="background:#f4f5f7;padding:24px 0;"><tr><td align="center">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" '
                . 'style="width:600px;max-width:600px;background:#ffffff;'
                . 'border-radius:10px;overflow:hidden;border:1px solid #e6e8eb;">'
            . '<tr><td style="background:' . $primary . ';padding:18px 28px;">' . $header . '</td></tr>'
            . '<tr><td style="padding:26px 28px;color:#232a31;font:15px/1.6 Arial,Helvetica,sans-serif;">'
            . $bodyhtml . '</td></tr>'
            . $footerhtml
            . '</table></td></tr></table></body></html>';
    }

    /**
     * Whether the HTML is already a complete document (so the shell must not wrap it again).
     *
     * @param string $html The candidate HTML.
     * @return bool True if it begins with a doctype or an <html> tag.
     */
    protected static function is_full_document(string $html): bool {
        return (bool) preg_match('~^\s*(<!doctype|<html[\s>])~i', $html);
    }

    /**
     * Validate an email address.
     *
     * @param string|null $e Raw email string from the profile.
     * @return string The email if valid, else ''.
     */
    protected static function safe_email(?string $e): string {
        $e = trim((string) $e);
        return ($e !== '' && validate_email($e)) ? $e : '';
    }
}
