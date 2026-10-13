=== Playlist List Block for YouTube ===
Contributors: yourwporgusername
Tags: youtube, playlist, video, block, gutenberg
Requires at least: 6.3
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show a YouTube playlist as a clean, numbered list of videos. Just enter the playlist ID.

== Description ==

Add the "YouTube Playlist List" block, paste a playlist ID (or the full playlist URL) and you get a tidy list with thumbnails, titles, channel names and durations. Every row links to the video on YouTube.

* Live preview in the block editor
* Dark and light block styles
* Duration, UPCOMING and LIVE badges
* Server-side caching (1 hour by default) to protect your API quota
* No build step, no external libraries, no tracking

You need a free YouTube Data API v3 key. Add it under Settings > YouTube Playlist.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/ or install the zip via Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Go to Settings > YouTube Playlist and paste your YouTube Data API key.
4. Edit a post or page, add the "YouTube Playlist List" block and enter a playlist ID.

Optional: define the key in wp-config.php instead of storing it in the database: define( 'PLB_API_KEY', 'your-key' );

== Frequently Asked Questions ==

= Where do I find the playlist ID? =

It is the value after list= in the playlist URL, for example PLxxxxxxxx.

= Why are some videos missing? =

Private and deleted videos are hidden. The block shows up to 50 videos.

= Who can see error messages? =

Only logged-in users who can edit posts. Visitors never see API errors.

= How much API quota does it use? =

One unit per playlist request, plus one if durations are enabled. Results are cached for an hour. Change the cache time with the plb_cache_ttl filter.

== External services ==

This plugin sends the playlist ID and your API key from your server to the YouTube Data API (https://www.googleapis.com/youtube/v3/) to fetch playlist details. Video thumbnails are loaded by visitors' browsers from YouTube's image servers (i.ytimg.com).

* YouTube Terms of Service: https://www.youtube.com/t/terms
* Google Privacy Policy: https://policies.google.com/privacy

== Changelog ==

= 1.0.0 =
* Initial release.
