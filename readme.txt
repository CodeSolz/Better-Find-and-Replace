=== Better Find and Replace ===
Contributors: CodeSolz, m.tuhin
Tags: database, search and replace, search replace, search, real-time replace, gutenberg, block-editor
Donate link: https://codesolz.net/our-products/wordpress-plugin/real-time-auto-find-and-replace/?utm_source=wordpress.org&utm_medium=README_DONATE_BTN
Requires at least: 4.0
Tested up to: 6.2
Stable tag: 1.5.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Easily Search and replace text, code blocks, URLs, footer credits, jQuery / Ajax loaded text or anything in real-time. Search replace within database too..

== Description ==
The plugin offers a seamless solution for efficient database search and replace, complemented by a dynamic real-time word masking feature. 

The real-time option employs a clever word masking technique to search and replace text, url ( anything ), leaving no trace behind. The find and replace process takes place before the website is 
rendered in the browser and does not impact any other files or databases. With this ultimate solution, easily find and replace text, HTML code, media/image URLs, footer credits, 
or any other content within your website without touching the database with the help of an easy-to-use user interface.

On the other hand, the permanent replace ensuring that any replaced text, URL etc is eliminated from your database permanently.
The plugin is equipped with powerful features that allow you to visualize the results of search and replace content within your database as well as permanently erasing it. It has the ability to
search within complex, serialized data structures and replace them with your own words, making it a powerful tool for managing website content for beginners and experienced users alike. 
Additionally, it allows for the removal or un-setting of any element in serialized data by specifying its key.

== Key Features ==

* Easy to use and user-friendly options
* Search and replace any text
* Search and replace text loaded by **Ajax / jQuery**
* Find and **replace URLs**
* Search and **replace images, attachment URLs etc..**
* Create word masking with find-replace over the whole website
* Create find-replace temp rules without touching database.
* Remove or change footer credit without touching database or HTML code
* Replace anything in HTML code
* **Replace images** in real-time rendering
* Mask bad words posted in comments 
* Change different language's content to your own language
* **RegEx** supported
* Replace any HTML tag or attribute
* Lighting first find-replacement in Database table's ( posts, postmeta, options )
* Select a specific database table to replace content
* **Dry Run** to see what will be change on Database
* Search and replace **Whole Words Only** in Database
* Ultimate powerful options for Search and replace **Serialized Data** in Database
* Remove any item from **Serialized Data** in Database 
* Assign a specific role to manage this plugin for lower level of users
* Real-time find and replace compatible with Gutenberg and other block / page builders

== How to replace in DB? ==
* First create a report by selecting *dry run* from bottom of the setting section 
* Report will appear in a modal window. You can check there which row / data is going to be replaced.
* On the report's, if you think the replacement is perfect which you want, then close the report window and un-check the dry run and click the Find & Replace button.
* **Attention:** Please check the report and make sure which data you are going to replace. It's very important because once you replace it in the Database you can't un-done it. 
* **important:** So, before replacing in the database create a dry run report and see if it's perfect or not. If it's wrong change the find keyword then try again the same procedure until you see it's perfect on the report. 

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
The process of installing the plugin adheres to industry standards and is conveniently straightforward to navigate. Kindly inform us should you encounter any challenges during the installation procedure.


### Automatic Installation From WordPress Dashboard

1. Login to your admin panel
2. Navigate to Plugins -> Add New
3. Search **better find and replace**
4. Click install and activate respectively.

### Manual Installation From WordPress Dashboard

If your not able to use the automatic installation, then you can use this method-

1. Download the plugin from <a href="https://wordpress.org/plugins/real-time-auto-find-and-replace/">https://wordpress.org/plugins/real-time-auto-find-and-replace/</a>. A ZIP file will be downloaded.
2. Login to your site's admin panel and navigate to Plugins -> Add New -> Upload.
3. Click choose file, select the plugin file and click install

### Installation Using FTP

If you are unable to use any of the methods due to internet connectivity and file permission issues, then you can use this method-

1. Download the plugin from <a href="https://wordpress.org/plugins/real-time-auto-find-and-replace/">https://wordpress.org/plugins/real-time-auto-find-and-replace/</a>. A ZIP file will be downloaded.
2. Unzip the file.
3. Launch your favorite FTP client. Such as FileZilla, FireFTP, CyberDuck etc. If you are a more advanced user, then you can use SSH too.
4. Upload the folder to `wp-content/plugins/`
5. Log in to your WordPress dashboard.
6. Navigate to Plugins -> Installed
7. Activate the plugin


