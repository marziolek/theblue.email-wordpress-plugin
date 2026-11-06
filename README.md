# theblue.email Forms

The WordPress plugin for [theblue.email](https://theblue.email) accounts:

- a **block** and a **shortcode** that place your theblue.email signup forms,
- **Allow this site** in one click when a form does not list your hostname yet,
- **Gravity Forms** (a feed add-on on the Add-On Framework) and **Contact Form 7** (a tab
  in the form editor) sending newsletter signups to a theblue.email form, with the
  platform's honeypot, proof of work, rate limits and double opt-in.

Works on every theblue.email plan with a Website key. The user-facing description is
[readme.txt](readme.txt); the design is [docs/PLAN.md](docs/PLAN.md); releasing to
WordPress.org and the Gravity Forms directory is [docs/PUBLISHING.md](docs/PUBLISHING.md).

## Develop

```bash
composer install
composer qa
```

`composer qa` checks the version fields agree, runs WordPress Coding Standards, PHPStan
level 6, the PHPUnit suite and the Node test that proves the in-browser proof-of-work
solver finds numbers theblue.email accepts.

The plugin needs the platform routes under `/api/tbe/v1/site/` (theblue.email 1.0.42 or
later). To test against a local or staging platform, define the base URL in
`wp-config.php`:

```php
define( 'TBEF_PLATFORM_URL', 'https://theblue.email.test' );
```

## Layout

```
theblue-email-forms.php     header, constants, boot
includes/                   one class per file, TheBlueEmail\Forms\
includes/integrations/      Gravity Forms (bootstrap + GFFeedAddOn) and Contact Form 7
blocks/form/                block.json, server render, plain-JS editor
assets/js/tbef-protect.js   proof of work for Gravity Forms and CF7 forms
languages/                  .pot, Polish .po/.mo and the editor's JSON
tests/                      PHPUnit (Unit/), Node (js/), stubs
.wordpress-org/             directory banner and icons
.github/workflows/          CI, release zip, WordPress.org deploy
```
