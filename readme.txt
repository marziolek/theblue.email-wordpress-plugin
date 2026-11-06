=== theblue.email Forms ===
Contributors: theblueemail
Tags: newsletter, signup form, email marketing, gravity forms, contact form 7
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Place your theblue.email signup forms on your site with a block or a shortcode, and collect newsletter signups from Gravity Forms and Contact Form 7.

== Description ==

theblue.email Forms connects your WordPress site to your [theblue.email](https://theblue.email) account, the email marketing service. It does three things:

* **A block and a shortcode for your signup forms.** Pick a form from your account in the block editor, or paste `[theblue_email_form key="..."]` anywhere shortcodes work. No copying HTML.
* **"Allow this site" in one click.** A theblue.email form only shows on the sites listed in its allowed domains. The plugin tells you when a form you placed does not allow your site, and adds it for you.
* **Signups from Gravity Forms and Contact Form 7.** Send the people who fill in your contact, order or registration forms to a theblue.email form, typically when they tick a "Send me the newsletter" box.

Every signup gets the protection of a theblue.email form, whichever form it comes from:

* **Double opt-in.** Nobody joins a list until they click the link in the confirmation email.
* **Honeypot.** A hidden field that people leave empty and naive bots fill in.
* **Proof of work.** The visitor's browser solves a small puzzle in the background before a signup is accepted. Visitors see nothing; scripts that post addresses in bulk get nothing through.
* **Rate limits.** Per visitor, per address and per form, on your site and again on theblue.email.
* **Protection for a form under attack.** When a form suddenly gets many signups nobody confirms, theblue.email asks visitors to pass an extra check first.

A contact form or an order still goes through as usual whatever happens to the newsletter signup: the plugin never blocks the form it is attached to.

= What you need =

A theblue.email account on any plan, including Free, and a **Website key** from Integrations in the theblue.email app. A Website key can only list your forms, allow your site on them and pass on signups. It cannot read your subscribers or send email.

= Gravity Forms =

Open a form, then Settings, theblue.email, and add a feed: choose the theblue.email form, map the email and first name fields, and use a consent checkbox as the feed condition. Built on the Gravity Forms Add-On Framework, version 2.5 or later.

= Contact Form 7 =

Edit a contact form and open the theblue.email tab: switch it on, choose the theblue.email form, and name the email, first name and consent fields. For consent, add a field such as `[acceptance newsletter optional] Send me the newsletter [/acceptance]`.

== External services ==

This plugin connects to **theblue.email**, the email marketing service it is made for, operated by the owner of theblue.email. It sends nothing until you connect your account.

* **When you connect, and when forms are listed or refreshed** in the admin, your site sends the Website key to `https://theblue.email/api/tbe/v1/site/` and receives your account name, plan and forms.
* **When you click "Allow this site"**, your site sends its own hostname to theblue.email.
* **On pages with a theblue.email form (block or shortcode)**, the visitor's browser loads the form script `https://theblue.email/embed/tbe-embed.js` and the form from theblue.email. When the visitor signs up, the browser sends the address and first name they typed straight to theblue.email.
* **On Gravity Forms and Contact Form 7 forms that send signups**, the visitor's browser asks your site for a proof-of-work puzzle (your site fetches it from theblue.email). After the form is submitted, and only if the integration is switched on and its consent condition is met, your site sends the address, first name, the visitor's IP address, the page URL and, behind Cloudflare, the visitor's country to theblue.email.

The form script is loaded from theblue.email rather than shipped in the plugin on purpose: the service updates its spam protection there, and every site gets the change at once.

Forms on the Free and Starter plans show a small "Powered by theblue.email" link, rendered by the service as part of the form; it is removed on the Pro and Business plans. The plugin adds no link of its own.

theblue.email [terms of service](https://theblue.email/regulamin/) and [privacy policy](https://theblue.email/privacy-policy/).

== Privacy ==

The plugin stores, in your site's database: the Website key (encrypted with a key derived from your site's security salts), your account name, email and plan, the list of your forms, and the last 50 signups it passed on from Gravity Forms and Contact Form 7 with the address masked (a***@example.com). For Gravity Forms it keeps the visitor's IP address with the entry only until the feed has run. Uninstalling removes all of it.

Settings, Privacy offers suggested text for your privacy policy.

== Installation ==

1. Install and activate the plugin.
2. In theblue.email, open Integrations and generate a Website key.
3. In WordPress, open theblue.email in the admin menu, paste the key and connect.
4. Add the "theblue.email form" block to a page, or set up Gravity Forms or Contact Form 7 as described above.

== Frequently Asked Questions ==

= The form shows "Add this domain in theblue.email settings" =

The form does not allow your site yet. Open theblue.email in the admin menu and click "Allow this site" next to the form, or select the block and use the button in its settings. `www.example.com` and `example.com` are different sites.

= A form I placed shows nothing =

It is a draft in theblue.email. Publish it there; the page shows it right away, with no need to save the page again.

= Do signups from Gravity Forms or Contact Form 7 need JavaScript? =

Yes, for the proof-of-work check. Without JavaScript the contact form still sends, but theblue.email refuses the newsletter signup. The theblue.email page in the admin menu lists recent signups and why any was refused.

= Which plans does this work on? =

Every theblue.email plan, including Free. Forms count towards your plan's live form limit as usual.

= My site is behind Cloudflare =

Add `define( 'TBEF_BEHIND_CLOUDFLARE', true );` to wp-config.php so the visitor's real IP is used for rate limits. Behind another proxy, use the `tbef_visitor_ip` filter.

= How do I remove the theblue.email link from the toolbar? =

Open theblue.email in the admin menu and untick "Show a theblue.email link in the toolbar" under Display. Only people who can manage the site see the link.

= Is my key safe? =

It is stored encrypted and never shown again in full. Code running on your site can still read it, which is why a Website key can do nothing but forms. Revoke it in theblue.email at any time.

== Screenshots ==

1. The theblue.email page: the connection, your forms with their shortcodes, and the "Allow this site" button.
2. The block in the editor, with the form chosen in the sidebar.
3. A Gravity Forms feed sending signups to a theblue.email form.
4. The theblue.email tab in the Contact Form 7 editor.

== Changelog ==

= 1.1.0 =
* The theblue.email page is now its own item in the admin menu, with an icon. Links to the old Settings page redirect there.
* A theblue.email link in the toolbar for people who can manage the site. Switch it off under Display on the theblue.email page.
* The release zip no longer includes development files when built on Windows.

= 1.0.0 =
* First release: block, shortcode, one-click allowed domain, Gravity Forms feed add-on, Contact Form 7 integration, Site Health test.

== Upgrade Notice ==

= 1.1.0 =
The theblue.email page moves from Settings to its own admin menu item, and a toolbar link is added (it can be switched off).

= 1.0.0 =
First release.
