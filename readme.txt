=== Better Find and Replace - AI-Powered Suggestions ===
Contributors: codesolz, m.tuhin
Tags: database, search replace, search, replace, search and replace
Donate link: https://codesolz.net/our-products/wordpress-plugin/real-time-auto-find-and-replace/?utm_source=wordpress.org&utm_medium=README_DONATE_BTN
Requires at least: 5.2
Tested up to: 7.0
Stable tag: 2.0.0
License: GPL-3.0+
License URI: http://www.gnu.org/licenses

Search and replace text, images, URLs, footer credits, code blocks or jQuery-Ajax content in real time or in Database, easy user-interface

== Description ==

= Smart Search, Replace & Media Tool (with AI) for WordPress =

[Better Find and Replace](https://codesolz.net/our-products/wordpress-plugin/real-time-auto-find-and-replace/?utm_campaign=wordpress-org-visitor&utm_medium=learn_more_about_dokan&utm_source=WordPress.org) lets you easily search and replace text, HTML, links and media across your entire WordPress site — no coding needed. Perfect for database cleanup, content updates or post-migration edits.

Replace text or media in bulk with full support for serialized data, custom tables and dry-run previews. AI-powered suggestions help you rewrite or improve content instantly, making your edits smarter and faster.

Easily find and replace images using drag-and-drop and auto-regenerate thumbnails. You can also update or add  alt text, captions and metadata with the suggestion of AI for better SEO.

Want to **change content without editing your database**? Use real-time masking to update text, links or HTML before the page loads — instantly and safely.

Built for developers, agencies and site owners, individual ( everyone ) who want fast, accurate control over their content management system — all in one clean, intuitive interface.


== Key Features ==

* **Site Health Dashboard** – One score and one issue count for your whole site — broken links, missing media, 404s and more — with an activity log of every change the plugin has made.
* **Broken Link Checker** – Finds broken internal links in posts, pages and public custom post types, showing where each appears, its link text and how often it is used. Links are checked against your database, never by hammering your own site with requests.
* **Redirect Manager** – Create and manage 301 redirects with on/off switches, hit counts, and automatic loop and self-redirect prevention.
* **404 Monitor** – Optional logging of every "page not found" hit with referrer and hit count, bot traffic filtered out, and one click to turn a 404 into a redirect or find where the missing URL is still linked.
* **Replace + Redirect** – Change a URL everywhere in your content and create the matching redirect in a single previewed step, so links people already shared keep working.
* **Missing Media Detection** – Spots content pointing at images and files that no longer exist on the server, and lets you replace them.
* **Duplicate & Clone Content** – "Duplicate" and "Clone & Edit" actions on posts, pages and public custom post types, always created as a draft.
* **Code Inserts** – Add header, body-start and footer snippets (HTML, CSS, JavaScript) without editing your theme. PHP is never executed.
* **Safe Media Replacement** – Replacing a file keeps the original until the new upload has fully committed, and restores it automatically if anything goes wrong.
* **Background Scanning** – Long scans run in the background via Action Scheduler or WP-Cron, so your admin screens never wait on them.
* **AI-Powered Suggestions** - Use artificial intelligence (AI) to get smart replacement suggestions, enhancing accuracy and efficiency.
* **Easy to Use** – Clean, user-friendly interface designed for effortless navigation and configuration.
* **Search and Replace Text** – Find and replace any text across your site, whether in static or dynamic content.
* **Search and Replace Ajax/jQuery Content** – Works seamlessly with content loaded via Ajax or jQuery on the frontend.
* **Find and Replace URLs** – Quickly search and replace outdated or incorrect URLs throughout your website.
* **Replace Images and Attachment URLs** – Replace image links and attachment URLs site-wide with precision.
* **Word Masking** – Mask specific words site-wide using flexible find and replace rules.
* **Temporary Find-Replace Rules** – Create live, non-permanent replacements without altering your database.
* **Edit Footer Credit** – Remove or update footer text without modifying HTML or database content.
* **HTML Code Replacement** – Replace anything within HTML code blocks, tags, or content.
* **Real-Time Image Replacement** – Replace images instantly during page rendering for dynamic updates.
* **Comment Word Filtering** – Automatically find and replace inappropriate words in user-submitted comments.
* **Language Replacement** – Change words or phrases from one language to another across your site.
* **RegEx Supported** – Use regular expressions for complex and pattern-based search and replace operations.
* **HTML Tag & Attribute Replacement** – Locate and replace specific HTML tags or attributes throughout your content.
* **Lightning Fast Database Replace** – High-speed search and replace operations in posts, postmeta, options, and more.
* **Table Selection** – Choose specific database tables for targeted replacements.
* **Dry Run Preview** – See a preview of all replacements before applying them to the database.
* **Whole Word Match** – Replace only exact word matches in the database to avoid partial replacements.
* **Serialized Data Support** – Safely search and replace serialized data without breaking structure or integrity.
* **Remove Serialized Items** – Delete specific items from serialized arrays in the database.
* **Role-Based Access** – Assign plugin management to specific user roles for better control.
* **Gutenberg and Page Builder Compatible** – Fully supports real-time replacements inside block editors and builders.
* **Targeted DB Replacement** – Refine search by limiting database replacements to post titles, content, or excerpts.

== How to replace in DB? ==
* **Start by generating a report**: Select the **Dry Run** option located at the bottom of the settings section.
* **Review the report**: A modal window will appear, showing the specific rows and data that will be affected by the replacement.
* **Proceed if satisfied**: If the preview looks accurate and matches what you intend to replace, simply close the report window, uncheck **Dry Run**, and click the **Find & Replace** button.
* **⚠️ Attention:** Please carefully review the dry run report before making any changes. Once replacements are applied to the database, they **cannot be undone**. The PRO version includes an undo feature, but it must be installed before performing the replacement.
* **✅ Important Tip:** Always run a dry report first to ensure your search term and replacement are correct. If anything looks off, adjust the keyword and repeat the process until the preview shows the desired results.

== Pro Features ==
* **Site Maintenance Features:**
    * External link, image and embed checking — everything the free scanner cannot reach without leaving your site
    * Scheduled and background scans, so problems are found before your visitors find them
    * Bulk fixes across every occurrence at once, with AI replacement suggestions
    * Broken links grouped by cause — timeout, DNS, SSL, rate limited — instead of one long list
    * 302 and 307 redirects, regex and prefix matching, and automatic redirects when a slug changes
    * Redirect chain and loop detection across your whole redirect set, plus import / export and analytics
    * 404 analytics, grouping, referrer analysis and bulk redirect creation
    * **Safe Revision** — an AI suggestion shown as a full diff, with selective approval, merge, publish and rollback
    * **AI Content Refresher** — scans posts for outdated years, software versions, prices and dead references, and applies approved rewrites as a reversible revision
    * **Maintenance Agent** — groups related problems, ranks them, prepares a plan for your approval, runs the approved batch and reports what actually changed
    * Bulk clone with metadata selection, WooCommerce-aware and page-builder-aware
    * Conditional, ordered and environment-specific code inserts, with import / export

* **Database Replacement Features:**
    * Powerful search and replace in database
    * Ultimate solution for search & replace in serialized data & remove item 
    * Automatic backup of the search and replacement data
    * Ultimate easy solution for restore data what you have replaced by mistake
    * Ability to check & replace each item separately which going to be replaced in the database
    * Bulk Replacement on report's page, generate by dry run option
    * All tables in database
    * Search and replace **Unicode Characters** *UTF-8  ( e.g: U+0026, REČA )* in Database
    * Additional filtering options in default / custom URLs 
    * Filter new comments before inserting into Database 
    * Filter new post before inserting into Database (Good for auto post generation website)
    * Special feature to search and replace in **large table**

* **Real-Time Rendering Features:**
    * RegEx supported
    * Advance Regex - Powerful code blocks / multi-lines find and replace in real-time (masking) rendering
    * Advance Regex - Any (CSS / JS / HTML) code Block find and replacement in real-time (masking) rendering
    * Masking on Shortcodes
    * **Advance filtering options** :-
        * Case insensitive - search and replace case sensitive or insensitive
        * Whole Word - search and replace whole word 
        * Unicode - search and replace Unicode Characters
        * Skip posts / page / custom taxonomies etc.. urls
        * Skip CSS - External, Internal, Inline
        * Skip JavaScript - External, Internal
        * Skip pages - if you don't want to apply rules on any specific page
        * Skip posts - if you don't want to apply rules on any specific posts
        * Bypass rule  - keep texts unchanged on specific area with special pattern
        * Bypass rule  - keep base links / urls ( post, pages, custom taxonomies etc..) unchanged where find word exists in that URL.


= Advance Regex - Code blocks / multi lines find and replacement example - (Real-time Rendering) =
*Find code block and replace with your own or keep blank *replacement field* to remove code block. Let consider the following CSS code block for replace. Put following
code block in find field*

	<style media="screen">
        html { margin-top: 32px !important; }
        * html body { margin-top: 32px !important; }
        @media screen and ( max-width: 782px ) {
            html { margin-top: 46px !important; }
            * html body { margin-top: 46px !important; }
        }
    </style>

*Then put following code block in *Replace* field to replace the above code block*

    <style>
    .site-title a{color: red;}
    </style>


**Join the elite web professionals who enjoy [Better Find And Replace Pro!](https://codesolz.net/our-products/wordpress-plugin/real-time-auto-find-and-replace/?utm_source=wordpress.org&utm_medium=README)**

== ➡️ Basic Documentation To Get Started == 

* Setup Video Guide - How to install and setup search and replace rules
[youtube https://www.youtube.com/watch?v=nDv6T72sRfc]

<hr/><hr/>

👉 Real-time search and replace

* General options for filtering
    * [Live Demo & Documentation](https://docs.codesolz.net/better-find-and-replace/real-time-find-replace/general-options/)
* Advance options for filtering
    * [Live Demo & Documentation](https://docs.codesolz.net/better-find-and-replace/real-time-find-replace/advance-filters/)


👉 Search and replace in Database

* [Live Demo & Documentation](https://docs.codesolz.net/better-find-and-replace/search-replace-in-database/)
* Find and replace in Database tables
    * [Live Demo & Documentation](https://docs.codesolz.net/better-find-and-replace/search-replace-in-database/find-and-replace-in-tables/)
* Find and replace in Database URLs
    * [Live Demo &  Documentation](https://docs.codesolz.net/better-find-and-replace/search-replace-in-database/find-and-replace-urls/)


= Forum and Feature Request = 

<blockquote>
= For Quick Support, feature request and bug reporting = 
<ul>
    <li> Visit our website <a target="_blank" href="https://codesolz.net/?utm_source=wordpress.org&utm_medium=README&utm_campaign=real-time-auto-find-and-replace">To Get Instant Support</a></li>
    <li> For more dedicated support or feature request write to us at <a target="_blank" href="mailto:support@codesolz.net">support@codesolz.net</a> or create a ticket <a href="http://support.codesolz.net/public/create-ticket" target="_blank"> Support Center</a></li>
</ul>

= Visit our forum to share your experience or request features = 
<ul>
    <li> Visit our <a target="_blank" href="https://codesolz.net/forum/?utm_source=wordpress.org&utm_medium=README&utm_campaign=real-time-auto-find-and-replace">forum</a></li>
</ul>

= As it's open source, check our github development Status = 
<ul>
    <li> Check development status or issues in <a target="_blank" href="https://github.com/CodeSolz/real-time-auto-find-and-replace" > github.com/CodeSolz/real-time-auto-find-and-replace </a>
</ul>
</blockquote>


== Installation ==
1. Upload the real-time-auto-find-and-replace folder to the '/wp-content/plugins/' directory
2. Activate Better find and replace through the 'Plugins' menu in WordPress

== Screenshots ==

1. Add Find Rule - Plain Text
2. Add Find Rule - RegEx
3. Add Find Rule - jQuery / Ajax Text
4. List of All Masking Rules
5. URLs replacement in Database
6. Media replacement in Database
7. Dry run report
8. List of All Masking Rules with pro features 
9. Media replacer
10. Media replacer
11. Media replacer

== Changelog ==

= Version: 2.0.0 ( September 07, 2026 ) =
* **New:** Site Health dashboard — an overall health score for your site, a count of every kind of problem found, and an activity log of every change the plugin has made.
* **New:** Content Health screen — broken links and missing media in one list, showing the page each problem was found on, the link text, how many times it is used, and how urgent it is.
* **New:** Broken link checking for internal links in posts, pages and public custom post types. Links are resolved against your database instead of by requesting your own pages, so a scan cannot slow your site down.
* **New:** Fix a broken link from the list itself — replace the URL, unlink it, ignore it, or re-check it.
* **New:** Redirect Manager — create 301 redirects, switch them on and off, and see how many times each has been used. Loops and self-redirects are rejected before they can be saved.
* **New:** 404 Monitor — off until you switch it on. Records every "page not found" request with its referrer and hit count, filters out bot traffic, and turns any entry into a redirect with one click, or shows you where the missing URL is still linked from.
* **New:** Replace + Redirect — change a URL across your content and create the matching redirect in one previewed operation, so links people have already shared keep working.
* **New:** Missing media detection — finds content pointing at images and files that are no longer on the server, so you can replace them.
* **New:** "Duplicate" and "Clone & Edit" actions for posts, pages and public custom post types. The copy is always created as a draft.
* **New:** Code Inserts — global header, body-start and footer snippets in HTML, CSS or JavaScript, each with its own on/off switch and validated before it is saved. PHP is never executed.
* **New:** Scans and checks run in the background through Action Scheduler when it is installed, or WP-Cron otherwise, so long scans never block your admin screens.
* **New:** Every maintenance action is written to an activity log, and content changes create a post revision you can roll back to.
* **New:** AI Content Refresher preview page, showing what the PRO scanner finds.
* **New (PRO):** External link, image and embed checking, with scheduled scans, bulk fixes, AI replacement suggestions, and failures grouped by cause — timeout, DNS, SSL or rate limit.
* **New (PRO):** 302 and 307 redirects, regex and prefix matching, automatic redirects when a slug changes, chain and loop detection across the whole set, import/export, and redirect analytics.
* **New (PRO):** Safe Revision — see an AI suggestion as a full diff, approve parts of it, merge, publish, and roll back afterwards.
* **New (PRO):** AI Content Refresher — scan a post for outdated years, software versions, prices and dead references, review every suggestion, and apply it as a reversible revision.
* **New (PRO):** Maintenance Agent — groups related problems, ranks them, prepares a plan for your approval, carries out the batch you approved and reports back on what actually changed.
* **Fix:** Replacing a media file is now crash-safe. The old files are moved aside rather than deleted and are put back automatically if the new upload fails at any point — including a fatal error part-way through — so a failed replacement can no longer leave an attachment with no file behind it.
* **Fix:** A media replacement is now rejected before anything on disk is touched if the new file is a different kind of media than the one it replaces.
* **Fix:** AI suggestions work again on current OpenAI models. The plugin always sent two parameters that today's reasoning models reject, so every request failed outright.
* **Fix:** AI suggestions no longer come back as "Empty response" on models that think before answering — the reply allowance was too small for the model to reach an answer.
* **Fix:** "Refresh from API" on the AI Settings screen no longer discards a saved model that the live list does not include, which could silently change your model the next time you saved.
* **Update:** Refreshed the model list for all ten AI providers — OpenAI, Anthropic Claude, Google Gemini, Groq, Mistral, OpenRouter, DeepSeek, xAI Grok, Hugging Face and Ollama. Models the providers have withdrawn, including several that were selected by default, have been replaced with current ones.
* **Update:** AI Settings now flags a saved model its provider has withdrawn and names the model that replaced it, instead of leaving you with an unexplained API error.
* **Update:** The Content Refresher page now shows the feature at a glance instead of a disabled form.
* **Update:** PRO-only areas are shown and plainly labelled rather than hidden, so you can always see that a problem exists even when the fix needs PRO.
* **Update:** Separate role capabilities for the new maintenance screens, so access to them can be granted independently of find and replace.
* **Update:** 404 records and activity history are pruned automatically, so the tables cannot grow without limit.
* **Dev:** The maintenance modules are extended entirely through hooks and filters — tabs, screens and issue types can be registered without editing plugin files.
* **Dev:** New test suites for the maintenance infrastructure, for every AI provider and model combination, and for WordPress integration against a live database — `composer test`, `composer test:ai`.

= Version: 1.9.5 ( August 21, 2026 ) =
* **Update:** Minor admin menu and interface refinements.

= Version: 1.9.4 ( August 07, 2026 ) =
* **New:** "Replace in Database" now also supports WordPress core content tables — comments, comment meta, terms, term meta, term descriptions, and links — alongside posts, postmeta, and options.
* **New:** Confirmation prompt before a live (non-dry-run) database replace, warning that the change is permanent; with the PRO version active, it links straight to the one-click Restore section instead.
* **Fix:** Resolved an Elementor "You must call the_content() function" error when editing or previewing a page — the real-time content filter no longer runs during Elementor, Divi, Beaver Builder, or WPBakery edit/preview modes.
* **Fix:** Admin submenu items now require the correct plugin capability instead of the low-privilege `read` capability, so they're hidden from users who shouldn't see them.
* **Fix:** Corrected the admin submenu ordering so PRO's Restore in Database, Pixel Manager, and License items appear in the right place.
* **Update:** Removed the "About Us" page from the plugin menu.

= Version: 1.9.3 ( June 04, 2026 ) =
* **Update:** Compatible with the latest WordPress version

= Version: 1.9.1 ( May 24, 2026 ) =
* **New:** "Replace media" action in the Media Library (upload.php) — in both list view (row action) and grid view (the attachment details popup) — replace any file without opening the dedicated Media Replacer page.
* **New:** Two replacement methods — keep the current file name, or adopt the new file's name and automatically repoint every link to it across your content.
* **New:** "Page Builders" targeting on Replace in Database — Elementor, Beaver Builder, Divi / WPBakery, Oxygen and Bricks — with automatic cache regeneration after a replace.
* **New:** "Escaped & Encoded URLs" option that also matches JSON-escaped (https:\/\/) and percent-encoded URLs, so replacing links inside Elementor and other page builders just works.
* **Fix:** Database replacement no longer corrupts PHP-serialized data (Beaver Builder, widgets, ACF, theme options, Bricks) when the replacement changes the text length.
* **Fix:** HTML inside serialized data is now preserved during replacement instead of being stripped.
* **Fix:** Numeric values inside serialized data keep their original type after a replace.
* **Fix:** AI provider API keys are no longer printed into the AI Settings page; leaving the key field blank keeps the saved key and preserves OAuth connections.
* **Fix:** Hardened internal input sanitization (nested arrays) and database column lookups.
* **Update:** Builder and object caches (Elementor CSS, Beaver Builder, post cache) are refreshed automatically after a live database replace.
* **Update:** Redesigned, collapsible "Replacement method" selector in the media replacer to save space.
* **Dev:** New hooks — `bfrp_builder_registry`, `bfrp_after_db_replace`, `bfrp_flush_object_cache` — plus unit and WordPress integration test suites.

= Version: 1.9.0 ( April 29, 2026 ) =
* **New:** AI suggestions now support 10 providers — OpenAI, Anthropic Claude, Google Gemini, Groq, Mistral, OpenRouter, DeepSeek, xAI Grok, Hugging Face, and Ollama (local).
* **New:** Sign in with OpenRouter via OAuth — access 100+ models without copying an API key.
* **New:** Per-provider configuration with "Get free key" deep links and live model-list fetching.
* **New:** Test Connection button on every provider card.
* **New:** Prompt template selector for AI rewrites — Persuasive, Concise, Formal, Friendly, Fix Grammar, or fully custom.
* **Fix:** Regex / Custom-Regex rules containing HTML with quotes (e.g. `<a href="tel:$1">$0</a>`) are saved cleanly — no more stray backslashes in the database.
* **Fix:** Editing a saved rule no longer shows double-encoded `&lt;` / `&quot;` in the textarea.
* **Fix:** Managed-regex rules no longer break the front-end page when the pattern contains `#`, is syntactically invalid, or is empty.
* **Fix:** A failing regex now preserves the original page content instead of rendering a blank page.
* **Fix:** Save-time regex validation rejects invalid patterns with a clear error message.
* **Update:** Modifier checkboxes (case-insensitive / whole-word / unicode) are now persisted via a new `flags` column and hidden for rule types that don't use them.
* **Update:** Database migration to 1.0.4 — adds the `flags` column and repairs legacy escape sequences in existing rules.


[CHECK THE FULL CHANGELOG](https://github.com/CodeSolz/Better-Find-and-Replace/blob/master/CHANGELOG.md).