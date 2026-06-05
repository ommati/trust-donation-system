# Clean URLs Setup - Nitya Seva Admin Panel

## Overview
The application now uses clean URLs without `.php` extensions. All internal links are clean and user-friendly.

**Examples:**
- `iskconpratappur.org/trust-donation-system/nitya-seva-members` (not `/nitya-seva-members.php`)
- `localhost/admin-panel/login` (not `/login.php`)
- Root URL redirects to Nitya Seva members page

## How It Works

### 1. URL Rewriting (.htaccess)
The `.htaccess` file in the root directory handles all URL rewriting:
- Strips `.php` extensions from URLs
- Preserves query strings
- Redirects root (`/`) to the home page
- Protects sensitive directories

### 2. URL Helper Functions (functions.php)
- `url($path)` - Generates clean URLs without `.php` extension
  - Example: `url('nitya-seva-members?id=5')` → `/admin-panel/nitya-seva-members?id=5`
- `redirect($path)` - Redirects to clean URLs
- `basePath()` - Automatically detects installation path

## Installation & Configuration

### Local Testing (XAMPP)

**Step 1: Enable mod_rewrite in Apache**

1. Open `C:\xampp\apache\conf\httpd.conf`
2. Find the line: `#LoadModule rewrite_module modules/mod_rewrite.so`
3. Remove the `#` to uncomment it:
   ```
   LoadModule rewrite_module modules/mod_rewrite.so
   ```
4. Save and restart Apache

**Step 2: Verify .htaccess Configuration**

The `.htaccess` file has `RewriteBase /admin-panel/`. Adjust if needed:
- **If installed at root:** Change to `RewriteBase /`
- **If in subdirectory:** Change to `RewriteBase /your-subdirectory/`

**Step 3: Test**

1. Start XAMPP
2. Visit `http://localhost/admin-panel/` (or your configured path)
3. You should be redirected to the login or nitya-seva-members page
4. All URLs should NOT show `.php` extension

### Production Deployment (cPanel/Live Server)

1. Upload the entire application to your hosting (e.g., `public_html/trust-donation-system/`)
2. Update `.htaccess` line 8 with your base path:
   - Root installation: `RewriteBase /`
   - Subdirectory: `RewriteBase /trust-donation-system/`
3. No additional setup needed if mod_rewrite is enabled (default on most hosts)

## URL Examples

| Clean URL | Actual File | Example |
|-----------|------------|---------|
| `/nitya-seva-members` | `nitya-seva-members.php` | View all members |
| `/login` | `login.php` | Login page |
| `/nitya-seva-view-member` | `nitya-seva-view-member.php?id=1` | View member details |
| `/nitya-seva-add-member` | `nitya-seva-add-member.php` | Add new member |
| `/nitya-seva-payments` | `nitya-seva-payments.php` | View payments |
| `/nitya-seva-sync` | `nitya-seva-sync.php` | Sync with Google Sheets |
| `/dashboard` | `dashboard.php` | Admin dashboard |
| `/donations` | `donations.php` | View donations |

## Important Notes

### URL Generation
Always use the `url()` helper function in templates and code:
```php
// Correct ✅
<a href="<?php echo url('nitya-seva-members'); ?>">Members</a>

// Wrong ❌
<a href="nitya-seva-members.php">Members</a>
```

### Query Parameters
Pass query parameters as part of the path:
```php
// Correct ✅
echo url('nitya-seva-view-member?id=' . $id);
echo url('donations?page=2&sort=date');

// Result: /admin-panel/nitya-seva-view-member?id=5
```

### Root Homepage Behavior
When visiting the root URL (`/admin-panel/` or `/`):
- **If logged in:** Redirects to `/nitya-seva-members`
- **If not logged in:** Redirects to `/login`

## Troubleshooting

### URLs Still Show `.php`
**Problem:** URLs in browser still show `.php` extension
**Solution:** 
1. Verify `LoadModule rewrite_module` is uncommented in `httpd.conf`
2. Check `.htaccess` is in the root directory with correct `RewriteBase`
3. Restart Apache after making changes

### 404 Errors on Clean URLs
**Problem:** Getting "Page not found" on clean URLs
**Solution:**
1. Check `.htaccess` `RewriteBase` matches your installation path
2. Verify `.php` files exist (e.g., `nitya-seva-members.php` exists)
3. Check `.htaccess` syntax is correct

### Links Not Working
**Problem:** Links in the application are broken
**Solution:**
1. Verify all links use `url()` helper function
2. Check `basePath()` is correctly detected
3. Check browser console for actual URL being requested

### mod_rewrite Not Available
**Problem:** "mod_rewrite not enabled" error
**Solution:**
1. Enable mod_rewrite in Apache configuration (see Installation section)
2. Ask hosting provider to enable mod_rewrite if on shared hosting
3. Use alternative: Enable AllowOverride All in Apache VirtualHost

## Security Implications

✅ **Protected:**
- `/includes/` directory blocked at .htaccess level
- `/lib/` directory blocked at .htaccess level
- `.env` files cannot be accessed
- `composer.json` and `.sql` files cannot be accessed

✅ **Additional Protection:**
- CSRF tokens required on all forms
- Password hashing with bcrypt
- Secure session cookies
- SQL injection prevention via PDO prepared statements
- XSS prevention via output escaping

## Performance

The `.htaccess` includes optimizations:
- **Gzip compression** for HTML, CSS, JavaScript, JSON
- **Cache headers** for images (30 days) and scripts (7 days)
- **Efficient rewrite rules** that don't impact performance

## Reverting to .php URLs

If you need to go back to explicit `.php` URLs:

1. Edit `includes/functions.php`
2. In the `url()` function, add `.php` extension back:
```php
if ($path !== '' && !preg_match('/\.[a-z0-9]+$/i', $path)) {
    $path .= '.php';
}
```
3. Update `.htaccess` to disable rewrite rules
4. Update all URL references in templates and code

## Files Changed

- `.htaccess` - Complete rewrite configuration
- `includes/functions.php` - url() function updated to remove .php appending
- `includes/.htaccess` - Created for directory protection
- `index.php` - Updated redirect logic
