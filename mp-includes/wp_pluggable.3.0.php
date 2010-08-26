<?php
if ( !function_exists( 'wp_mail' ) ) :
/**
 * wp_mail() - Function to send mail
 */
function wp_mail( $to, $subject, $message, $headers = '', $attachements = false ) {
	// Compact the input, apply the filters, and extract them back out
	extract( apply_filters('wp_mail', compact( 'to', 'subject', 'message', 'headers' ) ) );
	$mail =  new stdClass();
	// Headers
	if ( !empty( $headers ) && !is_array( $headers ) )
	{											// Explode the headers out, so this function can take both
												// string headers and an array of headers.
		$tempheaders = (array) explode( "\n", $headers );
		$headers = array();
		if ( !empty( $tempheaders ) ) 
		{										// Iterate through the raw headers
			foreach ( $tempheaders as $header ) 
			{
				if ( strpos($header, ':') === false ) continue;
												// Explode them out
				list( $name, $content ) = explode( ':', trim( $header ), 2 );
												// Cleanup crew
				$name = trim( $name );
				$content = trim( $content );
												// Mainly for legacy -- process a From: header if it's there
				switch (true)
				{
					case ( 'from' == strtolower($name) ) :
						if ( strpos($content, '<' ) !== false ) 
						{
												// So... making my life hard again?
							$from_name = substr( $content, 0, strpos( $content, '<' ) - 1 );
							$from_name = str_replace( '"', '', $from_name );
							$from_name = trim( $from_name );

							$from_email = substr( $content, strpos( $content, '<' ) + 1 );
							$from_email = str_replace( '>', '', $from_email );
							$from_email = trim( $from_email );
						} 
						else 
						{
							$from_name = trim( $content );
						}
					break;
					case ( 'content-type' == strtolower($name) ) :
						if ( strpos( $content, ';' ) !== false ) 
						{
							list( $type, $charset ) = explode( ';', $content );
							$content_type = trim( $type );
							$charset = trim( str_replace( array( 'charset=', '"' ), '', $charset ) );
						} 
						else 
						{
							$content_type = trim( $content );
						}
					break;
					default :
						$headers[trim( $name )] = trim( $content );
					break;
				}
			}
		}
	}
													// From email and name
													// Set the from name and email
	if ( isset( $from_email ) ) 
	{
		$mail->fromemail  = apply_filters('wp_mail_from', $from_email );
		$mail->fromname   = apply_filters('wp_mail_from_name', $from_name );
	}
													// Set destination address
	$mail->toemail = (is_array($to)) ? $to['email'] : $to;
	$mail->toname  = (is_array($to)) ? $to['name']  : '';
													// Set mail's subject and body
	$mail->subject = $subject;

	if (is_array($message))
	{
		$mail->plaintext = $message['plaintext'];
		$mail->html      = $message['html'];
	}
	else
	{
		$mail->content = $message;
	}

	if (!empty( $headers )) $mail->headers = $headers;

	return MailPress::mail($mail);
}
endif;

if ( ! function_exists('wp_notify_postauthor') ) :
/**
 * wp_notify_postauthor() - Notify an author of a comment/trackback/pingback to one of their posts
 */
