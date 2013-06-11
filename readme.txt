=== Drafts of Post Revisions ===
Contributors: daxitude
Tags: status, post status, workflow, Revision
Requires at least: 3.4
Tested up to: 3.4.2
Stable tag: 0.7.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Create drafts of WordPress posts/pages/CPTs even after they've been published

== Description ==

Create drafts of WordPress posts/pages/CPTs even after they've been published. And when you're ready, merge the changes back into the original published post.

= Features =

* Create multiple drafts of already published posts
* Merge the changes back into the published post when you're ready
* Uses the published posts's original post type, so metas, taxonomies, etc all are available in admin screens and can even be modified in the draft and merged back into the original post
* Perform a post diff similar to WP's default revision.php?action=diff with added ability to compare changes in post meta and taxonomies
* See a notice when the original post has been updated ahead of a draft
* Preview drafts in the post/page/CPT's natural template
* Since drafts all carry the same custom post status, they are organized in the admin's edit.php with their own status filter (see screenshot-4)

This plugin requires javascript.


== Installation ==

See [Installing Plugins](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins).

1. Download the zip from here or from [Github](http://github.com/daxitude/wp-draft-revisions) and drop it into your site's wp-content/plugins directory, or go to your site > Plugins > Add New > search for 'Drafts of Post Revisions'
1. Navigate to your site's Admin > Plugins section (wp-admin/plugins.php) and activate the plugin
1. Go to Settings > Drafts of Revisions to set up the permitted post types
1. Go to any post edit screen and look for the Drafts of Revisions postbox in the upper right above the Publish postbox. Click on "Save a Draft" to create a new draft of a published post

== How Does it Work? ==

Go to a post's edit screen and click on the "Save a Draft" button in the Drafts of Revisions postbox (make sure you've enabled the post type first). The post's core data, taxonomies, and meta data are all copied into a new post - the draft - as a child of the original post. The draft has a custom post status; it will never show up in any queries for posts. You can create as many drafts as you like.

You can edit a draft's post content, taxonomies, and meta data as you like and save progress with the native WP "Save Draft" button. You can also preview the draft and compare changes against the parent at any time (even comparing changes in taxonomies and meta data).

When you're ready to update the parent post, click the Publish button from the draft's edit page. All post data, taxonomies, and meta data are merged back into the parent post and the draft post is deleted.

== Screenshots ==

1. Editing a draft
1. Viewing the original page
1. Comparing changes
1. Draft revisions organized in edit.php under their own custom post status

== Changelog ==

= 0.7.3 =
* bugfix - make sure compare changes link works when WP is installed in a sub-dir
* bugfix - call-time pass-by-reference removed in php 5.4
* made one admin notice slightly less ambiguous

= 0.7.2 =
* bugfix - make sure serialized post metas are unserialized/serialized properly

= 0.7.1 =
* bugfix - make sure post_name is unique

= 0.7 =
* initial release
