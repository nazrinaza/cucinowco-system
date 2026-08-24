# CuciNow.co cPanel deployment

This release path is designed for shared hosting with no Terminal or SSH access. GitHub builds the PHP dependencies and browser assets into a `deploy` branch; cPanel only pulls that ready-to-run branch.

## 1. Prepare the database

In **MySQL Databases**, create a database and database user, add the user with all privileges, and keep the full cPanel-prefixed names for the `.env` file. Use `utf8mb4` with `utf8mb4_unicode_ci` where offered.

## 2. Connect the release branch

In **Git Version Control**:

1. Clone `https://github.com/nazrinaza/cucinowco-system.git`.
2. Use `/home/YOUR_CPANEL_USER/cucinowco-system` as the checkout path.
3. Select the `deploy` branch after the first GitHub build completes.

In **Domains**, set the document root to `/home/YOUR_CPANEL_USER/cucinowco-system/public`. Laravel's `public` directory is the only web-safe document root.

## 3. Create the server environment file

Using **File Manager**, copy `.env.example` to `.env` in the application root. Update at least:

- `APP_ENV=production`, `APP_DEBUG=false`, and `APP_URL=https://cucinow.co`
- `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`
- `ADMIN_EMAIL` and a strong, unique `ADMIN_PASSWORD`
- company phone, email, WhatsApp, address, and outgoing mail settings

Leave `APP_KEY=` blank only for the first deployment. The deployment task generates it once. Never delete or rotate the key after the site contains live data.

Keep `SST_ENABLED=false` until Thursina's current Service Tax registration is confirmed. If registered for taxable cleaning services, set `SST_ENABLED=true`; the configured current rate is 8%.

## 4. Deploy

Open the repository in cPanel, choose **Update from Remote**, then **Deploy HEAD Commit**. The deployment tasks apply database changes, create the admin account, prepare storage, and optimize the app.

## 5. Add the cron job

In **Cron Jobs**, run this every minute after replacing the username:

`/usr/local/bin/ea-php83 /home/YOUR_CPANEL_USER/cucinowco-system/artisan schedule:run >> /dev/null 2>&1`

This processes queued emails in short, shared-hosting-safe batches and marks overdue invoices daily.

## 6. Verify

- Open `https://cucinow.co/up`.
- Submit a test quote from the landing page.
- Sign in at `https://cucinow.co/admin/login`.
- Confirm the quote appears, then create a booking and invoice.
- Record a RM1 test payment entry before connecting a live payment gateway.

Payment gateways and email delivery remain disabled until production credentials are added. Never commit credentials or `.env` to GitHub.
