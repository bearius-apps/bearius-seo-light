=== Bearius SEO Light ===
Contributors: bearius
Tags: seo, sitemap, google news, schema, multilingual
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Light SEO for publishers: meta tags, Schema.org, multilingual XML and Google News sitemaps, robots.txt, IndexNow and a fact based audit.

== Description ==

Bearius SEO Light gives a site the SEO groundwork it actually needs, in a small and readable plugin. It is built for news sites, small publishers and multilingual sites, and everything that big suites usually sell as an add-on, such as the Google News sitemap, is included for free.

There is no traffic light scoring of your writing and no keyword density. The audit reports facts that can be checked: something is missing, duplicated or blocking search engines.

= Meta tags and structured data =

* Meta description, canonical link, Open Graph and X Card tags, each one switchable
* Correct canonical links on paginated archives and on posts split into pages
* Schema.org data as one connected graph: Organization, WebSite with search action, Article or NewsArticle, BreadcrumbList and CollectionPage
* A box in the editor with live previews of the search result and the share card
* SEO title, description and noindex for categories, tags and other archives
* og:locale:alternate for every language version of a page

= Sitemaps =

* An XML sitemap that replaces the one built into WordPress, readable in every browser without XSLT, which browsers are removing
* A preview in the settings with every sitemap file and clickable addresses
* A Google News sitemap with articles from the last two days, at most 1000
* Every language version is listed, with separate files per language, for Bearius Multilingual, Polylang, WPML and TranslatePress (including the free version of TranslatePress)
* Noindex content, empty archives and password protected entries are left out
* Fast on large sites: files are split by ID ranges and their map is cached until content changes
* The old WordPress sitemap address and the address used by Yoast and Rank Math redirect to the new one

= Robots.txt and AI crawlers =

* Keeps the WordPress rules, adds the sitemap lines and your own rules
* A switch for each AI crawler, presets (allow all, block training but allow AI search, block all) and a plain explanation of what each choice means. Nothing changes until you decide
* A rule that would block the whole site for everyone or for Google cannot be saved by accident
* Warnings when a physical robots.txt file or a subdirectory install makes the rules ineffective

= Audit =

* Technical checks of settings, sitemaps and robots.txt, also shown in Tools then Site Health
* The home page and the latest post are fetched on desktop and on mobile, to find duplicated canonical tags, descriptions or structured data added by the theme or a snippet
* Performance hints read from the HTML: response time, page weight, render blocking files, lazy loaded first image, images without dimensions, document size
* A content audit on live database queries: duplicate titles and descriptions, missing images, long titles, very short content and images without alt text
* An AI view that shows which AI crawlers may visit and what text they receive without JavaScript
* A score of our own, with a printable report that can be saved as PDF
* One click fixes for findings the plugin controls itself, always with a clear note of what changes
* Recognises maintenance and coming soon pages, and pages served from an old cache
* A dashboard widget, notices for critical problems and optional e-mail alerts

= Feeds, tags, comments and archives =

* RSS: switch off comment feeds, archive feeds or all feeds, add the featured image, add a source line with links, delay new posts and leave out noindex entries or chosen categories
* Tags: hide them from visitors (tag archives answer 410 Gone) or switch them off completely; nothing is deleted
* Comments: switch off pingbacks, close new comments or switch comments off everywhere; nothing is deleted
* Author and date archives: indexed, not indexed or switched off
* Every option comes with a plain explanation of what it changes

= Also included =

* IndexNow notifications for Bing, Yandex, Seznam, Naver and Yep, off until you switch them on
* Verification tags for Google Search Console, Bing, Seznam, Yandex, Naver, Baidu and Pinterest
* A getting started checklist on the Overview screen, with no wizard and no redirect after activation
* An optional "Powered by Bearius" footer link, off by default, nofollow, and shown only once even with several Bearius plugins
* Breadcrumbs as a block, a shortcode and a template function
* An importer for Yoast SEO, Rank Math and All in One SEO data

