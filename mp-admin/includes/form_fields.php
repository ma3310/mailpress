<?php
global $action;

$url_parms = self::get_url_parms(array('s', 'apage', 'id', 'form_id'));

self::require_class('Forms');
$form = MP_Forms::get($url_parms['form_id']);

$args = array();
$args['id'] 	= $form->id;
$args['action'] 	= 'ifview';
$args['KeepThis'] = 'true'; $args['TB_iframe']= 'true'; $args['width'] = '600'; $args['height']	= '400';
$view_url		= clean_url(self::url(MP_Action_url, $args));

$h2 = sprintf(__('Fields in "%1$s" form','MailPress'), "<a class='thickbox' href='$view_url' title=\"" . __('Form preview', 'MailPress') . "\" >" . stripslashes($form->label) . "</a>");

self::require_class('Forms_field_types');
$field_types = MP_Forms_field_types::get_all();

wp_reset_vars(array('action'));
if ('edit' == $action) 
{
	self::require_class('Forms_fields');
	$id = (int) $url_parms['id'];
	$field = MP_Forms_fields::get($id);

// protected
	$disabled = '';
	if (isset($field->settings['options']['protected']) && $field->settings['options']['protected']) $disabled = " disabled='disabled'";

	$h3 = sprintf(__('Update field # %1$s','MailPress'), $id);
	$action = 'edited';
	$cancel = "<input type='submit' class='button' name='cancel' value=\"" . __('Cancel','MailPress') . "\" />\n";
}
else 
{
	$field = new stdClass();
	$field->type = 'text';

	$h3 = __('Add a field','MailPress');
	$action = self::add_form_id;
	$disabled = '';
	$cancel = '';
}

$field->form_incopy = false;
if (isset($form->settings['visitor']['mail']) && ($form->settings['visitor']['mail'] != '0'))
{
	$field->form_incopy = true;
	add_filter('MailPress_form_columns_form_fields', array('MP_AdminPage', 'add_incopy_column'), 1, 1);
}

// Form templates

self::require_class('Forms_templates');
$form_templates = new MP_Forms_templates();
$xform_subtemplates = $form_templates->get_all_fields($form->template);


//
// MANAGING MESSAGE
//

$messages[1] = __('Field added.','MailPress');
$messages[2] = __('Field deleted.','MailPress');
$messages[3] = __('Field updated.','MailPress');
$messages[4] = __('Field not added.','MailPress');
$messages[5] = __('Field not updated.','MailPress');
$messages[6] = __('Fields deleted.','MailPress');

if (isset($_GET['message']))
{
	$message = $messages[$_GET['message']];
	$_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
}

//
// MANAGING PAGINATION
//

$url_parms['apage'] = isset($url_parms['apage']) ? $url_parms['apage'] : 1;
do
{
	$start = ( $url_parms['apage'] - 1 ) * 20;
	list($_fields, $total) = self::get_list($start, 25, $url_parms); // Grab a few extra
	$url_parms['apage']--;		
} while ( $total <= $start );
$url_parms['apage']++;
if( !isset($fieldsperpage) || $fieldsperpage <= 0 ) $fieldsperpage = 20;

$fields 		= array_slice($_fields, 0, $fieldsperpage);
$extra_fields 	= array_slice($_fields, $fieldsperpage);

$page_links = paginate_links	(array(	'base' => add_query_arg( 'apage', '%#%' ),
							'format' => '',
							'total' => ceil($total / $fieldsperpage),
							'current' => $url_parms['apage']
						)
					);
if ($url_parms['apage'] == 1) unset($url_parms['apage']);
?>
<div class="wrap nosubsub">
	<div id="icon-mailpress-tools" class="icon32"><br /></div>
	<h2><?php echo $h2; ?></h2>
<?php if (isset($message)) self::message($message); ?>
	<form class='search-form topmargin' action='' method='get'>
		<p class='search-box'>
			<input type='hidden' name='page' value='<?php echo MailPress_page_forms; ?>' />
			<input type='hidden' name='file' value='fields' />
			<input type='text' id='post-search-input' name='s' value='<?php if (isset($url_parms['s'])) echo $url_parms['s']; ?>' class="search-input"  />
			<input type='submit' value="<?php _e( 'Search Fields', 'MailPress' ); ?>" class='button' />
		</p>
	</form>
	<br class='clear' />
	<div id="col-container">
		<div id="col-right">
			<div class="col-wrap">	
				<form id='posts-filter' action='' method='get'>
<?php self::post_url_parms($url_parms, array('s', 'apage', 'id', 'form_id')); ?>
					<div class='tablenav'>
<?php 	if ( $page_links ) echo "						<div class='tablenav-pages'>$page_links</div>"; ?>
						<div class='alignleft actions'>
							<input type='submit' value="<?php _e('Delete','MailPress'); ?>" name='deleteit' class='button-secondary delete action' />
							<input type='hidden' name='page' value='<?php echo MailPress_page_forms; ?>' />
							<input type='hidden' name='file' value='fields' />
						</div>
						<br class='clear' />
					</div>
					<div class="clear"></div>
					<table class='widefat'>
						<thead>
							<tr>
<?php self::columns_list(); ?>
							</tr>
						</thead>
						<tfoot>
							<tr>
