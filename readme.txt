=== Bearius SEO Light ===
Contributors: bearius
Tags: seo, schema, structured data, open graph, meta tags
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight SEO meta tags and Schema.org structured data. No bloat, no upsells, no dashboard takeover.

== Description ==

Bearius SEO Light does one job: it outputs correct meta tags and structured data, then gets out of the way.

There is no content analysis, no traffic light scoring, no redirect manager and no premium upsell. If you want those, use one of the large SEO suites. This plugin is for people who already know what they are doing and want a small, readable plugin that adds the tags a site actually needs.

= What it adds =

* Meta description, canonical link, Open Graph and X Card tags
* Schema.org data as a single connected graph: Organization, WebSite with search action, Article or NewsArticle, BreadcrumbList and CollectionPage for archives
* A per post box with live previews of the search result and the share card, plus SEO title, meta description, social title, social image and a noindex switch
* News publisher mode that switches posts from Article to NewsArticle
* A modified date threshold, so fixing a typo right after publishing does not look like a real update

= What it does not do =

* It does not track you or phone home
* It does not add links, badges or credits to your site
* It does not take over your dashboard
* It does not create database tables

= Works with other SEO plugins =

It does not. If Yoast, Rank Math, SEOPress, All in One SEO, The SEO Framework, Slim SEO or Squirrly is active, this plugin stops printing anything and tells you why in the admin. Two plugins writing the same tags is worse than one, so it steps aside instead of competing.

= Data =

All values are stored in native post meta and a single option row. Nothing depends on a custom fields plugin, and your content stays readable if you deactivate this one.

== Installation ==

1. Upload the plugin to your plugins directory, or install it through the plugin screen.
2. Activate it.
3. Open Settings then Bearius SEO and fill in the publisher name, logo and default social image.

== Frequently Asked Questions ==

= I get duplicate tags or an error on the front end =

The plugin detects other SEO plugins, but it cannot detect SEO code you added yourself through a snippet manager such as WPCode, or code living in your theme. If your tags appear twice, look there first.

= Does this replace Yoast or Rank Math? =

No, and it is not trying to. Those are full suites with content analysis, redirects and much more. This plugin only outputs tags and structured data.

= Should I turn on news publisher mode? =

Only if you actually publish news. NewsArticle is meant for reporting on current events. Using it on evergreen content, product pages or a personal blog misrepresents what you publish.

= Why is my modified date not updating? =

Edits made within the threshold set in the settings, sixty minutes by default, keep the original publication date. This stops small corrections from sending a false freshness signal. Lower the threshold or set it to one minute if you want every edit to count.

= Does it generate a sitemap? =

No. WordPress has generated sitemaps in core since version 5.5 and that is enough for most sites.

= Can I change what goes into the structured data? =

Yes. The graph passes through the bearius_seo_schema_graph filter before it is printed, and the post types that get the meta box can be changed with bearius_seo_post_types.

== Changelog ==

= 1.0.0 =
* Initial release.
