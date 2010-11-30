<?php
abstract class MP_Autoresponders_event_abstract
{
	const bt = 100;

	function __construct($desc)
	{
		$this->desc = $desc;
		if (!isset($this->callback)) $this->callback = array(&$this, 'callback');

		add_filter('MailPress_autoresponder_events_register',	array(&$this, 'register'), 8, 1);
		add_action($this->event, $this->callback, 8, 1);
		add_action('mp_process_autoresponder_' . $this->id, array(&$this, 'process'), 8, 1);
	}

	function register($events)
	{
		$events[$this->id] = $this->desc;
		return $events;
	}

//// Tracking events to autorespond to  ////

	function callback($mp_user_id)
	{
		$autoresponders = MP_Autoresponders::get_from_event($this->id);
		if (empty($autoresponders)) return;

		foreach( $autoresponders as $autoresponder )
		{
			$_mails = MP_Autoresponders::get_term_objects($autoresponder->term_id);

			if (!isset($_mails[0])) continue;

			$term_id = $autoresponder->term_id;

			$time = time();
			$schedule = $this->schedule($time, $_mails[0]['schedule']);
			MailPress::require_class('Usermeta');
			$meta_id = MP_Usermeta::add($mp_user_id, '_MailPress_autoresponder_' . $term_id, $time);

			wp_schedule_single_event($schedule, 'mp_process_autoresponder_' . $this->id, 	array('args' => array('meta_id' => $meta_id, 'mail_order'=> 0 )));

			MailPress::require_class('Log');
			$this->trace = new MP_Log('autoresponder', MP_ABSPATH, 'MP_Autoresponder_' . $term_id, false, 'autoresponder');

			$this->trace->log('!' . str_repeat( '-', self::bt) . '!');
			$bm = "Batch Report autoresponder #$term_id            meta_id : $meta_id  mail_order : 0";
			$this->trace->log('!' . str_repeat( ' ', 5) . $bm . str_repeat( ' ', self::bt - 5 - strlen($bm)) . '!');
			$this->trace->log('!' . str_repeat( '-', self::bt) . '!');
			$bm = " mp_user    ! $mp_user_id";
			$this->trace->log('!' . $bm . str_repeat( ' ', self::bt - strlen($bm)) . '!');
			$bm = " event      ! " . $this->event;
			$this->trace->log('!' . $bm . str_repeat( ' ', self::bt - strlen($bm)) . '!');
			$bm = " 1st sched. ! " . date('Y-m-d H:i:s', $schedule);
			$this->trace->log('!' . $bm . str_repeat( ' ', self::bt - strlen($bm)) . '!');
			$this->trace->log('!' . str_repeat( '-', self::bt) . '!');

			$this->trace->end(true);
		}
	}

	function schedule($time, $schedule)
	{
		$Y = date('Y', $time);
		$M = date('n', $time) + substr($schedule, 0, 2);
		$D = date('j', $time) + substr($schedule, 2, 2);
		$H = date('G', $time) + substr($schedule, 4, 2);
		$Mn= date('i', $time);
		$S = date('s', $time);
		$U = date('u', $time);

		return mktime($H, $Mn, $S, $M, $D, $Y);
	}

	function process($args)
	{
		MailPress::no_abort_limit();

		extract($args);		// $meta_id, $mail_order
		$meta_id = (isset($umeta_id)) ? $umeta_id : $meta_id;

		MailPress::require_class('Usermeta');
		$meta = MP_Usermeta::get_by_id($meta_id);
		$term_id 	= (!$meta) ? 'unknown' : str_replace('_MailPress_autoresponder_', '', $meta->meta_key);

		MailPress::require_class('Log');
		$this->trace = new MP_Log('autoresponder', MP_ABSPATH, 'MP_Autoresponder_' . $term_id, false, 'autoresponder');

		$this->trace->log('!' . str_repeat( '-', self::bt) . '!');
		$bm = "Batch Report autoresponder #$term_id            meta_id : $meta_id  mail_order : $mail_order";
		$this->trace->log('!' . str_repeat( ' ', 5) . $bm . str_repeat( ' ', self::bt - 5 - strlen($bm)) . '!');
		$this->trace->log('!' . str_repeat( '-', self::bt) . '!');
		$bm = " start      !";
		$this->trace->log('!' . $bm . str_repeat( ' ', self::bt - strlen($bm)) . '!');

		$this->trace->end($this->send($args));
	}

