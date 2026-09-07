### Version: 2.0.0 ( September 07, 2026 ) ###
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

### Version: 1.9.5 ( August 21, 2026 ) ###
* **Update:** Minor admin menu and interface refinements.

### Version: 1.9.4 ( August 07, 2026 ) ###
* **New:** "Replace in Database" now also supports WordPress core content tables — comments, comment meta, terms, term meta, term descriptions, and links — alongside posts, postmeta, and options.
* **New:** Confirmation prompt before a live (non-dry-run) database replace, warning that the change is permanent; with the PRO version active, it links straight to the one-click Restore section instead.
* **Fix:** Resolved an Elementor "You must call the_content() function" error when editing or previewing a page — the real-time content filter no longer runs during Elementor, Divi, Beaver Builder, or WPBakery edit/preview modes.
* **Fix:** Admin submenu items now require the correct plugin capability instead of the low-privilege `read` capability, so they're hidden from users who shouldn't see them.
* **Fix:** Corrected the admin submenu ordering so PRO's Restore in Database, Pixel Manager, and License items appear in the right place.
* **Update:** Removed the "About Us" page from the plugin menu.

### Version: 1.9.3 ( June 04, 2026 ) ###
* **Update:** Compatible with the latest WordPress version

### Version: 1.9.1 ( May 24, 2026 ) ###
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

### Version: 1.9.0 ( April 29, 2026 ) ###
* **New:** AI suggestions now support 10 providers — OpenAI, Anthropic Claude, Google Gemini, Groq, Mistral, OpenRouter, DeepSeek, xAI Grok, Hugging Face, and Ollama (local).
* **New:** Sign in with OpenRouter via OAuth — access 100+ models without copying an API key.
* **New:** Per-provider configuration with "Get free key" deep links and live model-list fetching.
* **New:** Test Connection button on every provider card.
* **New:** Prompt template selector for AI rewrites — Persuasive, Concise, Formal, Friendly, Fix Grammar, or fully custom.
* **Fix:** Regex / Custom-Regex rules containing HTML with quotes are saved cleanly — no more stray backslashes in the database.
* **Fix:** Editing a saved rule no longer shows double-encoded characters in the textarea.
* **Fix:** Managed-regex rules no longer break the front-end page when the pattern contains `#`, is invalid, or is empty.
* **Fix:** A failing regex now preserves the original page content instead of rendering a blank page.
* **Fix:** Save-time regex validation rejects invalid patterns with a clear error message.
* **Update:** Modifier checkboxes (case-insensitive / whole-word / unicode) are now persisted via a new `flags` column.
* **Update:** Database migration to 1.0.4 — adds the `flags` column and repairs legacy escape sequences in existing rules.

### Version: 1.8.2 ( March 23, 2026 ) ###
* **Fix:** Removed some unnecessary files

### Version: 1.8.1 ( March 23, 2026 ) ###
* **Fix:** Resolved admin submenu ordering issue

### Version: 1.8.0 ( March 09, 2026 ) ###
* **Fix:** Patched Media Replacer stored XSS

### Version: 1.7.9 ( December 09, 2025 ) ###
* **Update:** Updated for the latest WP release compatibility

### Version: 1.7.8 ( November 05, 2025 ) ###
* **Update:** Security patch updated

### Version: 1.7.7 ( September 29, 2025 ) ###
* **Update:** Security patch updated

### Version: 1.7.6 ( August 21, 2025 ) ###
* **Fix:** Little bug fixed

### Version: 1.7.5 ( August 12, 2025 ) ###
- **Upgrade:** The JavaScript code has been modernized
- **Fix:** Detects **translation loading** on the `plugins_loaded` hook and requires moving it to the `init` hook.

### Version: 1.7.4 ( July 01, 2025 ) ###
- **Fix:** Flags **translation loading issue** on the `plugins_loaded` hook (requires `init` or later).

### Version: 1.7.3 ( May 23, 2025 ) ###
- **New PRO:** Snippet Manager – Create, edit, and manage reusable CSS & JS code snippets from the admin panel.
- **New PRO:** Apply snippets conditionally to specific posts, pages, or custom post types with a visual list of where each snippet is used.
- **New PRO:** Snippets are saved as physical files and loaded for better performance and compatibility.
- **New PRO:** Supports both CSS and JS snippets with real-time page/post targeting.
- **Improved:** Frontend only loads the exact snippets needed for the current page, reducing bloat.

