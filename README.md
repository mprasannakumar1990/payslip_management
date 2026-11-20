# Payslip Management (Laravel 12 + MySQL)

Web application for **Employee registration, Leave, OT and Payslip management**, built from the three workbooks in [`docs/sheets/`](docs/sheets):

| Workbook | What the app takes from it |
|---|---|
| `Salary Workings Nov 2025.xls` | Salary register layout, payslip (Form T) layout, every salary formula, employee master and allowances |
| `OT Sheet April 2024 to March 2025.xls` | Monthly OT & shift-hours sheet grouped by plant (HMI, NISSAN, ENFIELD, SEOYON, PRODUCTION, OTHERS) with plant summary |
| `El Calculation 2025.xlsx` | Yearly leave register, EL accrual, EL encashment + gratuity final settlement |

## Logins

Only two roles. There is no employee login.

| Role | Can do | Default account |
|---|---|---|
| **Super Admin** | Everything, plus admin accounts, settings (rates, leave/allowance types, plants), activity log, re-open / delete finalized payroll | `superadmin@payslip.local` / `Admin@123` |
| **Admin** | Employee registration, leave, OT, payroll & payslips, settlements | `admin@payslip.local` / `Admin@123` |

**Change both passwords after the first login** (My Profile → Change password).

## Setup (XAMPP)

```bash
composer install --ignore-platform-req=ext-gd   # gd is only needed for images; Excel import works without it
cp .env.example .env            # then set DB_* (database: payslip_management) and APP_URL
php artisan key:generate
php artisan migrate --seed      # creates tables, the two accounts and imports the Excel data
```

Open **http://localhost/payslip_management/** (it redirects to `/public`). Alternatively run `php artisan serve` and open http://127.0.0.1:8000.

`php artisan test` runs the formula and access tests on an in-memory SQLite database and does not touch MySQL.

### Seed data
`database/seeders/ExcelDataSeeder.php` loads the JSON files in `database/seeders/data/` (extracted from the workbooks): 36 employees with salary structure and allowances, November 2025 OT hours, monthly leave usage 2021–2025 and EL credited per year 2009–2020. A **draft November 2025 payroll** is generated so you can compare it with the Excel sheet.

## Modules

**Employee Registration**: employee master (ID, designation, grade, plant, DOJ/exit, EPF/UAN, ESIC, PAN, bank), salary structure (basic, manager/factory special, monthly TAX, OT multiplier, PF/ESI/OT/attendance-bonus flags), fixed allowances. Export to CSV.

**Leave Management**
- Leave requests with approval workflow (pending → approved / rejected / cancelled). Days are auto-counted excluding the weekly off; half-day supported; overlap check.
- *Monthly Leave Entry*: record a month's leave count without dates, the same way the Excel register works.
- Leave balances per type (EL 16, CL 6, SL 6 = 28 days, pro-rated in the joining/exit year).
- Yearly leave register (Jan–Dec grid like the Excel sheet), EL summary across years with encashment value and KRW. All exportable.
- Per-employee EL ledger: computed yearly, with manual override per year.

**OT Management**: plant-wise monthly grid (2nd shift, 3rd shift, normal OT, Spl OT, remarks, leave days) with live totals. Save as pending or save & approve; only **approved** OT is paid. Plant-wise report with summary and shift-allowance value (print / CSV). Multi-month OT history.

**Payslip Management**
- Monthly payroll run → generates every payslip from master data, approved OT and leave.
- Edit any payslip before finalizing (advance, misc deduction, Spl OT amount, birthday welfare, OT amount override, one-off allowances). *Recalculate* refreshes from source data and keeps these manual entries.
- Finalize locks the month (payslips, leave and OT). Only a Super Admin can re-open it.
- Salary register CSV (Excel column layout), payslip view/print/PDF, all payslips of a month in one PDF.
- **EL & Gratuity settlement**: year-wise EL rows (editable), per-day rate, gratuity, bonus, other, deductions, amount in words; PDF.

## Confirmations

Every action that changes data asks for confirmation in a pop-up. Critical steps use **two-step confirmation**: you confirm, then tick "I have checked the details" and confirm again. These steps are generating, recalculating, finalizing, re-opening or deleting a payroll; emailing payslips; approving all OT; closing the leave year; saving a settlement; and every Excel import. A progress overlay prevents double submission while long actions such as emailing run.

## Monthly routine (payslips between the 1st and 10th)

Open **Monthly Workflow**. It always opens the month you are working on: the previous month until the 10th, then the current month. The deadline day can be changed in Settings. Each step shows a live status:

1. **Employees**: new joiners and exits for the month, and anyone missing a basic salary.
2. **Leave**: pending requests must be approved; dated requests or monthly counts.
3. **OT**: enter in the grid or **Import Data → OT sheet** (your monthly Excel). Approve.
4. **Generate payslips**: one click. If OT, leave or employee data changes later, the step turns amber with a *Recalculate* button.
5. **Review & finalize**: edit advances, birthday welfare and so on, then finalize. This locks the month.
6. **Email payslips**: *Email all unsent* sends each employee their PDF payslip. Status is tracked per payslip (sent, failed, no email) and you can resend individually.

The dashboard shows the same steps for the current cycle with the days left to the deadline.

### Email setup
Super Admin → **Settings → Email (SMTP)**: host, port, TLS/SSL, username and password (stored encrypted), from address, and the subject and message with placeholders `{name} {month} {amount} {company}`. Use *Send test email* to check. Until SMTP is set, emails are written to `storage/logs/laravel.log` instead of being sent. Each employee needs an email address on their record; use the Employees filter *Missing email*.

