<?php
require 'PHPMailer/PHPMailerAutoload.php';
class Notification {
	public function __construct($params = array()) {
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

		$output = array( 'status' => false, 'success' => array(), 'failure' => array(), 'log' => array());
		$result = $mail->send();
		if ($result) {
			$output['status'] = true;
		} else {
			array_push($output['log'], $mail->ErrorInfo);
		}
		return $output;
	}

	public function apple_push_notification($pem_content, $token, $payload, $use_sandbox = false) {
		$api_key_file = tempnam(sys_get_temp_dir(), 'apn-key-');
		file_put_contents($api_key_file, $pem_content);
		$result = apple_push_notification_with_api_file($api_key_file, $token, $payload, $use_sandbox);
		unlink($api_key_file);
		return $result;
	}

	public function apple_push_notification_with_api_file($api_key_file, $token, $payload, $use_sandbox = false) {
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
		$output = array( 'status' => false, 'success' => array(), 'failure' => array(), 'log' => array());
		
		$fp = false;
		foreach($token_list as $token_info) {
			if( $fp === false ) {
				$fp = stream_socket_client( 
					$use_sandbox ? 'ssl://gateway.sandbox.push.apple.com:2195' : 'ssl://gateway.push.apple.com:2195',
					$err,
					$errstr, 
					60, 
					STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, 
					$ssl_ctx
				);

				if (!is_resource($fp)) {
					array_push($output['log'], array( 'timestamp' => date('Y-m-d H:i:S'), 'log' => 
						'Connection ERROR:' . ($use_sandbox ? 'ssl://gateway.sandbox.push.apple.com:2195' : 'ssl://gateway.push.apple.com:2195'. ' , key(md5): ' . md5(file_get_contents($api_key_file)) )
					));
					break;
				}
			}
	
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
				@fclose($fp);
				$fp = false;
			}
		}
		if (is_resource($fp)) {
			@fclose($fp);
		}
		$output['status'] = count($output['failure']) == 0 && count($output['success']) == count($token);
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

		$current_timestamp = date('Y-m-d H:i:S');

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
		
		$output = array( 'status' => false, 'success' => array(), 'failure' => array(), 'log' => array() );

		$ret_obj = @json_decode($ret, true);

		array_push($output['log'], $ret);
		$output['status'] = isset($ret_obj['success']) && $ret_obj['success'] == 1;
		for( $i=0, $cnt=count($ret_obj['results']) ; $i < $cnt ; ++$i ) {
			$token_info = $token[$i];
			if (isset($ret_obj['results'][$i]['error']))
				array_push($output['failure'], array( 'timestamp' => $current_timestamp, 'token' => $token_info, 'log' => $ret_obj['results'][$i]['error'] ) );
			else
				array_push($output['success'], array( 'timestamp' => $current_timestamp, 'token' => $token_info) );
		}
		return $output;
	}

