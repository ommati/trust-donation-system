# Trust Donation Management System

## Overview
A mobile-friendly admin panel for a religious trust to record donations, manage donor receipts, and generate PDF donation receipts.

## Included Features
- Single verified admin login with "remember me" device persistence
- Dashboard with total donations, unique donors, and recent entries
- Add donation form with auto-generated receipt number
- Donation listing with search, date filter, view, edit, and delete
- Printable receipt page and downloadable PDF receipts
- Bootstrap 5 responsive UI and secure PDO-based database access

## Installation
1. Place the `admin-panel` folder inside your web server root, for example `c:\xampp\htdocs\admin-panel`.
2. Create the database using `database.sql` in phpMyAdmin or from the command line.
3. Copy `.env.example.php` to `.env.php` and customize the database values.
4. Open `http://localhost/admin-panel/` in your browser.

## Admin Login
The app has no public registration. Access is limited to one configured administrator account.

- User ID is configured with `ADMIN_LOGIN_USER`.
- Verified email is configured with `ADMIN_LOGIN_EMAIL`.
- Password is stored with `ADMIN_PASSWORD_HASH`.
- Generate a new password hash with `php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"`.
- The "remember me" option stores a secure random token in a browser cookie and stores only the token hash in the database.

## Production Notes
- Create a local `.env.php` file from `.env.example.php` and do not commit it to GitHub.
- Keep `images/signature2.jpg` available for PDF receipts.
- Ensure `includes/config.php` or `.env.php` contains correct database host, name, user, and password.
- When using HTTPS, the system detects SSL and sets session cookies as secure.
- Keep `includes` and `lib` folders secure. `.htaccess` files are included in these folders to block direct web access.

## File Structure
- `index.php` - redirect to login/dashboard
- `login.php`, `logout.php` - authentication
- `dashboard.php` - admin overview
- `add-donation.php`, `donations.php`, `edit-donation.php`, `view-donation.php`, `delete-donation.php` - donation CRUD
- `receipt.php`, `download-pdf.php` - receipt generation
- `includes/` - config, database connection, auth, templates
- `lib/` - PDF support files
- `assets/` - CSS and JS resources

## Security
- Uses prepared statements
- Sanitizes output with `htmlspecialchars`
- Uses CSRF tokens for forms
- Session security with `session_regenerate_id`
- Stores passwords with `password_hash`
- Stores remember-me tokens as hashes, not plaintext passwords