## Loading data (2026 and later) — Import Data

| What | File | Notes |
|---|---|---|
| OT for a month | your OT workbook (sheet per month) or the OT CSV template | The matching month sheet ("Jan 2026") is picked automatically; switch sheets in the preview |
| Leave for a year | your "El Calculation" workbook (sheet per year) or the leave CSV template | Jan–Dec counts are saved as monthly leave entries; re-import replaces them |
| Employees | the Employees export | New Emp IDs are created and existing ones updated (joiners, exits, increments, emails) |

Templates are downloaded as formatted **.xlsx** files. They come pre-filled with your employees and include drop-down lists, number range checks and an *Instructions* sheet. Uploads accept .xlsx, .xls and .csv.

Each import is checked row by row before anything is saved: non-numeric or out-of-range values, impossible leave days, duplicate employees, unknown employees, people not on the rolls that month, and unusually high OT. Rows with errors cannot be imported. You then tick **"I have verified the preview"**, and the import asks for confirmation twice.

Every import shows a preview first. Names are matched by Emp ID, exact name, saved alias or a close spelling. Unmatched rows can be mapped by hand and the mapping is remembered for next month. Finalized months are never changed by an import.

## Daily database backup

- **Automatic every day.** The Windows scheduled task **"Payslip DB Backup"** runs `php artisan backup:database --if-due` at 11:00 PM and at Windows sign-in. A run missed while the PC was off starts as soon as the PC is on again. If the task is removed or moved to a new PC, reinstall it with `powershell -ExecutionPolicy Bypass -File scripts\install-backup-task.ps1` (options: `-Time 22:00`, `-Php C:\path\php.exe`).
- **Safety net.** If no backup succeeded in the last 24 hours, the app makes one in the background the next time someone signs in and uses it. It tries at most once per hour.
- **Daily reminder (default 4:00 PM, changeable).** A pop-up asks Super Admins to take today's backup. Clicking **"Yes, back up & download ZIP"** creates the backup and downloads it to your computer. You can also snooze it for 30 minutes or skip the day. It doesn't appear if a backup was already confirmed after the reminder time today. Change the time or turn it off under Super Admin → **Database Backups**.
- **Manual button.** The green **Backup** button in the top bar (Super Admin) opens the same dialog at any time.
- **Where.** Backups are saved to `storage/app/backups/db-backup-<database>-<date>_<time>.zip`, a ZIP package with the full MySQL dump (`.sql`) and a README of restore steps. Older backups may be `.sql.gz`. This folder is blocked from the web. Files older than the retention period (default 30 days) are deleted, but the newest 3 are always kept.
- **Second copy.** Super Admin → **Database Backups** → *Also copy each backup to*. Point it at another drive or a OneDrive / Google Drive folder, so a disk failure doesn't lose everything.
- **Database Backups page** (Super Admin). Shows status, the next scheduled run, and the backup list with *Back up now*, *Download* and *Delete*. The dashboard shows a red warning when the last backup is older than 26 hours or failed.
- **Manual backup from the command line:** `php artisan backup:database`.

### Restore (replaces ALL current data)
1. Make a fresh backup first (*Back up now*), so you can go back.
2. Stop using the app. Then in a terminal:
   ```
   C:\xampp\mysql\bin\mysql -u root -e "DROP DATABASE payslip_management; CREATE DATABASE payslip_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   C:\xampp\mysql\bin\mysql -u root payslip_management < <file>.sql
   ```
   Extract `<file>.sql` from the backup ZIP first (right-click → Extract All). For an older `.sql.gz` backup, unzip it with 7-Zip.
3. Run `php artisan cache:clear`.

## Year end (e.g. moving into 2027)

Nothing needs to be set up for a new year. Leave entitlement (28 days) starts fresh on 1 January, year lists grow automatically, and payroll works for any month.

On the December workflow page, **Close leave year** saves each employee's EL credit for the year in the EL ledger. For the yearly increment, add an allowance type such as "Increment 2027" in Settings, then fill the amounts via Employees → Export → edit in Excel → Import.

## Calculation rules (all rates editable in Settings)

| Item | Rule |
|---|---|
| Per day | Basic ÷ 26 working days |
| Basic earned | Per day × present days |
| 2nd / 3rd shift | hours × ₹5 / ₹6.875 |
| OT pay | Per day ÷ 8 × multiplier (1.5; driver 1.2) × (normal + Spl OT hours) |
| Gross | Basic earned + shifts + OT + Spl OT amount + manager special + factory special |
| PF | 12 % of basic earned, max ₹1,800 |
| ESI | 0.75 % of gross (when applicable) |
| Net | Gross − (PF + ESI + TAX + advance + misc) |
| Attendance bonus | One day's pay when absent days = 0 |
| Total payable | Net + allowances, rounded to the rupee |
| LOP | Paid leave beyond the yearly 28-day pool, plus LOP-type leave, reduces present days |
| EL credit / year | min(16, 28 − leave used), pro-rated in the joining year |
| EL encashment | Σ EL days × (Basic ÷ 26) |
| Gratuity | Basic ÷ 26 × 15 × years of service |

Generated against the Nov 2025 sheet, 30 of 36 payslips match to the rupee. The other 6 hold one-off manual entries in Excel (birthday welfare, Spl OT ₹486, a capped OT pay, manually removed attendance bonus). Enter those through *Edit payslip*.
