<?php 
require_once(MP_TMP . 'mp-admin/class/MP_Admin_abstract.class.php');

class MP_AdminPage extends MP_Admin_abstract
{
	const screen 	= MailPress_page_settings;
	const capability 	= 'MailPress_manage_options';

////  Styles  ////

	public static function print_styles($styles = array()) 
	{
		wp_register_style ( self::screen, get_option('siteurl') . '/' . MP_PATH . 'mp-admin/css/settings.css' );

		$styles[] = self::screen;
		parent::print_styles($styles);
	}

////  Scripts  ////

	public static function print_scripts() 
	{
		wp_register_script( self::screen, '/' . MP_PATH . 'mp-admin/js/settings.js', array('jquery-ui-tabs'), false, 1);
		wp_localize_script( self::screen, 'MP_AdminPageL10n', array( 'requestFile' => MP_Action_url ) );

		$scripts[] = self::screen;
		parent::print_scripts($scripts);
	}

////  Misc  ////

	public static function save_button()
	{
?>
<p class='submit'>
	<input class='button-primary' type='submit' name='Submit' value='<?php  _e('Save Changes'); ?>' />
</p>
<?php
	}

	public static function logs_sub_form ($name, $data, $headertext, $optiontext, $foralltext, $numbertext)
	{
		if (!isset($data[$name])) $data[$name] = array('level' => 123456789, 'lognbr' => 0, 'lastpurge' => '');

		$xlevel = array (		123456789	=> __('No logging', 'MailPress') , 
							0	=> $optiontext , 
							1 	=> 'E_ERROR', 
							2 	=> 'E_WARNING', 
							4 	=> 'E_PARSE', 
							8 	=> 'E_NOTICE', 
							16 	=> 'E_CORE_ERROR', 
							32 	=> 'E_CORE_WARNING', 
							64 	=> 'E_COMPILE_ERROR', 
							128 	=> 'E_COMPILE_WARNING', 
							256 	=> 'E_USER_ERROR', 
							512 	=> '* E_USER_WARNING *', 
							1024 	=> 'E_USER_NOTICE', 
							2048 	=> 'E_STRICT', 
							4096 	=> 'E_RECOVERABLE_ERROR', 
							8191 	=> 'E_ALL' );
?>
<tr><th></th><td></td></tr>
<tr valign='top' class='mp_sep'>
	<th scope='row'><strong><?php echo $headertext; ?></strong></th>
	<td>
		<?php _e('Logging level : ', 'MailPress'); ?>
		<select name='logs[<?php echo $name ?>][level]'>
<?php self::select_option($xlevel, $data[$name]['level']);?>
		</select> 
		&nbsp;&nbsp;
		<i><?php echo $foralltext; ?></i>
		<br />
		<?php echo $numbertext; ?>
		<select name='logs[<?php echo $name ?>][lognbr]'>
<?php self::select_number(1, 10, $data[$name]['lognbr']);?>
		</select>
		<i><?php _e('(one log file per day)', 'MailPress'); ?></i>
		&nbsp;&nbsp;&nbsp;&nbsp;
		<?php _e('Date of last purge', 'MailPress'); ?>
		<input type='text' size='9' value='<?php echo $data[$name]['lastpurge']; ?>' disabled='disabled' />
		<input type='hidden' name='logs[<?php echo $name ?>][lastpurge]' value='<?php echo $data[$name]['lastpurge']; ?>' />
	</td>
</tr>
<?php
	}

////  Body  ////

	public static function body()
	{
		include (MP_TMP . 'mp-admin/includes/settings.php');
	}
}
?>