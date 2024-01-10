=== NPR Story API ===
Contributors: jwcounts, tamw-wnet, bdivver
Donate link: https://www.npr.org/series/750002/support-public-radio
Tags: npr, news, public radio, api
Requires at least: 3.8.14
Tested up to: 6.0
Stable tag: 1.9.6
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: nprapi

A collection of tools for reusing content from NPR.org, now maintained and updated by NPR member station developers.

== Description ==

The NPR Story API Plugin provides push and pull functionality with the NPR Story API along with a user-friendly administrative interface.

NPR's API is a content API, which essentially provides a structured way for other computer applications to get NPR stories in a predictable, flexible and powerful way. The content that is available includes audio from most NPR programs dating back to 1995 as well as text, images and other web-only content from NPR and NPR member stations. This archive consists of over 250,000 stories that are grouped into more than 5,000 different aggregations.

Access to the NPR Story API requires an API Key to NPR's legacy APIs. If you are an NPR member station or are working with an NPR member station and do not know your key, please [ask NPR station relations for help](https://studio.npr.org).

The WordPress plugin was originally developed as an Open Source plugin by NPR and is now supported by developers with NPR member stations working within the Open Public Media group. If you would like to suggest features or bug fixes, or better yet if you would like to contribute new features or bug fixes please visit our [GitHub repository](https://github.com/OpenPublicMedia/nprapi-wordpress) and post an issue or contribute a pull request.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/plugin-name` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the **Settings > NPR API** screen to configure the plugin. Begin by entering your API Key, then add your Push URL and Org ID.


== Frequently Asked Questions ==

= Can anyone get an NPR Story API Key? =

NPR is no longer provisioning public API keys for their legacy APIs. If you are an NPR member station or are working with an NPR member station and do not know your key, please [ask NPR station relations for help](https://studio.npr.org).

= Can anyone push content into the NPR Story API using this plugin? =

Push requires an Organization ID in the NPR Story API, which is typically given out to only NPR stations and approved content providers. If that's you, you probably already have an Organization ID.

= Where can I find NPR's documentation on the NPR Story API? =

There is some documentation in the NPR Story API site: [legacy.npr.org/api/index.php](https://legacy.npr.org/api/index).

= Is there an easy way to directly query the NPR Story API? =

There is some documentation in the NPR Story API site: [legacy.npr.org/api/index.php](https://legacy.npr.org/api/index).

== Screenshots ==

NPR Story API Plugin Settings screen

![NPR Story API Plugin Settings screen](docs/assets/img/npr-api-wp-plugin-settings.png)

NPR Story API multiple get settings

![NPR Story API multiple get settings](docs/assets/img/npr-api-multiple-get-settings.png)

Get NPR Stories link in the dashboard

![Get NPR Stories link in the dashboard](docs/assets/img/get-npr-stories-link.png)

Getting an NPR Story by Story ID

![Getting NPR Stories by Story ID](docs/assets/img/get-npr-stories-link.png)

NPR Stories having got gotten

![NPR Stories having got gotten](docs/assets/img/npr-stories.png)


== Changelog ==
= V1.9.6 =
* Articles imported from the API will now show the original article URL as the canonical URL in the header

= V1.9.5.1 =
* Adding `'no_found_posts' => false` to the query in `update_posts_from_stories()` for greater MySQL 8 compatability. Will also help on sites with large archives of posts
* Adding support for admin messaging regarding the sunsetting of the NPR Story API and this plugin. Will actually roll the messaging out once the successor plugin (NPR CDS) is available in the plugin directory

= V1.9.5 =
* Added a check for image dimensions for imported article thumbnails, and use NPR resizer if necessary (credit to [@tamw-wnet](https://github.com/tamw-wnet) for the catch and PR) [PR #22](https://github.com/OpenPublicMedia/nprapi-wordpress/pull/22)
* Added filter `npr_pre_article_push` to allow for modifying the organization ID or API key before pushing an article. Example: if you run a site with more than one org ID in the Story API and you want certain categories to push to certain IDs, you can create a function to hook into that filter and modify the org ID and API key as needed

= V1.9.4.1 =
* Fixed an issue where updating imported articles was not also updating the post thumbnail (all credit to [@tamw-wnet](https://github.com/tamw-wnet) for the catch) [Issue #19](https://github.com/OpenPublicMedia/nprapi-wordpress/issues/19)

= V1.9.4 =
* Changed `nprstory_get_datetimezone()` to generate a new `DateTimeZone` off of the `timezone_string` WP option. The previous method tried to use `gmt_offset` in seconds, but `DateTimeZone` requires a string
* Fixed an issue where inserted images in imported stories might have quotation marks in their alt tags
* Fixed an issue where images were being duplicated while sideloading (all credit to [@tamw-wnet](https://github.com/tamw-wnet) for the catch) [Issue #18](https://github.com/OpenPublicMedia/nprapi-wordpress/issues/18)

= V1.9.3.1 =
* Fixed bug that was preventing display of Simple Story elements in imported stories

= V1.9.3 =
* Fixed a bug where image credit metadata was being attached to the article and not the imported image
* Added support for the [Co-Authors Plus plugin](https://wordpress.org/plugins/co-authors-plus/), so that guest authors are attached to imported articles if they already exist
* Fixed a bug that was wrapping <div> elements in <p> tags
* Updated the plugin to use the PUSH settings for bylines, story body, and media credits when PULLing as well
* Added user setting to ignore primary images when importing articles with rich layout. This will be useful for site themes that automatically display featured images, so that images don't get duplicated (thanks to [@bdivver](https://github.com/bdivver)) [PR #17](https://github.com/OpenPublicMedia/nprapi-wordpress/pull/17)

= V1.9.2 =
* Added support for 'container' elements in stories
* Added the ability to specify profile types in recurring API queries (thanks to [@bdivver](https://github.com/bdivver)) [PR #15](https://github.com/OpenPublicMedia/nprapi-wordpress/pull/15)
* Streamlined/consolidated some of the parsing of story elements in 'get_body_with_layout()'
* Improved error handling when pulling a story that isn't available in the API
* Pulling articles now includes the primary audio file as an '[audio]' shortcode at the top of the body

= V1.9.1 =
* Multiple bug fixes for the new rich layout option, including transcripts, corrections, and support for slideshows [PR #12](https://github.com/OpenPublicMedia/nprapi-wordpress/pull/12) and [PR #13](https://github.com/OpenPublicMedia/nprapi-wordpress/pull/13)
* Removed direct cURL reference, as well as adding proper sanitization and escaping for externally pulled content

= V1.9 =
* Document transition from NPR DS ownership to NPR Member Station developers [PR #9](https://github.com/OpenPublicMedia/nprapi-wordpress/issues/9)
* Set a default category for each query when using the 'post' type [PR #7](https://github.com/OpenPublicMedia/nprapi-wordpress/pull/7)
* Generate WP posts with rich layout including multimedia etc [PR #4](https://github.com/OpenPublicMedia/nprapi-wordpress/pull/4)
* Change the image_guid variable name to match content [issue](https://github.com/OpenPublicMedia/nprapi-wordpress/commit/1dec12ff5774bb6ec3017799980dddb7b8ff556e )
* Resolve an error raised when any non-admin user tries to trash a WP post [PR #3](https://github.com/OpenPublicMedia/nprapi-wordpress/pull/3)
* Styling and formatting and code normalization [PR #1](https://github.com/OpenPublicMedia/nprapi-wordpress/pull/1)

= V1.8 =
* Fixes issue preventing pushing to the API, introduced in V1.7. [PR #60](https://github.com/npr/nprapi-wordpress/pull/60) for [issue #57](https://github.com/npr/nprapi-wordpress/issues/57).
* Fixes issue where images were not properly sideloaded. [PR #60](https://github.com/npr/nprapi-wordpress/pull/60) for [issue #59](https://github.com/npr/nprapi-wordpress/issues/59).
* Fixes invalid GMT offset error when creating new DateTimeZone object in post metabox. [PR #53](https://github.com/npr/nprapi-wordpress/pull/53) for [issue #52](https://github.com/npr/nprapi-wordpress/issues/52).
* When interacting with a site using the plugin in an unconfigured state, users will be prompted to set the NPR Story API Push URL. [PR #56](https://github.com/npr/nprapi-wordpress/pull/56) for [issue #51](https://github.com/npr/nprapi-wordpress/issues/51).
* Miscellaneous code quality improvements.

= V1.7.1 =
* Fixes version numbers in the plugin for the plugin, WordPress "tested up to", and the stable tag.
* This release fixes some WordPress.org metadata. For a list of new features, see the version 1.7 changelog.

= V1.7 =
* Fixes issue preventing pushing to the API, introduced in V1.7. [PR #60](https://github.com/npr/nprapi-wordpress/pull/60) for [issue #57](https://github.com/npr/nprapi-wordpress/issues/57).
* Fixes issue where images were not properly sideloaded. [PR #60](https://github.com/npr/nprapi-wordpress/pull/60) for [issue #59](https://github.com/npr/nprapi-wordpress/issues/59).
* Fixes invalid GMT offset error when creating new DateTimeZone object in post metabox. [PR #53](https://github.com/npr/nprapi-wordpress/pull/53) for [issue #52](https://github.com/npr/nprapi-wordpress/issues/52).
* When interacting with a site using the plugin in an unconfigured state, users will be prompted to set the NPR Story API Push URL. [PR #56](https://github.com/npr/nprapi-wordpress/pull/56) for [issue #51](https://github.com/npr/nprapi-wordpress/issues/51).
* Miscellaneous code quality improvements.

= V1.6 =
* Added meta box to post edit page to explicitly push a story to NPR One
* Added [documentation](https://github.com/nprds/nprapi-wordpress/tree/master/docs)
* If pushing a story to NPR fails, the error message is displayed on the post edit page
* If it succeeds, a success message is displayed
* Rename the plugin to "NPR Story API"
* The plugin now requires certain WordPress capabilities before performing API actions:
  - deleting posts in the API now requires the `delete_others_posts` capability for the user trying to delete the post. In effect, this limits deletion of stories from the NPR Story API to admins and editors.
  - pushing stories to the API requires the `publish_posts` capability, which means that contributors and guests can't push to NPR.
  - pulling posts from the API requires the `edit_posts` capability, meaning that contributors and guests can't pull from NPR. We may want to revisit this in the future, or make the permissions filterable if sites using the plugin want to lock permissions down or open them up.
* A number of settings in the admin now use the number input type instead of text fields
* Added unit tests for much of the PHP code
* Most functions were renamed to use the prefix nprstory_ instead of ds_npr_ or no common prefix. This does not affect
* Added nonces, input sanitization, and input validation for settings.
* `DS_NPR_API` class now uses new-style `__construct()` constructor.
* Removed `/wp-admin/includes/admin.php`, `/wp-load.php`, `/wp-includes/class-wp-error.php` from the cron job function, see [4b3d65a](https://github.com/nprds/nprapi-wordpress/pull/19/commits/4b3d65a19122b0da5215997939db94c7accf3e5e) and [cf2dfa3](https://github.com/nprds/nprapi-wordpress/pull/19/commits/cf2dfa39c1f118d0ca0c836d7967e09baec63bd6) - the cron job works without them.

= V1.5.2 =
* Adding support for enclosures created as metadata by the PowerPress plugin.
* Added NPR One checkbox that makes pushed stories available to the NPR One app (unchecked by default).
* Users were getting a whitescreen when attempting to push posts. The default byline element -- when used with no mapping or co-authors plugin -- was being sent without a tag name, causing a fatal error.)
* General clean up; small doc tweaks; bug fixes.

= V1.5.1 =
* Multiple Bylines for pulled stories -  When an API story has multiple bylines, we will now populate a meta field `npr_multi_byline` with a pipe (|) deliminated list of names.  If the author also has a link for thier byline then there'll be a tilde (~) separating the author's name and the link.
  - An example of data for multiple bylines:

		Ari Shapiro~https://www.npr.org/people/2101154/ari-shapiro|kmoylan@npr.org|Tommy Writer

  - Here there are three authors for this story, Ari Shapiro (and his bio link), then "kmoylan@npr.org" and a third author "Tommy Writer". Single author stories will not be changed. (This fix came from Ryan Tainter at KERA. Thanks, Ryan!)

* Do not re-pull stories that are already in draft status - The get multi process would repleatedly re-pull stories into draft mode if there is already a draft copy. This fix will check for `status=any` when checking to see if a story already exists in the WordPress database.

= V1.5 =
* Reversed the order of images and audio that are being pushed to the NPR API.  We're now sending them in the order the file was created.
* Mapping of media credit and agency - If you have a field that you are storing image credit and agency, you can now send these to the NPR API. In making this change we needed to expose meta fields that begin with an underscore.  So you may see more meta fields visable on the mapping page.
* Multiple bylines are now being pushed to the NPR API if you use the plugin co-author-plus (<https://wordpress.org/plugins/co-authors-plus/>) We insert a byline row for each of the co-authors of a post when we push. You can still use a custom byline field if you want to use that along with the co-authors plugin.
* Retrieved story dates -  Now when you retrieve a story from the NPR API the published date for WordPress will be the date from the <storyDate> field in the NPR API. This will better allow stories to fall into their natural order when publishing from the Get Multi page. It's always possible to edit the date by hand when pulling single stories from the NPR API. Do remember though that you are contractually obligated to show the original date when rendering a story from the NPR API.
* Retrieved story order -  When you retrieve stories from the API, if you do not include a `sort` parameter in your query, we will insure that the order of the stories is in reverse cron for any stories that have the same storyDate in the API. Often some aggregate groups in the API will publish stories with the same storyDate. Shows like Morning Edition do this often, and prior to this fix stories would appear to be published in reverse order from what the API shows.  Now if you do not include `sort` in your query, the stories with the same time will be published in the same order as the API shows them.
* Configurable Cron interval -  When you use the Get Multi page, which fires off of wp-cron, you can now set the interval for the wp-cron to fire. This is on the Get Multi configuration page, and allows you to enter a number of minutes you want as your wp-cron interval. If you enter any thing that is not a number, or a number less than 1, we will resolve that to 60 minutes...because, well, that's a resonable interval, and the wp-cron doesn't understand 'every now and then' as an interval.
* Images from crops - In earlier versions of the plugin we would pull down the image from the URL in `<image src=>`. Alas, a lot of those images were small, with ?s=12 in the URL, or thumbnails. So now we're bringing down any bigger versions that we can find. We'll look for the following elements under <image> tags. In order `<enlargement>`, `<crop type=enlargement>`, `<crop type=standard>`, and then get `<image src=>` if those other elements do not exist. This should help with getting bigger images. If you need a smaller image, just theme the bigger one.
* When pushing a story to the NPR API, we will now be checking to see if images exist in the content, if so, add the query string parameter `origin=body` to the image url so CorePublisher will like it. This is only something the Core Publisher uses, but it will help greatly for stories published in Wordpress and retrieved into Core Publisher.
* Adding meta data as meta fields to each pulled image.  The following meta fields will be attached to the posts for pulled images:

		npr_image_credit =>$api_image->producer
		npr_image_agency =>$api_image->provider
		npr_image_caption =>$api_image->caption

* When pushing a story with Audio, the audio description will be pushed as well, provided there is one.

= V1.4 =
* Filters for Shortcodes - We've now implemented a hook to a filter that will be used to alter any short codes that a plugin may own in a post before pushing that post to the NPR API. The filter (`npr_ds_shortcode_filter`) will fire before we remove shortcodes when we're pushing the post. Any plugin that has shortcodes should alter those shortcodes via this filter to something that's more HTML-friendly, so that other clients can access that information. As an example of what needs to be done in a plugin to take advantage of this new filter please see the branch for the following plugin: <https://github.com/argoproject/navis-documentcloud/tree/kmoylan-filter> What we've done here is write a function `my_documentCloud_filter` that is linked to the filter `npr_ds_shortcode_filter` (all near the bottom of the PHP file).  This function will turn any shortcode created by this plugin into an HTML `<a>` tag to link to what was an embedded document. As with any other filter in WordPress, nothing will happen if you do not have any plugins installed that have implemented the filter. It will be up to any other plugin's maintainer to implement the filter correctly if they wish to take advantage of this functionality.
* Bulk Push - From the post list page for you NPR Push post type you can now select multiple posts and using the bulk operation dropdown on that page, push the selected posts to the NPR API. This should helpful for posts that have been living on a site before the NPR API plugin was installed. Note that this will only push a maximum 20 posts at one time.
* Publish or Draft for Get Multi -  It's now possible for an admin to select Draft or Publish for the posts that come from a query on the get multi page. This way, the return from each query can be reviewed before it's published to a site.
* Run Get Multi on Demand -  An admin can now select a checkbox if they would like the get multi queries to run when the page is saved. This will allow admins to immediately check queries instead of having to wait for the cron to run.

= V1.3 =
* Permissions - If you have created any NPR API Permissions Groups you can select which (if any) group you would like to use as your default group when pushing content to the NPR API.  Learn more about NPR API Permissions Groups here: <https://digitalservices.npr.org/post/npr-api-content-permissions-control>
* Multi-Site - Cron set up and other activation tasks will now happen on every site in a multi-site configuration.
* Cron queries - We won't try to query for any of the configured queries on the Get Multi page if the field isn't filled in with data.
* Content and short codes - Instead of blindly removing all shortcodes from a story's content, we are now trying to let any plugins replace the shortcodes with HTML, and then if there are any leftover shortcodes (we delete those?).
* Byline URL - We are now saving any links to the author of a story's bio page in the meta field `npr_byline_link`. This should allow you to supply a link for any author who has a biography registered with the NPR API.

= V1.2 =
* Enhance error messages when there are connectivity or permissions issues with your NPR API work. We have also included the ability for admins to map custom Meta fields to certain Post fields that are pushed to the NPR API. And finally, we've add the ability to retrieve transcripts for stories that have transcripts associated with them. Along with these changes we've added the the ability to save urls for the .m3u files attached to NPR stories.

= V1.1 =
This version will allow admins to configure their WordPress site to retrieve multiple NPR Story API stories that will be automatically published (via cron) to their site.

* From the **Settings -> NPR API Get Multi** page (wp-admin/options-general.php?page=ds_npr_api_get_multi_settings) an admin can add a number of queries.
* These query entries can contain an API ID for a single story, or an ID for a specific category, program, topic, etc.
* The query can also contain the full query string can be created from the NPR Story API Query Generator: <https://legacy.npr.org/api/queryGenerator.php>
* You can also enter the URL for a story you found on npr.org.
* The entered queries will be executed via the Wordpress cron functionality, hourly.
* Any new stories that are available will be automatically published.  You can find a list of query filters at the npr.org's API documentation page: <https://legacy.npr.org/api/inputReference.php>
* Stories retrieved from the NPR Story API will be created as Posts in WordPress.  Each post will have a number of meta fields associated with the post.  These meta fields will all begin with `npr_` and can be viewed on a post edit screen with Custom Fields option enabled through Screen Options. A story from the API that has a primary image defined will have that image set as the featured image of the Wordpress post.  Any bylines for the NPR Story will be stored in the meta field `npr_byline`. The list of npr_ meta fields is:

		npr_api_link
		npr_byline
		npr_html_link
		npr_last_modified_date
		npr_pub_date
		npr_retrieved_story
		npr_story_content
		npr_story_id

* On the All Posts admin screen we've made a couple of modification/additions to help you manage your NPR Story API content.
  - There is a new Bulk Action available, 'Update NPR Story'.  When one or many NPR Story API Stories are selected and the Update NPR Story action is applied, this plugin will re-query the NPR Story API for those stories, and if the story's content has changed it will update the post in WordPress.
  - There is also a new column added to this page titled "Update Story".  For any Post retrieved from the NPR Story API there will be a link to "Update" the story. Pressing this link will bring the user to the query NPR Story API page with the story ID pre-filled. Pressing "Publish" on this screen will re-query the NPR Story API for the story and update the Post with any changes that may have taken place on the NPR Story API for that story.
* Update and Delete of pushed stories - A story that was written in your Wordpress site and pushed to the NPR Story API will be automatically be updated in the API when your story is updated in Wordpress.  When your Post is moved to the Trash in Wordpress, your story will be deleted from the NPR Story API.  If a Trashed Post is resorted, your story will be made available to the NPR Story API again.

= V1.0 =
As not a lot of users have installed the V1.0 of the NPR Story API Plugin, there are a couple of things to keep in mind.

* On the NPR Story API settings page (wp-admin/options-general.php?page=ds_npr_api) there are 4 fields.
  - API KEY - This is your NPR Story API Key that you can get from NPR.  If you wish to push stories to the NPR Story API you'll need to have your key configured by NPR Support.  Please contact Digital Services with a support request at <https://studio.npr.org>
  - Pull URL - This is the root url for retrieving stories.  For testing purposes, you should configure this to be `https://api-s1.npr.org`. NOTE: this url should not contain a trailing slash.
  - Push URL - Much like the pull url, this url is used to pushing stories to the NPR Story API. Again, for testing purposes, you can utilize NPR's staging server  at `https://api-s1.npr.org`. If you do not wish to push your content, or your NPR Story API has not been authorized, you should leave this field empty and the WordPress plugin will not attempt to push you content to the NPR Story API.
  - Org ID - This is your organization's ID assigned by NPR.  If you don't know your Org ID, please contact NPR Support at: <https://studio.npr.org>
* You can pull stories one at a time from the NPR Story API by using the Get NPR Stories page under admin Posts menu (wp-admin/edit.php?page=get-npr-stories). This can be story ID from the API, or the URL for the story from npr.org. For help in finding possible query options, please use the Query Generator at <https://legacy.npr.org/api/queryGenerator.php> Documentation is at: <https://legacy.npr.org/api/inputReference.php>

== Upgrade Notice ==

= 1.5.2 =
This version adds export functionality for the NPR One mobile app, in addition to assorted bug fixes.