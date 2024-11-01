=== Tylr Slidr ===
Contributors: Tyler Craft
Donate link: http://www.tylrslidr.com/
Tags: flickr, slideshow, flickrslidr, photos
Requires at least: 2.6
Tested up to: 2.9
Stable tag: 1.6

The Easiest Way to Pull Your Flickr Photos into Wordpress.

== Description ==

Tired of copying and pasting the object/iframe HTML from Flickr and other tools like FlickrSlidr? Then this plugin is for you. It is the easiest way to pull your flickr photos into Wordpress.

This plugin adds a button to the WYSIWYG. Once clicked, an inline popup will come up to allow you to enter the URL of the slideshow. It then creates a quicktag to add the slideshow, similar to Viper's Quicktags.

== Installation ==

###Installing The Plugin###

Extract all files from the ZIP file, **making sure to keep the file structure intact**, and then upload it to `/wp-content/plugins/`. This should result in multiple subfolders and files.

Then just visit your admin area and activate the plugin.
**See Also:** ["Installing Plugins" article on the WP Codex](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins)

###Plugin Configuration###

To configure this plugin, visit it's settings page. It can be found under the "Settings" tab in your admin area, titled "Tylr Slidr".

On this page you can configure 5 things:

1. Default flickr user id
2. Default flickr group id
3. Default slideshow height
4. Default slideshow width
5. Position of button on the WYSIWYG

Finally, go write a post! You should see a new icon in your WYSIWYG. If you don't, you may need to click the 'Show/Hide the Kitchen Sink' icon to reveal more WYSIWYG buttons.

== Frequently Asked Questions ==

= Why not use a tool like FlickrSlidr.com? =

FlickrSlidr.com is a great tool that will generate the HTML markup (an object tag or an iframe) for you to insert into your webpage. However, wysiwyg's don't handle this markup well. Editing posts with this markup will often erase the markup that FlickrSlidr originally created. Thus requiring you to create the slideshow from scratch.

== Screenshots ==

1. TinyMCE, the plugin's buttons, and the plugin's dialog window.
2. Tylr Slidr configuration page.

== ChangeLog ==

**Version 1.6**

* Now includes support for slideshows of Global Tag Pools

**Version 1.5**

* Tested for 2.8. Upgraded swfobject from 2.1 to 2.2. Using Flickr swf version 71649

**Version 1.4**

* Updated wmode support to use 'opaque' instead of 'transparent'. The slideshow doesn't have transparency, so this will improve CPU performance.

**Version 1.3.0**

* Added support for wmode transparency

**Version 1.2.0**

* Tested for 2.7
* Updated CSS and JS window size for new 2.7 admin

**Version 1.1.0**

* Takes advantage of Flickrs new slideshow, which allows for fullscreen and better image scaling support.
* Uses SWFObject to insert slideshows now.

**Version 1.0.0**

* Inital release.