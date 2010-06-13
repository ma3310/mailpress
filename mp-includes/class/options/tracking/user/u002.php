<?php
class MP_Tracking_module_u002 extends MP_Tracking_module_abstract
{
	var $module = 'u002';
	var $context = 'side';
	var $file = __FILE__;

	function __construct($title)
	{
		add_filter('MailPress_scripts', array(&$this, 'scripts'), 8, 2);
		add_filter('MailPress_styles',  array(&$this, 'styles'),  8, 2);
		parent::__construct($title);
	}

	function styles($styles) 
	{
		$styles[] = 'thickbox';
		return $styles;
	}

	function scripts($scripts)
	{
		wp_register_script( 'mp-thickbox', 		'/' . MP_PATH . 'mp-includes/js/mp_thickbox.js', array('thickbox'), false, 1);
		$scripts[] = 'mp-thickbox';
		return $scripts;
	}

	function meta_box($mp_user)
	{
		global $wpdb;
		MailPress::require_class('Mail');
		$x = new MP_Mail();

		$tracks = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->mp_usermeta WHERE mp_user_id = %d AND meta_key = %s ORDER BY meta_id DESC LIMIT 10;", $mp_user->id, '_MailPress_mail_sent') );
		if ($tracks)
		{
			MailPress::require_class('Mailmeta');
			foreach($tracks as $track)
			{
				$subject = $wpdb->get_var($wpdb->prepare( "SELECT subject FROM $wpdb->mp_mails WHERE id = %d ;", $track->meta_value));

				if ($subject)
				{
					$subject 	= $x->viewsubject($subject, $track->meta_value, $track->meta_value, $mp_user->id);

					$view_url	= clean_url(add_query_arg( array('action' => 'iview', 'id' => $track->meta_value, 'user' => $mp_user->id, 'key' => $mp_user->confkey, 'KeepThis' => 'true', 'TB_iframe' => 'true', 'width' => '600', 'height' => '400'), MP_Action_url ));
					$track->meta_value = "<a href='$view_url' class='thickbox'  title='" . sprintf( __('View "%1$s"', MP_TXTDOM) , $subject) . "'>" . $track->meta_value . '</a>';
				}
				else $subject = __('(deleted)', MP_TXTDOM);
				echo '(' . $track->meta_value . ') ' . $subject . '<br />';
			} 
		}
	}
}
new MP_Tracking_module_u002(__('Last 10 mails',  MP_TXTDOM));