== Screenshots ==

1. Add Find Rule - Plain Text
2. Add Find Rule - RegEx
3. Add Find Rule - jQuery / Ajax Text
4. List of All Masking Rules
5. URLs replacement in Database
6. Media replacement in Database
7. Dry run report
7. List of All Masking Rules with pro features 

== Changelog ==

= Version: 1.5.1 ( June 22, 2023 ) =
* **Fix:** Fixed issue created on previous version

= Version: 1.5.0 ( June 19, 2023 ) =
* **Upgrade:** Real-time find and replace compatible with Gutenberg and other block / page builders
* **Upgrade:** Real-time find and replace buffering speed updated
* **Upgrade:** jQuery / Ajax loaded text replacer for real-time find and replace

= Version: 1.4.9 ( May 15, 2023 ) =
* **Fix:** preg_replace - issue on real-time find and replace
* **Fix:** Pro plan activate issue

= Version: 1.4.8 ( May 04, 2023 ) =
* **Upgrade:** Speed up on Real-time word masking
* **Upgrade:** Database search replacement results
* **Upgrade:** Multi-byte charset

= Version: 1.4.7 ( April 03, 2023 ) =
* **Fix:** Multi-byte charset issue fixed
* **Upgrade:** Optimized query in real-time search & replace
* **New:** Country-based search and replace for real-time
* **New:** language-based search and replace for real-time

= Version: 1.4.6 ( February 21, 2023 ) =
* **New:** Use your own REGEX for real-time find and replace
* **New:** Find & Replace in Multibyte characters ( Supported lang: Arabic, Chinese etc )
* **New:** Real-time Search and replace any HTML tags ( pro PRO / pro EXTEND )

= Version: 1.4.5 ( February 06, 2023 ) =
* **Upgrade:** Rules re-writing and rendering
* **Upgrade:** Speed upgrade on real-time rendering

= Version: 1.4.4 ( January 24, 2023 ) =
* **Fix:** Little bug fixed
* **Upgrade:** Speed up on real-time DOM loading 

= Version: 1.4.3 ( December 08, 2022 ) =
* **Upgrade:** JavaScript updated to fix little issue
* **Upgrade:** Speed up for real-time search and replace

= Version: 1.4.2 ( October 24, 2022 ) =
* **Fix:** Speed up on real-time search replace
* **Fix:** Bug fixed on real-time search replace
* **Update:** Upgraded database replacement functionalities

= Version: 1.4.1 ( September 19, 2022 ) =
* **Upgrade:** JavaScript code has been modernize for latest browsers
* **Improvement:** Support docs added

= Version: 1.4.0 ( August 21, 2022 ) =
* **Fix:** Bug fixed on real-time search replace
* **Fix:** Speed up on real-time search replace
* **Upgrade:** Rules saving updated more smoothly

= Version: 1.3.9 ( August 19, 2022 ) =
* **New:** Assign a specific user role to manage this plugin
* **New:** Single access level can be assign by most popular "User Role Editor" or "PublishPress Capabilities" plugin
* **New:** Group access level can be assign by most popular "User Role Editor" plugin
* **New:** Capabilities - bfar_menu_add_new_rule, bfar_menu_all_replacement_rules,  bfar_menu_replace_in_database, bfar_menu_restore_in_database

= Version: 1.3.8 ( July 26, 2022 ) =
* **New:** Screen options
* **New:** Initiated language support
* **New:** Clear log function - Restore in Db + All replacement rules section ( pro )
* **Fix:** Small bug on Export ( pro extend )

= Version: 1.3.7 ( June 15, 2022 ) =
* **New:** Special feature to search and replace in large table - ( pro extend )
* **Improvement:** Bulk replacement (pro)
* **Improvement:** Popup report page cleanup

= Version: 1.3.6 ( May 25, 2022 ) =
* **New:** Search and replace on a specific page or post (real-time) - (pro)
* **Improvement:** On Ajax / jQuery rule - added skip post / page options
* **Improvement:** Media/images URL/path updater
* **Improvement:** Removed integrated jQuery to reduce script size to load faster
* **Improvement:** PHP 8 compatible, checked up to: 8.1.4
* **Update:** Updated Sweetalert2 version to: 11.4.14
* **Update:** Updated Select2 version to: 4.0.13
* **Fix:** Data sanitize issues

= Version: 1.3.5 =
* **Fix:** Data sanitize issues

= Version: 1.3.4 =
* **Fix:** Activation hook updated
* **Improvement:** Search and replace speed on database feature

