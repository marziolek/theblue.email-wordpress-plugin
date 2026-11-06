# Publishing theblue.email Forms

Written 2026-11-01. Where the plugin can be listed, what each place requires, and the
steps. Nothing here has been submitted yet.

## The short answer

| Where | Can we list it? | How |
|---|---|---|
| **WordPress.org plugin directory** | Yes. This is the main channel, and the only one that installs and updates the plugin on tenants' sites | Submit the zip, pass manual review, then deploy tags to SVN (`wporg-deploy.yml`) |
| **Gravity Forms Add-On directory** (gravityforms.com) | Yes, as a third-party add-on. The directory lists add-ons wherever they are hosted, so it links to the WordPress.org listing | Submit through the Gravity Forms add-on submission form once the plugin is live on WordPress.org |
| **Contact Form 7** | There is no Contact Form 7 marketplace or add-on directory. Its users find integrations on WordPress.org, by searching "contact form 7" | The WordPress.org listing is the listing: "contact form 7" is one of the five tags in `readme.txt` |

One plugin, not three: the Gravity Forms and Contact Form 7 integrations load only when
those plugins are active, so a site without them carries no code for them. A separate
"theblue.email for Gravity Forms" plugin is possible later if the Gravity Forms directory
asks for one; the add-on class would move as it is.

## 1. Before the first submission

These cannot be done from this repository:

1. **Platform first.** Deploy theblue.email 1.0.42 (Website keys and `/api/tbe/v1/site/*`).
   The plugin answers 404 on every call without it.
2. **"Tested up to" in `readme.txt`** must be the current WordPress major release on the
   day of submission. It says 6.9 now; set it to the latest version at
   https://wordpress.org/download/ and run the plugin on that version once.
3. **A WordPress.org account for the brand** (the `Contributors:` line says
   `theblueemail`; change it to the real username). Turn on two-factor authentication:
   WordPress.org requires it for committers.
4. **CI green**, including the Plugin Check job and a manual pass with the
   [Plugin Check plugin](https://wordpress.org/plugins/plugin-check/) on a clean site.
5. **Hand test** on a clean WordPress with the latest Gravity Forms and Contact Form 7:
   connect, place a block, allow the site, one Gravity Forms feed with a consent
   checkbox, one Contact Form 7 form with `[acceptance newsletter optional]`, and confirm
   both signups arrive as pending subscribers on theblue.email. Then repeat one signup with
   JavaScript off and check the activity log says "browser check missing".
6. **Screenshots** `screenshot-1.png` ... `screenshot-4.png` in `.wordpress-org/`,
   matching the captions in `readme.txt`. They need a real site, so they are not made yet.

## 2. WordPress.org review: what they check, and where we stand

| Guideline | Status |
|---|---|
| GPL-compatible licence | GPL-2.0-or-later in the header and `readme.txt` |
| Software as a service is allowed, and must be documented | "External services" in `readme.txt` lists every call: what is sent, when, to which URL, with links to the terms and privacy policy |
| No remote code except from the service itself | `tbe-embed.js` is loaded from theblue.email, the service the plugin is for. Disclosed with the reason. If a reviewer objects, ship a copy in `assets/js/` and release on every protocol change (owner decision D5) |
| No tracking without consent | Nothing is sent until an administrator connects; visitors' browsers contact theblue.email only on pages with a form |
| No front-end credit links without permission | The plugin adds none. The "Powered by theblue.email" line on Free and Starter forms is rendered by the service inside its form; the readme says so. **Most likely review question.** Answer: it is part of the service's own output and plan, like any hosted form; the site owner removes it by upgrading, not through the plugin |
| Escaping, sanitising, nonces, capabilities | Every admin action has a nonce and `manage_options`; output is escaped at the echo; REST routes declare permission callbacks; the one public route (`/tbef/v1/challenge`) is rate limited and reveals only a puzzle |
| Unique prefixes | `tbef_`, `TBEF_`, `TheBlueEmail\Forms\` |
| Text domain equals the slug | `theblue-email-forms` |
| No direct file access | Every PHP file starts with `defined( 'ABSPATH' ) \|\| exit;` (`uninstall.php`: `WP_UNINSTALL_PLUGIN`) |
| Trademarks | "theblue.email" is the owner's; "Gravity Forms" and "Contact Form 7" appear only in the description and tags, not in the plugin name or slug |
| Bundled translations | Polish ships in `languages/` and is loaded with `load_plugin_textdomain()`, which Plugin Check reports as a warning. Remove both once translate.wordpress.org has the Polish set |

The slug is permanent once approved. Request `theblue-email-forms` in the submission.

## 3. Submitting

1. Tag nothing yet. Build the zip: `bin/build-zip.sh`.
2. Upload `dist/theblue-email-forms.zip` at https://wordpress.org/plugins/developers/add/.
3. Answer the reviewer by email; each round can take days, the first one weeks. Fix in
   this repository and re-upload the zip when asked.
4. After approval WordPress.org gives SVN access. Add repository secrets
   `SVN_USERNAME` and `SVN_PASSWORD` (an application password from the WordPress.org
   profile, not the login password).

## 4. Every release after that

1. Bump `Version:`, `TBEF_VERSION`, `Stable tag` and `blocks/form/block.json` together,
   add a changelog entry, and run `php bin/check-versions.php`.
2. Push a tag `vX.Y.Z`. `release.yml` checks the versions against the tag and drafts a
   GitHub release with the zip.
3. Publish the draft. `wporg-deploy.yml` copies the build and `.wordpress-org/` to SVN
   and tags it. Sites update within hours.
4. A readme-only change (for example "Tested up to" after a WordPress release) on `main`
   goes out through `wporg-assets.yml` without a new version.

## 5. Gravity Forms Add-On directory

Gravity Forms has listed third-party add-ons since 2018 "regardless of where they are
hosted", through a submission form on gravityforms.com. What makes an add-on acceptable,
as far as their developer documentation shows:

- built on the **Add-On Framework**: `Gravity_Forms_Addon` extends `GFFeedAddOn`,
  is registered with `GFAddOn::register()` on `gform_loaded`, and declares
  `$_min_gravityforms_version = '2.5'`;
- feed settings through the framework (field map, feed condition), feed notes on entries,
  no direct database access;
- a public listing with documentation and support, which the WordPress.org page provides.

The current listing criteria and form were not reachable from this session
(docs.gravityforms.com is outside its network allowance), so check them on the day:
https://www.gravityforms.com/add-ons/ and the "submit an add-on" link from the Gravity
Forms developer documentation.

Prepare for the submission: the WordPress.org URL, a 3-line description ("Send Gravity
Forms entries to theblue.email signup forms, with double opt-in, consent conditions and
the service's spam protection"), the icon from `.wordpress-org/`, the support URL, and a
note that it works on every theblue.email plan including Free.

## 6. Contact Form 7

Nothing to submit: Contact Form 7 has no add-on directory. Two things help discovery:

- the `contact form 7` tag in `readme.txt` (WordPress.org allows five tags; it is one);
- the readme section "Contact Form 7", which matches searches like "contact form 7
  newsletter".