### Version: 1.7.2 ( May 16, 2025 ) ###
- **New:** ✨ AI-Powered Suggestions – Generate intelligent replacement suggestions using OpenAI.
- **New:** AI suggestion - preview with Apply / Regenerate options.

### Version: 1.7.1 ( April 22, 2025 ) ###
- **Update:** updated for the latest release

### Version: 1.7.0 ( March 26, 2025 ) ###
- **Update:** Media replacer updated
- **Update:** Video replacer - media replacer

### Version: 1.6.9 ( January 30, 2025 ) ###
- **Update:** Small issue fixed

### Version: 1.6.8 ( January 22, 2025 ) ###
- **Update:** Security patch updated

### Version: 1.6.7 ( January 19, 2025 ) ###
- **New:** <a href="https://docs.codesolz.net/better-find-and-replace/real-time-find-replace/media-replacer/">Visual Media Replacer:</a> Effortlessly update images with drag and drop features
- **Update:** JS script has been modernize for latest browsers

### Version: 1.6.6 ( January 07, 2025 ) ###
- **Update:** Small JS issue fixed
- **Update:** JS script has been updated to work smoothly
- **DB:** Installation function updated

### Version: 1.6.5 ( November 15, 2024 ) ###
- **Update:** Updated for the WordPress latest version
- **Update:** Script updated / modernize

### Version: 1.6.4 ( October 02, 2024 ) ###
- **Update:** Translators updated
- **Fix:** Notification issue 
- **Fix:** Little bug fixed
- **Update:** Added quick help and supports links

### Version: 1.6.3 ( August 12, 2024 ) ###
- **Fix:** Little bug fixed

### Version: 1.6.2 ( July 26, 2024 ) ###
- **Fix:** Security patch updated

### Version: 1.6.1 ( July 17, 2024 ) ###
- **Fix:** Security patch updated to enhance data organization

### Version: 1.6.0 ( July 01, 2024 ) ###
- **New:** Refined Search Results - Narrow your search for precise database replacements. 
- **New:** Targeted Content - Focus on post or page titles, content, and excerpts for more control.

### Version: 1.5.9 ( April 13, 2024 ) ###
- **Upgrade:** Updated for WordPress latest version

### Version: 1.5.8 ( March 13, 2024 ) ###
- **Upgrade:** Code updated for smooth functionalities
- **Upgrade:** Hooks updated for modernize

### Version: 1.5.7 ( February 26, 2024 ) ###
- **Upgrade:** Minor JS issue updated

### Version: 1.5.6 ( January 09, 2024 ) ###
- **Upgrade:** Minor issue fixed on Real-time word masking
- **Upgrade:** JS has modernize

### Version: 1.5.5 ( November 14, 2023 ) ###
- **Upgrade:** Updated for WordPress latest version

### Version: 1.5.4 ( September 27, 2023 ) ###
- **New:** Search and replace jQuery / Ajax loaded text - Advanced option ( pro )

### Version: 1.5.3 ( August 24, 2023 ) ###
- **Fix:** Possible conflict fixed on JS 
- **Upgrade:** JavaScript has updated for modern browsers

### Version: 1.5.2 ( August 06, 2023 ) ###
- **Improvement:** Optimized for the WordPress latest version
- **Upgrade:** Database search and replace speed updated

### Version: 1.5.1 ( June 22, 2023 ) ###
- **Fix:** Fixed issue created on previous version

### Version: 1.5.0 ( June 19, 2023 ) ###
- **Upgrade:** Real-time find and replace compatible with Gutenberg and other block / page builders
- **Upgrade:** Real-time find and replace buffering speed updated
- **Upgrade:** jQuery / Ajax loaded text replacer for real-time find and replace

### Version: 1.4.9 ( May 15, 2023 ) ###
- **Fix:** preg_replace - issue on real-time find and replace
- **Fix:** Pro plan activate issue

