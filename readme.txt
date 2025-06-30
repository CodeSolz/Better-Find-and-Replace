=== Better Find and Replace - AI-Powered Suggestions ===
Contributors: codesolz, m.tuhin
Tags: database, search replace, search, replace, search and replace, real-time replace, find, find and replace, gutenberg, block-editor, search replace elementor
Donate link: https://codesolz.net/our-products/wordpress-plugin/real-time-auto-find-and-replace/?utm_source=wordpress.org&utm_medium=README_DONATE_BTN
Requires at least: 4.0
Tested up to: 6.8
Stable tag: 1.7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Search and replace text, images, URLs, footer credits, code blocks or jQuery-Ajax content in real time or in Database, easy user-interface

== Description ==

= Smart Search, Replace & Media Tool (with AI) for WordPress =

[Better Find and Replace](https://codesolz.net/our-products/wordpress-plugin/real-time-auto-find-and-replace/?utm_campaign=wordpress-org-visitor&utm_medium=learn_more_about_dokan&utm_source=WordPress.org) lets you easily search and replace text, HTML, links and media across your entire WordPress site — no coding needed. Perfect for database cleanup, content updates or post-migration edits.

Replace text or media in bulk with full support for serialized data, custom tables and dry-run previews. AI-powered suggestions help you rewrite or improve content instantly, making your edits smarter and faster.

Easily find and replace images using drag-and-drop and auto-regenerate thumbnails. You can also update or add  alt text, captions and metadata with the suggestion of AI for better SEO.

Want to **change content without editing your database**? Use real-time masking to update text, links or HTML before the page loads — instantly and safely.

Built for developers, agencies and site owners, individual ( everyone ) who want fast, accurate control over their content management system — all in one clean, intuitive interface.


== Key Features ==

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

= Version: 1.7.4 ( July 01, 2025 ) =
* **Fix:** Flags **translation loading issue** on the `plugins_loaded` hook (requires `init` or later).

= Version: 1.7.3 ( May 23, 2025 ) =
* **New PRO:** Snippet Manager – Create, edit, and manage reusable CSS & JS code snippets from the admin panel.
* **New PRO:** Apply snippets conditionally to specific posts, pages, or custom post types with a visual list of where each snippet is used.
* **New PRO:** Snippets are saved as physical files and loaded for better performance and compatibility.
* **New PRO:** Supports both CSS and JS snippets with real-time page/post targeting.
* **Improved:** Frontend only loads the exact snippets needed for the current page, reducing bloat.

= Version: 1.7.2 ( May 16, 2025 ) =
* **New:** ✨ AI-Powered Suggestions – Generate intelligent replacement suggestions using OpenAI.
* **New:** AI suggestion - preview with Apply / Regenerate options.

= Version: 1.7.1 ( April 22, 2025 ) =
* **Update:** updated for the latest release

= Version: 1.7.0 ( March 26, 2025 ) =
* **Update:** Media replacer updated
* **Update:** Video replacer - media replacer

= Version: 1.6.9 ( January 30, 2025 ) =
* **Update:** Small issue fixed

= Version: 1.6.8 ( January 22, 2025 ) =
* **Update:** Security patch updated

[CHECK THE FULL CHANGELOG](https://github.com/CodeSolz/Better-Find-and-Replace/blob/master/CHANGELOG.md).