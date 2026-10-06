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
 * Language strings for the local_brandmask plugin.
 *
 * @package    local_brandmask
 * @copyright  2026 Vbounds LLC (Sternfast)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accentcolor'] = 'Accent (hover) colour';
$string['addprofile'] = 'Add a branding profile';
$string['appearance'] = 'Appearance';
$string['brandkey'] = 'Brand key';
$string['brandkey_help'] = 'Short code (letters/numbers) used in the ?brand=KEY URL fallback and to match a session. Must be unique.';
$string['brandkeyreserved'] = '"off" is reserved for the ?brand=off opt-out and cannot be used as a brand key.';
$string['brandkeytaken'] = 'That brand key is already used by another profile.';
$string['brandmask:manage'] = 'Manage branding profiles';
$string['cohortmembers'] = '{$a} members';
$string['cohorts'] = 'Cohorts';
$string['cohorts_help'] = 'Search for and add one or more cohorts; their members get this brand once logged in. A specific-user assignment (below) overrides a cohort.';
$string['confirmdelete'] = 'Delete the branding profile "{$a}"? Tenants on it revert to the default site branding immediately.';
$string['dnshelp'] = 'To send from the partner\'s own domain, their DNS must authorise it: (1) add your mail server to their domain\'s SPF (TXT) record, and (2) publish a DKIM key so mail can be signed for their domain — otherwise it fails DMARC and gets quarantined. The simplest co-branded option is a subdomain of your own domain (e.g. tenant.yourdomain.com), which your existing SPF/DKIM already cover. Until then, leave this off and the tenant name still shows as the sender.';
$string['dnshost'] = 'Host / name';
$string['dnslive'] = '{$a->host} is resolving to {$a->ip} — the domain points here.';
$string['dnsnote'] = 'Notes';
$string['dnsnotlive'] = '{$a} is not resolving yet. Add the record above, then reload this page.';
$string['dnstype'] = 'Type';
$string['dnsvalue'] = 'Value';
$string['dnswrongip'] = '{$a->host} resolves to {$a->ip}, but this server is {$a->expected}. Point the record here, or update the Server public IP setting.';
$string['editprofile'] = 'Edit branding profile';
$string['emailenabled'] = 'Brand this tenant\'s emails';
$string['emailenabled_help'] = 'Off: these users get Moodle\'s normal emails, unchanged. On: their emails get this tenant\'s logo(s), brand colour, sender name, optional subject prefix and footer, and links pointing at the tenant domain. Only this tenant\'s users are affected.';
$string['emailenabled_label'] = 'Wrap this cohort\'s emails in the tenant logo, colours and sender';
$string['emailfooter'] = 'Email footer';
$string['emailhdr'] = 'Email';
$string['emailintro'] = 'Choose whether this cohort gets the normal system emails, or emails branded as this tenant. Everyone outside this tenant always gets the normal emails.';
$string['emailsendas'] = 'Send from your own address (advanced)';
$string['emailsendas_help'] = 'By default emails send from your verified site/subdomain address with the tenant name shown — fully deliverable, no setup. Turn this on only if the partner wants the address itself to be theirs; it needs the DNS records below on their domain.';
$string['emailsendas_label'] = 'Use the sender address below instead of the default — needs DNS set up';
$string['emailsubjectprefix'] = 'Subject prefix';
$string['emailsubjectprefix_help'] = 'Optional short tag added to the start of every subject, e.g. [Tenant].';
$string['enabled'] = 'Enabled';
$string['extracss'] = 'Extra CSS';
$string['extracss_help'] = 'Optional raw CSS injected for this tenant only. Scope your selectors carefully.';
$string['forwardedhostheader'] = 'Forwarded host header name';
$string['forwardedhostheader_desc'] = 'The header your proxy sends the original host in. Usually X-Forwarded-Host.';
$string['fromemail'] = 'Sender address';
$string['fromemail_help'] = 'The address to send as, e.g. noreply@tenant.example.com. Requires the DNS records below, or the message will fail spam checks and Moodle will fall back to the default address.';
$string['fromname'] = 'Sender name';
$string['fromname_help'] = 'The name recipients see the email is from, e.g. "Your Tenant". The sending address stays on your verified domain for deliverability (see "Send from your own address" below to change that).';
$string['golive'] = 'Go live';
$string['hostname'] = 'Branded domain / subdomain';
$string['hostname_help'] = 'The host this brand applies to — a subdomain of your Moodle (e.g. tenant.yourmoodle.com) or a client\'s own domain pointed at your server (e.g. learn.theircollege.edu). The request\'s Host header is matched against this (port ignored). Leave blank to use the ?brand=KEY link only.';
$string['hostnameinvalid'] = 'That doesn\'t look like a valid domain or subdomain. Enter a host like tenant.yourmoodle.com (no http://, no path).';
$string['hostnameiswwwroot'] = 'That is your main site address — using it would rebrand the primary sign-in for everyone. Use a dedicated subdomain or the client\'s own domain.';
$string['hostnametaken'] = 'Another enabled profile already uses this hostname.';
$string['howitworks'] = 'Per-tenant branding for the targeted cohorts or users only — everyone else sees the normal site, untouched.';
$string['loginbgcolor'] = 'Login background colour';
$string['loginbgurl'] = 'Login background image URL';
$string['logo'] = 'Logo';
$string['logo_help'] = 'The main tenant logo, shown in the navigation bar and on the sign-in page. Transparent PNG or SVG recommended.';
$string['logointro'] = 'Upload one logo, or two for a partnership — a primary and a partner logo are shown side by side as a co-branded lockup. Transparent PNG or SVG works best.';
$string['logopartner'] = 'Partner logo (optional)';
$string['logopartner_help'] = 'A second logo for partnerships / co-branded institutions. When set, both logos appear together with a divider, so users feel it is a shared institution.';
$string['logourl'] = 'Logo URL';
$string['logourl_help'] = 'Absolute URL of the tenant logo (swaps the login and navbar logo, and the favicon).';
$string['manageprofiles'] = 'Branding profiles';
$string['name'] = 'Tenant name';
$string['name_help'] = 'A label for you — e.g. the client/company name. Not shown to learners.';
$string['noprofiles'] = 'No branding profiles yet. Add one to start masking a tenant.';
$string['pluginname'] = 'White-Label Branding';
$string['primarycolor'] = 'Primary colour';
$string['primarycolor_help'] = 'Brand colour for the navbar, primary buttons and links. Hex (#1a73e8), rgb()/rgba() or a CSS colour name.';
$string['privacy:metadata:local_brandmask_user'] = 'Which users are directly assigned a branding profile.';
$string['privacy:metadata:local_brandmask_user:profileid'] = 'The branding profile assigned to the user.';
$string['privacy:metadata:local_brandmask_user:userid'] = 'The user who is assigned the branding profile.';
$string['profiledeleted'] = 'Branding profile deleted.';
$string['profilesaved'] = 'Branding profile saved.';
$string['replytoemail'] = 'Reply-to address';
$string['replytoemail_help'] = 'Where replies go, e.g. support@tenant.example.com. Any address is fine here — reply-to is not spam-checked — so this is the easy way to route replies to the partner.';
$string['replytoname'] = 'Reply-to name';
$string['replytoname_help'] = 'The display name shown on the Reply-to address, e.g. "Tenant Support". Optional.';
$string['serverip'] = 'Server public IP / host';
$string['serverip_desc'] = 'Your Moodle server\'s public IP address (or host). Shown on each tenant\'s Go-live page so you can copy the exact DNS record; leave blank to show a placeholder.';
$string['settings'] = 'Settings';
$string['setupemail'] = '2. Email';
$string['setupemaildefault'] = 'Emails send from your site address, shown as the name "{$a}". No DNS is needed.';
$string['setupemailintro'] = 'To send from {$a->email}, add these records on {$a->domain} so mail passes SPF, DKIM and DMARC:';
$string['setupemailoff'] = 'Email branding is off for this tenant — they receive the normal site emails.';
$string['setupintro'] = 'Add the DNS records below so this tenant\'s branded domain and email work. DNS changes can take a little while to take effect.';
$string['setupnosubdomain'] = 'No branded domain set. This tenant is branded by cohort/user membership and the ?brand= link. Add a domain or subdomain on the Edit page to brand a dedicated sign-in.';
$string['setuppreview'] = 'Preview the branded sign-in';
$string['setupproxynote'] = 'Your reverse proxy must also forward this host to Moodle (with X-Forwarded-Host), and Moodle must be in reverse-proxy mode. On managed hosting, ask your host to add the domain.';
$string['setupsubdomain'] = '1. Point the domain at your Moodle';
$string['setupsubdomainintro'] = 'Add this record at the DNS provider for this host — yours for a subdomain, or the client\'s for their own domain — so it points at this site:';
$string['setuptitle'] = 'Go live: {$a}';
$string['subdomainheader'] = 'Branded sign-in domain (optional)';
$string['targeting'] = 'Who sees this brand';
$string['targetingintro'] = 'Only the users below are masked. Everyone else keeps the normal site theme — nothing is changed for them.';
$string['targetnone'] = 'No one selected yet';
$string['targetsummary'] = '{$a->cohorts} cohort(s), {$a->users} user(s)';
$string['trustedproxies'] = 'Trusted proxy IPs';
$string['trustedproxies_desc'] = 'The forwarded-host header is honoured ONLY when the request arrives from one of these IPs/CIDRs (one per line) and Moodle is in reverse-proxy mode. Leave blank to never trust it. This stops anyone from spoofing the header to force a tenant\'s sign-in branding.';
$string['trustforwardedhost'] = 'Trust forwarded host header';
$string['trustforwardedhost_desc'] = 'Resolve the pre-login brand from a forwarded-host header (for a reverse proxy that rewrites Host to this site\'s wwwroot and passes the real subdomain on). Turn this ON only when a trusted proxy sits in front — the header is otherwise spoofable.';
$string['users'] = 'Specific users';
$string['users_help'] = 'Search for and add specific people. They get this brand even if they are not in a targeted cohort; a person added here overrides a cohort.';