= Works with other SEO plugins =

When Yoast, Rank Math, SEOPress, All in One SEO, The SEO Framework, Slim SEO or Squirrly is active, only the modules that would duplicate it are paused: meta tags, structured data, XML sitemap, robots.txt and verification. The Google News sitemap, IndexNow, the audit and the importer keep working, so you can add a free news sitemap to a site that runs another SEO plugin.

= Data =

All values are stored in native post meta, term meta and a few option rows. The plugin creates no database tables.

== External services ==

This plugin connects to one external service, and only after you switch it on.

**IndexNow** is used to tell search engines that a page was published, updated or removed. When the IndexNow option on the Indexing screen is on, the plugin sends a request to https://api.indexnow.org/indexnow each time a public entry is published, updated, unpublished or moved to the trash. The request contains the host name of your site, the IndexNow key generated by the plugin, the address of the key file and the addresses of the changed entries. Nothing else is sent. The service is operated by Microsoft and shared with the other participating search engines. Terms of use: https://www.indexnow.org/terms. Privacy statement: https://www.microsoft.com/en-us/privacy/privacystatement

The audit sends requests only to your own site, to read your pages, sitemaps and robots.txt.

== Installation ==

1. Upload the plugin to your plugins directory, or install it through the plugin screen.
2. Activate it.
3. Open Settings then Bearius SEO, fill in the publisher details and switch off any module your theme already covers.
4. Run the checks on the Audit screen.

== Frequently Asked Questions ==

= I get duplicate tags =

Run the checks on the Audit screen: it fetches your pages and shows exactly which tags appear twice. The plugin cannot see SEO code added through a snippet manager or the theme, so switch off the matching option under Meta and schema, or remove the other code.

= Does it replace the WordPress sitemap? =

Yes, while the XML sitemap module is on. The new sitemap lives at /sitemap.xml and the old /wp-sitemap.xml address redirects to it. Switch the module off to go back to the WordPress sitemap.

= Which multilingual plugins are supported? =

Bearius Multilingual, Polylang, WPML and TranslatePress. Each language gets its own sitemap files and its own Google News file. For TranslatePress the translated addresses are added even without its paid SEO add-on.

= Why is IndexNow off by default? =

It sends data to an outside service, and WordPress.org asks plugins to do that only after the site owner agrees. Switching it on is that consent. Google does not use IndexNow; it reads your sitemaps.

= Will e-mail alerts always arrive? =

The plugin uses the mail function of your server. Many hosts limit or block it, and messages may land in spam. Send a test e-mail from the Notifications screen first; an SMTP plugin makes delivery reliable. Critical problems are always shown in the dashboard as well.

= What is the Bearius score? =

A simple measure of the problems found: twenty points off per critical problem, six per warning and two per suggestion. It is not a Google or PageSpeed score.

= Does the audit slow down my site? =

No. Visitors never trigger it. The full check runs once a day in the background or when you start it, and the content audit runs only when you open it.

= Should I turn on news publisher mode? =

Only if you actually publish news. NewsArticle is meant for reporting on current events.

= Can I change what goes into the structured data? =

Yes. The graph passes through the bearius_seo_schema_graph filter before it is printed, and the post types that get the editor box can be changed with bearius_seo_post_types.

== Changelog ==

= 1.3.0 =
* New: complete translations into Polish, German, Spanish and French, included in the plugin.
* New: the plugin screens follow the language of your user profile, not the language of the site, so you can work in your language on a site in any language.
* New: a personal language choice on the Overview screen, visible only to you.

= 1.2.3 =
* Changed: Bearius SEO now has its own menu item with quick links instead of a page under Settings. Old bookmarks redirect.
* New: a checks panel in the editor: titles, description, featured image size for Discover, image text, indexing, Google News and links at a glance.
* New: the audit checks that the IndexNow key file can be opened.
* Fixed: sitemap styling did not load on servers that answer .css files themselves.
* Fixed: tags stayed selected in the sitemap after updating from older versions.
* Fixed: option cards overlapped on some screens.

