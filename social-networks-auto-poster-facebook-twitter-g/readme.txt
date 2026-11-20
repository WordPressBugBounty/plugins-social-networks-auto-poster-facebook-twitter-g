=== NextScripts: Social Networks Auto-Poster ===
Contributors: NextScripts, okapy
Donate link: https://www.nextscripts.com/social-networks-auto-poster-for-wordpress
Tags: autopost, social networks, social media, sharing, repost
Requires at least: 6.0
Tested up to: 7.1.5
Requires PHP: 7.4
Stable tag: 4.5.2
License: GPLv2 or later

Automatically publishes blog posts to profiles, pages, groups, and communities across supported social networks.

== Description ==

**Supercharge your social media reach automatically.**

NextScripts Social Networks Auto-Poster (SNAP) automatically publishes posts from your WordPress blog to your social media accounts. The entire workflow is 100% hands-free: write a post, click publish, and SNAP instantly broadcasts either the full content or a customized announcement with backlinks, hashtags, and media attachments to your connected channels.

= Choose How You Connect: Your Own Apps or Instant Cloud Setup =

SNAP gives you unmatched flexibility and privacy:

* **Instant 1-Click Cloud Connection via Post.Social**: Connect platforms like Facebook, Instagram, LinkedIn, X/Twitter, Threads, Bluesky, Pinterest, and VKontakte in seconds without registering developer accounts, creating apps, or handling API keys.
* **Direct Connection (Your Own API / App Keys)**: Prefer complete control and 100% white-labeled posts? Connect your own custom developer apps directly to supported networks with zero third-party branding.

= Supported Networks (24 Platforms) =

**Networks with Post.Social Cloud Connection (or Direct App setup):**

* **Bluesky** — Cloud 1-click connect or direct publishing with handle & App Password.
* **Facebook** — Cloud 1-click connect or custom Facebook App (Pages).
* **Instagram** — Cloud 1-click connect or direct Meta App integration (Business & Creator accounts).
* **LinkedIn** — Cloud 1-click connect or direct LinkedIn App (Personal Profiles and Company Pages).
* **Pinterest** — Cloud 1-click connect or direct Pinterest App (Boards).
* **Threads** — Cloud 1-click connect or custom Threads App.
* **X/Twitter** — Cloud 1-click connect or direct Twitter Developer API keys.
* **VKontakte (VK)** — Cloud 1-click connect or direct VK App (Profiles and Communities/Groups).

**Directly Integrated Networks:**

* **Blogger / Blogspot** — Autopost full HTML content to your blog.
* **Diigo** — Auto-submit bookmarks with tags.
* **Flickr** — Post images directly to photostreams and sets. Tags are supported.
* **Google Business Profile** — Publish updates directly to your local business listings.
* **Instapaper** — Auto-save bookmarks to your account.
* **LINE** — Broadcast messages to channels, groups, and rooms.
* **LiveJournal & Dreamwidth** — Auto-submit posts to personal journals and communities.
* **Mailchimp** — Distribute blog updates to specific email lists/campaigns.
* **ok.ru (Odnoklassniki)** — Share status updates, links, and photos to groups and pages.
* **Reddit** — Submit text posts or links to your target subreddits.
* **Scoop.it** — Curate updates directly into your Scoop.it topics.
* **Telegram** — Instant message broadcasting to channels, groups, and chats.
* **Tumblr** — Publish text, rich media, and link cards via Tumblr Neue Post Format (NPF).
* **vBulletin** — Automatically create forum threads or posts.
* **Weibo** — Publish microblog announcements and images.
* **WordPress** — Cross-post directly to other remote WordPress-powered sites (including WordPress.com).

= Post.Social Cloud Integration =

SNAP can seamlessly link each WordPress site to its own Post.Social Project without manually copying credentials. Open **SNAP → Post.Social**, complete the browser confirmation, and manage all your connected destinations directly from the WordPress post editor.