	function send($args)
	{
		extract($args);		// $meta_id, $mail_order
		$meta_id = (isset($umeta_id)) ? $umeta_id : $meta_id;

		MailPress::require_class('Usermeta');
		$meta = MP_Usermeta::get_by_id($meta_id);
		if (!$meta)
		{
			$bm = "** WARNING *! ** Unable to read table usermeta for id : $meta_id **";
			$this->trace->log('!' . $bm . str_repeat( ' ', self::bt - strlen($bm)) . '!');
			$bm = " end        ! Abort";
			$this->trace->log('!' . $bm . str_repeat( ' ', self::bt - strlen($bm)) . '!');
			$this->trace->log('!' . str_repeat( '-', self::bt) . '!');
			return false;
		}

		$mp_user_id = $meta->mp_user_id;
		$term_id 	= str_replace('_MailPress_autoresponder_', '', $meta->meta_key);
		$time		= $meta->meta_value;

		MailPress::require_class('Autoresponders');
		$autoresponder = MP_Autoresponders::get($term_id);
		if (!isset($autoresponder->description['active']))
		{
			$bm = "** WARNING *! ** Autoresponder :  $term_id is inactive **";
			$this->trace->log('!' . $bm . str_repeat( ' ', self::bt - strlen($bm)) . '!');
			$bm = " end        ! Abort";
			$this->trace->log('!' . $bm . str_repeat( ' ', self::bt - strlen($bm)) . '!');
			$this->trace->log('!' . str_repeat( '-', self::bt) . '!');
			return false;
		}

		MailPress::require_class('Users');
		$mp_user = MP_Users::get($mp_user_id);
		if (!$mp_user)
		{
			$bm = "** WARNING *! ** mp_user_id : $mp_user_id is not found **";
			$this->trace->log('!' . $bm . str_repeat( ' ', self::bt - strlen($bm)) . '!');
			$bm = " end        ! Abort";
			$this->trace->log('!' . $bm . str_repeat( ' ', self::bt - strlen($bm)) . '!');
			$this->trace->log('!' . str_repeat( '-', self::bt) . '!');
			return false;
		}

		$_mails = MP_Autoresponders::get_term_objects($term_id);
		if (!$_mails)
		{
			$bm = "** WARNING *! ** Autoresponder :  $term_id has no mails **";
			$this->trace->log('!' . $bm . str_repeat( ' ', self::bt - strlen($bm)) . '!');
			$bm = " end        ! Abort";
			$this->trace->log('!' . $bm . str_repeat( ' ', self::bt - strlen($bm)) . '!');
			$this->trace->log('!' . str_repeat( '-', self::bt) . '!');
			return false;
		}
		if (!isset($_mails[$mail_order]))
		{
			$bm = "** WARNING *! ** mail_order : $mail_order NOT in mails to be processed **";
			$this->trace->log('!' . $bm . str_repeat( ' ', self::bt - strlen($bm)) . '!');
			$bm = " end        ! Abort";
			$this->trace->log('!' . $bm . str_repeat( ' ', self::bt - strlen($bm)) . '!');
			$this->trace->log('!' . str_repeat( '-', self::bt) . '!');
			return false;
		}

		$_mail = $_mails[$mail_order];

		MailPress::require_class('Mails');
		$draft = MP_Mails::get($_mail['mail_id']);
		if (!$draft)
		{
			$bm = " processing ! mail_id : " . $_mail['mail_id'] . " NOT in mail table, skip to next mail/schedule if any";
			$this->trace->log('!' . $bm . str_repeat( ' ', self::bt - strlen($bm)) . '!');
		}

		if (!MP_Mails::send_draft($_mail['mail_id'], false, $mp_user->email, $mp_user->name))
		{
			$bm = " processing ! Sending mail_id : " . $_mail['mail_id'] . " failed, skip to next mail/schedule if any";
			$this->trace->log('!' . $bm . str_repeat( ' ', self::bt - strlen($bm)) . '!');
		}
		else
		{
			$bm = " processing ! Sending mail_id : " . $_mail['mail_id'] . " successfull ";
			$this->trace->log('!' . $bm . str_repeat( ' ', self::bt - strlen($bm)) . '!');
		}

		$mail_order++;
		if (!isset($_mails[$mail_order]))
		{
			$bm = " end        ! last mail processed";
			$this->trace->log('!' . $bm . str_repeat( ' ', self::bt - strlen($bm)) . '!');
			$this->trace->log('!' . str_repeat( '-', self::bt) . '!');
			return true;
		}

		$schedule = $this->schedule($time, $_mails[$mail_order]['schedule']);
		wp_schedule_single_event($schedule, 'mp_process_autoresponder_' . $this->id, array('args' => array('meta_id' => $meta_id, 'mail_order'=> $mail_order)));

		$bm = " end        !  next mail to be processed : $mail_order scheduled on : " . date('Y-m-d H:i:s', $schedule);
		$this->trace->log('!' . $bm . str_repeat( ' ', self::bt - strlen($bm)) . '!');
		$this->trace->log('!' . str_repeat( '-', self::bt) . '!');

		return true;
	}
}