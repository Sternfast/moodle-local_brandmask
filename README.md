# White-Label Branding (local_brandmask)

Per-tenant / per-partner **branding overlay** for a single Moodle. A surgical front-end mask:
it brands the sign-in page, the navigation chrome, logos and colours — and the outgoing
emails — **for a targeted cohort or set of users only**. Everyone else sees the normal site,
completely unchanged. Remove the plugin and the site reverts to stock.

Built for hosts, resellers and partnerships who run **one** Moodle but need each client /
partner institution to feel like it's theirs — on a co-branded subdomain
(`partner.yourmoodle.com`) **or the client's own domain** (`learn.theircollege.edu`) pointed at
your server, where several institutions share one LMS.

## What it does

- **Targets by cohort or explicit user.** A branding profile is owned by its cohort(s) and/or
  named users. Only those people are branded; non-members get the stock theme, untouched.
  (An explicit per-user assignment overrides a cohort.)
- **Clean white-label chrome.** Brand colour accents on the navbar, buttons and links; a crisp
  **one- or two-logo lockup** (a primary logo, plus an optional partner logo for co-branding)
  on the navbar and the sign-in card. Brand Mask overlays logo and colour only — the host's own
  guest access, headings, footer and policy links are left exactly as the site renders them.
- **Branded sign-in by domain or link.** Pre-login, the sign-in page is branded by the request
  host — a subdomain of your Moodle *or a client's own domain* pointed at your server (behind a
  trusted reverse proxy) — or by a `?brand=KEY` URL, so a tenant's people land on *their* branded
  door.
- **Tenant-aware email.** Per-tenant sender display name, reply-to, subject prefix, a branded
  HTML shell (logos + colour + footer), and in-email links rewritten to the tenant subdomain —
  applied per recipient, only for branded tenants, via a `pre_processor_message_send` hook that
  brands the email in place and leaves Moodle's normal email path and each user's notification
  preferences untouched. Sends from your deliverable (DMARC-aligned) address by default; "send
  as the partner's own address" is an opt-in that needs the partner's DNS.

## Safe by design

- **No core patches, no theme forks.** Branding is injected via the supported Hooks API
  (`before_standard_head_html_generation`) as runtime CSS — it never touches Moodle's compiled
  theme cache, so tenants can't cross-contaminate and there's nothing to recompile.
- **Clean removal.** Uninstalling drops the plugin's tables and removes the head-injection and
  email hooks. The core email path is never disabled, so nothing has to be restored — the site
  reverts to exactly how it looked before.
- **Isolation + security.** Branded (per-user) pages are marked non-cacheable so a shared CDN
  can't leak one tenant's brand to another; admin-entered colours/URLs/CSS are sanitised; the
  manage capability is site-admin level (`RISK_XSS`); cohort/user targeting rows self-clean when
  a cohort or user is deleted; a Privacy API provider covers the user-targeting data.

## Requirements

- Moodle 4.5 (LTS) or newer. Email branding uses Moodle's normal outbound email, configured as usual.

## Use it

1. Install `local/brandmask`.
2. *Site administration → Plugins → Local plugins → Brand Mask → Branding profiles.*
3. Add a profile: name it, pick its **cohort(s) / users**, upload one or two **logos**, set the
   **brand colour**, and (optionally) turn on **email branding**.
4. For a real subdomain, point a CNAME at your Moodle and enable the trusted-proxy setting; or
   just hand a tenant their `?brand=KEY` sign-in link.

## License

GNU GPL v3 or later. © Vbounds LLC (Sternfast).
