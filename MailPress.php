<?php
/*
Plugin Name: MailPress
Plugin URI: http://www.nogent94.com/?page_id=70
Description: This is just a plugin, to manage mails, subscribers, and much more ... with style !
Author: Andre Renaut
Version: 1.1.6
Author URI: http://www.nogent94.com/?page_id=70
*/

class MailPress
{
	function MailPress() {
		global $wpdb, $table_prefix, $mp_general;
// for plugin
		add_action( 'activate_' . MP_FOLDER . '/MailPress.php',	array(&$this,'install'));
// for widget
		add_action('plugins_loaded', array(&$this,'widget_init'));
// for mysql
		$wpdb->mp_users	= $table_prefix . 'MailPress_users';
		$wpdb->mp_stats	= $table_prefix . 'MailPress_stats';
		$wpdb->mp_mails	= $table_prefix . 'MailPress_mails';
// for gettext
		load_plugin_textdomain('MailPress', MP_PATH . 'mp-includes/languages');
// for post
		if (isset($mp_general['new_post']))	add_action('publish_post',	array(&$this,'send_post'), 8, 1);

		if (isset($mp_general['daily']))   if ( date('Ymd') 				> get_option('MailPress_daily')  ) 				add_action('init',		array(&$this,'send_daily'));
		if (isset($mp_general['weekly']))  if ( MailPress::get_yearweekofday(date('Y-m-d')) 	> get_option('MailPress_weekly') ) 	add_action('init',		array(&$this,'send_weekly'));
		if (isset($mp_general['monthly'])) if ( date('Ym') 				> get_option('MailPress_monthly') ) 			add_action('init',		array(&$this,'send_monthly'));

		add_action( 'delete_post', array(&$this,'delete_stats_c'));

// for urls
		if (isset($_POST['general']['menu']) || isset($mp_general['menu']))
		{
			define ('MailPress_users',	'admin.php?page=' 	. MP_FOLDER . '/mp-admin/users.php');
			define ('MailPress_user',	'admin.php?page=' 	. MP_FOLDER . '/mp-admin/users.php&file=user0');
			define ('MailPress_mails',	'admin.php?page=' 	. MP_FOLDER . '/mp-admin/mails.php');
			define ('MailPress_mail',	'admin.php?page=' 	. MP_FOLDER . '/mp-admin/mails.php&file=mail');
			define ('MailPress_write',	'admin.php?page=' 	. MP_FOLDER . '/mp-admin/mail-new.php');
			define ('MailPress_design',	'admin.php?page=' 	. MP_FOLDER . '/mp-admin/themes.php');
		}
		else					
		{
			define ('MailPress_users',	'edit.php?page=' 		. MP_FOLDER . '/mp-admin/users.php');
			define ('MailPress_user',	'edit.php?page=' 		. MP_FOLDER . '/mp-admin/users.php&file=user0');
			define ('MailPress_mails',	'edit.php?page=' 		. MP_FOLDER . '/mp-admin/mails.php');
			define ('MailPress_mail',	'edit.php?page=' 		. MP_FOLDER . '/mp-admin/mails.php&file=mail');
			define ('MailPress_write',	'post-new.php?page=' 	. MP_FOLDER . '/mp-admin/mail-new.php');
			define ('MailPress_design',	'themes.php?page=' 	. MP_FOLDER . '/mp-admin/themes.php');
		}
	}

////	install plugin	////

	function install() {
		include ('../' . MP_PATH . 'mp-admin/includes/install.php');
	}

	function tableExists($table) {
		global $wpdb;
		return strcasecmp($wpdb->get_var("show tables like '$table'"), $table) == 0;
	}

////	widget	////

	function widget_init() {
		if ( !function_exists('register_sidebar_widget') || !function_exists('register_widget_control') ) return;	
		function MailPress_widget() {	MailPress::form(); }	
		register_sidebar_widget('MailPress', 'MailPress_widget');
	}

////	email	////

