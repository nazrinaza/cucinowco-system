# Google Calendar integration

## What the implementation uses

- `Booking` is the source of truth for confirmed cleaning work. The existing states are `pending`, `confirmed`, `in_progress`, `completed`, and `cancelled`.
- `SiteVisitRequest` is the source of truth for assessment appointments. Its existing confirmed state is `scheduled`. The integration adds an exact confirmed start, end, and assigned staff record because the original public form only stored a preferred date and broad time slot.
- A booking carries the actual schedule (`scheduled_start`, `scheduled_end`), service address, service, customer, and assigned `Staff` record.
- Only `confirmed`, `in_progress`, and `completed` bookings with a start time require a job event. Only `scheduled` or `completed` site visits with an exact confirmed start and end require an appointment event. Pending/new/contacted records never create an event. Cancelling or reverting a previously synced record removes its event.
- CuciNow remains authoritative. Google Calendar changes are not imported.
- Calendar work runs on the existing Laravel database queue after the booking database write commits. Google failures are recorded separately and never roll back the booking.

Events contain the reference, customer name, service or assessment scope, operational location, start/end time, and assigned team. Customer email, phone number, pricing, access notes, and other unnecessary personal or commercial information are intentionally excluded.

## Google Cloud configuration

1. In [Google Cloud Console](https://console.cloud.google.com/), create or select the company project.
2. Open **APIs & Services → Library**, find **Google Calendar API**, and enable it.
3. Configure the OAuth consent screen. If the app is in testing mode, add the company Google account as a test user.
4. Create an OAuth client with application type **Web application**.
5. Add this exact production authorised redirect URI:

   ```text
   https://cucinow.co/admin/integrations/google-calendar/callback
   ```

   For staging tests, create a separate OAuth client or add this exact URI too:

   ```text
   https://staging.cucinow.co/admin/integrations/google-calendar/callback
   ```

6. In Google Calendar, create or choose the single shared company calendar. Under **Settings and sharing → Integrate calendar**, copy its Calendar ID. The Google account connected in CuciNow must own the calendar or have permission to make changes to events.

Google requires the redirect URI to match exactly, including scheme, hostname, path, and trailing slash. The server flow requests offline access so queue workers can refresh expired access tokens without an administrator being present. See Google's [OAuth web-server guide](https://developers.google.com/identity/protocols/oauth2/web-server) and [Calendar event API](https://developers.google.com/workspace/calendar/api/v3/reference/events/insert).

## Production environment variables

Add these only to the server `.env`. Never add real values to Git, frontend JavaScript, screenshots, logs, or chat.

```dotenv
GOOGLE_CALENDAR_CLIENT_ID="your-web-oauth-client-id"
GOOGLE_CALENDAR_CLIENT_SECRET="your-web-oauth-client-secret"
GOOGLE_CALENDAR_ID="company-calendar-id@group.calendar.google.com"
GOOGLE_CALENDAR_REDIRECT_URI="https://cucinow.co/admin/integrations/google-calendar/callback"
GOOGLE_CALENDAR_QUEUE=default
GOOGLE_CALENDAR_DEFAULT_DURATION_MINUTES=120
```

The default duration is used only for legacy bookings that have a start time but no end time. New operational bookings should continue to record both times.

OAuth access and refresh tokens are encrypted in the database with Laravel's `APP_KEY` and hidden from model serialization. Back up `APP_KEY`; changing it invalidates existing encrypted tokens and requires reconnecting Google.

After changing `.env`, clear and rebuild Laravel's cached configuration:

```bash
/opt/alt/php83/usr/bin/php artisan optimize:clear
/opt/alt/php83/usr/bin/php artisan optimize
```

## Database and deployment

The integration adds `google_calendar_connections`, `booking_calendar_syncs`, and `site_visit_calendar_syncs`, plus exact schedule/team fields for site visits. Do not run this against production until the deployment is approved. The repository's cPanel deployment file currently runs migrations automatically during **Deploy HEAD Commit**, so review the migration and take a database backup first.

Manual migration command, only after approval:

```bash
/opt/alt/php83/usr/bin/php artisan migrate --force
```

No real event is created by the migration or by connecting OAuth. Existing upcoming bookings are queued only when an administrator clicks **Sync upcoming confirmed bookings**.

## cPanel queue worker

The existing scheduler already drains the `default` and `newsletters` queues. Google Calendar jobs use `default`, so no second worker is required. Keep this cPanel cron running every minute:

```cron
* * * * * /opt/alt/php83/usr/bin/php /home2/shahjaha/cucinowco-system/artisan schedule:run >> /dev/null 2>&1
```

The scheduled worker command is intentionally short-lived for shared hosting:

```text
queue:work database --queue=default,newsletters --stop-when-empty --max-time=50 --max-jobs=25 --tries=3
```

Each Calendar job additionally defines five attempts and backoff intervals of 60, 300, 900, and 1,800 seconds.

## Admin operation

1. Sign in as a user with role `admin`.
2. Open **Admin → Google Calendar**.
3. Click **Connect Google Calendar** and sign in with the authorised company Google account.
4. Confirm the account and Calendar ID displayed in the portal.
5. Enter the exact confirmed start, end, and assigned team when changing a site visit to `scheduled`.
6. Click **Sync upcoming appointments & jobs** once to backfill future appointments and jobs.
7. Use the record-level **Retry sync** action or the recent sync table if a job fails.

Only the `admin` role can connect, disconnect, bulk sync, or manually retry. Office and field roles cannot access these actions.

## Idempotency and recovery

- Every booking has one unique sync record and one deterministic Google event ID using Google-compatible base32hex characters.
- Unique queued jobs, a distributed cache lock, a unique database constraint, payload fingerprints, and Google `409` recovery prevent duplicate events across retries and concurrent updates.
- A missing event during an update is recreated with the same ID. A missing event during deletion is treated as already deleted.
- Disconnecting removes local tokens and attempts to revoke the Google grant. Existing events remain in Google Calendar. Reconnect, then retry or bulk sync to resume.
- Sync errors are shortened before storage and never include OAuth credentials.

## Verification

The feature test suite mocks Google HTTP endpoints and covers creation, pending exclusion, updates, cancellation, retry/backoff, duplicate prevention, permissions, queue dispatch, data minimisation, and the `Asia/Kuala_Lumpur` timezone.

```bash
php artisan test tests/Feature/GoogleCalendarIntegrationTest.php
php artisan test
```
