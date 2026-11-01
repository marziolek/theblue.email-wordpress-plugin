# theblue.email Forms - Claude Working Instructions

The public WordPress plugin for theblue.email tenants: it connects to an account with an
API key, lists the account's signup forms and places one on a page with a block or a
shortcode. The platform renders and submits the form; this plugin never handles a
visitor's data.

Read [docs/PLAN.md](docs/PLAN.md) first. It holds the design, the owner decisions
(D1 to D7) and the phases. The platform itself lives in `marziolek/theblue.email`; its
CLAUDE.md and `context/` describe the routes this plugin calls.

## Rules that carry over from the platform

- **All code in English.** User-facing strings wrapped in `__('...', 'theblue-email-forms')`.
- **No long dashes** (U+2013, U+2014) in anything a person reads: UI strings, docs,
  comments, commit messages. Use a plain hyphen with spaces.
- **Say nothing about how mail is sent.** No text in this plugin names the sending
  provider or the platform's infrastructure.
- **Write the test first.** PHPUnit 10, Brain\Monkey, Mockery. Test names `it_does_x()`.
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
- Licence GPL-2.0-or-later; `readme.txt` discloses the script loaded from theblue.email
  under "External services".