	public static function is_email($email)
	{
		if(!eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$", $email)) return false;
		return true;
	}

////	user	////

	public static function get_wp_user_id() {
		global $user_ID;
		if ( is_numeric($user_ID) ) return $user_ID;
		return 0;
	}

	public static function get_wp_user_email() {
		$email = '';
		switch (true)
		{
			case (isset($_POST['email'])) :
				$email = $_POST['email'];
			break;
			default :
				$u = MailPress::get_wp_user_id();
				if ($u)
				{
					$user = get_userdata($u);
					$email = $user->user_email;
				}
				else
				{
					$email  = $_COOKIE['comment_author_email_' . COOKIEHASH];
				}
			break;
		}
		return $email;
	}

////	subscription form	////

	public static function form() {
		MP_User::form();
	}

////	stats functions 	////

	public static function update_stats($type,$lib,$count) {
		global $wpdb;
		$now	  = date('Y-m-d');
		$query = "UPDATE $wpdb->mp_stats SET scount=scount+$count WHERE sdate = '$now' AND stype = '$type' AND slib = '$lib';";
		$results = $wpdb->query( $query );
		if (!$results)
		{
			$query = "INSERT INTO $wpdb->mp_stats (sdate, stype, slib, scount) VALUES ('$now','$type','$lib', $count);";
			$results = $wpdb->query( $query );
		}
	}

	function delete_stats_c($postid)
	{
		global $wpdb;
		$query = "DELETE FROM $wpdb->mp_stats WHERE stype = 'c' AND slib = '$postid';";
		$results = $wpdb->query( $query );
	}

////	send subscription mail functions 	////

	function send_confirmation_subscription($email,$key) {
		global $mp_general;

		$url 		= get_bloginfo('siteurl');

		$args->Template 		= 'new_subscriber';
		$args->id			= MP_Mail::get_id();

		$args->toemail 		= $email;
		$args->toname		= $email;
		$args->subscribe	 	= MP_User::get_subscribe_url($key);
		$args->viewhtml	 	= MP_User::get_view_url($key,$args->id);

		$args->subject		= sprintf( __('[%1$s] Waiting for %2$s','MailPress'), get_bloginfo('name'), $email );

		$message  = sprintf( __('Please, confirm your subscription to %1$s emails by clicking the following link :','MailPress'), get_bloginfo('name') );
		$message .= "\n\n";
		$message .= $args->subscribe;
		$message .= "\n\n";
		$message .= __('If you do not want to receive more emails, ignore this one !','MailPress');
		$message .= "\n\n";
		$args->plaintext   	= $message;

		$message  = sprintf( __('Please, confirm your subscription to %1$s emails by clicking the following link :','MailPress'), "<a href='$args->subscribe'>" . get_bloginfo('name') . "</a>" );
		$message .= '<br/><br/>';
		$message .= "<a href='$url'>" . __('Confirm','MailPress') . "</a>";
		$message .= '<br/><br/>';
		$message .= __('If you do not want to receive more emails, ignore this one !','MailPress');
		$message .= '<br/><br/>';
		$args->html    		= $message;

		if (MailPress::mail($args)) return true;

		MP_Mail::delete($args->id);
		return false;
	}

	function resend_confirmation_subscription($email) {
		global $wpdb;
		$key = $wpdb->get_var("SELECT confkey FROM $wpdb->mp_users WHERE email = '$email'");
		return MailPress::send_confirmation_subscription($email,$key);
	}

	function send_succesfull_subscription($email,$key) {
		global $mp_general;

		$url 		= get_bloginfo('siteurl');

		$args->Template 		= 'confirmed';
		$args->id			= MP_Mail::get_id();

		$args->toemail 		= $email;
		$args->toname		= $email;
		$args->unsubscribe 	= MP_User::get_unsubscribe_url($key);
		$args->viewhtml	 	= MP_User::get_view_url($key,$args->id);

		$args->subject		= sprintf( __('[%1$s] Successful subscription for %2$s','MailPress'), get_bloginfo('name'), $email );

		$message  = sprintf(__('We confirm your subscription to %1$s emails','MailPress'), get_bloginfo('name') );
		$message .= "\n\n";
		$message .= __('Congratulations !','MailPress');
		$message .= "\n\n";
		$args->plaintext   	= $message;

		$message  = sprintf(__('We confirm your subscription to %1$s emails','MailPress'), "<a href='$url'>" . get_bloginfo('name') . "</a>" );
		$message .= '<br/><br/>';
		$message .= __('Congratulations !','MailPress');
		$message .= '<br/><br/>';
		$args->html    		= $message;

		if (MailPress::mail($args)) return true;

		MP_Mail::delete($args->id);
		return false;
	}

////	send post mail functions 	////

	function send_post($id) {

		if (get_post_meta($id,'_MailPress_published',true)) return true;
		$rc = true;

		$args->Template 	= 'single';
		$args->id 		= MP_Mail::get_id();

		global $wpdb;
		$query = "SELECT * FROM $wpdb->mp_users WHERE status='active' ORDER by email;";
		$args->replacements = MP_User::get_recipients($query,$args->id);

		if (array() != $args->replacements)
		{
			$args->toemail 	 = '{{toemail}}'; 
			$args->toname	 = '{{toemail}}'; 
			$args->unsubscribe = '{{unsubscribe}}';
			$args->viewhtml	 = '{{viewhtml}}';

			$args->subject	= sprintf( __('[%1$s] New post (%2$s)','MailPress'), get_bloginfo('name'),  $id );

			$args->p->id   	= $id;

			query_posts("p=$id");

			if (MailPress::mail($args)) 	return add_post_meta($id,'_MailPress_published','yes',true);
			$rc = false;
		}

		MP_Mail::delete($args->id);
		return $rc;
	}

	function send_daily() {

		$d = date('Ymd',mktime(0,0,0,date('m'),date('d') - 1, date('Y')));
		query_posts('m=' . $d);
		$nop = true;
		while (have_posts()) { $nop = false; break; }
		if ($nop) return update_option('MailPress_daily',date('Ymd'));
		$rc = true;

		$args->Template 	= 'daily';
		$args->id 		= MP_Mail::get_id();

		global $wpdb;
		$query = "SELECT * FROM $wpdb->mp_users WHERE status='active' ORDER by email;";
		$args->replacements = MP_User::get_recipients($query,$args->id);

		if (array() != $args->replacements)
		{
			$args->toemail 	 = '{{toemail}}'; 
			$args->toname	 = '{{toemail}}'; 
			$args->unsubscribe = '{{unsubscribe}}';
			$args->viewhtml	 = '{{viewhtml}}';

			$args->subject	= sprintf( __('[%1$s] Posts of Yesterday (%2$s)','MailPress'), get_bloginfo('name'),  $d );

			$args->d = $d;

			query_posts('m=' . $d);

			if (MailPress::mail($args)) 	return update_option('MailPress_daily',date('Ymd'));
			$rc = false;
		}

		MP_Mail::delete($args->id);
		return $rc;
	}

	function get_yearweekofday($date)
	{
		global $wpdb;

		$w = (int) $wpdb->get_var("SELECT WEEK('" . $date . "',1)");
		if (10 > $w) $w = '0' . $w;
		return date('Y') . $w;
	}

	function send_weekly() {
		
		$w = MailPress::get_yearweekofday(date('Y-m-d',mktime(10,0,0,date('m'),date('d') - 7, date('Y'))));
		query_posts('w=' . substr($w,4,2) . '&year=' . substr($w,0,4));
		$nop = true;
		while (have_posts()) { $nop = false; break; }
		if ($nop) return update_option('MailPress_weekly', MailPress::get_yearweekofday(date('Y-m-d')));
		$rc = true;

		$args->Template 	= 'weekly';	
		$args->id 		= MP_Mail::get_id();

		global $wpdb;
		$query = "SELECT * FROM $wpdb->mp_users WHERE status='active' ORDER by email;";
		$args->replacements = MP_User::get_recipients($query,$args->id);

		if (array() != $args->replacements)
		{
			$args->toemail 	 = '{{toemail}}'; 
			$args->toname	 = '{{toemail}}'; 
			$args->unsubscribe = '{{unsubscribe}}';
			$args->viewhtml	 = '{{viewhtml}}';

			$args->subject	= sprintf( __('[%1$s] Posts of last week (%2$s)','MailPress'), get_bloginfo('name'),  $w );

			$args->w = $w;

			query_posts('w=' . substr($w,4,2) . '&year=' . substr($w,0,4));

			if (MailPress::mail($args)) 	return update_option('MailPress_weekly', MailPress::get_yearweekofday(date('Y-m-d')));
			$rc = false;
		}

		MP_Mail::delete($args->id);
		return $rc;
	}

	function send_monthly() {
		$y = date('Y'); $m = date('m') - 1; if (0 == $m) { $m = 12; $y--;} if (10 > $m) $m = '0' . $m;
		query_posts('m=' . $y . $m);
		$nop = true;
		while (have_posts()) { $nop = false; break; }
		if ($nop) return update_option('MailPress_monthly',date('Ym'));
		$rc = true;

		$args->Template 	= 'monthly';
		$args->id 		= MP_Mail::get_id();

		global $wpdb;
		$query = "SELECT * FROM $wpdb->mp_users WHERE status='active' ORDER by email;";
		$args->replacements = MP_User::get_recipients($query,$args->id);

		if (array() != $args->replacements)
		{
			$args->toemail 	 = '{{toemail}}'; 
			$args->toname	 = '{{toemail}}'; 
			$args->unsubscribe = '{{unsubscribe}}';
			$args->viewhtml	 = '{{viewhtml}}';

			$args->subject	= sprintf( __('[%1$s] Posts of last month (%2$s)','MailPress'), get_bloginfo('name'),  $y . $m );

			$args->m = $m;
			$args->y = $y;

			query_posts('m=' . $y . $m);

			if (MailPress::mail($args)) 	return update_option('MailPress_monthly',date('Ym'));
			$rc = false;
		}

		MP_Mail::delete($args->id);
		return $rc;
	}

////	send comment mail functions 	////

	function approve_comment($id) {
		global $wpdb, $comment;

		$comment 		= $wpdb->get_row("SELECT * FROM $wpdb->comments WHERE comment_ID = $id LIMIT 1");
		if ('1' != $comment->comment_approved) return true;
		$rc = true;

		$args->Template 	= 'comments';
		$args->id 		= MP_Mail::get_id();

		$query = "SELECT c.email, c.confkey from $wpdb->comments a,  $wpdb->postmeta b, $wpdb->mp_users c WHERE a.comment_ID = $id AND a.comment_post_ID  = b.post_id AND b.meta_value = c.id AND b.meta_key = '_MailPress_subscribe_to_comments_' AND a.comment_author_email <> c.email" ;
		$args->replacements = MP_User::get_recipients($query,$args->id);

		if (array() != $args->replacements)
		{
			$args->toemail 	 = '{{toemail}}'; 
			$args->toname	 = '{{toemail}}'; 
			$args->unsubscribe = '{{unsubscribe}}';
			$args->viewhtml	 = '{{viewhtml}}';

			$args->subject	= sprintf( __('[%1$s] New Comment (%2$s)','MailPress'), get_bloginfo('name'),  $id);

			$args->content	= apply_filters('comment_text', get_comment_text() );

			$args->p->id	= $postid;
			$args->c->id   	= $id;

			if (MailPress::mail($args)) 	return true;
			$rc = false;
		}

		MP_Mail::delete($args->id);
		return $rc;
	}

// // // // // // // // // // // // // 				THE MAIL

	function mail($args)
	{

		$x = new MP_Mail();

		return $x->send($args);
	}
}

$mp_general = get_option('MailPress_general');

define ('MP_FOLDER', 	basename(dirname(__FILE__)));
define ('MP_PATH', 	'wp-content/plugins/' . MP_FOLDER . '/' );
define ('MP_TMP', 	dirname(__FILE__));

// for swift
require MP_TMP . "/mp-includes/class/swift/Swift.php";
require MP_TMP . "/mp-includes/class/swift/Swift/Connection/SMTP.php";

//  classes and misc

if (!class_exists('MP_Mail')) 	include (MP_TMP . '/mp-includes/class/MP_Mail.class.php');
if (!class_exists('MP_User')) 	include (MP_TMP . '/mp-includes/class/MP_User.class.php');
if (!class_exists('MP_Themes')) 	include (MP_TMP . '/mp-includes/class/MP_Themes.class.php');
if (!class_exists('MP_Log')) 		include (MP_TMP . '/mp-includes/class/MP_Log.class.php');
if (!class_exists('MP_Admin')) 	include (MP_TMP . '/mp-includes/class/MP_Admin.class.php');

// pluggable functions

if (isset($mp_general['wp_mail'])) 	include (MP_TMP . '/mp-includes/wp-pluggable.php');

$MailPress = new MailPress();
?>