* **Zero credential leakage**: Provider credentials never enter your local WordPress database.
* **High reliability**: Powered by a durable background WP-Cron queue with idempotent retry logic and status polling.
* **Multisite compatible**: Keeps an isolated installation UUID and encrypted connection state for each Multisite network blog. Disconnecting preserves remote history.

= Features =

* **Message Formatting Tags**: Full control over post structure using dynamic tags (%TITLE%, %EXCERPT%, %BODY%, %AUTHOR%, %POSTDATE%, etc.).
* **Spin-Syntax Support**: Spin message templates ({Great|Awesome|New} post: %TITLE%) to keep posts fresh and unique.
* **Filters**: Granular filtering allows you to post only specified categories, tags, authors, or custom post types to specific accounts.
* **Auto-Reposter**: Keep your social feeds active by automatically re-sharing existing posts on custom schedules.
* **Hashtag Automation**: Convert WordPress tags and categories into native social hashtags automatically.
* **WooCommerce Compatible**: Autopost new or auto-repost existing products to all your social media accounts.
* **URL Shorteners**: Native support for bit.ly, is.gd, and YOURLS.
* **Image Flexibility**: Attach featured images, select specific images from posts, or publish text-only status updates.
* **Direct Links**: See direct links to published live posts right from the WordPress "Edit Post" page.
* **Multi-Account & Multi-User Support**: Connect multiple accounts per network and assign specific permissions.
* **Proxy Support**: Route requests through proxies when required.
* **Export/Import**: Easily backup and transfer your plugin settings between sites.

= Requirements =

* WordPress 6.0+ (6.9+ recommended)
* PHP 7.4+ (8.2+ recommended)
* cURL extension enabled
* Correctly working WP-Cron (required for scheduled posts, background queue, and auto-reposter)

= Get Support =

Need help or found an issue?

* <a href="https://www.nextscripts.com/support/">Open a Support Ticket</a>
* <a href="https://www.nextscripts.com/support-faq/">Troubleshooting FAQ</a>

== Installation ==

1. Upload the `social-networks-auto-poster-for-wordpress` folder to `/wp-content/plugins/` (or install via the WordPress Plugins directory).
2. Activate the plugin via the **Plugins** menu in WordPress.
3. Open **SNAP → Accounts** and click **Add new account**.
4. Choose your preferred connection route:
   * **Cloud Connect (Post.Social)**: Click Connect, authorize the service, and start publishing immediately.
   * **Own App / Keys**: Follow the step-by-step setup guides to generate and paste your own developer API credentials.
5. Create a new post or test an existing post from the post editor metadata box.

Detailed instructions and network guides: <a href="https://www.nextscripts.com/instructions/">SNAP Setup Instructions</a>

== Frequently Asked Questions ==

= Do I have to create developer apps for every network? =

No. With the built-in Post.Social integration, you can connect platforms like Facebook, Instagram, LinkedIn, X/Twitter, Threads, Bluesky, Pinterest, and VKontakte in seconds without registering as a developer or dealing with API keys. If you still want 100% white-label posts and direct API control, you can always choose to set up your own apps.

= Can I use SNAP for only one or two networks? =

Yes. You can configure as few or as many networks as you need. Unused networks stay inactive and can be hidden in the editor to avoid clutter.

= Can SNAP publish to Facebook Pages and LinkedIn Company Pages? =

Yes. SNAP supports posting to Facebook Pages, as well as both personal profiles and Company Pages on LinkedIn.

= Does SNAP support WordPress Multisite? =

Yes. SNAP fully supports WordPress Multisite environments, maintaining isolated connection settings and tokens for individual network sites.

= Where do I report security bugs found in this plugin? =

Please report security bugs found in the source code of the plugin through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/d7947330-6e49-4e78-b25a-82f171a06559).

== Screenshots ==

1. Post Editor SNAP Publishing Box
2. Accounts Settings Dashboard
3. One-Click Cloud Connect Dialog (Post.Social)
4. Message Formatting & Filter Controls

== External Services ==