= Version: 1.3.3 =
* **Improvement:** WordPress 5.9 & PHP 8 compatible

= Version: 1.3.2 =
* **Feat:** Export / Import rules - (pro)
* **Feat:** Export / Import Database replacement logs - (pro)

= Version: 1.3.1 =
* **Improvement:** Speed up on Database replacement section
* **Improvement:** serialize data replacement algorithm
* **Fix:** PHP warnings

= Version: 1.3.0 =
* **Improvement:** string replacement 
* **Improvement:** loading time 

= Version: 1.2.9 =
* **Fix:** Security issues

= Version: 1.2.8 =
* **Improvement:** WordPress 5.8 compatible
* **Improvement:** Database search and replacement 

= Version: 1.2.7 =
* **Improvement:** Database search and replacement 

= Version: 1.2.6 =
* **Feat:** Masking rule on Shortcodes (pro)
* **Feat:** Masking on Old Comments - (pro)
* **Feat:** Skip posts - if you don't want to apply rules on any specific posts - (pro)
* **Feat:** Automatically filter New Posts before inserting into Database (good for auto posting websites) - (pro)
* **Feat:** Automatically filter New Comments before inserting into Database - (pro)

= Version: 1.2.5 =
* **Fix:** Database search replace: PHP error: Cannot access property started with '\0'

= Version: 1.2.4 =
* **Fix:** WP_Scripts::localize PHP Notice

= Version: 1.2.3 =
* **Improvement:** Database find and replacement 
* **Improvement:** WordPress 5.7 compatible

= Version: 1.2.2 =
* **Bug Fix:** bug fixed

= Version: 1.2.1 =
* **Feat:** Skip pages ( if you don't want to apply rules on any specific pages ) - pro
* **Improvement:** Real-time find and replacement 

= Version: 1.2.0 =
* **Bug Fix:** Replacement bug fixed
* **Improvement:** Improved database search and replace

= Version: 1.1.9 =
* **Improvement:** Ajax search & replace
* **Drop:** Droped ajax search & replace by tag selector

= Version: 1.1.8 =
* **Improvement:** Database search and replacement

= Version: 1.1.7 =
* **Feat:** Serialized data supported ( find & replace or remove item by it's key )
* **Feat:** Automatic backup options - pro
* **Feat:** Restore data - pro

= Version: 1.1.6 =
* **Improvement:** Database search and replacement
* **Improvement:** Special characters on Database search and replacement

= Version: 1.1.5 =
* **Improvement:** Ajax find and replacement
* **Feat:** Advance filters for CSS rule (pro)
* **Feat:** Advance filters for JavaScript (pro) 

= Version: 1.1.4 =
* **Improvement:** Real-time find and replacement 
* **Feat:** Real-time find and replacement - advance filtering( skip base urls) (pro)

= Version: 1.1.3 =
* **Improvement:** Real-time find and replacement 
* **Feat:** Real-time find and replacement - advance filtering (pro)
* **Feat:** Real-time find and replacement - bypass rule (pro)

= Version: 1.1.2 =
* **Improvement:** DB search and replacement 

= Version: 1.1.1 =
* **Improvement:** DB search and replacement multiple search to single downgraded

= Version: 1.1.0 =
* **Improvement:** RegEx improved for real-time find and replace
* **Feat:** Find and replace code blocks - pro

= Version: 1.0.9 =
* **Improvement:** Database Search and Replacement
* **Feat:** Whole Word Only - search on database
* **Feat:** Search Unicode Characters in DB - pro

= Version: 1.0.8 =
* **Improvement:** Multiple selection

= Version: 1.0.7 =
* **Fix:** Notification issues

= Version: 1.0.6 =
* **Feat:** Dry run on database search
* **Feat:** Reports on dry run search
* **Feat:** Case-insensitive search in database 

= Version: 1.0.5 =
* **Change:** Plugin name changed - *Real Time Auto Find and Replace* to **Better Find and Replace**
* **Feat:** jQuery / Ajax loaded text replacement
* **Feat:** Database table selection / filter
* **Feat:** URLs replacement with filtering options

= Version: 1.0.4 =
* **Fix:** Notification issues

= Version: 1.0.3 =
* **Fet:** RegEx Supoorted

= Version: 1.0.2 =
* **Fet:** Replace in Database
* **Improvement:** Find-Replace speed up
* Brand new user-interface

= Version: 1.0.1 =
* **Improvement:** Imporved some codings
* Fix a bug.

= Version: 1.0.0 =
* Fix a bug.

= Version: 0.5 =
* Initial release.
