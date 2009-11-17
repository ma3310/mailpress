<?php
class MP_Autoresponders
{
	const taxonomy = MailPress_autoresponder::taxonomy;

	public static function exists($term_name) 
	{
		$id = is_term($term_name, self::taxonomy);
		if ( is_array($id) )	$id = $id['term_id'];
		return $id;
	}

	public static function get($term_id, $output = OBJECT, $filter = 'raw') 
	{
		$term = get_term($term_id, self::taxonomy, $output, $filter);
		if ( is_wp_error( $term ) )	return false;
		$term->slug = self::remove_slug($term->slug);
		if (!is_array($term->description)) $term->description = unserialize($term->description);
		return $term;
	}

	public static function insert($term_arr, $wp_error = false) 
	{
		$term_defaults = array('id' => 0, 'name' => '', 'slug' => '', 'description' => '');
		$term_arr = wp_parse_args($term_arr, $term_defaults);
		extract($term_arr, EXTR_SKIP);

		if ( trim( $name ) == '' ) 
		{
			if ( ! $wp_error )	return 0;
			else				return new WP_Error( 'autoresponder_name', __('You did not enter a valid autoresponder name.', 'MailPress') );
		}

		$slug = self::add_slug($slug, $name);
		$description = mysql_real_escape_string(serialize($description));

		$id = (int) $id;

		// Are we updating or creating?
		$update = (!empty ($id) ) ? true : false;

		$args = compact('name', 'slug', 'parent', 'description');

		if ( $update )	$term = wp_update_term($id,   self::taxonomy, $args);
		else			$term = wp_insert_term($name, self::taxonomy, $args);

		if ( is_wp_error($term) ) 
		{
			if ( $wp_error )	return $term;
			else			return 0;
		}

		return $term['term_id'];
	}

	public static function delete($term_id)
	{
		$meta_key = '_MailPress_autoresponder_' . $term_id;

		global $wpdb;
		$query = "DELETE FROM $wpdb->mp_mailmeta WHERE meta_key = '$meta_key';";
		$wpdb->query($query);
		$query = "DELETE FROM $wpdb->mp_usermeta WHERE meta_key = '$meta_key';";
		$wpdb->query($query);

		return wp_delete_term( $term_id, self::taxonomy);
	}

	public static function get_all($args = '')
	{
		$defaults = array('hide_empty' => 0, 'hierarchical' => 0, 'child_of' => '0', 'parent' => '');
		$args = wp_parse_args($args, $defaults);
		$terms = get_terms(self::taxonomy, $args);
		if (empty($terms)) return array();
		foreach ($terms as $k => $term)
		{
			$terms[$k]->slug = self::remove_slug($term->slug);
			if (!is_array($term->description)) $terms[$k]->description = unserialize($term->description);
		}
		return $terms;
	}

	public static function get_from_event($event)
	{
		$defaults = array('hide_empty' => 0, 'hierarchical' => 0, 'child_of' => '0', 'parent' => '');
		$terms = get_terms(self::taxonomy, $defaults);
		if (empty($terms)) return array();
		foreach ($terms as $k => $term)
		{
			$terms[$k]->slug = self::remove_slug($term->slug);
			if (!is_array($term->description)) $terms[$k]->description = unserialize($term->description);
			if (isset($term->description['active']) && ($event == $term->description['event'])) continue;
			unset($terms[$k]);
		}
		return $terms;
	}

////  Object  ////

	public static function get_object_terms( $object_id = 0, $args = array() )
	{
		$_terms = array();
		$terms = self::get_all($args);

		if (!$terms) return array();

		MailPress::require_class('Mailmeta');
		foreach( $terms as $term )
		{
			$metakey = '_MailPress_autoresponder_' . $term->term_id;
			$metadata = MP_Mailmeta::has($object_id, $metakey);
			if ($metadata) foreach ($metadata as $entry) 	$_terms[] =	array('term_id' 	=> $term->term_id, 
														'mmeta_id' 	=> $entry['mmeta_id'], 
														'mail_id' 	=> $object_id, 
														'schedule' 	=> $entry['meta_value']
													);
		}

		uasort($_terms, create_function('$a, $b', 'return strcmp($a["term_id"] . "  " . $a["schedule"], $b["term_id"] . "  " . $b["schedule"]);'));
		return $_terms;
	}

	public static function object_have_relations($object_id)
	{
		$terms = self::get_all();

		if (!$terms) return false;

		foreach( $terms as $term )
		{
			$metakey = '_MailPress_autoresponder_' . $term->term_id;
			MailPress::require_class('Mailmeta');
			$metadata = MP_Mailmeta::has($object_id, $metakey);
			if ($metadata) return true;
		}
		return false;
	}

	public static function get_term_objects($term_id)
	{
		global $wpdb;
		$query = "SELECT * FROM $wpdb->mp_mailmeta WHERE meta_key = '_MailPress_autoresponder_$term_id' ORDER BY meta_value;";
		$metadata = $wpdb->get_results($query);
		if (!$metadata) return array();
		foreach ($metadata as $entry) $_objects[] = 	array(	'term_id' 	=> $term_id, 
												'mmeta_id' 	=> $entry->mmeta_id, 
												'mail_id' 	=> $entry->mail_id, 
												'schedule' 	=> $entry->meta_value
											);
		return $_objects;
	}

	public static function get_mmeta_id_term($mid)
	{
		MailPress::require_class('Mailmeta');
		$entry = MP_Mailmeta::get_by_id( $mid );

		$term_id = str_replace('_MailPress_autoresponder_', '', $entry->meta_key);

		return 							array(	'term_id' 	=> $term_id, 
												'mmeta_id' 	=> $entry->mmeta_id, 
												'mail_id' 	=> $entry->mail_id, 
												'schedule' 	=> $entry->meta_value
											);
	}

////  Slug  ////

	public static function add_slug( $slug, $name = false )
	{
		$slugs = ($name) ? array($slug, $name) : array($slug);
		foreach ($slugs as $slug)
		{
			$slug = self::remove_slug($slug);
			$slug = trim(stripslashes($slug));
			$slug = str_replace('"', '_', $slug);
			$slug = str_replace("'", '_', $slug);
			if (!empty($slug)) break;
		}
		return $slug = '_' . self::taxonomy . '_' . $slug;
	}

	public static function remove_slug( $slug )
	{
		return str_ireplace('_' . self::taxonomy . '_', '', $slug);
	}
}
?>