/*
MySQL: 
CREATE TABLE `notification_message` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(256) DEFAULT NULL,
  `message` text NOT NULL,
  `extra_data` text,
  `condition_data` text,
  `timestamp` timestamp NOT NULL,
  `total_receiver` int(11) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPRESSED;

CREATE TABLE `notification_sender` (
  `os_type` enum('ios','android','none') NOT NULL DEFAULT 'none',
  `app_id` enum('my.app.package.name','my.app.bundle.id','none') NOT NULL DEFAULT 'none',
  `gcm_project_info` varchar(64) DEFAULT NULL,
  `gcm_api_info` text,
  `apns_proudction_keyfile` blob,
  `apns_development_keyfile` blob,
  UNIQUE KEY `os_type` (`os_type`,`app_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPRESSED;

CREATE TABLE `notification_pool` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `mid` int(11) NOT NULL,
  `os_type` enum('ios','android','none') NOT NULL DEFAULT 'none',
  `app_id` enum('my.app.package.name','my.app.bundle.id','none') NOT NULL DEFAULT 'none',
  `token_check` varchar(64) NOT NULL DEFAULT '',
  `token` text NOT NULL,
  `mode` enum('production','development') NOT NULL DEFAULT 'production',
  `sendtime` timestamp NULL DEFAULT NULL,
  `error` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_id` (`app_id`,`mid`,`os_type`,`token_check`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

SQLite:

CREATE TABLE `notification_message` (
  `id` INTEGER AUTO_INCREMENT,
  `title` varchar(256) DEFAULT NULL,
  `message` text NOT NULL,
  `extra_data` text,
  `condition_data` text,
  `timestamp` timestamp NOT NULL,
  `total_receiver` int(11) DEFAULT '0',
  PRIMARY KEY (id)
);
CREATE TABLE `notification_pool` (
  `id` INTEGER AUTO_INCREMENT,
  `mid` int(11) NOT NULL,
  `os_type` varchar(16) NOT NULL ,
  `app_id` varchar(64) NOT NULL DEFAULT 'none',
  `token_check` varchar(64) NOT NULL DEFAULT '',
  `token` text NOT NULL,
  `mode` varchar(16) NOT NULL DEFAULT 'production',
  `sendtime` timestamp NULL DEFAULT NULL,
  `error` text,
  PRIMARY KEY (id)
);
CREATE UNIQUE INDEX app_id_key ON notification_pool(`app_id`,`mid`,`os_type`,`token_check`);
CREATE TABLE `notification_sender` (
  `os_type` varchar(16) NOT NULL DEFAULT 'none',
  `app_id` varchar(64) NOT NULL DEFAULT 'none',
  `gcm_project_info` varchar(64) DEFAULT NULL,
  `gcm_api_info` text,
  `apns_proudction_keyfile` blob,
  `apns_development_keyfile` blob
);
CREATE UNIQUE INDEX os_app_id_key ON notification_sender (`os_type`,`app_id`);
*/

	public function batch_run_via_sqlite3($db = 'test.db', $receiver_count_per_run = 15000, $memory_limit = "2048M", $exec_timeout = 0) {
		// Step 0:
		$output = array('time' => array(), 'receiver_count_per_run' => $receiver_count_per_run, 'log' => array(), 'data' => array() );
		set_time_limit($exec_timeout);
		ini_set('memory_limit', $memory_limit);

		$message_receiver = array();
		$message_id = array();
		$message_id_lookup = array();
		$message_api = array();
		$db = new SQLite3($db);

		// Step 1: Prepare Message Event
		$statement = $db->prepare('SELECT id, mid, os_type, app_id, token, mode FROM notification_pool WHERE sendtime is NULL LIMIT :limit;');
		$statement->bindValue(':limit', $receiver_count_per_run);
		$query = $statement->execute();
		while ($row = $query->fetchArray()) {
			//print_r($row);
			if (!isset($message_id_lookup[$row['mid']])) {
				$message_id_lookup[$row['mid']] = count($message_id);
				array_push($message_id, $row['mid']);
			}
			if (!isset($message_receiver[$row['mid']]))
				$message_receiver[$row['mid']] = array();
			if (!isset($message_receiver[$row['mid']][$row['os_type']]))
				$message_receiver[$row['mid']][$row['os_type']] = array();
			if (!isset($message_receiver[$row['mid']][$row['os_type']][$row['app_id']]))
				$message_receiver[$row['mid']][$row['os_type']][$row['app_id']] = array();
			if (!isset($message_receiver[$row['mid']][$row['os_type']][$row['app_id']][$row['mode']]))
				$message_receiver[$row['mid']][$row['os_type']][$row['app_id']][$row['mode']] = array();

			$message_receiver[$row['mid']][$row['os_type']][$row['app_id']][$row['mode']][$row['token']] = $row['id'];

			$flag = strtolower($row['os_type'] . '-' . $row['app_id'] . '-' . $row['mode']);
			if (!isset($message_api[$flag]))
				$message_api[$flag] = array( $row['os_type'], $row['app_id'] );
		}

		// Step 2: Prepare Message API info
		if (count($message_id) > 0) {
			$statement = $db->prepare(
				"SELECT os_type, app_id, gcm_project_info, gcm_api_info, apns_proudction_keyfile, apns_development_keyfile FROM notification_sender"
			);
			$query = $statement->execute();
			while ($row = $query->fetchArray()) {
				$flag = $row['os_type'] . '-' . $row['app_id'];
				if ($row['os_type'] == 'ios') {
					if (isset($message_api[strtolower($flag . '-production')]))
						array_push( $message_api[strtolower($flag . '-production')] , $row['apns_proudction_keyfile'] );
					else if (isset($message_api[strtolower($flag . '-development')]))
						array_push( $message_api[strtolower($flag . '-development')] , $row['apns_development_keyfile'] );
				} else if ($row['os_type'] == 'android') {
					if (isset($message_api[strtolower($flag . '-production')]))
						array_push( $message_api[strtolower($flag . '-production')] , $row['gcm_api_info'] );
					else if (isset($message_api[strtolower($flag . '-development')]))
						array_push( $message_api[strtolower($flag . '-development')] , $row['gcm_api_info'] );
				}
			}

			// prepare message info
			$statement = $db->prepare(
				"SELECT id, title, message, extra_data FROM notification_message WHERE id IN (".implode(',', $message_id).")"
			);
			$query = $statement->execute();
			while ($row = $query->fetchArray()) {
				$row['extra_data'] = !empty($row['extra_data']) ? @json_decode($row['extra_data'], true) : array();
				$message_id_lookup[$row['id']] = $row;
			}

			// Step 3: Fire 
			foreach ($message_receiver as $message_id => $level0) {
				//print_r($message_id_lookup[$message_id]);

				if (!is_array($message_id_lookup[$message_id])) {
					array_push($output['log'], "mid: $message_id not found");
					continue;
				}

				// push log
				$success_list = array();
				$error_list = array();
				$error_log = array();
				$run_log = array();

				// build Payload
				$payload = array(
					//'to' => array(),
					'registration_ids' => array(),
					'data' => array(
						'aps' => array(
							'alert' => array(
								'body' => $message_id_lookup[$message_id]['message']
							)
						)
					)
				);

				// add title
				if(isset($message_id_lookup[$message_id]['title']))
					$payload['data']['aps']['alert']['title'] = $message_id_lookup[$message_id]['title'];

				// add extract data
				if(isset($message_id_lookup[$message_id]['extra_data']) && isset($message_id_lookup[$message_id]['extra_data']['type']) && isset($message_id_lookup[$message_id]['extra_data']['data']))
					$payload['data']['aps']['alert']['action'] = $message_id_lookup[$message_id]['extra_data'];

				array_push($output['data'], $payload);

				foreach ($level0 as $os_type => $level1) {
					foreach ($level1 as $app_id => $level2) {
						foreach ($level2 as $mode => $level3) {
							$flag = strtolower("$os_type-$app_id-$mode");
							if (!isset($message_api[$flag]) || count($message_api[$flag]) <= 2 ) {
								array_push($output['log'], "message_api: $flag not found");
								continue;
							}
							$api = $message_api[$flag][2];
							if ($os_type == 'ios') {
								$api_key_file = tempnam("/tmp", "apns_key");
								file_put_contents($api_key_file, $api);
								$api = $api_key_file;
							}

							$token_pool = array();
							foreach ($level3 as $token => $id) {
								array_push($token_pool, $token);
	
								// batch mode
								if (count($token_pool) > 900) {
									if ($os_type == 'ios') {
										$response = $this->fireiOSNotification($api, $payload['data'], $token_pool, $level3);
									} else if ($os_type == 'android') {
										$response = $this->fireAndroidNotification($api, $payload, $token_pool, $level3);
									}

									$success_list = array_merge($success_list, $response['success']);
									$error_list = array_merge($error_list, $response['error']);
									$error_log = array_merge($error_log, $response['error_log']);
									$run_log = array_merge($run_log, $response['run_log']);

									//print_r($token_pool);
									$token_pool = array();
								}
							}
							// batch mode
							if (count($token_pool) > 0) {
								if ($os_type == 'ios') {
									$response = $this->fireiOSNotification($api, $payload['data'], $token_pool, $level3);
								} else if ($os_type == 'android') {
									$response = $this->fireAndroidNotification($api, $payload, $token_pool, $level3);
								}

								$success_list = array_merge($success_list, $response['success']);
								$error_list = array_merge($error_list, $response['error']);
								$error_log = array_merge($error_log, $response['error_log']);
								$run_log = array_merge($run_log, $response['run_log']);

								$token_pool = array();
							}

							$current_timestamp = date('Y-m-d H:i:s');

							// report
							while(count($success_list) > 0) {
								$target = array_splice($success_list, 0, 500);

								$statement = $db->prepare("UPDATE notification_pool SET `sendtime`=:sendtime WHERE id in ('".implode("','", $target)."')");
								$statement->bindValue(':sendtime', $current_timestamp);
								$exec_time = microtime(true);
								$query = $statement->execute();
								$exec_time = microtime(true) - $exec_time;
								array_push( $output['time'] , array( $exec_time , ' UPDAET notification_pool, items: '. count($target) ) );
							}

							if(count($error_list) > 0) {
								$update_log = array();
								for ( $i=0, $cnt =count($error_list) ; $i<$cnt ; ++$i ) {
									if (empty($error_list[$i]))
										continue;
									array_push( $update_log, 
										"(".SQLite3::escapeString($error_list[$i]).",".SQLite3::escapeString($error_log[$i]).",".SQLite3::escapeString($current_timestamp).")"
									);
								}
								while (count($update_log) > 0 ) {
									$target = array_splice($update_log, 0, 200);
									$statement = $db->prepare('INSERT INTO `notification_pool` (id,error,sendtime) VALUES '.implode(',', $target).' ON DUPLICATE KEY UPDATE error=VALUES(error), sendtime=VALUES(sendtime) ');

									$exec_time = microtime(true);
									$query = $statement->execute();
									$exec_time = microtime(true) - $exec_time;
									array_push( $output['time'] , array($exec_time, 'UPDATE notification_pool via INSERT/DUPLICATE KEY UPDATE, items: '.count($target)) );
								}
							}
						}
					}
				}
			}

		}
		// Step 4: Update log
	}
}
