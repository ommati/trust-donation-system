# Google Sheets Monthly Payment Status Sync

## Overview
The system now includes a **Monthly Payment Status** sheet that automatically tracks payment status for all members across months from January 2026 to the current month.

## Sheet Structure

### Sheet Name: `Nitya Seva Monthly Status`

**Columns:**
- **Column A**: Member ID (NS-00001, etc.)
- **Column B**: Member Name
- **Column C onwards**: Monthly status columns
  - Jan 2026, Feb 2026, Mar 2026, Apr 2026, May 2026, Jun 2026, etc.

**Status Values per Month:**
- **Paid** - Member has paid for that month
- **Due** - Member owes payment for that month
- **NIL** - Month is before member's Seva start date

## Configuration

### Required .env.php Settings

```php
// For Monthly Status Sheet (optional, uses default if not defined)
define('GSHEET_NITYA_MONTHLY_STATUS_SHEET_NAME', 'Nitya Seva Monthly Status');
```

This is already configured if you have:
```php
define('GSHEET_CREDENTIALS_PATH_NITYASEVA', 'credentials/excel sheet updater.json');
define('GSHEET_SPREADSHEET_ID_NITYASEVA', '1wpPU9etbrjSpGYCWt2hnS5SzpaNwDsrxx1AxDBXsHc4');
```

### Google Sheets Setup

1. **Create the third sheet** in your Google Sheet:
   - Name: `Nitya Seva Monthly Status`
   - Leave it empty (headers will be auto-generated)

2. **Ensure service account has access**:
   - Already done if shared at spreadsheet level

## How to Trigger Sync

### Option 1: Via Admin Panel
1. Go to **Nitya Seva** → **Sync**
2. Click **"Sync Monthly Status"** button
3. The sheet will be automatically populated with all members and their payment status

### Option 2: Via API/Cron Job
```php
require_once __DIR__ . '/includes/nitya_seva_functions.php';
require_once __DIR__ . '/includes/db.php';

$result = syncNityaSevaMonthlyStatus($pdo);
if ($result['ok']) {
    echo $result['message']; // "Monthly status sheet synced with X member records"
} else {
    echo "Error: " . $result['message'];
}
```

## Example Output

| Member ID | Name | Jan 2026 | Feb 2026 | Mar 2026 | Apr 2026 | May 2026 | Jun 2026 |
|-----------|------|----------|----------|----------|----------|----------|----------|
| NS-00001  | Achutya | Paid | Paid | Paid | Paid | Paid | Due |
| NS-00002  | Radha | NIL | Paid | Paid | Paid | Paid | Paid |
| NS-00003  | New Member | NIL | NIL | Paid | Paid | Paid | Due |

## How It Works

1. **Month Range**: Automatically generates from Jan 2026 to current month
2. **Member Filtering**: Only syncs active members
3. **Seva Start Logic**: 
   - If member's Seva start date is AFTER a month, that month shows "NIL"
   - If member's Seva start date is BEFORE/ON a month, shows actual status (Paid/Due)
4. **Payment Detection**: Uses the same payment allocation logic as the member view
   - Payments are allocated chronologically to months
   - If full monthly amount is paid, month shows "Paid"
   - Otherwise shows "Due"

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Sheet shows 0 records | Check if any members exist in database |
| "Google Sheets is not configured" | Verify GSHEET_CREDENTIALS_PATH_NITYASEVA and GSHEET_SPREADSHEET_ID_NITYASEVA in .env.php |
| Sheet not created | Create the sheet manually in Google Sheets, or rename the sheet to match GSHEET_NITYA_MONTHLY_STATUS_SHEET_NAME |
| "NIL" appears for active months | Likely Seva start date is in the future; verify member's Seva start date |

## Notes

- Monthly Status sheet is synced **independently** from member/payment syncs
- Does not interfere with automatic member/payment sync process
- Can be re-synced anytime without data loss (replaces entire sheet)
- Uses the same Nitya Seva service account credentials as members/payments sheets
