# Trust Donation Management System

## Overview
A mobile-friendly admin panel for a religious trust to record donations, manage donor receipts, and generate PDF donation receipts.

## Included Features
- Firebase phone OTP login with session authentication
- Dashboard with total donations, unique donors, and recent entries
- Add donation form with auto-generated receipt number
- Donation listing with search, date filter, view, edit, and delete
- Printable receipt page and downloadable PDF receipts
- Bootstrap 5 responsive UI and secure PDO-based database access

## Installation
1. Place the `admin-panel` folder inside your web server root (for example `c:\xampp\htdocs\admin-panel`).
2. Create the database using `database.sql`:
   - Open phpMyAdmin or command line
   - Run the SQL script in `database.sql`
3. Copy `.env.example.php` to `.env.php` and override any environment values if needed.
4. Update database credentials if needed in `includes/config.php` or `.env.php`.
5. Open `http://localhost/admin-panel/` in your browser.

## Phone OTP Login
The app has no public registration. Firebase sends and verifies phone OTPs in the browser; PHP verifies the Firebase ID token server-side and only allows phone numbers configured in `OTP_ALLOWED_PHONES`.

- Create a local `.env.php` file from `.env.example.php` and **do not commit** it to GitHub. The application will automatically load `.env.php` if present.
- Configure Firebase phone authentication and add your production domain in Firebase authorized domains.
- Keep your signature file local: `images/signature2.jpg` is used for PDF receipts.
- Ensure `includes/config.php` or your `.env.php` contains correct database host, name, user, and password.
- The repository contains a `.gitignore` that ignores `.env.php` and local signature files by default.
- When using HTTPS, the system now detects SSL and sets session cookies as secure.
- Keep `includes` and `lib` folders secure. `.htaccess` files are included in these folders to block direct web access.
- For shared hosting, make sure `includes/.htaccess` and `lib/.htaccess` are respected by Apache.

## File Structure
- `index.php` — redirect to login/dashboard
- `login.php`, `verify-otp.php`, `logout.php` — authentication
- `dashboard.php` — admin overview
- `add-donation.php`, `donations.php`, `edit-donation.php`, `view-donation.php`, `delete-donation.php` — donation CRUD
- `receipt.php`, `download-pdf.php` — receipt generation
- `includes/` — config, database connection, auth, templates
- `lib/` — minimal PDF generation library
- `assets/` — CSS and JS resources

## Security
- Uses prepared statements
- Sanitizes output with `htmlspecialchars`
- Uses CSRF tokens for forms
- Session security with `session_regenerate_id`
- Verifies Firebase phone login tokens server-side before creating an admin session
