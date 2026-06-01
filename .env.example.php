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
define('FIREBASE_API_KEY', 'your-firebase-api-key');
define('FIREBASE_AUTH_DOMAIN', 'your-project.firebaseapp.com');
define('FIREBASE_PROJECT_ID', 'your-project-id');
define('FIREBASE_APP_ID', 'your-firebase-app-id');
define('FIREBASE_STORAGE_BUCKET', 'your-project.firebasestorage.app');
define('FIREBASE_MESSAGING_SENDER_ID', 'your-sender-id');
