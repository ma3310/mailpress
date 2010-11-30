<?php
/*
Template Name: new_user
*/

$_the_title 	= "Congratulations !";

$_the_content 	= sprintf(__('Username: %s'), $this->args->u->login );
$_the_content    .= "\n";
$_the_content    .= (isset($this->args->admin)) ? sprintf(__('E-mail: %s'),   $this->args->u->email ) : sprintf(__('Password: %s'), $this->args->u->pwd ) ;
$_the_content    .= "\n";

$_the_actions 	= __('Log in') . ' [' . get_option('siteurl') . '/wp-login.php]';

include('_mail.php');