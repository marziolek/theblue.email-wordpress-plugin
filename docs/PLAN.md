# theblue.email Forms - the public WordPress plugin

Plan written 2026-11-01 against the platform as it exists in `marziolek/theblue.email`
(plugin version 1.0.41). **Built the same day**, on the recommendations below since the
owner decisions were still open; section 10 lists what was built and where it departs
from this plan. Publishing is [PUBLISHING.md](PUBLISHING.md).

## 1. What it is

A free plugin on the WordPress.org directory that a theblue.email tenant installs on their
own site. It connects to their theblue.email account with an API key, pulls the list of
their signup forms, and lets them place a form on any page with a Gutenberg block or a
shortcode. The form itself is rendered and submitted by the platform exactly as the paste-in
embed snippet does today; the plugin removes the copy-paste, the hostname allowlist
friction and the "where do I put HTML in WordPress" question.

What it is not, in v1: not a way to add subscribers from WooCommerce or comments, not a
campaign archive, not a landing-page embed. Those are in section 9 as later releases.

## 2. How the platform works today, and what that decides

Findings from the platform code that shape the design:

- **Forms are rendered client-side.** The snippet is a `<div class="tbe-form"
  data-form-id="{public_key}" data-fields="N">` plus `<script src="https://theblue.email/embed/tbe-embed.js" async>`
  (`admin/views/forms.php`). The script fetches `GET /api/tbe/v1/forms/{key}/render`, prints
  the HTML, solves the proof-of-work puzzle and posts to `/submit`. Forms are identified by a
  12-hex `public_key`, never by id (`FormRest`, `Form::get_public()`).
- **The hostname allowlist is enforced on the browser's Origin.** `FormRest::check_origin()`
  allows the platform host, the landing-page domains, and the hostnames the tenant typed into
  the form's `allowed_domains` setting. An empty list blocks every other site. A request with
  no Origin header (server to server) is allowed, but that does not help: the browser submits
  the form, so the tenant's hostname must be on the list either way. Server-side rendering
  from PHP would therefore buy nothing, and the plugin renders client-side like the snippet.
- **The script already supports a configured base URL.** `tbe-embed.js` reads
  `tbeEmbed.restUrl` when defined, otherwise derives the origin from `document.currentScript`.
  Script optimisers (concatenation, delayed loading) can leave `currentScript` null, so the
  plugin always defines `tbeEmbed.restUrl` inline before the script.
- **The script is cached for one hour on purpose** (`.htaccess`), so loading it from the
  platform means protocol changes (proof-of-work bits, the Turnstile hand-off) reach every
  site without a plugin release. Bundling a copy would undo that.
- **The tenant API is Pro and Business only** (`ApiRest::require_auth()` calls
  `PlanEnforcer::can_use_api()`), and it has **no endpoint that lists forms**. Keys are
  `tbe_` + 32 hex, SHA-256 hashed, one table `tbe3256_api_keys` with no scope column
  (`ApiKey`). Forms exist on every plan, so the plugin cannot use the API as it stands
  without either opening the API to every plan or adding a narrower key. Section 4 settles
  this.
- **The CSS class names are the platform's.** The rendered form uses `tbe-form__*` and loads
  `tbe-forms.css` from the platform when the form's `apply_styles` is on. The plugin must not
  restyle or rename them; it may add a wrapper class of its own.
- **Rules that follow the text into this repo:** no long dashes in anything a person reads,
  all code in English, and nothing a tenant reads names the sending provider or the
  infrastructure (platform CLAUDE.md, owner rules of 2026-10-03 and 2026-10-12).

## 3. Decisions the owner has to make

Each has a recommendation. Build starts on the recommendations unless told otherwise.

