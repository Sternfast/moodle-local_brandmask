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
 * Hook callbacks for the local_brandmask plugin.
 *
 * @package    local_brandmask
 * @copyright  2026 Vbounds LLC (Sternfast)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_brandmask;

/**
 * Hook callbacks for Brand Mask.
 * @package local_brandmask
 */
class hook_callbacks {
    /**
     * Inject the resolved tenant's branding into the page <head>.
     *
     * This is the ONLY thing the plugin does to a page. If no tenant resolves,
     * it adds nothing and the page renders exactly as stock Moodle.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook The head-generation hook.
     * @return void
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook
    ): void {
        global $PAGE;

        $profile = \local_brandmask\local\resolver::current();
        if (!$profile) {
            return;
        }

        // This page now carries per-tenant/per-user branding in its HTML. It must never be
        // served from a SHARED cache (CDN / reverse proxy), or one tenant's brand could leak to
        // another user. This hook runs before headers are sent, so Moodle will emit no-store.
        // Only branded pages opt out of caching; stock pages are unaffected.
        if (isset($PAGE) && $PAGE instanceof \moodle_page) {
            $PAGE->set_cacheable(false);
        }

        $hook->add_html(\local_brandmask\local\brander::head_html($profile));
    }
}
