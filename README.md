# Basic Usage

```
<?php

require 'Notification.php';

$obj = new Notification;

// func 1: send mail
$result = $obj->mail( 
	array(
		'smtp' => array(
			'host' => 'smtp.gmail.com',
			'port' => 587,
			'secure' => 'tls',
			//'debug' => 1,
			'auth' => array(
				'username' => 'account@gmail.com',
				'password' => 'password@gmail.com',
			),
		),
		'sender' => array(
			'name' => 'DisplayName',
			'email' => 'account@gmail.com'
		)
	), 
	'Mail Subject', 
	'Mail Content', 
	array( 
		'to' => 'receiver@gmail.com'
	) 
);
if ($result !== true) {
	echo "[ERROR] EMAIL";
	print_r($result);
}


// func 2: iOS app - Apple Push notification
$result = $obj->apple_push_notification(
	file_get_contents('/path/ios-apn-key'),
	'iOS-Device-APN-Token'
	array(
		// apn payload format
		'aps' => array(
			'alert' => array(
				'title' => $title,
				'body' => $message,
			)
		)
	)
);
if ($result !== true) {
	echo "[ERROR] APN";
	print_r($result);
}

// func 3: android app - Google Cloud Messaging
$result = $obj->google_cloud_messaging(
	'GCM_API_KEY',
	'Android-Device-GCM-Token',
	array(
		// With your payload format
		'data' => array(
			'title' => 'Hello',
			'message' => 'World'
		)
	)
);
if ($result !== true) {
	echo "[ERROR] GCM";
	print_r($result);
}

```

# CodeIgniter Usage

## Info

CodeIgniter 3.x

## Install

```
$ cd /path/project/application/library
$ git clone --recursive https://github.com/changyy/codeigniter-library-notification
```

## Example

```
<?php
	$this->load->library('codeigniter-library-notification/notification');

	// func 1: send mail
	$this->notification->mail();

	// func 2: iOS app - Apple Push notification
	$this->notification->apple_push_notification();

	// func 3: android app - Google Cloud Messaging
	$this->notification->google_cloud_messaging();

```

# Dependence

- Use PHPMailer v5.2.14 / 1 Nov 2015

https://github.com/PHPMailer/PHPMailer/commit/e774bc9152de85547336e22b8926189e582ece95

```
$ git submodule add https://github.com/PHPMailer/PHPMailer
$ cd PHPMailer
$ git reset --hard e774bc9152de85547336e22b8926189e582ece95
$ cd -
$ git commit -am 'set PHPMailer version to v5.2.14 / https://github.com/PHPMailer/PHPMailer/commit/e774bc9152de85547336e22b8926189e582ece95'
```

# GMail Notes

- Q: SMTP ERROR: Password command failed / SMTP Error: Could not authenticate.

  A: https://www.google.com/settings/security/lesssecureapps

