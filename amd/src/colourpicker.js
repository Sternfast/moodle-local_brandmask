// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Adds a native colour-picker swatch beside each named hex colour field and keeps the two
 * in sync, so a non-technical admin can click a colour instead of typing a hex code. Typing
 * rgb()/hsl()/named colours into the text field still works; the swatch reflects them too.
 *
 * @module     local_brandmask/colourpicker
 * @copyright  2026 Vbounds LLC (Sternfast)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const HEX = /^#[0-9a-fA-F]{6}$/;

/**
 * Convert any CSS colour the text field accepts (named, rgb(), hsl(), #rrggbb) into the #rrggbb a
 * native colour input needs, by letting the browser resolve it. Returns '' when the value is not a
 * colour the swatch can represent, so we never show a misleading black swatch for a valid value.
 *
 * @param {string} value The field's current value.
 * @return {string} A #rrggbb string, or ''.
 */
const toHex = (value) => {
    value = (value || '').trim();
    if (!value) {
        return '';
    }
    if (HEX.test(value)) {
        return value.toLowerCase();
    }
    const probe = document.createElement('span');
    probe.style.display = 'none';
    probe.style.color = '';
    probe.style.color = value;
    if (!probe.style.color) {
        return '';
    }
    document.body.appendChild(probe);
    const rgb = getComputedStyle(probe).color;
    probe.remove();
    const m = rgb.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
    if (!m) {
        return '';
    }
    const h = (n) => ('0' + (parseInt(n, 10) & 255).toString(16)).slice(-2);
    return '#' + h(m[1]) + h(m[2]) + h(m[3]);
};

/**
 * Attach a synced colour swatch to each given colour text field.
 *
 * @param {string[]} names The form field names to enhance.
 */
export const init = (names) => {
    names.forEach((name) => {
        const input = document.querySelector('input[name="' + name + '"]');
        if (!input || input.dataset.bmColour) {
            return;
        }
        input.dataset.bmColour = '1';

        const picker = document.createElement('input');
        picker.type = 'color';
        const label = input.id ? document.querySelector('label[for="' + input.id + '"]') : null;
        picker.setAttribute('aria-label', (label && label.textContent.trim()) || input.getAttribute('aria-label') || name);
        picker.className = 'ml-2 align-middle';
        picker.style.width = '32px';
        picker.style.height = '32px';
        picker.style.padding = '0';
        picker.style.cursor = 'pointer';

        const sync = () => {
            const hex = toHex(input.value);
            if (hex) {
                picker.value = hex;
            }
        };
        sync();
        picker.addEventListener('input', () => {
            input.value = picker.value;
        });
        input.addEventListener('change', sync);

        input.parentNode.insertBefore(picker, input.nextSibling);
    });
};