function wp_notify_postauthor($comment_id, $comment_type='') {
	$comment = get_comment($comment_id);
	$post    = get_post($comment->comment_post_ID);
	$user    = get_userdata( $post->post_author );
	$current_user = wp_get_current_user();

	if ( $comment->user_id == $post->post_author ) return false; 		// The author moderated a comment on his own post

	if ('' == $user->user_email) return false; 					// If there's no email to send the comment to

	$comment_author_domain = @gethostbyaddr($comment->comment_author_IP);

	$blogname = get_option('blogname');

	if ( empty( $comment_type ) ) $comment_type = 'comment';

	$url_comments = get_permalink($comment->comment_post_ID) . '#comments';
	$url_trash  = admin_url("comment.php?action=trash&c=$comment_id");
	$url_delete = admin_url("comment.php?action=delete&c=$comment_id");
	$url_spam   = admin_url("comment.php?action=spam&c=$comment_id");

	switch ($comment_type)
	{
		case 'trackback' :
			$notify_message  = sprintf( __( 'New trackback on your post "%s"' ), $post->post_title ) . "<br />\r\n";
			$notify_message .= sprintf( __('Website: %1$s (IP: %2$s , %3$s)'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "<br />\r\n";
			$notify_message .= sprintf( __('URL    : %s'), $comment->comment_author_url ) . "<br />\r\n";
			$notify_message .= __('Excerpt: ') . "<br />\r\n" . apply_filters('comment_text', $comment->comment_content) . "<br />\r\n<br />\r\n";
			$notify_message .= __('You can see all trackbacks on this post here: ') . "<br />\r\n";

			$subject = sprintf( __('[%1$s] Trackback: "%2$s"'), $blogname, $post->post_title );
		break;
		case 'pingback' :
			$notify_message  = sprintf( __( 'New pingback on your post "%s"' ), $post->post_title ) . "<br />\r\n";
			$notify_message .= sprintf( __('Website: %1$s (IP: %2$s , %3$s)'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "<br />\r\n";
			$notify_message .= sprintf( __('URL    : %s'), $comment->comment_author_url ) . "<br />\r\n";
			$notify_message .= __('Excerpt: ') . "<br />\r\n" . sprintf('[...] %s [...]', apply_filters('comment_text', $comment->comment_content) ) . "<br />\r\n<br />\r\n";
			$notify_message .= __('You can see all pingbacks on this post here: ') . "<br />\r\n";
			
			$subject = sprintf( __('[%1$s] Pingback: "%2$s"'), $blogname, $post->post_title );
		break;
		default: //Comments
			$notify_message  = sprintf( __( 'New comment on your post "%s"' ), $post->post_title ) . "<br />\r\n";
			$notify_message .= sprintf( __('Author : %1$s (IP: %2$s , %3$s)'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "<br />\r\n";
			$notify_message .= sprintf( __('E-mail : %s'), $comment->comment_author_email ) . "<br />\r\n";
			$notify_message .= sprintf( __('URL    : %s'), $comment->comment_author_url ) . "<br />\r\n";
			$notify_message .= sprintf( __('Whois  : http://ws.arin.net/cgi-bin/whois.pl?queryinput=%s'), $comment->comment_author_IP ) . "<br />\r\n";
			$notify_message .= __('Comment: ') . "<br />\r\n" . apply_filters('comment_text', $comment->comment_content) . "<br />\r\n<br />\r\n";
			$notify_message .= __('You can see all comments on this post here: ') . "<br />\r\n";
			
			$subject = sprintf( __('[%1$s] Comment: "%2$s"'), $blogname, $post->post_title );
		break;
	}
	$notify_message .= $url_comments . "<br />\r\n<br />\r\n";

	if ( EMPTY_TRASH_DAYS )
		$notify_message .= sprintf( __('Trash it: %s'), $url_trash ) . "<br />\r\n";
	else
		$notify_message .= sprintf( __('Delete it: %s'), $url_delete ) . "<br />\r\n";
	$notify_message .= sprintf( __('Spam it: %s'), $url_spam ) . "<br />\r\n";

	$mail = new stdClass();
	$mail->Template	= 'moderate';
	$mail->toemail 	= $user->user_email;
	$mail->toname     = $user->display_name;
	$mail->subject 	= $subject;
	$mail->content 	= $notify_message;

		$mail->advanced = new stdClass();
		$mail->advanced->comment       = $comment;
		$mail->advanced->user          = $user;
		unset ($post->post_content, $post->post_excerpt);
		$mail->advanced->post          = $post;
		$mail->advanced->url['comments']=$url_comments;
		$mail->advanced->url['trash']  = $url_trash;
		$mail->advanced->url['delete'] = $url_delete;
		$mail->advanced->url['spam']   = $url_spam;

		$mail->the_title 		 = $post->post_title; 

		/* deprecated */
			$mail->c = new stdClass();
			$mail->c->id		= $comment_id;
			$mail->c->post_ID 	= $comment->comment_post_ID;
			$mail->c->author		= $comment->comment_author;
			$mail->c->author_IP 	= $comment->comment_author_IP;
			$mail->c->email		= $comment->comment_author_email;
			$mail->c->url 		= $comment->comment_author_url;
			$mail->c->domain		= $comment_author_domain;
			$mail->c->content 	= $comment->comment_content;
			$mail->c->type		= $comment_type;
		/* deprecated */

	return MailPress::mail($mail);
}
endif;

if ( !function_exists('wp_notify_moderator') ) :
/**
 * wp_notify_moderator() - Notifies the moderator of the blog about a new comment that is awaiting approval
 */
function wp_notify_moderator($comment_id) {
	global $wpdb;

	if( get_option( "moderation_notify" ) == 0 )
		return true;

	$comment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->comments WHERE comment_ID=%d LIMIT 1", $comment_id));
	$post = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE ID=%d LIMIT 1", $comment->comment_post_ID));

	$comment_author_domain = @gethostbyaddr($comment->comment_author_IP);
	$comments_waiting = $wpdb->get_var("SELECT count(comment_ID) FROM $wpdb->comments WHERE comment_approved = '0'");

	$blogname = get_option('blogname');

	$url_approve  = admin_url("comment.php?action=approve&c=$comment_id");
	$url_trash  = admin_url("comment.php?action=trash&c=$comment_id");
	$url_delete = admin_url("comment.php?action=delete&c=$comment_id");
	$url_spam   = admin_url("comment.php?action=spam&c=$comment_id");
	$url_moderate = admin_url("edit-comments.php?comment_status=moderated");

	switch ($comment->comment_type)
	{
		case 'trackback':
			$notify_message  = sprintf( __('A new trackback on the post"%s" is waiting for your approval'), $post->post_title ) . "<br />\r\n";
			$notify_message .= get_permalink($comment->comment_post_ID) . "<br />\r\n<br />\r\n";
			$notify_message .= sprintf( __('Website : %1$s (IP: %2$s , %3$s)'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "<br />\r\n";
			$notify_message .= sprintf( __('URL    : %s'), $comment->comment_author_url ) . "<br />\r\n";
			$notify_message .= __('Trackback excerpt: ') . "<br />\r\n" . apply_filters('comment_text', $comment->comment_content) . "<br />\r\n<br />\r\n";
		break;
		case 'pingback':
			$notify_message  = sprintf( __('A new pingback on the post "%s" is waiting for your approval'), $post->post_title ) . "<br />\r\n";
			$notify_message .= get_permalink($comment->comment_post_ID) . "<br />\r\n<br />\r\n";
			$notify_message .= sprintf( __('Website : %1$s (IP: %2$s , %3$s)'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "<br />\r\n";
			$notify_message .= sprintf( __('URL    : %s'), $comment->comment_author_url ) . "<br />\r\n";
			$notify_message .= __('Pingback excerpt: ') . "<br />\r\n" . apply_filters('comment_text', $comment->comment_content) . "<br />\r\n<br />\r\n";
		break;
		default: //Comments
			$notify_message  = sprintf( __('A new comment on the post "%s" is waiting for your approval'), $post->post_title ) . "<br />\r\n";
			$notify_message .= get_permalink($comment->comment_post_ID) . "<br />\r\n<br />\r\n";
			$notify_message .= sprintf( __('Author : %1$s (IP: %2$s , %3$s)'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "<br />\r\n";
			$notify_message .= sprintf( __('E-mail : %s'), $comment->comment_author_email ) . "<br />\r\n";
			$notify_message .= sprintf( __('URL    : %s'), $comment->comment_author_url ) . "<br />\r\n";
			$notify_message .= sprintf( __('Whois  : http://ws.arin.net/cgi-bin/whois.pl?queryinput=%s'), $comment->comment_author_IP ) . "<br />\r\n";
			$notify_message .= __('Comment: ') . "<br />\r\n" . apply_filters('comment_text', $comment->comment_content) . "<br />\r\n<br />\r\n";
		break;
	}

	$notify_message .= sprintf( __('Approve it: %s'), $url_approve ) . "<br />\r\n";
	if ( EMPTY_TRASH_DAYS )
		$notify_message .= sprintf( __('Trash it: %s'), $url_trash ) . "<br />\r\n";
	else
		$notify_message .= sprintf( __('Delete it: %s'), $url_delete ) . "<br />\r\n";
	$notify_message .= sprintf( __('Spam it: %s'), $url_spam ) . "<br />\r\n";

	$notify_message .= sprintf( _n('Currently %s comment is waiting for approval. Please visit the moderation panel:',
 		'Currently %s comments are waiting for approval. Please visit the moderation panel:', $comments_waiting), number_format_i18n($comments_waiting) ) . "<br />\r\n";
	$notify_message .= $url_moderate . "<br />\r\n";

	$subject = sprintf( __('[%1$s] Please moderate: "%2$s"'), $blogname, $post->post_title );
	$user    = get_user_by_email( get_option('admin_email') );

	$notify_message = apply_filters('comment_moderation_text', $notify_message, $comment_id);
	$subject = apply_filters('comment_moderation_subject', $subject, $comment_id);

	$mail = new stdClass();
	$mail->Template	= 'moderate';
	$mail->toemail 	= $user->user_email;
	$mail->toname     = $user->display_name;
	$mail->subject 	= $subject;
	$mail->content 	= $notify_message;

		$mail->advanced = new stdClass();
		$mail->advanced->comment = $comment;
		$mail->advanced->user    = $user;
		unset ($post->post_content, $post->post_excerpt);
		$mail->advanced->post    = $post;
		$mail->advanced->url['approve']= $url_approve;
		$mail->advanced->url['trash']  = $url_trash;
		$mail->advanced->url['delete'] = $url_delete;
		$mail->advanced->url['spam']   = $url_spam;

		$mail->the_title 		 = $post->post_title; 

		/* deprecated */
			$mail->c = new stdClass();
			$mail->c->id		= $comment_id;
			$mail->c->post_ID 	= $comment->comment_post_ID;
			$mail->c->author 		= $comment->comment_author;
			$mail->c->author_IP 	= $comment->comment_author_IP;
			$mail->c->email 		= $comment->comment_author_email;
			$mail->c->url 		= $comment->comment_author_url;
			$mail->c->domain 		= $comment_author_domain;
			$mail->c->content 	= $comment->comment_content;
			$mail->c->type		= $comment->comment_type;
		/* deprecated */

	return MailPress::mail($mail);
}
endif;

if ( !function_exists('wp_password_change_notification') ) :
/**
 * wp_password_change_notification() - Notify the blog admin of a user changing password, normally via email.
 */
function wp_password_change_notification(&$user) {
	// send a copy of password change notification to the admin
	// but check to see if it's the admin whose password we're changing, and skip this
	if ( $user->user_email == get_option('admin_email') ) return;

	$admin = get_user_by_email( get_option('admin_email') );

	$blogname = get_option('blogname');

	$mail = new stdClass();
	$mail->Template	= 'changed_pwd';
	$mail->toemail 	= $admin->user_email;
	$mail->toname     = $admin->display_name;
	$mail->subject 	= sprintf(__('[%s] Password Lost/Changed'), $blogname);
	$mail->content 	= sprintf(__('Password Lost and Changed for user: %s'), $user->user_login) . "<br />\r\n";

		$mail->advanced = new stdClass();
		$mail->advanced->admin   = $admin;
		$mail->advanced->user    = $user;

	return MailPress::mail($mail);
}
endif;

if ( !function_exists('wp_new_user_notification') ) :
/**
 * wp_new_user_notification() - Notify the blog admin of a new user, normally via email
 *
 * @since 2.0
 *
 * @param int $user_id User ID
 * @param string $plaintext_pass Optional. The user's plaintext password
 */
function wp_new_user_notification($user_id, $plaintext_pass = '') {
    	$admin = get_user_by_email( get_option('admin_email') );
	$user = new WP_User($user_id);

	$user_login = stripslashes($user->user_login);
	$user_email = stripslashes($user->user_email);

	$blogname = get_option('blogname');

	$message  = sprintf(__('New user registration on your site %s:'), $blogname) . "<br />\r\n<br />\r\n";
	$message .= sprintf(__('Username: %s'), $user_login) . "<br />\r\n<br />\r\n";
	$message .= sprintf(__('E-mail: %s'), $user_email) . "<br />\r\n";

	$mail = new stdClass();
	$mail->Template	= 'new_user';
	$mail->toemail 	= $admin->user_email;
	$mail->toname     = $admin->display_name;
	$mail->subject 	= sprintf(__('[%s] New User Registration'), $blogname);
	$mail->content 	= $message;

		$mail->advanced = new stdClass();
       	$mail->advanced->admin   = $admin;
		$mail->advanced->user    = $user;

		/* deprecated */
			$mail->u = new stdClass();
			$mail->u->login	= $user_login;
			$mail->u->email	= $user_email;
		/* deprecated */

	$ret = MailPress::mail($mail);

	if ( empty($plaintext_pass) ) return $ret;

	$user->plaintext_pass = $plaintext_pass;

	$message  = sprintf(__('Username: %s'), $user_login) . "<br />\r\n";
	$message .= sprintf(__('Password: %s'), $plaintext_pass) . "<br />\r\n";
	$message .= wp_login_url() . "<br />\r\n";

	$mail = new stdClass();
	$mail->Template	= 'new_user';
	$mail->toemail 	= $user->user_email;
	$mail->toname     = $user->display_name;
	$mail->subject 	= sprintf(__('[%s] Your username and password'), $blogname);
	$mail->content 	= $message;

		$mail->advanced = new stdClass();
		$mail->advanced->user    = $user;

		/* deprecated */
			$mail->u = new stdClass();
			$mail->u->login	= $user_login;
			$mail->u->pwd	= $plaintext_pass;
		/* deprecated */

	return MailPress::mail($mail);
}
endif;

add_filter('retrieve_password_message', 	'mp_retrieve_password_message', 8, 2);

function mp_retrieve_password_message($message, $key)
{
	$user = ( strpos($_POST['user_login'], '@') ) ? get_user_by_email(trim($_POST['user_login'])) : get_userdatabylogin(trim($_POST['user_login']));
	if (!$user) return $message;

	$user_login = $user->user_login;
	$user_email = $user->user_email;
	$url_site   = network_site_url();
	$url_reset  = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');

	$_message = __('Someone has asked to reset the password for the following site and username.') . "<br />\r\n<br />\r\n";
	$_message .= $url_site . "<br />\r\n<br />\r\n";
	$_message .= sprintf(__('Username: %s'), $user_login) . "<br />\r\n<br />\r\n";
	$_message .= __('To reset your password visit the following address, otherwise just ignore this email and nothing will happen.') . "<br />\r\n<br />\r\n";
	$_message .= $url_reset . "<br />\r\n";

	$blogname = get_option('blogname');

	$mail = new stdClass();
	$mail->Template	= 'retrieve_pwd';
	$mail->toemail 	= $user->user_email;
	$mail->toname     = $user->display_name;
	$mail->subject 	= sprintf( __('[%s] Password Reset'), $blogname );
	$mail->content 	= $_message;

		$mail->advanced = new stdClass();
		$mail->advanced->user = $user;
		$mail->advanced->url['site']   = $url_site;
		$mail->advanced->url['reset']  = $url_reset;

		/* deprecated */
			$mail->u = new stdClass();
			$mail->u->login	= $user_login;
			$mail->u->key	= $key;
			$mail->u->url	= $url_reset;
		/* deprecated */

	if (MailPress::mail($mail)) return false;
	return $message;
}

add_filter('password_reset_message', 	'mp_password_reset_message', 8, 2);

function mp_password_reset_message($message, $new_pass)
{
	global $wpdb;

	$key   = preg_replace('/[^a-z0-9]/i', '', $_GET['key']);
	$login = $_GET['login'];

	$user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->users WHERE user_activation_key = %s AND user_login = %s", $key, $login));
	if ( empty( $user ) ) return $message;

	$user->new_pass = $new_pass;

	$url_login   = site_url('wp-login.php', 'login');

	$_message  = sprintf(__('Username: %s'), $user->user_login) . "<br />\r\n";
	$_message .= sprintf(__('Password: %s'), $new_pass) . "<br />\r\n";
	$_message .= $url_login . "<br />\r\n";

	$blogname = get_option('blogname');

	$mail = new stdClass();
	$mail->Template	= 'reset_pwd';
	$mail->toemail 	= $user->user_email;
	$mail->toname     = $user->display_name;
	$mail->subject 	= sprintf( __('[%s] Your new password'), $blogname );
	$mail->content 	= $_message;

		$mail->advanced = new stdClass();
		$mail->advanced->user = $user;
		$mail->advanced->url['login']  = $url_login;

		/* deprecated */
			$mail->u = new stdClass();
			$mail->u->login	= $user->user_login;
			$mail->u->new_pass= $new_pass;
			$mail->u->url	= $url_login;
		/* deprecated */

	if (MailPress::mail($mail)) return false;
	return $message;
}