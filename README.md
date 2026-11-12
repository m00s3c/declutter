# Declutter

**Declutter your WordPress admin.**

Declutter hides dashboard widgets, toolbar items and admin menu entries for everyone who logs in, so colleagues and clients see a clean admin instead of promo widgets, a crowded toolbar and menu tabs they don't need.

It is cosmetic only. No capabilities are removed and no screens are blocked. Hidden menu entries still open by direct URL.

## Features

- **Three independent sections**, each with its own on/off switch:
  - **Dashboard widgets**: hide any Dashboard widget, including the Welcome panel.
  - **Toolbar**: hide admin bar items in wp-admin and on the front end. Children go with their parent.
  - **Admin menu**: hide or rename top-level and submenu entries.
- **Applies to everyone**, administrators included, or limit each section to chosen roles.
- **Auto-discovery**: lists every widget, toolbar node and menu entry it has seen, with clean titles (no count bubbles or icons). "Refresh lists" rescans in one click.
- **Sensible defaults** so upload and activate is enough:
  - Dashboard keeps only At a Glance, Wordfence and WordPress's PHP/browser warnings.
  - Toolbar drops the WordPress logo, the comments bubble and common plugin clutter (Yoast, Rank Math, LiteSpeed, WP Rocket, WPForms, MonsterInsights, UpdraftPlus and more).
  - Admin menu stays untouched until you turn it on.
- **Choose what happens to new items**: show or hide widgets and nodes that appear later, for example after installing a plugin.
- **Export / import** settings as JSON to reuse a config across sites. **Reset to defaults** any time.
- Can't lock you out: the account menu, Log Out and Settings > Declutter are always shown.

## Requirements

- WordPress 5.8+
- PHP 7.4+

## Installation

1. Download the zip and upload it under **Plugins > Add New > Upload Plugin**, or copy the `declutter` folder to `wp-content/plugins/`.
2. Activate. Defaults apply immediately.
3. Fine-tune under **Settings > Declutter**.

## How it works

| Section   | Hook                          | Method |
|-----------|-------------------------------|--------|
| Dashboard | `do_meta_boxes` (dashboard)   | `remove_meta_box()`; Welcome panel via its action |
| Toolbar   | `wp_before_admin_bar_render`  | `WP_Admin_Bar::remove_node()` |
| Menu      | `admin_head`                  | CSS class on the entry; labels renamed in place. Access checks have already run, so nothing is blocked |

Detected items are stored in the `declutter_seen` option. Only users with `manage_options` update that list. Settings live in `declutter_settings`. Both are deleted on uninstall.

## License

GPL-2.0-or-later.
