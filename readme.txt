=== Better Find and Replace ===
Contributors: CodeSolz, m.tuhin
Tags: find, replace, search, find and replace, search and replace, auto find, auto replace
Donate link: https://codesolz.net/our-products/wordpress-plugin/real-time-auto-find-and-replace/?utm_source=wordpress.org&utm_medium=README_DONATE_BTN
Requires at least: 4.0
Tested up to: 5.5
Stable tag: 1.1.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Find and replace text, HTML code, Image's url, Footer credits, jQuery / Ajax loaded text or anything in the website without touching the database.

== Description ==
This plugin will automatically find a specific word and will replace it with your own word. The changes will be automatically done before the website renders to the browser's. It doesn't affect any other plugins files or your database.

Easily Find and replace text, HTML code, image's url, footer credits or anything in the website without touching the database. Also, you can search and replace permanently in Database too.

== Key Features ==

* Easy to use and user-friendly options
* Search and replace text loaded by **Ajax / jQuery**
* Search and replace any text
* Find and **replace URLs**
* Search and **replace images, attchment URLs etc..**
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

== How to replace in DB? ==
* First create a report by selecting *dry run* from bottom of the setting section 
* Report will appear in a modal window. You can check there which row / data is going to be replaced.
* On the report's, if you think the replacement is perfect which you want, then close the report window and un-check the dry run and click the Find & Replace button.
* **Attention:** Please check the report and make sure which data you are going to replace. It's very important because once you replace it in the Database you can't un-done it. 
* **important:** So, before replacing in the database create a dry run report and see if it's perfect or not. If it's wrong change the find keyword then try again the same procedure until you see it's perfect on the report. 

== Pro Features ==
* **Database Replacement Features:**
* Powerful search and replace in database
* Ability to check & replace each item separately which going to be replaced in the database
* Bulk Replacement on report's page, generate by dry run option
* All tables in database
* Search and replace **Unicode Characters** *UTF-8  ( e.g: U+0026, REČA )* in Database
* Additional filterting options in default / custom URLs 

* **Real-Time Rendering Features:**
* RegEx supported
* Advance Regex - Powerful code blocks / multilines find and replace in real-time (masking) rendering
* Advance Regex - Any (CSS / JS / HTML) code Block find and replacement in real-time (masking) rendering
* Advance filtering options - ( Case insensitive, Whole Word, Unicode, Skip posts / page / custom taxonomies etc.. URLs )
* Bypass rule  - keep texts unchanged on specific area with special pattern
* Bypass rule  - keep base links / urls ( post, pages, custom taxonomies etc..) unchanged where find word exists in that URL.

= Advance Regex - Code blocks / multilines find and replacement example - (Real-time Rendering) =
*Find code block and replace with your own or keep blank *replament field* to remove code block. Let consider the following CSS code block for replace. Put following
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

== Setup Video Guide - How to install and setup search and replace rules == 
[youtube https://www.youtube.com/watch?v=nDv6T72sRfc]

= Forum and Feature Request = 

<blockquote>
= For Quick Support, feature request and bug reporting = 
<ul>
    <li> Visit our website <a target="_blank" href="https://codesolz.net/?utm_source=wordpress.org&utm_medium=README&utm_campaign=real-time-auto-find-and-replace">To Get Instant Support</a></li>
    <li> For more dedicated support or feature request write to us at <a target="_blank" href="mailto:support@codesolz.net">support@codesolz.net</a> or create a ticket <a href="http://support.codesolz.net/public/create-ticket" target="_blank"> Support Center</a></li>
</ul>

= Visit our forum to share your experience or request features = 
<ul>
    <li> Visit our <a target="_blank" href="https://forum.codesolz.net/?utm_source=wordpress.org&utm_medium=README&utm_campaign=real-time-auto-find-and-replace">forum</a></li>
</ul>

= As it's open source, check our github development Status = 
<ul>
    <li> Check development status or issues in <a target="_blank" href="https://github.com/CodeSolz/real-time-auto-find-and-replace" > github.com/CodeSolz/real-time-auto-find-and-replace </a>
</ul>
</blockquote>


== Installation ==
1. Upload the WooCommerce_AltCoin_Payment_Gateway folder to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Screenshots ==

1. Add Find Rule - Plain Text
2. Add Find Rule - RegEx
3. Add Find Rule - jQuery / Ajax Text
4. List of All Masking Rules
5. URLs replacement in Database
6. Media replacement in Database
7. Dry run report

== Changelog ==

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