SNAP connects to [Post.Social](https://post.social/) when an administrator opts into the cloud connection or uses it for publishing. If the separately installed SNAP Pro Upgrade Helper or Multi Account add-on provides subscription eligibility checking, that component sends the SNAP activation key, or its existing license hash when the key is unavailable, to Post.Social over HTTPS. The response may include a masked registration email. SNAP displays the saved result and requests a fresh check when an administrator opens its Cloud page. Choosing Activate Post.Social opens its registration page with the SNAP key or license hash and site URL in the link.
When no subscription-checking component is available, SNAP looks only at local WordPress options for evidence of earlier Pro use. If found, it offers a Post.Social loyalty registration link containing `snap=expro` and the site's URL; no data is sent by this local check.
To pair your site and manage social channels, SNAP securely communicates the site URL, site title, installation identifier, and WordPress/SNAP version information. During automated publishing, it delivers formatted post text, metadata, target account IDs, and required media assets to Post.Social for processing and status reporting.

Review the Post.Social [Terms of Service](https://post.social/terms) and [Privacy Policy](https://post.social/privacy).

== Changelog ==

= Major Versions =

* **Version 4.5** — Post.Social Cloud integration (1-click connection without developer apps), new networks (Bluesky, Threads), modernized REST & OAuth APIs, bundled Pro/Multi-User engine, and new durable background publishing queue.
* **Version 4.0** — 100% Re-written. New engine. New interface. Existing Posts Auto-Reposter.
* *Version 3.7* — New networks: Mailchimp, ok.ru, Weibo. Improved interface.
* *Version 3.6* — Filters, performance optimizations.
* *Version 3.5* — New networks: Telegram. New publishing platform.
* *Version 3.4* — Compatibility with modern WP versions.
* *Version 3.3* — Direct social posting without creating a WP post. New networks: Flickr, Scoop.it.
* *Version 3.0* — Auto-repost existing posts, autoposting query, image selector, Reddit support.
* *Version 2.0* — Comment auto-import, Dreamwidth, Instapaper, LiveJournal support.
* *Version 1.0* — Initial release.

= 4.5.2 [19/11/2026] =
* Improved Post.Social credit handling: clearer publishing errors and notices when credits run out, plus current-period credit usage on the Cloud page.
* Added one-click authenticated access to the correct Post.Social Dashboard and Billing account for both temporary and regular connections.
* Improved the credit notice layout and made it dismissible to the SNAP sidebar.

= 4.5.1 [17/11/2026] =
* Updated VK authorization with your own application to VK ID OAuth 2.1, using PKCE and renewable access tokens.

= 4.5.0 [16/11/2026] =
* New: Post.Social Cloud Integration — instant 1-click account authorization and publishing for Facebook, Instagram, LinkedIn, X/Twitter, Threads, Bluesky, Pinterest, and VKontakte without creating developer apps or managing API keys.
* New: Bluesky & Threads Support — added native support for both platforms via direct app setup and instant Post.Social Cloud Connection.
* Core: Modernized Network APIs:
  - Updated LinkedIn integration to current versioned REST APIs (Profiles & Organization Pages).
  - Upgraded Tumblr publishing to the Neue Post Format (NPF) endpoint.
  - Migrated Google Business Profile to official OAuth & Local Posts API.
  - Upgraded Instagram to native Meta Content Publishing OAuth API.
  - Migrated Pinterest and Reddit to current OAuth APIs; updated Scoop.it to bearer-token authorization.
* Core: Publishing Reliability Engine:
  - Durable WP-Cron queue with server-side polling, encrypted temporary secrets, and automatic idempotent retries.
  - Immediate execution for "Post Now" requests with improved deduplication handling.
  - Detailed Post.Social publication status tracking and logging directly in the WordPress dashboard.
* Interface & UX:
  - Streamlined "Add New Account" UI with clear separation between Quick Connect (Cloud) and Direct (Own API/Keys).
  - Consolidated admin navigation: merged Log and Query/Timeline into a single unified interface.
  - Direct post-editor publishing box now natively supports cloud-backed accounts alongside direct ones.
* Security:
  - Addressed Wordfence & Patchstack vulnerability reports: resolved Reflected XSS (CVE-2026-16570, CVE-2024-37275, [WF-2021-09-29]), PHP Object Injection (CVE-2026-27379), and Missing Authorization checks (CVE-2026-97227).
  - Improved data security: secured comment avatar rendering, added PKCE support for OAuth exchanges, and enforced strict isolation for Multisite connection secrets.
* Cleanup: Retired deprecated libraries and removed discontinued networks (Google+, deviantART, Medium, 500px, Flipboard, SETT).

= 4.4.7 [26/02/2026] =
* New - WordPress 6.9 compatibility.
* Bug Fix - Possible XSS security issue [CVE-2026-3228]
* Bug Fix - Possible XSS security issue [CVE-2024-37275]

= 4.4.6 [11/06/2024] = 
* Bug Fix - Few possible security issues/unsafe XSS Calls

= 4.4.5 [30/05/2024] = 
* Bug Fix - Few possible security issues/unsafe SQL Calls

= 4.4.4 [10/05/2024] = 
* Bug Fix - Possible security issue [CVE-2024-2088]
* Bug Fix - Possible security issue [CVE-2024-1762]
* Bug Fix - Possible security issue [CVE-2024-1446]
* Bug Fix - Possible XSS security issue [PS-e8dc2137-b88e-4916-a46d-d85a0e33c40b]
* Bug Fix - Other possible security issues/unsafe SQL Calls

= 4.4.3 [12/01/2023] = 
* New - Support for GO2LN.com aka gd.is URL Shortener
* Bug Fix - Possible XSS security issue [CVE-2023-49183]

= 4.4.2 [10/27/2022] = 
* Bug Fix - "Cannot use object of type WP_Error as array" error.

= 4.4.1 [10/25/2022] = 
* New - Support for Addons.
* New/Improvement - [Facebook] - Updated to the latest API. App Review is no longer required! 
* Improvement - A lot of code cleanup/improvements. 

= 4.3.30 [07/22/2022] = 
* New/Improvement - Better compatibility with servers not configured to handle "Connection: keep-alive"
* Bug Fix - [Twitter] - 2 Errors fixed.
* Bug Fix - [Reddit]  - Login issue
* Bug Fix - [IG] Fix for 04 and 09I Errors.

= 4.3.29 [07/18/2022] = 
* New/Improvement - [Telegram] - "Image" post type has been added.
* New/Improvement - [LJ] - Added support for Proxy.
* Bug Fix - Proxy support was broken.
* Bug Fix - [Twitter] - fix for "Illegal string offset 'Error'"
* Bug Fix - [IG] Fix for "Value must be integer"

= 4.3.28 [07/16/2022] =
* Bug Fix - [ok.ru] - Image upload error fix. 
* Bug Fix - fix for "Cannot use assign-op operators with string offsets" error.
* Bug Fix - "Post to Favorite Accounts" code shown only to admins.

= 4.3.27 [07/15/2022] =
* New/Improvement - [Twitter] - Update to the latest API compatibility.
* New/Improvement - [LJ] - Update to the latest API compatibility.
* Removed [Google+/Google Currents]
* Bug Fix - Many security compliance changes/fixes
* Bug Fix - PHP 8 Compatibility Improvements. 
* Bug Fix - Fix for 'Uncaught TypeError: date(): Argument #2 ($timestamp) must be of type int, string given' error
* Improvement - A lot of code cleanup/improvements. 

= 4.3.26 [06/27/2022] =
* New - Support for Wordpress 6.0
* Bug Fix - Possible security vulnerability [WP-2022-05-31]

= 4.3.25 [12/29/2021] =
* New - [Blogger] Add Categories as Tags/Labels
* Bug Fix - [Tumblr] Authorization error fix.
* Bug Fix - [VK] Authorization error fix.
* Bug Fix - Possible CSRF security issue [WP-2021-12-29]

= 4.3.24 [12/28/2021] =
* Bug Fix - Possible XSS security issue [WP-2021-12-28]

= 4.3.23 [10/04/2021] =
* Bug Fix - Message in the footer has been removed.
* Bug Fix - Plugin was breaking the "Site Health" section.

= 4.3.21 [10/04/2021] =
* New - Favorite accounts.
* New - Quick post to favorite accounts.
* New/Improvement - [Wordpress Based Sites] - Ability to make an exact copy of the post.
* New - WordPress 5.8 support.
* Improvement - Support for hashtags with hyphens.
* Bug Fix - [Blogger] Fatal Error with incorrect token.
* Bug Fix - [vk.com] Outdated API error message
* Bug Fix - Possible XSS security issue [WF-2021-09-29]

= 4.3.20 [02/05/2021] =
* Improvement/Bug Fix  [Pinterest] - ability to use sessions (fix for error 429).
* Improvement/Bug Fix  [Medium] - ability to connect using integration tokens
* Small bug fixes and improvements for WP 5.6 compatibility

= 4.3.19 [10/22/2020] =
* Improvement - All applicable APIs updated to the latest versions. 
* Bug Fix - "Category" selection for filters. 
* Bug Fix - all references to obsolete "get_magic_quotes_gpc" have been removed. 
* Small bug fixes and improvements

= 4.3.18 [09/04/2020] =
* Bug Fix - Security issue (nonce). 
* Improvement/Bug Fix for better jQuery compatibility
* Small bug fixes and improvements

= 4.3.17 [08/14/2020] =
* Small bug fixes and improvements for WP 5.5 compatibility

= 4.3.16 [07/15/2020] =
* Bug Fix - [VK] - broken ability to add new VK account after deleting an old one.
* Bug Fix - [Flipboard] - new Flipboard interface compatibility update.

= 4.3.15 [05/08/2020] =
* Improvement/Bug Fix - [Bit.ly] - Bit.ly API V3 has been retired. SNAP now uses Bit.ly API V4. API Key must be replaced with Generic Access Token in the settings.

= 4.3.14 [03/30/2020] =
* New - WordPress 5.4 support.
* Improvement/Bug Fix - [LinkedIn] - Updated to latest LinkedIn API requrements.
* Bug Fix - [Mailchimp] - fixed character limit.

= 4.3.13 [02/28/2020] =
* Improvement/Bug Fix - [LinkedIn] - autoposting to "Pulse" changed to "Post New Article".
* Improvement/Bug Fix - [Pinterest] - Ability to add title to posts.
* Improvement/Bug Fix - [XING] - compatibility with all recent changes - "Image" posts to profiles and company pages, new post format for company pages, etc. It also fixes all the recent connection issues. 
* Bug Fix - [LinkedIn] - Broken autoposting to company pages using Premium API.
* Bug Fix - [Scoop.It] - error "302" has been fixed.
* Bug Fix - [Flipboard] - several login issues fixed. 

= 4.3.12 [02/16/2020] =
* Bug Fix - Blogger J69 Error fix

= 4.3.10 [11/13/2019] =
* New - WordPress 5.3 support.
* Bug Fix - Workaround for Wordpress 5.3 Bug #48599 causing "Warning: count(): Parameter must be an array" error message
* Bug Fix - Some custom taxonimies were ignored in %HCT-

= 4.3.9 [08/23/2019] =
* Improvement/Bug Fix - Session support for Blogger
* Improvement/Bug Fix - Proxy support for Reddit, Pinterest, Blogger.
* Improvement/Bug Fix - Better support for autoposting to latest Wordpress based Sites (including wordpress.com)

= 4.3.8 [06/25/2019] =
* New - %AUTHORUNAME% tag. It will put username.
* New - ability to "Exclude" authors in filters.
* Improvement/Bug Fix - Several small improvements and bug fixes.

= 4.3.7 [05/22/2019] =
* Bug Fix - Fixed incompatibility with ACF Plugin.

= 4.3.6 [05/14/2019] =
* New - WordPress 5.2 support.
* New - %AUTHORTWHANDLE% tag. It will put @twitterusername
* Improvement/Bug Fix - Better compatibility with latest versions of WPML
* Improvement/Bug Fix - [LinkedIn] Better compatibility with new API V2 changes. Fixes authorization issues.
* Bug Fix - Problems with scheduling on sites with huge amount of posts.

= 4.3.5 [04/16/2019] =
* Bug Fix - Plugin will no longer be trying to post to "unfinished" networks.
* Bug Fix - Sunday is no longer ignored in the reposter schedule. 

= 4.3.4 [04/03/2019] =
* New [Google My Business] – Events are now supported.
* Changed - [Google+] Google+ is no longer available for consumer (personal) and brand accounts. SNAP now supports only Google+ for G Suite. 
* Removed - [500px] 500px API has been shut down, so it was removed from SNAP.
* Bug Fix - [vk.com] Better compatibility with groups/publics.

= 4.3.3 [02/08/2019] =
* Bug Fix - fix for "in_array() expects parameter 2 to be array" error
* Bug Fix - [Security] Several security fixes. 

= 4.3.2 [02/07/2019] =
* New - Wordpress 5.0/Guttenberg support.
* New - PHP 7.3 support.
* Improvement - [LinkedIn] Support for new LinkedIn Groups 
* Bug Fix - [Blogger] fix for "Incorrect OAuth URL"
* Bug Fix - [Blogger] fix for "Incorrect password"
* Bug Fix - [vk.com] fix for "page not found"
* Bug Fix - [Rebrandly] configuration were not saved.

= 4.2.8 [11/02/2018] =
* New Network [Early Beta] – Google My Business (with third party API library) 
* Improvement - [Telegram] Support for Proxy
* Improvement - [LiveJournal] New Option - "Keep Original Post Date"
* Bug Fix - [Reposter] Sometimes reposter were skipping posts on Sunday.
* Bug Fix - [Security] Fix for XSS Vulnerability. 
* Bug Fix - "Skip/Discard/Don't Autopost" option for overlimit Query posts were not working correctly.

= 4.2.7 [08/15/2018] =
* Improvement - [Blogger] Support for Proxy
* Improvement - [Reddit] Support for Proxy
* Bug Fix - [Reposter] Fixed issue with "Loop it. Reset and Start from the beginning" option.

= 4.2.6 [07/06/2018] =
* New - [Google+] Alternative configuration method.
* New - [Google+] Support for proxies.
* Removed - [Stumbleupon] Stumbleupon service has been closed, so it was removed from SNAP.
* Bug Fix - Fixed [Change] button in the time scheduler.

= 4.2.5 [06/28/2018] =
* Bug Fix - Incorrect post format in reposter posts
* Bug Fix - [Update All] button didn't work sometimes.
* Bug Fix - [XING] Configuration issues.
* Bug Fix - [Youtube] fatal error.
* Bug Fix - [Twitter] Missing parts of Twitter message if "spin" syntax is used.
* Bug Fix - [Scoop.it] Incorrect error reporting.

= 4.2.4 [05/31/2018] =
* Bug Fix - Fix for "Hide unused accounts" functionality.
* Bug Fix - Incorrectly configured bit.ly was causing huge recodrs in meta table.
* Bug Fix - Fix for "&amp;" problem in URL parameters
* Bug Fix - [XING] Correct API shown 
* Bug Fix - [ok.ru] "Image" post type was not selectable
* Bug Fix - [ok.ru] Fix for "PARAM_API_KEY : Application not exist" and "IMAGE UPLOAD ERROR" errors
* Several minor bug fixes

= 4.2.3 [04/13/2018] =
* Improvement - Improved security - some sensitive data is now hidden.
* Bug Fix - [vk.com] fis for disappearing accounts. 
* Several minor bug fixes

= 4.2.1 [04/04/2018] =
* Bug Fix - Compatibility fix for PHP versions earlier then 5.5.0 ("Can't use function return value in write context" error)

= 4.2.0 [04/04/2018] =
* New - BuddyPress Support - BuddyPress posts could be autoposted to configured networks.
* New - [is.gd] New default URL shortener - is.gd
* New - [Mailchimp] Support for list segments.
* New - New setting - list of tags to be excluded from autoposting.
* New - New setting - "Hide unused networks". Helps to remove clutter from "New post" page.
* New - Improved security - some sensitive data is now encrypted.
* Improvement - "Exact posts" filter will show all post types.
* Removed [Delicious] Bookmarking service Delicious has been shut down.
* Removed [Goo.gl] Google is shutting down its goo.gl URL shortener
* Many code improvements, optimizations and some bug fixes. 

= 4.1.7 [03/02/2018] =
* Improvement/Bug Fix - [vk.com] Update for new VK API / fix for "No Image is uploaded" error.

= 4.1.6 [03/02/2018] =
* Improvement/Bug Fix - [vk.com] Update for new VK API / fix for "v (version) is required" error.
* Several minor bug fixes and performance improvements. 

= 4.1.5 [02/15/2018] =
* Improvement - [LinkedIn] Alternative way of configuring LinkedIn
* Improvement - [Flipboard] Alternative way of configuring Flipboard
* Bug Fix - [Scoop.it] "Code:" error.

= 4.1.4 [02/08/2018] =
* New - Two new message formatting tags: %POSTDATE% and %POSTTIME%
* Bug Fix - Unable to uncheck filtered account after post was scheduled or saved as draft.
* Several minor bug fixes. 

= 4.1.3 [02/06/2018] =
* Several minor bug fixes. 

= 4.1.2 [01/11/2018] =
* New - Added support for Nextscripts Scoop.it premium API.
* New - [Pro Only] Post to each network can be scheduled for multiple times from SNAP metabox. 
* Bug Fix - Non-working "Cancel" button in Query/Timeline
* Bug Fix - [Quick Post] Sometimes incorrect text is used when Quick Post is "Scheduled"

= 4.1.1 [12/08/2017] =
* New - "SNAP Quick Post" custom post type is now available in reposter.
* Improvement – Better protection against duplicates.
* Bug Fix - [StumbleUpon] "Invalid Token" issue.

= 4.1.0 [12/05/2017] =
* New [Beta] – WPML and Polylang support in Filters. 
* New Network – Messenger Line (line.me)
* New/Improvement – [Post Edit] - All changes are saved instantly. No more "Please save the post to apply changes".
* New/Improvement – [Import/Export Accounts] - There are 4 options now - Export All, Export Selected, Import and replace, Import and Merge
* Improvement – PHP 5.3 compatibility. (It still works better on PHP 5.6 and 7+)
* Bug Fix - [Twitter] Option "Dash" has been removed from "Replace spaces in hashtags with ..." because Twitter no longer supports dashes in hashtags.
* Several minor bug fixes. 

= 4.0.11 [11/10/2017] =
* New/Improvement – [Twitter] Compatibility with new 280 characters limit.
* Bug Fix - [Critical] Post Editor Broken
* Bug Fix - [Critical] Javascript Error - nxs_updateGetImgsX is not defined
* Bug Fix - [Critical] Only First network was autoposted
* Bug Fix - No SNAP Metabox if post type is not excluded

= 4.0.9 [11/09/2017] =
* Bug Fix - Some unnecessary debug info has been removed.
* Bug Fix - Broken Text/Manual Post Dialog
* Bug Fix - [Twitter] Unable to uncheck "Attach Image".

= 4.0.8 [11/07/2017] =
* New - New Option "Check Query Every (Sec)"
* Improvement/Bug Fix – Will not longer try to post to "unfinished" accounts.
* Bug Fix - [Reposter] Same post was selected.
* Bug Fix - "Post Links" shortcode is back.
* Bug Fix - Image selection was broken.
* Bug Fix - [LinkedIn] Protection against ghost account records created by browser.
* Bug Fix - Incompatibility with some plugins causing javascript issues.

= 4.0.7 [10/27/2017] =
* New - [Reposter] New Option "Post each post only once".
* Bug Fix - Comments import issues.
* Bug Fix - [Reposter] Limit Reposting to only specific days and hours were not working correctly.
* Bug Fix - [Reposter] Unable to save "When finished" option
* Bug Fix - [Reposter] "Post ONLY never autoposted posts" should count reposter made posts as well.
* Bug Fix - [Filters] Problems with "Custom fields"
* Bug Fix - [Filters] Problems with "Custom taxonomies"
* Bug Fix - Broken [Show More] buttons on "Edit Post" pages.
* Bug Fix - [Medium] Authorization issue.
* Bug Fix - Incompatibility with plugins that leave WP Query filters.

= 4.0.6 [10/25/2017] =
* Bug Fix - [Critical] Incompatibility with some SEO and Page Building plugins.
* Bug Fix - [Critical] Broken "Add Media" button issue.

= 4.0.5 [10/24/2017] =
* Bug Fix - [Twitter] "media_ids parameter is invalid." error.
* Bug Fix - [Telegram] Invalid formpost error.
* Bug Fix - Empty/Blank reposter page.
* Bug Fix - Javascript Error nxs_updateGetImgsX.
* Bug Fix - Twitter, Telegram, were ignoring "Attach Image" checkboxes.
* Bug Fix - Cron report messages.
* Bug Fix - [Reposter] Random reposter will wait for new posts when number of posts is 0, instead of stopping.
* Bug Fix - [Reposter] Random Reposter were always choosing the same post if WPML or some other plugins are installed.

= 4.0.4 [10/19/2017] =
* Improvement - You can now exclude regular posts from autoposting and autopost only pages or only custom post type.
* Bug Fix - "nxs_doManPost is not defined" JavaScript Error.
* Bug Fix - SNAP plugin messes admin Interface, breaks Visual Composer, Woocommerce interfaces (Add Order, Add Coupon, Add Product)
* Bug Fix - Reposter does not save the dates.
* Bug Fix - List of Reposters is showing only 5 recent items.
* Bug Fix - SNAP Interface is NOT shown for WP Pages even if selected in filters.
* Bug Fix - [Twitter] Bad Image Error
* Bug Fix - [Stumbleupon] Invalid token error.
* Bug Fix - Stability improvements. Should not cause crashes and white pages anymore.

= 4.0.3 [10/17/2017] =
* Bug Fix - CSS Image has been removed from all non-SNAP pages.
* Bug Fix - Insecure request for remote image has been removed.
* Bug Fix - [Telegram] Missing images.
* Bug Fix - Broken [Show More] buttons.
* Bug Fix – Performance improvement for some sites experiencing slow down.
* Bug Fix – Security improvements.

= 4.0.2 [10/16/2017] =
* Improvement/Bug Fix – Some performance improvements and bug fixes.

= 4.0.1 [10/15/2017] =
Initial Version 4 Release. 100% Re-written. New engine. New interface. Existing Posts Auto-Reposter. More info: <a href="https://www.nextscripts.com/v4/">https://www.nextscripts.com/v4</a>

= 1.0.0 - 3.8.8 [05/05/2012 - 08/26/2017] =
Here: <a href="https://www.nextscripts.com/snap-wp-changelog.txt">https://www.nextscripts.com/snap-wp-changelog.txt</a>

== Upgrade Notice ==

= 4.5.2 =
Just replace plugin files, the rest will be updated automatically.

== Other/Copyrights ==

Plugin Name: NextScripts Social Networks Auto-Poster
Plugin URI: https://www.nextscripts.com/social-networks-auto-poster-for-wordpress
Description: This plugin automatically publishes posts from your blog to supported social network profiles, pages, and groups.
Author: NextScripts
Author URL: https://www.nextscripts.com
Copyright 2012-2026 NextScripts Corp
PHP Twitter API: Copyright 2012 - themattharris - tmhOAuth
NextScripts Corp