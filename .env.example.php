<?php
// Copy this file to .env.php and customize values for your environment.

define('DB_HOST', 'localhost');
define('DB_NAME', 'trust_donation');
define('DB_USER', 'root');
define('DB_PASS', '');

define('SITE_TITLE', 'Trust Donation Management System');
define('TRUST_NAME', 'Sri Sri Radha Madhav Naamhatta Sangha Charitable & Welfare Trust');
define('TRUST_REGISTRATION', 'Registration No. 05010010423');
define('TRUST_ADDRESS', 'Pratappur Krishnabati, Panihati, West Bengal - 711410');
define('TRUST_CONTACT', '[Trust contact number]');
define('AUTHORIZED_SIGNATORY', 'Om Jagannath Das');
define('TRUST_SIGNATURE', 'images/signature2.jpg');

define('OTP_ALLOWED_PHONES', serialize(['7506316144', '8583819442', '7003538078']));
define('SMS_OTP_DEBUG', false);
define('SMS_GATEWAY_URL', 'https://sms-provider.example/send?to={phone}&message={message}');
define('OTP_EXPIRY_MINUTES', 10);
define('OTP_RESEND_SECONDS', 60);
define('OTP_MAX_ATTEMPTS', 5);
