# Changelog — Brand Mask (local_brandmask)

All notable changes to this plugin are documented here.

## 0.7.6 — 2026-10-05

Custom-domain support, searchable people/cohort pickers, and an edge-case hardening pass.

### Added
- **Custom domains, not just subdomains.** A branding profile's host can be a subdomain of your
  Moodle *or a client's own domain* pointed at your server (e.g. `learn.theircollege.edu`) — the
  request host is matched the same way either way, so no code path is domain-specific. The Go-live
  page and DNS guidance now cover both, including an apex A-record and who adds the record.

### Changed
- **Searchable targeting pickers.** The specific-user field is now an AJAX people search (nothing
  pre-loaded); the cohort field shows each cohort's ID number and member count.
- **Colour picker is now a proper AMD module** that reflects named/`rgb()`/`hsl()` values in the
  swatch (not only hex) and takes its accessible name from the field label.
- Display name is now **White-Label Branding** (frankenstyle component stays `local_brandmask`).
- Declares support for **Moodle 4.4 → 5.3**; minimum requirement lowered to 4.4.

### Fixed — 15 findings from an adversarial edge-case review, each independently verified
- Editing a user-targeted profile no longer fatals when a custom profile field is in "Show user
  identity".
- Host matching hardened: a trailing-dot FQDN, an uppercase host and a `host:port` all match; an
  invalid host is rejected with a clear error instead of silently vanishing; a Unicode domain is
  folded to punycode; the site's own wwwroot host is blocked.
- Profile save runs in a transaction and de-duplicates targeting ids (no partial writes or
  unique-key crashes).
- The Go-live DNS check is cached so a slow nameserver can't hang the page, and warns when the host
  resolves to a different IP than this server.
- The profiles-list colour swatch is rendered only from an allowlisted colour value.
- `upgrade.php` now creates the `fromname`/`fromemail` columns for sites upgrading from an early
  version.

## 0.7.5 — 2026-10-05

Narrower footprint — overlay only, never subtract.

### Changed
- The branded sign-in no longer hides the host's **guest-access button**, the login divider or
  the page headings. Brand Mask now overlays logo and brand colour only; every other element —
  guest access, the site footer, and any cookie/policy links the host site carries — renders
  exactly as the original Moodle does, unchanged.
- Example data in the test suite uses neutral placeholder names (no stand-in company name).

## 0.7.4 — 2026-10-05

Admin "go live" workflow.

### Added
- A per-tenant **Go live** page: shows the exact **DNS records** to add — the subdomain A/CNAME
  record (using a configurable server IP/host) and, for custom senders, the SPF / DKIM / DMARC
  records keyed to the sender's domain — checks **live** whether the subdomain is resolving, and
  gives a one-click **Preview**. Saving a profile lands straight on it.
- A `serverip` plugin setting so the DNS guidance shows a concrete value.

### Changed
- All example text uses neutral placeholders (no recognisable company names).

## 0.7.3 — 2026-10-05

Hardening release following two adversarial review rounds (25 findings resolved).

### Changed
- **Email branding redesigned** — applied in place via the `pre_processor_message_send` hook,
  so Moodle's core email processor stays enabled and every user's notification preferences keep
  working. The separate `message_brandmask` processor plugin has been **removed** (its routing
  no longer exists; nothing re-enables core email because it is never disabled).

### Fixed
- Pre-login `X-Forwarded-Host` trust checks the immediate proxy peer and uses the
  proxy-stamped (rightmost) host — closes an Apache append-spoof of the login brand.
- Sender branding no longer exposes a Hidden sender's real address; a message's own reply-to
  (e.g. forum reply-by-email) is never overwritten; empty-HTML notifications are no longer sent
  as a blank branded frame; in-email links are rewritten only at real URL boundaries; a rare
  full-document email body is left intact rather than nested.
- `?brand=off` reliably suppresses branding (case-insensitive); one hostname fronts only one
  enabled profile (deterministic resolution + form validation).
- Logo URL and reply-to-name fields wired into the admin form; root-relative logo URLs made
  absolute in email; the email header honours `rgb()`/named brand colours; deleting a profile
  removes its uploaded brand assets.

### Admin UI
- Trimmed the profiles-list intro to one line; added a native **colour picker** beside each
  colour field (hex text still accepts `rgb()`/named); added a **Preview** link per profile
  that opens the branded site home in a new tab.

## 0.7.0 — 2026-10-02

Initial prototype release.

### Added
- Per-cohort and per-user **branding overlay** via the output Hooks API
  (`before_standard_head_html_generation`) — no core patch, no theme fork.
- Clean white-chrome design: brand-colour accents on the navbar, buttons and links, and a
  one- or two-logo **co-brand lockup** on the navbar and the sign-in page.
- Branded, de-cluttered sign-in (host site-name and guest-access controls hidden for the
  branded tenant only).
- Pre-login branding resolved by **subdomain** (behind a trusted reverse proxy) or by a
  `?brand=KEY` link; after login, cohort/user membership is authoritative.
- Two filemanager logo slots (primary + optional partner), brand/accent colours, login
  background, and a sanitised extra-CSS field.
- Companion `message_brandmask` processor for per-tenant **branded email** (sender display
  name, reply-to, subject prefix, email-safe branded HTML shell, subdomain link rewriting).

### Security & privacy
- Colour values allowlisted; URLs escaped for CSS `url()`; extra CSS constrained (no
  `</`, `<script`, `@import`, `expression(`, `javascript:`; 16 KB cap).
- Per-user branded pages marked non-cacheable to prevent cross-tenant brand leakage via a
  shared cache/CDN.
- Forwarded-host header honoured only from configured trusted-proxy IPs in reverse-proxy mode.
- Privacy API provider for the user-targeting table; targeting rows self-clean when a cohort
  or user is deleted.

### Quality
- Passes the official Moodle coding standard (`phpcs` moodle standard): 0 errors, 0 warnings.
- Clean install / upgrade / uninstall; uninstalling reverts the site to stock.
