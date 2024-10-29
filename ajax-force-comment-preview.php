<?php
/*
Plugin Name: AJAX Force Comment Preview
Plugin URI: http://omninoggin.com/projects/wordpress-plugins/ajax-force-comment-preview-wordpress-plugin/
Description: Force commenters to preview their comment prior to submitting.  Inspired by Michael D Adams's AJAX Comment Preview and TextPattern's built-in Force Comment Preview.
Version: 1.5
Author: Thaya Kareeson
Author URI: http://omninoggin.com/
*/

/*
Copyright 2008 Thaya Kareeson (email : thaya.kareeson@gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class Ajax_Force_Comment_Preview {
  function version() { return '2.0'; }

  // function to remove all whitespace from comment (used for nonce generation and verification
  function despace($str)
  {
    $str = str_replace(array("\n", "\r", "\t", " ", "\o", "\xOB"), '', $str);
    return $str;
  }

  function activate() {
    global $wpdb;
    // set default options
    add_option( 'ajax_force_comment_preview', array(
      'salt_value' => md5(rand(0,100000)),
      'timeout_value' => 30,
      'template' => "<ul class='commentlist'>\n\t<li class='alt'>\n\t<cite>%author%</cite> Says:<br />\n\t<small class='commentmetadata'><a href='#'>%date%</a></small>\n\t%content%\n\t</li>\n</ul>",
      'date_format' => 'F jS, Y \a\t g:i a',
      'empty_string' => 'You must preview your comment before submitting.',
      'button_value' => 'Preview'
    ));
    // create database table
    $create_table_statement = ''
      . 'create table if not exists '.$wpdb->prefix.'ajax_force_comment_preview ('
      . 'session_id varchar(255) not null primary key,'
      . 'timestamp int unsigned null'
      . ')';
    $wpdb->query($create_table_statement);
  }

  function deactivate() {
    global $wpdb;
    // drop database table
    $drop_table_statement = 'drop table '.$wpdb->prefix.'ajax_force_comment_preview';
    $wpdb->query($drop_table_statement);
  }

  function wp_print_scripts() {
    global $userdata;
    get_currentuserinfo();
    if ( $userdata->user_level < 9 ) {
      if ( !is_single() && !is_page() || !comments_open() )
        return;
      extract(get_option( 'ajax_force_comment_preview' ));
      wp_enqueue_script( 'ajax_force_comment_preview', Ajax_Force_Comment_Preview::htmldir() . '/ajax-force-comment-preview.js', array('sack'), Ajax_Force_Comment_Preview::version() . mt_rand() );
      wp_localize_script( 'ajax_force_comment_preview', 'AjaxForceCommentPreviewVars', array(
        'emptyString' => $empty_string,
        'url' => Ajax_Force_Comment_Preview::htmldir() . '/ajax-force-comment-preview.php'
      ) );
    }
  }

  function comment_form() {
    global $userdata;
    get_currentuserinfo();
    if ( $userdata->user_level < 9 ) {
      $preview_vars = get_option( 'ajax_force_comment_preview' );
      echo '<input name="afcp-preview" type="button" id="afcp-preview" tabindex="6" value="' . attribute_escape( $preview_vars['button_value'] ) . '" />';
      echo '<div id="ajax-force-comment-preview"></div>';
      echo '<noscript><p><strong>Currently you have JavaScript disabled. In order to post comments, please make sure JavaScript is enabled, and reload the page.</strong></p></noscript>';
    }
  }

  function send($nonce_only = false) {
    global $user_ID, $user_url, $user_identity, $user_email, $wpdb;
    $preview_vars = get_option( 'ajax_force_comment_preview' );
    $salt_value = $preview_vars['salt_value'];
    $timeout_value = $preview_vars['timeout_value'];
    $author  = trim($_POST['author']);
    if (!$author) $author = 'Anonymous';
    $url  = trim($_POST['url']);
    $text  = trim($_POST['text']);
    $despaced_text = Ajax_Force_Comment_Preview::despace($text);
    $email = trim($_POST['email']);

    get_currentuserinfo();
    if ( $user_ID ) :
      $author  = addslashes($user_identity);
      $url  = addslashes($user_url);
      $email  = addslashes($user_email);
    endif;

    $text = apply_filters('pre_comment_content', $text);
    $text = apply_filters('post_comment_text', $text); // Deprecated
    $text = apply_filters('comment_content_presave', $text); // Deprecated
    $text = stripslashes($text);
    $text = apply_filters('get_comment_text', $text);
    $text = apply_filters('comment_text', $text);

    $author = apply_filters('pre_comment_author_name', $author);
    $author = stripslashes($author);
    $author = apply_filters('get_comment_author', $author);

    $email = apply_filters('pre_comment_author_email', $email);
    $email = stripslashes($email);
    $email = apply_filters('get_comment_author_email', $email);

    if ( $url && 'http://' !== $url ) :
      $url = apply_filters('pre_comment_author_url', $url);
      $url = stripslashes($url);
      $url = apply_filters('get_comment_url', $url);
      $author = '<a href="' . $url . '" rel="external">' . $author . '</a>';
      $author = apply_filters('get_comment_author_link', $author);
      $author = apply_filters('comment_author_link', $author);
    endif;
    $preview_vars = get_option( 'ajax_force_comment_preview' );
    $preview_vars['template'] = str_replace(
      array('%author%', '%date%', '%content%', '%email%'),
      array($author, date_i18n($preview_vars['date_format'], time() + get_settings('gmt_offset') * 3600 - date('Z')), $text, $email),
      $preview_vars['template']
    );

    if ( false !== strpos($preview_vars['template'], '%email_hash%') )
      $preview_vars['template'] = str_replace('%email_hash%', md5($email), $preview_vars['template']);

    // store wpdb
    $oldwpdb = $wpdb;
    // remove flush after finish debugging
    // $wpdb->flush();
    $session_id = session_id();
    $db_data = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."ajax_force_comment_preview WHERE session_id='".$session_id."'");
    $now = time();
    if ( $db_data ) {
      // if we found session data
      $timestamp = $db_data->timestamp;
      if ( $now - $timestamp > (60 * $timeout_value) ) {
        // if the last comment preview was more than 30 minutes, update timestamp.
        $wpdb->query("UPDATE ".$wpdb->prefix."ajax_force_comment_preview SET timestamp=$now WHERE session_id='".$session_id."'");
      }
    }
    else {
      // if we did not find session_id then record this session
      $wpdb->query("INSERT INTO ".$wpdb->prefix."ajax_force_comment_preview SET session_id='".$session_id."', timestamp=$now");
      // since we're here, might as well clean up sessions that are older than 24 hours
      $wpdb->query("DELETE FROM ".$wpdb->prefix."ajax_force_comment_preview WHERE $now - timestamp > 60 * 60 * 24");
    }
    $nonce = md5($salt_value . $despaced_text . $session_id);
    $nonce_html = '<input name="afcp-nonce" type="hidden" id="afcp-nonce" value="' . $nonce . '"/>';
    // restore wpdb
    $wpdb = $oldwpdb;

    if($nonce_only)
      return $nonce;
    else
      return $preview_vars['template'] . $nonce_html;
  }

  //Used to verify nonce before approving comment
  function verify($comment_data) {
    global $userdata;
    get_currentuserinfo();
    if ( $userdata->user_level < 9 ) {
      global $wpdb;
      $preview_vars = get_option( 'ajax_force_comment_preview' );
      $salt_value = $preview_vars['salt_value'];
      $timeout_value = $preview_vars['timeout_value'];
      $nonce = $_POST['afcp-nonce'];
      $session_id = session_id();
      // store wpdb
      $oldwpdb = $wpdb;
      // remove flush after finish debugging
      // $wpdb->flush();
      $db_data = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."ajax_force_comment_preview WHERE session_id='".$session_id."'");
      if ( $db_data ) {
        // found session data
        $timestamp = $db_data->timestamp;
        $db_session_id = $db_data->session_id;
        $now = time();
        if ( $now - $timestamp > (60 * $timeout_value) ) {
          // if it has been less than 30 minutes since comment preview
          wp_die( __('Your last comment preview was over 30 minutes ago.  Please re-preview your comment before submitting.') );
        }
        if ( $nonce == md5($salt_value . Ajax_Force_Comment_Preview::despace($comment_data['comment_content']) . $db_session_id) ) {
          // if nonce matches, then approve comment
          $wpdb = $oldwpdb; // restore wpdb before continuing
          return $comment_data;
        }
      }
      wp_die( __('Unable to approve comment.  Please make sure you have Javascript+Cookies enabled and have previewed your comment before submitting.') );
      return false;
    }
    else {
      return $comment_data;
    }
  }

  //Only works for files Ajax Force Comment Preview files
  function htmldir() {
    static $htmldir = false;
    if ( $htmldir )
      return $htmldir;

    $plugins = get_option( 'active_plugins' );

    $realfile = realpath( __FILE__ );

    $ajax_force_comment_preview = false;
    foreach ( $plugins as $plugin ) {
      if ( realpath( ABSPATH . PLUGINDIR . '/' . $plugin ) == $realfile ) {
        $ajax_force_comment_preview = $plugin;
        break;
      }
    }

    $htmldir = get_option( 'siteurl' ) . '/' . dirname( PLUGINDIR . '/' . $ajax_force_comment_preview );
    return $htmldir;
  }

  function admin_menu() {
    add_options_page( 'AJAX Force Comment Preview', 'AJAX Force Comment Preview', 'manage_options', 'afcp-admin', array('Ajax_Force_Comment_Preview', 'admin_page') );
  }

  function admin_page() {
    if ( isset($_POST['ajax_force_comment_preview_options_submit']) ) {
      check_admin_referer( 'ajax_force_comment_preview' );
      $ajax_force_comment_preview_options = stripslashes_deep($_POST['afcp']);
      if ( !$ajax_force_comment_preview_options['button_value'] )
        $ajax_force_comment_preview_options['button_value'] = 'Preview';
      $ajax_force_comment_preview_options['ver'] = time();
      update_option( 'ajax_force_comment_preview', $ajax_force_comment_preview_options );
      echo '<div class="updated fade"><p>Ajax Force Comment Preview options updated.</p></div>';
    }
    extract(get_option( 'ajax_force_comment_preview' )); ?>
<style type="text/css">
.afcp-focusable:focus {background-color: #ffc}
dl { margin-left: 3em }
</style>
<div class="wrap">
<h2>Ajax Force Comment Preview Options</h2>
<form method="post">
<fieldset>
  <div>
    <p>
      <label for="salt-value">This is the salt used to authenticate comments.  It is randomly generated during the first time of plugin activation.  You may change this to what ever you like.<br />
      <input name="afcp[salt_value]" id="salt-value" type="text" class="afcp-focusable" value="<?php echo attribute_escape( $salt_value ); ?>" /></label>
    </p>

    <p>
      <label for="timeout-value">This sets the preview timeout in minutes.  Users will have this number of minutes to submit the comment after clicking preview button.<br />
      <input name="afcp[timeout_value]" id="timeout-value" type="text" class="afcp-focusable" value="<?php echo attribute_escape( $timeout_value ); ?>" /></label>
    </p>

    <p>Enter the markup from your theme's comment template here.  The following special tags are available.</p>
    <dl>
      <dt>%author%</dt>
      <dd>The name of the comment author linked to the comment author's url.</dd>
      <dt>%date%</dt>
      <dd>The date formatted as <a><label for="date-format">below</label></a>.</dd>
      <dt>%content%</dt>
      <dd>The text of the comment.</dd>
      <dt>%email%</dt>
      <dd>The email of the comment author.</dd>
      <dt>%email_hash%</dt>
      <dd>The MD5 hash of the comment author's email address.  Useful for gravatars.</dd>
    </dl>
    <textarea name="afcp[template]" class="afcp-focusable widefat" rows="10"><?php echo attribute_escape( $template ); ?></textarea>

    <p>
      <label for="date-format"><a href="http://codex.wordpress.org/Formatting_Date_and_Time">Date format</a> of the date to be displayed in the preview.<br />
      <input name="afcp[date_format]" id="date-format" class="afcp-focusable" type="text" value="<?php echo attribute_escape( $date_format ); ?>" /></label>
    </p>

    <p>
      <label for="button-value">Text to appear on the Preview Button.<br />
      <input name="afcp[button_value]" id="button-value" class="afcp-focusable" type="text" value="<?php echo attribute_escape( $button_value ); ?>" /></label>
    </p>

    <p>
      <label for="empty-string">This text will appear in the preview area before the user previews the comment.  Leave blank to make the preview area initially invisible.<br />
      <input name="afcp[empty_string]" id="empty-string" type="text" class="afcp-focusable widefat" value="<?php echo attribute_escape( $empty_string ); ?>" /></label>
    </p>
  </div>
</fieldset>
<?php if ( function_exists( 'wp_nonce_field' ) ) wp_nonce_field( 'ajax_force_comment_preview' ); ?>
<p class="submit"><input type="submit" name="ajax_force_comment_preview_options_submit" value="Update Options &#187;" /></p>
</form>
</div>
<?php  }
}

if ( function_exists('add_action') ) {
  add_action('admin_menu', array('Ajax_Force_Comment_Preview', 'admin_menu') );
  add_action('wp_print_scripts', array('Ajax_Force_Comment_Preview', 'wp_print_scripts') );
  add_action('comment_form', array('Ajax_Force_Comment_Preview', 'comment_form') );
  add_action('preprocess_comment',  array('Ajax_Force_Comment_Preview', 'verify') );
}

if ( function_exists('register_activation_hook') ) {
  register_activation_hook(__FILE__, array('Ajax_Force_Comment_Preview', 'activate'));
}

if ( function_exists('register_deactivation_hook') ) {
  register_deactivation_hook(__FILE__, array('Ajax_Force_Comment_Preview', 'deactivate'));
}

if ( !defined( 'ABSPATH' ) && isset($_POST['text']) && $_POST['text'] ) {
  $root = dirname(dirname(dirname(dirname(__FILE__))));
  if (file_exists($root.DIRECTORY_SEPARATOR.'wp-load.php')) {
    // WP 2.6
    require_once($root.DIRECTORY_SEPARATOR.'wp-load.php');
  } else {
    // Before 2.6
    require_once($root.DIRECTORY_SEPARATOR.'wp-config.php');
  }

  require_once( $root.DIRECTORY_SEPARATOR.'wp-includes'.DIRECTORY_SEPARATOR.'wp-db.php' );

  if ( defined( 'ABSPATH' ) ) {
    echo Ajax_Force_Comment_Preview::send();
    exit;
  }

  die( 'Cannot load WordPress.' );
}
?>
