<?php
if (class_exists('MailPress_mailinglist'))
{

MailPress::require_class('Import_importer_abstract');
class MP_export_mailinglist extends MP_Import_importer_abstract
{
	function __construct() 
	{
		$this->importer 		= 'csv_export_mailing_list';
		$this->description 	= __('Export your mailing list in a <strong>csv</strong> file.', MP_TXTDOM);
		$this->header 		= __('Export mailing list', MP_TXTDOM);
		$this->callback 		= array (&$this, 'dispatch');
		parent::__construct();
	}

	function dispatch($step = 0) 
	{
		if (isset($_GET['step']) && !empty($_GET['step'])) $step = (int) $_GET['step'];

		$this->header();
		switch ($step) 
		{
			case 0 :
				$this->greet();
			break;
			case 1:
				$this->start_trace($step);
					$export = $this->export();
				$this->end_trace(true);
				if ($export)
				{
					$file = get_option('siteurl') . '/' . MP_PATH . 'tmp/' . $this->file;
					$this->success('<p>' . sprintf(__("<b>File exported</b> : <i>%s</i>", MP_TXTDOM), "<a href='$file'>$file</a>") . '</p><p><strong>' . sprintf(__("<b>Number of records</b> : <i>%s</i>", MP_TXTDOM), $export) . '</strong></p>');
				}
				else 
					$this->error('<p><strong>' . $this->file . '</strong></p>');
			break;
		}
		$this->footer();
	}

// step 0

	function greet() 
	{
?>
<div>
	<p>
<?php		_e('Howdy! Choose your mailing list and we&#8217;ll export the emails ... into a file.', MP_TXTDOM); ?>
		<br />
	</p>
	<form id='export-mailing-list' method='post' action='<?php echo MailPress_import . '&amp;mp_import=' . $this->importer . '&amp;step=1'; ?>'>
		<p>
			<label for='download'><?php _e( 'Choose a mailing list :', MP_TXTDOM ); ?></label>
<?php
			MP_AdminPage::require_class('Mailinglists');
			$dropdown_options = array('hierarchical' => true, 'show_count' => 0, 'orderby' => 'name', 'htmlid' => 'export_mailinglist', 'name' => 'export_mailinglist', 'selected' => get_option('MailPress_default_mailinglist'));
			MP_Mailinglists::dropdown($dropdown_options);
?>
		</p>
		<p class='submit'>
			<input type='submit' class='button' value='<?php esc_attr_e( 'Export', MP_TXTDOM ); ?>' />
		</p>
	</form>
</div>
<?php
	}

// step 1

	function export() 
	{
		MP_AdminPage::require_class('Mailinglists');

		$this->message_report(" EXPORTING  !");

		$id = $_POST['export_mailinglist'];

		$x = $id;
		$y = MP_Mailinglists::get_children($x, ', ', '');
		$x = ('' == $y) ? ' = ' . $x : ' IN (' . $x . $y . ') ';


		global $wpdb;
		$query = "SELECT DISTINCT c.id, c.email, c.name, c.status, c.created, c.created_IP, c.created_agent, c.created_user_id, c.created_country, c.created_US_state, c.laststatus, c.laststatus_IP, c.laststatus_agent, c.laststatus_user_id  FROM $wpdb->term_taxonomy a, $wpdb->term_relationships b, $wpdb->mp_users c WHERE a.taxonomy = '" . MailPress_mailinglist::taxonomy . "' AND  a.term_taxonomy_id = b.term_taxonomy_id AND a.term_id $x AND c.id = b.object_id AND c.status = 'active'; ";

		$fields = array('id', 'email', 'name', 'status', 'created', 'created_IP', 'created_agent', 'created_user_id', 'created_country', 'created_US_state', 'laststatus', 'laststatus_IP', 'laststatus_agent', 'laststatus_user_id');
		$users = $wpdb->get_results($query, ARRAY_A);

		if (empty($users))
		{
			$this->message_report(' **WARNING* ! Mailing list is empty !');
			return false;
		}

		$this->file = 'csv_export_mailing_list_' . $id . '_' . date('Ymd_Hi') . '.csv';

		require_once 'parsecsv/parsecsv.lib.php';
		$csv = new parseCSV();
		$r = file_put_contents(MP_TMP . 'tmp/' . $this->file, $csv->unparse($users, $fields));

		if (!$r)
		{
			$this->message_report(' ***ERROR** ! Unable to write file');
			return false;
		}

		$this->message_report('   SUCCESS  ! file available at ' . MP_TMP . 'tmp/' . $this->file);
		return count($users);
	}
}
$MP_export_mailinglist = new MP_export_mailinglist();
}
?>