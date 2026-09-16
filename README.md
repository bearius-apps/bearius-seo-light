# Bearius SEO Light

**Lightweight SEO meta tags and Schema.org structured data for WordPress.**
No bloat, no upsells, no dashboard takeover.

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/bearius-seo-light?label=wordpress.org)](https://wordpress.org/plugins/bearius-seo-light/)
[![Tested up to](https://img.shields.io/wordpress/plugin/tested/bearius-seo-light)](https://wordpress.org/plugins/bearius-seo-light/)
[![Required PHP](https://img.shields.io/wordpress/plugin/required-php/bearius-seo-light)](https://wordpress.org/plugins/bearius-seo-light/)
[![License: GPL v2 or later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/gpl-2.0.html)

Bearius SEO Light does one job: it outputs correct meta tags and structured data, then gets out of the way.

There is no content analysis, no traffic light scoring, no redirect manager and no premium tier. It is built for people who know what their site needs and want a small, readable plugin that does exactly that.

**Install from WordPress.org:** [wordpress.org/plugins/bearius-seo-light](https://wordpress.org/plugins/bearius-seo-light/)

---

## Features

### Meta tags

- Meta description and canonical link
- Open Graph tags with image dimensions and alt text
- X Card tags, with an optional site handle
- Custom SEO titles that override the browser title (can be switched off)
- Per post noindex switch

### Structured data

All Schema.org output is printed as a single connected `@graph` in one JSON-LD block, so search engines see how the pieces relate instead of a pile of unrelated snippets.

| Node | Where it appears |
| --- | --- |
| `Organization` | Every page - publisher name and logo |
| `WebSite` + `SearchAction` | Front page |
| `Article` / `NewsArticle` | Single posts and pages |
| `BreadcrumbList` | Single posts |
| `CollectionPage` + `ItemList` | Category, tag and taxonomy archives |

Every node can be turned on or off separately in the settings.

### Editor

- A meta box on every public post type with live previews of the search result and the share card
- Fields for SEO title, meta description, social title, social image and noindex

### Small details that matter

- **News publisher mode** - switches posts from `Article` to `NewsArticle`. Meant only for sites that actually report news.
- **Modified date threshold** - edits made shortly after publishing (60 minutes by default) keep the original date, so fixing a typo does not send a false freshness signal.
- **Smart logo fallback** - publisher logo falls back to the theme logo, then the site icon.
- **Language aware** - `inLanguage` and `og:locale` follow the site language.

---

## What it does not do

- It does not track you or phone home
- It does not add links, badges or credits to your site
- It does not take over your dashboard
- It does not create database tables
- It does not generate a sitemap - WordPress core has done that since 5.5

---

## Plays fair with other SEO plugins

Two plugins printing the same tags is worse than one, so Bearius SEO Light steps aside instead of competing.

If any of these is active, the plugin outputs nothing and shows a notice explaining why:

Yoast SEO, Rank Math, SEOPress, All in One SEO, The SEO Framework, Slim SEO, Squirrly SEO.

> **Note:** SEO code added through a snippet manager (for example WPCode) or living in your theme cannot be detected. If tags appear twice, check there first.

---

## Requirements

- WordPress 6.0 or newer
- PHP 7.4 or newer

---

## Installation

**From the WordPress dashboard (recommended)**

1. Go to **Plugins > Add New** and search for "Bearius SEO Light".
2. Install and activate.
3. Open **Settings > Bearius SEO** and fill in the publisher name, logo and default social image.

**From this repository**

1. Download the source of the latest release.
2. Make sure the folder is named `bearius-seo-light` and upload it to `wp-content/plugins/`.
3. Activate it in **Plugins** and configure it in **Settings > Bearius SEO**.

---

## Data storage

- Settings live in a single option row: `bearius_seo_settings`
- Per post values use native post meta:
  `_bearius_seo_title`, `_bearius_seo_desc`, `_bearius_seo_social`, `_bearius_seo_image`, `_bearius_seo_noindex`

Nothing depends on a custom fields plugin. Deactivating the plugin leaves your data untouched. Deleting the plugin from the dashboard removes the option and all of the meta keys above.

---

## For developers

### `bearius_seo_schema_graph`

Filters the structured data graph before it is printed. Receives an array of nodes.

Remove breadcrumbs from the graph:

```php
add_filter( 'bearius_seo_schema_graph', function ( $nodes ) {
	return array_filter( $nodes, function ( $node ) {
		return 'BreadcrumbList' !== ( $node['@type'] ?? '' );
	} );
} );
```

### `bearius_seo_post_types`

Filters the post types that get the SEO meta box. By default that is every public post type except attachments.

Hide the meta box on pages:

```php
add_filter( 'bearius_seo_post_types', function ( $types ) {
	return array_values( array_diff( $types, array( 'page' ) ) );
} );
```

---

## Project structure

```
bearius-seo-light/
├── bearius-seo-light.php                  Bootstrap and activation
├── uninstall.php                          Cleanup on delete
├── readme.txt                             WordPress.org readme
├── assets/
│   ├── css/admin.css
│   └── js/admin.js
└── includes/
    ├── helpers.php                        Settings and shared helpers
    ├── class-bearius-seo-compat.php       Conflict detection
    ├── class-bearius-seo-settings.php     Settings screen
    ├── class-bearius-seo-metabox.php      Editor meta box
    ├── class-bearius-seo-frontend.php     Meta tags and robots
    └── class-bearius-seo-schema.php       JSON-LD graph
```

---

## Support and contributions

- **Support questions:** please use the [WordPress.org support forum](https://wordpress.org/support/plugin/bearius-seo-light/)
- **Bug reports and suggestions:** issues and pull requests are welcome here

The plugin passes Plugin Check without errors.

---

## Changelog

### 1.0.0

- Initial release

---

## License

GPL-2.0-or-later. See [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).

---

Made by [Bearius](https://bearius.org) - small, focused WordPress plugins.