### Version: 1.4.8 ( May 04, 2023 ) ###
- **Upgrade:** Speed up on Real-time word masking
- **Upgrade:** Database search replacement results
- **Upgrade:** Multi-byte charset

### Version: 1.4.7 ( April 03, 2023 ) ###
- **Fix:** Multi-byte charset issue fixed
- **Upgrade:** Optimized query in real-time search & replace
- **New:** Country-based search and replace for real-time
- **New:** language-based search and replace for real-time

### Version: 1.4.6 ( February 21, 2023 ) ###
- **New:** Use your own REGEX for real-time find and replace
- **New:** Find & Replace in Multibyte characters ( Supported lang: Arabic, Chinese etc )
- **New:** Real-time Search and replace any HTML tags ( pro PRO / pro EXTEND )

### Version: 1.4.5 ( February 06, 2023 ) ###
- **Upgrade:** Rules re-writing and rendering
- **Upgrade:** Speed upgrade on real-time rendering

### Version: 1.4.4 ( January 24, 2023 ) ###
- **Fix:** Little bug fixed
- **Upgrade:** Speed up on real-time DOM loading 

### Version: 1.4.3 ( December 08, 2022 ) ###
- **Upgrade:** JavaScript updated to fix little issue
- **Upgrade:** Speed up for real-time search and replace

### Version: 1.4.2 ( October 24, 2022 ) ###
- **Fix:** Speed up on real-time search replace
- **Fix:** Bug fixed on real-time search replace
- **Update:** Upgraded database replacement functionalities

### Version: 1.4.1 ( September 19, 2022 ) ###
- **Upgrade:** JavaScript code has been modernize for latest browsers
- **Improvement:** Support docs added

### Version: 1.4.0 ( August 21, 2022 ) ###
- **Fix:** Bug fixed on real-time search replace
- **Fix:** Speed up on real-time search replace
- **Upgrade:** Rules saving updated more smoothly

### Version: 1.3.9 ( August 19, 2022 ) ###
- **New:** Assign a specific user role to manage this plugin
- **New:** Single access level can be assign by most popular "User Role Editor" or "PublishPress Capabilities" plugin
- **New:** Group access level can be assign by most popular "User Role Editor" plugin
- **New:** Capabilities - bfar_menu_add_new_rule, bfar_menu_all_replacement_rules,  bfar_menu_replace_in_database, bfar_menu_restore_in_database

### Version: 1.3.8 ( July 26, 2022 ) ###
- **New:** Screen options
- **New:** Initiated language support
- **New:** Clear log function - Restore in Db + All replacement rules section ( pro )
- **Fix:** Small bug on Export ( pro extend )

### Version: 1.3.7 ( June 15, 2022 ) ###
- **New:** Special feature to search and replace in large table - ( pro extend )
- **Improvement:** Bulk replacement (pro)
- **Improvement:** Popup report page cleanup

### Version: 1.3.6 ( May 25, 2022 ) ###
- **New:** Search and replace on a specific page or post (real-time) - (pro)
- **Improvement:** On Ajax / jQuery rule - added skip post / page options
- **Improvement:** Media/images URL/path updater
- **Improvement:** Removed integrated jQuery to reduce script size to load faster
- **Improvement:** PHP 8 compatible, checked up to: 8.1.4
- **Update:** Updated Sweetalert2 version to: 11.4.14
- **Update:** Updated Select2 version to: 4.0.13
- **Fix:** Data sanitize issues

### Version: 1.3.5 ( April 26, 2022 ) ###
- **Fix:** Data sanitize issues

### Version: 1.3.4 ###
- **Fix:** Activation hook updated
- **Improvement:** Search and replace speed on database feature

### Version: 1.3.3 ###
- **Improvement:** WordPress 5.9 & PHP 8 compatible

### Version: 1.3.2 ###
- **Feat:** Export / Import rules - (pro)
- **Feat:** Export / Import Database replacement logs - (pro)

### Version: 1.3.1 ###
- **Improvement:** Speed up on Database replacement section
- **Improvement:** serialize data replacement algorithm
- **Fix:** PHP warnings

### Version: 1.3.0 ###
- **Improvement:** string replacement 
- **Improvement:** loading time 

