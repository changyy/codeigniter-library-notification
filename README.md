# Basic Usage

```
<?php

	$obj = new Notification;

	// func 1: send mail
	$obj->mail();

	// func 2: iOS app - Apple Push notification
	$obj->apple_push_notification();

	// func 3: android app - Google Cloud Messaging
	$obj->google_cloud_messaging();
```

# CodeIgniter Usage

## Info

CodeIgniter 3.x

## Install

```
$ cd /path/project/application/library
$ git clone --recursive https://github.com/changyy/codeigniter-library-notification
```

## Exmaple

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
