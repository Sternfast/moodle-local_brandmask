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
 * Unit tests for the head-HTML / CSS branding builder and its sanitisers.
 *
 * @package    local_brandmask
 * @copyright  2026 Vbounds LLC (Sternfast)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_brandmask;

use local_brandmask\local\brander;

/**
 * Tests that valid branding is applied and that hostile colour / CSS input is neutralised.
 *
 * @covers \local_brandmask\local\brander
 */
final class brander_test extends \advanced_testcase {
    /**
     * Build a lightweight profile object for the head-HTML builder.
     *
     * @param array $overrides Field values to set on the profile.
     * @return object The profile object.
     */
    protected function profile(array $overrides = []): object {
        return (object) array_merge([
            'id' => 1,
            'brandkey' => 'tenant',
            'primarycolor' => '#c62828',
        ], $overrides);
    }

    /**
     * A valid primary colour is emitted into the injected CSS.
     *
     * @return void
     */
    public function test_valid_primary_colour_is_applied(): void {
        $out = brander::head_html($this->profile(['primarycolor' => '#c62828']));
        $this->assertStringContainsString('#c62828', $out);
        $this->assertStringContainsString('<style id="local-brandmask">', $out);
    }

    /**
     * A colour value carrying a CSS break-out payload is rejected, not emitted.
     *
     * @return void
     */
    public function test_malicious_colour_is_rejected(): void {
        $payload = 'red; } html { display:none } .x{';
        $out = brander::head_html($this->profile(['primarycolor' => $payload]));
        $this->assertStringNotContainsString('html { display:none }', $out);
        $this->assertStringNotContainsString($payload, $out);
    }

    /**
     * Dangerous extra CSS (script / import) is dropped entirely.
     *
     * @return void
     */
    public function test_dangerous_extracss_is_dropped(): void {
        $out = brander::head_html($this->profile([
            'extracss' => '</style><script>alert(1)</script>',
        ]));
        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('local-brandmask-extra', $out);

        $out2 = brander::head_html($this->profile(['extracss' => '@import url(https://evil.test/x.css);']));
        $this->assertStringNotContainsString('@import', $out2);
    }

    /**
     * Safe extra CSS is injected inside its own scoped style block.
     *
     * @return void
     */
    public function test_safe_extracss_is_injected(): void {
        $css = '.region-main{background:#fafafa;}';
        $out = brander::head_html($this->profile(['extracss' => $css]));
        $this->assertStringContainsString('<style id="local-brandmask-extra">', $out);
        $this->assertStringContainsString($css, $out);
    }

    /**
     * Brand Mask leaves the host's sign-in untouched apart from logo and colour: it never hides
     * the guest-access button, the login divider or the host headings.
     *
     * @return void
     */
    public function test_host_signin_controls_are_left_alone(): void {
        $out = brander::head_html($this->profile());
        $this->assertStringNotContainsString('#loginguestbtn', $out);
        $this->assertStringNotContainsString('.login-divider', $out);
        $this->assertStringNotContainsString('h2.login-heading', $out);
    }

    /**
     * With no uploaded files, a plain logo URL is used as the favicon and lockup image.
     *
     * @return void
     */
    public function test_logourl_fallback_is_used(): void {
        $url = 'https://cdn.example.com/tenant-logo.png';
        $out = brander::head_html($this->profile(['logourl' => $url]));
        $this->assertStringContainsString($url, $out);
        $this->assertStringContainsString('<link rel="icon"', $out);
    }

    /**
     * A non-https, non-root logo URL is not trusted.
     *
     * @return void
     */
    public function test_insecure_logourl_is_rejected(): void {
        $out = brander::head_html($this->profile(['logourl' => 'http://insecure.example.com/x.png']));
        $this->assertStringNotContainsString('insecure.example.com', $out);
    }
}