### Version: 1.2.9 ###
- **Fix:** Security issues

### Version: 1.2.8 ###
- **Improvement:** WordPress 5.8 compatible
- **Improvement:** Database search and replacement 

### Version: 1.2.7 ###
- **Improvement:** Database search and replacement 

### Version: 1.2.6 ###
- **Feat:** Masking rule on Shortcodes (pro)
- **Feat:** Masking on Old Comments - (pro)
- **Feat:** Skip posts - if you don't want to apply rules on any specific posts - (pro)
- **Feat:** Automatically filter New Posts before inserting into Database (good for auto posting websites) - (pro)
- **Feat:** Automatically filter New Comments before inserting into Database - (pro)

### Version: 1.2.5 ###
- **Fix:** Database search replace: PHP error: Cannot access property started with '\0'

### Version: 1.2.4 ###
- **Fix:** WP_Scripts::localize PHP Notice

### Version: 1.2.3 ###
- **Improvement:** Database find and replacement 
- **Improvement:** WordPress 5.7 compatible

### Version: 1.2.2 ###
- **Bug Fix:** bug fixed

### Version: 1.2.1 ###
- **Feat:** Skip pages ( if you don't want to apply rules on any specific pages ) - pro
- **Improvement:** Real-time find and replacement 

### Version: 1.2.0 ###
- **Bug Fix:** Replacement bug fixed
- **Improvement:** Improved database search and replace

### Version: 1.1.9 ###
- **Improvement:** Ajax search & replace
- **Drop:** Droped ajax search & replace by tag selector

### Version: 1.1.8 ###
- **Improvement:** Database search and replacement

### Version: 1.1.7 ###
- **Feat:** Serialized data supported ( find & replace or remove item by it's key )
- **Feat:** Automatic backup options - pro
- **Feat:** Restore data - pro

### Version: 1.1.6 ###
- **Improvement:** Database search and replacement
- **Improvement:** Special characters on Database search and replacement

### Version: 1.1.5 ###
- **Improvement:** Ajax find and replacement
- **Feat:** Advance filters for CSS rule (pro)
- **Feat:** Advance filters for JavaScript (pro) 

### Version: 1.1.4 ###
- **Improvement:** Real-time find and replacement 
- **Feat:** Real-time find and replacement - advance filtering( skip base urls) (pro)

### Version: 1.1.3 ###
- **Improvement:** Real-time find and replacement 
- **Feat:** Real-time find and replacement - advance filtering (pro)
- **Feat:** Real-time find and replacement - bypass rule (pro)

### Version: 1.1.2 ###
- **Improvement:** DB search and replacement 

### Version: 1.1.1 ###
- **Improvement:** DB search and replacement multiple search to single downgraded

### Version: 1.1.0 ###
- **Improvement:** RegEx improved for real-time find and replace
- **Feat:** Find and replace code blocks - pro

### Version: 1.0.9 ###
- **Improvement:** Database Search and Replacement
- **Feat:** Whole Word Only - search on database
- **Feat:** Search Unicode Characters in DB - pro

### Version: 1.0.8 ###
- **Improvement:** Multiple selection

### Version: 1.0.7 ###
- **Fix:** Notification issues

### Version: 1.0.6 ###
- **Feat:** Dry run on database search
- **Feat:** Reports on dry run search
- **Feat:** Case-insensitive search in database 

### Version: 1.0.5 ###
- **Change:** Plugin name changed - *Real Time Auto Find and Replace* to **Better Find and Replace**
- **Feat:** jQuery / Ajax loaded text replacement
- **Feat:** Database table selection / filter
- **Feat:** URLs replacement with filtering options

### Version: 1.0.4 ###
- **Fix:** Notification issues

### Version: 1.0.3 ###
- **Fet:** RegEx Supoorted

### Version: 1.0.2 ###
- **Fet:** Replace in Database
- **Improvement:** Find-Replace speed up
* Brand new user-interface

### Version: 1.0.1 ###
- **Improvement:** Imporved some codings
* Fix a bug.

### Version: 1.0.0 ###
* Fix a bug.

### Version: 0.5 ###
* Initial release.