= 1.2.2 =
* Security: the report accepts only a valid color from add-ons that brand it.

= 1.2.1 =
* New: extension points for add-ons: settings tabs, editor tools, audit findings, content audit actions, crawler view tools and report branding.

= 1.2.0 =
* New: Feeds and comments screen with RSS options, tag hiding, comment controls and author and date archive settings, each with a plain explanation.
* New: RSS featured images (media:content), source line, publishing delay and exclusions.
* New: audit checks for hreflang links on multilingual sites, large image previews and featured images too small for Google Discover, and a missing site icon.
* New: getting started checklist on the Overview screen, including one click IndexNow consent.
* New: optional "Powered by Bearius" footer link, nofollow, printed once across all Bearius plugins.
* Changed: hidden tags are left out of the sitemap automatically.

= 1.1.3 =
* New: the audit recognises maintenance and coming soon pages and reports one clear finding instead of missing tags. A maintenance page sent with status 200 is marked critical, because search engines may index it in place of your content.
* New: pages that do not contain the plugin's tags, for example an old cached copy, are reported as such instead of as missing canonical and description.
* New: one click fixes for findings the plugin controls itself: turning on missing tags, switching off our duplicates, loading the featured image right away, allowing search engines, the Google News sitemap and clearing the page cache of supported cache plugins. Nothing changes without a click.
* New: "Load the featured image right away" option under Meta and schema.

= 1.1.2 =
* Changed: sitemaps are styled with CSS instead of XSLT, so browsers no longer show a deprecation warning.
* New: preview of every sitemap file and of the Google News articles, with clickable links, on the Sitemaps screen.
* New: a switch for each AI crawler in AI visibility, with presets and a plain explanation of training versus AI search.
* New: the Google News category filter lists only categories of the default language, labelled in your admin language; translations are left out automatically.
* Changed: tags are no longer included in the sitemap by default.
* Fixed: posts and pages could drop out of the sitemap after saving the settings for the first time.
* Fixed: settings are created on every site after a network activation.

= 1.1.1 =
* Security: the audit fetches only addresses on this site, also after redirects, and caps the size of a fetched page.
* Security: settings are always sanitized, also when saved without the screen form.
* Security: category and tag SEO fields check the permission to edit that term.
* Security: the importer no longer unserializes data a second time.
* Security: sitemap, stylesheet and IndexNow key responses send a nosniff header.
* Hardening: the Google News sitemap is cached for five minutes, only one full check runs at a time, and the IndexNow queue is capped.
* Fixed: Rank Math noindex flags were not recognised by the importer.
* Fixed: content types and archives that cannot be viewed on the front end are left out of the sitemaps.
* Fixed: search phrases in breadcrumbs were escaped twice.

= 1.1.0 =
* New: XML sitemap with separate files per language, replacing the WordPress sitemap.
* New: Google News sitemap with one file per language under a shared index.
* New: support for Bearius Multilingual, Polylang, WPML and TranslatePress.
* New: robots.txt rules, an AI crawler policy and protection against blocking the whole site.
* New: IndexNow notifications, off by default.
* New: verification tags for Google, Bing, Seznam, Yandex, Naver, Baidu and Pinterest.
* New: audit with technical checks, desktop and mobile page scans, performance hints, a content audit, an AI view, a score and a printable report.
* New: dashboard widget, notices and optional e-mail alerts for critical problems.
* New: SEO fields for categories, tags and other archives.
* New: breadcrumbs block, shortcode and template function.
* New: importer for Yoast SEO, Rank Math and All in One SEO.
* New: og:locale:alternate tags for language versions.
* New: redesigned settings screen with a switch for every module and every tag.
* Changed: when another SEO plugin is active, only the overlapping modules are paused.
* Fixed: entries marked noindex are no longer listed in the sitemap.
* Fixed: paginated archives point their canonical link to the current page instead of page one.

= 1.0.0 =
* Initial release.
