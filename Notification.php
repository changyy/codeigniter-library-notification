<?php
require 'PHPMailer/PHPMailerAutoload.php';
class Notification {
	public function __construct($params) {
	}
	public function __destruct() {
	}
	public function mail($mail_options = array(), $subject, $message, $receiver = array()) {
		$mail = new PHPMailer;
		if (isset($mail_options['smtp']) && is_array($mail_options['smtp'])) {
			if (isset($mail_options['smtp']['debug']))
				$mail->SMTPDebug = $mail_options['smtp']['debug'];
			$mail->isSMTP();
			if (isset($mail_options['smtp']['host']))
				$mail->Host = $mail_options['smtp']['host'];
			if (isset($mail_options['smtp']['auth']) && isset($mail_options['smtp']['auth']['username']) && isset($mail_options['smtp']['auth']['password']) ) {
				$mail->SMTPAuth = true;
				$mail->Username = $mail_options['smtp']['auth']['username'];
				$mail->Password = $mail_options['smtp']['auth']['password'];
			}
			if (isset($mail_options['smtp']['secure']))
				$mail->SMTPSecure = $mail_options['smtp']['secure'];
			if (isset($mail_options['smtp']['port']))
				$mail->Port = $mail_options['smtp']['port'];
		}
		if (isset($mail_options['sender'])) {
			if (isset($mail_options['sender']['name']))
				$mail->FromName = $mail_options['sender']['name'];
			if (isset($mail_options['sender']['email']))
				$mail->From = $mail_options['sender']['email'];
		}
		if (isset($mail_options['format'])) {
			$mail->isHTML(strcasecmp($mail_options['format'], 'html') == 0);
		}
		if (isset($mail_options['charset']))
			$mail->CharSet = $mail_options['charset'];
		else
			$mail->CharSet = 'UTF-8';

		$mail->Subject = $subject;
		$mail->Body = $message;

		if (isset($receiver['to'])) {
			if (is_array($receiver['to'])) {
				foreach($receiver['to'] as $to) {
					$mail->addAddress($to);
				}
			} else if (!empty($receiver['to'])) {
				$mail->addAddress($receiver['to']);
			}
		}

		if (isset($receiver['cc'])) {
			if (is_array($receiver['cc'])) {
				foreach($receiver['cc'] as $cc) {
					$mail->addCC($cc);
				}
			} else if (!empty($receiver['cc'])) {
				$mail->addCC($receiver['cc']);
			}
		}

		if (isset($receiver['bcc'])) {
			if (is_array($receiver['bcc'])) {
				foreach($receiver['bcc'] as $bcc) {
					$mail->addBCC($bcc);
				}
			} else if (!empty($receiver['bcc'])) {
				$mail->addBCC($receiver['bcc']);
			}
		}

		$result = $mail->send();
		if ($result)
			return true;
		return $mail->ErrorInfo;
	}

	public function apple_push_notification($pem_content, $token, $payload, $use_sansbox = false) {
		$api_key_file = tempnam(sys_get_temp_dir(), 'apn-key-');
		file_put_contents($api_key_file, $pem_content);
		$result = apple_push_notification_with_api_file($api_key_file, $token, $payload, $use_sansbox);
		unlink($api_key_file);
		return $result;
	}

	public function apple_push_notification_with_api_file($api_key_file, $token, $payload, $use_sansbox = false) {
		//$payload = array(
		//	'aps' => array(
		//		'alert' => array(
		//			'title' => $title,
		//			'body' => $message,
		//		)
		//	)
		//);
		$raw_cmd = json_encode($payload);
		$raw_data = pack('n', strlen($raw_cmd)) . $raw_cmd;

		$ssl_ctx = stream_context_create();
		stream_context_set_option($ssl_ctx, 'ssl', 'local_cert', $api_key_file);

		$token_list = is_array($token) ? $token : array($token);
		$output = array( 'success' => array(), 'failure' => array() );
		
		foreach($token_list as $token_info) {
			if( !is_resource( $fp = stream_socket_client( 
				$use_sansbox ? 'ssl://gateway.sandbox.push.apple.com:2195' : 'ssl://gateway.push.apple.com:2195',
				$err,
				$errstr, 
				60, 
				STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, 
				$ssl_ctx
			) ) ) {
				array_push($output['failure'], array( 'timestamp' => date('Y-m-d H:i:S'), 'token' => $token_info, 'log' => 'Connection ERROR') );
				break;
			}
	
			if (is_resource($fp)) {
				$packed_data = 
					// Command (Simple Notification Format)
					chr(0)
					// device token
					. pack('n', 32) . pack('H*', $token_info)
					// payload
					//. pack('n', strlen($raw_cmd)) . $raw_cmd;
					. $raw_data;
		
				if( @fwrite($fp, $packed_data, strlen($packed_data)) !== false ) {
					array_push($output['success'], array( 'timestamp' => date('Y-m-d H:i:S'), 'token' => $token_info) );
				} else {
					array_push($output['failure'], array( 'timestamp' => date('Y-m-d H:i:S'), 'token' => $token_info, 'log' => 'Socket Write Error') );

				}
			}
		}
		if (is_resource($fp)) {
			@fclose($fp);
		}
		if (count($output['failure']) == 0)
			return true;
		return $output;
	}

	public function google_cloud_messaging($api_key, $token, $payload, $debug = false) {
		//$payload = array(
		//	'registration_ids' => array(),
		//	'data' => array(
		//		'aps' => array(
		//			'alert' => array(
		//				'title' => $title,
		//				'body' => $message,
		//			)
		//		)
		//	)
		//);
		if (!is_array($payload))
			$payload = array();
		if (!isset($payload['registration_ids']))
			$payload = array ( 'registration_ids' => array(), 'data' => $payload);
		if (!is_array($token))
			$token = array($token);
		$payload['registration_ids'] = $token;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://android.googleapis.com/gcm/send");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Authorization: key=".$api_key,
			'Content-Type: application/json'
		));
		curl_setopt($ch, CURLOPT_POST , true );
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if ($debug)
			curl_setopt($ch, CURLOPT_VERBOSE, true);
		$ret = curl_exec($ch);
		curl_close($ch);
		
		$ret_obj = @json_decode($ret);
		if (isset($ret_obj->success) && $ret_obj->success == 1)
			return true;
		return $ret;
	}
}
