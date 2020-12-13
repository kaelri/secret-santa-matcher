<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class App {

	public static $app_path     = '';
	public static $config       = [];
	public static $people       = [];
	public static $all_assigned = false;

	public static function run( $app_path, array $args = [] ) {

		echo PHP_EOL;

		self::$app_path = $app_path;

		self::load_config();

		$script = isset( $args[1] ) ? $args[1] : null;

		switch ( $script ) {
			case 'build':
				self::build();
				break;
			case 'test':
				self::send( false );
				break;
			case 'send':
				self::send( true );
				break;
			default:
				echo 'Please provide a command, e.g. `php run.php build`.' . PHP_EOL;
				break;
		}

		echo PHP_EOL . 'Done.' . PHP_EOL;

	}

	public static function build() {

		shuffle( self::$people );

		// Fill out default values for each person.
		foreach ( self::$people as $p => $person ) {

			self::$people[$p]['html']      = '';
			self::$people[$p]['recipient'] = false;
			self::$people[$p]['assigned']  = false;

			if ( !isset($person['include']) ) {
				self::$people[$p]['include'] = [];
			}

			if ( !isset($person['exclude']) ) {
				self::$people[$p]['exclude'] = [];
			}
			
		}

		while ( !self::$all_assigned ) {
			self::do_assignments();
		}

		self::save_matches();
		self::show_matches();

	}

	public static function send( bool $live = false ) {

		self::load_matches();
		self::show_matches();

		self::generate_html();
		self::save_email();

		if ( $live ) {
			self::send_email();
		}

	}

	public static function do_assignments() {

		// Add everyone to a list of unassigned people.
		$unassigned = [];

		foreach ( self::$people as $p => $person ) {

			$unassigned[] = $p;

		}

		// Assign everyone.
		foreach ( self::$people as $p => $person ) {

			// Shuffle the deck.
			shuffle( $unassigned );

			foreach ( $unassigned as $u => $r ) {

				$random = self::$people[ $r ];

				// Skip yourself.
				if ( $r == $p ) continue;

				// Skip if we have preferred correspondents & this person isn’t one of them.
				if ( !empty($person['include']) && !in_array( $random['name'], $person['include'] ) ) continue;
				if ( !empty($person['exclude']) &&  in_array( $random['name'], $person['exclude'] ) ) continue;

				// Skip if *they* have preferred & *we* aren’t one of them.
				if ( !empty($random['include']) && !in_array( $person['name'], $random['include'] ) ) continue;
				if ( !empty($random['exclude']) &&  in_array( $person['name'], $random['include'] ) ) continue;

				$person['recipient'] = $r;

				// Remove the match from the unassigned list.
				array_splice( $unassigned, $u, 1 );
				break;

			}

			// If we've run out of matching recipients, start over.
			if ( $person['recipient'] === false ) {
				return;
			}

			// Save match.
			self::$people[ $p ][ 'recipient' ] = $r;
			self::$people[ $r ][ 'assigned'  ] = $p;

		}

		self::$all_assigned = true;

		return;
		
	}

	public static function generate_html() {

		foreach ( self::$people as $p => $person ) {

			$r         = $person['recipient'];
			$recipient = self::$people[ $r ];

			ob_start(); ?><p>Hi family!</p>

			<p>Time for Secret Santa! You’ve received <b><?=$recipient['name']?></b> as your person.</p>
			
			<p>Each person will be the Secret Santa for another! You will send one gift ($10-$15, or something homemade) to your person. We will all get together on zoom one day around Christmas to open our gifts and have a  festive time of merriment! </p>
			
			<p><a href="<?=self::$config['links']['doodle']?>">Please fill out this Doodle</a> so we can start to narrow down the day/time we will be able to meet!</p>
			
			<p>Thanks for participating in this, I know we all want to be together and this will help fill that gap we are all feeling right now! </p>
			
			<p>If your person resides outside of the country you live in, I highly suggest checking out Etsy! You can specifically pick the country/area where you want to ship from/to. Also, this will help support small businesses during this difficult time! www.etsy.com</p>
			
			<p>You will find everyone’s contact info <a href="<?=self::$config['links']['spreadsheet']?>">in this Google Sheet</a>. (Please update your information if you need to!)</p>
			
			<p>Love you all!</p>
			
			<p>Caroline & Michael</p><?php

			$html = ob_get_contents(); ob_end_clean();

			self::$people[ $p ][ 'html' ] = $html;

		}

	}

	public static function save_matches() {

		$path    = sprintf( '%s/data/matches.json', self::$app_path );
		$content = json_encode ( self::$people );
		file_put_contents( $path, $content );

	}

	public static function load_config() {

		$path = sprintf( '%s/data/people.json', self::$app_path );
		$json = file_get_contents( $path );

		self::$config = json_decode( $json, true );
		self::$people = self::$config['people'];

	}

	public static function load_matches() {

		$path    = sprintf( '%s/data/matches.json', self::$app_path );
		$json    = file_get_contents( $path );
		self::$people = json_decode( $json, true );

	}

	public static function show_matches() {

		$min_length = 0;

		foreach ( self::$people as $p => $person ) {
			$min_length = max( strlen($person['name']), $min_length );
		}

		foreach ( self::$people as $p => $person ) {

			$r         = $person['recipient'];
			$recipient = self::$people[ $r ];

			echo str_pad( $person['name'], $min_length ) . ' -> ' . $recipient['name'] . PHP_EOL;

		}

	}

	public static function save_email() {

		foreach ( self::$people as $p => $person ) {

			$path    = sprintf( '%s/tests/%s.html', self::$app_path, $person['name'] );
			$content = $person['html'];

			ob_start();
			
			?><html>
				<head></head>
				<body><?=$person['html']?></body>
			</html><?php
			
			$content = ob_get_contents(); ob_end_clean();

			file_put_contents( $path, $content );

		}

	}

	public static function send_email() {

		echo PHP_EOL . 'Sending emails for real!' . PHP_EOL;

		foreach ( self::$people as $p => $person ) {

			$mail = new PHPMailer( true );
			$mail->SMTPDebug = 2;

			// Authentication
			$mail->isSMTP();
			$mail->SMTPAuth   = true; 
			$mail->SMTPSecure = 'tls';
			$mail->Host       = self::$config['smtp']['host'];
			$mail->Port       = self::$config['smtp']['port'];
			$mail->Username   = self::$config['smtp']['username'];
			$mail->Password   = self::$config['smtp']['password'];
			
			// From
			$mail->From       = self::$config['smtp']['from'];
			$mail->FromName   = self::$config['smtp']['name'];

			// To
			$mail->addAddress( $person['email'] );
			$mail->addReplyTo( self::$config['smtp']['reply'], 'Reply' );
			$mail->addBCC( self::$config['smtp']['bcc'] );

			$mail->isHTML(true);
			$mail->Subject = sprintf( '%s: Your Secret Santa Recipient', $person['name'] );

			ob_start();
			
			?><html>
				<head></head>
				<body><?=$person['html']?></body>
			</html><?php
			
			$mail->Body = ob_get_contents(); ob_end_clean();

			try {
				$mail->send();
				echo 'Message has been sent successfully'.PHP_EOL;
			} catch (Exception $e) {
				echo 'PHPMailer error: ' . $mail->ErrorInfo.PHP_EOL;
			}

		}
		
	}

}
