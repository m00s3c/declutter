=== Declutter ===
Contributors: webmastersh
Tags: admin, dashboard, toolbar, admin menu, clean up
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Declutter your WordPress admin. Hide dashboard widgets, toolbar items and menu entries.

== Description ==

Declutter cleans up wp-admin for everyone who logs in: colleagues, clients, your boss. No more promo widgets, a toolbar packed with plugin buttons, or menu tabs nobody needs.

It is purely cosmetic. Declutter never removes capabilities or blocks a screen. A hidden menu entry still opens by direct URL.

Three separate sections, each with its own on/off switch. A section that is switched off is left alone.

* **Dashboard widgets** - hide any widget on the Dashboard home screen, including the Welcome panel.
* **Toolbar** - hide items from the admin bar, in wp-admin and on the front end. Hiding a parent hides its children.
* **Admin menu** - hide or rename sidebar entries and sub-entries.

Every section lists the items Declutter has detected on your site, each with a simple Hide switch. Each section can apply to all users (administrators included) or only to the roles you pick.

**Works out of the box.** Activate it and you get:

* Dashboard: everything hidden except At a Glance, Wordfence widgets and WordPress's own PHP/browser warnings.
* Toolbar: the WordPress logo menu, the comments bubble and common plugin clutter (SEO, caching, forms, analytics and similar) hidden. Your account menu, site name, Edit, Search, + New and Updates stay.
* Admin menu: untouched until you switch it on.

**Move configs between sites.** Export your settings as JSON and import them on the next site. Reset to defaults any time.

== Installation ==

1. Upload the `declutter` folder to `/wp-content/plugins/`, or upload the zip under Plugins > Add New > Upload Plugin.
2. Activate it. The default config applies right away.
3. To fine-tune, go to Settings > Declutter.

== Frequently Asked Questions ==

= Does this change permissions? =

No. Nothing is removed from any role. Hidden items are only hidden from view.

= Why isn't a widget or toolbar item listed? =

Declutter lists what it has seen. Click "Refresh lists" under Tools to rescan. Toolbar items that only appear on the front end show up after you visit the site while logged in.

= Can I lock myself out? =

No. Your account menu and Log Out in the toolbar, and Settings > Declutter in the menu, can't be hidden.

= Can I reorder the admin menu? =

Not in 1.0. Declutter hides and renames. Pair it with a menu editor plugin if you need reordering.

= What happens when I delete the plugin? =

Its settings are removed from the database.

== Changelog ==

= 1.0.2 =
* Settings sections are collapsible and stay open or closed after you save.

= 1.0.1 =
* Polished the settings screen: native WordPress buttons, consistent control sizing and spacing.

= 1.0.0 =
* First release.
