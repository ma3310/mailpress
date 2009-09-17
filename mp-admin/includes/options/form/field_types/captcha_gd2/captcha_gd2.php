<?php
if (!extension_loaded('gd')) return;

MailPress::require_class('Forms_field_type_abstract');

class MP_Forms_field_type_captcha_gd2 extends MP_Forms_field_type_abstract
{
	var $field_type 		= 'captcha_gd2';
	var $order			= 91;

	function __construct()
	{
		$this->description = __('Captcha 2', 'MailPress');
		$this->settings	 = dirname(__FILE__) . '/settings.xml';
		parent::__construct();
	}

	function submitted($field)
	{
		$value	= trim($_POST[$this->prefix][$field->form_id][$field->id]);

		$required 	= true;
		$empty = empty($value);

		if ($empty)
		{
			$field->submitted['on_error'] = 1;
			return $field;
		}
		else
		{
			session_start();

			if ((!$_SESSION['mp_googlelike']) || (strtolower($_SESSION['mp_googlelike']) != strtolower($value)))
			{
				$field->submitted['on_error'] = 1;
				return $field;
			}
		}

		$field->submitted['value'] = 1;
		$field->submitted['text']  = __('ok', 'MailPress');

		return $field;
	}

	function attributes_filter($no_reset)
	{
		if (!$no_reset) return;
		
		$this->attributes_filter_css();
	}

	function build_tag()
	{
		$id_input 	= $this->get_id($this->field);
		$tag_input 	= parent::build_tag();

		$id_img 	= $id_input . '_img';
		$tag_img 	= "<img id='$id_img' src='" . clean_url(MP_Action_url . '?id=' . $this->field->id . '&action=2ahctpac') . "' alt='' />";

		$this->field->type = $this->field_type;

		$form_format =  '{{img}}<br />{{input}}';

		MailPress::require_class('Forms');
		$form_template = MP_Forms::get_template($this->field->form_id);
		if ($form_template)
		{
			MailPress::require_class('Forms_templates');
			$form_templates = new MP_Forms_templates();
			$f = $form_templates->get_composite_template($form_template, $this->field_type);
			if (!empty($f)) $form_format = $f;
		}

		$search[] = '{{img}}';		$replace[] = '%1$s';
		$search[] = '{{id_img}}'; 	$replace[] = '%2$s';
		$search[] = '{{input}}'; 	$replace[] = '%3$s';
		$search[] = '{{id_input}}';	$replace[] = '%4$s';

		$html = str_replace($search, $replace,  $form_format);

		return sprintf($html, $tag_img, $id_img, $tag_input, $id_input);
	}
}
$MP_Forms_field_type_captcha_gd2 = new MP_Forms_field_type_captcha_gd2();
?>