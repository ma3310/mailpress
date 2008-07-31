<?php
//
///////////////////////////////////////////////////////////////////
///////////////////            MAIL          //////////////////////
///////////////////////////////////////////////////////////////////
//
$style = '';

$list_url = MP_Admin::url(MailPress_mails,false,MP_Admin::get_url_parms());

if ( isset($_POST['action']) )    $action = $_POST['action'];
elseif ( isset($_GET['action']) ) $action = $_GET['action'];  

switch($action) 
{
	case 'view' :
		$id = $_GET['id'];
		$mail = MP_Mail::get_mail($id);
		$h2 = sprintf( __('View Mail # %1$s','MailPress'), $mail->id);

		$mp_general = get_option('MailPress_general');
		$from = 	('send' == $mail->status) ? $mail->fromemail . '&nbsp;&nbsp;&nbsp;&nbsp;< ' . $mail->fromname . ' >' : $mp_general['fromemail'] . '&nbsp;&nbsp;&nbsp;&nbsp;< ' . $mp_general['fromname'] . ' >';
		$to = MP_Mail::display_toemail($mail->toemail,$mail->toname);
		$subject = ('send' == $mail->status) ? $mail->subject : MP_Mail::do_eval(stripslashes($mail->subject));
		$html_url = clean_url(get_option('siteurl') . '/' . MP_PATH . "mp-includes/action.php?action=viewadmin&id=$id&type=html");
		$html = "<iframe style='width:100%;border:0;height:500px' src='" . $html_url . "'></iframe>";
		$plaintext_url = clean_url(get_option('siteurl') . '/' . MP_PATH . "mp-includes/action.php?action=viewadmin&id=$id&type=plaintext");
		$plaintext = "<iframe style='width:100%;border:0;height:500px' src='" . $plaintext_url . "'></iframe>";

		$last_user = get_userdata($mail->sent_user_id);
		$mailinfo = ('sent' == $mail->status) ? sprintf(__('Sent by %1$s on %2$s at %3$s','MailPress'), wp_specialchars( $last_user->display_name ), mysql2date(get_option('date_format'), $mail->created), mysql2date(get_option('time_format'), $mail->created)) : sprintf(__('Last edited by %1$s on %2$s at %3$s','MailPress'), wp_specialchars( $last_user->display_name ), mysql2date(get_option('date_format'), $mail->created), mysql2date(get_option('time_format'), $mail->created));

		$related_url = clean_url($list_url);
		$related = __('Back to list','MailPress');

		$form='';

		$delete_url = clean_url(MailPress_mail  ."&action=delete&id=$mail->id");

		if ('draft' == $mail->status)
		{
			$form	= "<input name='save' value='" . __('Save','MailPress') . "' type='submit' style='font-weight: bold;' />\n";
			$form	.= "<input name='send' value='" . __('Send','MailPress') . "' type='submit' />\n";
			$form .="<input type='hidden' name='action' value='draft' />\n";
			$form .="<input type='hidden' name='referredby' value='' />\n";
			$form .="<input type='hidden' name='id' value='" . $mail->id . "' />\n";
			if (is_numeric($mail->toemail))
			{
				$mail->to_list = $mail->toemail;
				$mail->toemail = $mail->toname = '';
			}
			$form .="<input type='hidden' name='toemail' 	value=\"" . $mail->toemail . "\" />\n";
			$form .="<input type='hidden' name='toname'  	value=\"" . $mail->toname  . "\" />\n";
			$form .="<input type='hidden' name='to_list' 	value=\"" . $mail->to_list . "\" />\n";
			$form .="<input type='hidden' name='subject' 	value=\"" . htmlspecialchars(stripslashes($mail->subject))   . "\" />\n";
			$form .="<input type='hidden' name='html'    	value=\"" . htmlspecialchars(stripslashes($mail->html))      . "\" />\n";
			$form .="<input type='hidden' name='plaintext' 	value=\"" . htmlspecialchars(stripslashes($mail->plaintext)) . "\" />\n";
			$form	.= "<a class='submitdelete' href='" . $delete_url . "' onclick=\"if ( confirm('" . js_escape(sprintf( __("You are about to delete this draft '%s'\n  'Cancel' to stop, 'OK' to delete."), $mail->id )) . "') ) return true;return false;\">" . __('Delete&nbsp;draft','MailPress') . "</a><br class='clear' />";

			$last_user = get_userdata($mail->created_user_id);
			$mailinfo = sprintf(__('Last edited by %1$s on %2$s at %3$s','MailPress'), wp_specialchars( $last_user->display_name ), mysql2date(get_option('date_format'), $mail->created), mysql2date(get_option('time_format'), $mail->created));
		}
		else
		{
			if ( current_user_can( 'level_10') ) $form	= "<a class='submitdelete' href='" . $delete_url . "' onclick=\"if ( confirm('" . js_escape(sprintf( __("You are about to delete this mail  '%s'\n  'Cancel' to stop, 'OK' to delete."), $mail->id )) . "') ) return true;return false;\">" . __('Delete&nbsp;mail','MailPress') . "</a><br class='clear' />";
		}
		include('mail-form.php');
	break;
	case 'draft' :
		if (empty($_POST['to_list']) && !MailPress::is_email($_POST['toemail']))
		{
			$message = __('Please, enter a valid email',  'MailPress') . '<br />'; 
			$style   = " style='background-color:#f00;color:#fff;'";
			$err     = false;
			MP_Admin::message($message,$err); 
		}

		switch(true)
		{
			case (isset($_POST['view'])) :
				if (isset($_POST['id']))
				{
					$mail = MP_Mail::get_mail($_POST['id']);
					$h2 = sprintf( __('View Draft # %1$s','MailPress'), $mail->id);
				}
				else 
				{
					$mail->status = 'draft';
					$h2 = __('View Draft','MailPress');
				}

				$mp_general = get_option('MailPress_general');
				$from = $mp_general['fromemail'] . '&nbsp;&nbsp;&nbsp;&nbsp;< ' . $mp_general['fromname'] . ' >';
				$to = MP_Mail::display_toemail($_POST['toemail'],$_POST['toname'],$_POST['to_list']);
				$subject = MP_Mail::do_eval(stripslashes($_POST['subject']));

				$x = new MP_Mail();

				$x->args->html = stripslashes($_POST['html']);
				$html = $x->build_mail_content('html');
				$html = $x->process_img($html,$x->mail->themedir,'draft');
				$html = "<object type='text/html' style='width:100%;border:0;height:500px'>$html</object>";

				$x->args->plaintext = htmlspecialchars(stripslashes($_POST['plaintext']));
				$plaintext = strip_tags($x->build_mail_content('plaintext'));
				$plaintext = '<pre>' . $plaintext . '</pre>';
				$plaintext = "<object type='text/html' style='width:100%;border:0;height:500px'>$plaintext</object>";

				$form	= "<input name='save' value='" . __('Save','MailPress') . "' type='submit' style='font-weight: bold;' />\n";
				$form	.= "<input name='send' value='" . __('Send','MailPress') . "' type='submit' />\n";

				foreach ($_POST as $k => $v) 
				{
					if ($k == 'view') continue;
					$v = htmlspecialchars(stripslashes($v));
					$form .="<input type='hidden' name='$k' value=\"$v\" />\n";
				}

				if (isset($mail->id))
				{
					$delete_url = clean_url(MailPress_mail  ."&amp;action=delete&amp;id=$mail->id");
					$form	.= "<a class='submitdelete' href='" . $delete_url . "' onclick=\"if ( confirm('" . js_escape(sprintf( __("You are about to delete this draft '%s'\n  'Cancel' to stop, 'OK' to delete."), $mail->id )) . "') ) return true;return false;\">" . __('Delete draft','MailPress') . "</a><br class='clear' />";

					$last_user = get_userdata($mail->created_user_id);
					$mailinfo = sprintf(__('Last edited by %1$s on %2$s at %3$s','MailPress'), wp_specialchars( $last_user->display_name ), mysql2date(get_option('date_format'), $mail->created), mysql2date(get_option('time_format'), $mail->created));
				}

				include('mail-form.php');
			break;
		}
	break;
	default :
		echo "<div class='wrap'>";
		echo '<h2>' . __('You should not be here','MailPress') . '</h2>';
		echo '<br/><br/><br/><br/><br/><br/><b>';
		_e('You should not be here','MailPress');
		echo '</b><br/><br/><br/><br/><br/><br/>';
		echo '</div>';
	break;
}
?>