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
 * Unit tests for the per-tenant email branding builder.
 *
 * @package    local_brandmask
 * @copyright  2026 Vbounds LLC (Sternfast)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_brandmask;

use local_brandmask\local\mailbrander;

/**
 * Tests subject prefixing, subdomain link rewriting, the HTML shell, sender and reply-to.
 *
 * @covers \local_brandmask\local\mailbrander
 */
final class mailbrander_test extends \advanced_testcase {
    /**
     * Build an email-branding profile object.
     *
     * @param array $overrides Field values to set on the profile.
     * @return object The profile object.
     */
    protected function profile(array $overrides = []): object {
        return (object) array_merge([
            'id' => 1,
            'name' => 'Riverton University',
            'brandkey' => 'riverton',
            'primarycolor' => '#123abc',
            'hostname' => 'riverton.example.com',
            'fromname' => 'Riverton University',
            'emailsubjectprefix' => '[Riverton]',
            'emailenabled' => 1,
        ], $overrides);
    }

    /**
     * The subject gets the prefix and links are rewritten to the tenant subdomain.
     *
     * @return void
     */
    public function test_subject_prefix_and_link_rewrite(): void {
        global $CFG;
        $this->resetAfterTest();

        $link = $CFG->wwwroot . '/mod/forum/discuss.php?d=12';
        $result = mailbrander::brand(
            $this->profile(),
            'New announcement',
            '<p>See <a href="' . $link . '">the post</a>.</p>',
            'See the post: ' . $link,
            null
        );

        $this->assertSame('[Riverton] New announcement', $result['subject']);
        $this->assertStringContainsString('https://riverton.example.com/mod/forum/discuss.php?d=12', $result['html']);
        $this->assertStringContainsString('https://riverton.example.com/mod/forum/discuss.php?d=12', $result['text']);
        $this->assertStringNotContainsString($CFG->wwwroot . '/mod/forum', $result['html']);
    }

    /**
     * The HTML body is wrapped in the branded, email-safe shell using the brand colour.
     *
     * @return void
     */
    public function test_html_shell_wraps_body(): void {
        $this->resetAfterTest();
        $result = mailbrander::brand($this->profile(), 'Hi', '<p>UNIQUEBODY123</p>', 'Hi', null);
        $this->assertStringStartsWith('<!DOCTYPE html>', $result['html']);
        $this->assertStringContainsString('UNIQUEBODY123', $result['html']);
        $this->assertStringContainsString('#123abc', $result['html']);
    }

    /**
     * The sender shows the tenant display name but keeps the aligned no-reply address by default.
     *
     * @return void
     */
    public function test_sender_name_shown_address_kept(): void {
        $this->resetAfterTest();
        $noreply = \core_user::get_noreply_user();
        $result = mailbrander::brand($this->profile(), 'Hi', '<p>x</p>', 'x', null);
        $this->assertSame('Riverton University', $result['userfrom']->firstname);
        $this->assertSame('', $result['userfrom']->lastname);
        $this->assertSame($noreply->email, $result['userfrom']->email);
    }

    /**
     * With send-as enabled and a valid address, the sender address is overridden.
     *
     * @return void
     */
    public function test_send_as_overrides_address(): void {
        $this->resetAfterTest();

        $on = mailbrander::brand(
            $this->profile(['emailsendas' => 1, 'fromemail' => 'noreply@riverton.edu']),
            'Hi',
            '<p>x</p>',
            'x',
            null
        );
        $this->assertSame('noreply@riverton.edu', $on['userfrom']->email);

        // An invalid override address is ignored, falling back to the no-reply address.
        $noreply = \core_user::get_noreply_user();
        $bad = mailbrander::brand(
            $this->profile(['emailsendas' => 1, 'fromemail' => 'not-an-email']),
            'Hi',
            '<p>x</p>',
            'x',
            null
        );
        $this->assertSame($noreply->email, $bad['userfrom']->email);
    }

    /**
     * A valid reply-to is returned; an invalid one is dropped.
     *
     * @return void
     */
    public function test_replyto_validation(): void {
        $this->resetAfterTest();

        $good = mailbrander::brand(
            $this->profile(['replytoemail' => 'support@riverton.edu', 'replytoname' => 'Riverton Support']),
            'Hi',
            '<p>x</p>',
            'x',
            null
        );
        $this->assertSame('support@riverton.edu', $good['replytoemail']);
        $this->assertSame('Riverton Support', $good['replytoname']);

        $bad = mailbrander::brand(
            $this->profile(['replytoemail' => 'nope']),
            'Hi',
            '<p>x</p>',
            'x',
            null
        );
        $this->assertSame('', $bad['replytoemail']);
    }

    /**
     * With no subject prefix configured, the subject is left unchanged.
     *
     * @return void
     */
    public function test_no_prefix_leaves_subject(): void {
        $this->resetAfterTest();
        $result = mailbrander::brand(
            $this->profile(['emailsubjectprefix' => '']),
            'Plain subject',
            '<p>x</p>',
            'x',
            null
        );
        $this->assertSame('Plain subject', $result['subject']);
    }

