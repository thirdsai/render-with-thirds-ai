=== Render with thirds.ai ===
Contributors: thirdsai
Tags: open graph, featured image, social image, og image, template
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Renders a branded Open Graph and featured image for each post from a template when it is published.

== Description ==

(Disclosure: built by thirds.ai.)

Drafted with AI help. Plugin author and contributor: thirds.ai (listings@thirds.ai).

Render with thirds.ai gives every post a branded 1200 by 630 social image without opening an image editor.

**Example 1.** You publish "Ten things we learned this year". A few seconds later the post has a PNG card with your site name, the title and the excerpt in your colours. It is in the media library, it is the featured image (if the post had none), and it is the og:image that link previews use.

**Example 2.** You fix a typo in a title. Click **Re-render image** in the post's thirds.ai image box or in the posts list. The card is rendered again, the old one is removed and the new one takes its place.

You can use the bundled layout with your own colours, or a template you saved in your thirds.ai account.

This plugin needs a thirds.ai account and an API key. [Get one at thirds.ai](https://thirds.ai/?utm_source=wordpress&utm_medium=listing&utm_campaign=own-content&utm_content=wp-render).

== External services ==

This plugin connects to the thirds.ai API (https://thirds.ai), a paid external service, to render images. It needs a thirds.ai account and API key.

It makes no requests of any kind until you tick "Send posts to thirds.ai" on Settings > thirds.ai Render and set an API key. After that it contacts thirds.ai only:

* when a post of a chosen type is published for the first time, from a background WP-Cron event, and
* when you click **Re-render image** on a post.

Each render sends: the post title, the excerpt (or the first 24 words of the content), the author display name, the publish date, the first category name, the site name, the site host name, your three layout colours, and either the bundled HTML layout or your saved template id. Your API key is sent in the Authorization header. Nothing else from your site, your users or your visitors is sent. The rendered PNG is then downloaded from thirds.ai and stored in your media library.

Each successful render uses your thirds.ai credits.

* thirds.ai terms of service: https://thirds.ai/terms
* thirds.ai privacy policy: https://thirds.ai/privacy

== Installation ==

1. Install and activate the plugin.
2. Go to Settings > thirds.ai Render.
3. Enter your API key, or add `define( 'THIRDS_API_KEY', 'your key' );` to wp-config.php.
4. Tick "Send posts to thirds.ai", pick the post types and save.

== Frequently Asked Questions ==

= Does it work with Yoast SEO or Rank Math? =

When either is active, this plugin prints no Open Graph tags of its own and passes the rendered image to it through its og:image and twitter:image filters. Checked with Yoast SEO 28.8 and Rank Math 1.0.280 on a test site, with a local stand in for the thirds.ai API. Rank Math prints no tags at all until its setup wizard is finished. Other SEO plugins are untested and may print their own og:image next to this one.

= What happens to my existing featured images? =

By default the plugin sets the featured image only when the post has none. You can choose to always replace it or never set it.

= Where is the key stored? =

In the rwtai_api_key option, or nowhere in the database if you use the THIRDS_API_KEY constant. The key is never printed back on the settings page and never written to a log.

= What has been tested? =

Tested against the live thirds.ai API on WordPress 7.1.4 with PHP 8.4, the key set by the THIRDS_API_KEY constant and the bundled layout:

* Publishing a post renders a 1200 by 630 PNG in the background, adds it to the media library and sets it as the featured image.
* The post page then carries og:image, og:image:width, og:image:height and twitter:image for that PNG.
* Publishing the post again renders a new image and removes the old one.
* An unknown saved template id shows the API's error in the post's thirds.ai image box.

Tested only with a local stand in for the API, so untested against the live service: the Re-render button and row action, a successful saved template render, a key saved on the settings page, failed renders, and the Yoast SEO and Rank Math hand off.

Untested: multisite, PHP versions other than 8.4, WordPress versions other than 7.1.4, and object caches.

== Screenshots ==

1. Settings page.
2. The Re-render image action in the posts list.
3. A card rendered by thirds.ai from the bundled layout on a test site.

== Changelog ==

= 0.1.0 =
* First release.

== Upgrade Notice ==

= 0.1.0 =
First release.
