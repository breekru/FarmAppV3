<?php
// includes/secrets.example.php — Committed to git as a template.
// Copy this file to secrets.php and fill in your real credentials.

// Database
define('DB_HOST',    'localhost');
define('DB_NAME',    'your_database_name');
define('DB_USER',    'your_db_user');
define('DB_PASS',    'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// Mail (SMTP)
define('MAIL_HOST',      'mail.yourdomain.com');
define('MAIL_USER',      'no_reply@yourdomain.com');
define('MAIL_PASS',      'your_email_password');
define('MAIL_FROM',      'no_reply@yourdomain.com');
define('MAIL_FROM_NAME', 'FarmApp');
define('MAIL_PORT',      465);
define('MAIL_SECURE',    'ssl');

// Application base URL (no trailing slash)
define('APP_URL', 'https://yourdomain.com');

// JWT signing secret — generate with: php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
// Must be long, random, and never committed. Changing it logs everyone out.
define('JWT_SECRET', 'replace_with_64_char_hex_string');
