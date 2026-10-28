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
 * Form for adding and editing branding profiles.
 *
 * @package    local_brandmask
 * @copyright  2026 Vbounds LLC (Sternfast)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_brandmask\form;

defined('MOODLE_INTERNAL') || die();

require_once("{$GLOBALS['CFG']->libdir}/formslib.php");

/**
 * Add/edit a branding profile, including its cohort and per-user targeting.
 *
 * @package local_brandmask
 */
class profile_form extends \moodleform {
    /**
     * Define the form fields.
     */
    protected function definition() {
        global $DB;
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'edit');
        $mform->setType('action', PARAM_ALPHA);

        // Identity.
        $mform->addElement('text', 'name', get_string('name', 'local_brandmask'), ['size' => 40]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('name', 'name', 'local_brandmask');

        $mform->addElement('text', 'brandkey', get_string('brandkey', 'local_brandmask'), ['size' => 20]);
        $mform->setType('brandkey', PARAM_ALPHANUMEXT);
        $mform->addRule('brandkey', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('brandkey', 'brandkey', 'local_brandmask');

        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_brandmask'));
        $mform->setDefault('enabled', 1);

        // Targeting.
        $mform->addElement('header', 'targetinghdr', get_string('targeting', 'local_brandmask'));
        $mform->setExpanded('targetinghdr', true);
        $mform->addElement('static', 'targetingdesc', '', get_string('targetingintro', 'local_brandmask'));

        // Cohorts — searchable multi-select; each label carries the ID number and member count.
        $cohorts = [];
        $cohortrecords = $DB->get_records_sql('
            SELECT c.id, c.name, c.idnumber, COUNT(cm.id) AS members
              FROM {cohort} c
         LEFT JOIN {cohort_members} cm ON cm.cohortid = c.id
          GROUP BY c.id, c.name, c.idnumber
          ORDER BY c.name ASC');
        foreach ($cohortrecords as $c) {
            $label = format_string($c->name);
            if ((string) $c->idnumber !== '' && strcasecmp((string) $c->idnumber, $c->name) !== 0) {
                $label .= ' [' . s($c->idnumber) . ']';
            }
            $label .= ' · ' . get_string('cohortmembers', 'local_brandmask', $c->members);
            $cohorts[$c->id] = $label;
        }
        $mform->addElement(
            'autocomplete',
            'cohorts',
            get_string('cohorts', 'local_brandmask'),
            $cohorts,
            ['multiple' => true, 'noselectionstring' => get_string('targetnone', 'local_brandmask')]
        );
        $mform->setType('cohorts', PARAM_INT);
        $mform->addHelpButton('cohorts', 'cohorts', 'local_brandmask');

        // Specific users — an AJAX picker over all site users (this is an admin-only page, so the
        // user-selector backend's view-details requirement is always met). Nothing is pre-loaded;
        // the valuehtmlcallback re-renders a chip for each user already targeted when editing.
        $useropts = [
            'ajax'              => 'core_user/form_user_selector',
            'multiple'          => true,
            'noselectionstring' => get_string('targetnone', 'local_brandmask'),
            'valuehtmlcallback' => function ($userid) {
                global $OUTPUT;
                $context = \context_system::instance();
                // With allowcustom=false the identity fields stay on the {user} table (email, dept,
                // …) with no joins, so passing get_sql()->selects to get_user() is safe. A custom
                // profile field in "Show user identity" would need a joined query and would fatal.
                $fields = \core_user\fields::for_name()->with_identity($context, false);
                $record = \core_user::get_user($userid, 'id ' . $fields->get_sql()->selects, IGNORE_MISSING);
                if (!$record) {
                    return false;
                }
                $user = (object) [
                    'id'          => $record->id,
                    'fullname'    => fullname($record, has_capability('moodle/site:viewfullnames', $context)),
                    'extrafields' => [],
                ];
                foreach ($fields->get_required_fields([\core_user\fields::PURPOSE_IDENTITY]) as $extrafield) {
                    $user->extrafields[] = (object) ['name' => $extrafield, 'value' => s($record->$extrafield)];
                }
                return $OUTPUT->render_from_template('core_user/form_user_selector_suggestion', $user);
            },
        ];
        $mform->addElement('autocomplete', 'userids', get_string('users', 'local_brandmask'), [], $useropts);
        $mform->setType('userids', PARAM_INT);
        $mform->addHelpButton('userids', 'users', 'local_brandmask');

        // Appearance.
        $mform->addElement('header', 'appearancehdr', get_string('appearance', 'local_brandmask'));
        $mform->setExpanded('appearancehdr', true);

        $mform->addElement('text', 'primarycolor', get_string('primarycolor', 'local_brandmask'), ['size' => 12]);
        $mform->setType('primarycolor', PARAM_TEXT);
        $mform->addHelpButton('primarycolor', 'primarycolor', 'local_brandmask');

        $mform->addElement('text', 'accentcolor', get_string('accentcolor', 'local_brandmask'), ['size' => 12]);
        $mform->setType('accentcolor', PARAM_TEXT);

        $mform->addElement('static', 'logodesc', '', get_string('logointro', 'local_brandmask'));
        $fileopts = ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['web_image']];
        $mform->addElement('filemanager', 'logo', get_string('logo', 'local_brandmask'), null, $fileopts);
        $mform->addHelpButton('logo', 'logo', 'local_brandmask');
        $mform->addElement('filemanager', 'logopartner', get_string('logopartner', 'local_brandmask'), null, $fileopts);
        $mform->addHelpButton('logopartner', 'logopartner', 'local_brandmask');

        // Alternative to uploading: paste an externally hosted logo URL (used only if no file).
        $mform->addElement('text', 'logourl', get_string('logourl', 'local_brandmask'), ['size' => 60]);
        $mform->setType('logourl', PARAM_URL);
        $mform->addHelpButton('logourl', 'logourl', 'local_brandmask');

        $mform->addElement('text', 'loginbgcolor', get_string('loginbgcolor', 'local_brandmask'), ['size' => 12]);
        $mform->setType('loginbgcolor', PARAM_TEXT);

        $mform->addElement('text', 'loginbgurl', get_string('loginbgurl', 'local_brandmask'), ['size' => 60]);
        $mform->setType('loginbgurl', PARAM_URL);

        $mform->addElement(
            'textarea',
            'extracss',
            get_string('extracss', 'local_brandmask'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('extracss', PARAM_RAW);
        $mform->addHelpButton('extracss', 'extracss', 'local_brandmask');

        // Branded sign-in / subdomain (optional).
        $mform->addElement('header', 'subdomainhdr', get_string('subdomainheader', 'local_brandmask'));
        $mform->addElement('text', 'hostname', get_string('hostname', 'local_brandmask'), ['size' => 40]);
        // Keep the raw value (not PARAM_HOST, which silently blanks anything invalid) so validation()
        // can give a clear error and fold a Unicode domain to punycode.
        $mform->setType('hostname', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('hostname', 'hostname', 'local_brandmask');

        // Email.
        $mform->addElement('header', 'emailhdr', get_string('emailhdr', 'local_brandmask'));
        $mform->addElement('static', 'emaildesc', '', get_string('emailintro', 'local_brandmask'));

        // The one switch: off = normal default emails; on = branded for this cohort.
        $mform->addElement(
            'advcheckbox',
            'emailenabled',
            get_string('emailenabled', 'local_brandmask'),
            get_string('emailenabled_label', 'local_brandmask')
        );
        $mform->addHelpButton('emailenabled', 'emailenabled', 'local_brandmask');

        $mform->addElement('text', 'fromname', get_string('fromname', 'local_brandmask'), ['size' => 40]);
        $mform->setType('fromname', PARAM_TEXT);
        $mform->addHelpButton('fromname', 'fromname', 'local_brandmask');
        $mform->hideIf('fromname', 'emailenabled', 'notchecked');

        $mform->addElement('text', 'replytoemail', get_string('replytoemail', 'local_brandmask'), ['size' => 40]);
        $mform->setType('replytoemail', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('replytoemail', 'replytoemail', 'local_brandmask');
        $mform->hideIf('replytoemail', 'emailenabled', 'notchecked');

        $mform->addElement('text', 'replytoname', get_string('replytoname', 'local_brandmask'), ['size' => 40]);
        $mform->setType('replytoname', PARAM_TEXT);
        $mform->addHelpButton('replytoname', 'replytoname', 'local_brandmask');
        $mform->hideIf('replytoname', 'emailenabled', 'notchecked');

        $mform->addElement('text', 'emailsubjectprefix', get_string('emailsubjectprefix', 'local_brandmask'), ['size' => 24]);
        $mform->setType('emailsubjectprefix', PARAM_TEXT);
        $mform->addHelpButton('emailsubjectprefix', 'emailsubjectprefix', 'local_brandmask');
        $mform->hideIf('emailsubjectprefix', 'emailenabled', 'notchecked');

        $mform->addElement('textarea', 'emailfooter', get_string('emailfooter', 'local_brandmask'), ['rows' => 2, 'cols' => 50]);
        $mform->setType('emailfooter', PARAM_TEXT);
        $mform->hideIf('emailfooter', 'emailenabled', 'notchecked');

        // Advanced: send from the tenant's own address (deliverability / DNS required).
        $mform->addElement(
            'advcheckbox',
            'emailsendas',
            get_string('emailsendas', 'local_brandmask'),
            get_string('emailsendas_label', 'local_brandmask')
        );
        $mform->addHelpButton('emailsendas', 'emailsendas', 'local_brandmask');
        $mform->hideIf('emailsendas', 'emailenabled', 'notchecked');

        $mform->addElement('text', 'fromemail', get_string('fromemail', 'local_brandmask'), ['size' => 40]);
        $mform->setType('fromemail', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('fromemail', 'fromemail', 'local_brandmask');
        $mform->hideIf('fromemail', 'emailsendas', 'notchecked');

        $mform->addElement('static', 'dnshelp', '', get_string('dnshelp', 'local_brandmask'));
        $mform->hideIf('dnshelp', 'emailsendas', 'notchecked');

        $this->add_action_buttons();
    }

    /**
     * Validate the submitted form data.
     *
     * @param array $data The submitted form data.
     * @param array $files Any uploaded files.
     * @return array The array of validation errors.
     */
    public function validation($data, $files) {
        global $DB, $CFG;
        $errors = parent::validation($data, $files);

        // The 'off' key is reserved for the per-request opt-out sentinel (?brand=off), never real.
        if (isset($data['brandkey']) && \core_text::strtolower(trim($data['brandkey'])) === 'off') {
            $errors['brandkey'] = get_string('brandkeyreserved', 'local_brandmask');
        }

        // Brandkey must be unique.
        $params = ['brandkey' => $data['brandkey']];
        $select = 'brandkey = :brandkey';
        if (!empty($data['id'])) {
            $select .= ' AND id <> :id';
            $params['id'] = $data['id'];
        }
        if ($DB->record_exists_select('local_brandmask_profile', $select, $params)) {
            $errors['brandkey'] = get_string('brandkeytaken', 'local_brandmask');
        }

        // Validate the branded host: reject invalid input with a clear error (rather than letting
        // it silently blank), block the site's own wwwroot host, and keep one host to one enabled
        // profile (single-wwwroot routing would otherwise be ambiguous).
        if (isset($data['hostname']) && trim($data['hostname']) !== '') {
            $chost = \local_brandmask\local\resolver::canonical_host($data['hostname']);
            if ($chost === '') {
                $errors['hostname'] = get_string('hostnameinvalid', 'local_brandmask');
            } else if ($chost === \core_text::strtolower((string) parse_url($CFG->wwwroot, PHP_URL_HOST))) {
                $errors['hostname'] = get_string('hostnameiswwwroot', 'local_brandmask');
            } else if (!empty($data['enabled'])) {
                $hselect = 'hostname = :hostname AND enabled = 1';
                $hparams = ['hostname' => $chost];
                if (!empty($data['id'])) {
                    $hselect .= ' AND id <> :hid';
                    $hparams['hid'] = $data['id'];
                }
                if ($DB->record_exists_select('local_brandmask_profile', $hselect, $hparams)) {
                    $errors['hostname'] = get_string('hostnametaken', 'local_brandmask');
                }
            }
        }
        return $errors;
    }
}