| # | Decision | Recommendation | Why |
|---|---|---|---|
| D1 | How a Free or Starter account authorises the plugin, given the API is Pro+ | Add a `scope` to API keys: `full` (today's key, Pro+) and `forms` (a "Website key", every plan). The forms routes accept either; the existing routes accept `full` only | Keeps "REST API is Pro and Business" true on the pricing page and in `PlanEnforcer`. A key that can only read form names and add a hostname is safe to paste into a WordPress site that other plugins and admins can read. One column, one enum, no new table |
| D2 | Licence and visibility of this repository | GPL-2.0-or-later, public repository | WordPress.org accepts only GPL-compatible plugins. The platform stays proprietary; nothing of it is copied here |
| D3 | Minimum PHP and WordPress | PHP 8.1, WordPress 6.4 | 8.1 gives enums and readonly properties while covering the hosts a paying tenant is on in 2026. 6.4 gives block API v3 and the `async` script strategy |
| D4 | Name and slug | "theblue.email Forms", slug `theblue-email-forms` | The slug `theblue-email` is the platform plugin's own; the two must never collide on the owner's installs. A WordPress.org slug is permanent |
| D5 | Where `tbe-embed.js` is loaded from | From theblue.email, disclosed in the readme under "External services" | See section 2. If the directory review objects, fall back to a bundled copy with a release per protocol change |
| D6 | Whether the plugin may add its own hostname to a form's allowed domains | Yes, with one click, through a new scoped endpoint | The allowlist is the one step tenants get wrong with the snippet today. Without it the plugin can only show a warning |
| D7 | Paste-a-key or a "Connect" button that opens theblue.email and returns a key | Paste-a-key in v1; the connect flow in v2 | The connect flow needs a one-time code exchange, a CSRF state, and an approval screen in the app. The key paste is three lines of UI and works on day one |

## 4. Changes on the platform first (repository `marziolek/theblue.email`)

These ship before any plugin build can be tested end to end. They follow the platform's
own rules: test first, `tbe-schema` skill for the column, `%i` for table names, the
`tbe-wp-sniffs` fixes, `tbe-i18n` for strings.

### 4.1 Key scope

- `tbe3256_api_keys` gains `scope varchar(16) NOT NULL DEFAULT 'full'` (dbDelta in
  `Activator`; existing rows read as `full`). Update `context/03-database.md`.
- `ApiKey::create(int $user_id, string $name, string $scope = 'full')`.
- `ApiKey::authenticate()` keeps returning the user id. New
  `ApiKey::authenticate_scoped(string $raw): ?array{user_id: int, scope: string}` for the
  new routes. `get_all()` returns the scope so the panel can label the key.
- Integrations screen (`admin/views/partials/api-keys-panel.php`): every plan may generate a
  **Website key**; Pro and Business keep the full key as today. Copy explains the
  difference in one sentence and links the plugin.

### 4.2 Routes (namespace `tbe/v1`, public prefix `/api/`)

Permission callback `ApiRest::require_forms_auth()`: a valid key of scope `forms` or
`full`, any plan; writes refused while the account is suspended, the same rule
`require_auth()` applies.

| Method and path | Returns | Notes |
|---|---|---|
| `GET /account` | `{ name, email, plan, platform_url, embed_script_url }` | `embed_script_url` carries the current `?v=` so the plugin never guesses `TBE_VERSION` |
| `GET /forms` | `{ data: [ { id, public_key, name, status, fields, list: { id, name }, allowed_domains: [], updated_at } ], total }` | Every form, drafts included, so the editor can say "this form is a draft". `fields` is the count the snippet writes into `data-fields` |
| `POST /forms/{key}/allowed-domains` | the updated `allowed_domains` | Body `{ host }`. Validated as a bare hostname, lower-cased, idempotent. Scoped to the key's user through `Form::get()`; a foreign key is 404 |

The existing five routes keep `require_auth()` and must also refuse a `forms`-scope key
with 403 `tbe_auth_scope`. Integration tests for the owner check, the scope check and the
suspended-write check, written red first.

### 4.3 Documentation and copy

- CLAUDE.md feature-gates table: new row "WordPress plugin connection: every plan"; the
  REST endpoint list re-enumerated; `ApiKey` and `ApiRest` entries in
  `context/02-plugin-map.md`.
- Tenant docs: `40-forms-and-pages/10-signup-forms.md` gets a "WordPress" section (install
  the plugin, paste the Website key, choose the form in the block), and
  `70-account/30-api.md` lists the three routes and the two key types. Four languages
  through the usual translation pass.
- The Forms screen in the app shows one line under the embed snippet: "On WordPress,
  install the theblue.email Forms plugin instead" with a link, once the plugin is live.

## 5. The plugin

### 5.1 Naming

```
Plugin name        theblue.email Forms
Slug / directory   theblue-email-forms
Main file          theblue-email-forms.php
Text domain        theblue-email-forms
PHP namespace      TheBlueEmail\Forms\
Constants          TBEF_            e.g. TBEF_VERSION, TBEF_PLATFORM_URL
Options            tbef_            e.g. tbef_api_key, tbef_connection
Hooks              tbef_            e.g. tbef_forms_refreshed
Shortcode          [theblue_email_form key="a1b2c3d4e5f6"]
Block              theblue-email/form
CSS (plugin's own) tbef-            the rendered form keeps the platform's tbe-form__*
```

`tbef_` rather than `tbe_` so a site that also runs the platform plugin (the owner's own
installs) never shares an option, hook or constant with it. There are no database tables:
a connection, a cached form list and a few settings belong in `wp_options`. The platform's
"custom tables only" rule is about the platform's app data and does not apply here.

### 5.2 Layout

```
theblue-email-forms/
├── theblue-email-forms.php      header, constants, autoloader, boot
├── uninstall.php                deletes tbef_* options and transients
├── readme.txt                   WordPress.org readme (Stable tag, FAQ, External services, Privacy)
├── README.md                    developer readme (build, test, release)
├── CLAUDE.md                    standing rules for this repository
├── includes/
│   ├── class-plugin.php         wires hooks; nothing else holds closures
│   ├── class-connection.php     stores and reads the key (encrypted with wp_salt), connection state
│   ├── class-client.php         wp_remote_get/post to the platform; typed results; errors as a Result object
│   ├── class-forms.php          the cached form list (transient, 10 minutes), refresh, lookup by key
│   ├── class-embed.php          enqueues tbe-embed.js once, tbeEmbed.restUrl inline, prints the div
│   ├── class-shortcode.php      [theblue_email_form]
│   ├── class-block.php          registers blocks/form, render callback
│   ├── class-settings-page.php  theblue.email (top-level admin menu)
│   ├── class-notices.php        admin notices: not connected, key revoked, hostname not allowed
│   └── class-site-health.php    one Site Health test: connection reachable and valid
├── blocks/form/
│   ├── block.json               apiVersion 3, dynamic, attributes { key }
│   ├── render.php               calls Embed::markup()
│   ├── editor.js                plain wp.element.createElement, no JSX, no build
│   └── editor.css
├── assets/admin/                settings.css, settings.js (copy buttons, refresh)
├── languages/                   .pot; pl_PL shipped until translate.wordpress.org takes over
├── tests/                       PHPUnit 10, Brain\Monkey, Mockery (same stack as the platform)
├── bin/build-zip.sh             the distributable zip, no dev files
├── composer.json                dev dependencies only; nothing in vendor/ is shipped
├── phpcs.xml.dist               WordPress-Extra + WordPress-Docs, full, clean from the first commit
├── phpstan.neon.dist            level 6 with szepeviktor/phpstan-wordpress
└── .github/workflows/           ci.yml, release.yml, wporg-deploy.yml
```

### 5.3 Behaviour

**Connection.** The theblue.email page, a top-level admin menu item with a toolbar link the owner can switch off under Display. One field for the key, a Connect button. On
submit (nonce, `manage_options`) the plugin calls `GET /account`; success stores the key
(encrypted with a key derived from `wp_salt('auth')`, prefix kept in clear for display as
`tbe_a3f9...`) and the account summary, and loads the forms. Failure shows the platform's
message. Disconnect deletes everything. The key is never printed back in full.

**Forms list.** `Forms::all()` returns the cached list; `Forms::refresh()` calls
`GET /forms` and stores it for 10 minutes. A Refresh button and the block editor's
"Refresh" link call it. Each row on the settings screen: name, status badge, list name,
"this site" column (the site's hostname is on `allowed_domains`, or an **Allow this site**
button that calls `POST /forms/{key}/allowed-domains` with `wp_parse_url(home_url(),
PHP_URL_HOST)`), and a copy button for the shortcode.

**Rendering.** `Embed::markup(string $key): string` returns the exact snippet div, with
`data-fields` from the cached list and an outer `<div class="tbef-form">` for theme
authors. It also enqueues the script once per page: `wp_enqueue_script('tbef-embed',
$embed_script_url, [], null, ['strategy' => 'async'])` plus
`wp_add_inline_script('tbef-embed', 'window.tbeEmbed = {restUrl: ".../api/tbe/v1/"};', 'before')`.
The URL comes from the stored account summary, with `TBEF_PLATFORM_URL . '/embed/tbe-embed.js'`
as the fallback. The platform renders, styles and submits the form; the plugin never touches
the visitor's data and never caches rendered HTML.

**Block.** `theblue-email/form`, dynamic, one attribute `key`. The edit side is a
`SelectControl` fed by the cached list through a small private REST route of the plugin's
own (`tbef/v1/forms`, read only, capability `edit_posts`) so authors who are not
administrators can pick a form. The editor preview uses `ServerSideRender` so the real
snippet loads inside the editor iframe, with an inspector panel showing status, list name
and, when this site is not on the form's allowed domains, a warning with the Allow button
(administrators only). Not connected: the block shows a one-line notice linking to the
settings page. Draft form: badge, and the front end prints nothing, as the platform does.

**Shortcode.** `[theblue_email_form key="..."]` for the classic editor, widgets and page
builders. An unknown or empty key prints nothing on the front end and an HTML comment for
the author.

**Notices.** Not connected (settings pages only, dismissible); key revoked or account gone
(401 on refresh, every admin page until fixed); this site not allowed on a form that is
placed on a published page (one notice, lists the forms). Site Health adds one test that
reports the same state.

**Uninstall.** `uninstall.php` removes the options and transients. Deactivation keeps them.

### 5.4 Security and privacy

- Every admin action: capability check and nonce. The settings REST route: `edit_posts`
  and read only. Output escaped where it is echoed, the platform's house rule.
- `wp_remote_*` with a 10 second timeout, `sslverify` on, the platform URL a constant, never
  an option a site admin can point elsewhere. Responses decoded with a size cap and
  validated field by field.
- The hostname sent to the allowlist endpoint is the site's own `home_url()` host, never
  user input.
- Privacy: the plugin itself sends no visitor data. The visitor's browser sends the form
  submission to theblue.email. `readme.txt` says so under "External services" and the plugin
  registers suggested privacy-policy text with `wp_add_privacy_policy_content()`.
- Nothing in the plugin names the sending provider or the platform's infrastructure.

### 5.5 Quality gates

- PHPUnit for `Connection`, `Client` (mocked `wp_remote_*`), `Forms`, `Embed`,
  `Shortcode`, `Block` render; test names `it_does_something()`, written first.
- `phpcs` with WordPress-Extra and WordPress-Docs over the whole plugin; the platform
  runs security sniffs only because its code predates them, this repository starts clean.
- PHPStan level 6 with the WordPress stubs.
- The WordPress.org **Plugin Check** action, which the directory review runs.
- Playwright smoke in CI against `wp-env`: activate, connect with a key against a mock of
  the three routes, place the block, assert the snippet div and the script tag on the page.
- A hand pass on the owner's local WordPress with a real key against the local platform
  (the platform's `tbe-qa` skill has the URLs and the preview-gate cookie).

## 6. Release and distribution

- `readme.txt` sections: Description, Installation, FAQ (allowed domains, draft forms, why
  the script loads from theblue.email, which plans), Screenshots, External services,
  Privacy, Changelog. Banner 1544x500 and icon 256x256 in `.wordpress-org/`.
- `release.yml` builds the zip on a tag `v*` and attaches it to a GitHub release, for beta
  testers before the directory listing exists.
- Submit to WordPress.org once Phase 4 is done; the first review takes weeks, so submit a
  working 1.0.0 and keep fixing in the meantime. After approval, `wporg-deploy.yml`
  (10up's action) pushes tags to SVN; the version in the header, `readme.txt` Stable tag
  and `TBEF_VERSION` must agree, checked in CI.
- Translations: `.pot` generated in CI; pl_PL shipped in `languages/` until the directory's
  translation project exists, then removed.

## 7. Phases

| Phase | Where | Work | Done when |
|---|---|---|---|
| 0 | this repo | D1 to D7 answered; scaffold: main file, CLAUDE.md, CI with lint and PHPUnit, phpcs and PHPStan configs, readme skeleton | CI green on an empty plugin that activates |
| 1 | platform | 4.1 key scope and Website key in Integrations; 4.2 routes with integration tests; 4.3 CLAUDE.md and context updates | `curl` with a Website key lists forms and adds a hostname on the local platform |
| 2 | this repo | Connection, Client, Forms cache, Settings page, Embed, Shortcode | A form placed by shortcode renders and accepts a signup on a local WordPress |
| 3 | this repo | Block with picker, ServerSideRender preview, Allow this site, notices, Site Health | The block works in the editor and on the front end; a wrong hostname is fixed in one click |
| 4 | this repo | Uninstall, privacy text, i18n, readme, assets, zip build, Plugin Check clean, Playwright smoke | Beta zip on a GitHub release installed on the owner's site |
| 5 | both | WordPress.org submission, SVN deploy workflow, tenant docs in four languages, the line on the app's Forms screen | Listed in the directory; docs live |

Phases 0 and 1 can run in parallel in the two repositories. Phase 2 can start against the
local platform as soon as Phase 1 is on a branch.

## 8. Risks

- **Directory review and the remote script** (D5). Mitigation: disclose it; have the
  bundled-copy fallback ready as a branch.
- **Hostname mismatches**: `www.` and bare, staging clones, a site behind a proxy with a
  different `home_url()`. The plugin compares the exact host and offers to add it; the
  settings page spells out which host it sent.
- **Caching plugins** rewriting or delaying the script: `tbeEmbed.restUrl` inline covers the
  base URL; the div is static HTML so it survives page caching.
- **Key in `wp_options`**: any administrator or plugin on the site can read it. Scoped keys
  (D1) keep the blast radius to form names and one hostname.
- **A second plugin with the `tbe` prefix**: the `tbef_` prefix and the distinct text domain
  avoid it; the owner's own installs run both.

## 9. Later releases

- v1.1: the connect flow (D7): a button opens theblue.email, the tenant approves, a
  one-time code returns and is exchanged for a Website key.
- v1.2: opt-in checkboxes on the comment form, the registration form and WooCommerce
  checkout, posting to `POST /subscribers` with a full key (Pro and Business, as the API is).
- v1.3: a campaign archive block from `GET /campaigns`, and a landing-page embed if the
  platform grows an embeddable landing-page route.
- A legacy widget only if tenants on classic themes ask; the shortcode in a Text widget
  covers it meanwhile.

## 10. As built (2026-11-01)

Phases 0 to 4 are done in code; phase 5 (submission, docs live) is the owner's, see
PUBLISHING.md. Every decision D1 to D7 was taken as recommended.

**Platform (theblue.email 1.0.42).** `tbe3256_api_keys.scope` (`full` | `forms`);
`ApiKey::authenticate($raw, $required)` asks for `full` by default, so the tenant API
refuses a Website key with no change to `ApiRest`. The Integrations panel offers a
Website key on every plan. Routes live in a new `SiteApiRest` under `/site/` rather than
beside the public form routes, so the two authentication models never share a path:

```
GET  /api/tbe/v1/site/account
GET  /api/tbe/v1/site/forms
POST /api/tbe/v1/site/forms/{key}/allowed-domains
GET  /api/tbe/v1/site/forms/{key}/challenge
POST /api/tbe/v1/site/forms/{key}/subscribe
```

**Gravity Forms and Contact Form 7 moved from "later" (v1.2) into 1.0**, at the owner's
request, and changed shape. The plan had them post to `POST /subscribers` with a full key,
which skips every form protection and would have needed Pro. Instead a relayed signup goes
through a theblue.email form with `FormRest::relay()`, which runs the same steps as an
embedded form: honeypot, per-visitor-IP limit (keyed on account and visitor, not on the
relaying server), FormGuard, proof of work, the Turnstile hand-off for a form under attack,
then double opt-in. So it works on every plan and counts against the form's list and
limits. An account may relay 600 signups an hour.

Proof of work for a form the site renders: `tbef-protect.js` asks this site for a
challenge (`/wp-json/tbef/v1/challenge?target=gf-12`), the site fetches it from the
platform with its key, and the browser solves it while the visitor types. Pages carry an
opaque target name, never a form key. The solver is copied from `tbe-embed.js`; a Node test
checks it and a cross-check against the platform's PHP verifier passed during the build.

**Block editor preview** shows the form's shape, not the live form: scripts inside
`ServerSideRender` output do not run, so the plan's live preview was not possible.

**Not done:** the connect-button flow (D7, v1.1), screenshots, the WordPress.org and
Gravity Forms submissions, tenant docs on theblue.email for the plugin beyond the API and
signup-forms pages.
