=== Firebase URL Shortener ===  
Donate link: https://www.flynsarmy.com/  
Tags: links, short, url, socialmedia, permalinks, redirect, microblogging, shorturl  
Requires at least: 3.0.0  
Tested up to: 5.6.0  
Stable tag: 2.1.1  

Uses Google's URL shortener (Firebase) to create short links for your WordPress posts.  

== Description ==

Google has launched a URL Shortener API (Firebase) - one of the fastest and most reliable URL shortners out there. This plugin creates short URLs for your posts, which then could be retrieved using the "Get Shortlink" button in your admin UI or the `wp_get_shortlink()` WordPress function.

== Installation ==

1. Upload archive contents to the `/wp-content/plugins/flyn-firebase` directory
1. Visit https://console.firebase.google.com and create a project URL Shortener
1. From the Project Settings page copy the Web API Key value and paste it into a line in *wp-config.php* `define('FLYN_FIREBASE_API_KEY', '<API KEY HERE>');`
1. From the Grow - Dynamic Links page create a dummy IOS app skipping all steps. Copy the URL and paste it into a line in *wp-config.php* `define('FLYN_FIREBASE_URL', '<YOUR URL>');` (Should look like *https://abc123.app.goo.gl*)
1. Activate the plugin through the 'Plugins' menu in WordPress
1. You're done!

== Screenshots ==

1. Shortened URLs
2. Posts List Column

== Change log ==

= 2.1.1 (2020-12-11) =
* Delete shortlink post meta only when the slug changes.

= 2.1.0 (2020-10-01) =
* Don't create duplicate meta records for posts with existing goo.gl shortlinks

= 2.0.0 (2020-04-21) =
* Added dashboard shortener widget

= 1.0.0 =
* First version