    /**
     * A whitespace-only subject prefix does not prepend a stray leading space.
     *
     * @return void
     */
    public function test_whitespace_prefix_adds_no_leading_space(): void {
        $this->resetAfterTest();
        $result = mailbrander::brand(
            $this->profile(['emailsubjectprefix' => '   ']),
            'Plain subject',
            '<p>x</p>',
            'x',
            null
        );
        $this->assertSame('Plain subject', $result['subject']);
    }

    /**
     * A notification with no HTML part gets the text rendered into the branded shell, not a blank.
     *
     * @return void
     */
    public function test_empty_html_backfilled_from_text(): void {
        $this->resetAfterTest();

        $result = mailbrander::brand($this->profile(), 'Hi', '', 'Your attempt was graded.', null);
        $this->assertStringStartsWith('<!DOCTYPE html>', $result['html']);
        $this->assertStringContainsString('Your attempt was graded.', $result['html']);

        // Nothing at all: leave HTML empty so core's own text->HTML fallback runs.
        $empty = mailbrander::brand($this->profile(), 'Hi', '', '', null);
        $this->assertSame('', $empty['html']);
    }

    /**
     * Link rewriting only touches real link boundaries, not a host that merely shares the prefix.
     *
     * @return void
     */
    public function test_link_rewrite_respects_boundaries(): void {
        global $CFG;
        $this->resetAfterTest();

        $good = $CFG->wwwroot . '/course/view.php?id=2';
        $collision = $CFG->wwwroot . '.evil.com/phish';
        $html = '<p><a href="' . $good . '">go</a> and ' . $collision . '</p>';

        $result = mailbrander::brand($this->profile(['hostname' => 'riverton.example.com']), 'S', $html, 'x', null);
        $this->assertStringContainsString('https://riverton.example.com/course/view.php?id=2', $result['html']);
        $this->assertStringContainsString($collision, $result['html']);
        $this->assertStringNotContainsString('https://riverton.example.com.evil.com', $result['html']);
    }

    /**
     * The original sender's maildisplay is preserved unless the admin opts into send-as.
     *
     * @return void
     */
    public function test_maildisplay_preserved_unless_sendas(): void {
        $this->resetAfterTest();

        $userfrom = (object) [
            'id' => 7, 'email' => 'author@university.edu',
            'firstname' => 'A', 'lastname' => 'Author', 'maildisplay' => 0,
        ];

        // With fromname only, the display name changes but maildisplay (Hidden) is untouched.
        $r1 = mailbrander::brand($this->profile(), 'S', '<p>x</p>', 'x', $userfrom);
        $this->assertSame(0, $r1['userfrom']->maildisplay);
        $this->assertSame('Riverton University', $r1['userfrom']->firstname);

        // With send-as and a valid address, exposing the tenant's own address is intended.
        $r2 = mailbrander::brand(
            $this->profile(['emailsendas' => 1, 'fromemail' => 'noreply@riverton.edu']),
            'S',
            '<p>x</p>',
            'x',
            $userfrom
        );
        $this->assertSame('noreply@riverton.edu', $r2['userfrom']->email);
        $this->assertSame(\core_user::MAILDISPLAY_EVERYONE, $r2['userfrom']->maildisplay);
    }

    /**
     * The email header honours rgb()/named brand colours, not just hex.
     *
     * @return void
     */
    public function test_email_header_accepts_rgb_colour(): void {
        $this->resetAfterTest();
        $result = mailbrander::brand(
            $this->profile(['primarycolor' => 'rgb(10, 20, 30)']),
            'S',
            '<p>x</p>',
            'x',
            null
        );
        $this->assertStringContainsString('background:rgb(10, 20, 30)', $result['html']);
    }

    /**
     * A root-relative logo URL is absolutised for email (email has no document base URL).
     *
     * @return void
     */
    public function test_root_relative_logo_absolutised_in_email(): void {
        global $CFG;
        $this->resetAfterTest();
        $result = mailbrander::brand(
            $this->profile(['logourl' => '/local/brandmask/x.png']),
            'S',
            '<p>x</p>',
            'x',
            null
        );
        $this->assertStringContainsString('src="' . $CFG->wwwroot . '/local/brandmask/x.png"', $result['html']);
        $this->assertStringNotContainsString('src="/local/brandmask/x.png"', $result['html']);
    }

    /**
     * A provider-supplied complete HTML document is not double-wrapped by the shell.
     *
     * @return void
     */
    public function test_full_document_not_double_wrapped(): void {
        $this->resetAfterTest();
        $doc = '<!DOCTYPE html><html><head></head><body><h1>Real</h1></body></html>';
        $result = mailbrander::brand($this->profile(['hostname' => '']), 'S', $doc, 'x', null);
        $this->assertSame(1, substr_count(strtolower($result['html']), '<!doctype'));
    }
}
