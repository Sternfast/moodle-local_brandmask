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
 * Manage branding profiles and their cohort/user targeting.
 *
 * @package    local_brandmask
 * @copyright  2026 Vbounds LLC (Sternfast)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_brandmask_manage');
$context = context_system::instance();
require_capability('local/brandmask:manage', $context);

global $DB, $OUTPUT, $PAGE;

$action  = optional_param('action', 'list', PARAM_ALPHA);
$id      = optional_param('id', 0, PARAM_INT);
$baseurl = new moodle_url('/local/brandmask/manage.php');
$PAGE->set_url($baseurl);

// Delete.
if ($action === 'delete' && $id) {
    $profile = $DB->get_record('local_brandmask_profile', ['id' => $id], '*', MUST_EXIST);
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        // Remove the profile's uploaded, publicly-served brand assets so they are not orphaned.
        $fs = get_file_storage();
        $sysctxid = context_system::instance()->id;
        foreach (['logo', 'logopartner', 'loginbg'] as $brandarea) {
            $fs->delete_area_files($sysctxid, 'local_brandmask', $brandarea, $id);
        }
        $DB->delete_records('local_brandmask_cohort', ['profileid' => $id]);
        $DB->delete_records('local_brandmask_user', ['profileid' => $id]);
        $DB->delete_records('local_brandmask_profile', ['id' => $id]);
        redirect($baseurl, get_string('profiledeleted', 'local_brandmask'));
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmdelete', 'local_brandmask', format_string($profile->name)),
        new moodle_url($baseurl, ['action' => 'delete', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]),
        $baseurl
    );
    echo $OUTPUT->footer();
    exit;
}

// Go live: the DNS + setup checklist for one profile.
if ($action === 'setup' && $id) {
    $profile = $DB->get_record('local_brandmask_profile', ['id' => $id], '*', MUST_EXIST);
    $PAGE->set_url(new moodle_url($baseurl, ['action' => 'setup', 'id' => $id]));

    $rendertable = function (array $records) {
        if (!$records) {
            return '';
        }
        $hasnote = false;
        foreach ($records as $r) {
            if (!empty($r['note'])) {
                $hasnote = true;
            }
        }
        $t = new html_table();
        $t->head = [
            get_string('dnstype', 'local_brandmask'),
            get_string('dnshost', 'local_brandmask'),
            get_string('dnsvalue', 'local_brandmask'),
        ];
        if ($hasnote) {
            $t->head[] = get_string('dnsnote', 'local_brandmask');
        }
        foreach ($records as $r) {
            $row = [
                html_writer::tag('code', s($r['type'])),
                html_writer::tag('code', s($r['host'])),
                html_writer::tag('code', s($r['value'])),
            ];
            if ($hasnote) {
                $row[] = s($r['note'] ?? '');
            }
            $t->data[] = $row;
        }
        return html_writer::table($t);
    };

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('setuptitle', 'local_brandmask', format_string($profile->name)));
    echo html_writer::div(get_string('setupintro', 'local_brandmask'), 'alert alert-info');

    // 1. Subdomain.
    echo $OUTPUT->heading(get_string('setupsubdomain', 'local_brandmask'), 4);
    if (!empty($profile->hostname)) {
        echo html_writer::tag('p', get_string('setupsubdomainintro', 'local_brandmask'));
        echo $rendertable(\local_brandmask\local\dnsguide::subdomain_records($profile->hostname));

        $resolvedip = \local_brandmask\local\dnsguide::resolves_to($profile->hostname);
        $serverip = trim((string) get_config('local_brandmask', 'serverip'));
        if ($resolvedip !== '' && $serverip !== '' && filter_var($serverip, FILTER_VALIDATE_IP) && $resolvedip !== $serverip) {
            // It resolves, but not to this server — a stale or wrong record.
            echo $OUTPUT->notification(get_string(
                'dnswrongip',
                'local_brandmask',
                (object) ['host' => s($profile->hostname), 'ip' => s($resolvedip), 'expected' => s($serverip)]
            ), 'warning');
        } else if ($resolvedip !== '') {
            echo $OUTPUT->notification(get_string(
                'dnslive',
                'local_brandmask',
                (object) ['host' => s($profile->hostname), 'ip' => s($resolvedip)]
            ), 'success');
        } else {
            echo $OUTPUT->notification(get_string('dnsnotlive', 'local_brandmask', s($profile->hostname)), 'warning');
        }
        echo html_writer::tag('p', get_string('setupproxynote', 'local_brandmask'), ['class' => 'text-muted']);
        echo $OUTPUT->single_button(
            new moodle_url('https://' . $profile->hostname . '/login/index.php'),
            get_string('setuppreview', 'local_brandmask'),
            'get'
        );
    } else {
        echo $OUTPUT->notification(get_string('setupnosubdomain', 'local_brandmask'), 'info');
    }

    // 2. Email.
    echo $OUTPUT->heading(get_string('setupemail', 'local_brandmask'), 4);
    if (empty($profile->emailenabled)) {
        echo html_writer::tag('p', get_string('setupemailoff', 'local_brandmask'));
    } else if (empty($profile->emailsendas) || empty($profile->fromemail)) {
        echo $OUTPUT->notification(get_string(
            'setupemaildefault',
            'local_brandmask',
            s($profile->fromname ?: $profile->name)
        ), 'success');
    } else {
        $emailguide = \local_brandmask\local\dnsguide::email_records($profile->fromemail);
        echo html_writer::tag('p', get_string(
            'setupemailintro',
            'local_brandmask',
            (object) ['email' => s($profile->fromemail), 'domain' => s($emailguide['domain'])]
        ));
        echo $rendertable($emailguide['records']);
    }

    echo html_writer::div(
        html_writer::link(
            new moodle_url($baseurl, ['action' => 'edit', 'id' => $id]),
            get_string('editprofile', 'local_brandmask'),
            ['class' => 'btn btn-secondary mr-2']
        )
        . ' ' . html_writer::link($baseurl, get_string('back'), ['class' => 'btn btn-link']),
        'mt-3'
    );
    echo $OUTPUT->footer();
    exit;
}

