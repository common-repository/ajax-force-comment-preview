=== Ajax Force Comment Preview ===
Tags: ajax, preview, comment, comments, anti-spam, spam
Contributors: Thaya Kareeson
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=madeinthayaland@gmail.com&currency_code=USD&amount=&return=&item_name=Donate+a+cup+of+coffee+or+two+for+AJAX+Force+Comment+Preview+WordPress+Plugin
Requires at least: 2.5
Tested up to: 2.6.2
Stable Tag: 1.5

Visitors to your site are forced to preview their comment prior to submitting it.

== Description ==

The plugin works like TextPattern's built-in "force comment preview" feature by
forcing your commenters to preview their comments prior to submission.  In addition
this plugin is AJAX enabled so the user does not have to reload the page to preview
his/her comment.  Here are the key benefits of having this plugin enabled.

1. Comments quality will increase as users will be forced to preview his/her
   comment before submitting it.  Previewed comments are sent through WordPress'
   various filters so that the user can see exactly how his/her comment will
   appear after it is submitted.

2. Spambots will not be able post comments unless it actually tries to "preview"
   the comment.  When a preview is requested, a nonce key is generated and
   returned along with the preview.  This nonce key is then required to be sent
   back to the server during comment submission.  So in order to submit a
   comment, the spambot would have to use javascript to request a comment
   preview prior to submitting the comment.  Most spambots do not care to preview
   so this offers some level of spam protection.

== Installation ==

1. Upload the plugin to your plugins folder: `wp-content/plugins/`
2. Activate the 'Ajax Force Comment Preview' plugin from the Plugins admin panel.
3. Go to the Options -> Ajax Force Comment Preview admin panel to configure the look
   of the preview, verification salt, preview timeout, and other optionts.

== Frequently Asked Questions ==

= How do I change the look of the preview? =

Go to the Options -> Ajax Force Comment Preview admin panel.  From there you'll be
able to specify the markup used to display the comment being previewed.  The
markup you enter will depend on what theme your site is using.  If you're using
Kubrick (the default theme for WordPress), the settings that come installed
with the plugin will work fine.  For other themes, I suggest the following.

1. Go to the permalink page for a post on your site that has a few comments.
2. In your web browser, view the Page Source of that page.  You can usually do
   this by finding that option in your browsers Edit or View menu or in the menu
   that pops up when you right click on the page.
3. Find the section of code that corresponds to one of the comments.  Copy it
   into your clipboard.
4. Paste that code into the big text box in the Options -> Ajax Force Comment Preview
   admin panel.
5. Replace the text specific to that comment (author name, time, comment text,
   ...) with the plugin's special tags (`%author%`, `%date%`, `%content%`, ...).
6. Most themes' code has all the comments inside one big `<ol>`, `<ul>`, or `<div>`
   tag.  You'll probably need to put your preview markup inside that
   "parent" tag too.  Make sure it has the same class(es) as the tag in your
   theme's code.

= Does this plugin conflict with other comment plugins? =

In most cases, this plugin will not conflict with other comment plugins.  The only
plugins that will conflict are plugins that submit comments without going through
AJAX Force Comment Preview's verification.  To get around this issue you will have
to modify the thrid party plugin by adding the following lines before its call to
"wp_new_comment( $commentdata );".

  // Ajax Forced Comment Preview Compatibility Code - START
  $_POST['afcp-nonce'] = Ajax_Force_Comment_Preview::send(true);
  // Ajax Forced Comment Preview Compatibility Code - END
  
  // Third party's plugin call to create a new comment
  wp_new_comment( $commentdata );

Keep in mind that by doing this you are bypassing previewing the comment if comments
are submitted through the third party plugin.

== Changelog ==

1.5
- Added mode to send() that returns the nonce value used for bypassing verification.
1.4
- Added support for the new WordPress 2.6 movable wp-content and wp-config.php feature.
1.3
- Fixed bug that causes Windows EOL characters (^M) in comments to invalidate nonce.
1.2
- Fixed bug that causes the "Submit" button to be enabled too early.
1.1
- Disable feature for Administrators
- Re enforce preview whenever comment text changes
- Merge "Preview" & "Submit" button
1.0
- Initial release

== Screenshots ==

1. Before preview
2. After preview
3. Attempting to submit without previewing
