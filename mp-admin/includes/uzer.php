<?php

$url_parms 	= MP_Admin::get_url_parms();
$mp_user 	= MP_User::get_user( $_GET['id'] );

$h2 = sprintf( __('Edit MailPress User # %1$s','MailPress'), $mp_user->id);

$delete_url = clean_url(MP_Admin::url(MailPress_user  ."&amp;action=delete&amp;id=$mp_user->id",false,$url_parms));
$write_url  = clean_url(MailPress_write . '&toemail=' . $mp_user->email);

$last_date  = ($mp_user->created > $mp_user->laststatus) ? $mp_user->created : $mp_user->laststatus ;
$last_user 	= ($mp_user->created > $mp_user->laststatus) ? $mp_user->created_user_id : $mp_user->laststatus_user_id ;
$last_user 	= get_userdata($last_user );

$h21 		= (has_action('MailPress_user_advanced')) ? __('Advanced Options','MailPress') : false ; 
?>
<form id='mp_user' name='mp_user_form' action='' method='post'>
	<div class='wrap'>
		<input type="hidden" name='id' 		value="<?php echo $mp_user->id ?>" id='mp_user_id' />
		<input type="hidden" name='referredby' 	value='<?php echo clean_url($_SERVER['HTTP_REFERER']); ?>' />
		<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>

		<h2>
			<?php echo $h2; ?>
		</h2>
		<div id='poststuff'>
			<div id='submitcomment' class='submitbox' style='margin-top:13px;'>
				<div id="previewview">
					&nbsp;<br/>
				</div>
				<div class="inside">
					<br class="clear" />
					<br class="clear" />
				</div>
				<p class="submit">
					<input name='save' value='<?php _e('Save','MailPress'); ?>' type="submit" style="font-weight: bold;"/>
					<a class='submitdelete' href='<?php echo $delete_url ?>' onclick="if (confirm('<?php echo(js_escape(sprintf( __("You are about to delete this MailPress user '%s'\n  'Cancel' to stop, 'OK' to delete.",'MailPress'), $mp_user->id ))); ?>')) return true; return false;">
						<?php _e('Delete&nbsp;MailPress&nbsp;user','MailPress'); ?>
					</a>
					<br class="clear" />
					<!-- <?php printf(__('Last edited by %1$s on %2$s at %3$s','MailPress'), wp_specialchars( $last_user->display_name ), mysql2date(get_option('date_format'), $last_date), mysql2date(get_option('time_format'), $last_date)); ?> -->
					<br class="clear" />
				</p>
				<div class="side-info">
					<h5><?php _e('Related','MailPress'); ?></h5>
					<ul>
						<li><a href="<?php echo $write_url; ?>"><?php _e('Write to this user','MailPress'); ?></a></li>
						<li><a href="<?php echo MailPress_users; ?>"><?php _e('Manage All users','MailPress'); ?></a></li>
<?php do_action('MailPress_user_relatedlinks'); ?>
					</ul>
				</div>
			</div>
			<div id='post-body'>
				<div>
					<table class='form-table'>
						<tbody>
							<tr valign='top'>
								<th scope='row'>
									<?php _e('Email','MailPress'); ?>
								</th>
								<td>
									<input type='text' disabled='disabled' value='<?php echo $mp_user->email; ?>' size='30'/>
								</td>
							</tr>
						</tbody>
					</table>
					<br />
				</div>
<?php
do_action('MailPress_user_form',$mp_user->id);
do_meta_boxes('MailPress_user','normal',$mp_user);
if ($h21) echo "\n<h2> $h21 </h2>\n";
do_action('MailPress_user_advanced',$mp_user->id);
do_meta_boxes('MailPress_user','advanced',$mp_user);
?>
			</div>
		</div>
	</div>
</form>