// Add / Edit.
if ($action === 'add' || $action === 'edit') {
    $profile = $id ? $DB->get_record('local_brandmask_profile', ['id' => $id], '*', MUST_EXIST) : null;
    $form = new \local_brandmask\form\profile_form($baseurl->out(false));

    if ($form->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $form->get_data()) {
        $now = time();
        $hostname = empty($data->hostname)
            ? null
            : (\local_brandmask\local\resolver::canonical_host($data->hostname) ?: null);
        $record = (object) [
            'brandkey'           => $data->brandkey,
            'hostname'           => $hostname,
            'name'               => $data->name,
            'enabled'            => !empty($data->enabled) ? 1 : 0,
            'primarycolor'       => $data->primarycolor,
            'accentcolor'        => $data->accentcolor,
            'logourl'            => isset($data->logourl) ? $data->logourl : null,
            'loginbgcolor'       => $data->loginbgcolor,
            'loginbgurl'         => $data->loginbgurl,
            'extracss'           => $data->extracss,
            'emailenabled'       => !empty($data->emailenabled) ? 1 : 0,
            'emailsendas'        => !empty($data->emailsendas) ? 1 : 0,
            'fromname'           => isset($data->fromname) ? $data->fromname : null,
            'fromemail'          => isset($data->fromemail) ? $data->fromemail : null,
            'replytoname'        => isset($data->replytoname) ? $data->replytoname : null,
            'replytoemail'       => isset($data->replytoemail) ? $data->replytoemail : null,
            'emailsubjectprefix' => isset($data->emailsubjectprefix) ? $data->emailsubjectprefix : null,
            'emailfooter'        => isset($data->emailfooter) ? $data->emailfooter : null,
            'timemodified'       => $now,
        ];
        // Write the profile row and its targeting atomically, so a mid-save failure can never
        // leave the profile pointing at a partial set of cohorts/users.
        $transaction = $DB->start_delegated_transaction();
        if (!empty($data->id)) {
            $record->id = $data->id;
            $DB->update_record('local_brandmask_profile', $record);
            $pid = (int) $data->id;
        } else {
            $record->timecreated = $now;
            $pid = (int) $DB->insert_record('local_brandmask_profile', $record);
        }

        // Rewrite cohort targeting (deduplicated — the UNIQUE (profileid,cohortid) index would
        // otherwise throw on a repeated id in a crafted submission).
        $DB->delete_records('local_brandmask_cohort', ['profileid' => $pid]);
        foreach (array_unique(array_map('intval', (array) $data->cohorts)) as $cid) {
            if ($cid && $DB->record_exists('cohort', ['id' => $cid])) {
                $DB->insert_record('local_brandmask_cohort', (object) ['profileid' => $pid, 'cohortid' => $cid]);
            }
        }

        // Rewrite per-user targeting from the user picker (deduplicated array of user ids).
        $DB->delete_records('local_brandmask_user', ['profileid' => $pid]);
        foreach (array_unique(array_map('intval', (array) $data->userids)) as $uid) {
            if ($uid && $DB->record_exists('user', ['id' => $uid, 'deleted' => 0])) {
                $DB->insert_record('local_brandmask_user', (object) ['profileid' => $pid, 'userid' => $uid]);
            }
        }

        // Save uploaded logo files.
        $fileopts = ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['web_image']];
        file_save_draft_area_files($data->logo, $context->id, 'local_brandmask', 'logo', $pid, $fileopts);
        file_save_draft_area_files($data->logopartner, $context->id, 'local_brandmask', 'logopartner', $pid, $fileopts);
        $transaction->allow_commit();

        $msg = get_string('profilesaved', 'local_brandmask');
        // Land on the Go-live page so the admin immediately sees what to set up next.
        redirect(new moodle_url($baseurl, ['action' => 'setup', 'id' => $pid]), $msg);
    }

    // Pre-fill the form; prepare the logo upload areas for both add and edit.
    $fileopts = ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['web_image']];
    $itemid = $profile ? $profile->id : null;
    $draftlogo = 0;
    file_prepare_draft_area($draftlogo, $context->id, 'local_brandmask', 'logo', $itemid, $fileopts);
    $draftpartner = 0;
    file_prepare_draft_area($draftpartner, $context->id, 'local_brandmask', 'logopartner', $itemid, $fileopts);

    if ($profile) {
        $toform = (array) $profile;
        $toform['action'] = 'edit';
        $toform['cohorts'] = $DB->get_fieldset_select('local_brandmask_cohort', 'cohortid', 'profileid = ?', [$profile->id]);
        $toform['userids'] = $DB->get_fieldset_select('local_brandmask_user', 'userid', 'profileid = ?', [$profile->id]);
    } else {
        $toform = ['action' => 'add'];
    }
    $toform['logo'] = $draftlogo;
    $toform['logopartner'] = $draftpartner;
    $form->set_data($toform);

    // Progressive enhancement: a native colour-picker swatch beside each hex colour field,
    // two-way synced with the text input (typing rgb()/named colours still works).
    $PAGE->requires->js_call_amd(
        'local_brandmask/colourpicker',
        'init',
        [['primarycolor', 'accentcolor', 'loginbgcolor']]
    );

    echo $OUTPUT->header();
    echo $OUTPUT->heading($id ? get_string('editprofile', 'local_brandmask') : get_string('addprofile', 'local_brandmask'));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// List.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageprofiles', 'local_brandmask'));
