=== Iframely Responsive Embeds ===
Contributors: @ivanp, @psergeev
Tags: iframely, oembed, embed, responsive, video, youtube, vimeo, instagram, gist, fitvids, mu
Requires at least: 2.9
Tested up to: 3.6.1
Stable tag: 0.1.0
License: MIT


Iframely converts URLs in your posts into responsive embed widgets for 900+ domains. 



== Description ==

[Iframely](http://iframely.com?from=wp) brings you the responsive embeds. It means the embeds will resize if you are on responsive theme. 

Iframely will detect URLs in your posts and replace it with responsive embed codes for over 900 domains. Supports YouTube, Vimeo, Instagram, GitHub Gists, Google +, Facebook Posts, Storify, SlideShare, well, you know, over 900 of them and keeps growing.

Iframely replaces the default WordPress embed method and does not require any API keys or signups on our main site. 
Was tested and works well with multi-site installations. 

The plugin works the same way the standard oEmbed is supported by WordPress: either an URL on a separate line, or [embed] shortcode.

Powered by [Iframely Open-Source API](http://iframely.com/gateway). Download plugin and change the API address if you'd like to host API yourself.



== Installation ==

Iframely only adds couple lines of code and a jQuery file for formatting of some embeds on the client side. 

As such, the installation is pretty standard:

1. Upload `iframely.php` to the `/wp-content/plugins/` directory
2. Upload `/js/iframely.js` jQuery plugin to javascript directory
3. Activate the plugin through the 'Plugins' menu in WordPress

No other configuration of the plugin is required. You are good to go.



== Screenshots ==

Here's some samples of what Iframely supports. 

1. Instagram sample
2. Vimeo sample
3. GitHub Gists
4. Google+ Posts
5. Imgur & Galleries
6. Facebook Statues
7. And many-many others


== Frequently Asked Questions ==

= How do I resize my widgets? = 

Well, that's the point of responsive embeds. You don't have to. 

Iframely widgets will take 100% available width. If you need to limit it, just define your CSS styles for `iframely-widget-container`.

Please, note that not all the embeds codes will be responsive. Some of hosting domains can not be converted responsively. In those cases, Iframely will left-align the embeds. You might want to consider padding and alignment styles, if you don't have generic ones for `iframes` and `images` yet.

= Is it compatible with other embeds plugins I have? =

* Iframely works in the same WordPress embeds framework as other plugins would. 
* Since default WordPress embeds are not responsive, Iframely disables the standard code and replaces it with the responsive embeds. Otherwise, you might be wondering why is some widgets are not responsive 
* This said, Iframely asks that it is a single oEmbed endpoint for any URL. It will un-register other parsers upon install. 

= What about embeds in my previous posts? =

Iframely works in native WordPress embeds framework. All your new posts should start seeing responsive widgets. The older posts will be re-cached via WordPress logic itself, and should granually get the new embeds code too. However, we do not interfere into this default process and do not re-cache older posts upon install. 

If there's a specific post you'd like to update, just go to Edit and Save it again. It should re-cache it and trigger Iframely.

= Will my shortcodes work? =

Iframely does not change behavior of the shortcode plugins. Everything should work the old way, except for `[embed]` shortcode, which will now start producing responsive widgets unless limited in size.



== Changelog ==

= 0.1.0 =
This is our initial release. Please, rate if you like the plugin. 

And please, help do submit issues if you see any.



== Upgrade Notice ==

It's our first release. But WP asks us to put an upgrade notice here :)



== Underlying tech ==

The server-side API is open-source. Check it out here: [http://iframely.com/gateway](http://iframely.com/gateway).

If you choose to host API on your own, just change the endpoint address in the plugin code. 

Responsive embeds concept is part of [Iframely Protocol For Responsive Embeds (oEmbed/2)](http://iframely.com/oembed2).