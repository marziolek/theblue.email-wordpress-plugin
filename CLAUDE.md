# theblue.email Forms - Claude Working Instructions

The public WordPress plugin for theblue.email tenants: it connects to an account with a
Website key, lists the account's signup forms and places one on a page with a block or a
shortcode, and relays newsletter signups from Gravity Forms and Contact Form 7. The
platform renders and submits its own forms; this plugin handles a visitor's address only
for the Gravity Forms and Contact Form 7 relay, and keeps nothing but a masked log.

Read [docs/PLAN.md](docs/PLAN.md) first (design, owner decisions D1 to D7, what was
built), then [docs/PUBLISHING.md](docs/PUBLISHING.md) before any release. The platform
lives in `marziolek/theblue.email`; its CLAUDE.md and `context/` describe the routes this
plugin calls (`SiteApiRest`).

## Rules that carry over from the platform

- **All code in English.** User-facing strings wrapped in `__('...', 'theblue-email-forms')`.
- **No long dashes** (U+2013, U+2014) in anything a person reads: UI strings, docs,
  comments, commit messages. Use a plain hyphen with spaces.
- **Say nothing about how mail is sent.** No text in this plugin names the sending
  provider or the platform's infrastructure.
- **Write the test first.** PHPUnit 10, Brain\Monkey, Mockery. Test names `it_does_x()`.
- **Every signup path runs the platform's protections.** A relayed signup carries the
  honeypot, the proof of work solved in the visitor's browser (`assets/js/tbef-protect.js`,
  whose solver is a byte-for-byte copy of the platform's `tbe-embed.js`; `tests/js/pow.test.js`
  guards it) and the visitor's IP, and goes to `/site/forms/{key}/subscribe`, which runs
  FormGuard, the Turnstile hand-off and double opt-in. Never add a path around it.
- **A relay never blocks the form it is attached to.** The contact message or entry goes
  through; the outcome is logged.
- **Escape at the echo, nonce and capability on every action, prepared and typed input.**
  phpcs runs WordPress-Extra over the whole plugin and must stay clean.

## Rules specific to this repository

```
Slug / text domain   theblue-email-forms
Namespace            TheBlueEmail\Forms\
Constants            TBEF_
Options and hooks    tbef_
Block                theblue-email/form
Shortcode            [theblue_email_form key="..."]
```

- `tbef_`, not `tbe_`: the owner's installs run this plugin and the platform plugin side
  by side, and nothing may collide.
- **No database tables, no Composer runtime dependencies, no JS build.** State is in
  `wp_options` and transients; the block editor script is plain `wp.element.createElement`.
- The rendered form keeps the platform's `tbe-form__*` classes untouched. The plugin's own
  CSS uses `tbef-`.
- The platform URL is a constant (`TBEF_PLATFORM_URL`), never an option.
- The API key is stored encrypted with a key derived from `wp_salt('auth')` and is never
  printed back in full.
- Licence GPL-2.0-or-later; `readme.txt` discloses every call to theblue.email under
  "External services". A new call means a new line there.
- Keep `Version:`, `TBEF_VERSION`, the readme `Stable tag` and `block.json` version equal
  (`php bin/check-versions.php`; CI and the release workflow enforce it).

## Commands

```
composer install            dev tools only, nothing ships from vendor/
composer qa                 versions, phpcs, phpstan, phpunit, node solver test
bin/build-zip.sh            dist/theblue-email-forms.zip, as WordPress installs it
wp i18n make-pot . languages/theblue-email-forms.pot --exclude=vendor,tests,bin,build,dist
```