echo html_writer::div(get_string('howitworks', 'local_brandmask'), 'alert alert-info');

$profiles = $DB->get_records('local_brandmask_profile', null, 'name ASC');
if (!$profiles) {
    echo $OUTPUT->notification(get_string('noprofiles', 'local_brandmask'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('name', 'local_brandmask'),
        get_string('brandkey', 'local_brandmask'),
        get_string('primarycolor', 'local_brandmask'),
        get_string('targeting', 'local_brandmask'),
        get_string('enabled', 'local_brandmask'),
        '',
    ];
    foreach ($profiles as $p) {
        $ncohort = $DB->count_records('local_brandmask_cohort', ['profileid' => $p->id]);
        $nuser   = $DB->count_records('local_brandmask_user', ['profileid' => $p->id]);

        $swatch = '-';
        if (!empty($p->primarycolor)) {
            // Render the coloured chip only from the allowlisted colour; show the raw value as text.
            $safe = \local_brandmask\local\brander::safecolor($p->primarycolor);
            $chip = $safe !== '' ? html_writer::span('', '', ['style' =>
                'display:inline-block;width:16px;height:16px;border:1px solid #ccc;vertical-align:middle;'
                . 'background:' . $safe . ';']) . ' ' : '';
            $swatch = $chip . s($p->primarycolor);
        }

        $editurl = new moodle_url($baseurl, ['action' => 'edit', 'id' => $p->id]);
        $delurl  = new moodle_url($baseurl, ['action' => 'delete', 'id' => $p->id]);
        $setupurl = new moodle_url($baseurl, ['action' => 'setup', 'id' => $p->id]);
        // Preview opens the site home with this brand applied (admins may preview any brand).
        $previewurl = new moodle_url('/', ['brand' => $p->brandkey]);
        $actions = html_writer::link($setupurl, get_string('golive', 'local_brandmask'))
            . ' ' . html_writer::link(
                $previewurl,
                $OUTPUT->pix_icon('t/preview', get_string('preview')),
                ['target' => '_blank', 'rel' => 'noopener']
            )
            . ' ' . html_writer::link($editurl, $OUTPUT->pix_icon('t/edit', get_string('edit')))
            . ' ' . html_writer::link($delurl, $OUTPUT->pix_icon('t/delete', get_string('delete')));

        $table->data[] = [
            format_string($p->name),
            s($p->brandkey),
            $swatch,
            get_string('targetsummary', 'local_brandmask', (object) ['cohorts' => $ncohort, 'users' => $nuser]),
            $p->enabled ? get_string('yes') : get_string('no'),
            $actions,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->single_button(
    new moodle_url($baseurl, ['action' => 'add']),
    get_string('addprofile', 'local_brandmask'),
    'get'
);
echo $OUTPUT->footer();
