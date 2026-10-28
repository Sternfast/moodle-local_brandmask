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
 * Turns a branding profile into a small, scoped, HARDENED <style> block for the page head.
 *
 * Design: clean white chrome + brand-colour accents + crisp logo lockup. One (transparent) logo
 * then reads correctly on the navbar, the login card and in email — no white-chip hack, no
 * logo-on-coloured-background contrast problem.
 *
 * Runtime CSS override (never SCSS) so nothing touches the compiled-theme cache. Colours are
 * allowlisted; URLs escaped for CSS url(); admin extra CSS constrained so it can't break out.
 *
 * @package    local_brandmask
 * @copyright  2026 Vbounds LLC (Sternfast)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class brander {
    /** Maximum size in bytes of admin-supplied extra CSS. */
    const EXTRACSS_MAX = 16384;

    /**
     * Build the scoped, hardened branding <style> block and related head tags.
     *
     * @param object $p Branding profile record.
     * @return string The HTML (style/link tags) to inject into the page head.
     */
    public static function head_html(object $p): string {
        $primary = self::safecolor($p->primarycolor ?? '');
        $accent  = self::safecolor($p->accentcolor ?? '') ?: $primary;
        $loginbg = self::safecolor($p->loginbgcolor ?? '');
        $loginbgurl = self::css_url($p->loginbgurl ?? '');
        $logos   = self::logo_urls($p);

        // Per-tenant VALUES only. The structural selectors live in styles.css, gated with
        // :has(#local-brandmask) so they take effect only when this block is present (i.e. for a
        // branded user). This head block injects just the custom properties plus the data-driven
        // logo lockup; an unbranded page has no such block, so it renders as stock Moodle.
        $vars = [];
        if ($primary) {
            $vars[] = '--bm-primary:' . $primary;
            $vars[] = '--bm-accent:' . $accent;
        }
        $bg = $loginbgurl ? "url('{$loginbgurl}') center center / cover no-repeat fixed" : ($loginbg ?: '#eef1f5');
        $vars[] = '--bm-loginbg:' . $bg;

        $css = ':root{' . implode(';', $vars) . ";}\n";

        // Logo lockup (navbar + login): the only mark Brand Mask leaves on the sign-in page. It is
        // data-driven (logo URLs, one- or two-logo layout) so it is generated here rather than in
        // styles.css. The host's own guest access, headings, footer and policy links are untouched.
        $css .= self::logo_css($logos);

        $out = "\n<!-- local_brandmask: " . s($p->brandkey) . " -->\n";
        if (trim($css) !== '') {
            $out .= '<style id="local-brandmask">' . "\n" . $css . "\n</style>\n";
        }
        if ($logos['primary'] !== '') {
            $out .= '<link rel="icon" href="' . s($logos['primary']) . '">' . "\n";
        }
        $extra = self::safe_extracss($p->extracss ?? '');
        if ($extra !== '') {
            $out .= '<style id="local-brandmask-extra">' . "\n" . $extra . "\n</style>\n";
        }
        return $out;
    }

    /**
     * Navbar lockup + login lockup. One logo, or two side-by-side with a hairline divider.
     * A fixed-width lockup block is left-aligned in the navbar and centred on the login card.
     *
     * @param array $logos The ['primary' => string, 'partner' => string] logo URLs.
     * @return string The scoped CSS for the logo lockup, or '' if there is no primary logo.
     */
    protected static function logo_css(array $logos): string {
        if ($logos['primary'] === '') {
            return '';
        }
        $a = addcslashes($logos['primary'], "'\"()\\\r\n");
        $b = $logos['partner'] !== '' ? addcslashes($logos['partner'], "'\"()\\\r\n") : '';

        if ($b !== '') {
            // Two logos with a hairline divider centred in the gap between them (a co-brand lockup).
            // Widths assume a roughly 2.3:1 logo; the gap is generous so logos never overlap.
            $nav   = "background-image:url('{$a}'),linear-gradient(#d4d9df,#d4d9df),url('{$b}');
background-position:left center,82px center,right center;
background-size:auto 30px,1px 24px,auto 30px;
background-repeat:no-repeat;";
            $navw  = '165px';
            $login = "background-image:url('{$a}'),linear-gradient(#d4d9df,#d4d9df),url('{$b}');
background-position:left center,125px center,right center;
background-size:auto 46px,1px 36px,auto 46px;
background-repeat:no-repeat;";
            $loginw = '250px';
            $loginh = '48px';
        } else {
            $nav    = "background-image:url('{$a}');background-position:left center;
background-size:auto 32px;background-repeat:no-repeat;";
            $navw   = '190px';
            $login  = "background-image:url('{$a}');background-position:center center;
background-size:auto 54px;background-repeat:no-repeat;";
            $loginw = '260px';
            $loginh = '56px';
        }

        return "
html:has(#local-brandmask) .navbar.fixed-top .navbar-brand {
{$nav} width:{$navw}; height:44px; font-size:0 !important; color:transparent !important; overflow:hidden; }
html:has(#local-brandmask) .navbar.fixed-top .navbar-brand img { opacity:0 !important; width:0 !important; height:0 !important; }
html:has(#local-brandmask) #page-login-index .loginform > h1.login-heading {
font-size:0 !important; line-height:0 !important; margin:2px auto 24px !important; }
html:has(#local-brandmask) #page-login-index .loginform > h1.login-heading::before {
content:''; display:block; margin:0 auto; width:{$loginw}; height:{$loginh}; {$login} }
";
    }

    /**
     * Allowlist-validate a colour value for safe interpolation into CSS.
     *
     * @param string|null $c Raw colour string from the profile.
     * @return string The colour if it matches an allowed form, else ''.
     */
    public static function safecolor(?string $c): string {
        $c = trim((string) $c);
        if ($c === '') {
            return '';
        }
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $c)) {
            return $c;
        }
        if (preg_match('/^(rgb|rgba|hsl|hsla)\(\s*[0-9.,%\s\/]+\)$/i', $c)) {
            return $c;
        }
        if (preg_match('/^[a-zA-Z]{3,20}$/', $c)) {
            return $c;
        }
        return '';
    }

    /**
     * Resolve the primary and partner logo URLs for a branding profile.
     *
     * @param object $p Branding profile record.
     * @return array The ['primary' => string, 'partner' => string] logo URLs.
     */
    public static function logo_urls(object $p): array {
        $out = ['primary' => '', 'partner' => ''];
        try {
            $fs = get_file_storage();
            $ctxid = \context_system::instance()->id;
            foreach (['logo' => 'primary', 'logopartner' => 'partner'] as $area => $key) {
                $files = $fs->get_area_files($ctxid, 'local_brandmask', $area, $p->id, 'itemid', false);
                if ($f = reset($files)) {
                    $out[$key] = \moodle_url::make_pluginfile_url(
                        $ctxid,
                        'local_brandmask',
                        $area,
                        $p->id,
                        $f->get_filepath(),
                        $f->get_filename()
                    )->out(false);
                }
            }
        } catch (\Throwable $e) {
            debugging('local_brandmask: logo lookup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        if ($out['primary'] === '' && !empty($p->logourl)) {
            $u = clean_param($p->logourl, PARAM_URL);
            if ($u !== '' && preg_match('~^(https://|/)~i', $u)) {
                $out['primary'] = $u;
            }
        }
        return $out;
    }

    /**
     * Validate and CSS-escape a URL for use inside a CSS url() value.
     *
     * @param string|null $url Raw URL string from the profile.
     * @return string The escaped URL, or '' if it is not allowed.
     */
    protected static function css_url(?string $url): string {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        $clean = clean_param($url, PARAM_URL);
        if ($clean === '' || !preg_match('~^(https://|/)~i', $clean)) {
            return '';
        }
        return addcslashes($clean, "'\"()\\\r\n");
    }

    /**
     * Sanitise and length-limit admin-supplied extra CSS.
     *
     * @param string|null $css Raw extra CSS from the admin.
     * @return string The constrained CSS, or '' if it is rejected.
     */
    protected static function safe_extracss(?string $css): string {
        $css = (string) $css;
        if (trim($css) === '') {
            return '';
        }
        if (preg_match('~</|<!--|-->|<style|<script|expression\s*\(|@import|javascript:~i', $css)) {
            return '';
        }
        if (\core_text::strlen($css) > self::EXTRACSS_MAX) {
            $css = \core_text::substr($css, 0, self::EXTRACSS_MAX);
        }
        return $css;
    }
}