<?php self::columns_list(false); ?>
							</tr>
						</tfoot>
						<tbody id='<?php echo self::list_id; ?>' class='list:<?php echo self::tr_prefix_id; ?>'>
<?php if ($fields) : ?>
<?php foreach ($fields as $_field) 		echo self::get_row( $_field->id, $url_parms ); ?>
<?php endif; ?>
						</tbody>
<?php if ($extra_fields) : ?>
						<tbody id='<?php echo self::list_id; ?>-extra' class='list:<?php echo self::tr_prefix_id; ?>' style='display: none;'>
<?php
	foreach ($extra_fields as $_field)	echo self::get_row( $_field->id, $url_parms ); ?>
						</tbody>
<?php endif; ?>
					</table>
					<div class='tablenav'>
<?php 	if ( $page_links ) echo "						<div class='tablenav-pages'>$page_links</div>\n"; ?>
						<div class='alignleft actions'>
							<input type='submit' value="<?php _e('Delete','MailPress'); ?>" name='deleteit' class='button-secondary delete' />
						</div>
						<br class='clear' />
					</div>
					<br class='clear' />
				</form>
			</div>
		</div><!-- /col-right -->
		<div id="col-left">
			<div class="col-wrap">
				<div class="form-wrap">
					<h3><?php echo $h3; ?></h3>
					<div id="ajax-response"></div>
					<form name='<?php echo $action; ?>'  id='<?php echo $action; ?>'  method='post' action='' class='<?php echo $action; ?>:<?php echo self::list_id; ?>: validate'>
						<input type='hidden' name='action'   value='<?php echo $action; ?>' />
<?php self::post_url_parms($url_parms, array('id', 'form_id')); ?>
						<?php wp_nonce_field('update-' . self::tr_prefix_id); ?>

						<div class="form-field form-required" style='margin:0;padding:0;'>
							<label for='field_label'><?php _e('Label','MailPress'); ?></label>
							<input name='label' id='field_label' type='text' value="<?php if (isset($field->label)) echo self::input_text($field->label); ?>" size='40' aria-required='true' />
							<p>&nbsp;</p>
						</div>

						<div class="form-field" style='margin:0;padding:0;'>
							<span style='float:right'>
								<span class='description'><small><?php _e('order in form', 'MailPress'); ?></small></span>
								<select name='ordre' id='field_ordre'>
<?php self::select_number(1, 100, (isset($field->ordre)) ? $field->ordre : 1); ?>
								</select>
								<span class='description'><small><?php _e('sub template', 'MailPress'); ?></small></span>
								<select name='template' id='field_template'>
<?php self::select_option($xform_subtemplates, (isset($field->template)) ? $field->template : ( (isset($xform_subtemplates[$field->type])) ? $field->type : 'standard' ) ); ?>
								</select>
							</span>
							<label for='field_description' style='display:inline;'><?php _e('Description','MailPress'); ?></label>
							<input name='description' id='field_description' type='text' value="<?php if (isset($field->description)) echo self::input_text($field->description); ?>" size='40' />
							<p><small><?php _e('The description can be use to give further explanations','MailPress'); ?></small></p>
						</div>


						<div>
							<label><?php _e('Type','MailPress') ?></label>
							<table style='margin:1px;padding:3px;width:100%;-moz-border-radius: 5px;-webkit-border-radius: 5px;-khtml-border-radius: 5px;' class='bkgndc bd1sc'>
<?php
$col = 2;
$td = 0;
$tr = false;
foreach ($field_types as $key => $field_type)
{
	if (intval ($td/$col) == $td/$col ) echo "\t\t\t\t\t\t\t\t<tr>\n";
?>
									<td style='padding:0 5px 5px;'>
										<input type='radio' value='<?php echo $key; ?>' name='_type' id='field_type_<?php echo $key; ?>' class="field_type"<?php checked($key, $field->type); ?><?php if ( (!empty($disabled)) && ($key != $field->type) ) echo " disabled='disabled'"; ?> />
									</td>
									<td>
										<label for="field_type_<?php echo $key; ?>" class="field_type_<?php echo $key; ?>" style="padding-left:28px;margin-right:1em;display:inline;font-size:11px;"><?php echo $field_type['desc']; ?></label>
									</td>
<?php
	$td++;
	if (intval ($td/$col) == $td/$col ) echo "\t\t\t\t\t\t\t\t</tr>\n";
}
if (intval ($td/$col) != $td/$col ) while (intval ($td/$col) != $td/$col ) {echo "\t\t\t\t\t\t\t\t\t<td colspan='2'></td>\n"; ++$td; $tr = true;}
if ($tr) echo "\t\t\t\t\t\t\t\t</tr>\n";
?>
							</table>
						</div>



						<div id='form_fields_specs' style='margin-top:18px;'>
<?php foreach ($field_types as $key => $field_type) MP_Forms_field_types::settings_form($key, $field); ?>
						</div>
						<p class='submit'>
							<input type='submit' class='button-primary' name='submit' id='form_submit' value="<?php echo $h3; ?>" />
							<?php echo $cancel; ?>
						</p>
					</form>
				</div>
			</div>
		</div><!-- /col-left -->
	</div><!-- /col-container -->
</div><!-- /wrap -->