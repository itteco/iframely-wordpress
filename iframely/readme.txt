=== Iframely - rich media embeds for 2000+ publishers ===
Contributors: psergeev, ivanp
Tags: iframely, oembed, embed, embed code, responsive, video, amp, youtube, vimeo, instagram, gist, gif, giphy, google maps, facebook, mu, streamable, gfycat, vidme, Reddit, Tableau, spotify, prezi, apester, qzzr, tidal, mlb, media
Requires at least: 3.5.1
Tested up to: 5.4.1
Stable tag: trunk
License: MIT


Iframely extends WordPress default rich media embeds and adds support of over 2000 more providers and cards as URL previews for the rest of the Web. Provides responsive embed codes, works with AMP. Powered by Iframely cloud service and requires an account with us.

== Description ==


[Iframely](https://iframely.com?from=wp) extends WordPress default embeds and adds over 2000 more providers and cards as URL previews for the rest of the Web. Provides responsive embed codes, works with AMP. 

Just like default WordPress embeds, Iframely will detect URLs in your posts and replace it with responsive embed codes. Supports all usual suspects such as YouTube, Vimeo, Instagram, Facebook, Giphy, GfyCat, Imgur, Google +, GitHub Gists, Storify, SlideShare, Streamable, Vidme, Reddit, Dailymotion, Spotify, Tableau, Prezi, Apester, QZZR, Tidal, MLB. Well over two thousand of providers and keeps growing. 

[Test your URL here](https://iframely.com/embed) to check if we support it.

Iframely also generates and hosts summary cards as URL previews for general articles. It includes your own site, and Iframely can replace the default embed cards that your publish via WordPress for other sites to use.

= API key and paid/free =

Powered by Iframely cloud service and requires an account with us. Grab an API key for extended trial at [iframely.com](https://iframely.com), or test the plugin out without an API key. 


Before you push the plugin to production, a [service subscription](https://iframely.com/plans) is required to avoid any possible disruptions. 

We maintain an overwhelming number of integrations and widgets in the background for you, and make sure your sites remains fast and keeps your users happy. This requires a substantial effort on our part, and we won't be able to provide it without requiring a service subscription.



= How to use: = 

The plugin works the same way the standard oEmbed is supported by WordPress: URL on a separate line. 

For example, 


`
Check out this cool video:

http://your.url/here

That was a cool video.
`


Iframely also has its own shortcode `[iframely]http://your.url/here[/iframely]`.


= Heads-up: =  

Iframely does not simply wrap URLs with <code>&lt;iframe src=...&gt;</code> code. That's not what Iframely is for. We can only match URLs to the known embed codes if publisher offers them for manual copy-paste, or generate a summary card for URL preview if a publisher provides at least a thumnnail image.


= To keep default embed providers = 

By default, Iframely will inject itself to be the first embeds provider in the list, thus intercepting all URLs. It means that the default providers that are later in the list won't get called and will thus be disabled. 

It means Iframely replaces default YouTube, Vimeo, Twitter, other oEmbed plugins that you might have(like JetPack), etc. 

Although we should support the same providers and output the same code, just make it responsive and add extra features, you can still disable such behavior and tell Iframely to only process links that otherwise don't have an embed provider. 

Just choose this option in your settings. It will essentially put Iframely to be the last in the list, be "a catcher", rather then "an interceptor".

= URL editor options = 

You can fine-tune many aspects of Iframely on your dashboard at iframely.com. This would include some most common settings for popular rich media publishers. Our support team can also fine-tune many other publishers, just for you. 

However, you might need a per-post editor for the widgets options. And, Iframely does provide this too. It's done via [URL options](https://iframely.com/docs/options) editor, and available for higher-tier plans, or during your initial trial period. Make sure to check it out. 

= AMP support = 

Yes, Iframely works nicely with [AMP WP plugin](https://wordpress.org/plugins/amp/). It catches all missing embeds and follow your Iframely settings. But you can also opt to have Iframely for all embeds, including default AMP embeds too. For example, Facebook video will be indeed a nice video without user's text message.


== Installation ==

The basic installation is standard:

1. Upload the package contents to to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to plugin settings to configure how you'd like to connect to Iframely cloud

Upon install, Iframely will work in new posts only. Iframely doesn't automatically refresh embeds in your old posts. By default, WordPress keeps embed codes cached until after author edits/saves a post. 

If you'd like to drop the cache for older posts, and also to make Iframely refresh WordPress embeds cache more often, change "Cache the embed codes for this number of days" value on Iframely settings page of your WordPress admin section.

To be able to save your settings, you'd need to get your own API key to connect to Iframely cloud. Get one by signing up at [iframely.com](https://iframely.com?from=wp). New API keys come with unlimited 30 days trial, and have a free tier option afterwards.

However, API key isn't required if you just want to give Iframely a try. The plugin will work with the default settings, albeit only for new posts or if you manually edit/save posts with expired embed codes.

You can fine-tune many more aspects of Iframely in your account settings with us, and therefore having API key is required for production usage.


== Screenshots ==

Here's some samples of what Iframely supports. 

1. Over 2000 domains
2. Responsive embed codes
3. This is Giphy
4. This is Tidal
5. This is Google Maps
6. Change URL options
7. Change URL options
8. Change URL options
9. Change URL options

[Here's some more examples](https://iframely.com/domains).

== Frequently Asked Questions ==

= I thought Iframely would wrap my URL into iframe? =

That's not a purpose of the plugin. Iframely works with original URLs and will detect the frame's `src` itself. If you just want to use `<iframe>`, switch your editor to HTML format and past iframe code in there.

= API key? =

Iframely is powered by the cloud APIs. Getting embed codes requires connecting to multiple 3rd party servers, and packiging it all into your WordPress server would create unsustaible workload. This plugin is therefore integrating your WordPress with Iframely cloud.

To be able to save your settings, you'd need to get your own API key. Get one by signing up at [iframely.com](https://iframely.com?from=wp). New API keys come with 30 days tria. You will require a monthly [subscription](https://iframely.com/plans) if you want to continue using Iframely on your live site afterwards.

However, API key isn't required if you just want to give Iframely a try. The plugin will work with the default settings, albeit only for new posts or if you manually edit/save posts with expired embed codes.

We maintain an overwhelming number of integrations and widgets in the background for you, and make sure your sites remains fast and keeps your users happy. This requires a substantial effort on our part, and we won't be able to provide it without requiring a service subscription.


= How do I resize my widgets? = 

Well, that's the point of responsive embeds. You don't have to. 

Iframely widgets will take 100% available width. If you need to customize CSS, add '&omit_css=1' as "Optional API query-string param" on Iframely config page and follow [this guide](https://iframely.com/docs/omit-css).

Please, note that not all the embeds codes will be responsive. Embeds from a tiny number of less frequent publishers cannot be converted responsively without ruining their content. 

= Is it compatible with other embeds plugins I have? =

* Iframely works in the same WordPress embeds framework as other plugins would. 
* Since default WordPress embeds are not responsive, Iframely can disable the standard code and replaces it with the responsive embeds. Otherwise, you might be wondering why is some widgets are not responsive 
* You can conifugure Iframely to only work in its own shortcode, thus leaving other plugins intact.
* Iframely plays well with [AMP](https://wordpress.org/plugins/amp/).

= What about embeds in my previous posts? =

Iframely works in native WordPress embeds framework. All your new posts should start seeing responsive widgets. The older posts will be re-cached via WordPress logic itself (usually when you edit and save the post), and should granually get the new embeds code too. However, we do not interfere into this default process and do not re-cache older posts upon install. 

To change this logic, define "Cache the embed codes for this number of days" on Iframely options page.

If there's a specific post you'd like to update, just go to Edit and Save it again. It should re-cache it and trigger Iframely.

= Will my other shortcodes work? =

Iframely does not change behavior of other shortcode plugins if they don't plug into oEmbed sandbox. Everything should work the old way, except for `[embed]` shortcode, which will now start producing responsive widgets unless limited in size.

= Does it support WordPress MU? =

Iframely will work well with multisite installations. Iframely options page is available for super admins only, and the settings will be the same for all blogs on your WPMU setup. 

= Oh, the Facebook! = 

Yes, Iframely knows the embed codes for Facebook posts, photos and videos. However, some of the posts can be private and not accesssible to our parsers. For those, we can not convert the URL into embed code. Also, Facebook events don't have native embed codes and so Iframely doesn't support these too. 

== Changelog ==

= 0.7.2 =

Fix scripting errors in Gutenberg editor in latest WordPress 5.4+.

= 0.7.0 =

Introducing [URL options](https://iframely.com/docs/options) editor for your Gutenberg embed blocks. Available for higher-tier plans or during a trial period.

We add publishers fine-tuning options as if you manually copy-pasted HTML codes from their websites. If you see that we miss any options that are important to you - let us know at support@iframely.com.


= 0.6.0 =

Keeping up with the changes to [AMP WP plugin](https://wordpress.org/plugins/amp/). Also making caching more reliable and responsive to the changes in settings.

= 0.5.0 =

Making Iframely to work nicely with [AMP WP plugin](https://wordpress.org/plugins/amp/). Iframely now catches all missing embeds and follow your Iframely settings. But you can also opt to have Iframely for all embeds.


= 0.4.0 =

Turns out, WordPress does not follow cache_age response from API after all. It only refreshes embed codes when you edit and save post. [This](https://core.trac.wordpress.org/ticket/37597) isn't right. This update enables you to refresh embed codes periodically. It also gives and option to add any [query-string parameters](https://iframely.com/docs/parameters) to the use with API.

= 0.3.1 =

We are reverting one of the changes in version 0.3.0 - linking Iframely to single post/page scope. Our apologies: we casted the net too wide and Iframely wasn't working properly with some installations.

= 0.3.0 =

WordPress 4.5+ forces you to use built-in default cards when you want to embed a link to your own site. Iframely v0.3.0 returns the option for you to use Iframely cards instead. To remind: you can change design of cards at [iframely.com](https://iframely.com)

Iframely v0.3.0 also disables the plugin outside of single post/page scope, as WordPress has caching issues and otherwise creates tremendous load to our servers.

= 0.2.9 =

Since WP 4.4, your site [publishes embeds](https://make.wordpress.org/core/2015/10/28/new-embeds-feature-in-wordpress-4-4/) by default so that other WP sites can embed summaries of your posts.

Iframely v 0.2.9 gives you an option to override the default widgets and use Iframely hosted [summary cards](https://iframely.com/docs/cards) instead. Change design in your Iframely account settings.

= 0.2.8 =

 - Support of direct links to GIF files (mobile-friendly!)
 - Features that were rarely used are now retired (embeds publishing, cache auto-pilot)
 - Keep up to WordPress 4.3


= 0.2.4 =

 - Makes Iframely work with WordPress 4.0 real-time previews


= 0.2.3 =

 - We enabled the hosted widgets. With it, we now can give you embed codes for videos that autoplay. We also handle SSL well, and provide graceful fallbacks for Flash videos for your iOS/mobile visitors. To enable this option, turn it on in Iframely settings.

 - We also fixed the broken link to Iframely settings. The one that was on plugins list page, so it properly links to the same settings you have in main (left) menu.


= 0.2.2 =

This version includes fixes for WordPress Multisite. Iframely plugin options page will be available only for the super admins. 

The regular WP installations should remain intact and do not require an instant upgrade. 


= 0.2.0 =

There are 3 main changes: API Key, Shortcode, and Options page.

 - In order to keep our servers up and running, we need to secure the API with the API Key. Get your [FREE API Key here](http://iframe.ly?from=wp). 
 - If you don't want the hastle of configuring API Key, just shorten your links manually at [http://iframe.ly](http://iframe.ly?from=wp) first, before pasting it into your post. The short URL will come with the embed codes attached to it.
 - Also, Iframely now has the options page where you can configure the way you'd like to use it.
 - For example, you can opt to use Iframely in `[iframely]` shortcode only, leaving all the other default oEmbed providers intact.
 - `[iframely]http://your.url/here[/iframely]` shortcode itself was introduced in this version.



= 0.1.0 =

This is our initial release. Please, rate if you like the plugin. 

And please, help do submit issues if you see any.



== Upgrade Notice ==

= 0.7.2 =

Fix scripting errors in Gutenberg editor in latest WordPress 5.4+.

= 0.7.1 =

Introducing [URL options](https://iframely.com/docs/options) editor for your Gutenberg embed blocks. Available for higher-tier plans or during a trial period.

We add publishers fine-tuning options as if you manually copy-pasted HTML codes from their websites. If you see that we miss any options that are important to you - let us know at support@iframely.com.


= 0.6.0 =

This Iframely update fixes compatibility with the newer versions of [AMP WP plugin](https://wordpress.org/plugins/amp/). Also makes caching more reliable and responsive to the changes in your settings.

= 0.4.0 =

Turns out, WordPress does not follow cache_age response from API after all. It only refreshes embed codes when you edit and save post. [This](https://core.trac.wordpress.org/ticket/37597) isn't right. This update enables you to refresh embed codes periodically. It also gives and option to add any [query-string parameters](https://iframely.com/docs/parameters) to the use with API.


= 0.3.1 =

We are reverting one of the changes in version 0.3.0 - linking Iframely to single post/page scope. Our apologies: we casted the net too wide and Iframely wasn't working properly with some installations.

= 0.3.0 =

WordPress 4.5+ forces you to use built-in default cards when you want to embed a link to your own site. Iframely v0.3.0 returns the option for you to use Iframely cards instead. To remind: you can change design of cards at [iframely.com](https://iframely.com)

= 0.2.9 =

Since WP 4.4, your site [publishes embeds](https://make.wordpress.org/core/2015/10/28/new-embeds-feature-in-wordpress-4-4/) by default so that other WP sites can embed summaries of your posts.

Iframely v0.2.9 gives you an option to override the default widgets and use Iframely hosted [summary cards](https://iframely.com/docs/cards) instead. Change design in your Iframely account settings.

= 0.2.8 =

This update adds support of direct links to GIF files (mobile-friendly!). It also retires features that were rarely used are now retired (embeds publishing, cache auto-pilot).

= 0.2.7 =

This update adds support of the new feature of Iframely cloud: [caching autopilot](https://iframely.com/docs/caching). It requires smart iFrames as embed codes and an API key.

= 0.2.6 =

Iframely now lets you publish embed codes from your own site through summary cards or promo cards. Let other WordPress blogs and sites re-post your content! [Learn more](https://iframely.com/docs/promo-cards)


= 0.2.5 =

Hey, we know you'll be happy about it. Our "No API Key is required" stance is back. API Key is now optional for Iframely WordPress plugin.


= 0.2.4 =

Makes Iframely work with WordPress 4.0 (sigh)

= 0.2.3 =

This version enables the "hosted widgets" option (to properly handle autoplay videos, SSL, Flash-on-iOS and also improve load times). 

= 0.2.2 =

This is a patch for WordPress MU. If you are on a single site installations, you can safely skip this version.

= 0.2.0 =

In order to keep our API servers up and running, we need to secure the API with the API Key. Get your FREE API Key at iframe.ly. The previous (open) API will be available until August 1st, 2014 only. 